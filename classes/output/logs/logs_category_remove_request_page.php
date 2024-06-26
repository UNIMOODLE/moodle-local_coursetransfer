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
 * Logs Category remove request page.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_coursetransfer\output\logs;

use coding_exception;
use local_coursetransfer\tables\logs_category_remove_request_table;
use moodle_exception;
use moodle_url;

/**
 * logs_category_remove_request_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logs_category_remove_request_page extends logs_page {

    /**
     *  constructor.
     *
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function __construct() {
        parent::__construct();
        $uniqid = uniqid('', true);
        $this->table = new logs_category_remove_request_table($uniqid);
        $this->url = new moodle_url(self::PAGE);
        $this->selects = [
                'type' => [
                        [
                                'value' => 0,
                                'name' => get_string('restore_course', 'local_coursetransfer'),
                                'selected' => false,
                        ],
                        [
                                'value' => 1,
                                'name' => get_string('restore_category', 'local_coursetransfer'),
                                'selected' => false,
                        ],
                        [
                                'value' => 2,
                                'name' => get_string('remove_course', 'local_coursetransfer'),
                                'selected' => false,
                        ],
                        [
                                'value' => 3,
                                'name' => get_string('remove_category', 'local_coursetransfer'),
                                'selected' => true,
                        ],
                ],
                'direction' => [
                        [
                                'value' => 0,
                                'name' => get_string('request', 'local_coursetransfer'),
                                'selected' => true,
                        ],
                        [
                                'value' => 1,
                                'name' => get_string('response', 'local_coursetransfer'),
                                'selected' => false,
                        ],
                ],
        ];
    }


}
