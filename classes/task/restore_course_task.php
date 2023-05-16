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

use backup;
use core\session\exception;
use restore_controller;

class restore_course_task extends \core\task\adhoc_task {


    public function execute() {
        // TODO: Implement execute() method.
        // Necesitamos el backup y saber a que curso vamos a restaurarlo.
        $backupdir = $this->get_custom_data()->backupdir;
        $courseid = $this->get_custom_data()->courseid;
        $adminid = $this->get_custom_data()->adminid;
        $restoreoptions = $this->get_custom_data()->restoreoptions;
        try {
            $controller = new restore_controller($backupdir, $courseid,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $adminid,
                backup::TARGET_NEW_COURSE);

            foreach ($restoreoptions as $option => $value) {
                $controller->get_plan()->get_setting($option)->set_value($value);
            }

            if ($controller->get_status() == backup::STATUS_REQUIRE_CONV) {
                $controller->convert();
            }

            // Execute restore.
            $controller->execute_precheck();
            $controller->execute_plan();
            // $transaction->allow_commit();
            $controller->destroy();

            fulldelete($path);

            return $courseid;
        } catch (exception | \restore_controller_exception $e) {
            var_dump($e);
        }
    }
}
