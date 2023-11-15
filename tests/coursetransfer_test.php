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

namespace local_coursetransfer;

use advanced_testcase;
use backup;
use base_plan_exception;
use base_setting_exception;
use coding_exception;
use context_course;
use core\event\course_section_deleted;
use core\task\adhoc_task;
use core\task\manager;
use core_user;
use dml_exception;
use invalid_parameter_exception;
use local_coursetransfer\external\frontend\sites_external;
use local_coursetransfer\factory\user;
use local_coursetransfer\models\configuration_course;
use local_coursetransfer\task\create_backup_course_task;
use mod_label_generator;
use mod_resource_generator;
use moodle_exception;
use moodle_url;
use phpunit_util;
use stdClass;
use testing_data_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/setuplib.php');
require_once($CFG->libdir . '/cronlib.php');

/**
 * coursetransfer_test
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @group      local_coursetransfer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer_test extends advanced_testcase {


    /** @var stdClass Origin Course */
    protected $origincourse;

    /** @var stdClass Destiny New Course */
    protected $destinynewcourse;

    /** @var stdClass User */
    protected $user;

    /** @var stdClass Site Origin */
    protected $siteorigin;

    /** @var stdClass Site Destination */
    protected $sitedestiny;

    /** @var testing_data_generator Generator */
    protected $generator;

    /** @var stdClass Mod Assign 1 */
    protected $modassign1;

    /** @var stdClass Mod Assign 2 */
    protected $modassign2;

    /** @var stdClass Mod Assign 3 */
    protected $modassign3;

    /** @var stdClass Mod Resource 1 */
    protected $modresource1;

    /** @var stdClass Mod Label 1 */
    protected $modlabel1;

    /** @var stdClass Mod Label 2 */
    protected $modlabel2;

    /** @var stdClass Student1 */
    protected $student1;

    /** @var stdClass Student1 */
    protected $student2;

    /** @var stdClass Student1 */
    protected $student3;

    /** @var stdClass Tutor1 */
    protected $tutor1;

    /** @var stdClass Tutor2 */
    protected $tutor2;

    /** @var stdClass Group 1 */
    protected $group1;

    /** @var stdClass Group 2 */
    protected $group2;

    /**
     * Tests Set UP.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function setUp():void {
        $this->generator = phpunit_util::get_data_generator();

        $this->setup_config();

        $this->setAdminUser();

        $this->resetAfterTest(true);
    }

    /**
     * Setup Config.
     *
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function setup_config() {
        global $CFG;
        $token = coursetransfer::postinstall();
        $this->user = core_user::get_user_by_username(user::USERNAME_WS);
        $ressiteorigin = sites_external::site_add('origin', $CFG->wwwroot, $token);
        $ressitedestiny = sites_external::site_add('destiny', $CFG->wwwroot, $token);
        if ($ressiteorigin['success']) {
            $site = coursetransfer_sites::get('origin', $ressiteorigin['data']->id);
            $this->siteorigin = $site;
        }
        if ($ressitedestiny['success']) {
            $site = coursetransfer_sites::get('destiny', $ressitedestiny['data']->id);
            $this->sitedestiny = $site;
        }
    }

    /**
     * Create New Courses.
     *
     * @param string $shortname
     * @throws moodle_exception
     */
    protected function create_new_courses(string $shortname) {
        // Create Origin Course.
        $oc = [
                'fullname' => 'Origen Course',
                'shortname' => $shortname,
                'summary' => 'This a Summary',
                'numsections' => 0
        ];
        $this->origincourse = $this->getDataGenerator()->create_course($oc);

        $this->student1 = $this->getDataGenerator()->create_user(['email' => 'student1@moodle.com']);
        $this->getDataGenerator()->enrol_user($this->student1->id, $this->origincourse->id, 'student');
        $this->student2 = $this->getDataGenerator()->create_user(['email' => 'student2@moodle.com']);
        $this->getDataGenerator()->enrol_user($this->student2->id, $this->origincourse->id, 'student');
        $this->student3 = $this->getDataGenerator()->create_user(['email' => 'student3@moodle.com']);
        $this->getDataGenerator()->enrol_user($this->student3->id, $this->origincourse->id, 'student');
        $this->tutor1 = $this->getDataGenerator()->create_user(['email' => 'tutor1@moodle.com']);
        $this->getDataGenerator()->enrol_user($this->tutor1->id, $this->origincourse->id, 'editingteacher');
        $this->tutor2 = $this->getDataGenerator()->create_user(['email' => 'tutor2@moodle.com']);
        $this->getDataGenerator()->enrol_user($this->tutor2->id, $this->origincourse->id, 'editingteacher');

        $this->group1 = $this->getDataGenerator()->create_group(['courseid' => $this->origincourse->id]);
        groups_add_member($this->group1, $this->student1);
        groups_add_member($this->group1, $this->student2);

        $this->group2 = $this->getDataGenerator()->create_group(['courseid' => $this->origincourse->id]);
        groups_add_member($this->group2, $this->student3);

        // Create Destiny New Course.
        $dnc = [
                'fullname' => 'Remote Restoring in process...',
                'shortname' => 'IN-PROGRESS-' . time(),
                'summary' => 'This a other Summary',
                'numsections' => 0
        ];
        $this->destinynewcourse = $this->getDataGenerator()->create_course($dnc);

        $this->create_sections();
    }

    /**
     * Create Sections.
     *
     * @throws moodle_exception
     */
    protected function create_sections() {
        $newsection = course_create_section($this->origincourse->id);
        $new = clone($newsection);
        $new->name = 'SuperHeroes';
        $new->summary = 'SuperHeroes Summary';
        course_update_section($this->origincourse->id, $newsection, $new);
        $this->modassign1 = $this->create_mod_assign('Superman', 'Intro Superman', $newsection);
        $this->modassign2 = $this->create_mod_assign('Spiderman', 'Intro Spiderman', $newsection);
        $this->modassign3 = $this->create_mod_assign('Batman', 'Intro Batman', $newsection);
        $this->modresource1 = $this->create_mod_resource('Catwoman', 'Intro Catwoman', $newsection);
        $newsection2 = course_create_section($this->origincourse->id);
        $new2 = clone($newsection2);
        $new2->name = 'Cars';
        $new2->summary = 'Cars Summary';
        course_update_section($this->origincourse->id, $newsection2, $new2);
        $this->modlabel1 = $this->create_mod_label('Ford', 'Intro Ford', $newsection2);
        $this->modlabel2 = $this->create_mod_label('Ford', 'Intro Ford', $newsection2);
    }

    /**
     * Create Mod Assign.
     *
     * @param string $name
     * @param string $intro
     * @param stdClass $section
     * @return stdClass
     * @throws coding_exception
     */
    protected function create_mod_assign(string $name, string $intro, stdClass $section): stdClass {
        $generator = $this->generator->get_plugin_generator('mod_assign');

        $record = [
                'course' => $this->origincourse,
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
     * @param string $name
     * @param string $intro
     * @param stdClass $section
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function create_mod_resource(string $name, string $intro, stdClass $section): stdClass {
        /** @var mod_resource_generator $generator */
        $generator = $this->generator->get_plugin_generator('mod_resource');

        $record = [
                'course' => $this->origincourse,
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
     * @param string $name
     * @param string $intro
     * @param stdClass $section
     * @return stdClass
     */
    protected function create_mod_label(string $name, string $intro, stdClass $section): stdClass {
        /** @var mod_label_generator $generator */
        $generator = $this->generator->get_plugin_generator('mod_label');

        $record = [
                'course' => $this->origincourse,
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
     * Tests restore course.
     *
     * @throws moodle_exception
     */
    public function tests_restore_course() {
        // 1. Test.
        $this->create_new_courses('phpunit_origincourse-1');
        $configuration = new configuration_course(
                backup::TARGET_NEW_COURSE,
                false,
                false,
                true,
                false);
        $requestorigin1 = $this->test_restore_course($configuration);
        $this->execute_tasks();
        $this->execute_restore($requestorigin1);
        $this->validate();
    }

    /**
     * Tests backup.
     *
     * @covers coursetransfer::restore_course
     * @param configuration_course $configuration
     * @return stdClass
     * @throws base_plan_exception
     * @throws base_setting_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function test_restore_course(configuration_course $configuration): stdClass {

        $sections = [];

        $requestdestination = coursetransfer_request::set_request_restore_course(
                $this->user,
                $this->siteorigin,
                $this->destinynewcourse->id,
                $this->origincourse->id,
                $configuration,
                $sections,
                null);

        $requestorigin = coursetransfer_request::set_request_restore_course_response(
                $this->user,
                $requestdestination->id,
                $this->sitedestiny,
                $this->destinynewcourse->id,
                $this->origincourse,
                $configuration,
                $sections);

        // Create Backup.
        $restask = coursetransfer_backup::create_task_backup_course(
                $this->origincourse->id,
                $this->user->id,
                $this->sitedestiny,
                $requestdestination->id,
                $requestorigin->id,
                $sections,
                $configuration->originenrolusers,
                true
        );

        $this->assertTrue($restask);

        return $requestorigin;

    }

    /**
     * Execute Tasks.
     */
    protected function execute_tasks() {
        // Backup Task.
        $tasks = manager::get_adhoc_tasks('local_coursetransfer\task\create_backup_course_task');
        foreach ($tasks as $task) {
            ob_start();
            $task->execute();
            ob_end_clean();
        }
    }

    /**
     * Execute Tasks.
     *
     * @param stdClass $requestorigin
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function execute_restore(stdClass $requestorigin) {

        $requestorigin = coursetransfer_request::get($requestorigin->id);

        $this->assertNotEmpty($requestorigin->origin_backup_url);

        $context = context_course::instance($this->origincourse->id);

        $fs = get_file_storage();

        $file = $fs->get_file(
                $context->id, 'local_coursetransfer', 'backup',
                $requestorigin->id, '/', 'backup.mbz');

        $this->assertNotEmpty($file);

        $resrest = coursetransfer_restore::create_task_restore_course($requestorigin, $file);

        $this->assertTrue($resrest);
        $this->assertNotEquals($this->origincourse->summary, $this->destinynewcourse->summary);

        $rtasks = manager::get_adhoc_tasks('local_coursetransfer\task\restore_course_task');
        foreach ($rtasks as $rtask) {
            ob_start();
            $rtask->execute();
            ob_end_clean();
        }
    }

    /**
     * Validate.
     *
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function validate() {
        $destinycoursemod = get_course($this->destinynewcourse->id);
        $this->assertEquals($this->origincourse->summary, $destinycoursemod->summary);
        $this->review_modules();
        $this->review_enrols();
    }

    /**
     *
     * @throws coding_exception
     */
    protected function review_enrols() {
        $users = enrol_get_course_users($this->destinynewcourse->id);
        $this->assertCount(5, $users);
        $groups = groups_get_all_groups($this->destinynewcourse->id);
        $this->assertCount(2, $groups);
        $members = groups_get_members($this->group1->id);
        $this->assertCount(2, $members);
        $members = groups_get_members($this->group2->id);
        $this->assertCount(1, $members);
        foreach ($members as $member) {
            $this->assertEquals('student3@moodle.com', $member->email);
        }
    }

    /**
     * @throws moodle_exception
     */
    protected function review_modules() {
        $modinfo = get_fast_modinfo($this->destinynewcourse->id);
        $cms = $modinfo->get_cms();
        $sections = $modinfo->get_section_info_all();
        $this->assertCount(6, $cms);
        $this->assertCount(3, $sections);
        $sc = 0;
        foreach ($sections as $section) {
            if ($section->name === 'SuperHeroes') {
                $this->assertEquals('SuperHeroes Summary', $section->summary);
                $mods = 0;
                foreach ($cms as $cm) {
                    if ($cm->section === $section->id) {
                        $mods ++;
                    }
                }
                $this->assertEquals(4, $mods);
                $sc ++;
            }
            if ($section->name === 'Cars') {
                $this->assertEquals('Cars Summary', $section->summary);
                $mods = 0;
                foreach ($cms as $cm) {
                    if ($cm->section === $section->id) {
                        $mods ++;
                    }
                }
                $this->assertEquals(2, $mods);
                $sc ++;
            }
        }
        $this->assertEquals(2, $sc);

    }

}
