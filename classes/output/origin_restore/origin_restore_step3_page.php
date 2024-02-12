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

namespace local_coursetransfer\output\origin_restore;

use context_system;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
use renderer_base;
use stdClass;

/**
 * origin_restore_step3_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_restore_step3_page extends origin_restore_step_page {

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;
        $data = new stdClass();
        $data->button = true;
        $data->steps = self::get_steps(3);
        $tableurl = new moodle_url(self::URL);
        $backurl = new moodle_url(self::URL,
                ['step' => 2, 'site' => $this->site, 'type' => 'courses', 'page' => $this->page, ]
        );
        $nexturl = new moodle_url(self::URL,
                ['step' => 4, 'site' => $this->site, 'type' => 'courses']
        );
        $data->table_url = $tableurl->out(false);
        $data->back_url = $backurl->out(false);
        $data->next_url = $nexturl->out(false);
        $data->next_url_disabled = false;

        $data->has_origin_user_data = coursetransfer::has_origin_user_data($USER);
        $data->can_remove_origin_course = coursetransfer::can_remove_origin_course($USER);
        $data->can_destiny_restore_merge = coursetransfer::can_destiny_restore_merge($USER, context_system::instance());
        $data->can_destiny_restore_content_remove =
                coursetransfer::can_destiny_restore_content_remove($USER, context_system::instance());
        $data->can_destiny_restore_groups_remove = coursetransfer::can_destiny_restore_groups_remove($USER);
        $data->can_destiny_restore_enrol_remove = coursetransfer::can_destiny_restore_enrol_remove($USER);
        $data->restore_this_course =
                $data->can_destiny_restore_merge || $data->can_destiny_restore_content_remove;
        $data->remove_in_destination =
                $data->can_destiny_restore_groups_remove || $data->can_destiny_restore_enrol_remove;
        $data->has_scheduled_time = true;
        $data->origin_course_configuration = $data->has_origin_user_data || $data->has_scheduled_time;
        return $data;
    }
}
