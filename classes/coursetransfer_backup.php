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
 * Coursetransfer Backup.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use backup;
use backup_controller;
use base_plan_exception;
use base_setting;
use base_setting_exception;
use cm_info;
use core\task\manager;
use local_coursetransfer\task\create_backup_course_task;
use moodle_exception;
use section_info;
use stdClass;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/local/coursetransfer/classes/task/create_backup_course_task.php');

/**
 * coursetransfer_backup
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer_backup {
    /**
     * Create Task to back up of Course.
     *
     * @param int $courseid
     * @param int $userid
     * @param stdClass $targetsite
     * @param int $requestid
     * @param int $requestoriginid
     * @param array $sections
     * @param int $rootusers
     * @param int|null $nextruntime
     * @param bool $istest
     * @return bool
     * @throws base_plan_exception
     * @throws base_setting_exception
     * @throws moodle_exception
     */
    public static function create_task_backup_course(
        int $courseid,
        int $userid,
        stdClass $targetsite,
        int $requestid,
        int $requestoriginid,
        array $sections,
        int $rootusers = 0,
        ?int $nextruntime = null,
        bool $istest = false
    ): bool {
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $courseid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $userid,
            backup::RELEASESESSION_YES
        );
        $bc->set_status(backup::STATUS_AWAITING);
        $plan = $bc->get_plan();
        // The core backup_check locks what the user may not back up (e.g. without moodle/backup:userinfo):
        // keep those locks, as the manual backup form does (TIPCOUTRAN-10, N4).
        if ($plan->get_setting('users')->get_status() === base_setting::LOCKED_BY_PERMISSION) {
            $rootusers = 0;
        }
        self::apply_root_settings($plan, $rootusers);
        // What finally goes: the administrator may have locked user data off (A7).
        $rootusers = (int)(bool)$plan->get_setting('users')->get_value();

        if (empty($sections)) {
            // Whole course: every section and activity with its user data, whatever the site defaults (LCT-063).
            self::apply_element_settings($plan, $rootusers);
        } else {
            self::set_value_settings_section_activities($bc, $courseid, $rootusers, $sections);
        }

        // Core defaults 'filename' to 'backup.mbz' and stores the backup in a file area shared by
        // every request of the same user, deleting any file already stored under that name. Without
        // a unique name per request, two overlapping backups would destroy each other's file.
        $filename = 'local_coursetransfer_' . $requestoriginid . '_' . $courseid . '.mbz';
        $bc->get_plan()->get_setting('filename')->set_value($filename);

        $bc->set_execution(backup::EXECUTION_DELAYED);
        $bc->save_controller();
        $asynctask = new create_backup_course_task();
        if (!is_null($nextruntime)) {
            $asynctask->set_next_run_time($nextruntime);
        }
        $backupid = $bc->get_backupid();

        $payload = [
                'backupid' => $backupid,
                'targetsite' => $targetsite->id,
                'requestid' => $requestid,
                'requestoriginid' => $requestoriginid,
                'istest' => $istest,
        ];
        $asynctask->set_custom_data($payload);
        $asynctask->set_userid($userid);
        return manager::queue_adhoc_task($asynctask);
    }

    /**
     * What a transfer backs up and restores, whatever the general backup and restore defaults of the site (LCT-063).
     *
     * A transfer is a migration: the origin may be removed afterwards, so it carries the whole course, as the
     * backup form with every option ticked. With users, every user setting too (logs only while transferlogs
     * is on); without users, all the content and no user data.
     *
     * @param int $rootusers 1 to include user data.
     * @return array Setting name => value, users first (the others depend on it).
     */
    public static function root_settings(int $rootusers): array {
        $values = ['users' => $rootusers, 'anonymize' => 0];
        $content = ['activities', 'blocks', 'files', 'filters', 'calendarevents', 'badges', 'questionbank', 'groups',
            'competencies', 'customfield', 'contentbankcontent', 'legacyfiles', 'permissions'];
        foreach ($content as $name) {
            $values[$name] = 1;
        }
        foreach (['role_assignments', 'comments', 'userscompletion', 'grade_histories', 'xapistate'] as $name) {
            $values[$name] = $rootusers;
        }
        $logs = get_config('local_coursetransfer', 'transferlogs');
        $values['logs'] = ($logs === false || $logs === '' || (bool)$logs) ? $rootusers : 0;
        return $values;
    }

    /**
     * Apply root_settings() to a backup or restore plan. What the core locks by permission is kept.
     *
     * @param \base_plan $plan
     * @param int $rootusers 1 to include user data.
     * @param array|null $available Restore: root settings of the backup; a setting it lacks is not turned on.
     */
    public static function apply_root_settings(\base_plan $plan, int $rootusers, ?array $available = null): void {
        foreach (self::root_settings($rootusers) as $name => $value) {
            if (!$plan->setting_exists($name)) {
                continue;
            }
            if ($value && $available !== null && empty($available[$name])) {
                continue;
            }
            if ($name === 'competencies' && !\core_competency\api::is_enabled()) {
                // Disabled on the site: the core locks it and there is nothing to carry.
                continue;
            }
            $setting = $plan->get_setting($name);
            if ($setting->get_status() === base_setting::LOCKED_BY_PERMISSION) {
                continue;
            }
            if (in_array($name, ['users', 'anonymize'], true) && $setting->get_status() === base_setting::LOCKED_BY_CONFIG) {
                // A privacy decision locked by the administrator of the site (user data, anonymisation) is kept (A7).
                continue;
            }
            $setting->set_status(base_setting::NOT_LOCKED);
            $setting->set_value($value);
        }
    }

    /**
     * Set the included and userinfo settings of every section and activity of a plan (LCT-063).
     *
     * The general defaults of the site reach them too: with "users" off by default, the core leaves
     * every activity without its user data even after the root setting is turned on (turning a parent on
     * only unlocks its dependants, it does not change their value).
     *
     * @param \base_plan $plan Backup or restore plan.
     * @param int $rootusers 1 to include user data.
     * @param array|null $values Setting name => value to apply (restore: what the backup carries); null for all.
     */
    public static function apply_element_settings(\base_plan $plan, int $rootusers, ?array $values = null): void {
        // Included first: an activity left out locks its userinfo.
        foreach (['included', 'userinfo'] as $kind) {
            foreach ($plan->get_settings() as $name => $setting) {
                if (!str_ends_with($name, '_' . $kind)) {
                    continue;
                }
                if ($values !== null) {
                    if (!array_key_exists($name, $values)) {
                        continue;
                    }
                    $value = (int)!empty($values[$name]);
                } else {
                    $value = 1;
                }
                if ($kind === 'userinfo') {
                    $value = $value && $rootusers ? 1 : 0;
                }
                if ($setting->get_status() === base_setting::LOCKED_BY_PERMISSION || (int)$setting->get_value() === $value) {
                    continue;
                }
                $setting->set_status(base_setting::NOT_LOCKED);
                $setting->set_value($value);
            }
        }
    }

    /**
     * Set Value in settings for sections and activities.
     *
     * @param backup_controller $bc
     * @param int $courseid
     * @param int $rootusers
     * @param array $sectionsselected
     * @throws base_plan_exception
     * @throws base_setting_exception
     * @throws moodle_exception
     */
    public static function set_value_settings_section_activities(
        backup_controller $bc,
        int $courseid,
        int $rootusers,
        array $sectionsselected
    ): void {
        global $DB;
        if (!empty($sectionsselected)) {
            $bc->get_plan()->set_excluding_activities();
            $modinfo = get_fast_modinfo($courseid);
            $sections = $modinfo->get_section_info_all();
            $cms = $modinfo->get_cms();
            // User data per element is computed into a local value: overwriting $rootusers turned it
            // into a boolean, so from the second element on every section and activity lost its user
            // data (submissions, posts, attempts) without any error (LCT-033).
            // Subsections (Moodle 4.5+, #58): as in the native backup, the delegated section and its
            // activities follow the subsection activity. Included, its content goes with it (an activity of
            // it missing from the selection too); left out, nothing of it goes, so no activity ends up
            // restored outside its subsection.
            $parents = [];
            foreach ($sections as $section) {
                // Resolved from the database, not from modinfo: a disabled module is not in modinfo, and the
                // backup still carries its section.
                if ($section->is_delegated() && str_starts_with((string)$section->component, 'mod_')) {
                    $parentcmid = $DB->get_field_sql(
                        'SELECT cm.id
                           FROM {course_modules} cm
                           JOIN {modules} m ON m.id = cm.module
                          WHERE cm.course = :course AND m.name = :modname AND cm.instance = :instance',
                        ['course' => $courseid, 'modname' => substr($section->component, 4), 'instance' => (int)$section->itemid]
                    );
                    if ($parentcmid) {
                        $parents[$section->id] = self::cmid_is_included((int)$parentcmid, $sectionsselected);
                    }
                }
            }
            foreach ($sections as $section) {
                $value = $parents[$section->id] ?? self::section_is_included($section, $sectionsselected);
                $userinfo = ($value === 1 && $rootusers === 1) ? 1 : 0;
                $nameincluded = 'section_' . $section->id . '_included';
                $bc->get_plan()->get_setting($nameincluded)->set_value($value);
                $nameuserinfo = 'section_' . $section->id . '_userinfo';
                $bc->get_plan()->get_setting($nameuserinfo)->set_value($userinfo);
            }
            foreach ($cms as $cm) {
                if (!$cm->deletioninprogress) {
                    $value = self::cm_is_included($cm, $sectionsselected);
                    $parent = $parents[$cm->section] ?? null;
                    if ($parent !== null) {
                        $value = ($parent === 1 && ($value === 1 || !self::cm_is_listed($cm, $sectionsselected))) ? 1 : 0;
                    }
                    $userinfo = ($value === 1 && $rootusers === 1) ? 1 : 0;
                    $nameincluded = $cm->modname . '_' . $cm->id . '_included';
                    $bc->get_plan()->get_setting($nameincluded)->set_value($value);
                    $nameuserinfo = $cm->modname . '_' . $cm->id . '_userinfo';
                    $bc->get_plan()->get_setting($nameuserinfo)->set_value($userinfo);
                }
            }
        }
    }

    /**
     * Section is included?
     *
     * @param section_info $section
     * @param array $sections
     * @return int
     */
    protected static function section_is_included(section_info $section, array $sections): int {
        foreach ($sections as $s) {
            if ((int)$section->id === (int)$s['sectionid']) {
                if ((int)$s['selected'] === 1) {
                    return 1;
                }
            }
        }
        return 0;
    }

    /**
     * Is the course module with this id selected?
     *
     * @param int $cmid
     * @param array $sections
     * @return int
     */
    protected static function cmid_is_included(int $cmid, array $sections): int {
        foreach ($sections as $s) {
            foreach ($s['activities'] ?? [] as $activity) {
                if ($cmid === (int)$activity['cmid'] && (int)$activity['selected'] === 1) {
                    return 1;
                }
            }
        }
        return 0;
    }

    /**
     * Whether the selection mentions this course module at all.
     *
     * @param cm_info $cm
     * @param array $sections
     * @return bool
     */
    protected static function cm_is_listed(cm_info $cm, array $sections): bool {
        foreach ($sections as $s) {
            foreach ($s['activities'] ?? [] as $activity) {
                if ((int)$cm->id === (int)$activity['cmid']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Course Module is included?
     *
     * @param cm_info $cm
     * @param array $sections
     * @return int
     */
    protected static function cm_is_included(cm_info $cm, array $sections): int {
        foreach ($sections as $s) {
            foreach ($s['activities'] as $activity) {
                if ((int)$cm->id === (int)$activity['cmid']) {
                    if ((int)$activity['selected'] === 1) {
                        return 1;
                    }
                }
            }
        }
        return 0;
    }
}
