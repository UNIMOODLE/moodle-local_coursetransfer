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
 * This file defines an adhoc task to create a backup of the curse.
 *
 * @package    mod_forum
 * @copyright  2023 3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_coursetransfer\task;

use async_helper;
use file_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use stdClass;
use stored_file_creation_exception;

/**
 * Create Backup Course Task
 */
class create_backup_course_task extends \core\task\asynchronous_backup_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /** @var stdClass Site (host & token) */
    public stdClass $site;

    /**
     * Execute the task.
     *
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    public function execute() {
        global $DB;
        $started = time();

        $backupid = $this->get_custom_data()->backupid;
        $bc = \backup_controller::load_controller($backupid);

        $this->log_start("Course Transfer Backup Starting...");

        $backupid = $this->get_custom_data()->backupid;
        $backuprecord = $DB->get_record('backup_controllers', array('backupid' => $backupid), 'id, controller', MUST_EXIST);
        mtrace('Processing asynchronous backup for backup: ' . $backupid);

        // Get the backup controller by backup id. If controller is invalid, this task can never complete.
        if ($backuprecord->controller === '') {
            mtrace('Bad backup controller status, invalid controller, ending backup execution.');
            return;
        }
        $bc = \backup_controller::load_controller($backupid);
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

        $result  = $bc->get_results();
        $site = $this->get_custom_data()->destinysite;
        $requestid = $this->get_custom_data()->requestid;
        $request = new request($site);
        try {
            if ($status === \backup::STATUS_FINISHED_OK) {
                $fileurl = coursetransfer::create_backupfile_url($bc->get_courseid(), $result['backup_destination']);
                $res = $request->destiny_backup_course_completed($fileurl, $requestid);
            } else {
                $res = $request->destiny_backup_course_error($requestid, $result);
            }
            $this->log(json_encode($res));
        } catch (moodle_exception $e) {
            $this->log($e->getMessage());
        }

        $this->log_finish("Course Transfer Backup Finishing...");

        $bc->destroy();
        $duration = time() - $started;
        mtrace('Backup completed in: ' . $duration . ' seconds');
    }

}
