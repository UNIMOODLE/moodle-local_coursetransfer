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
 * Coursetransfer Category.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use core_course_category;
use dml_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\factory\category;
use local_coursetransfer\factory\course;
use local_coursetransfer\models\configuration_category;
use local_coursetransfer\models\configuration_course;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/local/coursetransfer/classes/task/create_backup_course_task.php');

/**
 * Coursetransfer Category.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer_category {

    /**
     * Restore Category.
     *
     * @param stdClass $user
     * @param stdClass $site
     * @param int $targetcategoryid
     * @param int $origincategoryid
     * @param configuration_category $configuration
     * @param array $courses
     * @return array
     */
    public static function restore(
            stdClass $user, stdClass $site, int $targetcategoryid, int $origincategoryid,
            configuration_category $configuration, array $courses = []): array {

        try {

            // 1. Category Request DB.
            $requestobject = coursetransfer_request::set_request_restore_category(
                    $site, $targetcategoryid, $origincategoryid, '', $configuration, $user
            );

            $request = new request($site);
            $res = $request->origin_get_category_detail($origincategoryid, $user);

            if ($res->success) {
                // 1. Data Category Main.
                $origincategoryname = $res->data->name;
                $origincategoryidnumber = isset($res->data->idnumber) ? $res->data->idnumber : '';
                $origincategorydesc = isset($res->data->description) ? $res->data->description : '';

                // 2. If targetcategoryid is new (0).
                if ($targetcategoryid === 0) {
                    $targetcategoryid = category::create($origincategoryname, $origincategoryidnumber, $origincategorydesc);
                } else {
                    category::update($targetcategoryid, $origincategoryname, $origincategorydesc);
                }

                $requestobject->origin_category_name = $origincategoryname;
                coursetransfer_request::insert_or_update($requestobject, $requestobject->id);

                $success = true;
                $errors = [];
                $catcourserequests = [];

                // 3. Courses iteration.
                if (count($courses) === 0) {
                    $courses = $res->data->courses;
                } else {
                    $courses = coursetransfer::get_courses_detail($user, $site, $courses);
                }

                foreach ($courses as $course) {
                    $res = self::restore_course(
                            $course, $user, $site, $targetcategoryid, $configuration, $requestobject, $errors);
                    $success = $res['success'];
                    $errors = $res['errors'];
                }

            } else {
                $success = false;
                $errors = $res->errors;
            }

            return [
                    'success' => $success,
                    'errors' => $errors,
                    'data' => [
                            'requestid' => $requestobject->id,
                    ],
            ];

        } catch (moodle_exception $e) {
            $error = [
                    'code' => '11001',
                    'msg' => $e->getMessage(),
            ];
            $errors[] = $error;
            return [
                    'success' => false,
                    'errors' => $errors,
            ];
        }
    }

    /**
     * Restore Tree Category.
     *
     * @param stdClass $user
     * @param stdClass $site
     * @param int $targetcategoryid
     * @param int $origincategoryid
     * @param configuration_category $configuration
     * @return array
     */
    public static function restore_tree(
            stdClass $user, stdClass $site, int $targetcategoryid, int $origincategoryid,
            configuration_category $configuration): array {

        try {

            // 1. Category Request DB.
            $requestobject = coursetransfer_request::set_request_restore_category(
                    $site, $targetcategoryid, $origincategoryid, '', $configuration, $user
            );

            $request = new request($site);
            $res = $request->origin_get_category_detail_tree($origincategoryid, $user);

            if ($res->success) {
                $data = json_decode($res->data);
                if (isset($data->name)) {
                    // 1. Data Category Main.
                    $origincategoryname = $data->name;
                    $origincategoryidnumber = isset($data->idnumber) ? $data->idnumber : '';
                    $origincategorydesc = isset($data->description) ? $data->description : '';

                    // 2. If targetcategoryid is new (0)
                    if ($targetcategoryid === 0) {
                        $targetcategoryid = category::create(
                                $origincategoryname, $origincategoryidnumber, $origincategorydesc);
                    } else {
                        category::update($targetcategoryid, $origincategoryname, $origincategorydesc);
                    }

                    $requestobject->origin_category_name = $origincategoryname;
                    coursetransfer_request::insert_or_update($requestobject, $requestobject->id);

                    $success = true;
                    $errors = [];
                    $catcourserequests = [];

                    $courseslist = $data->courses;
                    $catlist = $data->categories;

                    // 3. Create iterator tree courses and categories.
                    // 3A. Courses
                    if (count($courseslist) > 0 || count($catlist) > 0) {
                        foreach ($courseslist as $course) {
                            self::restore_course(
                                    $course, $user, $site, $targetcategoryid, $configuration, $requestobject, $errors);
                        }
                        foreach ($catlist as $cat) {
                            $res = self::restore_category(
                                    $cat, $user, $site, $targetcategoryid, $configuration, $requestobject, $errors);
                            $success = $res['success'];
                            $errors = $res['errors'];
                        }
                    } else {
                        $success = false;
                        $error = [
                                'code' => '11003',
                                'msg' => 'Courses not found',
                        ];
                        $errors[] = $error;
                    }

                } else {
                    $success = false;
                    $error = [
                            'code' => '11002',
                            'msg' => 'Name not found in response data',
                    ];
                    $errors[] = $error;
                }
            } else {
                $success = false;
                $errors = $res->errors;
            }

            return [
                    'success' => $success,
                    'errors' => $errors,
                    'data' => [
                            'requestid' => $requestobject->id,
                    ],
            ];

        } catch (moodle_exception $e) {
            $error = [
                    'code' => '11001',
                    'msg' => $e->getMessage(),
            ];
            $errors[] = $error;
            return [
                    'success' => false,
                    'errors' => $errors,
            ];
        }
    }

    /**
     * Remove Category.
     *
     * @param stdClass $site
     * @param int $origincatid
     * @param stdClass|null $user
     * @param int|null $nextruntime
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function remove(stdClass $site, int $origincatid,
            stdClass $user = null, int $nextruntime = null): array {

        $errors = [];

        // 1. Request DB.
        $requestobject = coursetransfer_request::set_request_remove_category($site, $origincatid, $user, $nextruntime);

        // 2. Call CURL Origin Backup Course.
        $request = new request($site);
        $res = $request->origin_remove_category($requestobject->id, $origincatid, $nextruntime, $user);
        // 3. Success or Errors.
        if ($res->success) {
            // 4a. Update Request DB Completed.
            $requestobject->status = coursetransfer_request::STATUS_IN_PROGRESS;
            $requestobject->origin_category_id = $origincatid;
            $requestobject->origin_category_name = $res->data->course_category_name;
            $requestobject->origin_category_idnumber = $res->data->course_category_idnumber;
            coursetransfer_request::insert_or_update($requestobject, $requestobject->id);
            $success = true;
        } else {
            // 4b. Update Request DB Errors.
            $err = $res->errors;
            $errors = $res->errors;
            $requestobject->status = coursetransfer_request::STATUS_ERROR;
            $requestobject->error_code = $err[0]->code;
            $requestobject->error_message = $err[0]->msg;
            coursetransfer_request::insert_or_update($requestobject, $requestobject->id);
            $success = false;
        }
        return [
                'success' => $success,
                'errors' => $errors,
                'data' => [
                        'requestid' => $requestobject->id,
                ],
        ];
    }

    /**
     * Restore Course.
     *
     * @param stdClass $course
     * @param stdClass $user
     * @param stdClass $site
     * @param int $targetcategoryid
     * @param configuration_category $configuration
     * @param stdClass $requestobject
     * @param array $errors
     * @return array
     */
    protected static function restore_course(stdClass $course, stdClass $user, stdClass $site, int $targetcategoryid,
            configuration_category $configuration, stdClass $requestobject, array $errors): array {

        try {
            $success = true;
            // 1. Configuration Course.
            $configurationcourse = new configuration_course(
                    $configuration->targettarget,
                    $configuration->targetremoveenrols,
                    $configuration->targetremovegroups,
                    $configuration->originenrolusers,
                    false,
                    $configuration->nextruntime);

            // 2. Create new course in this category.
            $targetcourseid = course::create(
                    core_course_category::get($targetcategoryid),
                    $course->fullname, $course->shortname . '_' . uniqid());
            $origincourseid = $course->id;

            // 3. Request Restore Course.
            $courseres = coursetransfer_course::restore_unity(
                    $user, $site, $targetcourseid, $origincourseid, $configurationcourse, [], $requestobject->id);

            if (!$courseres['success']) {
                $success = false;
                $errors = array_merge($errors, $courseres['errors']);
            }

            // 4. Update category course requests.
            if (isset($courseres['data']['requestid'])) {
                $catcourserequests[] = $courseres['data']['requestid'];
                $requestobject->origin_category_requests = json_encode($catcourserequests);
                coursetransfer_request::insert_or_update($requestobject, $requestobject->id);
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors = [
                    'code' => '11301',
                    'msg' => $e->getMessage(),
            ];
        }


        return [
                'success' => $success,
                'errors' => $errors,
        ];
    }

    /**
     * Restore Category.
     *
     * @param stdClass $cat
     * @param stdClass $user
     * @param stdClass $site
     * @param int $parentid
     * @param configuration_category $configuration
     * @param stdClass $requestobject
     * @param array $errors
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected static function restore_category(stdClass $cat, stdClass $user, stdClass $site, int $parentid,
            configuration_category $configuration, stdClass $requestobject, array $errors): array {

        $success = true;

        $name = isset($cat->name) ? $cat->name : '';
        $idnumber = isset($cat->idnumber) ? $cat->idnumber : '';
        $desc = isset($cat->description) ? $cat->description : '';

        $targetcategoryid = category::create($name, $idnumber, $desc, $parentid);

        $courses = isset($cat->courses) ? $cat->courses : [];
        $cats = isset($cat->categories) ? $cat->categories : [];

        foreach ($courses as $course) {
            self::restore_course($course, $user, $site, $targetcategoryid, $configuration, $requestobject, $errors);
        }

        foreach ($cats as $cat) {
            self::restore_category($cat, $user, $site, $targetcategoryid, $configuration, $requestobject, $errors);
        }

        return [
                'success' => $success,
                'errors' => $errors,
        ];

    }


}
