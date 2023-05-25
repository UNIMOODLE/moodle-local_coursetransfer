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

use core_course_category;
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

class origin_course_external extends external_api {
    /**
     * @return external_function_parameters
     */
    public static function origin_get_courses_parameters(): external_function_parameters {
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
    public static function origin_get_courses(string $field, string $value): array {
        self::validate_parameters(
            self::origin_get_courses_parameters(), [
                'field' => $field,
                'value' => $value
            ]
        );

        $success = true;
        $errors = [];
        $data = [];

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                global $DB;
                $res = $authres['data'];
                $courses = enrol_get_users_courses($res->id);
                foreach ($courses as $course) {
                    $item = new stdClass();
                    $item->id = $course->id;
                    $item->fullname = $course->fullname;
                    $item->shortname = $course->shortname;
                    $item->idnumber = $course->idnumber;
                    $item->categoryid = $course->category;
                    $category = core_course_category::get($item->categoryid);
                    $item->categoryname = $category->name;
                    $data[] = $item;
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '200061',
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
    public static function origin_get_courses_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message')
                    ), PARAM_TEXT, 'Errors'
                )),
                'data' => new external_multiple_structure(new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'Course ID'),
                        'fullname' => new external_value(PARAM_TEXT, 'Fullname', VALUE_OPTIONAL),
                        'shortname' => new external_value(PARAM_TEXT, 'Shortname', VALUE_OPTIONAL),
                        'idnumber' => new external_value(PARAM_TEXT, 'idNumber', VALUE_OPTIONAL),
                        'categoryid' => new external_value(PARAM_INT, 'Category ID', VALUE_OPTIONAL),
                        'categoryname' => new external_value(PARAM_TEXT, 'Category Name', VALUE_OPTIONAL)
                    ), PARAM_TEXT, 'Data'
                ))
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function origin_get_course_detail_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }

    /**
     * Get course details
     *
     * @param string $field
     * @param string $value
     * @param int $courseid
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function origin_get_course_detail(string $field, string $value, int $courseid): array {
        self::validate_parameters(
            self::origin_get_course_detail_parameters(), [
                'field' => $field,
                'value' => $value,
                'courseid' => $courseid
            ]
        );

        $errors = [];
        $data = [
            'id' => 0,
            'fullname' => '',
            'shortname' => '',
            'idnumber' => 0,
            'categoryid' => 0,
            'categoryname' => '',
            'backupsizeestimated' => 0,
            'sections' => []
            ];

        try {
            $course = get_course($courseid);
            $category = core_course_category::get($course->category);
            $data = [
                'id' => $course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'idnumber' => $course->idnumber,
                'categoryid' => $course->category,
                'categoryname' => $category->name,
                'backupsizeestimated' => coursetransfer::get_backup_size_estimated($course->id),
                'sections' => coursetransfer::get_sections_with_activities($course->id)
            ];
            $success = true;
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => 200062,
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
    public static function origin_get_course_detail_returns(): external_single_structure {
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
                        'id' => new external_value(PARAM_INT, 'Course ID', VALUE_OPTIONAL),
                        'fullname' => new external_value(PARAM_TEXT, 'Fullname', VALUE_OPTIONAL),
                        'shortname' => new external_value(PARAM_TEXT, 'Shortname', VALUE_OPTIONAL),
                        'idnumber' => new external_value(PARAM_TEXT, 'idNumber', VALUE_OPTIONAL),
                        'categoryid' => new external_value(PARAM_INT, 'Category ID', VALUE_OPTIONAL),
                        'categoryname' => new external_value(PARAM_TEXT, 'Category Name', VALUE_OPTIONAL),
                        'backupsizeestimated' => new external_value(PARAM_INT, 'Backup Size Estimated', VALUE_OPTIONAL),
                        'sections' => new external_multiple_structure(new external_single_structure(
                            array(
                                'sectionnum' => new external_value(PARAM_INT, 'Section Number', VALUE_OPTIONAL),
                                'sectionid' => new external_value(PARAM_INT, 'Section ID', VALUE_OPTIONAL),
                                'sectionname' => new external_value(PARAM_TEXT, 'Section Name', VALUE_OPTIONAL),
                                'activities' => new external_multiple_structure(new external_single_structure(
                                    array(
                                        'cmid' => new external_value(PARAM_INT, 'CMID', VALUE_OPTIONAL),
                                        'name' => new external_value(PARAM_TEXT, 'Name', VALUE_OPTIONAL),
                                        'instance' => new external_value(PARAM_INT, 'Instance', VALUE_OPTIONAL),
                                        'modname' => new external_value(PARAM_TEXT, 'Module Name', VALUE_OPTIONAL)
                                    )
                                ))
                            )
                        ))
                    ), PARAM_TEXT
                )
            )
        );
    }

};
