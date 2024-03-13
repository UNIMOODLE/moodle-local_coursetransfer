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
 * sites_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use coding_exception;
use local_coursetransfer\tables\sites_table;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * sites_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sites_page implements renderable, templatable {

    /** @var string Type */
    protected $type;

    /**
     *  constructor.
     *
     * @param string $type
     */
    public function __construct(string $type) {
        $this->type = $type;
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->table = $this->get_table();
        $back = new moodle_url('/admin/settings.php', ['section' => 'local_coursetransfer']);
        $data->back = $back->out(false);
        $summary = new moodle_url('/local/coursetransfer/index.php');
        $data->summary = $summary->out(false);
        $data->type = $this->type;
        $data->desc = get_string('setting_' . $this->type . '_sites_desc', 'local_coursetransfer');
        return $data;
    }

    /**
     * Get Table.
     *
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function get_table(): string {
        $table = new sites_table($this->type);
        $table->is_downloadable(false);
        $table->pageable(false);
        $select = 'cto.*';
        $from = '{local_coursetransfer_'. $this->type . '} cto';
        $where = '1 = 1';
        $table->set_sql($select, $from, $where);
        $table->sortable(false, 'id', SORT_DESC);
        $table->collapsible(false);
        $url = new moodle_url('/local/coursetransfer/'. $this->type . 'sites.php');
        $table->define_baseurl($url);
        ob_start();
        $table->out(200, true, false);
        $tablecontent = ob_get_contents();
        ob_end_clean();
        return $tablecontent;
    }

}
