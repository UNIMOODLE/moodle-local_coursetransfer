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

use auth_cncsregister\models\preuser;
use DateTime;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use Matrix\Exception;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

class coursetransfer_external extends external_api
{

    /**
     * Get list of origin sites configured
     *
     * @return array
     */
    public static function origin_sites(): array
    {

        $success = true;
        $message = '';
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. origin_sites logic
        } catch (moodle_exception $e) {
            $success = false;
            $message = $e->getMessage();
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function origin_sites_returns(): external_single_structure
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'message' => new external_value(PARAM_TEXT, 'Message'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ), PARAM_TEXT, 'Parameters errors'
                )),
                'data' => new external_multiple_structure(new external_single_structure(
                    array(
                        'host' => new external_value(PARAM_TEXT, 'Host')
                    ), PARAM_TEXT, 'Sites List'
                ))
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function origin_has_user_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value')
            )
        );
    }

    /**
     * Check if user exists
     *
     * @param string $field
     * @param string $value
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function origin_has_user(string $field, string $value): array
    {

        self::validate_parameters(
            self::origin_has_user_parameters(), [
                'field' => $field,
                'value' => $value
            ]
        );

        $success = true;
        $message = '';
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. origin_has_user logic
        } catch (moodle_exception $e) {
            $success = false;
            $message = $e->getMessage();
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function origin_has_user_returns(): external_single_structure
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'message' => new external_value(PARAM_TEXT, 'Message'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ), PARAM_TEXT, 'Parameters errors'
                )),
                'data' => new external_single_structure(
                    array(
                        'userid' => new external_value(PARAM_INT, 'User ID'),
                        'username' => new external_value(PARAM_TEXT, 'Username'),
                        'firstname' => new external_value(PARAM_TEXT, 'Firstname'),
                        'lastname' => new external_value(PARAM_TEXT, 'Lastname'),
                        'email' => new external_value(PARAM_TEXT, 'Email')
                    ), PARAM_TEXT, 'User information'
                )
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function origin_get_courses_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value')
            )
        );
    }

    /**
     * Get list of courses
     *
     * @param string $field
     * @param string $value
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function origin_get_courses(string $field, string $value): array
    {

        self::validate_parameters(
            self::origin_get_courses_parameters(), [
                'field' => $field,
                'value' => $value
            ]
        );

        $success = true;
        $message = '';
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. origin_get_courses logic
        } catch (moodle_exception $e) {
            $success = false;
            $message = $e->getMessage();
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function origin_get_courses_returns(): external_single_structure
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'message' => new external_value(PARAM_TEXT, 'Message'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ), PARAM_TEXT, 'Parameters errors'
                )),
                'data' => new external_multiple_structure(new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'Course ID'),
                        'fullname' => new external_value(PARAM_TEXT, 'Fullname'),
                        'shortname' => new external_value(PARAM_TEXT, 'Shortname'),
                        'idnumber' => new external_value(PARAM_INT, 'idNumber'),
                        'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                        'categoryname' => new external_value(PARAM_TEXT, 'Category Name')
                    ), PARAM_TEXT, 'Courses information'
                ))
            )
        );
    }


    /**
     * @return external_function_parameters
     */
    public static function origin_get_course_detail_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }

    /**
     * Get course detail
     *
     * @param string $field
     * @param string $value
     * @param int $courseid
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function origin_get_course_detail(string $field, string $value, int $courseid): array
    {

        self::validate_parameters(
            self::origin_get_course_detail_parameters(), [
                'field' => $field,
                'value' => $value,
                'courseid' => $courseid
            ]
        );

        $success = true;
        $message = '';
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. origin_get_course_detail logic
        } catch (moodle_exception $e) {
            $success = false;
            $message = $e->getMessage();
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'message' => $success,
            'errors' => $errors,
            'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function origin_get_course_detail_returns(): external_single_structure
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'message' => new external_value(PARAM_TEXT, 'Message'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ), PARAM_TEXT, 'Parameters errors'
                )),
                'data' => new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'Course ID'),
                        'fullname' => new external_value(PARAM_TEXT, 'Fullname'),
                        'shortname' => new external_value(PARAM_TEXT, 'Shortname'),
                        'idnumber' => new external_value(PARAM_INT, 'idNumber'),
                        'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                        'categoryname' => new external_value(PARAM_TEXT, 'Category Name'),
                        'backupsizeestimated' => new external_value(PARAM_TEXT, 'Backup Size Estimated'),
                        'sections' => new external_multiple_structure(new external_single_structure(
                            array(
                                'sectionnum' => new external_value(PARAM_INT, 'Section Number'),
                                'sectionid' => new external_value(PARAM_INT, 'Section ID'),
                                'sectionname' => new external_value(PARAM_TEXT, 'Section Name'),
                                'activities' => new external_multiple_structure(new external_single_structure(
                                    array(
                                        'cmid' => new external_value(PARAM_INT, 'CMID'),
                                        'name' => new external_value(PARAM_TEXT, 'Name'),
                                        'instanceid' => new external_value(PARAM_INT, 'Instance ID'),
                                        'modulename' => new external_value(PARAM_TEXT, 'Module Name')
                                    )
                                ))
                            )
                        ))
                    ), PARAM_TEXT, 'Course information'
                )
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function origin_backup_course_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'requestid' => new external_value(PARAM_INT, 'Request ID'),
                'enrollusers' => new external_value(PARAM_BOOL, 'Enroll Users'),
                'sections' => new external_multiple_structure(new external_single_structure(
                    array(
                        'sectionnum' => new external_value(PARAM_INT, 'Section Number'),
                        'sectionid' => new external_value(PARAM_TEXT, 'Section ID'),
                        'sectionname' => new external_value(PARAM_TEXT, 'Section Name'),
                        'enabled' => new external_value(PARAM_BOOL, 'Enabled'),
                        'activities' => new external_multiple_structure(new external_single_structure(
                            array(
                                'cmid' => new external_value(PARAM_INT, 'CMID'),
                                'name' => new external_value(PARAM_TEXT, 'Name'),
                                'instanceid' => new external_value(PARAM_TEXT, 'Instance ID'),
                                'modulename' => new external_value(PARAM_TEXT, 'Module Name'),
                                'enabled' => new external_value(PARAM_BOOL, 'Enabled'),
                            )
                        ))
                    )
                ))
            )
        );
    }

    /**
     * Backup of the course in origin
     *
     * @param string $field
     * @param string $value
     * @param int $courseid
     * @param int $requestid
     * @param bool $enrollusers
     *
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function origin_backup_course(string $field, string $value, int $courseid,int $requestid, bool $enrollusers): array
    {

        self::validate_parameters(
            self::origin_backup_course_parameters(), [
                'field' => $field,
                'value' => $value,
                'courseid' => $courseid,
                'requestid' => $requestid,
                'enrollusers' => $enrollusers
            ]
        );

        $success = true;
        $message = '';
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. origin_backup_course logic
        } catch (moodle_exception $e) {
            $success = false;
            $message = $e->getMessage();
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function origin_backup_course_returns(): external_single_structure
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'message' => new external_value(PARAM_TEXT, 'Message'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ), PARAM_TEXT, 'Parameters errors'
                )),
                'data' => new external_single_structure(
                    array(
                        'requestid' => new external_value(PARAM_INT, 'Request ID'),
                        'buckupsizeestimated' => new external_value(PARAM_INT, 'Backup Size Estimated (MB)'),
                    ), PARAM_TEXT, 'Backup information'
                )
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function destiny_backup_course_completed_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'requestid' => new external_value(PARAM_int, 'Request ID'),
                'backupsize' => new external_value(PARAM_INT, 'Backup Size (MB)'),
                'fileurl' => new external_value(PARAM_TEXT, 'File URL'),
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
    public static function destiny_backup_course_completed(string $field, string $value, int $requestid, int $backupsize, string $fileurl): array
    {

        self::validate_parameters(
            self::destiny_backup_course_completed_parameters(), [
                'field' => $field,
                'value' => $value,
                'requestid' => $requestid,
                'backupsize' => $backupsize,
                'fileurl' => $fileurl
            ]
        );

        $success = true;
        $message = '';
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. destiny_backup_course_completed logic
        } catch (moodle_exception $e) {
            $success = false;
            $message = $e->getMessage();
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function destiny_backup_course_completed_returns(): external_single_structure
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'message' => new external_value(PARAM_TEXT, 'Message'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ), PARAM_TEXT, 'Parameters errors'
                )),
                'data' => new external_value(PARAM_TEXT, 'Data')
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function destiny_backup_course_error_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'requestid' => new external_value(PARAM_int, 'Request ID'),
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
     * @param int $errorcode
     * @param string $errormsg
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function destiny_backup_course_error(string $field, string $value, int $requestid, int $errorcode, string $errormsg): array
    {

        self::validate_parameters(
            self::destiny_backup_course_error_parameters(), [
                'field' => $field,
                'value' => $value,
                'requestid' => $requestid,
                'errorcode' => $errorcode,
                'errormsg' => $errormsg
            ]
        );

        $success = true;
        $message = '';
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. destiny_backup_course_error logic
        } catch (moodle_exception $e) {
            $success = false;
            $message = $e->getMessage();
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function destiny_backup_course_error_returns(): external_single_structure
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'message' => new external_value(PARAM_TEXT, 'Message'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ), PARAM_TEXT, 'Parameters errors'
                )),
                'data' => new external_value(PARAM_TEXT, 'Data')
            )
        );
    }
}