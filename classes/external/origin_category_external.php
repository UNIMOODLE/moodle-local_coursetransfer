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
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

class origin_category_external extends external_api {
    /**
     * @return external_function_parameters
     */
    public static function origin_get_categories_parameters(): external_function_parameters {
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
    public static function origin_get_categories(string $field, string $value): array {
        self::validate_parameters(
            self::origin_get_categories_parameters(), [
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
                $res = $authres['data'];
                $categories = core_course_category::get_all();
                foreach ($categories as $category) {
                    if ($category->is_uservisible($res)) {
                        $item = new stdClass();
                        $item->id = $category->id;
                        $item->name = $category->name;
                        $item->idnumber = $category->idnumber;
                        $item->parentid = $category->parent;
                        $categoryparent = core_course_category::get($item->parentid);
                        $item->parentname = $categoryparent->name;
                        $item->totalcourses = $category->get_courses_count();
                        $data[] = $item;
                    }
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '200041',
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
    public static function origin_get_categories_returns(): external_single_structure {
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
                        'id' => new external_value(PARAM_INT, 'Category ID'),
                        'name' => new external_value(PARAM_TEXT, 'Name', VALUE_OPTIONAL),
                        'idnumber' => new external_value(PARAM_TEXT, 'idNumber', VALUE_OPTIONAL),
                        'parentid' => new external_value(PARAM_INT, 'Category parent ID', VALUE_OPTIONAL),
                        'parentname' => new external_value(PARAM_TEXT, 'Category parent Name', VALUE_OPTIONAL),
                        'totalcourses' => new external_value(PARAM_INT, 'Total courses', VALUE_OPTIONAL)
                    ), PARAM_TEXT, 'Data'
                ))
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function origin_get_category_detail_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'categoryid' => new external_value(PARAM_INT, 'Category ID')
            )
        );
    }

    /**
     * Get course details
     *
     * @param string $field
     * @param string $value
     * @param int $categoryid
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_get_category_detail(string $field, string $value, int $categoryid): array {
        self::validate_parameters(
            self::origin_get_category_detail_parameters(), [
                'field' => $field,
                'value' => $value,
                'categoryid' => $categoryid
            ]
        );

        $errors = [];
        $data = [
            'id' => 0,
            'name' => '',
            'idnumber' => 0,
            'parentid' => 0,
            'parentname' => '',
            'courses' => []
            ];

        try {
            $category = core_course_category::get($categoryid);
            $categoryparent = core_course_category::get($category->parent);
            $courses = [];
            foreach ($category->get_courses() as $c) {
                $courseurl = new moodle_url('/course/view.php', ['id' => $c->id]);
                $course = new stdClass();
                $course->id = $c->id;
                $course->url = $courseurl->out(false);
                $course->fullname = $c->fullname;
                $course->shortname = $c->shortname;
                $course->idnumber = $c->idnumber;
                $course->categoryid = $c->category;
                $ccategory = core_course_category::get($c->category);
                $course->categoryname = $ccategory->name;
                $courses[] = $course;
            }
            $parentname = !empty($categoryparent->name) ? $categoryparent->name : get_string('top');
            $data = [
                'id' => $category->id,
                'name' => $category->name,
                'idnumber' => $category->idnumber,
                'parentid' => $category->parent,
                'parentname' => $parentname,
                'courses' => $courses
            ];
            $success = true;
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => 200042,
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
    public static function origin_get_category_detail_returns(): external_single_structure {
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
                        'id' => new external_value(PARAM_INT, 'Category ID', VALUE_OPTIONAL),
                        'name' => new external_value(PARAM_TEXT, 'Name', VALUE_OPTIONAL),
                        'idnumber' => new external_value(PARAM_TEXT, 'idNumber', VALUE_OPTIONAL),
                        'parentid' => new external_value(PARAM_INT, 'Category Parent ID', VALUE_OPTIONAL),
                        'parentname' => new external_value(PARAM_TEXT, 'Category ParentName', VALUE_OPTIONAL),
                        'courses' => new external_multiple_structure(new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Course ID', VALUE_OPTIONAL),
                                'url' => new external_value(PARAM_RAW, 'Course URL', VALUE_OPTIONAL),
                                'fullname' => new external_value(PARAM_TEXT, 'Course fullname', VALUE_OPTIONAL),
                                'shortname' => new external_value(PARAM_TEXT, 'Course ShortName', VALUE_OPTIONAL),
                                'idnumber' => new external_value(PARAM_TEXT, 'Course Idnumber', VALUE_OPTIONAL),
                                'categoryid' => new external_value(PARAM_INT, 'Category Id', VALUE_OPTIONAL),
                                'categoryname' => new external_value(PARAM_TEXT, 'Category Name', VALUE_OPTIONAL),
                                )
                            )
                        )
                    )
                )
            )
        );
    }

};