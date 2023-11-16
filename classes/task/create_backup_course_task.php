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
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use async_helper;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_sites;
use moodle_exception;
use stdClass;

/**
 * create_backup_course_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_backup_course_task extends \core\task\asynchronous_backup_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /** @var stdClass Site (host & token) */
    public stdClass $site;

    /**
     * Execute the task.
     *
     */
    public function execute() {
        global $DB;

        $started = time();

        try {

            $this->log_start("Course Transfer Backup Starting...");

            $istest = $this->get_custom_data()->istest;
            $backupid = $this->get_custom_data()->backupid;
            $requestid = $this->get_custom_data()->requestid;
            $siteid = $this->get_custom_data()->destinysite;
            $requestoriginid = $this->get_custom_data()->requestoriginid;

            if (!$backupid) {
                throw new moodle_exception('BACKUP ID NOT FOUND');
            }
            if (!$requestoriginid) {
                throw new moodle_exception('REQUEST ORIGIN ID NOT FOUND');
            }

            $bc = \backup_controller::load_controller($backupid);

            $backuprecord = $DB->get_record('backup_controllers', array('backupid' => $backupid), 'id, controller', MUST_EXIST);
            mtrace('Processing asynchronous backup for backup: ' . $backupid);

            // Get the backup controller by backup id. If controller is invalid, this task can never complete.
            if ($backuprecord->controller === '') {
                mtrace('Bad backup controller status, invalid controller, ending backup execution.');
                return;
            }

            $bc->set_progress(new \core\progress\db_updater($backuprecord->id, 'backup_controllers', 'progress'));

            // Do some preflight checks on the backup.
            $status = $bc->get_status();
            $execution = $bc->get_execution();

            // Check that the backup is in the correct status and
            // that is set for asynchronous execution.
            if ($status == \backup::STATUS_AWAITING && $execution == \backup::EXECUTION_DELAYED) {
                // Execute the backup.
                $bc->execute_plan();

                // Send message to user if enabled.
                $messageenabled = (bool)get_config('backup', 'backup_async_message_users');
                if ($messageenabled && $bc->get_status() == \backup::STATUS_FINISHED_OK) {
                    $asynchelper = new async_helper('backup', $backupid);
                    $asynchelper->send_message();
                }

            } else {
                // If status isn't 700, it means the process has failed.
                // Retrying isn't going to fix it, so marked operation as failed.
                $bc->set_status(\backup::STATUS_FINISHED_ERR);
                mtrace('Bad backup controller status, is: ' . $status . ' should be 700, marking job as failed.');
            }

            $result = $bc->get_results();
            $userid = $bc->get_userid();
            $user = \core_user::get_user($userid);
            $site = coursetransfer_sites::get('destiny', $siteid);
            $request = new request($site);

            $requestorigin = coursetransfer_request::get($requestoriginid);
            if ($bc->get_status() === \backup::STATUS_FINISHED_OK) {
                mtrace('Course Transfer Backup - Creating File ... ');
                $resfileurl = coursetransfer::create_backupfile_url(
                        $bc->get_courseid(), $result['backup_destination'], $requestorigin->id);
                error_log(json_encode($resfileurl));
                if ($resfileurl->success) {
                    mtrace('Course Transfer Backup - Creating File OK');
                    if ($requestorigin) {
                        $requestorigin->fileurl = $resfileurl->fileurl;
                        $requestorigin->origin_backup_url = $resfileurl->fileurl;
                        $requestorigin->origin_backup_size = $resfileurl->filesize;
                        coursetransfer_request::insert_or_update($requestorigin, $requestorigin->id);
                    }
                    if (!$istest) {
                        $res = $request->destiny_backup_course_completed(
                                $resfileurl->fileurl, $requestid, $resfileurl->filesize, $user);
                    }
                    $requestorigin->status = coursetransfer_request::STATUS_COMPLETED;
                } else {
                    mtrace('Course Transfer Backup - Creating File ERROR');
                    if (!$istest) {
                        $res = $request->destiny_backup_course_error(
                                $user, $requestid, $resfileurl->error, [], $resfileurl->filesize);
                    }
                }
            } else {
                if (!$istest) {
                    $res = $request->destiny_backup_course_error($user, $requestid, '', $result);
                }
            }
            if (!$istest) {
                if (!$res->success) {
                    $requestorigin->status = coursetransfer_request::STATUS_ERROR;
                    $requestorigin->error_code = $res->errors[0]->code;
                    $requestorigin->error_message = $res->errors[0]->msg;
                    coursetransfer_request::insert_or_update($requestorigin, $requestorigin->id);
                    mtrace('Course Transfer Backup ERROR: ' . $res->errors[0]->msg);
                    $this->log(json_encode($res));
                }
            }
            coursetransfer_request::insert_or_update($requestorigin, $requestorigin->id);
            $bc->destroy();
        } catch (moodle_exception $e) {
            mtrace('Course Transfer Backup ERROR: ' . $e->getMessage());
            $this->log($e->getMessage());
        }
        $this->log_finish("Course Transfer Backup Finishing...");

        $duration = time() - $started;
        mtrace('Backup completed in: ' . $duration . ' seconds');
    }

}
