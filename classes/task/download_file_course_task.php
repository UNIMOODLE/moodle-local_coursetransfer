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
 * logs_course_response_table
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
 * logs_course_response_table
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
        $requestid = $this->get_custom_data()->requestid;
        $request = coursetransfer_request::get($requestid);

        try {
            $fs = get_file_storage();
            $filecontent = @file_get_contents($fileurle);
            $responsejs = json_decode($filecontent);

            $validatecontent = true;
            if (empty($filecontent)) {
                $validatecontent = false;
                $msg = 'Backup Content empty!';
                $errorcode = '13004';
            } else {
                if (isset($responsejs->error)) {
                    $validatecontent = false;
                    $msg = 'Backup Content Error in Moodle: ' . json_encode($responsejs);
                    $errorcode = '13003';
                }
            }

            if ($validatecontent) {
                $this->log('Backup File Dowload Success!');

                $context = context_course::instance($request->target_course_id);
                $filename = 'local_coursetransfer_' . $request->origin_course_id . '_' . time() . '.mbz';

                $fileinfo = [
                        'contextid' => $context->id,
                        'component' => 'backup',
                        'filearea' => 'course',
                        'itemid' => 0,
                        'filepath' => '/',
                        'filename' => $filename,
                ];
                $file = $fs->create_file_from_string($fileinfo, $filecontent);
                $this->log('Backup File Dowload in Moodle Success!');
                $request->status = coursetransfer_request::STATUS_DOWNLOADED;
                coursetransfer_request::insert_or_update($request, $request->id);
                coursetransfer_restore::create_task_restore_course($request, $file);
            } else {
                $errorlast = error_get_last();
                $error = empty($msg) ? 'HTTP request failed in file download' : $msg;
                $error = isset($errorlast['message']) ? $errorlast['message'] : $error;
                $errorcode = isset($errorcode) ? $errorcode : '13002';
                $errorcode = isset($errorlast['message']) ? '13001' : $errorcode;
                $this->log($error);
                $request->status = coursetransfer_request::STATUS_ERROR;
                $request->error_code = $errorcode;
                $request->error_message = $error;
                coursetransfer_request::insert_or_update($request, $request->id);
            }
        } catch (\Exception $e) {
            $this->log($e->getMessage());
            $request->status = coursetransfer_request::STATUS_ERROR;
            $request->error_code = '13000';
            $request->error_message = $e->getMessage();
            coursetransfer_request::insert_or_update($request, $request->id);
        }
        $this->log_finish("Download File Backup Course Remote and Restore Finishing...");
    }

}
