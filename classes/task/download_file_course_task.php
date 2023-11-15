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

use context_course;
use dml_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_restore;
use moodle_exception;

/**
 * download_file_course_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class download_file_course_task extends \core\task\adhoc_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Execute.
     *
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function execute() {

        $this->log_start("Download File Backup Course Remote and Restore Starting...");
        $fileurle = $this->get_custom_data()->fileurl;
        $request = $this->get_custom_data()->request;

        try {

            $filecontent = file_get_contents($fileurle);

            $this->log('Backup File Dowload Success!');

            $fs = get_file_storage();

            $context = context_course::instance($request->destiny_course_id);
            $filename = 'local_coursetransfer_' . $request->origin_course_id . '_' . time() . '.mbz';

            $fileinfo = array(
                    'contextid' => $context->id,
                    'component' => 'backup',
                    'filearea' => 'course',
                    'itemid' => 0,
                    'filepath' => '/',
                    'filename' => $filename);

            $file = $fs->create_file_from_string($fileinfo, $filecontent);

            $this->log('Backup File Dowload in Moodle Success!');

            $request->status = coursetransfer_request::STATUS_DOWNLOADED;
            coursetransfer_request::insert_or_update($request, $request->id);

            coursetransfer_restore::create_task_restore_course($request, $file);

            $this->log('Restore in Moodle Success!');
            $request->status = coursetransfer_request::STATUS_COMPLETED;
            coursetransfer_request::insert_or_update($request, $request->id);

            $site = coursetransfer::get_site_by_url($request->siteurl);

            // Category Request logical.
            if (!is_null($request->request_category_id)) {
                $reqcat = coursetransfer_request::update_status_request_cat($request->request_category_id);
                $this->log('Update Status Category Request');
                $remcaterrorcode = null;
                $remcaterrormsg = null;
                if ($reqcat->status === coursetransfer_request::STATUS_COMPLETED) {
                    if ($reqcat->origin_remove_category) {
                        $this->log('Origin Category Removing...');
                        try {
                            $resremcat = coursetransfer::remove_category($site, $request->origin_category_id);
                            if (isset($resremcat['data']['requestid'])) {
                                $remcatrequestid = $resremcat['data']['requestid'];
                                $requestremcat = coursetransfer_request::get($remcatrequestid);
                                if (!$resremcat['success']) {
                                    $errors = $resremcat['errors'];
                                    $requestremcat->status = coursetransfer_request::STATUS_ERROR;
                                    $requestremcat->error_code = $errors[0]->code;
                                    $requestremcat->error_message = $errors[0]->msg;
                                    $remcaterrormsg = 'Origin Category Removed not working. Error: ' . json_encode($errors);
                                    $this->log($remcaterrormsg);
                                    $remcaterrorcode = $requestremcat->error_code;
                                } else {
                                    $request->status = coursetransfer_request::STATUS_COMPLETED;
                                    $this->log('Origin Category Removed!');
                                }
                                coursetransfer_request::insert_or_update($requestremcat, $remcatrequestid);
                            } else {
                                $remcaterrorcode = '640002';
                                $remcaterrormsg = 'Origin Category Removed not working. ERROR NOT CONTROLLED';
                                $this->log($remcaterrormsg);
                            }
                        } catch (moodle_exception $e) {
                            $remcaterrorcode = '640001';
                            $remcaterrormsg = 'Origin Category Removed not working. Error: ' . $e->getMessage();
                            $this->log($remcaterrormsg);
                        }
                    }
                }
                $request->error_code = $remcaterrorcode;
                $request->error_message = $remcaterrormsg;
            }

            // Remove origen course logical.
            if ($request->origin_remove_course && !$request->origin_remove_category) {
                $this->log('Origin Course Removing...');
                $remcouerrorcode = null;
                $remcouerrormsg = null;
                try {
                    $resrem = coursetransfer::remove_course($site, $request->origin_course_id);
                    if (isset($resrem['data']['requestid'])) {
                        $requesremtid = $resrem['data']['requestid'];
                        $requestrem = coursetransfer_request::get($requesremtid);
                        if (!$resrem['success']) {
                            $errors = $resrem['errors'];
                            $requestrem->status = coursetransfer_request::STATUS_ERROR;
                            $requestrem->error_code = $errors[0]->code;
                            $requestrem->error_message = $errors[0]->msg;
                            $remcouerrormsg = 'Origin Course Removed not working. Error: ' . json_encode($errors);
                            $this->log($remcouerrormsg);
                            $remcouerrorcode = $requestrem->error_code;
                        } else {
                            $requestrem->status = coursetransfer_request::STATUS_COMPLETED;
                            $this->log('Origin Course Removed!');
                        }
                        coursetransfer_request::insert_or_update($requestrem, $requesremtid);
                    } else {
                        $remcouerrorcode = '640004';
                        $remcouerrormsg = 'Origin Course Removed not working. ERROR NOT CONTROLLED';
                        $this->log($remcouerrormsg);
                    }
                } catch (moodle_exception $e) {
                    $remcouerrorcode = '640003';
                    $remcouerrormsg = 'Origin Course Removed not working. Error: ' . $e->getMessage();
                    $this->log($remcouerrormsg);
                }
                $request->error_code = $remcouerrorcode;
                $request->error_message = $remcouerrormsg;
            }

        } catch (\Exception $e) {
            $this->log($e->getMessage());
            $request->status = coursetransfer_request::STATUS_ERROR;
            $request->error_code = '140000';
            $request->error_message = $e->getMessage();
            coursetransfer_request::insert_or_update($request, $request->id);
        }

        $this->log_finish("Download File Backup Course Remote and Restore Finishing...");
    }

}
