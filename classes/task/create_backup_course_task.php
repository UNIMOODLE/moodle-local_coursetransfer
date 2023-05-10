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

use file_exception;
use local_coursetransfer\coursetransfer;
use stored_file_creation_exception;

/**
 * Create Backup Course Task
 */
class create_backup_course_task extends \core\task\asynchronous_backup_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;


    /**
     * Execute the task.
     *
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    public function execute() {
        $backupid = $this->get_custom_data()->backupid;
        $bc = \backup_controller::load_controller($backupid);

        $this->log_start("Course Transfer Backup");
        parent::execute();
        $result  = $bc->get_results();
        error_log(json_encode($result));
        $file = coursetransfer::create_backupfile_url($bc->get_courseid(), $result['backup_destination']);
        error_log(json_encode($file));
        // TODO. Request a Destino. local_coursetransfer_destiny_backup_course_completed
        // local_coursetransfer_destiny_backup_course_error
        // Cual es la URL de descarga con token.
        $this->log_finish("Course Transfer Backup");
    }

    /**
     * Course Backup.
     */
    public function course_backup() {

    }

}
