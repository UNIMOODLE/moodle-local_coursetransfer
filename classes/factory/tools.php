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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\factory;

use coding_exception;
use mod_label_generator;
use mod_quiz_generator;
use mod_resource_generator;
use moodle_exception;
use phpunit_util;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/externallib.php');

/**
 * tools
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tools {

    /**
     * Create Mod Assign.
     *
     * @param stdClass $course
     * @param string $name
     * @param string $intro
     * @param stdClass $section
     * @return stdClass
     * @throws coding_exception
     */
    public static function create_mod_assign(stdClass $course, string $name, string $intro, stdClass $section): stdClass {
        $gen = phpunit_util::get_data_generator();
        $generator = $gen->get_plugin_generator('mod_assign');

        $record = [
                'course' => $course,
                'name' => $name,
                'intro' => $intro,
                'introformat' => FORMAT_HTML
        ];
        $options = [
                'section' => $section->section,
                'visible' => 1,
                'showdescription' => false
        ];
        return $generator->create_instance($record, $options);
    }

    /**
     * Create Mod Resource.
     *
     * @param stdClass $course
     * @param string $name
     * @param string $intro
     * @param stdClass $section
     * @return stdClass
     * @throws coding_exception
     */
    public static function create_mod_resource(stdClass $course, string $name, string $intro, stdClass $section): stdClass {
        $gen = phpunit_util::get_data_generator();
        /** @var mod_resource_generator $generator */
        $generator = $gen->get_plugin_generator('mod_resource');

        $record = [
                'course' => $course,
                'name' => $name,
                'intro' => $intro,
                'introformat' => FORMAT_HTML,
                'idnumber' => $name,
                'display' => 4
        ];
        $options = [
                'section' => $section->section,
                'visible' => 1,
                'showdescription' => false
        ];
        return $generator->create_instance($record, $options);
    }

    /**
     * Create Mod Label.
     *
     * @param stdClass $course
     * @param string $name
     * @param string $intro
     * @param stdClass $section
     * @return stdClass
     */
    public static function create_mod_label(stdClass $course, string $name, string $intro, stdClass $section): stdClass {
        $gen = phpunit_util::get_data_generator();
        /** @var mod_label_generator $generator */
        $generator = $gen->get_plugin_generator('mod_label');

        $record = [
                'course' => $course,
                'name' => $name,
                'intro' => $intro,
                'introformat' => FORMAT_HTML
        ];
        $options = [
                'section' => $section->section,
                'visible' => 1,
                'showdescription' => false
        ];
        return $generator->create_instance($record, $options);
    }

    /**
     * Create Mod Quiz.
     *
     * @param stdClass $course
     * @param string $name
     * @param string $intro
     * @param stdClass $section
     * @return stdClass
     * @throws moodle_exception
     */
    public static function create_mod_quiz(stdClass $course, string $name, string $intro, stdClass $section): stdClass {
        $gen = phpunit_util::get_data_generator();
        /** @var mod_quiz_generator $generator */
        $generator = $gen->get_plugin_generator('mod_quiz');

        $record = [
                'course' => $course,
                'name' => $name,
                'intro' => $intro,
                'introformat' => FORMAT_HTML,
                'files' => file_get_unused_draft_itemid(),
                'timeopen'                    => 0,
                'timeclose'                   => 0,
                'preferredbehaviour'          => 'deferredfeedback',
                'attempts'                    => 0,
                'attemptonlast'               => 0,
                'grademethod'                 => QUIZ_GRADEHIGHEST,
                'decimalpoints'               => 2,
                'questiondecimalpoints'       => -1,
                'attemptduring'               => 1,
                'correctnessduring'           => 1,
                'marksduring'                 => 1,
                'specificfeedbackduring'      => 1,
                'generalfeedbackduring'       => 1,
                'rightanswerduring'           => 1,
                'overallfeedbackduring'       => 0,
                'attemptimmediately'          => 1,
                'correctnessimmediately'      => 1,
                'marksimmediately'            => 1,
                'specificfeedbackimmediately' => 1,
                'generalfeedbackimmediately'  => 1,
                'rightanswerimmediately'      => 1,
                'overallfeedbackimmediately'  => 1,
                'attemptopen'                 => 1,
                'correctnessopen'             => 1,
                'marksopen'                   => 1,
                'specificfeedbackopen'        => 1,
                'generalfeedbackopen'         => 1,
                'rightansweropen'             => 1,
                'overallfeedbackopen'         => 1,
                'attemptclosed'               => 1,
                'correctnessclosed'           => 1,
                'marksclosed'                 => 1,
                'specificfeedbackclosed'      => 1,
                'generalfeedbackclosed'       => 1,
                'rightanswerclosed'           => 1,
                'overallfeedbackclosed'       => 1,
                'questionsperpage'            => 1,
                'shuffleanswers'              => 1,
                'sumgrades'                   => 10,
                'grade'                       => 10,
                'timecreated'                 => time(),
                'timemodified'                => time(),
                'timelimit'                   => 0,
                'overduehandling'             => 'autosubmit',
                'graceperiod'                 => 86400,
                'quizpassword'                => '',
                'subnet'                      => '',
                'browsersecurity'             => '',
                'delay1'                      => 0,
                'delay2'                      => 0,
                'showuserpicture'             => 0,
                'showblocks'                  => 0,
                'navmethod'                   => QUIZ_NAVMETHOD_FREE,
        ];
        $options = [
                'section' => $section->section,
                'visible' => 1,
                'showdescription' => false
        ];
        return $generator->create_instance($record, $options);
    }

}
