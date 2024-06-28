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
 * origin_restore_step4_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output\origin_restore;

use context_system;
use core_course_category;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
use renderer_base;
use stdClass;

/**
 * origin_restore_step4_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_restore_step4_page extends origin_restore_step_page {

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;
        $categorynamedefault = '';
        $categoryiddefault = 0;
        $data = new stdClass();
        $data->button = true;
        $data->steps = self::get_steps(4);
        $backurl = new moodle_url(self::URL,
                ['step' => 3, 'site' => $this->site, 'type' => 'courses']
        );
        $tableurl = new moodle_url(self::URL);
        $data->table_url = $tableurl->out(false);
        $data->next_url_disabled = false;
        $siteposition = required_param('site', PARAM_RAW);
        $data->siteposition = $siteposition;
        $site = coursetransfer::get_site_by_position($siteposition);
        $data->host = $site->host;

        $cids = required_param_array('courseids', PARAM_INT);
        $targetids = required_param_array('targetids', PARAM_INT);
        $categorytargets = required_param_array('categorytargets', PARAM_INT);

        $courseset = [];
        $courseids = [];
        for ($i = 0; $i < count($cids); $i++) {
            $co = new stdClass();
            $co->id = $cids[$i];
            $courseids[$co->id] = $co->id;
            $co->targetid = $targetids[$i];
            $co->categorytarget = $categorytargets[$i];
            $courseset[$co->id] = $co;
        }

        $context = \context_system::instance();
        if (has_capability('local/coursetransfer:origin_view_courses', $context)) {
            try {
                $request = new request($site);
                $res = $request->origin_get_courses_by_ids(array_keys($courseids), $USER);
                if ($res->success) {
                    $courses = $res->data;
                    $datacourses = [];
                    $categories = coursetransfer::get_categories();
                    $default = reset($categories);
                    $categorynamedefault = isset($default) ? $default->name : '';
                    $categoryiddefault = isset($default) ? $default->id : 0;
                    foreach ($categories as $cat) {
                        $ct = new stdClass();
                        $ct->id = $cat->id;
                        $ct->name = $cat->get_nested_name();
                        $cats[] = $ct;
                    }
                    foreach ($courses as $c) {
                        if ( ! isset($courseids[$c->id])) {
                            continue;
                        }
                        if ((int)$courseset[$c->id]->targetid === 0) {
                            $c->targetname = get_string('newcourse');
                            if ((int)$courseset[$c->id]->categorytarget === 0) {
                                $c->cattargetname = core_course_category::get_default()->get_formatted_name();
                            } else {
                                $targetcat = core_course_category::get($courseset[$c->id]->categorytarget);
                                $c->cattargetname = $targetcat->get_formatted_name();
                            }
                        } else {
                            $target = get_course($courseset[$c->id]->targetid);
                            $targetcat = core_course_category::get($target->category);
                            $c->targetname = $target->fullname;
                            $c->cattargetname = $targetcat->get_formatted_name();
                        }
                        $datacourses[] = $c;
                    }
                    $data->courses = $datacourses;
                    $data->haserrors = false;
                } else {
                    $data->errors = $res->errors;
                    $data->haserrors = true;
                }
            } catch (moodle_exception $e) {
                $data->errors = ['code' => '20004', 'msg' => $e->getMessage()];
                $data->haserrors = true;
            }
        } else {
            $data->errors = [
                    'code' => '20003',
                    'msg' => get_string('you_have_not_permission', 'local_coursetransfer')];
            $data->haserrors = true;
        }
        $data->categorynamedefault = $categorynamedefault;
        $data->categoryiddefault = $categoryiddefault;
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
        $data->remove_in_target =
                $data->can_target_restore_groups_remove || $data->can_target_restore_enrol_remove;
        $data->origin_course_configuration = $data->has_origin_user_data || $data->has_scheduled_time;

        return $data;
    }
}
