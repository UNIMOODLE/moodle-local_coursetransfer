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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * orphan_tasks_test
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use advanced_testcase;
use core\task\adhoc_task;
use core\task\manager;
use core_course_category;

/**
 * A task whose request was deleted ends without doing anything and leaves the queue (LCT-036, LCT-126).
 *
 * Deleting a request from the history does not delete its tasks: this is what makes that safe.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\task\create_backup_course_task
 * @covers     \local_coursetransfer\task\download_file_course_task
 * @covers     \local_coursetransfer\task\restore_course_task
 * @covers     \local_coursetransfer\task\remove_course_task
 * @covers     \local_coursetransfer\task\remove_category_task
 */
final class orphan_tasks_test extends advanced_testcase {
    /** @var int A request id that does not exist. */
    private const MISSING = 987654;

    /**
     * Queue a task, run it as the cron would and return what it printed.
     *
     * @param adhoc_task $task
     * @return string
     */
    private function run_queued(adhoc_task $task): string {
        manager::queue_adhoc_task($task);
        ob_start();
        $this->runAdhocTasks(get_class($task));
        return (string) ob_get_clean();
    }

    /**
     * Each task of a deleted request: no error, nothing copied, downloaded, restored nor removed, queue empty.
     */
    public function test_tasks_of_a_deleted_request_end_cleanly(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category(['name' => 'Kept category']);
        $course = $generator->create_course(['category' => $category->id, 'shortname' => 'KEPT']);
        $coursesbefore = $DB->count_records('course');

        $backup = new create_backup_course_task();
        $backup->set_custom_data(['backupid' => 'none', 'targetsite' => 0, 'requestid' => self::MISSING,
            'requestoriginid' => self::MISSING, 'istest' => true]);
        $download = new download_file_course_task();
        $download->set_custom_data(['requestid' => self::MISSING, 'fileurl' => 'https://origin.example.com/x.mbz']);
        $restore = new restore_course_task();
        $restore->set_custom_data(['requestid' => self::MISSING, 'fileid' => 0]);
        $removecourse = new remove_course_task();
        $removecourse->set_custom_data(['targetsiteid' => 0, 'courseid' => (int) $course->id,
            'requestoriginid' => self::MISSING, 'requestdestid' => 0, 'userid' => get_admin()->id]);
        $removecategory = new remove_category_task();
        $removecategory->set_custom_data(['targetsiteid' => 0, 'catid' => (int) $category->id,
            'requestoriginid' => self::MISSING, 'requestdestid' => 0, 'userid' => get_admin()->id]);

        foreach ([$backup, $download, $restore, $removecourse, $removecategory] as $task) {
            $output = $this->run_queued($task);
            $this->assertStringContainsString('not found', $output, get_class($task));
            $this->assertCount(0, manager::get_adhoc_tasks(get_class($task)), get_class($task) . ' stays queued');
        }

        // Nothing was removed nor created.
        $this->assertTrue($DB->record_exists('course', ['id' => $course->id]));
        $this->assertNotEmpty(core_course_category::get($category->id, IGNORE_MISSING));
        $this->assertSame($coursesbefore, $DB->count_records('course'));
        $this->assertSame(0, $DB->count_records('local_coursetransfer_request'));
    }
}
