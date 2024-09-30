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
 * Origin Course Backup External.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external\backend;

use context_course;
use core_course_category;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_backup;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\models\configuration_course;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * Class origin_course_backup_external
 *
 * @package local_coursetransfer\external\backend
 */
class origin_course_backup_external extends external_api {

    /**
     * Origin Backup course parameters.
     *
     * @return external_function_parameters
     */
    public static function origin_backup_course_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'targetcourseid' => new external_value(PARAM_INT, 'Target Course ID'),
                'requestid' => new external_value(PARAM_INT, 'Request ID'),
                'targetsite' => new external_value(PARAM_TEXT, 'Target Site'),
                'configuration' => new external_single_structure(
                        [
                               'target_target' => new external_value(PARAM_INT, 'Target Target'),
                               'target_remove_enrols' => new external_value(PARAM_BOOL, 'Target Remove Enrols'),
                               'target_remove_groups' => new external_value(PARAM_BOOL, 'Target Remove Groups'),
                               'origin_remove_course' => new external_value(PARAM_BOOL,
                                               'Origin Remove Course', VALUE_DEFAULT, false),
                               'origin_enrol_users' => new external_value(PARAM_BOOL,
                                               'Origin Enrol Users', VALUE_DEFAULT, false),
                               'target_notremove_activities' => new external_value(PARAM_TEXT,
                                               'Target Not Remove Activities by commas', VALUE_DEFAULT, ''),
                               'nextruntime' => new external_value(PARAM_INT,
                                               'Scheduler Next Run Time Timestamp', VALUE_DEFAULT, 0),
                        ]
                ),
                'sections' => new external_multiple_structure(new external_single_structure(
                    [
                        'sectionnum' => new external_value(PARAM_INT, 'Section Number'),
                        'sectionid' => new external_value(PARAM_INT, 'Section ID'),
                        'sectionname' => new external_value(PARAM_TEXT, 'Section Name'),
                        'selected' => new external_value(PARAM_BOOL, 'Enabled'),
                        'activities' => new external_multiple_structure(new external_single_structure(
                            [
                                'cmid' => new external_value(PARAM_INT, 'CMID'),
                                'name' => new external_value(PARAM_TEXT, 'Name'),
                                'instance' => new external_value(PARAM_INT, 'Instance ID'),
                                'modname' => new external_value(PARAM_TEXT, 'Module Name'),
                                'selected' => new external_value(PARAM_BOOL, 'Selected'),
                            ]
                        ), '', VALUE_DEFAULT, []),
                    ]
                ), '', VALUE_DEFAULT, []),
            ]
        );
    }

    /**
     * Origin Backup course.
     *
     * @param string $field
     * @param string $value
     * @param int $courseid
     * @param int $targetcourseid
     * @param int $requestid
     * @param string $targetsite
     * @param array $configuration
     * @param array $sections
     *
     *
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_backup_course(string $field, string $value, int $courseid, int $targetcourseid,
            int $requestid, string $targetsite, array $configuration, array $sections = []): array {

        $params = self::validate_parameters(
            self::origin_backup_course_parameters(), [
                'field' => $field,
                'value' => $value,
                'courseid' => $courseid,
                'targetcourseid' => $targetcourseid,
                'requestid' => $requestid,
                'targetsite' => $targetsite,
                'configuration' => $configuration,
                'sections' => $sections,
            ]
        );

        $field = $params['field'];
        $value = $params['value'];
        $courseid = $params['courseid'];
        $targetcourseid = $params['targetcourseid'];
        $requestid = $params['requestid'];
        $targetsite = $params['targetsite'];
        $configuration = $params['configuration'];
        $sections = $params['sections'];

        $errors = [];
        $data = new stdClass();
        $data->requestid = $requestid;
        $data->request_origin_id = null;
        $data->origin_backup_size_estimated = null;

        try {
            $course = get_course($courseid);
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $verifytarget = coursetransfer::verify_target_site($targetsite);
                $user = $authres['data'];
                if ($verifytarget['success']) {
                    if (has_capability('moodle/backup:backupcourse', context_course::instance($course->id), $user)) {

                        $nextruntime = empty($configuration['nextruntime']) ? null : $configuration['nextruntime'];
                        $config = new configuration_course(
                                $configuration['target_target'],
                                $configuration['target_remove_enrols'],
                                $configuration['target_remove_groups'],
                                $configuration['origin_enrol_users'],
                                $configuration['origin_remove_course'],
                                $nextruntime
                        );
                        $requestorigin = coursetransfer_request::set_request_restore_course_response(
                                $user,
                                $requestid,
                                $verifytarget['data'],
                                $targetcourseid,
                                $course,
                                $config,
                                $sections);

                        $resbackup = coursetransfer_backup::create_task_backup_course(
                                $course->id, $user->id, $verifytarget['data'], $requestid, $requestorigin->id, $sections,
                                $configuration['origin_enrol_users'], $nextruntime);

                        if ($resbackup) {
                            $requestorigin->status = coursetransfer_request::STATUS_IN_PROGRESS;

                            $requestorigin->origin_backup_size_estimated =
                                    coursetransfer::get_backup_size_estimated_int($course->id);
                            coursetransfer_request::insert_or_update($requestorigin, $requestorigin->id);

                            $cat = core_course_category::get($course->category, MUST_EXIST);

                            $data->origin_backup_size_estimated = $requestorigin->origin_backup_size_estimated;
                            $data->request_origin_id = $requestorigin->id;
                            $data->course_fullname = $course->fullname;
                            $data->course_shortname = $course->shortname;
                            $data->course_idnumber = $course->idnumber;
                            $data->course_category_id = $course->category;
                            $data->course_category_name = isset($cat->name) ? $cat->name : '#';
                            $data->course_category_idnumber = $cat->idnumber;
                            $success = true;
                        } else {
                            $requestorigin->error_code = '10103';
                            $requestorigin->error_message = 'BACKUP NOT SAVE';
                            $requestorigin->status = coursetransfer_request::STATUS_ERROR;

                            coursetransfer_request::insert_or_update($requestorigin, $requestorigin->id);
                            $success = false;
                            $errors[] =
                                    [
                                            'code' => $requestorigin->error_code,
                                            'msg' => $requestorigin->error_message,
                                    ];
                        }
                    } else {
                        $success = false;
                        $errors[] =
                                [
                                        'code' => '10102',
                                        'msg' => 'USER HAS NOT CAPABILITY',
                                ];
                    }
                } else {
                    $success = false;
                    $errors[] = $verifytarget['error'];
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '10010',
                    'msg' => $e->getMessage(),
                ];
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * Origin Backup course returns.
     *
     * @return external_single_structure
     */
    public static function origin_backup_course_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message'),
                    ],'Errors'
                )),
                'data' => new external_single_structure(
                    [
                        'requestid' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL),
                        'request_origin_id' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL),
                        'course_fullname' => new external_value(PARAM_RAW, 'Origin Course Fullname', VALUE_OPTIONAL),
                        'course_shortname' => new external_value(PARAM_RAW, 'Origin Course Shortname', VALUE_OPTIONAL),
                        'course_idnumber' => new external_value(PARAM_RAW, 'Origin Course ID Number', VALUE_OPTIONAL),
                        'course_category_id' => new external_value(PARAM_INT, 'Category ID', VALUE_OPTIONAL),
                        'course_category_name' => new external_value(PARAM_RAW, 'Category Name', VALUE_OPTIONAL),
                        'course_category_idnumber' => new external_value(PARAM_RAW, 'Category ID Number', VALUE_OPTIONAL),
                        'origin_backup_size_estimated' => new external_value(PARAM_INT,
                            'Backup Size Estimated (MB)', VALUE_OPTIONAL ),
                    ],'Data'
                ),
            ]
        );
    }
};
