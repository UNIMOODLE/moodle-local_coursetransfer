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

use dml_exception;
use local_coursetransfer\coursetransfer_request;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * log_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_page implements renderable, templatable {

    /** @var int Id  */
    protected $id;

    /**
     *  constructor.
     *
     */
    public function __construct(int $id) {
        $this->id = $id;
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws dml_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $record = coursetransfer_request::get($this->id);
        $back = new moodle_url('/local/coursetransfer/logs.php');
        $data->back = $back->out(false);
        $data->id = $this->id;
        $data->type = $record->type;
        $data->siteurl = $record->siteurl;
        $data->direction = $record->direction;
        $data->destiny_request_id = $record->destiny_request_id;
        $data->request_category_id = $record->request_category_id;
        $data->origin_course_id = $record->origin_course_id;
        $data->destiny_request_id = $record->destiny_request_id;
        $data->request_category_id = $record->request_category_id;
        $data->origin_course_id = $record->origin_course_id;
        $data->origin_course_fullname = $record->origin_course_fullname;
        $data->origin_course_shortname = $record->origin_course_shortname;
        $data->origin_course_idnumber = $record->origin_course_idnumber;
        $data->origin_category_id = $record->origin_category_id;
        $data->origin_category_idnumber = $record->origin_category_idnumber;
        $data->origin_category_name = $record->origin_category_name;
        $data->origin_enrolusers = $record->origin_enrolusers;
        $data->origin_remove_course = $record->origin_remove_course;
        $data->origin_remove_category = $record->origin_remove_category;
        $data->origin_schedule_datetime = $record->origin_schedule_datetime;
        $data->origin_remove_activities = $record->origin_remove_activities;
        $data->origin_activities = $record->origin_activities;
        $data->origin_category_requests = $record->origin_category_requests;
        $data->origin_backup_size = $record->origin_backup_size;
        $data->origin_backup_size_estimated = $record->origin_backup_size_estimated;
        $data->origin_backup_url = $record->origin_backup_url;
        $data->destiny_course_id = $record->destiny_course_id;
        $data->destiny_category_id = $record->destiny_category_id;
        $data->destiny_remove_enrols = $record->destiny_remove_enrols;
        $data->destiny_remove_groups = $record->destiny_remove_groups;
        $data->destiny_target = $record->destiny_target;
        $data->error_code = $record->error_code;
        $data->error_message = $record->error_message;
        $data->fileurl = $record->fileurl;
        $data->userid = $record->userid;
        $data->status = $record->status;
        $data->timemodified = $record->timemodified;
        return $data;
    }
}
