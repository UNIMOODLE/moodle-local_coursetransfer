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

use local_coursetransfer\forms\new_origin_restore_course_step1_form;
use moodle_exception;
use moodle_url;
use renderer_base;
use stdClass;

/**
 * new_origin_restore_category_step1_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class new_origin_restore_category_step1_page  extends new_origin_restore_category_step_page {

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $backurl = new moodle_url('/local/coursetransfer/origin_restore_category.php', ['id' => $this->category->id]);
        $tableurl = new moodle_url('/local/coursetransfer/origin_restore_category.php', ['id' => $this->category->id]);

        $data = new stdClass();
        $data->button = true;
        $data->steps = [ ["current" => true, "num" => 1], ["current" => false, "num" => 2],
            ["current" => false, "num" => 3], ["current" => false, "num" => 4], ["current" => false, "num" => 5] ];
        $data->back_url = $backurl->out(false);
        $data->table_url = $tableurl->out(false);

        $url = new moodle_url(
            '/local/coursetransfer/origin_restore_category.php',
            ['id' => $this->category->id, 'new' => 1, 'step' => 2 ]
        );
        //$form = new new_origin_restore_course_step1_form($this->category, $url->out(false));
        //$data->form = $form->render();
        $data->next_url_disabled = false;
        return $data;
    }
}
