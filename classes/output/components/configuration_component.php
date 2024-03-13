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
 * Configuration Component.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output\components;

use backup;
use context_course;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\models\configuration_course;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * configuration_component
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configuration_component implements renderable, templatable {

    /** @var configuration_course Configuration */
    protected $configuration;

    /** @var int ID */
    protected $id;

    /**
     *  constructor.
     *
     * @param configuration_course $configuration
     * @param int $id
     */
    public function __construct(configuration_course $configuration, int $id) {
        $this->configuration = $configuration;
        $this->id = $id;
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->id = $this->id;
        $data->destiny_remove_activities = $this->configuration->destinytarget === backup::TARGET_EXISTING_DELETING;
        $data->destiny_merge_activities = $this->configuration->destinytarget === backup::TARGET_EXISTING_ADDING;
        $data->destiny_remove_enrols = $this->configuration->destinyremoveenrols;
        $data->destiny_remove_groups = $this->configuration->destinyremovegroups;
        $data->origin_remove_course = $this->configuration->originremovecourse;
        $data->course_new = $this->configuration->destinytarget === backup::TARGET_NEW_COURSE;
        $data->has_scheduled_time = empty($this->configuration->nextruntime) ? false : true;
        $data->origin_schedule = empty($this->configuration->nextruntime) ? false : true;
        $unixtime = $this->configuration->nextruntime;
        $data->origin_schedule_datetime = date("Y-m-d H:i:s", $unixtime);
        return $data;
    }
}
