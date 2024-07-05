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
 * Category.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\factory;

use core_course_category;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/externallib.php');

/**
 * category
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category {

    /**
     * Create
     *
     * @param string $name
     * @param string $idnumber
     * @param string $description
     * @return int
     * @throws moodle_exception
     */
    public static function create(string $name, ?string $idnumber, string $description = ''): int {
        $record = new stdClass();
        $record->name = $name;
        $record->description = $description;
        $record->idnumber = $idnumber;
        $res = core_course_category::create($record);
        return $res->id;
    }

    /**
     * Update
     *
     * @param int $id
     * @param string $name
     * @param string $idnumber
     * @param string $description
     * @throws moodle_exception
     */
    public static function update(int $id, string $name, string $idnumber, string $description = '') {
        $record = new stdClass();
        $record->id = $id;
        $record->name = $name;
        $record->idnumber = $idnumber;
        $record->description = $description;
        $cat = core_course_category::get($id);
        $cat->update($record);
    }

}
