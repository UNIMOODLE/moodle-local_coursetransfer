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
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\forms\new_origin_restore_course_step1_form;
use local_coursetransfer\forms\origin_remove_form;
use local_coursetransfer\forms\origin_restore_form;
use local_coursetransfer\tables\logs_table;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * logs_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logs_page implements renderable, templatable {

    /** @var int Type  */
    protected $type;
    /** @var int Direction */
    protected $direction;

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
        $data = new stdClass();
        $data->table = $this->get_logs_table();
        $data->title = $this->get_title();
        return $data;
    }

    /**
     * Get restore request table
     *
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function get_logs_table(): string {
        $uniqid = uniqid('', true);
        $table = new logs_table($uniqid);
        $table->is_downloadable(false);
        $table->pageable(true);
        $select = 'csr.*';
        $from = '{local_coursetransfer_request} csr';
        $where = 'direction = :direction AND type = :type';
        $params = [
                'direction' => $this->direction,
                'type' => $this->type
        ];
        $table->set_sql($select, $from, $where, $params);
        $table->sortable(true, 'timemodified', SORT_DESC);
        $table->collapsible(false);
        $table->define_baseurl(
                new moodle_url('/local/coursetransfer/logs_page.php')
        );
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
                return get_string('origin_restore_category', 'local_coursetransfer');
            case coursetransfer_request::TYPE_REMOVE_COURSE:
                return get_string('remove_course_page', 'local_coursetransfer');
            case coursetransfer_request::TYPE_REMOVE_CATEGORY:
                return get_string('remove_category_page', 'local_coursetransfer');
            default:
                return get_string('origin_restore_course', 'local_coursetransfer');
        }
    }

}
