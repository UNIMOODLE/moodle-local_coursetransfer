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
 * create_backup_course_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use async_helper;
use backup_controller;
use core_user;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_sites;
use moodle_exception;
use stdClass;
use Throwable;

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
     * Local (origin) request id of the task.
     *
     * Every released version (2.1 and 2.2) stores the target's id in requestid and the local one in
     * requestoriginid. The inverse layout (local id in requestid, target's in remoterequestid) is read too,
     * but nothing writes it.
     *
     * @return int
     */
    public function get_local_request_id(): int {
        $data = $this->get_custom_data();
        if (isset($data->remoterequestid)) {
            return (int)($data->requestid ?? 0);
        }
        return (int)($data->requestoriginid ?? 0);
    }

    /**
     * Request id on the target site, to which the callbacks are sent.
     *
     * @return int
     */
    public function get_remote_request_id(): int {
        $data = $this->get_custom_data();
        return (int)($data->remoterequestid ?? $data->requestid ?? 0);
    }

    /**
     * Name shown in the task lists and logs, in the language of the site (LCT-124).
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_create_backup_course', 'local_coursetransfer');
    }

    /**
     * Execute the task.
     *
     */
    public function execute(): void {
        global $DB;

        $started = time();
        $this->log_start("Course Transfer Backup Starting...");

        $istest = !empty($this->get_custom_data()->istest);
        $backupid = $this->get_custom_data()->backupid ?? '';
        $requestid = $this->get_remote_request_id();
        $siteid = (int)($this->get_custom_data()->targetsite ?? 0);
        $requestoriginid = $this->get_local_request_id();
        $bc = null;
        $requestorigin = null;
        $callback = null;

        try {
            $user = core_user::get_user((int)$this->get_userid());
            if (!$istest) {
                $site = coursetransfer_sites::get('target', $siteid);
                $callback = $site ? new request($site) : null;
            }

            $requestorigin = $requestoriginid ? coursetransfer_request::get($requestoriginid) : false;
            if (!$requestorigin) {
                // The request was deleted here: tell the target so it does not wait forever (LCT-036).
                $this->notify_error($callback, $user, $requestid, 'Origin request ' . $requestoriginid . ' not found');
                $this->log_finish('Request ' . $requestoriginid . ' not found: no backup.');
                return;
            }
            $finished = [coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::STATUS_ERROR];
            if (in_array((int)$requestorigin->status, $finished, true)) {
                // A retry must not make a second backup nor call the target again.
                $this->log_finish('Request ' . $requestoriginid . ' already finished (status ' . $requestorigin->status . ').');
                return;
            }
            if (!$backupid) {
                throw new moodle_exception('BACKUP ID NOT FOUND');
            }

            $bc = backup_controller::load_controller($backupid);

            $backuprecord = $DB->get_record(
                'backup_controllers',
                ['backupid' => $backupid],
                'id, controller',
                MUST_EXIST
            );
            mtrace('Processing asynchronous backup for backup: ' . $backupid);

            // Get the backup controller by backup id. If controller is invalid, this task can never complete.
            if ($backuprecord->controller === '') {
                throw new moodle_exception('Bad backup controller status, invalid controller');
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
            $user = core_user::get_user($bc->get_userid());

            if ($bc->get_status() !== \backup::STATUS_FINISHED_OK) {
                // The origin records the failure whatever the target answers (LCT-037).
                $this->fail($requestoriginid, '10201', 'Backup failed: ' . json_encode($result));
                if ($callback) {
                    $res = $callback->target_backup_course_error($user, $requestid, '', $result);
                    $this->check_callback($requestoriginid, $res);
                }
            } else {
                mtrace('Course Transfer Backup - Creating File ... ');
                $resfileurl = coursetransfer::create_backupfile_url(
                    $bc->get_courseid(),
                    $result['backup_destination'],
                    $requestoriginid
                );
                if ($resfileurl->success) {
                    mtrace('Course Transfer Backup - Creating File OK');
                    coursetransfer_request::update_fields($requestoriginid, [
                        'fileurl' => $resfileurl->fileurl,
                        'origin_backup_url' => $resfileurl->fileurl,
                        'origin_backup_size' => $resfileurl->filesize,
                        'status' => coursetransfer_request::STATUS_COMPLETED,
                    ]);
                    if ($callback) {
                        $res = $callback->target_backup_course_completed(
                            $resfileurl->fileurl,
                            $requestid,
                            $resfileurl->filesize,
                            $user
                        );
                        $this->check_callback($requestoriginid, $res);
                    }
                } else {
                    mtrace('Course Transfer Backup - Creating File ERROR');
                    // The origin records the failure whatever the target answers (LCT-049).
                    $this->fail($requestoriginid, '10201', $resfileurl->error);
                    if ($callback) {
                        $res = $callback->target_backup_course_error(
                            $user,
                            $requestid,
                            $resfileurl->error,
                            [],
                            $resfileurl->filesize
                        );
                        $this->check_callback($requestoriginid, $res);
                    }
                }
            }
        } catch (Throwable $e) {
            // Leave the request in a final state and tell the target (LCT-015).
            mtrace('Course Transfer Backup ERROR: ' . $e->getMessage());
            $this->log($e->getMessage());
            if ($requestorigin) {
                $this->fail($requestoriginid, '10200', $e->getMessage());
                $this->notify_error($callback, $user ?? null, $requestid, $e->getMessage());
            }
        } finally {
            if ($bc) {
                $bc->destroy();
            }
        }
        $this->log_finish("Course Transfer Backup Finishing...");

        $duration = time() - $started;
        mtrace('Backup completed in: ' . $duration . ' seconds');
    }

    /**
     * Mark the local request as failed.
     *
     * @param int $requestoriginid
     * @param string $code
     * @param string $message
     */
    protected function fail(int $requestoriginid, string $code, string $message): void {
        try {
            coursetransfer_request::update_fields($requestoriginid, [
                'status' => coursetransfer_request::STATUS_ERROR,
                'error_code' => $code,
                'error_message' => $message,
            ]);
        } catch (Throwable $e) {
            $this->log('Request ' . $requestoriginid . ' not updated: ' . $e->getMessage());
        }
    }

    /**
     * Record on the local request that the target rejected a callback.
     *
     * @param int $requestoriginid
     * @param \local_coursetransfer\api\response $res
     */
    protected function check_callback(int $requestoriginid, $res): void {
        if (!$res->success) {
            $this->fail($requestoriginid, (string)$res->errors[0]->code, $res->errors[0]->msg);
            mtrace('Course Transfer Backup ERROR: ' . $res->errors[0]->msg);
        }
    }

    /**
     * Best-effort error callback to the target.
     *
     * @param request|null $callback
     * @param stdClass|false|null $user
     * @param int $requestid Target request id.
     * @param string $message
     */
    protected function notify_error(?request $callback, $user, int $requestid, string $message): void {
        if (!$callback || !$user || !$requestid) {
            return;
        }
        try {
            $callback->target_backup_course_error($user, $requestid, $message);
        } catch (Throwable $e) {
            $this->log('Error callback not sent: ' . $e->getMessage());
        }
    }
}
