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
 * Tests for the category recycle bin cleanup task.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use advanced_testcase;
use core\task\manager;
use local_coursetransfer\coursetransfer_remove;

/**
 * Tests for cleanup_category_bin_task.
 *
 * Items are put in the bin by the core recycle bin itself (it backs up what it keeps), so these
 * tests are slow.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @group      local_coursetransfer_slow
 * @covers     \local_coursetransfer\task\cleanup_category_bin_task
 * @covers     \local_coursetransfer\coursetransfer::cleanup_category_bin
 */
final class cleanup_category_bin_task_test extends advanced_testcase {
    /** @var \core_course_category Category whose bin is cleaned. */
    private $category;

    /** @var \core_course_category Category whose bin must be left alone. */
    private $other;

    /**
     * Delete one course in each of two categories through the category recycle bin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('categorybinenable', 1, 'tool_recyclebin');

        $this->category = $this->getDataGenerator()->create_category();
        $this->other = $this->getDataGenerator()->create_category();
        foreach ([$this->category, $this->other] as $category) {
            delete_course($this->getDataGenerator()->create_course(['category' => $category->id]), false);
        }
        $this->assertEquals(1, $this->bin_count($this->category));
        $this->assertEquals(1, $this->bin_count($this->other));
    }

    /**
     * Number of items in a category bin.
     *
     * @param \core_course_category $category
     * @return int
     */
    private function bin_count(\core_course_category $category): int {
        global $DB;
        return $DB->count_records('tool_recyclebin_category', ['categoryid' => $category->id]);
    }

    /**
     * Queue the cleanup as the removal task does and run it.
     *
     * @param int $age Seconds since the category was removed (past the 30 days of retention by default).
     */
    private function run_cleanup(int $age = 31 * DAYSECS): void {
        global $DB;
        $this->assertTrue(coursetransfer_remove::create_cleanup_category_bin_task((int) $this->category->id, time() - $age));
        $tasks = manager::get_adhoc_tasks(cleanup_category_bin_task::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $DB->delete_records('task_adhoc', ['id' => $task->get_id()]);
        ob_start();
        $task->execute();
        ob_end_clean();
    }

    /**
     * With remove_cat_cleanup on, only the bin of the removed category is purged.
     */
    public function test_cleanup_enabled(): void {
        set_config('remove_cat_cleanup', 1, 'local_coursetransfer');

        $this->run_cleanup();

        $this->assertEquals(0, $this->bin_count($this->category));
        $this->assertEquals(1, $this->bin_count($this->other));
    }

    /**
     * With remove_cat_cleanup off, the bin is left as it is.
     */
    public function test_cleanup_disabled(): void {
        set_config('remove_cat_cleanup', 0, 'local_coursetransfer');

        $this->run_cleanup();

        $this->assertEquals(1, $this->bin_count($this->category));
    }

    /**
     * Before the retention the bin is kept and the purge is queued again.
     */
    public function test_cleanup_waits_for_retention(): void {
        set_config('remove_cat_cleanup', 1, 'local_coursetransfer');

        $this->run_cleanup(DAYSECS);

        $this->assertEquals(1, $this->bin_count($this->category));
        $this->assertCount(1, manager::get_adhoc_tasks(cleanup_category_bin_task::class));
    }
}
