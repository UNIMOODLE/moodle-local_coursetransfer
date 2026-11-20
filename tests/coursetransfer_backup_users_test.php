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
 * User data in the backups the origin makes for a peer.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use backup_controller;
use core\task\manager;
use local_coursetransfer\task\create_backup_course_task;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

/**
 * The backup only includes user data when the requesting user may back it up (N4).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\coursetransfer_backup
 */
final class coursetransfer_backup_users_test extends advanced_testcase {
    /**
     * Queue a backup asking for user data and return the users setting of its plan.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    private function users_setting(int $courseid, int $userid): bool {
        $site = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_site('target');
        $this->assertTrue(coursetransfer_backup::create_task_backup_course($courseid, $userid, $site, 1, 1, [], 1));
        $tasks = manager::get_adhoc_tasks(create_backup_course_task::class);
        $bc = backup_controller::load_controller(end($tasks)->get_custom_data()->backupid);
        $value = (bool) $bc->get_plan()->get_setting('users')->get_value();
        $bc->destroy();
        return $value;
    }

    /**
     * A teacher without moodle/backup:userinfo gets a backup without user data.
     */
    public function test_users_locked_by_permission(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->assertFalse(has_capability('moodle/backup:userinfo', \context_course::instance($course->id), $teacher));

        $this->assertFalse($this->users_setting((int) $course->id, (int) $teacher->id));
    }

    /**
     * A user with moodle/backup:userinfo gets the user data asked for.
     */
    public function test_users_allowed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $this->assertTrue($this->users_setting((int) $course->id, (int) get_admin()->id));
    }
}
