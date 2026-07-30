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
 * cli_helper
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
use local_coursetransfer\factory\user;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared helpers for the plugin CLI scripts.
 *
 * NOTE: these helpers deliberately preserve the existing CLI contract
 * (argument names, exit codes, error codes and success output are consumed by
 * third-party automation). They only centralise duplicated logic without
 * changing observable behaviour.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cli_helper {

    /** @var int Exit code: success. */
    const EXIT_OK = 0;

    /** @var int Exit code: runtime error (operation failed). */
    const EXIT_RUNTIME = 1;

    /** @var int Exit code: usage/validation error (bad arguments). */
    const EXIT_USAGE = 2;

    /** @var int Maximum deferral (days) for a scheduled operation. */
    const SCHEDULE_MAX_DAYS = 30;

    /**
     * Normalise a CLI option to an integer boolean (0|1), keeping the historical
     * semantics: only `true` or `1` are truthy.
     *
     * @param mixed $value
     * @return int 0|1
     */
    public static function to_bool(mixed $value): int {
        return ($value === 'true' || (int) $value === 1) ? 1 : 0;
    }

    /**
     * Validate a scheduled-execution timestamp: 0 (ASAP) or within the allowed
     * future window (max 30 days).
     *
     * @param int $timestamp
     * @return bool
     */
    public static function schedule_is_valid(int $timestamp): bool {
        if ($timestamp === 0) {
            return true;
        }
        $now = time();
        $max = $now + (DAYSECS * self::SCHEDULE_MAX_DAYS);
        return $timestamp >= $now && $timestamp <= $max;
    }

    /**
     * Validate the scheduled timestamp; abort with a usage error if invalid,
     * otherwise echo the scheduled time (for a deferred run).
     *
     * @param int $timestamp
     * @throws coding_exception
     */
    public static function check_schedule(int $timestamp): void {
        if (!self::schedule_is_valid($timestamp)) {
            cli_error(get_string('cli_schedule_invalid', 'local_coursetransfer'), self::EXIT_USAGE);
        }
        if ($timestamp !== 0) {
            cli_writeln(get_string('cli_scheduler_time', 'local_coursetransfer', userdate($timestamp)));
        }
    }

    /**
     * Return the web service service user, or abort the CLI with a clear message
     * pointing at postinstall if it does not exist yet (instead of an opaque
     * fatal further down). Exit code stays 1, as in the existing error paths.
     *
     * @return stdClass
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function require_ws_user(): \stdClass {
        $wsuser = \core_user::get_user_by_username(user::USERNAME_WS);
        if (!$wsuser) {
            cli_error(get_string('cli_ws_user_missing', 'local_coursetransfer'), self::EXIT_RUNTIME);
        }
        return $wsuser;
    }
}
