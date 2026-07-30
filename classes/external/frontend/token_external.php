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
 * Token web services (summary screen): create / revoke / regenerate the
 * service-user token of this site. Site-admin only.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external\frontend;

use coding_exception;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use dml_exception;
use local_coursetransfer\factory\user;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * token_external
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_external extends external_api {

    /**
     * Common returns: success + token + errors.
     *
     * @return external_single_structure
     */
    protected static function token_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
            'token' => new external_value(PARAM_RAW, 'Current token (empty if none)', VALUE_DEFAULT, ''),
            'errors' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_TEXT, 'Code'),
                'msg' => new external_value(PARAM_RAW, 'Message'),
            ])),
        ]);
    }

    /**
     * Guard: only a site administrator may manage the token.
     *
     * @throws moodle_exception
     */
    protected static function require_admin(): void {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);
    }

    /**
     * Resolve the service user id.
     *
     * @return int
     * @throws dml_exception
     */
    protected static function ws_userid(): int {
        $wsuser = \core_user::get_user_by_username(user::USERNAME_WS);
        return $wsuser ? (int)$wsuser->id : 0;
    }

    /**
     * Create Parameters.
     *
     * @return external_function_parameters
     */
    public static function create_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Create the token (repairing the service user/role if needed).
     *
     * @return array
     * @throws coding_exception|moodle_exception
     */
    public static function create(): array {
        self::require_admin();
        $errors = [];
        $token = '';
        try {
            // postinstall() creates/repairs user+role+capabilities+WS+REST and
            // returns the token (creating it if missing).
            $token = (string)\local_coursetransfer\coursetransfer::postinstall();
        } catch (moodle_exception $e) {
            $errors[] = ['code' => '31001', 'msg' => $e->getMessage()];
        }
        return ['success' => empty($errors) && $token !== '', 'token' => $token, 'errors' => $errors];
    }

    /**
     * Create returns.
     *
     * @return external_single_structure
     */
    public static function create_returns(): external_single_structure {
        return self::token_returns();
    }

    /**
     * @return external_function_parameters
     */
    public static function revoke_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Revoke (delete) the token.
     *
     * @return array
     * @throws coding_exception|moodle_exception
     */
    public static function revoke(): array {
        self::require_admin();
        $errors = [];
        try {
            $userid = self::ws_userid();
            if ($userid > 0) {
                user::revoke_token($userid);
            } else {
                $errors[] = ['code' => '31002', 'msg' => get_string('token_not_found', 'local_coursetransfer')];
            }
        } catch (moodle_exception $e) {
            $errors[] = ['code' => '31003', 'msg' => $e->getMessage()];
        }
        return ['success' => empty($errors), 'token' => '', 'errors' => $errors];
    }

    /**
     * Revoke returns.
     *
     * @return external_single_structure
     */
    public static function revoke_returns(): external_single_structure {
        return self::token_returns();
    }

    /**
     * Regenerate Parameters.
     *
     * @return external_function_parameters
     */
    public static function regenerate_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Regenerate the token (revoke + create new).
     *
     * @return array
     * @throws coding_exception|moodle_exception
     */
    public static function regenerate(): array {
        self::require_admin();
        $errors = [];
        $token = '';
        try {
            $userid = self::ws_userid();
            if ($userid > 0) {
                $token = (string)user::regenerate_token($userid);
            } else {
                // No service user yet: create the whole config.
                $token = (string)\local_coursetransfer\coursetransfer::postinstall();
            }
        } catch (moodle_exception $e) {
            $errors[] = ['code' => '31004', 'msg' => $e->getMessage()];
        }
        return ['success' => empty($errors) && $token !== '', 'token' => $token, 'errors' => $errors];
    }

    /**
     * Regenerate returns.
     *
     * @return external_single_structure
     */
    public static function regenerate_returns(): external_single_structure {
        return self::token_returns();
    }
}
