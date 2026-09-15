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
use backup_general_helper;
use core\task\manager;
use dml_exception;
use local_coursetransfer\task\restore_course_task;
use moodle_exception;
use restore_controller;
use restore_controller_exception;
use stdClass;
use stored_file;
use Throwable;

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
        $resasynctask->set_custom_data(
            ['requestid' => $request->id, 'fileid' => $file->get_id()]
        );
        return manager::queue_adhoc_task($resasynctask);
    }

    /**
     * Create Task to restore Course.
     *
     * @param stdClass $request
     * @param stored_file $file
     * @return bool
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function restore_course(stdClass $request, stored_file $file): bool {
        $filepath = '';
        try {
            $courseid = (int)$request->target_course_id;
            $userid = (int)$request->userid;
            $fullname = $request->origin_course_fullname;
            $shortname = $request->origin_course_shortname;
            $removeenrols = (int)$request->target_remove_enrols;
            $removegroups = (int)$request->target_remove_groups;
            $target = (int)$request->target_target;

            $backuptmpdir = 'local_coursetransfer';

            if (!check_dir_exists($backuptmpdir, true, true)) {
                throw new restore_controller_exception('cannot_create_backup_temp_dir');
            }

            $filepath = restore_controller::get_tempdir_name($file->get_contextid(), $userid);
            $backuptempdir = make_backup_temp_directory('', false);
            $fb = get_file_packer('application/vnd.moodle.backup');

            $fb->extract_to_pathname($file, $backuptempdir . '/' . $filepath . '/');

            if ($target !== backup::TARGET_EXISTING_DELETING && $target !== backup::TARGET_CURRENT_DELETING) {
                $keeprolesenrolments = true;
                $keepgroupsgroupings = true;
            } else {
                $keeprolesenrolments = !($removeenrols === 1);
                $keepgroupsgroupings = !($removegroups === 1);
            }

            $restoreoptions = [
                    'overwrite_conf' => false,
                    'keep_roles_and_enrolments' => $keeprolesenrolments,
                    'keep_groups_and_groupings' => $keepgroupsgroupings,
            ];

            if ($target === backup::TARGET_NEW_COURSE) {
                $restoreoptions['overwrite_conf'] = true;
                $restoreoptions['course_fullname'] = $fullname;
                $restoreoptions['course_shortname'] = $shortname;
            }

            if ($target === backup::TARGET_NEW_COURSE) {
                $target = backup::TARGET_EXISTING_DELETING;
            }

            $rc = new restore_controller(
                $filepath,
                $courseid,
                backup::INTERACTIVE_NO,
                backup::MODE_GENERAL,
                $userid,
                $target
            );

            // Report live restore progress (percent) into the request row (LCT-022).
            $rc->set_progress(new restore_progress((int)$request->id));

            $plan = $rc->get_plan();

            if (!is_null($plan)) {
                foreach ($restoreoptions as $option => $value) {
                    $plan->get_setting($option)->set_status(\base_setting::NOT_LOCKED);
                    $plan->get_setting($option)->set_value($value);
                }

                if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
                    $rc->convert();
                }

                // Execute precheck.
                $resexecute = $rc->execute_precheck();
                $results = $rc->get_precheck_results();
                if ($resexecute) {
                    // Execute restore.
                    $rc->execute_plan();
                    $rc->destroy();
                    return true;
                } else {
                    if (!array_key_exists('errors', $results)) {
                        // Only warnings (e.g. role mapping, question bank category context):
                        // the restore proceeds and completes. Keep the warnings VISIBLE in the
                        // request log (error_message) but WITHOUT an error code, so they are not
                        // lost and the restore is not flagged as failed. Log to cron too.
                        // (LLAOMW-107 / LCT-013.)
                        $warnings = $rc->get_precheck_results();
                        mtrace('local_coursetransfer restore warnings: ' . json_encode($warnings));
                        $request->error_code = null;
                        $request->error_message = 'Warnings en precheck: ' . json_encode($warnings);
                        coursetransfer_request::insert_or_update($request, $request->id);
                        $rc->execute_plan();
                        $rc->destroy();
                        return true;
                    }
                    // Error in precheck.
                    $request->status = coursetransfer_request::STATUS_ERROR;
                    $request->error_code = '104002';
                    $request->error_message = 'Error en precheck: ' . json_encode($rc->get_precheck_results());
                    coursetransfer_request::insert_or_update($request, $request->id);
                    return false;
                }
            } else {
                $request->status = coursetransfer_request::STATUS_ERROR;
                $request->error_code = '104001';
                $request->error_message = 'MBZ file is invalid. Plan is NULL: ' . $file->get_filepath();
                coursetransfer_request::insert_or_update($request, $request->id);
                return false;
            }
        } catch (\Throwable $e) {
            $request->status = coursetransfer_request::STATUS_ERROR;
            $request->error_code = '10400';
            $request->error_message = self::build_restore_error_message($e, $filepath);
            coursetransfer_request::insert_or_update($request, $request->id);
            return false;
        }
    }

    /**
     * Build a readable restore error message, adding which activity modules of the
     * backup are NOT installed on this site (the most common cause of restore
     * failures such as 'not_specified_restore_task'). See LLAOMW-107.
     *
     * @param Throwable $e The exception/error thrown during the restore.
     * @param string $filepath Extracted backup temp dir name (empty if not reached).
     * @return string
     */
    private static function build_restore_error_message(Throwable $e, string $filepath): string {
        global $CFG;
        $msg = get_class($e) . ': ' . $e->getMessage();
        if ($filepath !== '') {
            // Compare the origin Moodle version embedded in the backup with this
            // (target) site. A backup made on a NEWER Moodle than the target is the
            // most common cause of 'not_specified_restore_task': the backup carries
            // restore tasks/steps the target code does not know about.
            try {
                $info = backup_general_helper::get_backup_information($filepath);
            } catch (Throwable $ignored) {
                $info = null;
            }
            if ($info && !empty($info->moodle_version)) {
                $msg .= ' | Origin Moodle ' . ($info->moodle_release ?? '?')
                        . ' (v' . $info->moodle_version . ') -> target Moodle ' . $CFG->release
                        . ' (v' . $CFG->version . ')';
                if ((float)$info->moodle_version > (float)$CFG->version) {
                    $msg .= ' | The origin is NEWER than the target: upgrade the target Moodle to '
                            . 'the origin version (or higher) before transferring.';
                }
            }

            $missing = self::get_missing_target_modules($filepath);
            if (!empty($missing)) {
                $msg .= ' | Missing modules in target (install/upgrade them): ' . implode(', ', $missing);
            } else {
                $msg .= ' | All backup modules are installed in target '
                        . '(likely a subplugin/content incompatibility -- e.g. assignsubmission_*, '
                        . 'qtype_*, qbank_*, format_* present in origin but missing/older in target).';
            }
        }
        return $msg;
    }

    /**
     * List the activity modules present in the backup that are NOT installed on this site.
     *
     * @param string $filepath Extracted backup temp dir name.
     * @return string[] Module names missing on the target.
     * @throws dml_exception
     */
    private static function get_missing_target_modules(string $filepath): array {
        global $DB;
        try {
            $info = backup_general_helper::get_backup_information($filepath);
        } catch (\Throwable $e) {
            return [];
        }
        if (empty($info->activities)) {
            return [];
        }
        $installed = $DB->get_records_menu('modules', null, '', 'name, id');
        $missing = [];
        foreach ($info->activities as $activity) {
            if (!empty($activity->modulename) && !isset($installed[$activity->modulename])) {
                $missing[$activity->modulename] = true;
            }
        }
        return array_keys($missing);
    }
}
