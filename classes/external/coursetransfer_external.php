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

class coursetransfer_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function remote_has_user_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'username' => new external_value(PARAM_TEXT, 'Username'),
            )
        );
    }

    /**
     * Check if user exists (remote)
     *
     * @param string $username
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function remote_has_user(string $username): array {

        self::validate_parameters(
            self::remote_has_user_parameters(), [
                'username' => $username,
            ]
        );

        $success = true;
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. remote_has_user logic
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
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
    public static function remote_has_user_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure( new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ),PARAM_TEXT, 'Parameters errors')),
                'data' => new external_single_structure(
                    array(
                        'userid' => new external_value(PARAM_INT, 'User ID'),
                    )
                    ,PARAM_TEXT, 'Data'))
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function origin_has_user_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'username' => new external_value(PARAM_TEXT, 'Username'),
            )
        );
    }

    /**
     * Check if user exists (origin)
     *
     * @param string $username
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function origin_has_user(string $username): array {

        self::validate_parameters(
            self::origin_has_user_parameters(), [
                'username' => $username,
            ]
        );

        $success = true;
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. origin_has_user logic
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
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
    public static function origin_has_user_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure( new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ),PARAM_TEXT, 'Parameters errors')),
                'data' => new external_single_structure(
                    array(
                        'userid' => new external_value(PARAM_INT, 'User ID'),
                    )
                    ,PARAM_TEXT, 'User'))
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function remote_get_courses_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'username' => new external_value(PARAM_TEXT, 'Username'),
            )
        );
    }

    /**
     * Get courses from user (remote)
     *
     * @param string $username
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function remote_get_courses(string $username): array {

        self::validate_parameters(
            self::remote_get_courses_parameters(), [
                'username' => $username,
            ]
        );

        $success = true;
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. origin_has_user logic
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
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
    public static function remote_get_courses_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure( new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ),PARAM_TEXT, 'Parameters errors')),
                'data' => new external_multiple_structure( new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'Course ID'),
                        'fullname' => new external_value(PARAM_TEXT, 'Fullname'),
                        'shortname' => new external_value(PARAM_TEXT, 'Shortname'),
                        'idnumber' => new external_value(PARAM_INT, 'idNumber'),
                        'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                        'categoryname' => new external_value(PARAM_TEXT, 'Category Name')
                    )
                ),PARAM_TEXT, 'Course Info'))
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function origin_get_courses_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'username' => new external_value(PARAM_TEXT, 'Username'),
            )
        );
    }

    /**
     * Get course (origin)
     *
     * @param string $username
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function origin_get_courses(string $username): array {

        self::validate_parameters(
            self::origin_get_courses_parameters(), [
                'username' => $username,
            ]
        );

        $success = true;
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. origin_get_courses logic
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
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
    public static function origin_get_courses_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure( new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ),PARAM_TEXT, 'Parameters errors')),
                'data' => new external_multiple_structure( new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'Course ID'),
                        'fullname' => new external_value(PARAM_TEXT, 'Fullname'),
                        'shortname' => new external_value(PARAM_TEXT, 'Shortname'),
                        'idnumber' => new external_value(PARAM_INT, 'idNumber'),
                        'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                        'categoryname' => new external_value(PARAM_TEXT, 'Category Name')
                    )
                ),PARAM_TEXT, 'Course Info'))
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function remote_get_course_detail_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'username' => new external_value(PARAM_TEXT, 'Username'),
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }

    /**
     * Get courses from user (origin)
     *
     * @param string $username
     * @param int $courseid
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function remote_get_course_detail(string $username, int $courseid): array {

        self::validate_parameters(
            self::remote_get_course_detail_parameters(), [
                'username' => $username,
                'courseid' => $courseid
            ]
        );

        $success = true;
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. remote_get_course_detail logic
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
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
    public static function remote_get_course_detail_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure( new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ),PARAM_TEXT, 'Parameters errors')),
                'data' => new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'Course ID'),
                        'fullname' => new external_value(PARAM_TEXT, 'Fullname'),
                        'shortname' => new external_value(PARAM_TEXT, 'Shortname'),
                        'idnumber' => new external_value(PARAM_INT, 'idNumber'),
                        'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                        'categoryname' => new external_value(PARAM_TEXT, 'Category Name'),
                        'sections' => new external_multiple_structure( new external_single_structure(
                            array(
                                'sectionnum' => new external_value(PARAM_INT, 'Section Number'),
                                'sectionname' => new external_value(PARAM_TEXT, 'Section Name'),
                                'modules' => new external_multiple_structure( new external_single_structure(
                                    array(
                                        'cmid' => new external_value(PARAM_INT, 'CMID'),
                                        'name' => new external_value(PARAM_TEXT, 'Name'),
                                        'instance' => new external_value(PARAM_TEXT, 'Instance'),
                                        'modulename' => new external_value(PARAM_TEXT, 'Module Name')

                                    )
                                ))
                            )
                        ))
                    )
                    ,PARAM_TEXT, 'Course Info'
                )
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function remote_get_course_size_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'username' => new external_value(PARAM_TEXT, 'Username'),
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }

    /**
     * Get course size (MB)
     *
     * @param string $username
     * @param int $courseid
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function remote_get_course_size(string $username, int $courseid): array {

        self::validate_parameters(
            self::remote_get_course_size_parameters(), [
                'username' => $username,
                'courseid' => $courseid
            ]
        );

        $success = true;
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. remote_get_course_size logic
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'param' => 'no_params',
                    'string' => $e->getMessage()
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
    public static function remote_get_course_size_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure( new external_single_structure(
                    array(
                        'param' => new external_value(PARAM_TEXT, 'Param'),
                        'string' => new external_value(PARAM_TEXT, 'String')
                    ),PARAM_TEXT, 'Parameters errors')),
                'data' => new external_single_structure(
                    array(
                        'size' => new external_value(PARAM_INT, 'Size MB'),
                    )
                    ,PARAM_TEXT, 'User'
                )
            )
        );
    }
}
