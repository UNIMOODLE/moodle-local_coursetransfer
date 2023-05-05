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
use local_coursetransfer\coursetransfer_request;
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
    public static function origin_backup_course_parameters(): external_function_parameters {
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
                ), '', VALUE_DEFAULT, [])
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
    public static function origin_backup_course(string $field, string $value,
            int $courseid, int $requestid, bool $enrollusers, array $sections = []): array {

        global $CFG;

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

        $errors = [];
        $data = new stdClass();

        try {
            // TODO. Crear petición de ten la tabla request en direction answer con el destiny_request_id que sería request_id
            $object = new stdClass();
            $object->type = 0;
            $object->siteurl = $CFG->wwwroot;
            $object->direction = 1;
            $object->destiny_request_id = $requestid;
            $object->request_category_id = null;
            $object->origin_course_id = null;
            $object->origin_category_id = null;
            $object->origin_enrolusers = null;
            $object->origin_remove_course = null;
            $object->origin_schedule_datetime = null;
            $object->origin_remove_activities = null;
            $object->origin_activities = null;
            $object->origin_backup_size = null;
            $object->origin_backup_size_estimated = null;
            $object->origin_backup_url = null;
            $object->destiny_course_id = null;
            $object->destiny_category_id = null;
            $object->destiny_remove_activities = null;
            $object->destiny_merge_activities = null;
            $object->destiny_remove_enrols = null;
            $object->destiny_remove_groups = null;
            $object->error_code = null;
            $object->error_message = null;
            $object->userid = null;
            $object->status = null;
            $object->timemodified = null;
            $object->timecreated = null;
            // TODO. Añadimos todos los parámetros.
            coursetransfer_request::insert_or_update($object);
            // TODO. origin_backup_course logic
            coursetransfer::create_task_backup_course($courseid);
            $success = true;
            $data->requestid = $requestid;
            $data->buckupsizeestimated = coursetransfer::get_backup_size_estimated($courseid);;
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '056465',
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
    public static function origin_backup_course_returns(): external_single_structure {
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
                        'requestid' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL),
                        'buckupsizeestimated' => new external_value(PARAM_INT, 'Backup Size Estimated (MB)',
                                VALUE_OPTIONAL ),
                    ), PARAM_TEXT, 'Data'
                )
            )
        );
    }

};