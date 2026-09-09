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

use coding_exception;
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
    public static function get(string $type, int $id): mixed {
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
     * @return array
     * @throws dml_exception
     */
    public static function list(string $type): array {
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
    public static function get_by_host(string $type, string $host): mixed {
        global $DB;
        // The length is explicit: the 32-character default of sql_compare_text() would compare only
        // the start of the URL, and two platforms sharing that prefix would resolve to the same row.
        $compare = $DB->sql_compare_text('host', 255);
        $compareplaceholder = $DB->sql_compare_text(':host', 255);
        $records = $DB->get_records_sql(
                "SELECT id, host, token
                    FROM {" . self::TABLE_PREX . $type . "}
                    WHERE {$compare} = {$compareplaceholder}",
                [
                        'host' => self::clean_host($host),
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

    /**
     * Unified platform list: merges origin and target site rows by host.
     *
     * Each platform aggregates the two possible roles of a remote Moodle:
     * "origin" (we pull courses from it) and "target" (it may request
     * courses from us). Rows sharing the same host are one platform.
     *
     * @return stdClass[] platforms, each with: name, host, origin (row|null),
     *                    target (row|null), lasttest, lastteststatus,
     *                    lasttesterror (most recent of both roles).
     * @throws dml_exception|coding_exception
     */
    public static function get_platforms(): array {
        $platforms = [];
        foreach (['origin', 'target'] as $type) {
            foreach (self::list($type) as $row) {
                $key = self::clean_host($row->host);
                if (!isset($platforms[$key])) {
                    $platform = new stdClass();
                    $platform->host = $key;
                    $platform->name = '';
                    $platform->origin = null;
                    $platform->target = null;
                    $platform->lasttest = null;
                    $platform->lastteststatus = null;
                    $platform->lasttesterror = null;
                    $platforms[$key] = $platform;
                }
                $platforms[$key]->{$type} = $row;
                if (!empty($row->name)) {
                    $platforms[$key]->name = $row->name;
                }
            }
        }
        foreach ($platforms as $platform) {
            if ($platform->name === '') {
                $platform->name = preg_replace('#^https?://#i', '', $platform->host);
            }
            self::set_platform_test_info($platform);
        }
        return array_values($platforms);
    }

    /**
     * Combine the persisted test results of both role rows into the
     * platform: status is OK only if every tested role is OK, and each
     * failure is labelled with its role so the admin knows which
     * direction of the pairing is broken.
     *
     * @param stdClass $platform
     * @throws coding_exception
     */
    protected static function set_platform_test_info(stdClass $platform): void {
        $tested = [];
        foreach (['origin', 'target'] as $type) {
            $row = $platform->{$type};
            if ($row && isset($row->lastteststatus)) {
                $tested[$type] = $row;
            }
        }
        if (empty($tested)) {
            return;
        }
        $status = 1;
        $errors = [];
        foreach ($tested as $type => $row) {
            $platform->lasttest = max((int)$platform->lasttest, (int)$row->lasttest);
            if ((int)$row->lastteststatus !== 1) {
                $status = 0;
                $errors[] = get_string('platforms_role_' . $type, 'local_coursetransfer')
                        . ' — ' . $row->lasttesterror;
            }
        }
        $platform->lastteststatus = $status;
        $platform->lasttesterror = $errors ? implode(' | ', $errors) : null;
    }

    /**
     * Persist the result of a connection test on a site row.
     *
     * @param string $type origin|target
     * @param int $id site row id
     * @param bool $ok test outcome
     * @param string $error error message when the test failed
     * @throws dml_exception
     */
    public static function save_test_result(string $type, int $id, bool $ok, string $error = ''): void {
        global $DB;
        $object = new stdClass();
        $object->id = $id;
        $object->lasttest = time();
        $object->lastteststatus = $ok ? 1 : 0;
        $object->lasttesterror = $ok ? null : $error;
        $DB->update_record(self::TABLE_PREX . $type, $object);
    }

    /**
     * Whether a platform host has requests registered (any direction).
     *
     * @param string $host
     * @return bool
     * @throws dml_exception
     */
    public static function is_in_use(string $host): bool {
        global $DB;
        // The siteurl column is TEXT, so a plain equality condition is rejected by the DML layer.
        // The length is explicit: the 32-character default would compare only the start of the URL.
        $select = $DB->sql_compare_text('siteurl', 255) . ' = ' . $DB->sql_compare_text(':siteurl', 255);
        return $DB->record_exists_select(
            'local_coursetransfer_request',
            $select,
            ['siteurl' => self::clean_host($host)]
        );
    }
}
