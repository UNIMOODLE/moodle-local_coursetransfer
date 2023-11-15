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

namespace local_coursetransfer\output\logs;

use coding_exception;
use core\progress\display;
use dml_exception;
use local_coursetransfer\coursetransfer;
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

    public const STATUS = [
        0 => 'Error',
        1 => 'Not started',
        10 => 'In progress',
        30 => 'Alert',
        50 => 'Download',
        70 => 'Downloaded',
        80 => 'Restore',
        90 => 'Incompleted',
        100 => 'Completed',
    ];

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
     * @throws coding_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB;

        $data = new stdClass();
        $record = coursetransfer_request::get($this->id);
        $back = new moodle_url(logs_page::PAGE);
        $data->back = $back->out(false);
        $data->id = $this->id;
        $data->type = $this->get_type($record->type);
        $data->siteurl = $record->siteurl;
        $data->direction = $this->get_direction($record->direction);
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
        $data->origin_enrolusers = $this->get_bool($record->origin_enrolusers);
        $data->origin_remove_course = $this->get_bool($record->origin_remove_course);
        $data->origin_remove_category = $this->get_bool($record->origin_remove_category);
        if (isset($data->origin_schedule_datetime)) {
            $data->origin_schedule_datetime = date("Y-m-d h:i:s", $record->origin_schedule_datetime);
        }
        $data->origin_remove_activities = $record->origin_remove_activities;
        $data->origin_activities = json_decode($record->origin_activities);
        $data->has_sections = count($data->origin_activities) > 0;
        if (isset($data->origin_activities)) {
            for ($i = 0; $i < count($data->origin_activities); $i++) {
                $data->origin_activities[$i]->hasactivities = count($data->origin_activities[$i]->activities);
            }
        }
        $data->origin_category_requests = $record->origin_category_requests;
        $data->origin_backup_size = display_size($record->origin_backup_size, 3, 'MB');
        if (isset($data->origin_backup_size_estimated)) {
            $data->origin_backup_size_estimated = display_size($data->origin_backup_size_estimated, 3, 'MB');
        }
        $data->origin_backup_url = $record->origin_backup_url;
        $data->destiny_course_id = $record->destiny_course_id;
        $data->destiny_category_id = $record->destiny_category_id;
        $data->destiny_remove_enrols = $this->get_bool($record->destiny_remove_enrols);
        $data->destiny_remove_groups = $this->get_bool($record->destiny_remove_groups);
        $data->destiny_target = $this->get_target($record->destiny_target);
        $data->error_code = $record->error_code;
        $data->error_message = $record->error_message;
        $data->fileurl = $record->fileurl;
        $user = $DB->get_record('user', ['id' => $record->userid]);
        $data->username = $user->username;

        $data->status = self::STATUS[$record->status];
        $data->timemodified = date("Y-m-d h:i:s", $record->timemodified);
        return $data;
    }

    /**
     * Get Type.
     *
     * @param int $type
     * @return string
     */
    protected function get_type(int $type): string {
        switch ($type) {
            case coursetransfer_request::TYPE_COURSE:
                return 'Restauración Curso';
            case coursetransfer_request::TYPE_CATEGORY:
                return 'Restauración Categoría';
            case coursetransfer_request::TYPE_REMOVE_COURSE:
                return 'Borrado Curso';
            case coursetransfer_request::TYPE_REMOVE_CATEGORY:
                return 'Borrado Categoría';
            default:
                return 'Tipo erroneo';
        }
    }

    /**
     * Get Direction.
     *
     * @param int $direction
     * @return string
     */
    protected function get_direction(int $direction): string {
        switch ($direction) {
            case coursetransfer_request::DIRECTION_REQUEST:
                return 'Petición';
            case coursetransfer_request::DIRECTION_RESPONSE:
                return 'Respuesta';
            default:
                return 'Dirección erronea';
        }
    }

    /**
     * Get Target.
     *
     * @param int $direction
     * @return string
     */
    protected function get_target(int $direction): string {
        switch ($direction) {
            case 2:
                return 'En Nuevo Curso';
            case 3:
                return 'Borrar contenido de destino';
            default:
                return 'Fusionar contenido en destino';
        }
    }

    /**
     * Get Bool.
     *
     * @param int $data
     * @return string
     * @throws coding_exception
     */
    protected function get_bool(int $data): string {
        return $data === 1 ? get_string('yes') : get_string('no');
    }
}
