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
 * Coursetransfer Sites.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use dml_exception;
use moodle_exception;
use stdClass;

/**
 * coursetransfer_sites
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer_sites {

    /** @var string Table Prex */
    const TABLE_PREX = 'local_coursetransfer_';
    /** @var string Table Target */
    const TABLE_TARGET = 'local_coursetransfer_target';
    /** @var string Table Origin */
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
            throw new moodle_exception($type . ' site not valid: ' . $id);
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
     * Get by Target Course Id.
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
            throw new moodle_exception($type . ' site not valid: ' . $host);
        }
    }

    /**
     * Clean host.
     *
     * @param string $host
     * @return string
     */
    public static function clean_host(string $host): string {
        return rtrim($host, '/');
    }
}
