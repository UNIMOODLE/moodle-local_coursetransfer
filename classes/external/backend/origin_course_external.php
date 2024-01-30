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
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external\backend;

use core_course_category;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
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
            [
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'page' => new external_value(PARAM_INT, 'Page number been requested (starts with page 0)', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'Items per page to  (starts with page 0)', VALUE_DEFAULT, 0),
            ]
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
    public static function origin_get_courses(string $field, string $value, int $page = 0, int $perpage = 0): array {
        $params = self::validate_parameters(
            self::origin_get_courses_parameters(), [
                'field' => $field,
                'value' => $value,
                'page' => $page,
                'perpage' => $perpage,
            ]
        );
        $field = $params['field'];
        $value = $params['value'];
        $perpage = $params['perpage'] ?? 0;
        $page = $perpage == 0 ? 0 : $params['page'] ?? 0;

        $success = true;
        $errors = [];
        $data = [];
        $paging = [];

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $user = $authres['data'];
                $courses = coursetransfer::get_courses_user($user, $page, $perpage);
                $totalcourses = coursetransfer::count_courses_user($user);
                foreach ($courses as $course) {
                    $url = new moodle_url('/course/view.php', ['id' => $course->id]);
                    $item = new stdClass();
                    $item->id = $course->id;
                    $item->url = $url->out(false);
                    $item->fullname = $course->fullname;
                    $item->shortname = $course->shortname;
                    $item->idnumber = $course->idnumber;
                    $item->categoryid = $course->category;
                    $item->backupsizeestimated = coursetransfer::get_backup_size_estimated($course->id);
                    $category = core_course_category::get($item->categoryid);
                    $item->categoryname = $category->name;
                    $data[] = $item;
                }
                $paging['totalcount'] = $totalcourses;
                $paging['page'] = $page;
                $paging['perpage'] = ($perpage !== 0 && $perpage < $totalcourses) ? $perpage : $totalcourses;
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '22011',
                    'msg' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'paging' => $paging,
            'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function origin_get_courses_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message')
                    ], 'Errors'
                )),
                'paging' => new external_single_structure([
                    'totalcount' => new external_value(PARAM_INT, 'Total number of courses', VALUE_OPTIONAL),
                    'page' => new external_value(PARAM_INT, 'Current page', VALUE_OPTIONAL),
                    'perpage' => new external_value(PARAM_INT, 'Items per page', VALUE_OPTIONAL),
                ], 'Paging data'),
                'data' => new external_multiple_structure(new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'Course ID'),
                        'url' => new external_value(PARAM_RAW, 'URL', VALUE_OPTIONAL),
                        'fullname' => new external_value(PARAM_TEXT, 'Fullname', VALUE_OPTIONAL),
                        'shortname' => new external_value(PARAM_TEXT, 'Shortname', VALUE_OPTIONAL),
                        'idnumber' => new external_value(PARAM_TEXT, 'idNumber', VALUE_OPTIONAL),
                        'categoryid' => new external_value(PARAM_INT, 'Category ID', VALUE_OPTIONAL),
                        'backupsizeestimated' => new external_value(PARAM_TEXT, 'Backup Size Estimated', VALUE_OPTIONAL),
                        'categoryname' => new external_value(PARAM_TEXT, 'Category Name', VALUE_OPTIONAL)
                    ], 'Course info'
                ), 'Courses info')
            ]
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
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
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
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '22001',
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
                        'backupsizeestimated' => new external_value(PARAM_TEXT, 'Backup Size Estimated', VALUE_OPTIONAL),
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
