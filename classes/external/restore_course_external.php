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
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

class restore_course_external extends external_api {
    /**
     * @return external_function_parameters
     */
    public static function new_origin_restore_course_step1_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'siteurl' => new external_value(PARAM_INT, 'Site Url'),
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }

    /**
     * Check if user exists
     *
     * @param int $siteurl
     * @param int $courseid
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function new_origin_restore_course_step1(int $siteurl, int $courseid): array {
        self::validate_parameters(
            self::new_origin_restore_course_step1_parameters(),
            [
                'siteurl' => $siteurl,
                'courseid' => $courseid
            ]
        );

        $success = false;
        $errors = [];
        $data = new stdClass();
        $data->userid = 0;
        $data->username = '';
        $data->firstname = '';
        $data->lastname = '';
        $data->email = '';
        $data->nexturl = '';

        try {
            $site = coursetransfer::get_site_by_position($siteurl);
            $request = new request($site);
            $res = $request->origin_has_user();
            if ($res->success) {
                $data = $res->data;
                $nexturl = new moodle_url(
                    '/local/coursetransfer/origin_restore_course.php',
                    ['id' => $courseid, 'new' => 1, 'step' => 2, 'site' => $siteurl]
                );
                $data->nexturl = $nexturl->out(false);
                $success = true;
            } else {
                $errors[] = $res->errors;
            }
        } catch (moodle_exception $e) {
            $errors[] =
                [
                    'code' => '030340',
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
    public static function new_origin_restore_course_step1_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message')
                    ),
                    PARAM_TEXT,
                    'Errors'
                )),
                'data' => new external_single_structure(
                    array(
                        'userid' => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL),
                        'username' => new external_value(PARAM_TEXT, 'Username', VALUE_OPTIONAL),
                        'firstname' => new external_value(PARAM_TEXT, 'Firstname', VALUE_OPTIONAL),
                        'lastname' => new external_value(PARAM_TEXT, 'Lastname', VALUE_OPTIONAL),
                        'email' => new external_value(PARAM_TEXT, 'Email', VALUE_OPTIONAL),
                        'nexturl' => new external_value(PARAM_RAW, 'Next URL', VALUE_OPTIONAL)
                    ),
                    PARAM_TEXT,
                    'Data'
                )
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function new_origin_restore_course_step5_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'siteurl' => new external_value(PARAM_INT, 'Site Url'),
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'configuration' => new external_single_structure(
                    array(
                        'destiny_merge_activities' => new external_value(PARAM_BOOL, 'Destiny Merge Activities'),
                        'destiny_remove_enrols' => new external_value(PARAM_BOOL, 'Destiny Remove Enrols'),
                        'destiny_remove_groups' => new external_value(PARAM_BOOL, 'Destiny Remove Groups'),
                        'destiny_remove_activities' => new external_value(PARAM_BOOL, 'Destiny Remove Activities')
                    )
                ),
                'sections' => new external_multiple_structure(new external_single_structure(
                    array(
                        'sectionnum' => new external_value(PARAM_INT, 'Section Number'),
                        'sectionid' => new external_value(PARAM_INT, 'Section ID'),
                        'sectionname' => new external_value(PARAM_TEXT, 'Section Name'),
                        'selected' => new external_value(PARAM_BOOL, 'Selected'),
                        'activities' => new external_multiple_structure(new external_single_structure(
                            array(
                                'cmid' => new external_value(PARAM_INT, 'CMID'),
                                'name' => new external_value(PARAM_TEXT, 'Name'),
                                'instance' => new external_value(PARAM_INT, 'Instance ID'),
                                'modname' => new external_value(PARAM_TEXT, 'Module Name'),
                                'selected' => new external_value(PARAM_BOOL, 'Selected'),
                            )
                        ))
                    )
                ))
            )
        );
    }

    /**
     * Check if user exists
     *
     * @param int $siteurl
     * @param int $courseid
     * @param array $configuration
     * @param array $sections
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function new_origin_restore_course_step5(int $siteurl, int $courseid,
                                                           array $configuration, array $sections): array {
        global $USER;
        self::validate_parameters(
            self::new_origin_restore_course_step5_parameters(),
            [
                'siteurl' => $siteurl,
                'courseid' => $courseid,
                'configuration' => $configuration,
                'sections' => $sections
            ]
        );

        $success = false;
        $errors = [];

        try {
            $site = coursetransfer::get_site_by_position($siteurl);
            $object = new stdClass();
            $object->type = 0;
            $object->siteurl = $site->host;
            $object->direction = 0;
            $object->destiny_course_id = 2; // El curso actual, id=X en la url.
            $object->origin_course_id = $courseid;
            $object->origin_enrolusers = 0; // Revisar pliego, posiblemente esto sea 0, pq el profesor no puede.
            $object->origin_remove_course = 0; // No está en configuración, es configuración de origen?
            $object->origin_activities = json_encode($sections);
            $object->destiny_remove_activities = $configuration['destiny_remove_activities'];
            $object->destiny_merge_activities = $configuration['destiny_merge_activities'];
            $object->destiny_remove_enrols = $configuration['destiny_remove_enrols'];
            $object->destiny_remove_groups = $configuration['destiny_remove_groups'];
            $object->status = 1;
            $object->userid = $USER->id;
            $requestid = coursetransfer_request::insert_or_update($object);
            $request = new request($site);
            $res = $request->origin_backup_course($requestid, $courseid);
            if ($res->success) {
                $object->status = 30;
                coursetransfer_request::insert_or_update($object, $requestid);
                $success = true;
            } else {
                $errors[] = $res->errors;
                $object->status = 0;
                $object->error_code = $errors[0]['code'];
                $object->error_message = $errors[0]['msg'];
                coursetransfer_request::insert_or_update($object, $requestid);
                $success = false;
            }
        } catch (moodle_exception $e) {
            $errors[] =
                [
                    'code' => '030340',
                    'msg' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'errors' => $errors
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function new_origin_restore_course_step5_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message')
                    ),
                    PARAM_TEXT,
                    'Errors'
                ))
            )
        );
    }
};
