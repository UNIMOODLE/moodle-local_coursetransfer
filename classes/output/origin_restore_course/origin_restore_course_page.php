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
 * origin_restore_course_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output\origin_restore_course;

use coding_exception;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\tables\origin_restore_course_table;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * origin_restore_course_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_restore_course_page implements renderable, templatable {

    /** @var string URL */
    const URL = '/local/coursetransfer/origin_restore_course.php';

    /** @var stdClass Course */
    protected $course;

    /**
     *  constructor.
     *
     * @param stdClass $course
     */
    public function __construct(stdClass $course) {
        $this->course = $course;
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
        $newurl = new moodle_url(self::URL,
            ['id' => $this->course->id, 'new' => 1, 'step' => 1]
        );
        $data = new stdClass();
        $data->new_url = $newurl->out(false);
        $data->table = $this->get_restore_table();
        return $data;
    }

    /**
     * Get restore request table
     *
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function get_restore_table(): string {
        $uniqid = uniqid('', true);
        $table = new origin_restore_course_table($uniqid, $this->course);
        $table->is_downloadable(false);
        $table->pageable(false);
        $select = 'csr.*';
        $from = '{local_coursetransfer_request} csr';
        $where = 'target_course_id = :courseid AND direction = :direction AND type = :type';
        $params = [
                'courseid' => $this->course->id,
                'direction' => coursetransfer_request::DIRECTION_REQUEST,
                'type' => coursetransfer_request::TYPE_COURSE,
        ];
        $table->set_sql($select, $from, $where, $params);
        $table->sortable(true, 'timemodified', SORT_DESC);
        $table->collapsible(false);
        $table->define_baseurl(
            new moodle_url(self::URL, ['id' => $this->course->id])
        );

        ob_start();
        $table->out(200, true, false);
        $tablecontent = ob_get_contents();
        ob_end_clean();
        return $tablecontent;
    }

}
