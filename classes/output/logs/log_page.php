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
use dml_exception;
use local_coursetransfer\coursetransfer_request;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

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
        $data->origin_enrolusers = is_null($record->origin_enrolusers) ? '-' : $this->get_bool($record->origin_enrolusers);
        $data->origin_remove_course = is_null($record->origin_remove_course) ? '-' : $this->get_bool($record->origin_remove_course);
        $data->origin_remove_category =
                is_null($record->origin_remove_category) ? '-' : $this->get_bool($record->origin_remove_category);
        $data->origin_enrolusers = is_null($record->origin_enrolusers) ? '-' : $this->get_bool($record->origin_enrolusers);
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

        $data->destiny_remove_enrols =
                is_null($record->destiny_remove_enrols) ? '-' : $this->get_bool($record->destiny_remove_enrols);
        $data->destiny_remove_groups =
                is_null($record->destiny_remove_groups) ? '-' : $this->get_bool($record->destiny_remove_groups);
        $data->origin_enrolusers = is_null($record->origin_enrolusers) ? '-' : $this->get_bool($record->origin_enrolusers);

        $data->destiny_target = $this->get_target($record->destiny_target);
        $data->error_code = $record->error_code;
        $data->error_message = $record->error_message;
        $data->fileurl = $record->fileurl;
        $user = $DB->get_record('user', ['id' => $record->userid]);
        $data->username = $user->username;

        $data->status = $this->get_status($record->status);
        $data->timemodified = date("Y-m-d h:i:s", $record->timemodified);
        return $data;
    }

    /**
     * Get Type.
     *
     * @param int $type
     * @return string
     * @throws coding_exception
     */
    protected function get_type(int $type): string {
        switch ($type) {
            case coursetransfer_request::TYPE_COURSE:
                return get_string('restore_course', 'local_coursetransfer');
            case coursetransfer_request::TYPE_CATEGORY:
                return get_string('restore_category', 'local_coursetransfer');
            case coursetransfer_request::TYPE_REMOVE_COURSE:
                return get_string('remove_course', 'local_coursetransfer');
            case coursetransfer_request::TYPE_REMOVE_CATEGORY:
                return get_string('remove_category', 'local_coursetransfer');
            default:
                return '-';
        }
    }

    /**
     * Get Direction.
     *
     * @param int $direction
     * @return string
     * @throws coding_exception
     */
    protected function get_direction(int $direction): string {
        switch ($direction) {
            case coursetransfer_request::DIRECTION_REQUEST:
                return get_string('request', 'local_coursetransfer');
            case coursetransfer_request::DIRECTION_RESPONSE:
                return get_string('response', 'local_coursetransfer');
            default:
                return '-';
        }
    }

    /**
     * Get Target.
     *
     * @param int $direction
     * @return string
     * @throws coding_exception
     */
    protected function get_target(int $direction): string {
        switch ($direction) {
            case \backup::TARGET_NEW_COURSE:
                return get_string('in_new_course', 'local_coursetransfer');
            case \backup::TARGET_EXISTING_DELETING:
                return get_string('remove_content', 'local_coursetransfer');
            case \backup::TARGET_EXISTING_ADDING:
                return get_string('merge_content', 'local_coursetransfer');
            default:
                return '-';
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

    /**
     * Get Status.
     *
     * @param int $data
     * @return string
     * @throws coding_exception
     */
    protected function get_status(int $data): string {
        switch ($data) {
            case coursetransfer_request::STATUS_ERROR:
                return get_string('status_error', 'local_coursetransfer');
            case coursetransfer_request::STATUS_NOT_STARTED:
                return get_string('status_not_started', 'local_coursetransfer');
            case coursetransfer_request::STATUS_IN_PROGRESS:
                return get_string('status_in_progress', 'local_coursetransfer');
            case coursetransfer_request::STATUS_BACKUP:
                return get_string('status_in_backup', 'local_coursetransfer');
            case coursetransfer_request::STATUS_INCOMPLETED:
                return get_string('status_incompleted', 'local_coursetransfer');
            case coursetransfer_request::STATUS_DOWNLOAD:
                return get_string('status_download', 'local_coursetransfer');
            case coursetransfer_request::STATUS_DOWNLOADED:
                return get_string('status_downloaded', 'local_coursetransfer');
            case coursetransfer_request::STATUS_RESTORE:
                return get_string('status_restore', 'local_coursetransfer');
            case coursetransfer_request::STATUS_COMPLETED:
                return get_string('status_completed', 'local_coursetransfer');
            default:
                return '-';
        }
    }
}
