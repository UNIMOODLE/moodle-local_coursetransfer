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

namespace local_coursetransfer\output;

use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
use renderer_base;
use stdClass;

/**
 * new_origin_restore_course_step4_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class new_origin_restore_course_step4_page extends new_origin_restore_course_step_page {

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;
        $siteposition = required_param('site', PARAM_INT);
        $restoreid = required_param('restoreid', PARAM_INT);
        $backurl = new moodle_url(
            '/local/coursetransfer/origin_restore_course.php',
            ['id' => $this->course->id, 'new' => 1, 'step' => 3, 'site' => $siteposition, 'restoreid' => $restoreid]
        );
        $nexturl = new moodle_url(
            '/local/coursetransfer/origin_restore_course.php',
            ['id' => $this->course->id, 'new' => 1, 'step' => 5, 'site' => $siteposition, 'restoreid' => $restoreid]
        );
        $tableurl = new moodle_url(
            '/local/coursetransfer/origin_restore_course.php',
            ['id' => $this->course->id]
        );
        $data = new stdClass();
        $data->restoreid = $restoreid;
        $data->button = false;
        $data->steps = [ ["current" => false, "num" => 1], ["current" => false, "num" => 2],
            ["current" => false, "num" => 3], ["current" => true, "num" => 4], ["current" => false, "num" => 5] ];
        $data->back_url = $backurl->out(false);
        $data->next_url = $nexturl->out(false);
        $data->table_url = $tableurl->out(false);
        $site = coursetransfer::get_site_by_position($siteposition);

        if (coursetransfer::validate_origin_site($site->host)) {
            $data->haserrors = false;
            $request = new request($site);
            $res = $request->origin_get_course_detail($restoreid, $USER);
            if ($res->success) {
                $data->course = $res->data;
            } else {
                $data->errors = $res->errors;
                $data->haserrors = true;
            }
        } else {
            $data->haserrors = true;
            $errors[] = ['code' => '200150', 'msg' => get_string('error_validate_site', 'local_coursetransfer')];
            $data->errors = $errors;
        }
        $data->next_url_disabled = false;
        $data->course->sessionStorage_id = "local_coursetransfer_".$this->course->id."_".$data->course->id;
        return $data;
    }

}

