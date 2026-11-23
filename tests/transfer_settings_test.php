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
 * What a transfer backs up and restores, whatever the general defaults of the site.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use backup;
use backup_controller;
use completion_info;
use core\task\manager;
use grade_item;
use local_coursetransfer\task\create_backup_course_task;
use restore_controller;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * A transfer carries the whole course (LCT-063) and keeps every enrolment as a manual one (LCT-064).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\coursetransfer_backup
 * @covers     \local_coursetransfer\coursetransfer_restore
 * @covers     \local_coursetransfer\restore_manifest
 */
final class transfer_settings_test extends advanced_testcase {
    /**
     * Turn off (and lock) the general backup and restore defaults a transfer must not depend on.
     */
    private function disable_site_defaults(): void {
        $names = ['users', 'activities', 'blocks', 'files', 'filters', 'logs', 'histories', 'calendarevents', 'questionbank',
            'groups'];
        foreach ($names as $name) {
            // User data off by default only: a lock on it is a privacy decision the transfer keeps (A7).
            $locked = $name === 'users' ? 0 : 1;
            set_config('backup_general_' . $name, 0, 'backup');
            set_config('backup_general_' . $name . '_locked', $locked, 'backup');
            set_config('restore_general_' . $name, 0, 'restore');
            set_config('restore_general_' . $name . '_locked', $locked, 'restore');
        }
    }

    /**
     * Root settings of the backup the plugin queues.
     *
     * @param int $courseid
     * @param int $rootusers
     * @return array
     */
    private function queued_settings(int $courseid, int $rootusers): array {
        $site = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_site('target');
        $this->assertTrue(coursetransfer_backup::create_task_backup_course(
            $courseid,
            (int) get_admin()->id,
            $site,
            1,
            1,
            [],
            $rootusers
        ));
        $tasks = manager::get_adhoc_tasks(create_backup_course_task::class);
        $bc = backup_controller::load_controller(end($tasks)->get_custom_data()->backupid);
        $values = [];
        foreach (array_keys(coursetransfer_backup::root_settings(1)) as $name) {
            if ($bc->get_plan()->setting_exists($name)) {
                $values[$name] = (int) $bc->get_plan()->get_setting($name)->get_value();
            }
        }
        $bc->destroy();
        return $values;
    }

    /**
     * With users, everything as the backup form with every option ticked; without users, all the content.
     */
    public function test_backup_settings_ignore_site_defaults(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->disable_site_defaults();
        $course = $this->getDataGenerator()->create_course();

        $with = $this->queued_settings((int) $course->id, 1);
        $names = ['users', 'blocks', 'files', 'filters', 'logs', 'grade_histories', 'calendarevents', 'questionbank',
            'groups', 'role_assignments', 'comments', 'userscompletion'];
        foreach ($names as $name) {
            if ($name === 'questionbank' && !array_key_exists($name, $with)) {
                continue; // Moodle 5.0+: question banks are activities, there is no such root setting.
            }
            $this->assertSame(1, $with[$name], $name);
        }
        $this->assertSame(0, $with['anonymize']);

        $without = $this->queued_settings((int) $course->id, 0);
        $this->assertSame(0, $without['users']);
        $this->assertSame(0, $without['logs']);
        $this->assertSame(1, $without['blocks']);
        $this->assertSame(1, $without['groups']);

        set_config('transferlogs', 0, 'local_coursetransfer');
        $nologs = $this->queued_settings((int) $course->id, 1);
        $this->assertSame(0, $nologs['logs']);
        $this->assertSame(1, $nologs['grade_histories']);
    }

    /**
     * A course with user data travels whole: the manifest finds nothing missing and enrolments become manual.
     */
    public function test_transfer_with_users_is_complete(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enablecompletion = 1;
        $this->disable_site_defaults();
        $gen = $this->getDataGenerator();

        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_user();
        // Self enrolment stands for any method tied to the origin.
        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $self->status = ENROL_INSTANCE_ENABLED;
        $DB->update_record('enrol', $self);
        enrol_get_plugin('self')->enrol_user($self, $student->id, $DB->get_field('role', 'id', ['shortname' => 'student']));

        $page = $gen->create_module('page', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);
        (new completion_info($course))->update_state($cm, COMPLETION_COMPLETE, $student->id);
        $item = $gen->create_grade_item(['courseid' => $course->id]);
        grade_item::fetch(['id' => $item->id])->update_final_grade($student->id, 75);
        // A forum post: user data with no grade nor file, so only the per-activity userinfo carries it (A2).
        $forum = $gen->create_module('forum', ['course' => $course->id]);
        $gen->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id, 'forum' => $forum->id,
            'userid' => $student->id]);
        get_file_storage()->create_file_from_string([
            'contextid' => $cm->context->id, 'component' => 'mod_page', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'notes.txt',
        ], 'notes');

        // Backup as the origin does it.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            get_admin()->id
        );
        coursetransfer_backup::apply_root_settings($bc->get_plan(), 1);
        coursetransfer_backup::apply_element_settings($bc->get_plan(), 1);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $tempdir = 'lct_' . random_string(8);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), make_backup_temp_directory($tempdir));

        $backup = restore_manifest::from_backup($tempdir);
        $this->assertSame([], $backup['settings_missing']);
        $this->assertSame([], $backup['userinfo_missing']);
        $this->assertSame(1, $backup['enrolments']);
        $this->assertGreaterThanOrEqual(1, $backup['userdata']['grades']);
        $this->assertSame(1, $backup['userdata']['completion']);
        $this->assertSame(1, $backup['userdata']['files']['mod_page'] ?? 0);

        // Restore as the target does it.
        $newid = \restore_dbops::create_new_course('Restored', 'RESTORED', $course->category);
        $rc = new restore_controller(
            $tempdir,
            $newid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            get_admin()->id,
            backup::TARGET_NEW_COURSE
        );
        coursetransfer_restore::apply_transfer_settings($rc->get_plan(), $tempdir);
        $rc->save_controller();
        $rc = restore_controller::load_controller($rc->get_restoreid());
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $enrolments = $DB->get_records_sql(
            'SELECT ue.id, e.enrol, ue.status
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :course AND ue.userid = :user',
            ['course' => $newid, 'user' => $student->id]
        );
        $this->assertSame(1, (int) $DB->count_records_sql(
            'SELECT COUNT(p.id)
               FROM {forum_posts} p
               JOIN {forum_discussions} d ON d.id = p.discussion
              WHERE d.course = :course AND p.userid = :user',
            ['course' => $newid, 'user' => $student->id]
        ), 'The forum post of the student arrives');
        $this->assertCount(1, $enrolments);
        $enrolment = reset($enrolments);
        $this->assertSame('manual', $enrolment->enrol);
        $this->assertSame(ENROL_USER_ACTIVE, (int) $enrolment->status);

        $expected = restore_manifest::of_course((int) $course->id);
        foreach (['enrolments', 'settings_missing', 'userinfo_missing', 'userdata'] as $key) {
            $expected[$key] = $backup[$key];
        }
        $actual = restore_manifest::of_course($newid);
        $actual['userdata'] = restore_manifest::userdata_of_course($newid);
        $this->assertSame([], restore_manifest::compare($expected, $actual));
    }

    /**
     * Missing user data or an incomplete backup hold the removal of the origin.
     */
    public function test_compare_user_data(): void {
        $expected = [
            'sections' => 1, 'activities' => [], 'enrolments' => 2,
            'settings_missing' => ['blocks'], 'userinfo_missing' => ['assign_7'],
            'userdata' => ['grades' => 4, 'completion' => 2, 'files' => ['assignsubmission_file' => 3]],
        ];
        $actual = [
            'sections' => 1, 'activities' => [], 'enrolments' => 2,
            'userdata' => ['grades' => 4, 'completion' => 1, 'files' => ['assignsubmission_file' => 1]],
        ];
        $this->assertSame([
            'backup without: blocks',
            'activities without user data in the backup: assign_7',
            'completion 1/2',
            'files assignsubmission_file 1/3',
        ], restore_manifest::compare($expected, $actual));
    }

    /**
     * Without the manifest of the backup the removal of the origin is held (A3).
     */
    public function test_compare_backup_unread(): void {
        $manifest = ['sections' => 1, 'activities' => [], 'enrolments' => null];
        $this->assertSame([], restore_manifest::compare($manifest, $manifest));
        $this->assertSame(
            ['backup manifest not read'],
            restore_manifest::compare(['backup_unread' => true] + $manifest, $manifest)
        );
    }

    /**
     * Only what the restore can bring is compared: no false holds (A4).
     */
    public function test_restorable_userdata(): void {
        global $CFG;
        $this->resetAfterTest();
        $userdata = ['grades' => 3, 'completion' => 2, 'files' => ['badges' => 1, 'course' => 2, 'mod_page' => 1]];

        $new = restore_manifest::restorable_userdata($userdata, backup::TARGET_NEW_COURSE);
        $this->assertSame(2, $new['files']['course']);
        $existing = restore_manifest::restorable_userdata($userdata, backup::TARGET_EXISTING_ADDING);
        $this->assertSame(['mod_page' => 1], $existing['files']);
        $this->assertSame(3, $existing['grades']);

        $CFG->enablecompletion = 0;
        $this->assertSame(0, restore_manifest::restorable_userdata($userdata, backup::TARGET_NEW_COURSE)['completion']);
    }

    /**
     * Privacy decisions locked by the administrator of the origin are kept (A7).
     */
    public function test_backup_keeps_admin_privacy_locks(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        set_config('backup_general_anonymize', 1, 'backup');
        set_config('backup_general_anonymize_locked', 1, 'backup');
        $this->assertSame(1, $this->queued_settings((int) $course->id, 1)['anonymize']);

        set_config('backup_general_users', 0, 'backup');
        set_config('backup_general_users_locked', 1, 'backup');
        $settings = $this->queued_settings((int) $course->id, 1);
        $this->assertSame(0, $settings['users']);
        $this->assertSame(1, $settings['blocks'], 'The content still goes whole');
    }

    /**
     * Enrolments are only turned into manual ones while manual enrolments are enabled (A8).
     */
    public function test_restore_enrolments_need_manual(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            get_admin()->id
        );
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $tempdir = 'lct_' . random_string(8);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), make_backup_temp_directory($tempdir));
        $newid = \restore_dbops::create_new_course('Restored', 'RESTORED2', $course->category);

        $enabled = $this->restore_enrolments_value($tempdir, $newid);
        $this->assertSame(backup::ENROL_NEVER, $enabled);

        $plugins = explode(',', get_config('core', 'enrol_plugins_enabled'));
        set_config('enrol_plugins_enabled', implode(',', array_diff($plugins, ['manual'])));
        \core_plugin_manager::reset_caches();
        $this->assertNotSame(backup::ENROL_NEVER, $this->restore_enrolments_value($tempdir, $newid));
    }

    /**
     * Value of the enrolments setting after the plugin sets up a restore.
     *
     * @param string $tempdir
     * @param int $courseid
     * @return int
     */
    private function restore_enrolments_value(string $tempdir, int $courseid): int {
        $rc = new restore_controller(
            $tempdir,
            $courseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            get_admin()->id,
            backup::TARGET_NEW_COURSE
        );
        coursetransfer_restore::apply_transfer_settings($rc->get_plan(), $tempdir);
        $value = (int) $rc->get_plan()->get_setting('enrolments')->get_value();
        $rc->destroy();
        return $value;
    }
}
