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
 * @package     local_coursetransfer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright   3iPunt <https://www.tresipunt.com/>
 */

namespace local_coursetransfer\external;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

class remove_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function origin_remove_course_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'field' => new external_value(PARAM_TEXT, 'Field'),
                        'value' => new external_value(PARAM_TEXT, 'Value'),
                        'courseid' => new external_value(PARAM_INT, 'Course ID'),
                        'requestid' => new external_value(PARAM_INT, 'Request ID'),
                        'destinysite' => new external_value(PARAM_TEXT, 'Destiny Site')
                )
        );
    }

    /**
     *
     * @param string $field
     * @param string $value
     * @param int $courseid
     * @param int $requestid
     * @param string $destinysite
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_remove_course(string $field, string $value, int $courseid,
            int $requestid, string $destinysite): array {

        global $CFG;

        self::validate_parameters(
                self::origin_remove_course_parameters(), [
                        'field' => $field,
                        'value' => $value,
                        'courseid' => $courseid,
                        'requestid' => $requestid,
                        'destinysite' => $destinysite
                ]
        );

        $errors = [];
        $data = new stdClass();
        $data->requestid = $requestid;
        $data->request_origin_id = null;

        try {
            $course = get_course($courseid);
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $verifydestiny = coursetransfer::verify_destiny_site($destinysite);
                if ($verifydestiny['success']) {
                    $success = true;
                } else {
                    $success = false;
                    $errors[] = $verifydestiny['error'];
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                    [
                            'code' => '200151',
                            'msg' => $e->getMessage()
                    ];
        }

        return [
                'success' => $success,
                'errors' => $errors,
                'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function origin_backup_course_returns(): external_single_structure {
        return new external_single_structure(
                array(
                        'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                        'errors' => new external_multiple_structure(new external_single_structure(
                                array(
                                        'code' => new external_value(PARAM_TEXT, 'Code'),
                                        'msg' => new external_value(PARAM_TEXT, 'Message')
                                ), PARAM_TEXT, 'Errors'
                        )),
                        'data' => new external_single_structure(
                            array(
                                'requestid' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL),
                                'request_origin_id' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL),
                                'course_fullname' => new external_value(PARAM_RAW, 'Origin Course Fullname', VALUE_OPTIONAL),
                                'course_shortname' => new external_value(PARAM_RAW, 'Origin Course Shortname', VALUE_OPTIONAL),
                                'course_idnumber' => new external_value(PARAM_RAW, 'Origin Course ID Number', VALUE_OPTIONAL),
                                'course_category_id' => new external_value(PARAM_INT, 'Category ID', VALUE_OPTIONAL),
                                'course_category_name' => new external_value(PARAM_RAW, 'Category Name', VALUE_OPTIONAL),
                                'course_category_idnumber' => new external_value(PARAM_RAW, 'Category ID Number', VALUE_OPTIONAL),
                            ), PARAM_TEXT, 'Data'
                        )
                )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function origin_remove_category_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'field' => new external_value(PARAM_TEXT, 'Field'),
                        'value' => new external_value(PARAM_TEXT, 'Value'),
                        'catid' => new external_value(PARAM_INT, 'Course Category ID'),
                        'requestid' => new external_value(PARAM_INT, 'Request ID'),
                        'destinysite' => new external_value(PARAM_TEXT, 'Destiny Site')
                )
        );
    }

    /**
     *
     * @param string $field
     * @param string $value
     * @param int $catid
     * @param int $requestid
     * @param string $destinysite
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_remove_category(string $field, string $value, int $catid,
            int $requestid, string $destinysite): array {

        global $CFG;

        self::validate_parameters(
                self::origin_remove_category_parameters(), [
                        'field' => $field,
                        'value' => $value,
                        'courseid' => $catid,
                        'requestid' => $requestid,
                        'destinysite' => $destinysite
                ]
        );

        $errors = [];
        $data = new stdClass();
        $data->requestid = $requestid;
        $data->request_origin_id = null;

        try {
            $category = \core_course_category::get($catid);
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $verifydestiny = coursetransfer::verify_destiny_site($destinysite);
                if ($verifydestiny['success']) {
                    $success = true;
                } else {
                    $success = false;
                    $errors[] = $verifydestiny['error'];
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                    [
                            'code' => '200251',
                            'msg' => $e->getMessage()
                    ];
        }

        return [
                'success' => $success,
                'errors' => $errors,
                'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function origin_remove_category_returns(): external_single_structure {
        return new external_single_structure(
                array(
                        'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                        'errors' => new external_multiple_structure(new external_single_structure(
                                array(
                                        'code' => new external_value(PARAM_TEXT, 'Code'),
                                        'msg' => new external_value(PARAM_TEXT, 'Message')
                                ), PARAM_TEXT, 'Errors'
                        )),
                        'data' => new external_single_structure(
                            array(
                                'requestid' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL),
                                'request_origin_id' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL),
                                'course_category_id' => new external_value(PARAM_INT, 'Category ID', VALUE_OPTIONAL),
                                'course_category_name' => new external_value(PARAM_RAW, 'Category Name', VALUE_OPTIONAL),
                                'course_category_idnumber' => new external_value(PARAM_RAW, 'Category ID Number', VALUE_OPTIONAL),
                            ), PARAM_TEXT, 'Data'
                        )
                )
        );
    }
};
