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
 * Tests for the origin course removal task.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use advanced_testcase;
use core\task\manager;
use local_coursetransfer\coursetransfer_remove;
use local_coursetransfer\coursetransfer_request;
use stdClass;

/**
 * Tests for remove_course_task.
 *
 * The callback to the target goes through Moodle's curl mock: no request leaves the test.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\task\remove_course_task
 * @covers     \local_coursetransfer\coursetransfer_remove
 */
final class remove_course_task_test extends advanced_testcase {
    /** @var stdClass Course to remove. */
    private $course;

    /** @var stdClass Response request on this origin. */
    private $request;

    /** @var stdClass Target site. */
    private $site;

    /**
     * Queue the removal of a small course, as the origin_remove_course service does.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        // The recycle bin would back up the course before deleting it; it is not under test here.
        set_config('categorybinenable', 0, 'tool_recyclebin');

        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $site = $generator->create_site('target', ['host' => 'https://target.example.com']);
        $this->site = $site;
        $this->course = $this->getDataGenerator()->create_course(['shortname' => 'TO-REMOVE']);
        $this->request = $generator->create_request([
            'type' => coursetransfer_request::TYPE_REMOVE_COURSE,
            'direction' => coursetransfer_request::DIRECTION_RESPONSE,
            'siteurl' => $site->host,
            'status' => coursetransfer_request::STATUS_IN_PROGRESS,
            'origin_course_id' => $this->course->id,
        ]);
        coursetransfer_remove::create_task_remove_course(
            (int) $this->request->id,
            77,
            (int) $this->course->id,
            $site,
            (int) get_admin()->id
        );
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
     * Run the queued removal task.
     */
    private function run_task(): void {
        $tasks = manager::get_adhoc_tasks(remove_course_task::class);
        $this->assertCount(1, $tasks);
        ob_start();
        reset($tasks)->execute();
        ob_end_clean();
    }

    /**
     * The course is deleted, the request completed, the target notified and the bin cleanup queued.
     */
    public function test_remove_course(): void {
        global $DB;
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => ['id' => 77]]));
        $sink = $this->redirectEvents();

        $this->run_task();

        $executed = array_filter($sink->get_events(), fn($e) => $e instanceof \local_coursetransfer\event\remote_removal_executed);
        $sink->close();
        $this->assertCount(1, $executed);
        $this->assertEquals($this->course->id, reset($executed)->other['id']);
        $this->assertFalse($DB->record_exists('course', ['id' => $this->course->id]));
        $request = coursetransfer_request::get($this->request->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $request->status);
        $cleanups = manager::get_adhoc_tasks(cleanup_course_bin_task::class);
        $this->assertCount(1, $cleanups);
        $cleanup = reset($cleanups);
        $this->assertEquals($this->course->id, $cleanup->get_custom_data()->courseid);
        $this->assertSame('TO-REMOVE', $cleanup->get_custom_data()->shortname);
        $this->assertGreaterThan(time(), $cleanup->get_next_run_time());
    }

    /**
     * If the target rejects the completed callback, the course is gone but the request records the error.
     */
    public function test_remove_course_callback_failed(): void {
        global $DB;
        \curl::mock_response(json_encode(['success' => false, 'errors' => [['code' => '14022', 'msg' => 'Request id not found']]]));

        $this->run_task();

        $this->assertFalse($DB->record_exists('course', ['id' => $this->course->id]));
        $request = coursetransfer_request::get($this->request->id);
        // Removed: a failed notice to the target is a note on a completed request, not an error (C3).
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $request->status);
        $this->assertSame('15010', $request->error_code);
        $this->assertStringContainsString('14022 Request id not found', $request->error_message);
    }

    /**
     * A course that no longer exists marks the request as failed and tells the target (LCT-015).
     */
    public function test_remove_course_missing(): void {
        delete_course($this->course, false);
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => ['id' => 77]]));

        $this->run_task();

        $request = coursetransfer_request::get($this->request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertSame('15003', $request->error_code);
        $this->assertCount(0, manager::get_adhoc_tasks(cleanup_course_bin_task::class));
    }

    /**
     * The removal runs with the capabilities of the user who asked for it, not the cron's.
     */
    public function test_remove_course_requester_without_capability(): void {
        global $DB;
        $DB->delete_records('task_adhoc', ['classname' => '\\' . remove_course_task::class]);
        $user = $this->getDataGenerator()->create_user();
        coursetransfer_remove::create_task_remove_course(
            (int) $this->request->id,
            77,
            (int) $this->course->id,
            $this->site,
            (int) $user->id
        );
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => ['id' => 77]]));
        $sink = $this->redirectEvents();

        $this->run_task();

        $rejected = array_filter($sink->get_events(), fn($e) => $e instanceof \local_coursetransfer\event\remote_removal_rejected);
        $sink->close();
        $this->assertCount(1, $rejected);
        $this->assertTrue($DB->record_exists('course', ['id' => $this->course->id]));
        $request = coursetransfer_request::get($this->request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertSame('15002', $request->error_code);
    }

    /**
     * A task whose request was deleted ends without removing anything (LCT-036).
     */
    public function test_remove_course_request_deleted(): void {
        global $DB;
        $DB->delete_records('local_coursetransfer_request', ['id' => $this->request->id]);

        $this->run_task();

        $this->assertTrue($DB->record_exists('course', ['id' => $this->course->id]));
    }

    /**
     * A second run on a completed request removes nothing and calls no one.
     */
    public function test_remove_course_completed_is_not_rerun(): void {
        global $DB;
        $DB->set_field(
            'local_coursetransfer_request',
            'status',
            coursetransfer_request::STATUS_COMPLETED,
            ['id' => $this->request->id]
        );

        $this->run_task();

        $this->assertTrue($DB->record_exists('course', ['id' => $this->course->id]));
    }
}
