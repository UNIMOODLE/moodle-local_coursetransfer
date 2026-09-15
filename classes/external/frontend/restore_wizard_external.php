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
 * Restore Wizard External.
 *
 * Frontend web services for the redesigned restore wizard. These methods only
 * WRAP the existing business logic (coursetransfer, api\request, models); they
 * do not modify it. All operations run in the admin (system) context.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external\frontend;

use backup;
use coding_exception;
use context_course;
use context_coursecat;
use context_system;
use core_course_category;
use dml_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_sites;
use local_coursetransfer\factory\course;
use local_coursetransfer\models\configuration_category;
use local_coursetransfer\models\configuration_course;
use moodle_exception;
use moodle_url;
use required_capability_exception;
use restricted_context_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/lib.php');

/**
 * restore_wizard_external
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_wizard_external extends external_api {
    /**
     * Get sites parameters.
     *
     * @return external_function_parameters
     */
    public static function get_sites_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get origin sites.
     *
     * Wraps coursetransfer::get_origin_sites() (which returns [id => host]) and
     * coursetransfer_sites::list('origin') (which carries name and last test
     * information). The returned "id" is the DB record id of each origin site,
     * which is exactly the "position" expected by
     * coursetransfer::get_site_by_position().
     *
     * @return array
     * @throws restricted_context_exception
     * @throws required_capability_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function get_sites(): array {
        global $CFG;
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/coursetransfer:origin_restore', $context);

        $normalize = function (string $url): string {
            return strtolower(rtrim(trim($url), '/'));
        };
        $localhost = $normalize($CFG->wwwroot);
        $sites = [];
        // Note that coursetransfer_sites::list('origin') gives us name/lasttest/lastteststatus
        // keyed and indexed by the site record id, which IS the position.
        $records = coursetransfer_sites::list('origin');
        foreach ($records as $record) {
            $connected = isset($record->lastteststatus) && (int)$record->lastteststatus === 1;
            if (!empty($record->name)) {
                $name = $record->name;
            } else {
                $name = preg_replace('#^https?://#i', '', $record->host);
            }
            if (!isset($record->lastteststatus) || $record->lastteststatus === null) {
                $status = get_string('platforms_test_idle', 'local_coursetransfer');
            } else {
                $status = $connected
                        ? get_string('platform_conn_ok', 'local_coursetransfer')
                        : get_string('platform_conn_error', 'local_coursetransfer');
            }
            $sites[] = [
                'id' => (int)$record->id,
                'name' => $name,
                'host' => $record->host,
                'connected' => $connected,
                'status' => $status,
                'iscurrent' => ($normalize($record->host) === $localhost),
            ];
        }

        return [
            'sites' => $sites,
        ];
    }

    /**
     * Get sites returns.
     *
     * @return external_single_structure
     */
    public static function get_sites_returns(): external_single_structure {
        return new external_single_structure(
            [
                'sites' => new external_multiple_structure(new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'Site position (origin record id)'),
                        'name' => new external_value(PARAM_TEXT, 'Site name'),
                        'host' => new external_value(PARAM_RAW, 'Site host'),
                        'connected' => new external_value(PARAM_BOOL, 'Whether last test was OK'),
                        'status' => new external_value(PARAM_TEXT, 'Connection status text'),
                        'iscurrent' => new external_value(
                            PARAM_BOOL,
                            'Whether the site is this very platform (self-pairing)',
                            VALUE_OPTIONAL
                        ),
                    ]
                )),
            ]
        );
    }

    /**
     * List origin parameters.
     *
     * @return external_function_parameters
     */
    public static function list_origin_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'siteid' => new external_value(PARAM_INT, 'Site position (origin record id)'),
                'type' => new external_value(PARAM_ALPHA, 'Type: course|category'),
                'page' => new external_value(PARAM_INT, 'Page (0-based)', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 10),
                'query' => new external_value(PARAM_TEXT, 'Search query', VALUE_DEFAULT, ''),
            ]
        );
    }

    /**
     * List origin courses or categories.
     *
     * Wraps api\request::origin_get_courses()/origin_get_categories() and
     * normalises their response into a common item shape.
     *
     * @param int $siteid
     * @param string $type
     * @param int $page
     * @param int $perpage
     * @param string $query
     * @return array
     * @throws restricted_context_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     */
    public static function list_origin(int $siteid, string $type, int $page, int $perpage, string $query): array {
        global $USER;
        $params = self::validate_parameters(
            self::list_origin_parameters(),
            [
                'siteid' => $siteid,
                'type' => $type,
                'page' => $page,
                'perpage' => $perpage,
                'query' => $query,
            ]
        );
        $siteid = $params['siteid'];
        $type = $params['type'];
        $page = $params['page'];
        $perpage = $params['perpage'];
        $query = $params['query'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/coursetransfer:origin_restore', $context);

        $items = [];
        $total = 0;
        $pages = 1;
        $success = false;
        $errors = [];

        try {
            $site = coursetransfer::get_site_by_position($siteid);
            $request = new request($site);
            if ($type === 'category') {
                $res = $request->origin_get_categories($USER, $page, $perpage);
            } else {
                $res = $request->origin_get_courses($USER, $page, $perpage, $query);
            }

            $success = (bool)$res->success;
            if ($success) {
                $data = is_array($res->data) ? $res->data : [];
                $host = rtrim((string)$site->host, '/');
                foreach ($data as $item) {
                    if ($type === 'category') {
                        // A category restore imports the WHOLE subtree (the
                        // category's own courses plus every subcategory's,
                        // recursively). The backend exposes:
                        // totalcourses      = courses directly in the root
                        // totalcourseschild = grand total (root + subcategories)
                        // So the headline "courses to import" is totalcourseschild,
                        // and the breakdown is root + (total - root). This avoids
                        // the confusing "0 courses" (root only) next to a large
                        // subcategory count.
                        $root = isset($item->totalcourses) ? (int)$item->totalcourses : 0;
                        $total = isset($item->totalcourseschild) ? (int)$item->totalcourseschild : $root;
                        if ($total < $root) {
                            $total = $root;
                        }
                        $items[] = [
                            'id' => (int)$item->id,
                            'name' => $item->name ?? '',
                            'shortname' => '',
                            'idnumber' => isset($item->idnumber) ? (string)$item->idnumber : '',
                            'category' => '',
                            'categoryid' => 0,
                            'parent' => isset($item->parentname) ? (string)$item->parentname : '',
                            'subcats' => isset($item->totalsubcategories) ? (int)$item->totalsubcategories : 0,
                            'rootcourses' => $root,
                            'subcatcourses' => $total - $root,
                            'meta' => get_string('rw_meta_courses', 'local_coursetransfer', $total),
                            'url' => $host . '/course/index.php?categoryid=' . (int)$item->id,
                        ];
                    } else {
                        // Separate fields; the AMD builds the sub line with bold
                        // labels (ours) and escaped values (remote). meta = size.
                        $meta = !empty($item->backupsizeestimated)
                                ? display_size((int)$item->backupsizeestimated) : '—';
                        $items[] = [
                            'id' => (int)$item->id,
                            'name' => $item->fullname ?? '',
                            'shortname' => isset($item->shortname) ? (string)$item->shortname : '',
                            'idnumber' => isset($item->idnumber) ? (string)$item->idnumber : '',
                            'category' => isset($item->categoryname) ? (string)$item->categoryname : '',
                            'categoryid' => isset($item->categoryid) ? (int)$item->categoryid : 0,
                            'parent' => '',
                            'subcats' => 0,
                            'rootcourses' => 0,
                            'subcatcourses' => 0,
                            'meta' => $meta,
                            'url' => $host . '/course/view.php?id=' . (int)$item->id,
                        ];
                    }
                }
                // Paging: the remote may return totalcount/perpage in $res->paging.
                // LIMITATION: if the remote does not report paging (older origin
                // sites), we cannot know the real total, so we fall back to the
                // size of the current page and a single page.
                if (!empty($res->paging) && isset($res->paging->totalcount)) {
                    $total = (int)$res->paging->totalcount;
                    $pp = !empty($res->paging->perpage) ? (int)$res->paging->perpage : $perpage;
                    $pages = ($pp > 0) ? (int)ceil($total / $pp) : 1;
                    if ($pages < 1) {
                        $pages = 1;
                    }
                } else {
                    $total = count($items);
                    $pages = 1;
                }
            } else {
                $errors = self::normalize_errors($res->errors);
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] = [
                'code' => '30001',
                'msg' => $e->getMessage(),
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'success' => $success,
            'errors' => $errors,
        ];
    }

    /**
     * List origin returns.
     *
     * @return external_single_structure
     */
    public static function list_origin_returns(): external_single_structure {
        return new external_single_structure(
            [
                'items' => new external_multiple_structure(new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'Course/Category ID'),
                        'name' => new external_value(PARAM_TEXT, 'Name'),
                        'shortname' => new external_value(PARAM_TEXT, 'Course shortname', VALUE_OPTIONAL, ''),
                        'idnumber' => new external_value(PARAM_TEXT, 'ID number', VALUE_OPTIONAL, ''),
                        'category' => new external_value(PARAM_TEXT, 'Origin category name', VALUE_OPTIONAL, ''),
                        'categoryid' => new external_value(PARAM_INT, 'Origin category id (courses)', VALUE_OPTIONAL, 0),
                        'parent' => new external_value(PARAM_TEXT, 'Parent category name (categories)', VALUE_OPTIONAL, ''),
                        'subcats' => new external_value(PARAM_INT, 'Number of subcategories (categories)', VALUE_OPTIONAL, 0),
                        'rootcourses' => new external_value(
                            PARAM_INT,
                            'Courses directly in the category root (categories)',
                            VALUE_OPTIONAL,
                            0
                        ),
                        'subcatcourses' => new external_value(
                            PARAM_INT,
                            'Courses in subcategories (categories)',
                            VALUE_OPTIONAL,
                            0
                        ),
                        'meta' => new external_value(PARAM_TEXT, 'Size or course count'),
                        'url' => new external_value(PARAM_RAW, 'Remote URL of the course/category', VALUE_OPTIONAL, ''),
                    ]
                )),
                'total' => new external_value(PARAM_INT, 'Total items'),
                'page' => new external_value(PARAM_INT, 'Current page (0-based)'),
                'pages' => new external_value(PARAM_INT, 'Total pages'),
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_RAW, 'Message'),
                    ]
                )),
            ]
        );
    }

    /**
     * Get sections parameters.
     *
     * @return external_function_parameters
     */
    public static function get_sections_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'siteid' => new external_value(PARAM_INT, 'Site position (origin record id)'),
                'courseid' => new external_value(PARAM_INT, 'Origin course id'),
                'targetcourseid' => new external_value(
                    PARAM_INT,
                    'Target (local) course id where the teacher is acting (0 = system context)',
                    VALUE_DEFAULT,
                    0
                ),
            ]
        );
    }

    /**
     * Get the sections/activities of an origin course.
     *
     * Wraps api\request::origin_get_course_detail() and extracts the sections
     * tree so the teacher can pick which sections/activities to restore over
     * the current course.
     *
     * Security (LCT-012 pattern): the teacher acts inside a course context, so
     * the restore capability is checked against the DESTINATION course context
     * (targetcourseid). When targetcourseid is 0 we fall back to the system
     * context with the generic origin_restore capability.
     *
     * @param int $siteid
     * @param int $courseid
     * @param int $targetcourseid
     * @return array
     * @throws restricted_context_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     */
    public static function get_sections(int $siteid, int $courseid, int $targetcourseid = 0): array {
        global $USER;
        $params = self::validate_parameters(
            self::get_sections_parameters(),
            [
                'siteid' => $siteid,
                'courseid' => $courseid,
                'targetcourseid' => $targetcourseid,
            ]
        );
        $siteid = $params['siteid'];
        $courseid = $params['courseid'];
        $targetcourseid = $params['targetcourseid'];

        // The teacher acts in a course context: validate the restore capability
        // over the destination course context (LCT-012). If no target course is
        // known, fall back to the system context with the generic capability.
        if ($targetcourseid > 0) {
            $context = context_course::instance($targetcourseid);
            self::validate_context($context);
            require_capability('local/coursetransfer:origin_restore_course', $context);
        } else {
            $context = context_system::instance();
            self::validate_context($context);
            require_capability('local/coursetransfer:origin_restore', $context);
        }

        $sections = [];
        $errors = [];

        try {
            $site = coursetransfer::get_site_by_position($siteid);
            $request = new request($site);
            $res = $request->origin_get_course_detail($courseid, $USER);

            $success = (bool)$res->success;
            if ($success) {
                // Note that $res->data is the origin course object; $res->data->sections is
                // an array of {sectionnum, sectionid, sectionname, activities[]}
                // (see origin_course_external::origin_get_course_detail_returns
                // and new_origin_restore_course_step3_page::export_for_template).
                $data = $res->data;
                $rawsections = isset($data->sections) && is_array($data->sections) ? $data->sections : [];
                foreach ($rawsections as $section) {
                    $activities = [];
                    if (isset($section->activities) && is_array($section->activities)) {
                        foreach ($section->activities as $act) {
                            $activities[] = [
                                'cmid' => isset($act->cmid) ? (int)$act->cmid : 0,
                                'name' => $act->name ?? '',
                                'instance' => isset($act->instance) ? (int)$act->instance : 0,
                                'modname' => $act->modname ?? '',
                            ];
                        }
                    }
                    $sections[] = [
                        'sectionnum' => isset($section->sectionnum) ? (int)$section->sectionnum : 0,
                        'sectionid' => isset($section->sectionid) ? (int)$section->sectionid : 0,
                        'sectionname' => $section->sectionname ?? '',
                        'activities' => $activities,
                    ];
                }
            } else {
                $errors = self::normalize_errors($res->errors);
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] = [
                'code' => '30301',
                'msg' => $e->getMessage(),
            ];
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'sections' => $sections,
        ];
    }

    /**
     * Get sections returns.
     *
     * @return external_single_structure
     */
    public static function get_sections_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_RAW, 'Message'),
                    ]
                )),
                'sections' => new external_multiple_structure(new external_single_structure(
                    [
                        'sectionnum' => new external_value(PARAM_INT, 'Section Number'),
                        'sectionid' => new external_value(PARAM_INT, 'Section ID'),
                        'sectionname' => new external_value(PARAM_TEXT, 'Section Name'),
                        'activities' => new external_multiple_structure(new external_single_structure(
                            [
                                'cmid' => new external_value(PARAM_INT, 'CMID'),
                                'name' => new external_value(PARAM_TEXT, 'Name'),
                                'instance' => new external_value(PARAM_INT, 'Instance ID'),
                                'modname' => new external_value(PARAM_TEXT, 'Module Name'),
                            ]
                        )),
                    ]
                )),
            ]
        );
    }

    /**
     * Get category tree parameters.
     *
     * @return external_function_parameters
     */
    public static function get_category_tree_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'siteid' => new external_value(PARAM_INT, 'Site position (origin record id)'),
                'categoryid' => new external_value(PARAM_INT, 'Origin category id'),
            ]
        );
    }

    /**
     * Get the full subtree (nested subcategories + courses) of an origin category.
     *
     * Wraps api\request::origin_get_category_detail_tree() so the restore wizard can
     * preview the hierarchy that will be recreated on the target. Read-only preview:
     * validated against the system context with the generic origin_restore capability.
     *
     * @param int $siteid
     * @param int $categoryid
     * @return array
     * @throws restricted_context_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     */
    public static function get_category_tree(int $siteid, int $categoryid): array {
        global $USER;
        $params = self::validate_parameters(
            self::get_category_tree_parameters(),
            [
                'siteid' => $siteid,
                'categoryid' => $categoryid,
            ]
        );
        $siteid = $params['siteid'];
        $categoryid = $params['categoryid'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/coursetransfer:origin_restore', $context);

        $errors = [];
        $tree = '';

        try {
            $site = coursetransfer::get_site_by_position($siteid);
            $request = new request($site);
            $res = $request->origin_get_category_detail_tree($categoryid, $USER);
            $success = (bool)$res->success;
            if ($success) {
                // Note that origin_get_category_detail_tree returns its payload as a JSON string.
                $tree = is_string($res->data) ? $res->data : json_encode($res->data);
            } else {
                $errors = $res->errors;
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] = ['code' => '30601', 'msg' => $e->getMessage()];
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'tree' => $tree,
        ];
    }

    /**
     * Get category tree returns.
     *
     * @return external_single_structure
     */
    public static function get_category_tree_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_RAW, 'Message'),
                    ]
                )),
                'tree' => new external_value(PARAM_RAW, 'Category subtree as JSON', VALUE_OPTIONAL),
            ]
        );
    }

    /**
     * Delete request parameters.
     *
     * @return external_function_parameters
     */
    public static function delete_request_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'requestid' => new external_value(PARAM_INT, 'Request (execution log) id to delete'),
            ]
        );
    }

    /**
     * Delete a request (execution log) record.
     *
     * Removes the local_coursetransfer_request row so the entry disappears from the
     * logs. Guarded by the view_logs capability (system context).
     *
     * @param int $requestid
     * @return array
     * @throws restricted_context_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     */
    public static function delete_request(int $requestid): array {
        global $DB;
        $params = self::validate_parameters(
            self::delete_request_parameters(),
            ['requestid' => $requestid]
        );
        $requestid = $params['requestid'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/coursetransfer:view_logs', $context);

        $errors = [];
        $success = false;
        try {
            // Remove any queued adhoc task tied to this request so it does not run
            // orphaned after the request record is gone.
            foreach (coursetransfer_request::get_related_adhoc_tasks($requestid) as $task) {
                $DB->delete_records('task_adhoc', ['id' => $task->id]);
            }
            $DB->delete_records(coursetransfer_request::TABLE, ['id' => $requestid]);
            $success = true;
        } catch (moodle_exception $e) {
            $errors[] = ['code' => '30701', 'msg' => $e->getMessage()];
        }

        return [
            'success' => $success,
            'errors' => $errors,
        ];
    }

    /**
     * Delete request returns.
     *
     * @return external_single_structure
     */
    public static function delete_request_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Whether it was deleted'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_RAW, 'Message'),
                    ]
                )),
            ]
        );
    }

    /**
     * Submit parameters.
     *
     * @return external_function_parameters
     */
    public static function submit_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'siteid' => new external_value(PARAM_INT, 'Site position (origin record id)'),
                'type' => new external_value(PARAM_ALPHA, 'Type: course|category'),
                // Per-course destination config (type course).
                'courses' => new external_multiple_structure(
                    new external_single_structure([
                            'origincourseid' => new external_value(PARAM_INT, 'Origin course id'),
                            'targetid' => new external_value(PARAM_INT, 'Target course id (0 = create new)'),
                            'categorytarget' => new external_value(
                                PARAM_INT,
                                'Target category when creating new (0 = default)',
                                VALUE_DEFAULT,
                                0
                            ),
                            'mode' => new external_value(
                                PARAM_ALPHA,
                                'merge|replace when target exists',
                                VALUE_DEFAULT,
                                'merge'
                            ),
                            'removeenrols' => new external_value(PARAM_BOOL, 'Remove enrolments', VALUE_DEFAULT, false),
                            'removegroups' => new external_value(PARAM_BOOL, 'Remove groups', VALUE_DEFAULT, false),
                            // Teacher flow: optional per-section/activity selection. Empty => whole course.
                            'sections' => new external_multiple_structure(new external_single_structure(
                                [
                                    'sectionnum' => new external_value(PARAM_INT, 'Section Number'),
                                    'sectionid' => new external_value(PARAM_INT, 'Section ID'),
                                    'sectionname' => new external_value(PARAM_TEXT, 'Section Name'),
                                    'selected' => new external_value(PARAM_BOOL, 'Selected'),
                                    'activities' => new external_multiple_structure(new external_single_structure(
                                        [
                                            'cmid' => new external_value(PARAM_INT, 'CMID'),
                                            'name' => new external_value(PARAM_TEXT, 'Name'),
                                            'instance' => new external_value(PARAM_INT, 'Instance ID'),
                                            'modname' => new external_value(PARAM_TEXT, 'Module Name'),
                                            'selected' => new external_value(PARAM_BOOL, 'Selected'),
                                        ]
                                    )),
                                ]
                            ), 'Selected sections/activities (teacher flow)', VALUE_DEFAULT, []),
                    ]),
                    'Per-course destination config',
                    VALUE_DEFAULT,
                    []
                ),
                // Origin categories (type category).
                'catids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Origin category id'),
                    'Origin categories',
                    VALUE_DEFAULT,
                    []
                ),
                'targetcatid' => new external_value(
                    PARAM_INT,
                    'Target local category for category restore (0 = default)',
                    VALUE_DEFAULT,
                    0
                ),
                'catmode' => new external_value(
                    PARAM_ALPHA,
                    'merge|replace for category restore',
                    VALUE_DEFAULT,
                    'merge'
                ),
                'includeusers' => new external_value(PARAM_BOOL, 'Include users (global)'),
                'removeorigin' => new external_value(
                    PARAM_BOOL,
                    'Delete the origin course/category after restoring (global)',
                    VALUE_DEFAULT,
                    false
                ),
                'schedule' => new external_value(PARAM_INT, 'Schedule timestamp in ms (0 = now)'),
            ]
        );
    }

    /**
     * Submit restore.
     *
     * Replicates the logic of restore_external::origin_restore_step4 /
     * origin_restore_cat_step4 (create the target course/category with the
     * factory, build the configuration models and call
     * coursetransfer::restore_course/restore_category), fixing bug LCT-040:
     * errors are accumulated with array_merge and success is an AND of every
     * unit, so a later success no longer masks an earlier failure.
     *
     * @param int $siteid
     * @param string $type
     * @param array $courses
     * @param array $catids
     * @param int $targetcatid
     * @param string $catmode
     * @param bool $includeusers
     * @param bool $removeorigin
     * @param int $schedule
     * @return array
     * @throws moodle_exception
     * @throws restricted_context_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     */
    public static function submit(
        int $siteid,
        string $type,
        array $courses = [],
        array $catids = [],
        int $targetcatid = 0,
        string $catmode = 'merge',
        bool $includeusers = false,
        bool $removeorigin = false,
        int $schedule = 0
    ): array {
        global $USER;
        $params = self::validate_parameters(
            self::submit_parameters(),
            [
                'siteid' => $siteid,
                'type' => $type,
                'courses' => $courses,
                'catids' => $catids,
                'targetcatid' => $targetcatid,
                'catmode' => $catmode,
                'includeusers' => $includeusers,
                'removeorigin' => $removeorigin,
                'schedule' => $schedule,
            ]
        );
        $siteid = $params['siteid'];
        $type = $params['type'];
        $courses = $params['courses'];
        $catids = $params['catids'];
        $targetcatid = $params['targetcatid'];
        $catmode = $params['catmode'];
        $includeusers = $params['includeusers'];
        $removeorigin = $params['removeorigin'];
        $schedule = $params['schedule'];

        // System context + origin_restore capability. Per-unit restore
        // capabilities are checked inside each loop over the actual
        // destination context (LCT-012).
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/coursetransfer:origin_restore', $context);

        // Schedule: incoming timestamp is in ms; 0 = run now (null nextruntime).
        $nextruntime = ($schedule > 0) ? (int)floor($schedule / 1000) : null;

        $success = true;
        $errors = [];
        $requestids = [];

        if (($type === 'category' && empty($catids)) || ($type !== 'category' && empty($courses))) {
            $success = false;
            $errors[] = [
                'code' => '30502',
                'msg' => get_string('courses_not_selected', 'local_coursetransfer'),
            ];
        } else {
            try {
                $site = coursetransfer::get_site_by_position($siteid);

                if ($type === 'category') {
                    // One restore_category per selected origin category into the
                    // chosen destination category. A category restore always
                    // creates fresh courses (coursetransfer::restore_category
                    // calls course::create per course), so the target is always
                    // TARGET_NEW_COURSE — matching the legacy category flow.
                    // There is no merge/replace concept for categories, hence
                    // $catmode is ignored here.
                    $targettarget = backup::TARGET_NEW_COURSE;
                    $dcat = ($targetcatid === 0)
                            ? core_course_category::get_default()
                            : core_course_category::get($targetcatid);
                    require_capability(
                        'moodle/restore:restorecourse',
                        context_coursecat::instance($dcat->id)
                    );
                    foreach ($catids as $catid) {
                        try {
                            $config = new configuration_category(
                                $targettarget,
                                false,
                                false,
                                $includeusers,
                                $removeorigin,
                                $nextruntime
                            );
                            // Preserve the origin subcategory hierarchy on the target.
                            $res = coursetransfer::restore_category_tree($USER, $site, $targetcatid, (int)$catid, $config);
                            $success = $success && (bool)$res['success'];
                            if (!empty($res['errors'])) {
                                $errors = array_merge($errors, $res['errors']);
                            }
                            if (isset($res['data']['requestid'])) {
                                $requestids[] = (int)$res['data']['requestid'];
                            }
                        } catch (moodle_exception $e) {
                            $success = false;
                            $errors[] = [
                                'code' => '30501',
                                'msg' => 'Category ID: ' . $catid . ' - ' . $e->getMessage(),
                            ];
                        }
                    }
                } else {
                    // Per-course destination: create a new course in the chosen
                    // category, or restore over an existing target course.
                    $num = 1;
                    foreach ($courses as $c) {
                        $origincourseid = (int)$c['origincourseid'];
                        try {
                            if ((int)$c['targetid'] === 0) {
                                // Create a new empty course in the target category.
                                $catid = isset($c['categorytarget']) ? (int)$c['categorytarget'] : 0;
                                $dcat = ($catid === 0)
                                        ? core_course_category::get_default()
                                        : core_course_category::get($catid);
                                require_capability(
                                    'moodle/restore:restorecourse',
                                    context_coursecat::instance($dcat->id)
                                );
                                $targetcourseid = course::create(
                                    $dcat,
                                    'Remote Restoring in process...',
                                    'IN-PROGRESS-' . time() . '-' . $num
                                );
                                $targettarget = backup::TARGET_NEW_COURSE;
                                $removeenrols = false;
                                $removegroups = false;
                            } else {
                                // Restore over an existing target course.
                                $targetcourseid = (int)$c['targetid'];
                                require_capability(
                                    'moodle/restore:restorecourse',
                                    context_course::instance($targetcourseid)
                                );
                                $mode = $c['mode'] ?? 'merge';
                                $targettarget = ($mode === 'replace')
                                        ? backup::TARGET_EXISTING_DELETING
                                        : backup::TARGET_EXISTING_ADDING;
                                $removeenrols = !empty($c['removeenrols']);
                                $removegroups = !empty($c['removegroups']);
                            }
                            $config = new configuration_course(
                                $targettarget,
                                $removeenrols,
                                $removegroups,
                                $includeusers,
                                $removeorigin,
                                $nextruntime
                            );
                            // Teacher flow: pass the selected sections/activities. The
                            // params structure already matches the format expected by
                            // restore_course/restore_course_unity (same shape as the old
                            // new_origin_restore_course_step5). Empty => whole course.
                            $sections = (isset($c['sections']) && is_array($c['sections'])) ? $c['sections'] : [];
                            $res = coursetransfer::restore_course(
                                $USER,
                                $site,
                                $targetcourseid,
                                $origincourseid,
                                $config,
                                $sections
                            );
                            $success = $success && (bool)$res['success'];
                            if (!empty($res['errors'])) {
                                $errors = array_merge($errors, $res['errors']);
                            }
                            if (isset($res['data']['requestid'])) {
                                $requestids[] = (int)$res['data']['requestid'];
                            }
                            $num++;
                        } catch (moodle_exception $e) {
                            $success = false;
                            $errors[] = [
                                'code' => '30501',
                                'msg' => 'Course ID: ' . $origincourseid . ' - ' . $e->getMessage(),
                            ];
                        }
                    }
                }
            } catch (moodle_exception $e) {
                $success = false;
                $errors[] = [
                    'code' => '30500',
                    'msg' => $e->getMessage(),
                ];
            }
        }

        if ($type === 'category') {
            $nexturl = new moodle_url(
                '/local/coursetransfer/logs.php',
                ['type' => coursetransfer_request::TYPE_CATEGORY]
            );
        } else {
            $nexturl = new moodle_url('/local/coursetransfer/logs.php');
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'data' => [
                'requestids' => $requestids,
                'nexturl' => $nexturl->out(false),
            ],
        ];
    }

    /**
     * Submit returns.
     *
     * @return external_single_structure
     */
    public static function submit_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_RAW, 'Message'),
                    ]
                )),
                'data' => new external_single_structure(
                    [
                        'requestids' => new external_multiple_structure(
                            new external_value(PARAM_INT, 'Request ID')
                        ),
                        'nexturl' => new external_value(PARAM_RAW, 'Next URL', VALUE_OPTIONAL, '#'),
                    ]
                ),
            ]
        );
    }

    /**
     * Submit course (teacher) parameters.
     *
     * @return external_function_parameters
     */
    public static function submit_course_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'siteid' => new external_value(PARAM_INT, 'Site position (origin record id)'),
                'origincourseid' => new external_value(PARAM_INT, 'Origin course id'),
                'targetcourseid' => new external_value(PARAM_INT, 'Target (current) course id'),
                'mode' => new external_value(PARAM_ALPHA, 'merge|replace over the current course', VALUE_DEFAULT, 'merge'),
                'includeusers' => new external_value(PARAM_BOOL, 'Include users and groups', VALUE_DEFAULT, false),
                // Optional per-section/activity selection. Empty => whole course.
                'sections' => new external_multiple_structure(new external_single_structure(
                    [
                        'sectionnum' => new external_value(PARAM_INT, 'Section Number'),
                        'sectionid' => new external_value(PARAM_INT, 'Section ID'),
                        'sectionname' => new external_value(PARAM_TEXT, 'Section Name'),
                        'selected' => new external_value(PARAM_BOOL, 'Selected'),
                        'activities' => new external_multiple_structure(new external_single_structure(
                            [
                                'cmid' => new external_value(PARAM_INT, 'CMID'),
                                'name' => new external_value(PARAM_TEXT, 'Name'),
                                'instance' => new external_value(PARAM_INT, 'Instance ID'),
                                'modname' => new external_value(PARAM_TEXT, 'Module Name'),
                                'selected' => new external_value(PARAM_BOOL, 'Selected'),
                            ]
                        )),
                    ]
                ), 'Selected sections/activities', VALUE_DEFAULT, []),
            ]
        );
    }

    /**
     * Submit a teacher course restore over the CURRENT course.
     *
     * Unlike submit() (admin, system context), the teacher acts inside a course
     * context, so security is checked against the destination course context
     * (LCT-012): the generic origin_restore_course capability plus the specific
     * merge/replace capability that matches the chosen mode (parity with the
     * legacy new_origin_restore_course flow, which gated each radio by
     * target_restore_merge / target_restore_content_remove). The origin course
     * is never deleted and there is no deferred execution in this flow.
     *
     * @param int $siteid
     * @param int $origincourseid
     * @param int $targetcourseid
     * @param string $mode
     * @param bool $includeusers
     * @param array $sections
     * @return array
     * @throws moodle_exception
     * @throws restricted_context_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     */
    public static function submit_course(
        int $siteid,
        int $origincourseid,
        int $targetcourseid,
        string $mode = 'merge',
        bool $includeusers = false,
        array $sections = []
    ): array {
        global $USER;
        $params = self::validate_parameters(
            self::submit_course_parameters(),
            [
                'siteid' => $siteid,
                'origincourseid' => $origincourseid,
                'targetcourseid' => $targetcourseid,
                'mode' => $mode,
                'includeusers' => $includeusers,
                'sections' => $sections,
            ]
        );
        $siteid = $params['siteid'];
        $origincourseid = $params['origincourseid'];
        $targetcourseid = $params['targetcourseid'];
        $mode = $params['mode'];
        $includeusers = $params['includeusers'];
        $sections = $params['sections'];

        // Course context + capability (LCT-012). The teacher never restores at
        // system level; the destination is always the current course.
        $context = context_course::instance($targetcourseid);
        self::validate_context($context);
        require_capability('local/coursetransfer:origin_restore_course', $context);

        $success = true;
        $errors = [];
        $requestids = [];

        try {
            // Enforce the capability that matches the requested mode, mirroring
            // the legacy flow (merge vs empty-and-restore are separate rights).
            if ($mode === 'replace') {
                if (!coursetransfer::can_target_restore_content_remove($USER, $context)) {
                    require_capability('local/coursetransfer:target_restore_content_remove', $context);
                }
                $targettarget = backup::TARGET_EXISTING_DELETING;
            } else {
                if (!coursetransfer::can_target_restore_merge($USER, $context)) {
                    require_capability('local/coursetransfer:target_restore_merge', $context);
                }
                $targettarget = backup::TARGET_EXISTING_ADDING;
            }

            $site = coursetransfer::get_site_by_position($siteid);
            $config = new configuration_course(
                $targettarget,
                false,
                false,
                $includeusers,
                false,
                null
            );
            $res = coursetransfer::restore_course(
                $USER,
                $site,
                $targetcourseid,
                $origincourseid,
                $config,
                $sections
            );
            $success = (bool)$res['success'];
            if (!empty($res['errors'])) {
                $errors = array_merge($errors, $res['errors']);
            }
            if (isset($res['data']['requestid'])) {
                $requestids[] = (int)$res['data']['requestid'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] = [
                'code' => '30601',
                'msg' => $e->getMessage(),
            ];
        }

        $nexturl = new moodle_url('/local/coursetransfer/origin_restore_course.php', ['id' => $targetcourseid]);

        return [
            'success' => $success,
            'errors' => $errors,
            'data' => [
                'requestids' => $requestids,
                'nexturl' => $nexturl->out(false),
            ],
        ];
    }

    /**
     * Submit course (teacher) returns.
     *
     * @return external_single_structure
     */
    public static function submit_course_returns(): external_single_structure {
        return self::submit_returns();
    }

    /**
     * Submit category (teacher/manager) parameters.
     *
     * @return external_function_parameters
     */
    public static function submit_category_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'siteid' => new external_value(PARAM_INT, 'Site position (origin record id)'),
                'origincatid' => new external_value(PARAM_INT, 'Origin category id'),
                'targetcatid' => new external_value(PARAM_INT, 'Target (current) category id'),
                'includeusers' => new external_value(PARAM_BOOL, 'Include users and groups', VALUE_DEFAULT, false),
                'removeorigin' => new external_value(
                    PARAM_BOOL,
                    'Delete the origin category after restoring',
                    VALUE_DEFAULT,
                    false
                ),
                'schedule' => new external_value(PARAM_INT, 'Schedule timestamp in ms (0 = now)', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Submit a teacher/manager category restore INTO the current category.
     *
     * The manager acts inside a category context, so security is checked
     * against the destination category context (LCT-012/LCT-028): the
     * origin_restore_category capability plus moodle/restore:restorecourse.
     * A category restore always creates fresh courses, so the target is always
     * TARGET_NEW_COURSE.
     *
     * @param int $siteid
     * @param int $origincatid
     * @param int $targetcatid
     * @param bool $includeusers
     * @param bool $removeorigin
     * @param int $schedule
     * @return array
     * @throws moodle_exception
     * @throws restricted_context_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     */
    public static function submit_category(
        int $siteid,
        int $origincatid,
        int $targetcatid,
        bool $includeusers = false,
        bool $removeorigin = false,
        int $schedule = 0
    ): array {
        global $USER;
        $params = self::validate_parameters(
            self::submit_category_parameters(),
            [
                'siteid' => $siteid,
                'origincatid' => $origincatid,
                'targetcatid' => $targetcatid,
                'includeusers' => $includeusers,
                'removeorigin' => $removeorigin,
                'schedule' => $schedule,
            ]
        );
        $siteid = $params['siteid'];
        $origincatid = $params['origincatid'];
        $targetcatid = $params['targetcatid'];
        $includeusers = $params['includeusers'];
        $removeorigin = $params['removeorigin'];
        $schedule = $params['schedule'];

        // Category context + capabilities (LCT-012 / LCT-028).
        $context = context_coursecat::instance($targetcatid);
        self::validate_context($context);
        require_capability('local/coursetransfer:origin_restore_category', $context);
        require_capability('moodle/restore:restorecourse', $context);

        $nextruntime = ($schedule > 0) ? (int)floor($schedule / 1000) : null;

        $success = true;
        $errors = [];
        $requestids = [];

        try {
            $site = coursetransfer::get_site_by_position($siteid);
            $config = new configuration_category(
                backup::TARGET_NEW_COURSE,
                false,
                false,
                $includeusers,
                $removeorigin,
                $nextruntime
            );
            // Preserve the origin subcategory hierarchy on the target.
            $res = coursetransfer::restore_category_tree($USER, $site, $targetcatid, $origincatid, $config);
            $success = (bool)$res['success'];
            if (!empty($res['errors'])) {
                $errors = array_merge($errors, $res['errors']);
            }
            if (isset($res['data']['requestid'])) {
                $requestids[] = (int)$res['data']['requestid'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] = [
                'code' => '30701',
                'msg' => $e->getMessage(),
            ];
        }

        $nexturl = new moodle_url('/local/coursetransfer/origin_restore_category.php', ['id' => $targetcatid]);

        return [
            'success' => $success,
            'errors' => $errors,
            'data' => [
                'requestids' => $requestids,
                'nexturl' => $nexturl->out(false),
            ],
        ];
    }

    /**
     * Submit category (teacher/manager) returns.
     *
     * @return external_single_structure
     */
    public static function submit_category_returns(): external_single_structure {
        return self::submit_returns();
    }

    /**
     * Remove submit parameters.
     *
     * @return external_function_parameters
     */
    public static function remove_submit_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'siteid' => new external_value(PARAM_INT, 'Site position (origin record id)'),
                'type' => new external_value(PARAM_ALPHA, 'Type: course|category'),
                'ids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Origin course/category id'),
                    'Ids to delete'
                ),
                'schedule' => new external_value(PARAM_INT, 'Schedule timestamp in ms (0 = now)', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Submit a remote DELETE of the selected courses or categories.
     *
     * Wraps coursetransfer::remove_course()/remove_category() (which ask the
     * remote origin to delete and track it via a request + callback). Supports
     * multiple ids (deleting a category removes all its courses/subcategories
     * on the remote). Errors are accumulated (LCT-040) and success is an AND.
     *
     * @param int $siteid
     * @param string $type
     * @param array $ids
     * @param int $schedule
     * @return array
     * @throws moodle_exception
     * @throws restricted_context_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     */
    public static function remove_submit(int $siteid, string $type, array $ids, int $schedule = 0): array {
        global $USER;
        $params = self::validate_parameters(
            self::remove_submit_parameters(),
            [
                'siteid' => $siteid,
                'type' => $type,
                'ids' => $ids,
                'schedule' => $schedule,
            ]
        );
        $siteid = $params['siteid'];
        $type = $params['type'];
        $ids = $params['ids'];
        $schedule = $params['schedule'];

        // Destructive remote action: check the matching capability (system).
        $context = context_system::instance();
        self::validate_context($context);
        if ($type === 'category') {
            require_capability('local/coursetransfer:origin_remove_category', $context);
        } else {
            require_capability('local/coursetransfer:origin_remove_course', $context);
        }

        // Deferred execution: incoming ms; 0 = run now.
        $nextruntime = ($schedule > 0) ? (int)floor($schedule / 1000) : 0;

        $success = true;
        $errors = [];
        $requestids = [];

        if (empty($ids)) {
            $success = false;
            $errors[] = [
                'code' => '30801',
                'msg' => get_string('courses_not_selected', 'local_coursetransfer'),
            ];
        } else {
            try {
                $site = coursetransfer::get_site_by_position($siteid);
                foreach ($ids as $id) {
                    try {
                        if ($type === 'category') {
                            $res = coursetransfer::remove_category($site, (int)$id, $USER, $nextruntime);
                        } else {
                            $res = coursetransfer::remove_course($site, (int)$id, $USER, $nextruntime);
                        }
                        $success = $success && (bool)$res['success'];
                        if (!empty($res['errors'])) {
                            $errors = array_merge($errors, $res['errors']);
                        }
                        if (isset($res['data']['requestid'])) {
                            $requestids[] = (int)$res['data']['requestid'];
                        }
                    } catch (moodle_exception $e) {
                        $success = false;
                        $errors[] = [
                            'code' => '30802',
                            'msg' => 'ID: ' . $id . ' - ' . $e->getMessage(),
                        ];
                    }
                }
            } catch (moodle_exception $e) {
                $success = false;
                $errors[] = [
                    'code' => '30800',
                    'msg' => $e->getMessage(),
                ];
            }
        }

        $nexturl = new moodle_url('/local/coursetransfer/logs.php', [
            'type' => ($type === 'category')
                    ? coursetransfer_request::TYPE_REMOVE_CATEGORY
                    : coursetransfer_request::TYPE_REMOVE_COURSE,
        ]);

        return [
            'success' => $success,
            'errors' => $errors,
            'data' => [
                'requestids' => $requestids,
                'nexturl' => $nexturl->out(false),
            ],
        ];
    }

    /**
     * Remove submit returns.
     *
     * @return external_single_structure
     */
    public static function remove_submit_returns(): external_single_structure {
        return self::submit_returns();
    }

    /**
     * Map wizard mode to a backup target_target constant.
     *
     * new     => backup::TARGET_NEW_COURSE (2)
     * replace => backup::TARGET_EXISTING_DELETING (3)
     * merge   => backup::TARGET_EXISTING_ADDING (4)
     *
     * @param string $mode
     * @return int
     */
    protected static function mode_to_target(string $mode): int {
        switch ($mode) {
            case 'replace':
                return backup::TARGET_EXISTING_DELETING;
            case 'merge':
                return backup::TARGET_EXISTING_ADDING;
            case 'new':
            default:
                return backup::TARGET_NEW_COURSE;
        }
    }

    /**
     * Normalise remote errors (stdClass with code/msg) into the array shape
     * expected by the external returns.
     *
     * @param array|null $errors
     * @return array
     */
    protected static function normalize_errors(?array $errors): array {
        $out = [];
        if (is_array($errors)) {
            foreach ($errors as $error) {
                $out[] = [
                    'code' => isset($error->code) ? (string)$error->code : '0',
                    'msg' => $error->msg ?? '',
                ];
            }
        }
        return $out;
    }
}
