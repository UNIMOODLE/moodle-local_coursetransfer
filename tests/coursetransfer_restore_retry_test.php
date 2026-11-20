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
}
