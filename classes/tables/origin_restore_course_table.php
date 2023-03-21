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
 * Class origin_restore_course_table
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\tables;

use coding_exception;
use moodle_exception;
use moodle_url;
use stdClass;
use table_sql;

defined('MOODLE_INTERNAL') || die;

require_once('../../lib/tablelib.php');

/**
 * Class origin_restore_course_table
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_restore_course_table extends table_sql {

    /** @var int PAGE SIZE */
    const PAGE_SIZE = 20;

    /** @var stdClass Course */
    protected $course;

    /**
     * constructor.
     *
     * @param stdClass $course
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function __construct(stdClass $course) {

        $uniqueid = time();
        parent::__construct($uniqueid);

        $this->course = $course;

        $this->pageable(true);
        $this->collapsible(true);
        $this->sortable(true);
        $url = '/local/coursetransfer/origin_restore_course.php';
        $paramsurl = ['id' => $this->course->id];
        $moodleurl = new moodle_url($url, $paramsurl);
        $this->define_baseurl($moodleurl);

        $this->define_columns([
            'id', 'userid', 'timemodified'
        ]);
        $this->define_headers([
                get_string('request_id', 'local_coursetransfer'),
                get_string('table_userid', 'local_coursetransfer'),
                get_string('table_timemodified', 'local_coursetransfer'),
        ]);

        $this->is_collapsible = false;
        $this->sortable(false);

        $this->column_style('id', 'text-align', 'center');
        $this->column_style('userid', 'text-align', 'left');
        $this->column_style('timemodified', 'text-align', 'center');
    }

    /**
     * Query DB.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     * @throws moodle_exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        $this->rawdata = $this->get_data();
    }

    /**
     * Get Data
     *
     * @return array
     * @throws moodle_exception
     */
    public function get_data(): array {

        $data = [];
        $total = [];
        $data = $this->data_sort_columns($data);
        $this->pagesize(self::PAGE_SIZE, count($total));
        return $data;
    }

    /**
     * Data Sort Columns.
     *
     * @param $data
     * @return mixed
     * @throws coding_exception
     */
    protected function data_sort_columns($data) {
        $columns = array_reverse($this->get_sort_columns());
        foreach ($columns as $k => $v) {
            usort($data, function($a, $b) use ($k, $v){
                if (isset($a->{$k})) {
                    if ($v === 3) {
                        return $a->{$k} < $b->{$k} ? 1 : -1;
                    } else {
                        return $a->{$k} < $b->{$k} ? -1 : 1;
                    }
                } else {
                    return true;
                }
            });
        }
        return $data;
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
     * Col User id
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_userid(stdClass $row): string {
        return $row->userid;
    }

    /**
     * Col TimeModified
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_timemodified(stdClass $row): string {
        return $row->timemodified;
    }

}
