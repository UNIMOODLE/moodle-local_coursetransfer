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
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\factory;

use coding_exception;
use context_system;
use dml_exception;

/**
 * role
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role {

    /**
     * Create roles.
     *
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function create_roles(): int {
        $name = 'Local Course Transfer WS';
        $shortname = user::USERNAME_WS;
        $desc = 'Local Course Transfer Role for services web and cli script';
        $arc = 'coursecreator';
        return self::create($name, $shortname, $desc, $arc);
    }

    /**
     * Create.
     *
     * @param string $name
     * @param string $shortname
     * @param string $desc
     * @param string $arc
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function create(string $name, string $shortname, string $desc, string $arc): int {
        global $DB;
        $record = $DB->get_record('role', ['shortname' => $shortname], '*');
        if (empty($record)) {
            return create_role($name, $shortname, $desc, $arc);
        } else {
            return $record->id;
        }
    }

    /**
     * Add capability.
     *
     * @param int $roleid
     * @param string $capability
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function add_capability(int $roleid, string $capability) {
        assign_capability($capability, CAP_ALLOW, $roleid, context_system::instance(), $overwrite = true);
    }

    /**
     * Add Role to User.
     *
     * @param int $roleid
     * @param int $userid
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function add_role_to_user(int $roleid, int $userid) {
        role_assign($roleid, $userid, context_system::instance());
    }
}
