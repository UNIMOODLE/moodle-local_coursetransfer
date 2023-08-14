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
use local_coursetransfer\forms\origin_remove_form;
use local_coursetransfer\forms\origin_restore_form;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * origin_remove_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_remove_page implements renderable, templatable {

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
     */
    public function export_for_template(renderer_base $output): stdClass {
        $url = new moodle_url(
                '/local/coursetransfer/origin_remove.php?step=1',
        );
        $form = new origin_remove_form($url->out(false));

        $data = new stdClass();
        $data->steps = [
                ['current' => true, 'num' => 1],
                ['current' => false, 'num' => 2],
                ['current' => false, 'num' => 3]
        ];
        $data->button = true;
        $data->next_url = $url->out(false);
        $data->next_url_disabled = false;
        $data->form = $form->render();

        return $data;
    }

}
