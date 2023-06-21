<?php
// This file is part of the block_tresipuntsepe plugin for Moodle - http://moodle.org/
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
 * course
 *
 * @package     local_coursetransfer
 * @copyright   2023 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\factory;

use core_course_category;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/externallib.php');

class course {

    /**
     * Create
     *
     * @param core_course_category $category
     * @param string $fullname
     * @param string $shortname
     * @param string $summary
     * @return int
     * @throws moodle_exception
     */
    public static function create(core_course_category $category,
            string $fullname, string $shortname, string $summary): int {

        $datacourse = new stdClass();
        $datacourse->category = $category->id;
        $datacourse->shortname = $shortname;
        $datacourse->idnumber = $shortname;
        $datacourse->fullname = $fullname;
        $datacourse->visible = 1;
        $datacourse->summary = $summary;
        $datacourse->summaryformat = FORMAT_PLAIN;
        try {
            $res = create_course($datacourse);
            return $res->id;
        } catch (moodle_exception $e) {
            throw new moodle_exception('0090100', $e->getMessage());
        }
    }


}
