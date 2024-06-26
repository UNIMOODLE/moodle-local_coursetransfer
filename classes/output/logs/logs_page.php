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
 * logs_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output\logs;

use coding_exception;
use local_coursetransfer\coursetransfer_request;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use table_sql;
use templatable;

/**
 * logs_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logs_page implements renderable, templatable {

    /** @var string Page */
    const PAGE = '/local/coursetransfer/logs.php';

    /** @var int Type  */
    protected $type;

    /** @var int Direction */
    protected $direction;

    /** @var table_sql Table */
    protected $table;

    /** @var moodle_url URL */
    protected $url;

    /** @var string[] URL */
    protected $selects;

    /**
     *  constructor.
     *
     * @throws coding_exception
     */
    public function __construct() {
        $this->type = optional_param('type', coursetransfer_request::TYPE_COURSE, PARAM_INT);
        $this->direction = optional_param('direction', coursetransfer_request::DIRECTION_REQUEST, PARAM_INT);
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
        $formurl = new moodle_url(self::PAGE);
        $restoreurl = new moodle_url('/local/coursetransfer/origin_restore.php');
        $data = new stdClass();
        $data->table = $this->get_logs_table();
        $data->title = $this->get_title();
        $data->selects = $this->selects;
        $data->form_url = $formurl->out(false);
        $data->restore_url = $restoreurl->out(false);
        return $data;
    }

    /**
     * Get logs Table.
     *
     * @return string
     */
    protected function get_logs_table(): string {
        $table = $this->table;
        $table->is_downloadable(false);
        $table->pageable(false);
        $select = 'csr.*';
        $from = '{local_coursetransfer_request} csr';
        $where = 'direction = :direction AND type = :type';
        $params = [
                'direction' => $this->direction,
                'type' => $this->type,
        ];
        $table->set_sql($select, $from, $where, $params);
        $table->sortable(false, 'id', SORT_DESC);
        $table->collapsible(false);
        $table->define_baseurl($this->url);
        ob_start();
        $table->out(10, true, false);
        $tablecontent = ob_get_contents();
        ob_end_clean();
        return $tablecontent;
    }

    /**
     * Get Title.
     *
     * @return string
     * @throws coding_exception
     */
    protected function get_title(): string {
        switch ($this->type) {
            case coursetransfer_request::TYPE_CATEGORY:
                $title = get_string('restore_category', 'local_coursetransfer');
                break;
            case coursetransfer_request::TYPE_REMOVE_COURSE:
                $title = get_string('remove_course', 'local_coursetransfer');
                break;
            case coursetransfer_request::TYPE_REMOVE_CATEGORY:
                $title = get_string('remove_category', 'local_coursetransfer');
                break;
            default:
                $title = get_string('restore_course', 'local_coursetransfer');
        }
        if ($this->direction === coursetransfer_request::DIRECTION_REQUEST) {
            $title .= ' - ' . get_string('request', 'local_coursetransfer');
        } else {
            $title .= ' - ' . get_string('response', 'local_coursetransfer');
        }
        return $title;
    }

}
