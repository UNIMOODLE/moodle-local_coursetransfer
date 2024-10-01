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
 * Origin Category External.
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

/**
 * Class origin_category_external
 *
 * @package local_coursetransfer\external\backend
 */
class origin_category_external extends external_api {

    /**
     * Origin get categories parameters.
     *
     * @return external_function_parameters
     */
    public static function origin_get_categories_parameters(): external_function_parameters {
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
     * Origin get categories.
     *
     * @param string $field
     * @param string $value
     * @param int $page
     * @param int $perpage
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function origin_get_categories(string $field, string $value, int $page = 0, int $perpage = 0): array {
        $params = self::validate_parameters(
            self::origin_get_categories_parameters(), [
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
                $categories = coursetransfer::get_categories_user($user, $page, $perpage);
                $totalcategories = coursetransfer::count_categories_user();
                foreach ($categories as $category) {
                    $item = new stdClass();
                    $item->id = $category->id;
                    $item->name = $category->name;
                    $item->idnumber = $category->idnumber;
                    $item->parentid = $category->parent;
                    $categoryparent = core_course_category::get($item->parentid);
                    $item->parentname = $categoryparent->name;
                    $item->totalcourses = $category->get_courses_count();
                    $subcategories = coursetransfer::get_subcategories($category, $user);
                    $item->totalsubcategories = count($subcategories);
                    $item->totalcourseschild = coursetransfer::get_subcategories_numcourses(
                            $category->get_courses_count(), $subcategories);
                    $data[] = $item;
                }
                $paging['totalcount'] = $totalcategories;
                $paging['page'] = $page;
                $paging['perpage'] = ($perpage !== 0 && $perpage < $totalcategories) ? $perpage : $totalcategories;
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '23011',
                    'msg' => $e->getMessage(),
                ];
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'paging' => $paging,
            'data' => $data,
        ];
    }

    /**
     * Origin get categories returns.
     *
     * @return external_single_structure
     */
    public static function origin_get_categories_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message'),
                    ], 'Errors'
                )),
                'paging' => new external_single_structure([
                    'totalcount' => new external_value(PARAM_INT, 'Total number of courses', VALUE_OPTIONAL),
                    'page' => new external_value(PARAM_INT, 'Current page', VALUE_OPTIONAL),
                    'perpage' => new external_value(PARAM_INT, 'Items per page', VALUE_OPTIONAL),
                ], 'Paging data'),
                'data' => new external_multiple_structure(new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'Category ID'),
                        'name' => new external_value(PARAM_TEXT, 'Name', VALUE_OPTIONAL),
                        'idnumber' => new external_value(PARAM_TEXT, 'idNumber', VALUE_OPTIONAL),
                        'parentid' => new external_value(PARAM_INT, 'Category parent ID', VALUE_OPTIONAL),
                        'parentname' => new external_value(PARAM_TEXT, 'Category parent Name', VALUE_OPTIONAL),
                        'totalcourses' => new external_value(PARAM_INT, 'Total courses', VALUE_OPTIONAL),
                        'totalsubcategories' => new external_value(PARAM_INT, 'Total subcategories', VALUE_OPTIONAL),
                        'totalcourseschild' => new external_value(PARAM_INT, 'Total courses all subcategory', VALUE_OPTIONAL),
                    ], 'Data'
                )),
            ]
        );
    }

    /**
     * Origin get categories detail parameters.
     *
     * @return external_function_parameters
     */
    public static function origin_get_category_detail_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'categoryid' => new external_value(PARAM_INT, 'Category ID'),
            ]
        );
    }

    /**
     * Origin get categories detail.
     *
     * @param string $field
     * @param string $value
     * @param int $categoryid
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_get_category_detail(string $field, string $value, int $categoryid): array {
        $params = self::validate_parameters(
                self::origin_get_category_detail_parameters(), [
                        'field' => $field,
                        'value' => $value,
                        'categoryid' => $categoryid,
                ]
        );
        $field = $params['field'];
        $value = $params['value'];
        $categoryid = $params['categoryid'] ?? 0;

        $errors = [];
        $data = [
                'id' => 0,
                'name' => '',
                'idnumber' => 0,
                'parentid' => 0,
                'parentname' => '',
                'courses' => [],
        ];

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $category = core_course_category::get($categoryid);
                $categoryparent = core_course_category::get($category->parent);
                $courses = [];
                $subcategories = coursetransfer::get_subcategories($category);
                array_unshift($subcategories, $category);
                foreach ($subcategories as $sub) {
                    foreach ($sub->get_courses() as $c) {
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
                        $course->categoryidnumber = $ccategory->idnumber;
                        $courses[] = $course;
                    }
                }
                $parentname = !empty($categoryparent->name) ? $categoryparent->name : get_string('top');
                $data = [
                        'id' => $category->id,
                        'name' => $category->name,
                        'idnumber' => $category->idnumber,
                        'parentid' => $category->parent,
                        'parentname' => $parentname,
                        'courses' => $courses,
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
                            'code' => '23001',
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
     * Origin get categories detail returns.
     *
     * @return external_single_structure
     */
    public static function origin_get_category_detail_returns(): external_single_structure {
        return new external_single_structure(
                [
                        'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                        'errors' => new external_multiple_structure(new external_single_structure(
                                [
                                        'code' => new external_value(PARAM_INT, 'Code'),
                                        'msg' => new external_value(PARAM_TEXT, 'Message'),
                                ], PARAM_TEXT, 'Errors'
                        )),
                        'data' => new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'Category ID', VALUE_OPTIONAL),
                                'name' => new external_value(PARAM_TEXT, 'Name', VALUE_OPTIONAL),
                                'idnumber' => new external_value(PARAM_TEXT, 'idNumber', VALUE_OPTIONAL),
                                'parentid' => new external_value(PARAM_INT, 'Category Parent ID', VALUE_OPTIONAL),
                                'parentname' => new external_value(PARAM_TEXT, 'Category ParentName', VALUE_OPTIONAL),
                                'courses' => new external_multiple_structure(
                                    new external_single_structure(
                                        [
                                            'id' => new external_value(PARAM_INT, 'Course ID', VALUE_OPTIONAL),
                                            'url' => new external_value(PARAM_RAW, 'Course URL', VALUE_OPTIONAL),
                                            'fullname' => new external_value(PARAM_TEXT, 'Course fullname', VALUE_OPTIONAL),
                                            'shortname' => new external_value(PARAM_TEXT, 'Course ShortName', VALUE_OPTIONAL),
                                            'idnumber' => new external_value(PARAM_TEXT, 'Course Idnumber', VALUE_OPTIONAL),
                                            'categoryid' => new external_value(PARAM_INT, 'Category Id', VALUE_OPTIONAL),
                                            'categoryname' => new external_value(PARAM_TEXT, 'Category Name', VALUE_OPTIONAL),
                                            'categoryidnumber' => new external_value(PARAM_TEXT, 'Category Name', VALUE_OPTIONAL),
                                        ]
                                )
                            ),
                            ]
                        ),
                ]
        );
    }


    /**
     * Origin get categories detail parameters.
     *
     * @return external_function_parameters
     */
    public static function origin_get_category_detail_tree_parameters(): external_function_parameters {
        return new external_function_parameters(
                [
                        'field' => new external_value(PARAM_TEXT, 'Field'),
                        'value' => new external_value(PARAM_TEXT, 'Value'),
                        'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                ]
        );
    }

    /**
     * Origin get categories detail.
     *
     * @param string $field
     * @param string $value
     * @param int $categoryid
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_get_category_detail_tree(string $field, string $value, int $categoryid): array {
        $params = self::validate_parameters(
                self::origin_get_category_detail_tree_parameters(), [
                        'field' => $field,
                        'value' => $value,
                        'categoryid' => $categoryid,
                ]
        );
        $field = $params['field'];
        $value = $params['value'];
        $categoryid = $params['categoryid'] ?? 0;

        $errors = [];
        $data = [
                'id' => 0,
                'name' => '',
                'idnumber' => 0,
                'parentid' => 0,
                'parentname' => '',
                'courses' => [],
        ];

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $category = core_course_category::get($categoryid);
                $data = json_encode(self::get_courses_and_categories($category));
                $success = true;
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                    [
                            'code' => '24001',
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
     * Origin get categories detail returns.
     *
     * @return external_single_structure
     */
    public static function origin_get_category_detail_tree_returns(): external_single_structure {
        return new external_single_structure(
                [
                        'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                        'errors' => new external_multiple_structure(new external_single_structure(
                                [
                                        'code' => new external_value(PARAM_INT, 'Code'),
                                        'msg' => new external_value(PARAM_TEXT, 'Message'),
                                ], 'Errors'
                        )),
                        'data' => new external_value(PARAM_RAW, 'Courses JSON', VALUE_OPTIONAL),
                ]
        );
    }

    /**
     * Get Courses and Categories.
     *
     * @param core_course_category $category
     * @return array
     * @throws \coding_exception
     * @throws moodle_exception
     */
    protected static function get_courses_and_categories(core_course_category $category): array {
        $maincourses = $category->get_courses();
        $courses = [];
        foreach ($maincourses as $mc) {
            if ($mc->category === $category->id) {
                $courseurl = new moodle_url('/course/view.php', ['id' => $mc->id]);
                $course = new stdClass();
                $course->id = $mc->id;
                $course->url = $courseurl->out(false);
                $course->fullname = $mc->fullname;
                $course->shortname = $mc->shortname;
                $course->idnumber = $mc->idnumber;
                $course->categoryid = $mc->category;
                $ccategory = core_course_category::get($mc->category);
                $course->categoryname = $ccategory->name;
                $course->categoryidnumber = $ccategory->idnumber;
                $courses[] = $course;
            }
        }
        $categoryparent = core_course_category::get($category->parent);
        $parentname = !empty($categoryparent->name) ? $categoryparent->name : get_string('top');
        $data = [
                'id' => $category->id,
                'name' => $category->name,
                'idnumber' => $category->idnumber,
                'parentid' => $category->parent,
                'description' => $category->description,
                'parentname' => $parentname,
                'courses' => $courses,
                'categories' => [],
        ];
        $childrens = $category->get_children();

        if (count($childrens) > 0) {
            foreach ($childrens as $child) {
                $data['categories'][] = self::get_courses_and_categories($child);
            }
        }

        return $data;
    }
};


