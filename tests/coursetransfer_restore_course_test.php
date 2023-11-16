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
use local_coursetransfer\factory\tools;
use local_coursetransfer\factory\user;
use local_coursetransfer\models\configuration_course;
use local_coursetransfer\task\create_backup_course_task;
use mod_label_generator;
use mod_quiz_generator;
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
 * coursetransfer_restore_course_test
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @group      local_coursetransfer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer_restore_course_test extends advanced_testcase {


    /** @var stdClass Origin Course */
    protected $origincourse;

    /** @var stdClass Destination New Course */
    protected $destinynewcourse1;

    /** @var stdClass Destination 2 */
    protected $destinycourse2;

    /** @var stdClass Destination 3 */
    protected $destinycourse3;

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
     * Create Courses.
     *
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function create_courses() {
        // Create Origin Course with modules and users&groups...
        $oc = [
                'fullname' => 'Origen Course',
                'shortname' => 'phpunit-origin-course',
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

        $this->create_sections_origin($this->origincourse);

        // Create Destiny New Course.
        $dnc1 = [
                'fullname' => 'Remote Restoring in process...',
                'shortname' => 'IN-PROGRESS-' . time(),
                'summary' => 'This a other Summary',
                'numsections' => 0
        ];
        $this->destinynewcourse1 = $this->getDataGenerator()->create_course($dnc1);

        // Create Destination Course 2 with modules and users&groups..
        $dnc2 = [
                'fullname' => 'Destination Course 2',
                'shortname' => 'phpunit-destination-course-2',
                'summary' => 'This a Summary of Destination 2',
                'numsections' => 0
        ];
        $this->destinycourse2 = $this->getDataGenerator()->create_course($dnc2);

        // Create Destination Course 2 with modules and users&groups..
        $dnc3 = [
                'fullname' => 'Destination Course 3',
                'shortname' => 'phpunit-destination-course-3',
                'summary' => 'This a Summary of Destination 3',
                'numsections' => 0
        ];
        $this->destinycourse3 = $this->getDataGenerator()->create_course($dnc3);
        $this->create_sections_destiny2($this->destinycourse3);

        $student4 = $this->getDataGenerator()->create_user(['email' => 'student4@moodle.com']);
        $this->getDataGenerator()->enrol_user($student4->id, $this->destinycourse3->id, 'student');
        $student5 = $this->getDataGenerator()->create_user(['email' => 'student5@moodle.com']);
        $this->getDataGenerator()->enrol_user($student5->id, $this->destinycourse3->id, 'student');
        $student6 = $this->getDataGenerator()->create_user(['email' => 'student6@moodle.com']);
        $this->getDataGenerator()->enrol_user($student6->id, $this->destinycourse3->id, 'student');
        $tutor3 = $this->getDataGenerator()->create_user(['email' => 'tutor3@moodle.com']);
        $this->getDataGenerator()->enrol_user($tutor3->id, $this->destinycourse3->id, 'editingteacher');
        $tutor4 = $this->getDataGenerator()->create_user(['email' => 'tutor4@moodle.com']);
        $this->getDataGenerator()->enrol_user($tutor4->id, $this->destinycourse3->id, 'editingteacher');

        $group3 = $this->getDataGenerator()->create_group(['courseid' => $this->destinycourse3->id]);
        groups_add_member($group3, $student4);
        groups_add_member($group3, $student5);

        $group4 = $this->getDataGenerator()->create_group(['courseid' => $this->destinycourse2->id]);
        groups_add_member($group4, $student6);
    }

    /**
     * Create Sections.
     *
     * @param stdClass $course
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function create_sections_origin(stdClass $course) {
        $newsection = course_create_section($course->id);
        $new = clone($newsection);
        $new->name = 'SuperHeroes';
        $new->summary = 'SuperHeroes Summary';
        course_update_section($course->id, $newsection, $new);
        $this->modassign1 = tools::create_mod_assign($course, 'Superman', 'Intro Superman', $newsection);
        $this->modassign2 = tools::create_mod_assign($course, 'Spiderman', 'Intro Spiderman', $newsection);
        $this->modassign3 = tools::create_mod_assign($course, 'Batman', 'Intro Batman', $newsection);
        $this->modresource1 = tools::create_mod_resource($course, 'Catwoman', 'Intro Catwoman', $newsection);
        $newsection2 = course_create_section($course->id);
        $new2 = clone($newsection2);
        $new2->name = 'Cars';
        $new2->summary = 'Cars Summary';
        course_update_section($course->id, $newsection2, $new2);
        $this->modlabel1 = tools::create_mod_label($course, 'Ford', 'Intro Ford', $newsection2);
        $this->modlabel2 = tools::create_mod_label($course, 'Ford', 'Intro Ford', $newsection2);
    }

    /**
     * Create Sections.
     *
     * @param stdClass $course
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function create_sections_destiny2(stdClass $course) {
        $newsection = course_create_section($course->id);
        $new = clone($newsection);
        $new->name = 'Football Players';
        $new->summary = 'Football Players Summary';
        course_update_section($course->id, $newsection, $new);
        $this->modassign1 = tools::create_mod_assign($course, 'Leo Messi', 'Intro Messi', $newsection);
        $this->modassign2 = tools::create_mod_assign($course, 'Cristiano Ronaldo', 'Intro Ronaldo', $newsection);
        $this->modresource1 = tools::create_mod_resource($course, 'Erling Haaland', 'Intro Catwoman', $newsection);
        $newsection2 = course_create_section($course->id);
        $new2 = clone($newsection2);
        $new2->name = 'Basketball Players';
        $new2->summary = 'Basketball Players Summary';
        course_update_section($course->id, $newsection2, $new2);
        $this->modlabel1 = tools::create_mod_label($course, 'Lebron James', 'Intro Lebron James', $newsection2);
        $this->modlabel2 = tools::create_mod_quiz($course, 'James Harden', 'Intro James Harden', $newsection2);
    }

    /**
     * Tests restore course.
     *
     * @throws moodle_exception
     */
    public function tests_restore_course() {
        // 1. Test New Course. Without Users.
        $this->create_courses();
        $configuration1 = new configuration_course(
                backup::TARGET_NEW_COURSE,
                false,
                false,
                false,
                false);
        //$requestorigin1 = $this->test_restore_course($configuration1, $this->destinynewcourse1, $this->origincourse);
        // 2. Test in Destination Course. With Users and Groups.
        $configuration2 = new configuration_course(
                backup::TARGET_NEW_COURSE,
                false,
                false,
                true,
                false);
        //$requestorigin2 = $this->test_restore_course($configuration2, $this->destinycourse2, $this->origincourse);
        // 3. Test in Destination Course. Witouth Users. Delete Content and Users and Groups.
        $configuration3 = new configuration_course(
                backup::TARGET_EXISTING_DELETING,
                true,
                true,
                true,
                false);
        $requestorigin3 = $this->test_restore_course($configuration3, $this->destinycourse3, $this->origincourse);
        // 4. Test in Destination Course. With Users and Groups. Merge Content and Users and Groups.
        // 5. Test in Destination Course. Remove Origin Course.

        // EXECUTE TASKS.
        $this->execute_tasks();
        // 1. Test New Course. Without Users.
        //$this->execute_restore($requestorigin1, $this->destinynewcourse1, $this->origincourse);
        //$this->execute_restore($requestorigin2, $this->destinycourse2, $this->origincourse);
        $this->execute_restore($requestorigin3, $this->destinycourse3, $this->origincourse);

        // VALIDATE DATA.
        // 1. Test New Course. Without Users.
        //$this->validate_course($this->destinynewcourse1, $this->origincourse);
        //$this->review_modules($this->destinynewcourse1);
        //$this->review_enrols($this->destinynewcourse1, 0, 0, []);
        // 2. Test in Destination Course. With Users and Groups.
        //$this->validate_course($this->destinycourse2, $this->origincourse);
        //$this->review_modules($this->destinycourse2);
        //$this->review_enrols($this->destinycourse2, 5, 2, [
        //        ['group' => $this->group1, 'count' => 2], ['group' => $this->group2, 'count' => 1]]);
        // 3. Test in Destination Course. Without Users. Delete Content and Users and Groups.
        $this->validate_course_not_equals($this->destinycourse3, $this->origincourse);
        $this->validate_request($requestorigin3);
    }

    /**
     * Tests backup.
     *
     * @covers coursetransfer::restore_course
     * @param configuration_course $configuration
     * @param stdClass $coursedestiny
     * @param stdClass $courseorigin
     * @param array $sections
     * @return stdClass
     * @throws base_plan_exception
     * @throws base_setting_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function test_restore_course(
            configuration_course $configuration, stdClass $coursedestiny, stdClass $courseorigin, $sections = []): stdClass {

        $requestdestination = coursetransfer_request::set_request_restore_course(
                $this->user,
                $this->siteorigin,
                $coursedestiny->id,
                $courseorigin->id,
                $configuration,
                $sections,
                null);

        $requestorigin = coursetransfer_request::set_request_restore_course_response(
                $this->user,
                $requestdestination->id,
                $this->sitedestiny,
                $coursedestiny->id,
                $courseorigin,
                $configuration,
                $sections);

        // Create Backup.
        $restask = coursetransfer_backup::create_task_backup_course(
                $courseorigin->id,
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
     * @param stdClass $coursedestiny
     * @param stdClass $courseorigin
     * @throws dml_exception
     */
    protected function execute_restore(stdClass $requestorigin, stdClass $coursedestiny, stdClass $courseorigin) {

        $requestorigin = coursetransfer_request::get($requestorigin->id);

        $this->assertNotEmpty($requestorigin->origin_backup_url);

        $context = context_course::instance($courseorigin->id);

        $fs = get_file_storage();

        $file = $fs->get_file(
                $context->id, 'local_coursetransfer', 'backup',
                $requestorigin->id, '/', 'backup.mbz');

        $this->assertNotEmpty($file);

        $resrest = coursetransfer_restore::create_task_restore_course($requestorigin, $file);

        $this->assertTrue($resrest);
        $this->assertNotEquals($courseorigin->summary, $coursedestiny->summary);

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
     * @param stdClass $coursedestiny
     * @param stdClass $courseorigin
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function validate_course_equals(stdClass $coursedestiny, stdClass $courseorigin) {
        $destinycoursemod = get_course($coursedestiny->id);
        $this->assertEquals($courseorigin->summary, $destinycoursemod->summary);
    }

    /**
     * Validate.
     *
     * @param stdClass $coursedestiny
     * @param stdClass $courseorigin
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function validate_course_not_equals(stdClass $coursedestiny, stdClass $courseorigin) {
        $destinycoursemod = get_course($coursedestiny->id);
        $this->assertNotEquals($courseorigin->summary, $destinycoursemod->summary);
    }

    /**
     * Validate Request.
     *
     * @param stdClass $request
     * @throws dml_exception
     */
    protected function validate_request(stdClass $request) {
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, (int)$request->status);
    }

    /**
     * @param stdClass $coursedestiny
     * @throws moodle_exception
     */
    protected function review_modules(stdClass $coursedestiny) {
        $modinfo = get_fast_modinfo($coursedestiny->id);
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

    /**
     *
     * @param stdClass $coursedestiny
     * @param int $userscount
     * @param int $groupscount
     * @param array $gs
     * @throws coding_exception
     */
    protected function review_enrols(stdClass $coursedestiny, int $userscount, int $groupscount, array $gs) {
        $users = enrol_get_course_users($coursedestiny->id);
        $this->assertCount($userscount, $users);
        $groups = groups_get_all_groups($coursedestiny->id);
        $this->assertCount($groupscount, $groups);
        foreach ($gs as $g) {
            $members = groups_get_members($g['group']->id);
            $this->assertCount($g['count'], $members);
            $members = groups_get_members($g['group']->id);
            $this->assertCount($g['count'], $members);
        }
    }

}
