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
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

class origin_course_backup_external extends external_api {

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
                        'sectionid' => new external_value(PARAM_INT, 'Section ID'),
                        'sectionname' => new external_value(PARAM_TEXT, 'Section Name'),
                        'enabled' => new external_value(PARAM_BOOL, 'Enabled'),
                        'activities' => new external_multiple_structure(new external_single_structure(
                            array(
                                'cmid' => new external_value(PARAM_INT, 'CMID'),
                                'name' => new external_value(PARAM_TEXT, 'Name'),
                                'instanceid' => new external_value(PARAM_INT, 'Instance ID'),
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
     * @param array $sections
     *
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function origin_backup_course(string $field, string $value, int $courseid,int $requestid, bool $enrollusers, array $sections): array
    {

        self::validate_parameters(
            self::origin_backup_course_parameters(), [
                'field' => $field,
                'value' => $value,
                'courseid' => $courseid,
                'requestid' => $requestid,
                'enrollusers' => $enrollusers,
                'sections' => $sections
            ]
        );

        $success = true;
        $errors = [];
        $data = new stdClass();

        try {
            // TODO. origin_backup_course logic
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
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'code' => new external_value(PARAM_INT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message')
                    ), PARAM_TEXT, 'Errors'
                )),
                'data' => new external_single_structure(
                    array(
                        'requestid' => new external_value(PARAM_INT, 'Request ID'),
                        'buckupsizeestimated' => new external_value(PARAM_INT, 'Backup Size Estimated (MB)'),
                    ), PARAM_TEXT, 'Data'
                )
            )
        );
    }

};