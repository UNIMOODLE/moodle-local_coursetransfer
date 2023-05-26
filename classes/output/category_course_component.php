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

use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * category_course_component
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_course_component implements renderable, templatable {

    /** @var int ID */
    protected $id;

    /** @var string Courses JSON details */
    protected $courses;

    /** @var string Origin Site URL */
    protected $siteurl;

    /**
     *  constructor.
     *
     * @param string $courses
     * @param string $siteurl
     * @param int $id
     */
    public function __construct(string $courses, string $siteurl, int $id) {
        $this->id = $id;
        $this->courses = $courses;
        $this->siteurl = $siteurl;
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $category = new stdClass();
        $category->courses = $this->get_courses_data();
        $data = new stdClass();
        $data->id = $this->id;
        $data->category = $category;
        return $data;
    }

    /**
     * Get Courses Data.
     *
     * @return array
     */
    protected function get_courses_data(): array {
        if (!empty($this->courses)) {
            return json_decode($this->courses);
        } else {
            return [];
        }
    }
}
