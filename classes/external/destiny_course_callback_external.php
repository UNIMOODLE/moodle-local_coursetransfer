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
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

class destiny_course_callback_external extends external_api
{
    /**
     * @return external_function_parameters
     */
    public static function destiny_backup_course_completed_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'requestid' => new external_value(PARAM_INT, 'Request ID'),
                'backupsize' => new external_value(PARAM_INT, 'Backup Size (MB)'),
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
        $errors = [];

        try {
            // TODO. destiny_backup_course_completed logic
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => 'no_code',
                    'string' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'errors' => $errors,
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
                        'code' => new external_value(PARAM_INT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message')
                    ), PARAM_TEXT, 'Errors'
                )),
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
                'requestid' => new external_value(PARAM_INT, 'Request ID'),
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

        try {
            // TODO. destiny_backup_course_error logic
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => 'no_code',
                    'msg' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'errors' => $errors,
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
                        'code' => new external_value(PARAM_INT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message')
                    ), PARAM_TEXT, 'Errors'
                )),
            )
        );
    }
}