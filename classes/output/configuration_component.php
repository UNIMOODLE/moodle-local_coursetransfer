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

/**
 * Local Course Transfer
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use backup;
use local_coursetransfer\models\configuration_course;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * activities_component
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
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
        $data->has_origin_user_data = true;
        $data->can_remove_origin_course = true;
        return $data;
    }
}
