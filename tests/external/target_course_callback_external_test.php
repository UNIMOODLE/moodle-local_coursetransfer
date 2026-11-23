<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Contract tests for the status callbacks the origin sends to the target.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external;

use advanced_testcase;
use core\task\manager;
use core_external\external_api;
use core_external\external_description;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\event\request_completed;
use local_coursetransfer\external\backend\target_course_callback_external;
use local_coursetransfer\task\download_file_course_task;
use stdClass;

/**
 * Contract tests for target_backup_course_completed, target_backup_course_error,
 * target_remove_course_completed and target_remove_course_error, and the status transitions
 * each one accepts.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\backend\target_course_callback_external
 */
final class target_course_callback_external_test extends advanced_testcase {
    /**
     * Act as admin; the callbacks authenticate the user given in field/value, who needs a course.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->create_course();
    }

    /**
     * Return description of a method.
     *
     * Its errors structure passes PARAM_TEXT and a label as description and $required, which
     * raises a developer debugging() message when the description is built (plugin bug, reported
     * in TIPCOUTRAN-10). It is discarded here so the contract itself can be checked.
     *
     * @param string $method
     * @return external_description
     */
    private function returns(string $method): external_description {
        $description = call_user_func([target_course_callback_external::class, $method . '_returns']);
        $this->resetDebugging();
        return $description;
    }

    /**
     * A request waiting on this (target) site.
     *
     * @param int $type
     * @param int $status
     * @param int $direction
     * @return stdClass
     */
    private function create_request(
        int $type = coursetransfer_request::TYPE_COURSE,
        int $status = coursetransfer_request::STATUS_IN_PROGRESS,
        int $direction = coursetransfer_request::DIRECTION_REQUEST
    ): stdClass {
        return $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request([
            'type' => $type,
            'status' => $status,
            'direction' => $direction,
            'origin_course_id' => 5,
        ]);
    }

    /** @var string Backup URL as the origin builds it (make_webservice_pluginfile_url). */
    private const BACKUP_URL = 'https://origin.example.com/webservice/pluginfile.php/1/local_coursetransfer/backup/0/backup.mbz';

    /**
     * Call target_backup_course_completed and clean its return value.
     *
     * @param int $requestid
     * @param string $fileurl
     * @return array
     */
    private function backup_completed(int $requestid, string $fileurl = self::BACKUP_URL): array {
        $res = target_course_callback_external::target_backup_course_completed(
            'username',
            'admin',
            $requestid,
            2048,
            $fileurl
        );
        return external_api::clean_returnvalue($this->returns('target_backup_course_completed'), $res);
    }

    /**
     * The backup completed callback records the backup and queues one download; a repeated call
     * answers success without queuing a second one (LCT-051).
     */
    public function test_target_backup_course_completed(): void {
        $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')
            ->create_site('origin', ['host' => 'https://origin.example.com']);
        $request = $this->create_request();

        $res = $this->backup_completed((int) $request->id);
        $this->assertTrue($res['success']);
        $this->assertSame((int) $request->id, $res['data']['id']);
        $stored = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_BACKUP, $stored->status);
        $this->assertEquals(2048, $stored->origin_backup_size);
        $this->assertSame(self::BACKUP_URL, $stored->origin_backup_url);
        // The origin's token is not stored with the URL (LCT-011): the download task resolves it.
        $this->assertSame(self::BACKUP_URL, $stored->fileurl);
        $this->assertCount(1, manager::get_adhoc_tasks(download_file_course_task::class));

        $res = $this->backup_completed((int) $request->id);
        $this->assertTrue($res['success']);
        $this->assertSame((int) $request->id, $res['data']['id']);
        $this->assertCount(1, manager::get_adhoc_tasks(download_file_course_task::class));
        $this->assertEquals($stored, coursetransfer_request::get($request->id));
    }

    /**
     * The token never leaves for another host: a URL with the path of a backup of this plugin is rebuilt on the
     * registered host of the origin (an alias of the origin keeps working); anything else is rejected with 10303,
     * without touching the request or queuing a download.
     */
    public function test_target_backup_course_completed_rejects_foreign_url(): void {
        $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')
            ->create_site('origin', ['host' => 'https://origin.example.com']);
        $path = '/webservice/pluginfile.php/1/local_coursetransfer/backup/0/backup.mbz';
        $rebuilt = [
            'other host' => 'https://attacker.example.net' . $path,
            'misleading suffix' => 'https://origin.example.com.attacker.example.net' . $path,
            'misleading prefix' => 'https://evilorigin.example.com' . $path,
            'other scheme' => 'http://origin.example.com' . $path,
            'other port' => 'https://origin.example.com:8443' . $path,
            'credentials' => 'https://user:pass@origin.example.com' . $path,
            'at sign' => 'https://origin.example.com@attacker.example.net' . $path,
            'alias in a subdirectory' => 'https://alias.example.com/moodle' . $path,
            'default port and case' => 'https://ORIGIN.example.com:443' . $path,
        ];
        foreach ($rebuilt as $case => $url) {
            $request = $this->create_request();
            $res = $this->backup_completed((int) $request->id, $url);
            $this->assertTrue($res['success'], $case);
            $stored = coursetransfer_request::get($request->id);
            $this->assertSame(self::BACKUP_URL, $stored->fileurl, $case);
            $this->assertSame(self::BACKUP_URL, $stored->origin_backup_url, $case);
        }
        foreach (manager::get_adhoc_tasks(download_file_course_task::class) as $task) {
            $this->assertSame(self::BACKUP_URL, $task->get_custom_data()->fileurl, 'The download only goes to the origin');
        }
        $this->assertCount(count($rebuilt), manager::get_adhoc_tasks(download_file_course_task::class));

        $request = $this->create_request();
        $rejected = [
            'not pluginfile' => 'https://origin.example.com/pluginfile.php/1/local_coursetransfer/backup/0/backup.mbz',
            'not webservice' => 'https://origin.example.com/login/index.php',
            'other component' => 'https://origin.example.com/webservice/pluginfile.php/1/user/private/0/x.mbz',
            'other file' => 'https://origin.example.com/webservice/pluginfile.php/1/local_coursetransfer/backup/0/x.php',
            'query' => 'https://origin.example.com' . $path . '?forcedownload=1',
            'fragment' => 'https://origin.example.com' . $path . '#x',
            'dot segments' => 'https://origin.example.com/webservice/pluginfile.php/../../login/index.php',
            'dot segments in the file path' => 'https://origin.example.com/webservice/pluginfile.php/1/local_coursetransfer/'
                . 'backup/0/../backup.mbz',
            'encoded dot segments' => 'https://origin.example.com/webservice/pluginfile.php/%2e%2e/%2E%2E/login/index.php',
            'not a url' => 'origin.example.com' . $path,
            'other protocol' => 'ftp://origin.example.com' . $path,
            'empty' => '',
        ];
        foreach ($rejected as $case => $url) {
            $res = $this->backup_completed((int) $request->id, $url);
            $this->assertFalse($res['success'], $case);
            $this->assertSame('10303', $res['errors'][0]['code'], $case);
            $this->assertEquals($request, coursetransfer_request::get($request->id), $case);
        }
        $this->assertCount(count($rebuilt), manager::get_adhoc_tasks(download_file_course_task::class));
    }

    /**
     * An origin installed in a subdirectory: the URL is rebuilt under its registered address.
     */
    public function test_target_backup_course_completed_subdirectory_origin(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $generator->create_site('origin', ['host' => 'https://origin.example.com/moodle/']);
        $path = '/webservice/pluginfile.php/1/local_coursetransfer/backup/0/backup.mbz';

        foreach (['https://origin.example.com' . $path, 'https://origin.example.com/moodle' . $path] as $url) {
            $request = $generator->create_request([
                'siteurl' => 'https://origin.example.com/moodle/',
                'status' => coursetransfer_request::STATUS_IN_PROGRESS,
            ]);
            $res = $this->backup_completed((int) $request->id, $url);
            $this->assertTrue($res['success']);
            $this->assertSame('https://origin.example.com/moodle' . $path, coursetransfer_request::get($request->id)->fileurl);
        }
    }

    /**
     * Once the download is under way or done, the callback is a no-op that answers success.
     */
    public function test_target_backup_course_completed_duplicate(): void {
        $statuses = [
            coursetransfer_request::STATUS_BACKUP,
            coursetransfer_request::STATUS_DOWNLOAD,
            coursetransfer_request::STATUS_DOWNLOADED,
            coursetransfer_request::STATUS_RESTORE,
            coursetransfer_request::STATUS_COMPLETED,
        ];
        foreach ($statuses as $status) {
            $request = $this->create_request(coursetransfer_request::TYPE_COURSE, $status);
            $res = $this->backup_completed((int) $request->id);
            $this->assertTrue($res['success'], "status $status");
            $this->assertEquals($request, coursetransfer_request::get($request->id), "status $status");
        }
        $this->assertCount(0, manager::get_adhoc_tasks(download_file_course_task::class));
    }

    /**
     * A request in error (given up or relaunched), of another type or made by another site
     * rejects the callback with 10302 and is left as it was.
     */
    public function test_target_backup_course_completed_rejected_transition(): void {
        $requests = [
            $this->create_request(coursetransfer_request::TYPE_COURSE, coursetransfer_request::STATUS_ERROR),
            $this->create_request(coursetransfer_request::TYPE_CATEGORY),
            $this->create_request(coursetransfer_request::TYPE_REMOVE_COURSE),
            $this->create_request(
                coursetransfer_request::TYPE_COURSE,
                coursetransfer_request::STATUS_IN_PROGRESS,
                coursetransfer_request::DIRECTION_RESPONSE
            ),
        ];
        foreach ($requests as $request) {
            $res = $this->backup_completed((int) $request->id);
            $this->assertFalse($res['success']);
            $this->assertSame('10302', $res['errors'][0]['code']);
            $this->assertEquals($request, coursetransfer_request::get($request->id));
        }
        $this->assertCount(0, manager::get_adhoc_tasks(download_file_course_task::class));
    }

    /**
     * A repeated backup error keeps the first error; an error after the backup completed is rejected.
     */
    public function test_target_backup_course_error_transitions(): void {
        $request = $this->create_request();
        target_course_callback_external::target_backup_course_error('username', 'admin', (int) $request->id, 0, 1, 'First');

        $res = target_course_callback_external::target_backup_course_error(
            'username',
            'admin',
            (int) $request->id,
            0,
            2,
            'Second'
        );
        $res = external_api::clean_returnvalue($this->returns('target_backup_course_error'), $res);
        $this->assertTrue($res['success']);
        $stored = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $stored->status);
        $this->assertEquals(1, $stored->error_code);
        $this->assertSame('First', $stored->error_message);

        $request = $this->create_request(coursetransfer_request::TYPE_COURSE, coursetransfer_request::STATUS_DOWNLOAD);
        $res = target_course_callback_external::target_backup_course_error('username', 'admin', (int) $request->id, 0, 2, 'Late');
        $res = external_api::clean_returnvalue($this->returns('target_backup_course_error'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame(14003, $res['errors'][0]['code']);
        $this->assertEquals($request, coursetransfer_request::get($request->id));

        $request = $this->create_request(coursetransfer_request::TYPE_REMOVE_COURSE);
        $res = target_course_callback_external::target_backup_course_error('username', 'admin', (int) $request->id, 0, 2, 'x');
        $res = external_api::clean_returnvalue($this->returns('target_backup_course_error'), $res);
        $this->assertSame(14003, $res['errors'][0]['code']);
        $this->assertEquals($request, coursetransfer_request::get($request->id));
    }

    /**
     * A repeated remove completed does not notify nor fire the event again, an error already
     * recorded is kept (the origin has deleted anyway), and other request types are rejected.
     */
    public function test_target_remove_course_completed_transitions(): void {
        $request = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request([
            'type' => coursetransfer_request::TYPE_REMOVE_CATEGORY,
            'status' => coursetransfer_request::STATUS_IN_PROGRESS,
            'origin_category_id' => 6,
        ]);
        target_course_callback_external::target_remove_course_completed('username', 'admin', (int) $request->id);

        $messages = $this->redirectMessages();
        $events = $this->redirectEvents();
        $res = target_course_callback_external::target_remove_course_completed('username', 'admin', (int) $request->id);
        $res = external_api::clean_returnvalue($this->returns('target_remove_course_completed'), $res);
        $this->assertTrue($res['success']);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($request->id)->status);

        $this->assertCount(0, $messages->get_messages());
        $this->assertCount(0, array_filter($events->get_events(), fn($e) => $e instanceof request_completed));

        // Recorded as an error here but removed by the origin: it becomes completed, the error kept as a note (C3).
        $failed = $this->create_request(coursetransfer_request::TYPE_REMOVE_COURSE, coursetransfer_request::STATUS_ERROR);
        coursetransfer_request::update_fields((int) $failed->id, ['error_code' => '15000', 'error_message' => 'Timeout']);
        $res = target_course_callback_external::target_remove_course_completed('username', 'admin', (int) $failed->id);
        $res = external_api::clean_returnvalue($this->returns('target_remove_course_completed'), $res);
        $this->assertTrue($res['success']);
        $stored = coursetransfer_request::get($failed->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $stored->status);
        $this->assertEmpty($stored->error_code);
        $this->assertStringContainsString('15000 Timeout', $stored->error_message);
        $this->assertCount(1, $messages->get_messages());
        $this->assertCount(1, array_filter($events->get_events(), fn($e) => $e instanceof request_completed));

        $course = $this->create_request(coursetransfer_request::TYPE_COURSE);
        $res = target_course_callback_external::target_remove_course_completed('username', 'admin', (int) $course->id);
        $res = external_api::clean_returnvalue($this->returns('target_remove_course_completed'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('14023', $res['errors'][0]['code']);
        $this->assertEquals($course, coursetransfer_request::get($course->id));
    }

    /**
     * A repeated remove error keeps the first error; an error on a completed removal is rejected.
     */
    public function test_target_remove_course_error_transitions(): void {
        $request = $this->create_request(coursetransfer_request::TYPE_REMOVE_COURSE);
        target_course_callback_external::target_remove_course_error('username', 'admin', (int) $request->id, 15001, 'First');

        $res = target_course_callback_external::target_remove_course_error(
            'username',
            'admin',
            (int) $request->id,
            19001,
            'Second'
        );
        $res = external_api::clean_returnvalue($this->returns('target_remove_course_error'), $res);
        $this->assertTrue($res['success']);
        $stored = coursetransfer_request::get($request->id);
        $this->assertEquals(15001, $stored->error_code);
        $this->assertSame('First', $stored->error_message);

        $done = $this->create_request(coursetransfer_request::TYPE_REMOVE_COURSE, coursetransfer_request::STATUS_COMPLETED);
        $res = target_course_callback_external::target_remove_course_error('username', 'admin', (int) $done->id, 19001, 'Late');
        $res = external_api::clean_returnvalue($this->returns('target_remove_course_error'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame(14013, $res['errors'][0]['code']);
        $this->assertEquals($done, coursetransfer_request::get($done->id));
    }

    /**
     * The backup error callback marks the request as failed with the origin's code and message.
     */
    public function test_target_backup_course_error(): void {
        $request = $this->create_request();

        $res = target_course_callback_external::target_backup_course_error(
            'username',
            'admin',
            (int) $request->id,
            1234,
            12002,
            'Backup failed'
        );
        $res = external_api::clean_returnvalue($this->returns('target_backup_course_error'), $res);

        $this->assertTrue($res['success']);
        $this->assertSame((int) $request->id, $res['data']['id']);
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertEquals(12002, $request->error_code);
        $this->assertSame('Backup failed', $request->error_message);
        $this->assertEquals(1234, $request->origin_backup_size);
    }

    /**
     * Unknown users and unknown requests are rejected without touching any request.
     */
    public function test_target_backup_course_error_rejected(): void {
        $request = $this->create_request();

        $res = target_course_callback_external::target_backup_course_error('username', 'nobody', (int) $request->id, 0, 1, 'x');
        $res = external_api::clean_returnvalue($this->returns('target_backup_course_error'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame(17002, $res['errors'][0]['code']);

        $res = target_course_callback_external::target_backup_course_error('username', 'admin', 999999, 0, 1, 'x');
        $res = external_api::clean_returnvalue($this->returns('target_backup_course_error'), $res);
        $this->assertSame(14002, $res['errors'][0]['code']);

        $this->assertEquals(coursetransfer_request::STATUS_IN_PROGRESS, coursetransfer_request::get($request->id)->status);
    }

    /**
     * The remove completed callback closes the request, notifies its owner and fires request_completed.
     */
    public function test_target_remove_course_completed(): void {
        $request = $this->create_request(coursetransfer_request::TYPE_REMOVE_COURSE);
        $messages = $this->redirectMessages();
        $events = $this->redirectEvents();

        $res = target_course_callback_external::target_remove_course_completed('username', 'admin', (int) $request->id);
        $res = external_api::clean_returnvalue($this->returns('target_remove_course_completed'), $res);

        $this->assertTrue($res['success']);
        $this->assertSame((int) $request->id, $res['data']['id']);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($request->id)->status);
        $this->assertCount(1, $messages->get_messages());
        $completed = array_filter($events->get_events(), fn($e) => $e instanceof request_completed);
        $this->assertCount(1, $completed);
    }

    /**
     * Unknown users and unknown requests are rejected.
     */
    public function test_target_remove_course_completed_rejected(): void {
        $request = $this->create_request(coursetransfer_request::TYPE_REMOVE_COURSE);

        $res = target_course_callback_external::target_remove_course_completed('username', 'nobody', (int) $request->id);
        $res = external_api::clean_returnvalue($this->returns('target_remove_course_completed'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('17002', $res['errors'][0]['code']);

        $res = target_course_callback_external::target_remove_course_completed('username', 'admin', 999999);
        $res = external_api::clean_returnvalue($this->returns('target_remove_course_completed'), $res);
        $this->assertSame('14022', $res['errors'][0]['code']);

        $this->assertEquals(coursetransfer_request::STATUS_IN_PROGRESS, coursetransfer_request::get($request->id)->status);
    }

    /**
     * The remove error callback marks the request as failed with the origin's code and message.
     */
    public function test_target_remove_course_error(): void {
        $request = $this->create_request(coursetransfer_request::TYPE_REMOVE_COURSE);

        $res = target_course_callback_external::target_remove_course_error(
            'username',
            'admin',
            (int) $request->id,
            15001,
            'Course could not be deleted'
        );
        $res = external_api::clean_returnvalue($this->returns('target_remove_course_error'), $res);

        $this->assertTrue($res['success']);
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertEquals(15001, $request->error_code);
        $this->assertSame('Course could not be deleted', $request->error_message);
    }

    /**
     * Unknown users and unknown requests are rejected.
     */
    public function test_target_remove_course_error_rejected(): void {
        $res = target_course_callback_external::target_remove_course_error('username', 'nobody', 1, 1, 'x');
        $res = external_api::clean_returnvalue($this->returns('target_remove_course_error'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame(17002, $res['errors'][0]['code']);

        $res = target_course_callback_external::target_remove_course_error('username', 'admin', 999999, 1, 'x');
        $res = external_api::clean_returnvalue($this->returns('target_remove_course_error'), $res);
        $this->assertSame(14012, $res['errors'][0]['code']);
    }
}
