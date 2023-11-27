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

use backup;
use dml_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_notification;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_restore;
use moodle_exception;
use restore_controller;

/**
 * restore_course_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_course_task extends \core\task\adhoc_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Execute.
     *
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function execute() {

        $this->log_start("Restore Backup Course Remote Starting...");

        $fileid = $this->get_custom_data()->fileid;
        $requestid = $this->get_custom_data()->requestid;
        $fs = get_file_storage();

        $request = coursetransfer_request::get($requestid);
        $file = $fs->get_file_by_id($fileid);

        if (!$file) {
            $this->log('Restore in Moodle not working beacuse File not found! :' . $fileid);
            $request->status = coursetransfer_request::STATUS_ERROR;
            $request->error_code = '19000';
            $request->error_message = 'Restore in Moodle not working beacuse File not found! :' . $fileid;
            coursetransfer_request::insert_or_update($request, $requestid);
        } else {
            coursetransfer_restore::restore_course($request, $file);

            $this->log('Restore in Moodle Success!');
            $request->status = coursetransfer_request::STATUS_COMPLETED;
            coursetransfer_request::insert_or_update($request, $request->id);

            $site = coursetransfer::get_site_by_url($request->siteurl);

            // Category Request logical.
            if (!is_null($request->request_category_id)) {
                $reqcat = coursetransfer_request::update_status_request_cat($request->request_category_id);
                $this->log('Update Status Category Request');
                $remcaterrormsg = null;
                if ($reqcat->status === coursetransfer_request::STATUS_COMPLETED) {
                    coursetransfer_notification::send_restore_category_completed($request->userid, $request->origin_category_id);
                    if ($reqcat->origin_remove_category) {
                        $this->log('Origin Category Removing...');
                        try {
                            coursetransfer::remove_category($site, $request->origin_category_id);
                        } catch (moodle_exception $e) {
                            $remcaterrormsg = 'Origin Category Removed not working. Error: ' . $e->getMessage();
                            $this->log($remcaterrormsg);
                        }
                    }
                }
            } else {
                coursetransfer_notification::send_restore_course_completed($request->userid, $request->destiny_course_id);
            }

            // Remove origen course logical.
            if ($request->origin_remove_course && !$request->origin_remove_category) {
                $this->log('Origin Course Removing...');
                $remcouerrormsg = null;
                try {
                    coursetransfer::remove_course($site, $request->origin_course_id);
                } catch (moodle_exception $e) {
                    $remcouerrormsg = 'Origin Course Removed not working. Error: ' . $e->getMessage();
                    $this->log($remcouerrormsg);
                }
            }

            $this->log_finish("Restore Backup Course Remote Finishing...");
        }
    }
}
