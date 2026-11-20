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
 * Tests for the target restore task: state, idempotency and the chained origin removal.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use advanced_testcase;
use backup;
use backup_controller;
use core\task\manager;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_restore;
use stdClass;
use stored_file;

/**
 * Tests for restore_course_task.
 *
 * Restores are real (a backup of a generated course); calls to the origin go through Moodle's curl mock.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @group      local_coursetransfer_slow
 * @covers     \local_coursetransfer\task\restore_course_task
 * @covers     \local_coursetransfer\coursetransfer_restore
 * @covers     \local_coursetransfer\coursetransfer_request::update_status_request_cat
 */
final class restore_course_task_test extends advanced_testcase {
    /**
     * Load the test double.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        require_once(__DIR__ . '/../fixtures/restore_course_task_probe.php');
    }

    /** @var string Origin host. */
    private const ORIGIN = 'https://origin.example.com';

    /** @var stdClass Course to back up. */
    private $origin;

    /** @var stdClass Manager who asks for the restore. */
    private $requester;

    /**
     * Origin site, a course with a file and a manager as requester; the cron runs as admin.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_site('origin', ['host' => self::ORIGIN]);
        $this->origin = $this->getDataGenerator()->create_course(['shortname' => 'ORIG']);
        $this->getDataGenerator()->create_module('resource', ['course' => $this->origin->id]);
        $this->requester = $this->getDataGenerator()->create_user();
        role_assign($this->manager_role(), $this->requester->id, \context_system::instance());
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
     * Manager role id.
     *
     * @return int
     */
    private function manager_role(): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
    }

    /**
     * Back up the origin course and store the .mbz as the download task does.
     *
     * @param int $targetcourseid
     * @param bool $dropfiles Remove the file contents from the backup (an incomplete restore).
     * @return stored_file
     */
    private function make_backup(int $targetcourseid, bool $dropfiles = false): stored_file {
        global $USER;
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $this->origin->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->execute_plan();
        $backup = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $packer = get_file_packer('application/vnd.moodle.backup');
        $dir = make_request_directory();
        $backup->extract_to_pathname($packer, $dir);
        $backup->delete();
        if ($dropfiles) {
            remove_dir($dir . '/files', true);
        }
        $files = [];
        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $files[$entry] = $dir . '/' . $entry;
        }
        $mbz = make_request_directory() . '/backup.mbz';
        $packer->archive_to_pathname($files, $mbz);

        return get_file_storage()->create_file_from_pathname([
            'contextid' => \context_course::instance($targetcourseid)->id,
            'component' => 'backup',
            'filearea' => 'course',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'local_coursetransfer_' . $this->origin->id . '_' . time() . '.mbz',
        ], $mbz);
    }

    /**
     * A downloaded course request of the requester.
     *
     * @param array $record
     * @return stdClass
     */
    private function create_request(array $record = []): stdClass {
        $target = $this->getDataGenerator()->create_course();
        return $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request($record + [
            'siteurl' => self::ORIGIN,
            'status' => coursetransfer_request::STATUS_DOWNLOADED,
            'target_course_id' => $target->id,
            'origin_course_id' => $this->origin->id,
            'origin_category_id' => $this->origin->category,
            'target_target' => backup::TARGET_EXISTING_DELETING,
            'origin_remove_course' => 1,
            'userid' => $this->requester->id,
        ]);
    }

    /**
     * Queue and run the restore of a request with a file.
     *
     * @param stdClass $request
     * @param stored_file|null $file
     */
    private function run_restore(stdClass $request, ?stored_file $file): void {
        global $DB;
        if ($file) {
            $this->assertTrue(coursetransfer_restore::create_task_restore_course($request, $file));
        } else {
            $task = new restore_course_task();
            $task->set_custom_data(['requestid' => (int) $request->id, 'fileid' => 999999]);
            manager::queue_adhoc_task($task);
        }
        foreach (manager::get_adhoc_tasks(restore_course_task::class) as $task) {
            ob_start();
            $task->execute();
            ob_end_clean();
            $DB->delete_records('task_adhoc', ['id' => $task->get_id()]);
        }
    }

    /**
     * Successful answer of the origin to a removal.
     */
    private function mock_remove_ok(): void {
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => [
            'course_fullname' => 'O', 'course_shortname' => 'ORIG', 'course_idnumber' => '',
            'course_category_id' => 1, 'course_category_name' => 'C', 'course_category_idnumber' => '',
            'requestid' => 1, 'request_origin_id' => 1,
        ]]));
    }

    /**
     * Answer of the origin to the course detail (the manifest), as it would describe the origin course.
     *
     * @param array $extra Activities the origin has on top of the backed up ones, modname => count.
     */
    private function mock_detail(array $extra = []): void {
        $sections = \local_coursetransfer\coursetransfer::get_sections_with_activities((int) $this->origin->id);
        foreach ($extra as $modname => $count) {
            for ($i = 0; $i < $count; $i++) {
                $sections[0]['activities'][] = ['cmid' => 0, 'name' => 'x', 'instance' => 0, 'modname' => $modname];
            }
        }
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => [
            'id' => $this->origin->id, 'fullname' => 'O', 'shortname' => 'ORIG', 'sections' => $sections,
        ]]));
    }

    /**
     * Pop a mocked response the code did not use (it must not call the origin).
     */
    private function assert_origin_not_called(): void {
        $property = new \ReflectionProperty(\curl::class, 'mockresponses');
        $this->assertCount(1, $property->getValue(), 'The origin must not be called');
        $property->setValue(null, []);
    }

    /**
     * Removal requests sent from this site.
     *
     * @param int $type
     * @return stdClass[]
     */
    private function removals(int $type): array {
        global $DB;
        return array_values($DB->get_records('local_coursetransfer_request', ['type' => $type]));
    }

    /**
     * A complete restore: completed, progress kept, .mbz deleted and origin removed as the requester.
     */
    public function test_complete_restore_removes_origin_as_requester(): void {
        $request = $this->create_request();
        $file = $this->make_backup($request->target_course_id);
        $this->mock_remove_ok();
        $this->mock_detail();
        $sink = $this->redirectEvents();

        $this->run_restore($request, $file);

        $events = [];
        foreach ($sink->get_events() as $event) {
            $events[get_class($event)] = $event;
        }
        $sink->close();
        $decided = $events[\local_coursetransfer\event\chained_removal_decided::class];
        $this->assertSame('allowed', $decided->other['result']);
        $this->assertSame('accepted', $events[\local_coursetransfer\event\remote_removal_requested::class]->other['result']);
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $request->status);
        $this->assertEmpty($request->error_code);
        $this->assertSame([], json_decode($request->origin_manifest, true)['issues']);
        $this->assertEquals(100, $request->restored, 'The progress written during the restore is kept (H7)');
        $this->assertFalse(get_file_storage()->get_file_by_id($file->get_id()), 'The downloaded .mbz is deleted');
        $copies = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_coursetransfer',
            'safetycopy',
            $request->id,
            'id',
            false
        );
        $this->assertCount(1, $copies, 'A copy is kept while the origin is removed');
        $removals = $this->removals(coursetransfer_request::TYPE_REMOVE_COURSE);
        $this->assertCount(1, $removals);
        $this->assertEquals($this->origin->id, $removals[0]->origin_course_id);
        $this->assertEquals($this->requester->id, $removals[0]->userid, 'The removal is sent as the requester');
    }

    /**
     * Without an origin removal the downloaded .mbz is deleted once restored (A8).
     */
    public function test_complete_restore_deletes_backup(): void {
        $request = $this->create_request(['origin_remove_course' => 0, 'target_target' => backup::TARGET_EXISTING_ADDING]);
        $file = $this->make_backup($request->target_course_id);

        $this->run_restore($request, $file);

        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($request->id)->status);
        $this->assertFalse(get_file_storage()->get_file_by_id($file->get_id()), 'The downloaded .mbz is deleted (merge too)');
        $this->assertCount(0, $this->safety_copies((int) $request->id), 'Nothing needs the safety copy');
    }

    /**
     * Files missing in the backup: completed with the issue recorded, and the origin is kept (LCT-013).
     */
    public function test_incomplete_restore_keeps_origin(): void {
        $request = $this->create_request();
        $file = $this->make_backup($request->target_course_id, true);
        $this->mock_remove_ok();

        $this->run_restore($request, $file);

        $this->assert_origin_not_called();
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $request->status);
        $this->assertEquals(coursetransfer_request::CODE_RESTORE_WITH_ISSUES, $request->error_code);
        $this->assertStringContainsString('file_missing_in_backup', $request->error_message);
        $this->assertCount(0, $this->removals(coursetransfer_request::TYPE_REMOVE_COURSE));
    }

    /**
     * The removal capability is checked on the requester, not on the cron user.
     */
    public function test_requester_without_capability_keeps_origin(): void {
        assign_capability(
            'local/coursetransfer:origin_remove_course',
            CAP_PROHIBIT,
            $this->manager_role(),
            \context_system::instance()->id,
            true
        );
        $request = $this->create_request();
        $file = $this->make_backup($request->target_course_id);
        $this->mock_remove_ok();

        $this->run_restore($request, $file);

        $this->assert_origin_not_called();
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $request->status);
        $this->assertCount(0, $this->removals(coursetransfer_request::TYPE_REMOVE_COURSE));
        $this->assertStringContainsString('origin_remove_course', $request->error_message);
    }

    /**
     * A failed removal on the origin is recorded on the restore request.
     */
    public function test_remote_removal_error_is_recorded(): void {
        $request = $this->create_request();
        $file = $this->make_backup($request->target_course_id);
        \curl::mock_response(json_encode([
            'success' => false,
            'errors' => [['code' => '16011', 'msg' => 'USER HAS NOT CAPABILITY']],
        ]));
        $this->mock_detail();

        $this->run_restore($request, $file);

        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $request->status);
        $this->assertStringContainsString('USER HAS NOT CAPABILITY', $request->error_message);
    }

    /**
     * The origin has activities the restored course lacks: the origin is kept and the request says what.
     */
    public function test_manifest_mismatch_keeps_origin(): void {
        $request = $this->create_request();
        $file = $this->make_backup($request->target_course_id);
        $this->mock_remove_ok();
        $this->mock_detail(['forum' => 2]);
        $sink = $this->redirectEvents();

        $this->run_restore($request, $file);

        $held = array_filter($sink->get_events(), fn($e) => $e instanceof \local_coursetransfer\event\chained_removal_decided);
        $sink->close();
        $this->assert_origin_not_called();
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $request->status);
        $this->assertEquals(coursetransfer_request::CODE_MANIFEST_MISMATCH, $request->error_code);
        $this->assertStringContainsString('mod_forum 0/2', $request->error_message);
        $this->assertCount(0, $this->removals(coursetransfer_request::TYPE_REMOVE_COURSE));
        $this->assertStringStartsWith('held', reset($held)->other['result']);
    }

    /**
     * With verifymanifest off, the origin is not asked for its detail.
     */
    public function test_manifest_disabled(): void {
        set_config('verifymanifest', 0, 'local_coursetransfer');
        $request = $this->create_request();
        $file = $this->make_backup($request->target_course_id);
        $this->mock_remove_ok();

        $this->run_restore($request, $file);

        $this->assertCount(1, $this->removals(coursetransfer_request::TYPE_REMOVE_COURSE));
    }

    /**
     * A retry of the cron on a completed request does nothing (LCT-035, LCT-050).
     */
    public function test_completed_request_is_not_touched(): void {
        $request = $this->create_request(['status' => coursetransfer_request::STATUS_COMPLETED]);

        $this->run_restore($request, null);

        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $request->status);
        $this->assertEmpty($request->error_code);
    }

    /**
     * A task whose request was deleted ends without error (LCT-036).
     */
    public function test_missing_request(): void {
        $task = new restore_course_task();
        $task->set_custom_data(['requestid' => 999999, 'fileid' => 999999]);
        ob_start();
        $task->execute();
        $output = ob_get_clean();
        $this->assertStringContainsString('999999', $output);
    }

    /**
     * A category request with a course that was never created does not complete nor remove the origin (N2).
     */
    public function test_category_with_missing_course_keeps_origin(): void {
        $reqcat = $this->create_category_request(2);
        $request = $this->create_request(['request_category_id' => $reqcat->id, 'origin_remove_course' => 0]);
        $file = $this->make_backup($request->target_course_id);
        $this->mock_remove_ok();
        $this->mock_detail();

        $this->run_restore($request, $file);

        $this->assert_origin_not_called();
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($request->id)->status);
        $this->assertEquals(coursetransfer_request::STATUS_INCOMPLETED, coursetransfer_request::get($reqcat->id)->status);
        $this->assertCount(0, $this->removals(coursetransfer_request::TYPE_REMOVE_CATEGORY));
    }

    /**
     * A complete category removes the origin category of the category request, as its requester (N2, S1).
     */
    public function test_category_complete_removes_its_origin_category(): void {
        $reqcat = $this->create_category_request(1);
        $request = $this->create_request(['request_category_id' => $reqcat->id, 'origin_remove_course' => 0]);
        $file = $this->make_backup($request->target_course_id);
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => [
            'course_category_name' => 'C', 'course_category_idnumber' => '', 'requestid' => 1, 'request_origin_id' => 1,
        ]]));
        $this->mock_category_detail([101]);
        $this->mock_detail();

        $this->run_restore($request, $file);

        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($reqcat->id)->status);
        $removals = $this->removals(coursetransfer_request::TYPE_REMOVE_CATEGORY);
        $this->assertCount(1, $removals);
        $this->assertEquals(5000, $removals[0]->origin_category_id, 'The category of the category request');
        $this->assertEquals($this->requester->id, $removals[0]->userid);
    }

    /**
     * Answer of the origin to the category detail (R3): its courses now.
     *
     * @param int[] $ids Origin course ids.
     * @param array $extra Other fields of the data (hiddencategories, hiddencourses).
     */
    private function mock_category_detail(array $ids, array $extra = []): void {
        $courses = array_map(fn($id) => ['id' => $id, 'fullname' => "Course $id", 'shortname' => "C$id"], $ids);
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => [
            'id' => 5000, 'name' => 'Cat', 'idnumber' => '', 'parentid' => 0, 'parentname' => '', 'courses' => $courses,
        ] + $extra]));
    }

    /**
     * Safety copies of a request.
     *
     * @param int $requestid
     * @return \stored_file[]
     */
    private function safety_copies(int $requestid): array {
        return get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_coursetransfer',
            'safetycopy',
            $requestid,
            'id',
            false
        );
    }

    /**
     * Run a restore task of a given class directly.
     *
     * @param restore_course_task $task
     * @param stdClass $request
     * @param stored_file $file
     */
    private function run_task(restore_course_task $task, stdClass $request, stored_file $file): void {
        $task->set_custom_data(['requestid' => (int) $request->id, 'fileid' => (int) $file->get_id()]);
        ob_start();
        $task->execute();
        ob_end_clean();
    }

    /**
     * The manifest is checked while the request is not completed yet: a category could close in between.
     */
    public function test_manifest_checked_before_completed(): void {
        $request = $this->create_request();
        $file = $this->make_backup($request->target_course_id);
        $this->mock_remove_ok();
        $this->mock_detail();

        $this->run_task(new restore_course_task_probe(), $request, $file);

        $this->assertEquals(coursetransfer_request::STATUS_RESTORE, restore_course_task_probe::$statusatcheck);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($request->id)->status);
        $this->assertCount(1, $this->removals(coursetransfer_request::TYPE_REMOVE_COURSE));
    }

    /**
     * Race: a sibling completed without its manifest checked; the last course must not remove the origin category.
     */
    public function test_category_waits_for_unverified_sibling(): void {
        $reqcat = $this->create_category_request(2);
        $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request([
            'request_category_id' => $reqcat->id,
            'status' => coursetransfer_request::STATUS_COMPLETED,
            'siteurl' => self::ORIGIN,
            'origin_course_id' => 102,
            'userid' => $this->requester->id,
        ]);
        $request = $this->create_request(['request_category_id' => $reqcat->id, 'origin_remove_course' => 0]);
        $file = $this->make_backup($request->target_course_id);
        $this->mock_remove_ok();
        $this->mock_detail();

        $this->run_restore($request, $file);

        $this->assert_origin_not_called();
        $reqcat = coursetransfer_request::get($reqcat->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $reqcat->status);
        $this->assertStringContainsString('without a successful manifest check', $reqcat->error_message);
        $this->assertCount(0, $this->removals(coursetransfer_request::TYPE_REMOVE_CATEGORY));
    }

    /**
     * R3: a course created in the origin category after the request holds its removal.
     */
    public function test_category_origin_changed_keeps_origin(): void {
        $reqcat = $this->create_category_request(1);
        $request = $this->create_request(['request_category_id' => $reqcat->id, 'origin_remove_course' => 0]);
        $file = $this->make_backup($request->target_course_id);
        $this->mock_remove_ok();
        $this->mock_category_detail([101, 999]);
        $this->mock_detail();

        $this->run_restore($request, $file);

        $this->assert_origin_not_called();
        $this->assertStringContainsString('not copied (ids 999)', coursetransfer_request::get($reqcat->id)->error_message);
        $this->assertCount(0, $this->removals(coursetransfer_request::TYPE_REMOVE_CATEGORY));
    }

    /**
     * Subcategories the requester cannot see were not copied: the origin category is kept.
     */
    public function test_category_with_hidden_subcategories_keeps_origin(): void {
        $reqcat = $this->create_category_request(1);
        $request = $this->create_request(['request_category_id' => $reqcat->id, 'origin_remove_course' => 0]);
        $file = $this->make_backup($request->target_course_id);
        $this->mock_remove_ok();
        $this->mock_category_detail([101], ['hiddencategories' => 1, 'hiddencourses' => 2]);
        $this->mock_detail();

        $this->run_restore($request, $file);

        $this->assert_origin_not_called();
        $message = coursetransfer_request::get($reqcat->id)->error_message;
        $this->assertStringContainsString('1 subcategory(ies) and 2 course(s)', $message);
        $this->assertCount(0, $this->removals(coursetransfer_request::TYPE_REMOVE_CATEGORY));
    }

    /**
     * An incomplete restore keeps the downloaded .mbz and its safety copy.
     */
    public function test_incomplete_restore_keeps_backup(): void {
        $request = $this->create_request(['origin_remove_course' => 0, 'target_target' => backup::TARGET_EXISTING_ADDING]);
        $file = $this->make_backup($request->target_course_id, true);

        $this->run_restore($request, $file);

        $errorcode = coursetransfer_request::get($request->id)->error_code;
        $this->assertEquals(coursetransfer_request::CODE_RESTORE_WITH_ISSUES, $errorcode);
        $this->assertNotFalse(get_file_storage()->get_file_by_id($file->get_id()), 'The downloaded .mbz is kept');
        $this->assertCount(1, $this->safety_copies((int) $request->id));
    }

    /**
     * A request that asks to keep the safety copy keeps it after a complete restore without removal.
     */
    public function test_keepsafetycopy_restore(): void {
        $request = $this->create_request([
            'origin_remove_course' => 0,
            'target_target' => backup::TARGET_EXISTING_ADDING,
            'origin_manifest' => json_encode(['keepsafetycopy' => true]),
        ]);
        $file = $this->make_backup($request->target_course_id);

        $this->run_restore($request, $file);

        $this->assertFalse(get_file_storage()->get_file_by_id($file->get_id()));
        $this->assertCount(1, $this->safety_copies((int) $request->id));
        $manifest = json_decode(coursetransfer_request::get($request->id)->origin_manifest, true);
        $this->assertTrue($manifest['keepsafetycopy'], 'The manifest of the backup does not overwrite the flag');
        $this->assertArrayHasKey('backup', $manifest);
    }

    /**
     * A category that cannot be evaluated (lock taken) is evaluated again by a queued task.
     */
    public function test_category_refresh_is_queued(): void {
        global $DB;
        $reqcat = $this->create_category_request(1);
        $DB->set_field('local_coursetransfer_request', 'origin_remove_category', 0, ['id' => $reqcat->id]);
        $request = $this->create_request(['request_category_id' => $reqcat->id, 'origin_remove_course' => 0]);
        $file = $this->make_backup($request->target_course_id);

        restore_course_task_probe::$failevaluation = true;
        $this->run_task(new restore_course_task_probe(), $request, $file);
        restore_course_task_probe::$failevaluation = false;

        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($request->id)->status);
        $this->assertNotEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($reqcat->id)->status);
        $tasks = manager::get_adhoc_tasks(restore_course_task::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertNotEmpty($task->get_custom_data()->refreshcategory);

        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($reqcat->id)->status);
    }

    /**
     * A category restore request with a snapshot of the courses to copy.
     *
     * @param int $courses Number of courses in the origin category.
     * @return stdClass
     */
    private function create_category_request(int $courses): stdClass {
        $list = [];
        for ($i = 1; $i <= $courses; $i++) {
            $list[] = ['id' => 100 + $i, 'fullname' => "Course $i", 'shortname' => "C$i"];
        }
        return $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request([
            'type' => coursetransfer_request::TYPE_CATEGORY,
            'siteurl' => self::ORIGIN,
            'status' => coursetransfer_request::STATUS_NOT_STARTED,
            'origin_category_id' => 5000,
            'origin_remove_category' => 1,
            'origin_category_tree' => json_encode(['name' => 'Cat', 'idnumber' => '', 'courses' => $list, 'categories' => []]),
            'userid' => $this->requester->id,
        ]);
    }
}
