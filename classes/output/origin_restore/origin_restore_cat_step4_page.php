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
 * origin_restore_cat_step4_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output\origin_restore;

use context_system;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
use renderer_base;
use stdClass;

/**
 * origin_restore_cat_step4_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_restore_cat_step4_page extends origin_restore_step_page {

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;
        $restoreid = required_param('restoreid', PARAM_INT);
        $siteposition = required_param('site', PARAM_RAW);
        $data = new stdClass();
        $data->button = true;
        $data->steps = [
                ['current' => false, 'num' => 1],
                ['current' => false,  'num' => 2],
                ['current' => false, 'num' => 3],
                ['current' => true, 'num' => 4],
        ];
        $tableurl = new moodle_url(self::URL);
        $backurl = new moodle_url(self::URL,
                ['step' => 3, 'site' => $this->site, 'type' => 'categories', 'restoreid' => $restoreid]
        );
        $nexturl = new moodle_url(self::URL,
            ['step' => 5, 'site' => $this->site, 'type' => 'categories']
        );
        $data->table_url = $tableurl->out(false);
        $data->back_url = $backurl->out(false);
        $data->next_url = $nexturl->out(false);
        $site = coursetransfer::get_site_by_position($this->site);
        $data->siteposition = $siteposition;
        $cats = \core_course_category::get_all();
        $destinies = [];
        foreach ($cats as $cat) {
            $des = new stdClass();
            $des->id = $cat->id;
            $name = '';
            $parents = $cat->get_parents();
            foreach ($parents as $parent) {
                $catp = \core_course_category::get($parent);
                if ($name !== '') {
                    $name .= ' > ' . $catp->name;
                } else {
                    $name .= $catp->name;
                }
            }
            if ($name !== '') {
                $name .= ' > ' . $cat->name;
            } else {
                $name .= $cat->name;
            }
            $des->name = $name;
            $des->idnumber = $cat->idnumber;
            $destinies[] = $des;
        }
        if (coursetransfer::validate_origin_site($site->host)) {
            $data->haserrors = false;
            try {
                $request = new request($site);
                $res = $request->origin_get_category_detail($restoreid, $USER);
                if ($res->success) {
                    $data->category = $res->data;
                } else {
                    $data->errors = $res->errors;
                    $data->haserrors = true;
                }
            } catch (moodle_exception $e) {
                $data->errors = ['code' => '11101', 'msg' => $e->getMessage()];
                $data->haserrors = true;
            }
        } else {
            $data->haserrors = true;
            $errors[] = ['code' => '11102',
                    'msg' => get_string('error_validate_site', 'local_coursetransfer')];
            $data->errors = $errors;
        }
        $data->button = true;
        $data->next_url_disabled = false;
        $data->siteurl = $site->host;
        $data->destinies = $destinies;
        $data->has_scheduled_time = true;
        $data->has_origin_user_data = coursetransfer::has_origin_user_data($USER);
        $data->can_remove_origin_course = coursetransfer::can_remove_origin_course($USER);
        $data->can_target_restore_merge = coursetransfer::can_target_restore_merge($USER, context_system::instance());
        $data->can_target_restore_content_remove =
                coursetransfer::can_target_restore_content_remove($USER, context_system::instance());
        $data->can_target_restore_groups_remove = coursetransfer::can_target_restore_groups_remove($USER);
        $data->can_target_restore_enrol_remove = coursetransfer::can_target_restore_enrol_remove($USER);
        $data->restore_this_course =
                $data->can_target_restore_merge || $data->can_target_restore_content_remove;
        $data->remove_in_destination =
                $data->can_target_restore_groups_remove || $data->can_target_restore_enrol_remove;
        $data->origin_course_configuration = $data->has_origin_user_data || $data->has_scheduled_time;
        return $data;
    }
}
