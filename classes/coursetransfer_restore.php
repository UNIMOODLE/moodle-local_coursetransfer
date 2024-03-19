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
 * Coursetransfer Restore.
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
use dml_exception;
use local_coursetransfer\task\create_backup_course_task;
use local_coursetransfer\task\restore_course_task;
use moodle_exception;
use restore_controller;
use section_info;
use stdClass;
use stored_file;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/local/coursetransfer/classes/task/create_backup_course_task.php');

/**
 * coursetransfer_restore
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer_restore {

    /**
     * Create task restore course.
     *
     * @param stdClass $request
     * @param stored_file $file
     * @return bool
     */
    public static function create_task_restore_course(stdClass $request, stored_file $file): bool {
        $resasynctask = new restore_course_task();
        $resasynctask->set_blocking(false);
        $resasynctask->set_custom_data(
                ['requestid' => $request->id, 'fileid' => $file->get_id()]
        );
        return \core\task\manager::queue_adhoc_task($resasynctask);
    }

    /**
     * Create Task to restore Course.
     *
     * @param stdClass $request
     * @param stored_file $file
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function restore_course(stdClass $request, stored_file $file) {
        try {
            $courseid = (int)$request->destiny_course_id;
            $userid = (int)$request->userid;
            $fullname = $request->origin_course_fullname;
            $shortname = $request->origin_course_shortname;
            $removeenrols = (int)$request->destiny_remove_enrols;
            $removegroups = (int)$request->destiny_remove_groups;
            $target = (int)$request->destiny_target;

            $backuptmpdir = 'local_coursetransfer';

            if (!check_dir_exists($backuptmpdir, true, true)) {
                throw new \restore_controller_exception('cannot_create_backup_temp_dir');
            }

            $filepath = restore_controller::get_tempdir_name($file->get_contextid(), $userid);
            $backuptempdir = make_backup_temp_directory('', false);
            $fb = get_file_packer('application/vnd.moodle.backup');

            $fb->extract_to_pathname($file, $backuptempdir . '/' . $filepath . '/');

            if ($target !== backup::TARGET_EXISTING_DELETING && $target !== backup::TARGET_CURRENT_DELETING) {
                $keeprolesenrolments = 0;
                $keepgroupsgroupings = 0;
            } else {
                $keeprolesenrolments = $removeenrols === 1 ? 0 : 1;
                $keepgroupsgroupings = $removegroups === 1 ? 0 : 1;
            }

            $restoreoptions = [
                    'overwrite_conf' => 0,
                    'keep_roles_and_enrolments' => $keeprolesenrolments,
                    'keep_groups_and_groupings' => $keepgroupsgroupings,
            ];

            if ($target === backup::TARGET_NEW_COURSE) {
                $restoreoptions['overwrite_conf'] = 1;
                $restoreoptions['course_fullname'] = $fullname;
                $restoreoptions['course_shortname'] = $shortname;
            }

            if ($target === backup::TARGET_NEW_COURSE) {
                $target = backup::TARGET_EXISTING_DELETING;
            }

            $rc = new restore_controller($filepath, $courseid,
                    backup::INTERACTIVE_NO, backup::MODE_GENERAL, $userid, $target);

            $plan = $rc->get_plan();
            if (!is_null($plan)) {
                foreach ($restoreoptions as $option => $value) {
                    $plan->get_setting($option)->set_status(\base_setting::NOT_LOCKED);
                    $plan->get_setting($option)->set_value($value);
                }

                if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
                    $rc->convert();
                }

                // Execute restore.
                $rc->execute_precheck();
                $rc->execute_plan();
                $rc->destroy();
                return true;
            } else {
                $request->status = coursetransfer_request::STATUS_ERROR;
                $request->error_code = '104001';
                $request->error_message = 'MBZ file is invalid. Plan is NULL: ' . $file->get_filepath();
                coursetransfer_request::insert_or_update($request, $request->id);
                return false;
            }

        } catch (\Exception $e) {
            $request->status = coursetransfer_request::STATUS_ERROR;
            $request->error_code = '10400';
            $request->error_message = $e->getMessage();
            coursetransfer_request::insert_or_update($request, $request->id);
            return false;
        }
    }

}
