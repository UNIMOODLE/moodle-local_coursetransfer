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
use local_coursetransfer\forms\new_origin_restore_course_step1_form;
use local_coursetransfer\forms\origin_restore_form;
use local_coursetransfer\forms\remove_courses_time;
use local_coursetransfer\tables\origin_restore_course_table;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * origin_remove_page_step2
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_remove_page_step2 implements renderable, templatable {

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
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {

        $data = new stdClass();
        $data->steps = [ ["current" => false, "num" => 1], ["current" => true, "num" => 2],
                ["current" => false, "num" => 3], ["current" => false, "num" => 4], ["current" => false, "num" => 5] ];

        $url = new moodle_url(
            '/local/coursetransfer/origin_restore_course.php',
            ['step' => 2 ]
        );
        $form = new remove_courses_time($url->out(false));
        $data->form = $form->render();

        $backurl = new moodle_url(
            '/local/coursetransfer/origin_remove.php',
            ['step' => 1]
        );
        $data->back_url = $backurl->out(false);
        
        return $data;
    }

}
