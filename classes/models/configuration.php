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
 * Configuration.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\models;

/**
 * configuration
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class configuration {

    /** @var int Destinity Target: 2: New Course, 3: Remove Content , 4: Merge the backup course into this course */
    public $targettarget;

    /** @var bool Destinity Remove Enrols */
    public $targetremoveenrols;

    /** @var bool Destinity Remove Groups */
    public $targetremovegroups;

    /** @var bool Origin Enrol Users */
    public $originenrolusers;

    /** @var int Next Run Time TimeStamp */
    public $nextruntime;

    /**
     * constructor.
     *
     * @param int $targettarget
     * @param bool $targetremoveenrols
     * @param bool $targetremovegroups
     * @param bool $originenrolusers
     * @param int|null $nextruntime
     */
    public function __construct(
            int $targettarget,
            bool $targetremoveenrols,
            bool $targetremovegroups,
            bool $originenrolusers,
            int $nextruntime = null
    ) {
        $this->set_target_target($targettarget);
        $this->set_target_remove_enrols($targetremoveenrols);
        $this->set_target_remove_groups($targetremovegroups);
        $this->set_origin_enrol_users($originenrolusers);
        $this->set_nextruntime($nextruntime);
    }

    /**
     * Set Target Remove Enrols.
     *
     * @param int $config
     */
    protected function set_target_target(int $config) {
        $this->targettarget = $config;
    }

    /**
     * Set Target Remove Enrols.
     *
     * @param bool $config
     */
    protected function set_target_remove_enrols(bool $config) {
        $this->targetremoveenrols = $config;
    }

    /**
     * Set Target Remove Groups.
     *
     * @param bool $config
     */
    protected function set_target_remove_groups(bool $config) {
        $this->targetremovegroups = $config;
    }

    /**
     * Set Origin Enrol Users.
     *
     * @param bool $config
     */
    protected function set_origin_enrol_users(bool $config) {
        $this->originenrolusers = $config;
    }

    /**
     * Set Next Run Time.
     *
     * @param int|null $config
     */
    protected function set_nextruntime(int $config = null) {
        if (!is_null($config)) {
            $this->nextruntime = $config;
        }
    }

}
