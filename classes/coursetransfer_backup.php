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
     * @param stdClass $destinysite
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
            int $courseid, int $userid, stdClass $destinysite, int $requestid, int $requestoriginid,
            array $sections, int $rootusers = 0, int $nextruntime = null, bool $istest = false): bool {
        $bc = new backup_controller(
                backup::TYPE_1COURSE, $courseid,
                backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO,
                backup::MODE_GENERAL, $userid,
                backup::RELEASESESSION_YES);
        $bc->set_status(backup::STATUS_AWAITING);
        $bc->set_status(backup::STATUS_AWAITING);
        $bc->get_plan()->get_setting('users')->set_status(base_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($rootusers);
        $bc->get_plan()->get_setting('role_assignments')->set_status(base_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('role_assignments')->set_value($rootusers);
        $bc->get_plan()->get_setting('comments')->set_status(base_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('comments')->set_value($rootusers);
        $bc->get_plan()->get_setting('badges')->set_status(base_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('badges')->set_value($rootusers);
        $bc->get_plan()->get_setting('userscompletion')->set_status(base_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('userscompletion')->set_value($rootusers);
        $bc->get_plan()->get_setting('groups')->set_status(base_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('groups')->set_value($rootusers);

        self::set_value_settings_section_activities($bc, $courseid, $rootusers, $sections);

        $bc->set_execution(backup::EXECUTION_DELAYED);
        $bc->save_controller();
        $asynctask = new create_backup_course_task();
        $asynctask->set_blocking(false);
        if (!is_null($nextruntime)) {
            $asynctask->set_next_run_time($nextruntime);
        }
        $backupid = $bc->get_backupid();

        $payload = [
                'backupid' => $backupid,
                'destinysite' => $destinysite->id,
                'requestid' => $requestid,
                'requestoriginid' => $requestoriginid,
                'istest' => $istest,
        ];
        $asynctask->set_custom_data($payload);
        $asynctask->set_userid($userid);
        return \core\task\manager::queue_adhoc_task($asynctask);
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
    public static function set_value_settings_section_activities(backup_controller $bc, int $courseid, int $rootusers,
            array $sectionsselected) {
        if (!empty($sectionsselected)) {
            $bc->get_plan()->set_excluding_activities();
            $modinfo = get_fast_modinfo($courseid);
            $sections = $modinfo->get_section_info_all();
            $cms = $modinfo->get_cms();
            foreach ($sections as $section) {
                $value = self::section_is_included($section, $sectionsselected);
                $rootusers = ($value === 1) && ($rootusers === 1);
                $nameincluded = 'section_' . $section->id . '_included';
                $bc->get_plan()->get_setting($nameincluded)->set_value($value);
                $nameuserinfo = 'section_' . $section->id . '_userinfo';
                $bc->get_plan()->get_setting($nameuserinfo)->set_value($rootusers);
            }
            foreach ($cms as $cm) {
                if (!$cm->deletioninprogress) {
                    $value = self::cm_is_included($cm, $sectionsselected);
                    $rootusers = ($value === 1) && ($rootusers === 1);
                    $nameincluded = $cm->modname . '_' . $cm->id . '_included';
                    $bc->get_plan()->get_setting($nameincluded)->set_value($value);
                    $nameuserinfo = $cm->modname . '_' . $cm->id . '_userinfo';
                    $bc->get_plan()->get_setting($nameuserinfo)->set_value($rootusers);
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
