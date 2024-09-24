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
 * origin_remove_page_cat_step2
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output\origin_remove;

use coding_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
use renderer_base;
use stdClass;

/**
 * origin_remove_page_cat_step2
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_remove_page_cat_step2  extends origin_remove_page_base {

    /**
     * Base url used to build html paging bar links.
     *
     * @return string
     */
    public function get_paging_url() : string {
        return parent::URL . '?step=2&type=courses&site=' . $this->site;
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
        $data->steps = self::get_steps(2);
        $backurl = new moodle_url(self::URL);
        $nexturl = new moodle_url(self::URL,
            ['step' => 3, 'site' => $this->site, 'type' => 'categories', 'page' => $this->page]
        );
        $tableurl = new moodle_url(self::URL);
        $data->table_url = $tableurl->out(false);
        $data->back_url = $backurl->out(false);
        $data->next_url = $nexturl->out(false);
        $paging = new stdClass();
        $paging->totalcount = 0;
        $paging->page = 0;
        $paging->perpage = 0;
        $data->paging = $paging;
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
            $data->errors = ['code' => '31001', 'msg' => $e->getMessage()];
            $data->haserrors = true;
        }
        $data->next_url_disabled = true;
        $data->button = true;

        return $data;
    }

}
