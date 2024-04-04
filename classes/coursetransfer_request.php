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
 * Coursetransfer Request.
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
use local_coursetransfer\models\configuration_category;
use local_coursetransfer\models\configuration_course;
use moodle_exception;
use stdClass;

/**
 * coursetransfer_request
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer_request {

    /** @var string Table */
    const TABLE = 'local_coursetransfer_request';

    /** @var int Type Course */
    const TYPE_COURSE = 0;
    /** @var int Type Category */
    const TYPE_CATEGORY = 1;
    /** @var int Type Remove Course */
    const TYPE_REMOVE_COURSE = 2;
    /** @var int Type Remove Category */
    const TYPE_REMOVE_CATEGORY = 3;

    /** @var int Direction Request */
    const DIRECTION_REQUEST = 0;
    /** @var int Direction Response */
    const DIRECTION_RESPONSE = 1;

    /** @var int Status Error */
    const STATUS_ERROR = 0;
    /** @var int Status not started */
    const STATUS_NOT_STARTED = 1;
    /** @var int Status in progress */
    const STATUS_IN_PROGRESS = 10;
    /** @var int Status Backup */
    const STATUS_BACKUP = 30;
    /** @var int Status Download */
    const STATUS_DOWNLOAD = 50;
    /** @var int Status Downloaded */
    const STATUS_DOWNLOADED = 70;
    /** @var int Status Restore */
    const STATUS_RESTORE = 80;
    /** @var int Status Incompleted */
    const STATUS_INCOMPLETED = 90;
    /** @var int Status Completed */
    const STATUS_COMPLETED = 100;

    /**
     * Get.
     *
     * @param int $requestid
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    public static function get(int $requestid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $requestid]);
    }

    /**
     * Get by Target Course Id.
     *
     * @param int $courseid
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    public static function get_by_target_course_id(int $courseid) {
        global $DB;
        return $DB->get_records(self::TABLE,
                ['target_course_id' => $courseid, 'type' => self::TYPE_COURSE, 'direction' => self::DIRECTION_REQUEST]);
    }

    /**
     * Get by Target Category Id.
     *
     * @param int $catid
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    public static function get_by_target_category_id(int $catid) {
        global $DB;
        return $DB->get_records(self::TABLE,
                ['target_category_id' => $catid, 'type' => self::TYPE_CATEGORY, 'direction' => self::DIRECTION_REQUEST]);
    }

    /**
     * Get by Origin Course Id.
     *
     * @param int $courseid
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    public static function get_by_origin_course_id(int $courseid) {
        global $DB;
        return $DB->get_records(self::TABLE,
                ['origin_course_id' => $courseid, 'type' => self::TYPE_COURSE, 'direction' => self::DIRECTION_RESPONSE]);
    }

    /**
     * Get by Origin Category Id.
     *
     * @param int $catid
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    public static function get_by_origin_category_id(int $catid) {
        global $DB;
        return $DB->get_records(self::TABLE,
                ['origin_category_id' => $catid, 'type' => self::TYPE_CATEGORY, 'direction' => self::DIRECTION_RESPONSE]);
    }

    /**
     * Filters
     *
     * @param array $filters
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    public static function filters(array $filters) {
        global $DB;
        $where = '';
        if (isset($filters['type'])) {
            if (empty($where)) {
                $where .= 'WHERE type = ' . $filters['type'];
            } else {
                $where .= ' AND type = ' . $filters['type'];
            }
        }
        if (isset($filters['direction'])) {
            if (empty($where)) {
                $where .= 'WHERE direction = ' . $filters['direction'];
            } else {
                $where .= ' AND direction = ' . $filters['direction'];
            }
        }
        if (isset($filters['status'])) {
            if (empty($where)) {
                $where .= 'WHERE status = ' . $filters['status'];
            } else {
                $where .= ' AND status = ' . $filters['status'];
            }
        }
        if (isset($filters['userid'])) {
            if (empty($where)) {
                $where .= 'WHERE userid = ' . $filters['userid'];
            } else {
                $where .= ' AND userid = ' . $filters['userid'];
            }
        }
        if (isset($filters['from'])) {
            if (empty($where)) {
                $where .= 'WHERE timemodified >= ' . $filters['from'];
            } else {
                $where .= ' AND timemodified >= ' . $filters['from'];
            }
        }
        if (isset($filters['to'])) {
            if (empty($where)) {
                $where .= 'WHERE timemodified <= ' . $filters['to'];
            } else {
                $where .= ' AND timemodified <= ' . $filters['to'];
            }
        }

        $sql = 'SELECT *
                FROM {' . self::TABLE . '}
                ' . $where . '
                LIMIT 201';

        return $DB->get_records_sql($sql);
    }

    /**
     * Update status request category.
     *
     * @param int $requestid
     * @return false|mixed|stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function update_status_request_cat(int $requestid): stdClass {
        global $DB;

        $reqcat = $DB->get_record(self::TABLE, ['id' => $requestid]);
        $courses = $DB->get_records(self::TABLE,
                ['request_category_id' => $requestid, 'type' => self::TYPE_COURSE, 'direction' => self::DIRECTION_REQUEST]);

        $completed = 1;
        foreach ($courses as $course) {
            if ($course->status < self::STATUS_COMPLETED) {
                $completed = 0;
                break;
            }
        }
        $reqcat->status = $completed === 1 ? self::STATUS_COMPLETED : self::STATUS_INCOMPLETED;
        self::insert_or_update($reqcat, $requestid);
        return $reqcat;
    }

    /**
     * Insert or update row in table.
     *
     * @param stdClass $object
     * @param int|null $id
     * @return bool|int
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function insert_or_update(stdClass $object, int $id = null) {
        global $DB;
        if (!array_key_exists($object->status, coursetransfer::STATUS)) {
            throw new moodle_exception('STATUS IS NOT VALID');
        }
        if (!in_array($object->type, [0, 1, 2, 3])) {
            throw new moodle_exception('TYPE IS NOT VALID');
        }
        if (!in_array($object->direction, [0, 1])) {
            throw new moodle_exception('DIRECTION IS NOT VALID');
        }
        $object->timemodified = time();
        if (is_null($id)) {
            $object->timecreated = time();
            return $DB->insert_record(self::TABLE, $object);
        } else {
            $object->id = $id;
            return $DB->update_record(self::TABLE, $object);
        }
    }

    /**
     * Set Request Restore Course.
     *
     * @param stdClass $user
     * @param stdClass $site
     * @param int $targetcourseid
     * @param int $origincourseid
     * @param configuration_course $configuration $configuration
     * @param array $sections
     * @param int|null $requestcatid
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_restore_course(stdClass $user,
            stdClass $site, int $targetcourseid, int $origincourseid, configuration_course $configuration,
            array $sections, int $requestcatid = null): stdClass {
        global $USER;
        $userid = is_null($user) ? $USER->id : $user->id;
        $object = new stdClass();
        $object->type = self::TYPE_COURSE;
        $object->siteurl = $site->host;
        $object->direction = self::DIRECTION_REQUEST;
        $object->target_course_id = $targetcourseid;
        $object->origin_course_id = $origincourseid;
        $object->origin_enrolusers = $configuration->originenrolusers;
        $object->request_category_id = $requestcatid;
        $object->origin_activities = json_encode($sections);
        $object->target_remove_enrols = $configuration->targetremoveenrols;
        $object->target_remove_groups = $configuration->targetremovegroups;
        $object->origin_remove_course = $configuration->originremovecourse;
        $object->target_notremove_activities = $configuration->targetnotremoveactivities;
        $object->origin_backup_size_estimated = 0;
        $object->origin_schedule_datetime = $configuration->nextruntime;
        $object->target_target = $configuration->targettarget;
        $object->status = self::STATUS_NOT_STARTED;
        $object->userid = $userid;
        $object->id = self::insert_or_update($object);
        return $object;
    }

    /**
     * Set Request Restore Course.
     *
     * @param stdClass $user
     * @param int $targetrequestid
     * @param stdClass $targetsite
     * @param int $targetcourseid
     * @param stdClass $origincourse
     * @param configuration_course $configuration $configuration
     * @param array $sections
     * @param int|null $requestcatid
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_restore_course_response(stdClass $user, int $targetrequestid,
            stdClass $targetsite, int $targetcourseid, stdClass $origincourse, configuration_course $configuration,
            array $sections, int $requestcatid = null): stdClass {
        global $USER;
        $user = is_null($user) ? $USER : $user;
        $origincat = core_course_category::get($origincourse->category, MUST_EXIST);

        $object = new stdClass();
        $object->type = self::TYPE_COURSE;
        $object->siteurl = $targetsite->host;
        $object->direction = self::DIRECTION_RESPONSE;
        $object->target_request_id = $targetrequestid;
        $object->request_category_id = $requestcatid;
        $object->origin_course_id = $origincourse->id;
        $object->origin_course_fullname = $origincourse->fullname;
        $object->origin_course_shortname = $origincourse->shortname;
        $object->origin_category_id = $origincourse->category;
        $object->origin_category_idnumber = $origincourse->idnumber;
        $object->origin_category_name = $origincat->name;
        $object->origin_enrolusers = $configuration->originenrolusers;
        $object->origin_remove_course = $configuration->originremovecourse;
        $object->origin_remove_category = null;
        $object->origin_schedule_datetime = $configuration->nextruntime;
        $object->origin_remove_activities = 0;
        $object->origin_activities = json_encode($sections);
        $object->origin_category_requests = null;
        $object->origin_backup_size = null;
        $object->origin_backup_size_estimated = null;
        $object->origin_backup_url = null;
        $object->target_course_id = $targetcourseid;
        $object->target_category_id = null;
        $object->target_remove_enrols = $configuration->targetremoveenrols;
        $object->target_remove_groups = $configuration->targetremovegroups;
        $object->target_target = $configuration->targettarget;
        $object->error_code = null;
        $object->error_message = null;
        $object->userid = $user->id;
        $object->status = self::STATUS_NOT_STARTED;
        $object->id = self::insert_or_update($object);
        return $object;
    }

    /**
     * Set Request Object Restore Category.
     *
     * @param stdClass $site
     * @param int $targetcategoryid
     * @param int $origincategoryid
     * @param string $origincategoryname
     * @param configuration_category $configuration $configuration
     * @param stdClass $user
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_restore_category(
            stdClass $site, int $targetcategoryid, int $origincategoryid, string $origincategoryname,
            configuration_category $configuration, stdClass $user): stdClass {
        global $USER;
        $userid = is_null($user) ? $USER->id : $user->id;
        $object = new stdClass();
        $object->type = self::TYPE_CATEGORY;;
        $object->siteurl = $site->host;
        $object->direction = self::DIRECTION_REQUEST;
        $object->target_category_id = $targetcategoryid;
        $object->origin_category_id = $origincategoryid;
        $object->origin_category_name = $origincategoryname;
        $object->origin_category_requests = json_encode([]);
        $object->origin_activities = json_encode([]);
        $object->origin_enrolusers = $configuration->originenrolusers;
        $object->target_remove_enrols = $configuration->targetremoveenrols;
        $object->target_remove_groups = $configuration->targetremovegroups;
        $object->origin_remove_category = $configuration->originremovecategory;
        $object->origin_schedule_datetime = $configuration->nextruntime;
        $object->target_target = $configuration->targettarget;
        $object->status = self::STATUS_NOT_STARTED;
        $object->userid = $userid;
        $object->id = self::insert_or_update($object);
        return $object;
    }

    /**
     * Get Status Category Request.
     *
     * @param int $requestid
     * @return int
     * @throws dml_exception
     */
    public static function get_status_category_request(int $requestid): int {
        $request = self::get($requestid);
        $status = self::STATUS_NOT_STARTED;
        $requests = json_decode($request->origin_category_requests);
        $total = count($requests);
        foreach ($requests as $req) {
            $courserequest = self::get($req);
            if ((int)$courserequest->status > self::STATUS_NOT_STARTED) {
                $status = self::STATUS_IN_PROGRESS;
            }
            if ((int)$courserequest->status === self::STATUS_COMPLETED) {
                $total--;
            }
        }
        if ($total === 0) {
            $status = self::STATUS_COMPLETED;
        }
        return $status;
    }

    /**
     * Set Request Remove Course.
     *
     * @param stdClass $site
     * @param int $origincourseid
     * @param stdClass|null $user
     * @param null $nextruntime
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_remove_course(
            stdClass $site, int $origincourseid, stdClass $user = null, $nextruntime = null): stdClass {
        global $USER;
        $userid = is_null($user) ? $USER->id : $user->id;
        $object = new stdClass();
        $object->type = self::TYPE_REMOVE_COURSE;
        $object->siteurl = $site->host;
        $object->direction = self::DIRECTION_REQUEST;
        $object->origin_course_id = $origincourseid;
        $object->origin_schedule_datetime = $nextruntime;
        $object->status = self::STATUS_NOT_STARTED;
        $object->userid = $userid;
        $object->id = self::insert_or_update($object);
        return $object;
    }

    /**
     * Set Request Remove Category.
     *
     * @param stdClass $site
     * @param int $origincatid
     * @param stdClass|null $user
     * @param int $nextruntime
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_remove_category(
            stdClass $site, int $origincatid, stdClass $user = null, $nextruntime = null): stdClass {
        global $USER;
        $userid = is_null($user) ? $USER->id : $user->id;
        $object = new stdClass();
        $object->type = self::TYPE_REMOVE_CATEGORY;
        $object->siteurl = $site->host;
        $object->direction = self::DIRECTION_REQUEST;
        $object->origin_category_id = $origincatid;
        $object->origin_schedule_datetime = $nextruntime;
        $object->status = self::STATUS_NOT_STARTED;
        $object->userid = $userid;
        $object->id = self::insert_or_update($object);
        return $object;
    }
}
