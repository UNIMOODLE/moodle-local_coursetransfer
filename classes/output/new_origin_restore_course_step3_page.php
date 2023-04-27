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
use core_reportbuilder\local\aggregation\count;
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
 * new_origin_restore_course_step3_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class new_origin_restore_course_step3_page implements renderable, templatable {

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
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $backurl = new moodle_url(
            '/local/coursetransfer/origin_restore_course.php',
            ['id' => $this->course->id, 'new' => 1, 'step' => 2]
        );
        $data->back_url = $backurl->out(false);
        $site = required_param('site', PARAM_INT);
        $restoreid = required_param('restoreid', PARAM_INT);
        $data->steps = [ ["current" => false, "num" => 1], ["current" => false, "num" => 2],
            ["current" => true, "num" => 3], ["current" => false, "num" => 4], ["current" => false, "num" => 5] ];
        $errors = [];

        if (coursetransfer::validate_origin_site($site)) {
            $data->haserrors = false;
            try {
                $request = new request($site);
                $res = $request->origin_get_course_detail($restoreid);
                if ($res->success) {
                    $data->course = $res->data;
                } else {
                    $data->errors = $res->errors;
                    $data->haserrors = true;
                }
            } catch (moodle_exception $e) {
                $data->errors = ['code' => '234341', 'msg' => $e->getMessage()];
                $data->haserrors = true;
            }
        } else {
            $data->haserrors = true;
            $errors[] = ['code' => 140, 'msg' => get_string('error_validate_site', 'local_coursetransfer')];
            $data->errors = $errors;
        }

        $nexturl = new moodle_url(
            '/local/coursetransfer/origin_restore_course.php',
            ['id' => $this->course->id, 'new' => 1, 'step' => 4, 'site' => $site, 'restoreid' => $restoreid]
        );
        $data->next_url = $nexturl->out(false);
        //echo '<pre>';
        // foreach ($data->course->sections as $section) {
            // var_dump(count($section->activities));
        // }
        for ($i = 0; $i < count($data->course->sections); $i++) {
            //var_dump($data->course->sections[0]->activities);
        }
        return $data;
    }
}
