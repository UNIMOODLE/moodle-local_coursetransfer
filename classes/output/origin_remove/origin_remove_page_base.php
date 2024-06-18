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
 * origin_remove_page_base
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output\origin_remove;

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
 * origin_remove_page_base
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_remove_page_base implements renderable, templatable {

    /** @var string URL */
    const URL = '/local/coursetransfer/origin_remove.php';

    /** @var int Site */
    protected $site;

    /**
     * Page of data requested.
     *
     * @var int
     */
    protected $page;

    /**
     * Number of items to show on each page.
     *
     * @var int
     */
    protected $perpage;

    /** @var string Search */
    protected $search;

    /**
     *  constructor.
     *
     * @throws coding_exception
     */
    public function __construct() {
        global $CFG;
        $this->site = required_param('site', PARAM_INT);
        $this->page = optional_param('page', 0, PARAM_INT);
        $this->perpage = optional_param('perpage', $CFG->coursesperpage, PARAM_INT);
        $this->search = optional_param('search', '', PARAM_TEXT);
    }


    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        return new stdClass();
    }

    /**
     * Get Steps.
     *
     * @param int $current
     * @return array|array[]
     */
    public static function get_steps(int $current): array {
        $steps = [];
        for ($i = 1; $i <= 3; $i++) {
            $step = ['current' => $current === $i, 'num' => $i];
            $steps[] = $step;
        }
        return $steps;
    }
}
