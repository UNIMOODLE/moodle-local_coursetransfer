<?php
// This file is part of the local_amnh plugin for Moodle - http://moodle.org/
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
 * coursetransfer_request
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use dml_exception;
use local_coursetransfer\models\configuration_category;
use local_coursetransfer\models\configuration_course;
use moodle_exception;
use stdClass;

class coursetransfer_request {

    const TABLE = 'local_coursetransfer_request';

    const TYPE_COURSE = 0;
    const TYPE_CATEGORY = 1;
    const TYPE_REMOVE_COURSE = 2;

    const DIRECTION_REQUEST = 0;
    const DIRECTION_RESPONSE = 1;

    const STATUS_ERROR = 0;
    const STATUS_NOT_STARTED = 1;
    const STATUS_IN_PROGRESS = 10;
    const STATUS_BACKUP = 30;
    const STATUS_DOWNLOAD = 50;
    const STATUS_DOWNLOADED = 70;
    const STATUS_RESTORE = 80;
    const STATUS_INCOMPLETED = 90;
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
     * Get by Destiny Course Id.
     *
     * @param int $courseid
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    public static function get_by_destiny_course_id(int $courseid) {
        global $DB;
        return $DB->get_records(self::TABLE,
                ['destiny_course_id' => $courseid, 'type' => self::TYPE_COURSE, 'direction' => self::DIRECTION_REQUEST]);
    }

    /**
     * Get by Destiny Category Id.
     *
     * @param int $catid
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    public static function get_by_destiny_category_id(int $catid) {
        global $DB;
        return $DB->get_records(self::TABLE,
                ['destiny_category_id' => $catid, 'type' => self::TYPE_CATEGORY, 'direction' => self::DIRECTION_REQUEST]);
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
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function update_status_request_cat(int $requestid) {
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
        if (!in_array($object->type, [0, 1])) {
            throw new moodle_exception('TYPE IS NOT VALID');
        }
        if (!in_array($object->direction, [0, 1])) {
            throw new moodle_exception('DIRECTION IS NOT VALID');
        }
        if (!in_array($object->origin_enrolusers, [0, 1])) {
            throw new moodle_exception('ORIGIN ENROL USERS IS NOT VALID');
        }
        if (!in_array($object->destiny_target, [2, 3, 4])) {
            throw new moodle_exception('DESTINY TARGET IS NOT VALID (2,3,4)');
        }
        if (!in_array($object->destiny_remove_enrols, [0, 1])) {
            throw new moodle_exception('DESTINY REMOVE ENROLS IS NOT VALID');
        }
        if (!in_array($object->destiny_remove_groups, [0, 1])) {
            throw new moodle_exception('DESTINY REMOVE GROUPS IS NOT VALID');
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
     * @param stdClass $site
     * @param int $destinycourseid
     * @param int $origincourseid
     * @param configuration_course $configuration $configuration
     * @param array $sections
     * @param int|null $requestcatid
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_restore_course(
            stdClass $site, int $destinycourseid, int $origincourseid, configuration_course $configuration,
            array $sections, int $requestcatid = null): stdClass {
        global $USER;
        $object = new stdClass();
        $object->type = self::TYPE_COURSE;
        $object->siteurl = $site->host;
        $object->direction = self::DIRECTION_REQUEST;
        $object->destiny_course_id = $destinycourseid;
        $object->origin_course_id = $origincourseid;
        $object->origin_enrolusers = $configuration->originenrolusers;
        $object->request_category_id = $requestcatid;
        $object->origin_activities = json_encode($sections);
        $object->destiny_remove_enrols = $configuration->destinyremoveenrols;
        $object->destiny_remove_groups = $configuration->destinyremovegroups;
        $object->origin_remove_course = $configuration->originremovecourse;
        $object->destiny_notremove_activities = $configuration->destinynotremoveactivities;
        $object->origin_backup_size_estimated = coursetransfer::get_backup_size_estimated($origincourseid);
        $object->destiny_target = $configuration->destinytarget;
        $object->status = self::STATUS_NOT_STARTED;
        $object->userid = $USER->id;
        $object->id = self::insert_or_update($object);
        return $object;
    }

    /**
     * Set Request Object Restore Category.
     *
     * @param stdClass $site
     * @param int $destinycategoryid
     * @param int $origincategoryid
     * @param configuration_category $configuration $configuration
     * @param array $courses
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_restore_category(
            stdClass $site, int $destinycategoryid, int $origincategoryid,
            configuration_category $configuration, array $courses): stdClass {
        global $USER;
        $object = new stdClass();
        $object->type = self::TYPE_CATEGORY;;
        $object->siteurl = $site->host;
        $object->direction = self::DIRECTION_REQUEST;
        $object->destiny_category_id = $destinycategoryid;
        $object->origin_category_id = $origincategoryid;
        $object->origin_category_courses = json_encode($courses);
        $object->origin_enrolusers = $configuration->originenrolusers;
        $object->destiny_remove_enrols = $configuration->destinyremoveenrols;
        $object->destiny_remove_groups = $configuration->destinyremovegroups;
        $object->origin_remove_category = $configuration->originremovecategory;
        $object->destiny_target = $configuration->destinytarget;
        $object->status = self::STATUS_NOT_STARTED;
        $object->userid = $USER->id;
        $object->id = self::insert_or_update($object);
        return $object;
    }
}
