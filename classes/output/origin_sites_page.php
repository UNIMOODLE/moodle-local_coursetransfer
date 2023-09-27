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
use local_coursetransfer\tables\destiny_sites_table;
use local_coursetransfer\tables\origin_sites_table;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * origin_sites_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_sites_page implements renderable, templatable {

    /**
     *  constructor.
     *
     */
    public function __construct() {
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
        return $data;
    }

    /**
     * Get logs Table.
     *
     * @return string
     */

    /**
     * Get Table.
     *
     * @return string
     */
    protected function get_table(): string {
        $table = new origin_sites_table();
        $table->is_downloadable(false);
        $table->pageable(false);
        $select = 'cto.*';
        $from = '{local_coursetransfer_origin} cto';
        $where = '1 = 1';
        $table->set_sql($select, $from, $where);
        $table->sortable(false, 'id', SORT_DESC);
        $table->collapsible(false);
        $url = new moodle_url('/local/coursetransfer/originsites.php');
        $table->define_baseurl($url);
        ob_start();
        $table->out(200, true, false);
        $tablecontent = ob_get_contents();
        ob_end_clean();
        return $tablecontent;
    }

}
