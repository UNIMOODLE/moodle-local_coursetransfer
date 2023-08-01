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
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_coursetransfer\task;

use backup;
use local_coursetransfer\coursetransfer_request;
use moodle_exception;
use restore_controller;

class restore_course_task extends \core\task\adhoc_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Execute.
     *
     * @throws moodle_exception
     */
    public function execute() {

        $this->log_start("Restore Backup Course Remote Starting...");

        /** @var restore_controller $rc */
        $rc = $this->get_custom_data()->controller;
        $request = $this->get_custom_data()->request;
        $restoreoptions = $this->get_custom_data()->restoreoptions;
        try {

            foreach ($restoreoptions as $option => $value) {
                $rc->get_plan()->get_setting($option)->set_value($value);
            }

            if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
                $rc->convert();
            }

            // Execute restore.
            $rc->execute_precheck();
            $rc->execute_plan();
            $rc->destroy();

            $this->log('Restore Backup Cours Remote Success!');

        } catch (\Exception $e) {
            $this->log($e->getMessage());
            $request->status = coursetransfer_request::STATUS_ERROR;
            $request->error_code = '200210';
            $request->error_message = $e->getMessage();
            coursetransfer_request::insert_or_update($request, $request->id);
        }

        $this->log_finish("Restore Backup Cours Remote Finishing...");

    }
}
