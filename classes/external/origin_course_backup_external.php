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

use context_course;
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
                'destinycourseid' => new external_value(PARAM_INT, 'Destiny Course ID'),
                'requestid' => new external_value(PARAM_INT, 'Request ID'),
                'destinysite' => new external_value(PARAM_TEXT, 'Destiny Site'),
                'configuration' => new external_single_structure(
                        array(
                               'destiny_merge_activities' => new external_value(PARAM_BOOL, 'Destiny Merge Activities'),
                               'destiny_remove_enrols' => new external_value(PARAM_BOOL, 'Destiny Remove Enrols'),
                               'destiny_remove_groups' => new external_value(PARAM_BOOL, 'Destiny Remove Groups'),
                               'destiny_remove_activities' => new external_value(PARAM_BOOL, 'Destiny Remove Activities'),
                               'origin_remove_course' => new external_value(PARAM_BOOL,
                                               'Origin Remove Course', VALUE_DEFAULT, false),
                               'origin_enrol_users' => new external_value(PARAM_BOOL,
                                               'Origin Enrol Users', VALUE_DEFAULT, false),
                               'destiny_notremove_activities' => new external_value(PARAM_TEXT,
                                               'Destiny Not Remove Activities by commas', VALUE_DEFAULT, '')
                        )
                ),
                'sections' => new external_multiple_structure(new external_single_structure(
                    array(
                        'sectionnum' => new external_value(PARAM_INT, 'Section Number'),
                        'sectionid' => new external_value(PARAM_INT, 'Section ID'),
                        'sectionname' => new external_value(PARAM_TEXT, 'Section Name'),
                        'selected' => new external_value(PARAM_BOOL, 'Enabled'),
                        'activities' => new external_multiple_structure(new external_single_structure(
                            array(
                                'cmid' => new external_value(PARAM_INT, 'CMID'),
                                'name' => new external_value(PARAM_TEXT, 'Name'),
                                'instance' => new external_value(PARAM_INT, 'Instance ID'),
                                'modname' => new external_value(PARAM_TEXT, 'Module Name'),
                                'selected' => new external_value(PARAM_BOOL, 'Selected'),
                            )
                        ), '', VALUE_DEFAULT, [])
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
     * @param int $destinycourseid
     * @param int $requestid
     * @param string $destinysite
     * @param array $configuration
     * @param array $sections
     *
     *
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_backup_course(string $field, string $value, int $courseid, int $destinycourseid,
            int $requestid, string $destinysite, array $configuration, array $sections = []): array {

        global $CFG;

        self::validate_parameters(
            self::origin_backup_course_parameters(), [
                'field' => $field,
                'value' => $value,
                'courseid' => $courseid,
                'destinycourseid' => $destinycourseid,
                'requestid' => $requestid,
                'destinysite' => $destinysite,
                'configuration' => $configuration,
                'sections' => $sections,
            ]
        );

        $errors = [];
        $data = new stdClass();
        $data->requestid = $requestid;
        $data->request_origin_id = null;
        $data->origin_backup_size_estimated = null;

        try {
            $course = get_course($courseid);
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $verifydestiny = coursetransfer::verify_destiny_site($destinysite);
                if ($verifydestiny['success']) {
                    if (has_capability('moodle/backup:backupcourse', context_course::instance($course->id))) {
                        $res = $authres['data'];
                        $object = new stdClass();
                        $object->type = 0;
                        $object->siteurl = $CFG->wwwroot;
                        $object->direction = 1;
                        $object->destiny_request_id = $requestid;
                        $object->request_category_id = null;
                        $object->origin_course_id = $course->id;
                        $object->origin_category_id = null;

                        $object->origin_enrolusers = $configuration['origin_enrol_users'];
                        $object->origin_schedule_datetime = null;
                        $object->origin_remove_activities = 0;

                        $object->origin_activities = json_encode($sections);
                        $object->origin_backup_size = null;
                        $object->origin_backup_size_estimated = coursetransfer::get_backup_size_estimated($course->id);
                        $object->origin_backup_url = null;
                        $object->destiny_course_id = $destinycourseid;
                        $object->destiny_category_id = null;
                        $object->destiny_remove_activities = $configuration['destiny_remove_activities'];
                        $object->destiny_merge_activities = $configuration['destiny_merge_activities'];
                        $object->destiny_remove_enrols = $configuration['destiny_remove_enrols'];
                        $object->destiny_remove_groups = $configuration['destiny_remove_groups'];
                        $object->origin_remove_course = $configuration['origin_remove_course'];

                        $object->error_code = null;
                        $object->error_message = null;

                        $object->userid = $res->id;
                        $object->status = 10;

                        $requestoriginid = coursetransfer_request::insert_or_update($object);
                        coursetransfer::create_task_backup_course(
                                $course->id, $res->id, $verifydestiny['data'], $requestid, $requestoriginid,
                                $configuration['origin_enrol_users']);
                        $data->origin_backup_size_estimated = $object->origin_backup_size_estimated;
                        $data->request_origin_id = $requestoriginid;
                        $data->course_fullname = $course->fullname;
                        $data->course_shortname = $course->shortname;
                        $success = true;
                    } else {
                        $success = false;
                        $errors[] =
                                [
                                        'code' => '200052',
                                        'msg' => 'USER HAS NOT CAPABILITY'
                                ];
                    }
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
                    'code' => '200051',
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
                        'origin_backup_size_estimated' => new external_value(PARAM_INT,
                                'Backup Size Estimated (MB)', VALUE_OPTIONAL ),
                    ), PARAM_TEXT, 'Data'
                )
            )
        );
    }

};
