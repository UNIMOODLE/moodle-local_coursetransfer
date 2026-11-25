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
 * The backup only includes user data when the requesting user may back it up (N4), in every selected section.
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

    /**
     * With user data, every selected section and activity keeps its user data, not only the first (LCT-033).
     */
    public function test_userinfo_in_every_selected_section(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['numsections' => 4]);
        $cms = [];
        foreach ([1, 2, 3, 4] as $sectionnum) {
            $cms[$sectionnum] = $gen->create_module('assign', ['course' => $course->id, 'section' => $sectionnum]);
        }
        $modinfo = get_fast_modinfo($course);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            // Section 2 is left out: the ones after it must still carry their user data.
            $selected = ((int) $section->section === 2) ? 0 : 1;
            $activities = [];
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $activities[] = ['cmid' => $cmid, 'selected' => $selected];
            }
            $sections[] = ['sectionid' => $section->id, 'selected' => $selected, 'activities' => $activities];
        }

        $site = $gen->get_plugin_generator('local_coursetransfer')->create_site('target');
        $adminid = (int) get_admin()->id;
        $this->assertTrue(coursetransfer_backup::create_task_backup_course((int) $course->id, $adminid, $site, 1, 1, $sections, 1));
        $tasks = manager::get_adhoc_tasks(create_backup_course_task::class);
        $bc = backup_controller::load_controller(end($tasks)->get_custom_data()->backupid);
        $plan = $bc->get_plan();
        foreach ($modinfo->get_section_info_all() as $section) {
            $expected = ((int) $section->section === 2) ? 0 : 1;
            $this->assertSame($expected, (int) $plan->get_setting('section_' . $section->id . '_included')->get_value());
            $userinfo = (int) $plan->get_setting('section_' . $section->id . '_userinfo')->get_value();
            $this->assertSame($expected, $userinfo, 'section ' . $section->section);
        }
        foreach ($cms as $sectionnum => $module) {
            $expected = ($sectionnum === 2) ? 0 : 1;
            $this->assertSame($expected, (int) $plan->get_setting('assign_' . $module->cmid . '_included')->get_value());
            $userinfo = (int) $plan->get_setting('assign_' . $module->cmid . '_userinfo')->get_value();
            $this->assertSame($expected, $userinfo, 'activity in section ' . $sectionnum);
        }
        $bc->destroy();
    }
}
