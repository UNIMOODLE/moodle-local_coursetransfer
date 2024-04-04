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
 * new_origin_restore_category_step4_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output\origin_restore_category;

use coding_exception;
use context_system;
use core_course_category;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
use renderer_base;
use stdClass;

/**
 * new_origin_restore_category_step4_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class new_origin_restore_category_step4_page extends new_origin_restore_category_step_page {

    /**
     *  constructor.
     *
     * @param core_course_category $category
     * @throws coding_exception
     */
    public function __construct(core_course_category $category) {
        parent::__construct($category);
        $this->site = required_param('site', PARAM_INT);
        $this->restoreid = required_param('restoreid', PARAM_INT);
        $this->targetid = required_param('id', PARAM_INT);
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;
        $backurl = new moodle_url(self::URL, [
                    'id' => $this->category->id,
                    'new' => 1, 'step' => 3, 'site' => $this->site, 'restoreid' => $this->restoreid]
        );
        $tableurl = new moodle_url(self::URL, ['id' => $this->category->id]);
        $data = new stdClass();
        $data->button = false;
        $data->restoreid = $this->restoreid;
        $data->targetid = $this->targetid;
        $data->siteposition = $this->site;
        $data->steps = self::get_steps(4);
        $data->back_url = $backurl->out(false);
        $data->table_url = $tableurl->out(false);
        $site = coursetransfer::get_site_by_position($this->site);
        $data->host = $site->host;
        if (coursetransfer::validate_origin_site($site->host)) {
            $data->haserrors = false;
            try {
                $request = new request($site);
                $res = $request->origin_get_category_detail($this->restoreid, $USER);
                if ($res->success) {
                    $data->category = $res->data;
                    $data->category->sessionStorage_id = "local_coursetransfer_".$this->category->id."_".$data->restoreid;
                } else {
                    $data->errors = $res->errors;
                    $data->haserrors = true;
                }
            } catch (moodle_exception $e) {
                $data->errors = ['code' => '21402', 'msg' => $e->getMessage()];
                $data->haserrors = true;
            }
        } else {
            $data->haserrors = true;
            $errors[] = ['code' => '21401',
                    'msg' => get_string('error_validate_site', 'local_coursetransfer')];
            $data->errors = $errors;
        }
        $data->has_origin_user_data = coursetransfer::has_origin_user_data($USER);
        $data->has_scheduled_time = true;
        $data->can_remove_origin_course = false;
        $data->can_target_restore_merge = false;
        $data->can_target_restore_content_remove = false;
        $data->can_target_restore_groups_remove = false;
        $data->can_target_restore_enrol_remove = false;
        $data->restore_this_course =
                $data->can_target_restore_merge || $data->can_target_restore_content_remove;
        $data->remove_in_destination =
                $data->can_target_restore_groups_remove || $data->can_target_restore_enrol_remove;
        $data->origin_course_configuration = $data->has_origin_user_data || $data->has_scheduled_time;
        return $data;
    }

}

