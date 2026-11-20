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
 * Tests for the backup download task.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use advanced_testcase;
use core\task\manager;
use local_coursetransfer\coursetransfer_download;
use local_coursetransfer\coursetransfer_request;
use stdClass;

/**
 * Tests for download_file_course_task.
 *
 * The download goes through Moodle's curl mock, so no request leaves the test. The mock can only
 * answer the transfer itself (the file on disk stays empty), so the failure paths are covered;
 * the valid MBZ path is covered end to end by coursetransfer_restore_course_test.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\task\download_file_course_task
 * @covers     \local_coursetransfer\coursetransfer_download
 */
final class download_file_course_task_test extends advanced_testcase {
    /**
     * Load the test double.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        require_once(__DIR__ . '/../fixtures/download_file_course_task_fake.php');
    }

    /**
     * Act as admin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * No mocked response may be left behind (it would leak into another test).
     */
    protected function tearDown(): void {
        $property = new \ReflectionProperty(\curl::class, 'mockresponses');
        $left = $property->getValue();
        $property->setValue(null, []);
        parent::tearDown();
        $this->assertSame([], $left, 'Every mocked remote response must be consumed');
    }

    /**
     * Queue the download of a backup for a new request and return the request.
     *
     * @return stdClass
     */
    private function queue_download(): stdClass {
        $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')
            ->create_site('origin', ['host' => 'https://origin.example.com']);
        $course = $this->getDataGenerator()->create_course();
        $request = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request([
            'status' => coursetransfer_request::STATUS_BACKUP,
            'target_course_id' => $course->id,
            'origin_course_id' => 11,
        ]);
        $this->assertTrue(coursetransfer_download::create_task_download_course($request, 'https://origin.example.com/backup.mbz'));
        return $request;
    }

    /**
     * Run the queued download tasks.
     */
    private function run_tasks(): void {
        foreach (manager::get_adhoc_tasks(download_file_course_task::class) as $task) {
            ob_start();
            $task->execute();
            ob_end_clean();
        }
    }

    /**
     * The task is queued with the file URL and the request.
     */
    public function test_create_task_download_course(): void {
        $request = $this->queue_download();

        $tasks = manager::get_adhoc_tasks(download_file_course_task::class);
        $this->assertCount(1, $tasks);
        $data = reset($tasks)->get_custom_data();
        $this->assertEquals($request->id, $data->requestid);
        $this->assertSame('https://origin.example.com/backup.mbz', $data->fileurl);
    }

    /**
     * A failed transfer marks the request as failed with 13001 and queues no restore.
     */
    public function test_download_failed(): void {
        $request = $this->queue_download();
        \curl::mock_response('Could not resolve host');

        $this->run_tasks();

        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertSame('13001', $request->error_code);
        $this->assertStringContainsString('Could not resolve host', $request->error_message);
        $this->assertCount(0, manager::get_adhoc_tasks(restore_course_task::class));
    }

    /**
     * A body that is not an MBZ marks the request as failed with 13003 and queues no restore.
     */
    public function test_download_not_mbz(): void {
        $request = $this->queue_download();
        \curl::mock_response(true);

        $this->run_tasks();

        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertSame('13003', $request->error_code);
        $this->assertCount(0, manager::get_adhoc_tasks(restore_course_task::class));
    }

    /**
     * A request with a registered origin, waiting for its download.
     *
     * @param array $record
     * @return stdClass
     */
    private function backup_request(array $record = []): stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $generator->create_site('origin', ['host' => 'https://origin.example.com', 'token' => 'origintoken123']);
        $course = $this->getDataGenerator()->create_course();
        return $generator->create_request($record + [
            'status' => coursetransfer_request::STATUS_BACKUP,
            'target_course_id' => $course->id,
            'origin_course_id' => 11,
        ]);
    }

    /**
     * Run a download with a fake transfer that writes the given body.
     *
     * @param stdClass $request
     * @param string $fileurl URL stored in the task.
     * @param string $body
     */
    private function run_fake(stdClass $request, string $fileurl, string $body): void {
        download_file_course_task_fake::$calls = [];
        download_file_course_task_fake::$body = $body;
        $task = new download_file_course_task_fake();
        $task->set_custom_data(['requestid' => (int) $request->id, 'fileurl' => $fileurl]);
        // Guard: if the transfer ever reached the real cURL client, it gets this answer instead of the network.
        \curl::mock_response(true);
        ob_start();
        $task->execute();
        ob_end_clean();
        (new \ReflectionProperty(\curl::class, 'mockresponses'))->setValue(null, []);
    }

    /**
     * The origin's token is neither stored in the request nor in the task (LCT-011).
     */
    public function test_callback_keeps_token_at_rest(): void {
        $request = $this->backup_request(['status' => coursetransfer_request::STATUS_IN_PROGRESS]);
        $url = 'https://origin.example.com/webservice/pluginfile.php/5/local_coursetransfer/backup/9/backup.mbz';

        \local_coursetransfer\external\backend\target_course_callback_external::target_backup_course_completed(
            'username',
            get_admin()->username,
            (int) $request->id,
            100,
            $url
        );

        $request = coursetransfer_request::get($request->id);
        $this->assertSame($url, $request->origin_backup_url);
        $this->assertStringNotContainsString('origintoken123', (string) $request->fileurl);
        $tasks = manager::get_adhoc_tasks(download_file_course_task::class);
        $this->assertCount(1, $tasks, json_encode(array_map(fn($t) => $t->get_custom_data(), $tasks)));
        $this->assertSame($url, reset($tasks)->get_custom_data()->fileurl);
    }

    /**
     * The download sends the token of the registered origin apart from the URL, and queues one restore.
     */
    public function test_download_resolves_token(): void {
        $request = $this->backup_request(['origin_backup_size' => strlen(gzencode('valid backup'))]);
        $url = 'https://origin.example.com/webservice/pluginfile.php/5/local_coursetransfer/backup/9/backup.mbz';

        $this->run_fake($request, $url, gzencode('valid backup'));

        $this->assertSame([[$url, 'origintoken123']], download_file_course_task_fake::$calls);
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_DOWNLOADED, $request->status);
        $this->assertCount(1, manager::get_adhoc_tasks(restore_course_task::class));
        $this->assertFileDoesNotExist(download_file_course_task_fake::$tmpfile, 'The temporary file is removed');
    }

    /**
     * Tasks queued by 2.1 carry the token in the URL: it is moved out of the URL and of the request.
     */
    public function test_download_legacy_url(): void {
        $url = 'https://origin.example.com/webservice/pluginfile.php/5/local_coursetransfer/backup/9/backup.mbz';
        $request = $this->backup_request(['fileurl' => $url . '?token=oldtoken']);

        $this->run_fake($request, $url . '?token=oldtoken', gzencode('valid backup'));

        $this->assertSame([[$url, 'origintoken123']], download_file_course_task_fake::$calls);
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_DOWNLOADED, $request->status);
        $this->assertStringNotContainsString('oldtoken', (string) $request->fileurl);
    }

    /**
     * A request that is no longer waiting for its download is left alone (no second file, no second restore).
     */
    public function test_download_is_idempotent(): void {
        $request = $this->backup_request(['status' => coursetransfer_request::STATUS_RESTORE]);

        $this->run_fake($request, 'https://origin.example.com/webservice/pluginfile.php/5/x/backup.mbz', gzencode('x'));

        $this->assertSame([], download_file_course_task_fake::$calls);
        $this->assertEquals(coursetransfer_request::STATUS_RESTORE, coursetransfer_request::get($request->id)->status);
        $this->assertCount(0, manager::get_adhoc_tasks(restore_course_task::class));
    }

    /**
     * A file whose size differs from the one announced by the origin is rejected.
     */
    public function test_download_size_mismatch(): void {
        $request = $this->backup_request(['origin_backup_size' => 999999]);

        $this->run_fake($request, 'https://origin.example.com/webservice/pluginfile.php/5/x/backup.mbz', gzencode('x'));

        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertSame('13004', $request->error_code);
        $this->assertCount(0, manager::get_adhoc_tasks(restore_course_task::class));
    }

    /**
     * The target's own size limit applies to the download.
     */
    public function test_download_too_large(): void {
        set_config('target_restore_course_max_size', 1, 'local_coursetransfer');
        $request = $this->backup_request();

        $this->run_fake(
            $request,
            'https://origin.example.com/webservice/pluginfile.php/5/x/backup.mbz',
            "\x1f\x8b" . str_repeat('x', 1000001)
        );

        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertSame('13005', $request->error_code);
        $this->assertSame(1000000, download_file_course_task_fake::$maxbytes);
    }

    /**
     * The transfer verifies the origin certificate and limits size and stalls.
     */
    public function test_curl_options(): void {
        $options = (new download_file_course_task())->get_curl_options(500);
        $this->assertSame(1, $options['CURLOPT_SSL_VERIFYPEER']);
        $this->assertSame(2, $options['CURLOPT_SSL_VERIFYHOST']);
        $this->assertSame(0, $options['CURLOPT_FOLLOWLOCATION']);
        $this->assertSame(500, $options['CURLOPT_MAXFILESIZE']);
        $this->assertArrayHasKey('CURLOPT_LOW_SPEED_TIME', $options);

        // Turned off by the admin (sslverifypeer), at their own risk.
        set_config('sslverifypeer', 0, 'local_coursetransfer');
        $options = (new download_file_course_task())->get_curl_options(500);
        $this->assertSame(0, $options['CURLOPT_SSL_VERIFYPEER']);
        $this->assertSame(0, $options['CURLOPT_SSL_VERIFYHOST']);
        $this->assertSame(0, $options['CURLOPT_FOLLOWLOCATION'], 'Never a redirect elsewhere');
    }
}
