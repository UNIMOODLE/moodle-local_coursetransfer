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
 * new_origin_restore_category_step2_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output\origin_restore_category;

use coding_exception;
use core_course_category;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
use renderer_base;
use stdClass;

/**
 * new_origin_restore_category_step2_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class new_origin_restore_category_step2_page  extends new_origin_restore_category_step_page {

    /**
     *  constructor.
     *
     * @param core_course_category $category
     * @throws coding_exception
     */
    public function __construct(core_course_category $category) {
        parent::__construct($category);
        $this->site = required_param('site', PARAM_INT);
    }

    /**
     * Base url used to build html paging bar links.
     *
     * @return string
     */
    public function get_paging_url() : string {
        $categoryid = $this->category->id;
        return parent::URL . "?id=$categoryid&new=1&step=2&site=$this->site";
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
        $data = new stdClass();
        $data->button = true;
        $data->steps = self::get_steps(2);
        $backurl = new moodle_url(self::URL, ['id' => $this->category->id, 'new' => 1, 'step' => 1]);
        $tableurl = new moodle_url(self::URL, ['id' => $this->category->id]);
        $data->categoryid = $this->category->id;
        $nexturl = new moodle_url(self::URL,
            ['id' => $this->category->id, 'new' => 1, 'step' => 3, 'site' => $this->site,  'page' => $this->page]
        );
        $data->back_url = $backurl->out(false);
        $data->next_url = $nexturl->out(false);
        $data->table_url = $tableurl->out(false);
        $site = coursetransfer::get_site_by_position($this->site);
        try {
            $request = new request($site);
            $res = $request->origin_get_categories($USER, $this->page, $this->perpage);
            if ($res->success) {
                $data->categories = $res->data;
                $data->haserrors = false;
                $data->paging = $res->paging;
            } else {
                $data->errors = $res->errors;
                $data->haserrors = true;
            }
        } catch (moodle_exception $e) {
            $data->errors = ['code' => '21201', 'msg' => $e->getMessage()];
            $data->haserrors = true;
        }
        $data->button = true;
        $data->next_url_disabled = true;
        $data->siteurl = $site->host;
        return $data;
    }
}
