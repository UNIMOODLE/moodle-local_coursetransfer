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
 * Tests for the retry of a course restore and the lookup of its adhoc tasks.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use core\task\manager;
use local_coursetransfer\task\restore_course_task;
use stdClass;

/**
 * A retry never runs alongside a task that is still working on the request (LCT-038).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\coursetransfer::restore_retry
 * @covers     \local_coursetransfer\coursetransfer_request::get_related_adhoc_tasks
 */
final class coursetransfer_restore_retry_test extends advanced_testcase {
    /**
     * Act as admin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * A downloaded request with its .mbz in the target course.
     *
     * @return array [stdClass request, \stored_file file]
     */
    private function downloaded_request(): array {
        $course = $this->getDataGenerator()->create_course();
        $request = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request([
            'status' => coursetransfer_request::STATUS_DOWNLOADED,
            'target_course_id' => $course->id,
            'origin_course_id' => 11,
        ]);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($course->id)->id, 'component' => 'backup', 'filearea' => 'course',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'local_coursetransfer_11_1.mbz',
        ], gzencode('x'));
        return [$request, $file];
    }

    /**
     * Tasks queued with the id of a database record (a string) are found.
     */
    public function test_related_tasks_found(): void {
        [$request, $file] = $this->downloaded_request();
        coursetransfer_restore::create_task_restore_course($request, $file);

        $this->assertCount(1, coursetransfer_request::get_related_adhoc_tasks((int) $request->id));
    }

    /**
     * A failing task that is running right now blocks the retry instead of being replaced.
     */
    public function test_retry_blocked_by_running_task(): void {
        global $DB;
        [$request, $file] = $this->downloaded_request();
        $task = new restore_course_task();
        $task->set_custom_data(['requestid' => (int) $request->id, 'fileid' => $file->get_id()]);
        $taskid = manager::queue_adhoc_task($task);
        $DB->update_record('task_adhoc', (object) ['id' => $taskid, 'faildelay' => 120, 'timestarted' => time()]);

        $res = coursetransfer::restore_retry($request);

        $this->assertFalse($res['success']);
        $this->assertSame('10023', $res['errors'][0]['code']);
        $this->assertTrue($DB->record_exists('task_adhoc', ['id' => $taskid]));
    }

    /**
     * A failing task waiting for its next attempt is replaced by the retry.
     */
    public function test_retry_replaces_waiting_task(): void {
        global $DB;
        [$request, $file] = $this->downloaded_request();
        $task = new restore_course_task();
        $task->set_custom_data(['requestid' => (int) $request->id, 'fileid' => $file->get_id()]);
        $taskid = manager::queue_adhoc_task($task);
        $DB->set_field('task_adhoc', 'faildelay', 120, ['id' => $taskid]);

        $res = coursetransfer::restore_retry($request);

        $this->assertTrue($res['success']);
        $this->assertSame('rerestore', $res['mode']);
        $this->assertFalse($DB->record_exists('task_adhoc', ['id' => $taskid]));
        $this->assertCount(1, manager::get_adhoc_tasks(restore_course_task::class));
    }

    /**
     * A request still moving is not relaunched; once stopped for over an hour it is (#45).
     */
    public function test_retry_stalled_request(): void {
        global $DB;
        [$request, $file] = $this->downloaded_request();
        $this->assertFalse(coursetransfer::can_retry($request));

        $res = coursetransfer::restore_retry($request);
        $this->assertFalse($res['success']);
        $this->assertSame('10024', $res['errors'][0]['code']);
        $this->assertCount(0, manager::get_adhoc_tasks(restore_course_task::class));

        $DB->set_field('local_coursetransfer_request', 'timemodified', time() - HOURSECS - 60, ['id' => $request->id]);
        $request = coursetransfer_request::get((int) $request->id);
        $this->assertTrue(coursetransfer::can_retry($request));

        $res = coursetransfer::restore_retry($request);
        $this->assertTrue($res['success']);
        $this->assertSame('rerestore', $res['mode']);
        $this->assertCount(1, manager::get_adhoc_tasks(restore_course_task::class));
    }

    /**
     * Requests in error can always be retried; completed ones and other types never.
     */
    public function test_can_retry(): void {
        $error = (object) ['type' => coursetransfer_request::TYPE_COURSE, 'direction' => coursetransfer_request::DIRECTION_REQUEST,
            'status' => coursetransfer_request::STATUS_ERROR, 'timemodified' => time()];
        $this->assertTrue(coursetransfer::can_retry($error));
        $this->assertFalse(coursetransfer::can_retry((object) (['status' => coursetransfer_request::STATUS_COMPLETED,
            'timemodified' => 0] + (array) $error)));
        $category = (object) (['type' => coursetransfer_request::TYPE_CATEGORY] + (array) $error);
        $this->assertFalse(coursetransfer::can_retry($category));
    }

    /**
     * Scheduled requests, the wait for the origin backup and courses of a category restore (A5, A6).
     */
    public function test_can_retry_waits(): void {
        $base = ['type' => coursetransfer_request::TYPE_COURSE, 'direction' => coursetransfer_request::DIRECTION_REQUEST];
        $old = time() - 2 * HOURSECS;
        $waiting = (object) (['status' => coursetransfer_request::STATUS_IN_PROGRESS, 'timemodified' => $old] + $base);
        $this->assertFalse(coursetransfer::can_retry($waiting), 'The origin may still be making its backup');
        $waiting->timemodified = time() - 7 * HOURSECS;
        $this->assertTrue(coursetransfer::can_retry($waiting));
        $waiting->origin_schedule_datetime = time() + DAYSECS;
        $this->assertFalse(coursetransfer::can_retry($waiting), 'Scheduled for tomorrow');
        $waiting->origin_schedule_datetime = (time() + DAYSECS) * 1000;
        $this->assertFalse(coursetransfer::can_retry($waiting), 'Scheduled, stored in milliseconds');

        $child = (object) (['status' => coursetransfer_request::STATUS_ERROR, 'timemodified' => $old,
            'request_category_id' => 5] + $base);
        $this->assertFalse(coursetransfer::can_retry($child), 'Would be relaunched outside its category');
        $child->status = coursetransfer_request::STATUS_DOWNLOADED;
        $this->assertTrue(coursetransfer::can_retry($child), 'Its downloaded backup can be restored again');
    }

    /**
     * A retry already running for the request blocks a second one (B3).
     */
    public function test_retry_locked(): void {
        global $CFG;
        // File locks: the database ones let the same process take its own lock again.
        $CFG->lock_factory = '\\core\\lock\\file_lock_factory';
        [$request] = $this->downloaded_request();
        $lock = \core\lock\lock_config::get_lock_factory('local_coursetransfer')->get_lock('retry_' . $request->id, 0);
        $this->assertNotFalse($lock);
        try {
            $res = coursetransfer::restore_retry($request);
        } finally {
            $lock->release();
        }
        $this->assertFalse($res['success']);
        $this->assertSame('10026', $res['errors'][0]['code']);
    }

    /**
     * A completed request whose origin was kept says so; one without a removal asked for has warnings (B1).
     */
    public function test_origin_kept_action(): void {
        $request = (object) ['status' => coursetransfer_request::STATUS_COMPLETED, 'origin_remove_course' => 1,
            'error_code' => coursetransfer_request::CODE_MANIFEST_MISMATCH];
        $this->assertSame(['exec_kept_title', 'exec_kept_action_manifest'], coursetransfer_request::origin_kept_action($request));
        $request->origin_remove_course = 0;
        $request->error_code = coursetransfer_request::CODE_RESTORE_WITH_ISSUES;
        $this->assertSame(['exec_issues_title', 'exec_kept_action_issues'], coursetransfer_request::origin_kept_action($request));
        $request->error_code = null;
        $this->assertNull(coursetransfer_request::origin_kept_action($request));
        $request->status = coursetransfer_request::STATUS_ERROR;
        $request->error_code = coursetransfer_request::CODE_MANIFEST_MISMATCH;
        $this->assertNull(coursetransfer_request::origin_kept_action($request));
    }

    /**
     * A request already relaunched is not relaunched again; a course of a category gets its own reason.
     */
    public function test_retry_relaunched_and_category_course(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $relaunched = $generator->create_request(['status' => coursetransfer_request::STATUS_ERROR,
            'error_message' => 'Relaunched as request #42']);
        $this->assertFalse(coursetransfer::can_retry($relaunched));
        $res = coursetransfer::restore_retry($relaunched);
        $this->assertFalse($res['success']);
        $this->assertSame('10028', $res['errors'][0]['code']);

        $category = $generator->create_request(['type' => coursetransfer_request::TYPE_CATEGORY]);
        $child = $generator->create_request(['status' => coursetransfer_request::STATUS_ERROR,
            'request_category_id' => $category->id]);
        $res = coursetransfer::restore_retry($child);
        $this->assertFalse($res['success']);
        $this->assertSame('10025', $res['errors'][0]['code']);
    }
}
