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

use coding_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * origin_remove_page_step3
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_remove_page_step3 implements renderable, templatable {

    /** @var int Site */
    protected $site;

    /**
     *  constructor.
     *
     * @throws coding_exception
     */
    public function __construct() {
        $this->site = required_param('site', PARAM_INT);
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {

        $data = new stdClass();
        $data->steps = [
                ['current' => false, 'num' => 1],
                ['current' => false, 'num' => 2],
                ['current' => true, 'num' => 3]
        ];
        $backurl = new moodle_url(
                '/local/coursetransfer/origin_remove.php',
                ['step' => 2, 'site' => $this->site, 'type' => 'courses']
        );
        $tableurl = new moodle_url(
                '/local/coursetransfer/origin_remove.php'
        );
        $data->table_url = $tableurl->out(false);
        $data->back_url = $backurl->out(false);
        $site = coursetransfer::get_site_by_position($this->site);
        $data->siteposition = $this->site;
        $data->host = $site->host;
        try {
            $request = new request($site);
            $res = $request->origin_get_courses();
            if ($res->success) {
                $courses = $res->data;
                $datacourses = [];
                $coursesdest = get_courses();
                $destinies = [];
                foreach ($coursesdest as $cd) {
                    $destinies[] = [
                            'id' => $cd->id,
                            'name' => $cd->fullname,
                            'shortname' => $cd->shortname
                    ];
                }
                foreach ($courses as $c) {
                    $c->destinies = $destinies;
                    $datacourses[] = $c;
                }
                $data->courses = $datacourses;
                $data->haserrors = false;
            } else {
                $data->errors = $res->errors;
                $data->haserrors = true;
            }
        } catch (moodle_exception $e) {
            $data->errors = ['code' => '205001', 'msg' => $e->getMessage()];
            $data->haserrors = true;
        }
        $data->next_url_disabled = true;
        $data->button = true;

        return $data;
    }

}
