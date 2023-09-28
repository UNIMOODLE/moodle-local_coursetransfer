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
 * coursetransfer_sites
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

class coursetransfer_sites {

    const TABLE_PREX = 'local_coursetransfer_';
    const TABLE_DESTINY = 'local_coursetransfer_destiny';
    const TABLE_ORIGIN = 'local_coursetransfer_origin';

    /**
     * Get.
     *
     * @param string $type
     * @param int $id
     * @return false|mixed|stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get(string $type, int $id) {
        global $DB;
        $record = $DB->get_record(self::TABLE_PREX . $type, ['id' => $id]);
        if ($record) {
            return $record;
        } else {
            throw new moodle_exception('SITE NOT VALID');
        }
    }

    /**
     * List.
     *
     * @param string $type
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    public static function list(string $type) {
        global $DB;
        return $DB->get_records(self::TABLE_PREX . $type);
    }

    /**
     * Get by Destiny Course Id.
     *
     * @param string $type
     * @param string $host
     * @return false|mixed|stdClass
     * @throws dml_exception|moodle_exception
     */
    public static function get_by_host(string $type, string $host) {
        global $DB;
        $compare = $DB->sql_compare_text('host');
        $compareplaceholder = $DB->sql_compare_text(':host');
        $records = $DB->get_records_sql(
                "SELECT id, host, token
                    FROM {" . self::TABLE_PREX . $type . "}
                    WHERE {$compare} = {$compareplaceholder}",
                [
                        'host' => $host,
                ]
        );
        if ($records) {
            return current($records);
        } else {
            throw new moodle_exception('SITE NOT VALID');
        }
    }
}
