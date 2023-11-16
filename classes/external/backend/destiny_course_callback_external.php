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

namespace local_coursetransfer\external\backend;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_download;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_sites;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/local/coursetransfer/classes/task/download_file_course_task.php');

class destiny_course_callback_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function destiny_backup_course_completed_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'requestid' => new external_value(PARAM_INT, 'Request ID'),
                'backupsize' => new external_value(PARAM_INT, 'Backup Size (Bytes)'),
                'fileurl' => new external_value(PARAM_RAW, 'File URL'),
            )
        );
    }

    /**
     * Notify that the backup is completed
     *
     * @param string $field
     * @param string $value
     * @param int $requestid
     * @param int $backupsize
     * @param string $fileurl
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function destiny_backup_course_completed(string $field, string $value, int $requestid,
                                                           int $backupsize, string $fileurl): array {

        self::validate_parameters(
            self::destiny_backup_course_completed_parameters(), [
                'field' => $field,
                'value' => $value,
                'requestid' => $requestid,
                'backupsize' => $backupsize,
                'fileurl' => $fileurl
            ]
        );

        $errors = [];
        $data = new stdClass();
        $data->id = 0;

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $request = coursetransfer_request::get($requestid);
                if ($request) {
                    $origin = coursetransfer_sites::get_by_host('origin', $request->siteurl);
                    $finalurl = $fileurl . '?token=' . $origin->token;
                    $request->status = coursetransfer_request::STATUS_BACKUP;
                    $request->origin_backup_size = $backupsize;
                    $request->origin_backup_url = $fileurl;
                    $request->fileurl = $finalurl;
                    coursetransfer_request::insert_or_update($request, $requestid);
                    coursetransfer_download::create_task_download_course($request, $finalurl);
                    $data->id = $request->id;
                    $success = true;
                } else {
                    $success = false;
                    $errors[] =
                        [
                            'code' => '130002',
                            'msg' => 'Request id not found: ' . $requestid
                        ];
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '130001',
                    'msg' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'data' => $data,
            'errors' => $errors,
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function destiny_backup_course_completed_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'data' => new external_single_structure(
                        array(
                                'id' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL)
                        )
                ),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message')
                    ), PARAM_TEXT, 'Errors'
                )),
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function destiny_backup_course_error_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'requestid' => new external_value(PARAM_INT, 'Request ID'),
                'backupsize' => new external_value(PARAM_INT, 'Backup Size (Bytes)'),
                'errorcode' => new external_value(PARAM_INT, 'Error Code'),
                'errormsg' => new external_value(PARAM_TEXT, 'Error Message'),
            )
        );
    }

    /**
     * Notify that the backup is completed
     *
     * @param string $field
     * @param string $value
     * @param int $requestid
     * @param int $backupsize
     * @param int $errorcode
     * @param string $errormsg
     *
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function destiny_backup_course_error(string $field, string $value, int $requestid, int $backupsize,
                                                       int $errorcode, string $errormsg): array {
        self::validate_parameters(
            self::destiny_backup_course_error_parameters(), [
                'field' => $field,
                'value' => $value,
                'requestid' => $requestid,
                'backupsize' => $backupsize,
                'errorcode' => $errorcode,
                'errormsg' => $errormsg
            ]
        );

        $data = new stdClass();
        $data->id = 0;
        $errors = [];

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $request = coursetransfer_request::get($requestid);
                if ($request) {
                    $request->origin_backup_size = $backupsize;
                    $request->error_code = $errorcode;
                    $request->error_message = $errormsg;
                    $request->status = coursetransfer_request::STATUS_ERROR;
                    coursetransfer_request::insert_or_update($request, $requestid);
                    $data->id = $requestid;
                    $success = true;
                } else {
                    $success = false;
                    $errors[] =
                        [
                            'code' => '140002',
                            'msg' => 'Request id not found: ' . $requestid
                        ];
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '140001',
                    'msg' => $e->getMessage()
                ];
        }

        return [
                'success' => $success,
                'data' => $data,
                'errors' => $errors,
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function destiny_backup_course_error_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'data' => new external_single_structure(
                        array(
                                'id' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL)
                        )
                ),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'code' => new external_value(PARAM_INT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message')
                    ), PARAM_TEXT, 'Errors'
                )),
            )
        );
    }
}
