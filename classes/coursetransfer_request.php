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
     * @param int $destinycourseid
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    public static function get_by_destiny_course_id(int $destinycourseid) {
        global $DB;
        return $DB->get_records(self::TABLE,
                ['destiny_course_id' => $destinycourseid, 'type' => 0, 'direction' => 0]);
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
