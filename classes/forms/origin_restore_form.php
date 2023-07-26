<?php
// This file is part of the local_tresipuntintegrations plugin for Moodle - http://moodle.org/
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
 * Class origin_restore_form
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_coursetransfer\forms;

defined('MOODLE_INTERNAL') || die();
global $CFG;

use coding_exception;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodleform;

require_once($CFG->libdir . '/formslib.php');

class origin_restore_form extends moodleform {

    /**
     * Definition
     *
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function definition() {
        $this->_form->disable_form_change_checker();

        $mform = $this->_form;

        $mform->addElement(
                'select', 'course_categories',
                get_string('origin_site', 'local_coursetransfer'),
                [ 'courses' => 'courses', 'categories' => 'categories']);
        $mform->addHelpButton('course_categories', 'course_categories', 'local_coursetransfer');

        $sites = coursetransfer::get_origin_sites();

        $mform->addElement(
                'select', 'origin_site',
                get_string('origin_site', 'local_coursetransfer'), $sites);
        $mform->addHelpButton('origin_site', 'origin_site', 'local_coursetransfer');

    }

}
