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
use dml_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * category_course_component
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_course_component implements renderable, templatable {

    /** @var int ID */
    protected $id;

    /** @var string Courses Requests JSON */
    protected $requests;

    /** @var string Origin Site URL */
    protected $siteurl;

    /**
     *  constructor.
     *
     * @param string $requests
     * @param string $siteurl
     * @param int $id
     */
    public function __construct(string $requests, string $siteurl, int $id) {
        $this->id = $id;
        $this->requests = $requests;
        $this->siteurl = $siteurl;
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $category = new stdClass();
        $category->courses = $this->get_courses_data();
        $data = new stdClass();
        $data->id = $this->id;
        $data->category = $category;
        $data->has_status = true;
        return $data;
    }

    /**
     * Get Courses Data.
     *
     * @return array
     * @throws dml_exception
     * @throws coding_exception
     */
    protected function get_courses_data(): array {
        $cs = [];
        $requests = json_decode($this->requests);
        foreach ($requests as $reqid) {
            $request = coursetransfer_request::get($reqid);
            $c = new stdClass();
            $c->id = $request->origin_course_id;
            $c->fullname = $request->origin_course_fullname;
            $c->shortname = $request->origin_course_shortname;
            $c->idnumber = $request->origin_course_idnumber;
            $c->categoryid = $request->origin_category_id;
            $c->categoryname = $request->origin_category_name;
            $c->has_status = true;
            $status = (int)$request->status;
            if (!empty($status)) {
                if (isset(coursetransfer::STATUS[$status])) {
                    $c->status = get_string(
                            'status_' . coursetransfer::STATUS[$status]['shortname'],
                            'local_coursetransfer');
                }
            }
            $c->checked = true;
            $c->disabled = true;
            $cs[] = $c;
        }
        return $cs;
    }
}
