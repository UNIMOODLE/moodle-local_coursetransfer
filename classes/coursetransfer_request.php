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

use course_modinfo;
use dml_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\api\response;
use moodle_exception;
use stdClass;

class coursetransfer_request {

    const TABLE = 'local_coursetransfer_request';

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
                ['destiny_course_id' => $courseid, 'type' => 0, 'direction' => 0]);
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
                ['destiny_category_id' => $catid, 'type' => 1, 'direction' => 0]);
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
                ['origin_course_id' => $courseid, 'type' => 0, 'direction' => 1]);
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
                ['origin_category_id' => $catid, 'type' => 1, 'direction' => 1]);
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
        if (!in_array($object->origin_remove_course, [0, 1])) {
            throw new moodle_exception('ORIGIN REMOVE COURSE IS NOT VALID');
        }
        if (!in_array($object->destiny_remove_activities, [0, 1])) {
            throw new moodle_exception('DESTINY REMOVE ACTIVITIES IS NOT VALID');
        }
        if (!in_array($object->destiny_merge_activities, [0, 1])) {
            throw new moodle_exception('DESTINY MERGE ACTIVITIES IS NOT VALID');
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
}
