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

use coding_exception;
use dml_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * category_course_component
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
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
     * @throws coding_exception
     * @throws dml_exception
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
     * @throws moodle_exception
     */
    protected function get_courses_data(): array {
        $cs = [];
        $requests = json_decode($this->requests);
        foreach ($requests as $reqid) {
            $request = coursetransfer_request::get($reqid);
            $c = new stdClass();
            $c->origin_id = $request->origin_course_id;
            $c->destiny_course_id = $request->destiny_course_id;
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
            $logurl = new moodle_url('/local/coursetransfer/origin_restore_course.php',
                    ['id' => $request->destiny_course_id]);
            $urldes = new moodle_url('/course/view.php',
                    ['id' => $request->destiny_course_id]);
            $c->log_url = $logurl->out(false);
            $c->destiny_url = $urldes->out(false);
            $cs[] = $c;
        }
        return $cs;
    }
}
