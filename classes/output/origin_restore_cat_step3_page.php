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

use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
use renderer_base;
use stdClass;

/**
 * origin_restore_cat_step3_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_restore_cat_step3_page extends origin_restore_step_page {

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->button = true;
        $data->steps = [
                ['current' => false, 'num' => 1],
                ['current' => false,  'num' => 2],
                ['current' => true, 'num' => 3],
                ['current' => false, 'num' => 4]
        ];
        $tableurl = new moodle_url(self::URL);
        $backurl = new moodle_url(self::URL,
                ['step' => 2, 'site' => $this->site, 'type' => 'categories']
        );
        $nexturl = new moodle_url(self::URL,
            ['step' => 4, 'site' => $this->site, 'type' => 'categories']
        );
        $data->table_url = $tableurl->out(false);
        $data->back_url = $backurl->out(false);
        $data->next_url = $nexturl->out(false);
        $site = coursetransfer::get_site_by_position($this->site);

        $data->host = $site->host;

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
        $restoreid = required_param('restoreid', PARAM_INT);

        if (coursetransfer::validate_origin_site($site->host)) {
            $data->haserrors = false;
            try {
                $request = new request($site);
                $res = $request->origin_get_category_detail($restoreid);
                if ($res->success) {
                    $data->category = $res->data;
                } else {
                    $data->errors = $res->errors;
                    $data->haserrors = true;
                }
            } catch (moodle_exception $e) {
                $data->errors = ['code' => '200110', 'msg' => $e->getMessage()];
                $data->haserrors = true;
            }
        } else {
            $data->haserrors = true;
            $errors[] = [
                    'code' => '200111',
                    'msg' => get_string('error_validate_site', 'local_coursetransfer')
            ];
            $data->errors = $errors;
        }
        $data->button = true;
        $data->next_url_disabled = false;
        $data->siteurl = $site->host;
        $data->destinies = $destinies;
        return $data;
    }
}
