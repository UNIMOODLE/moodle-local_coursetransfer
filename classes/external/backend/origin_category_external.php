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
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * Class origin_category_external
 *
 * @package local_coursetransfer\external\backend
 */
class origin_category_external extends backend_api {
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
                // Optional since 2.2.3: an older target never sends it (LCT-128).
                'search' => new external_value(PARAM_TEXT, 'Text to find in the category name or path', VALUE_DEFAULT, ''),
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
     * @param string $search Text to find in the category name or path (LCT-128).
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function origin_get_categories(
        string $field,
        string $value,
        int $page = 0,
        int $perpage = 0,
        string $search = ''
    ): array {
        $params = self::validate_parameters(
            self::origin_get_categories_parameters(),
            [
                'field' => $field,
                'value' => $value,
                'page' => $page,
                'perpage' => $perpage,
                'search' => $search,
            ]
        );
        self::validate_service_user();
        $field = $params['field'];
        $value = $params['value'];
        $perpage = $params['perpage'] ?? 0;
        $page = $perpage == 0 ? 0 : $params['page'] ?? 0;
        $search = trim((string)$params['search']);

        $success = true;
        $errors = [];
        $data = [];
        $paging = [];

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $user = $authres['data'];
                $categories = coursetransfer::get_categories_user($user, $page, $perpage, $search);
                $totalcategories = coursetransfer::count_categories_user($user, $search);
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
                        $category->get_courses_count(),
                        $subcategories
                    );
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
                    ],
                    'Errors',
                    VALUE_OPTIONAL
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
                    ],
                    'Data',
                    VALUE_OPTIONAL
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
            self::origin_get_category_detail_parameters(),
            [
                'field' => $field,
                'value' => $value,
                'categoryid' => $categoryid,
            ]
        );
        self::validate_service_user();
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
                if (!coursetransfer::can_backup_category($category, $authres['data'])) {
                    throw new moodle_exception('nopermissions', 'error', '', 'moodle/backup:backupcourse');
                }
                $categoryparent = core_course_category::get($category->parent);
                $courses = [];
                $subcategories = coursetransfer::get_subcategories($category, $authres['data']);
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
                ] + self::count_hidden($category, count($subcategories) - 1, count($courses));
                $success = true;
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    // 23002/24002: the named user may not back up this category (LCT-010).
                    'code' => $e->errorcode === 'nopermissions' ? '23002' : '23001',
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
                    ],
                    'Errors',
                    VALUE_OPTIONAL
                )),
                'data' => new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'Category ID', VALUE_OPTIONAL),
                        'name' => new external_value(PARAM_TEXT, 'Name', VALUE_OPTIONAL),
                        'idnumber' => new external_value(PARAM_TEXT, 'idNumber', VALUE_OPTIONAL),
                        'parentid' => new external_value(PARAM_INT, 'Category Parent ID', VALUE_OPTIONAL),
                        'parentname' => new external_value(PARAM_TEXT, 'Category ParentName', VALUE_OPTIONAL),
                        'hiddencategories' => new external_value(
                            PARAM_INT,
                            'Subcategories in the tree not listed (not visible to the user)',
                            VALUE_OPTIONAL
                        ),
                        'hiddencourses' => new external_value(
                            PARAM_INT,
                            'Courses in the tree not listed (not visible to the user)',
                            VALUE_OPTIONAL
                        ),
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
     * Origin get category detail tree parameters.
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
     * Origin get category detail tree.
     *
     * Returns the full category subtree (nested categories + their courses), used by the
     * restore wizard to render the category hierarchy and import preserving the tree.
     *
     * @param string $field
     * @param string $value
     * @param int $categoryid
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_get_category_detail_tree(string $field, string $value, int $categoryid): array {
        $params = self::validate_parameters(
            self::origin_get_category_detail_tree_parameters(),
            [
                'field' => $field,
                'value' => $value,
                'categoryid' => $categoryid,
            ]
        );
        self::validate_service_user();
        $field = $params['field'];
        $value = $params['value'];
        $categoryid = $params['categoryid'] ?? 0;

        $errors = [];
        // The service declares data as a JSON string (PARAM_RAW): on failure it stays an empty
        // string, so the caller gets success = false and the errors instead of an invalid response.
        $data = '';

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $category = core_course_category::get($categoryid);
                if (!coursetransfer::can_backup_category($category, $authres['data'])) {
                    throw new moodle_exception('nopermissions', 'error', '', 'moodle/backup:backupcourse');
                }
                $tree = self::get_courses_and_categories($category);
                [$ncategories, $ncourses] = self::count_tree($tree);
                $data = json_encode($tree + self::count_hidden($category, $ncategories, $ncourses));
                $success = true;
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    // 23002/24002: the named user may not back up this category (LCT-010).
                    'code' => $e->errorcode === 'nopermissions' ? '24002' : '24001',
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
     * Origin get category detail tree returns.
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
                    ],
                    'Errors'
                )),
                'data' => new external_value(PARAM_RAW, 'Courses JSON', VALUE_OPTIONAL),
            ]
        );
    }

    /**
     * How many subcategories and courses of a category tree a listing left out.
     *
     * A recursive removal of the category takes them too, so the target must know it did not copy them.
     *
     * @param core_course_category $category Root of the tree.
     * @param int $listedcategories Subcategories listed (root excluded).
     * @param int $listedcourses Courses listed.
     * @return array ['hiddencategories' => int, 'hiddencourses' => int]
     */
    protected static function count_hidden(core_course_category $category, int $listedcategories, int $listedcourses): array {
        global $DB;
        $subids = $DB->get_fieldset_select(
            'course_categories',
            'id',
            $DB->sql_like('path', ':path'),
            ['path' => $category->path . '/%']
        );
        [$insql, $params] = $DB->get_in_or_equal(array_merge([(int)$category->id], $subids), SQL_PARAMS_NAMED, 'cat');
        $courses = $DB->count_records_select('course', "category $insql", $params);
        return [
            'hiddencategories' => max(0, count($subids) - $listedcategories),
            'hiddencourses' => max(0, $courses - $listedcourses),
        ];
    }

    /**
     * Subcategories (root excluded) and courses of a tree built by get_courses_and_categories().
     *
     * @param array $tree
     * @return int[] [categories, courses]
     */
    protected static function count_tree(array $tree): array {
        $categories = 0;
        $courses = 0;
        $pending = [$tree];
        while ($node = array_pop($pending)) {
            $courses += count($node['courses'] ?? []);
            foreach ($node['categories'] ?? [] as $child) {
                $categories++;
                $pending[] = $child;
            }
        }
        return [$categories, $courses];
    }

    /**
     * Builds the recursive category + courses tree.
     *
     * @param core_course_category $category
     * @param array $visited Category ids already walked, to break any cycle (corrupted tree).
     * @param int $depth Current recursion depth.
     * @return array
     */
    protected static function get_courses_and_categories(
        core_course_category $category,
        array $visited = [],
        int $depth = 0
    ): array {
        // Defensive guard: a well-formed Moodle category tree has no cycles, but a corrupted
        // parent chain (or an excessively deep tree) must not cause infinite recursion.
        if ($depth > coursetransfer::MAX_TREE_DEPTH || isset($visited[$category->id])) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'idnumber' => $category->idnumber,
                'parentid' => $category->parent,
                'description' => $category->description,
                'parentname' => '',
                'courses' => [],
                'categories' => [],
            ];
        }
        $visited[$category->id] = true;
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
                $data['categories'][] = self::get_courses_and_categories($child, $visited, $depth + 1);
            }
        }

        return $data;
    }
}
