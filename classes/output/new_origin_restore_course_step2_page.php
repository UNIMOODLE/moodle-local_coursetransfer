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
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\forms\new_origin_restore_course_step1_form;
use local_coursetransfer\forms\new_origin_restore_course_step2_form;
use local_coursetransfer\tables\origin_courses_table;
use local_coursetransfer\tables\origin_restore_course_table;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * new_origin_restore_course_step2_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class new_origin_restore_course_step2_page implements renderable, templatable
{

    /** @var stdClass Course */
    protected $course;

    /**
     *  constructor.
     *
     * @param stdClass $course
     */
    public function __construct(stdClass $course)
    {
        $this->course = $course;
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass
    {
        $backurl = new moodle_url(
            '/local/coursetransfer/origin_restore_course.php',
            ['id' => $this->course->id, 'new' => 1, 'step' => 1]
        );
        $nexturl = new moodle_url(
            '/local/coursetransfer/origin_restore_course.php',
            ['id' => $this->course->id, 'new' => 1, 'step' => 3]
        );

        $data = new stdClass();
        $data->siteurl = required_param('site', PARAM_RAW);
        $data->steps = [ ["current" => false, "num" => 1], ["current" => true, "num" => 2],
            ["current" => false, "num" => 3], ["current" => false, "num" => 4], ["current" => false, "num" => 5] ];
        $data->back_url = $backurl->out(false);
        $data->next_url = $nexturl->out(false);

        $request = new request($data->siteurl);
        $res = $request->origin_get_courses();
        if ($res->success) {
            $data->courses = $res->data;
            $data->haserrors = false;
        } else {
            $data->errors = $res->errors;
            $data->haserrors = true;
        }

        return $data;
    }
}
