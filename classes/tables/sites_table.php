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
 * logs_course_response_table
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\tables;

use coding_exception;
use local_coursetransfer\output\components\actions_site_component;
use moodle_exception;
use moodle_url;
use stdClass;
use table_sql;

defined('MOODLE_INTERNAL') || die;

require_once('../../lib/tablelib.php');

/**
 * logs_course_response_table
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sites_table extends table_sql {

    /** @var int PAGE SIZE */
    const PAGE_SIZE = 100;

    /** @var string Type */
    protected $type;

    /**
     * constructor.
     *
     * @param string $type
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function __construct(string $type) {

        $this->type = $type;
        $uniqueid = time();
        parent::__construct($uniqueid);

        $this->pageable(true);
        $this->collapsible(true);
        $this->sortable(true);
        $url = '/local/coursetransfer/' . $this->type . 'sites.php';
        $moodleurl = new moodle_url($url);
        $this->define_baseurl($moodleurl);

        $this->define_columns([
            'id', 'url', 'token', 'actions',
        ]);

        $this->define_headers([
                get_string('id', 'local_coursetransfer'),
                get_string('host_url', 'local_coursetransfer'),
                get_string('host_token', 'local_coursetransfer'),
                get_string('actions', 'local_coursetransfer'),
        ]);

        $this->is_collapsible = false;
        $this->sortable(false);

        $this->column_style('id', 'text-align', 'center');
        $this->column_style('url', 'text-align', 'left');
        $this->column_style('token', 'text-align', 'center');
        $this->column_style('actions', 'text-align', 'center');
    }

    /**
     * Col request id
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_id(stdClass $row): string {
        return $row->id;
    }

    /**
     * Col URL
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_url(stdClass $row): string {
        return $row->host;
    }

    /**
     * Col Token
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_token(stdClass $row): string {
        return $row->token;
    }

    /**
     * Col Actions
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws coding_exception
     */
    public function col_actions(stdClass $row): string {
        global $PAGE;
        $output = $PAGE->get_renderer('local_coursetransfer');
        $component = new actions_site_component($this->type, $row);
        return $output->render($component);
    }

}
