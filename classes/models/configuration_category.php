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
 * Configuration Category.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\models;

/**
 * configuration_category
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configuration_category extends configuration {

    /** @var bool Origin Remove Category */
    public $originremovecategory;

    /**
     * constructor.
     *
     * @param int $targettarget
     * @param bool $targetremoveenrols
     * @param bool $targetremovegroups
     * @param bool $originenrolusers
     * @param bool $originremovecategory
     * @param int|null $nextruntime
     */
    public function __construct(
            int $targettarget, bool $targetremoveenrols, bool $targetremovegroups,
            bool $originenrolusers = false, bool $originremovecategory = false, int $nextruntime = null) {
        parent::__construct($targettarget, $targetremoveenrols, $targetremovegroups, $originenrolusers, $nextruntime);
        $this->set_origin_remove_category($originremovecategory);
    }

    /**
     * Set Origin Remove Category.
     *
     * @param bool $config
     */
    protected function set_origin_remove_category(bool $config) {
        $this->originremovecategory = $config;
    }

}
