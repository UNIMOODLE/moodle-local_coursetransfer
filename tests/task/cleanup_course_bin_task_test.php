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
 * Tests for the course recycle bin cleanup task.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use advanced_testcase;
use core\task\manager;
use local_coursetransfer\coursetransfer_remove;
use stdClass;

/**
 * Tests for cleanup_course_bin_task.
 *
 * Items are put in the bins by the core recycle bin itself (it backs up what it keeps), so these
 * tests are slow.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @group      local_coursetransfer_slow
 * @covers     \local_coursetransfer\task\cleanup_course_bin_task
 * @covers     \local_coursetransfer\coursetransfer::cleanup_course_bin
 */
final class cleanup_course_bin_task_test extends advanced_testcase {
    /** @var stdClass Removed course. */
    private $course;

    /**
     * Delete an activity of a course and then the course, both through the recycle bins.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('coursebinenable', 1, 'tool_recyclebin');
        set_config('categorybinenable', 1, 'tool_recyclebin');

        $this->course = $this->getDataGenerator()->create_course(['shortname' => 'BIN1']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        course_delete_module($page->cmid);
        $this->assertTrue($this->bin_has_items());
        delete_course($this->course, false);
        $this->assertTrue($this->bin_has_items());
    }

    /**
     * Whether the recycle bins still hold anything of the course.
     *
     * @return bool
     */
    private function bin_has_items(): bool {
        global $DB;
        return $DB->record_exists('tool_recyclebin_course', ['courseid' => $this->course->id])
            || $DB->record_exists('tool_recyclebin_category', ['shortname' => $this->course->shortname]);
    }

    /**
     * Queue the cleanup as the removal task does and run it.
     *
     * @param int $age Seconds since the course was removed (past the 30 days of retention by default).
     */
    private function run_cleanup(int $age = 31 * DAYSECS): void {
        global $DB;
        $this->assertTrue(coursetransfer_remove::create_cleanup_course_bin_task($this->course, time() - $age));
        $tasks = manager::get_adhoc_tasks(cleanup_course_bin_task::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $DB->delete_records('task_adhoc', ['id' => $task->get_id()]);
        ob_start();
        $task->execute();
        ob_end_clean();
    }

    /**
     * With remove_course_cleanup on, the course items are purged from both bins.
     */
    public function test_cleanup_enabled(): void {
        global $DB;
        set_config('remove_course_cleanup', 1, 'local_coursetransfer');

        $this->run_cleanup();

        $this->assertFalse($DB->record_exists('tool_recyclebin_course', ['courseid' => $this->course->id]));
        $this->assertFalse($DB->record_exists('tool_recyclebin_category', ['shortname' => 'BIN1']));
    }

    /**
     * With remove_course_cleanup off, the bins are left as they are.
     */
    public function test_cleanup_disabled(): void {
        global $DB;
        set_config('remove_course_cleanup', 0, 'local_coursetransfer');

        $this->run_cleanup();

        $this->assertTrue($DB->record_exists('tool_recyclebin_category', ['shortname' => 'BIN1']));
    }

    /**
     * Only the bin item of the removed course goes, not another course's with the same shortname.
     */
    public function test_cleanup_only_this_course(): void {
        global $DB;
        set_config('remove_course_cleanup', 1, 'local_coursetransfer');
        $other = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'BIN1', 'category' => $other->id]);
        delete_course($course, false);
        $this->assertTrue($DB->record_exists('tool_recyclebin_category', ['shortname' => 'BIN1', 'categoryid' => $other->id]));

        $this->run_cleanup();

        $this->assertFalse($DB->record_exists(
            'tool_recyclebin_category',
            ['shortname' => 'BIN1', 'categoryid' => $this->course->category]
        ));
        $this->assertTrue($DB->record_exists('tool_recyclebin_category', ['shortname' => 'BIN1', 'categoryid' => $other->id]));
    }

    /**
     * Before the retention the bins are kept and the purge is queued again for when it ends.
     */
    public function test_cleanup_waits_for_retention(): void {
        global $DB;
        set_config('remove_course_cleanup', 1, 'local_coursetransfer');
        set_config('recyclebinretentiondays', 10, 'local_coursetransfer');

        $this->run_cleanup(5 * DAYSECS);

        $this->assertTrue($DB->record_exists('tool_recyclebin_category', ['shortname' => 'BIN1']));
        $tasks = manager::get_adhoc_tasks(cleanup_course_bin_task::class);
        $this->assertCount(1, $tasks);
        $this->assertEqualsWithDelta(time() + 5 * DAYSECS, reset($tasks)->get_next_run_time(), 5);
    }

    /**
     * The purge is queued for the end of the retention, never right after the removal.
     */
    public function test_queued_after_retention(): void {
        set_config('recyclebinretentiondays', 0, 'local_coursetransfer');
        coursetransfer_remove::create_cleanup_course_bin_task($this->course);
        $tasks = manager::get_adhoc_tasks(cleanup_course_bin_task::class);
        $this->assertEqualsWithDelta(time() + DAYSECS, reset($tasks)->get_next_run_time(), 5, 'At least one day');
    }
}
