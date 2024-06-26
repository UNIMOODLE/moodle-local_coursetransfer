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
 * coursetransfer_restore_course_test
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
use local_coursetransfer\external\backend\target_course_callback_external;
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
use stored_file;
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
class coursetransfer_restore_course_merge_test extends advanced_testcase {


    /** @var stdClass Origin Course */
    protected $origincourse;

    /** @var stdClass Target 4 */
    protected $targetcourse4;

    /** @var stdClass User */
    protected $user;

    /** @var stdClass Site Origin */
    protected $siteorigin;

    /** @var stdClass Site Target */
    protected $sitetarget;

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

    /** @var stdClass Group 3 */
    protected $group3;

    /** @var stdClass Group 4 */
    protected $group4;

    /**
     * Tests Set UP.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function setUp():void {

        $this->resetAfterTest(true);
        $this->generator = phpunit_util::get_data_generator();

        $this->setup_config();

        $this->setAdminUser();
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
        $ressitetarget = sites_external::site_add('target', $CFG->wwwroot, $token);
        if ($ressiteorigin['success']) {
            $site = coursetransfer_sites::get('origin', $ressiteorigin['data']->id);
            $this->siteorigin = $site;
        }
        if ($ressitetarget['success']) {
            $site = coursetransfer_sites::get('target', $ressitetarget['data']->id);
            $this->sitetarget = $site;
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
                'numsections' => 0,
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

        // Create Target Course 4 with modules and users&groups..
        $dnc4 = [
                'fullname' => 'Target Course 4',
                'shortname' => 'phpunit-target-course-4',
                'summary' => 'This a Summary of Target 4',
                'numsections' => 0,
        ];
        $this->targetcourse4 = $this->getDataGenerator()->create_course($dnc4);
        $this->create_sections_target4($this->targetcourse4);
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
        $this->modlabel2 = tools::create_mod_label($course, 'Renault', 'Intro Renault', $newsection2);
    }

    /**
     * Create Sections.
     *
     * @param stdClass $course
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function create_sections_target4(stdClass $course) {
        $newsection = course_create_section($course->id);
        $new = clone($newsection);
        $new->name = 'Colors';
        $new->summary = 'Colors Summary';
        course_update_section($course->id, $newsection, $new);
        $this->modassign1 = tools::create_mod_assign($course, 'Blue', 'Intro Blue', $newsection);
        $this->modassign2 = tools::create_mod_assign($course, 'Red', 'Intro Red', $newsection);
        $this->modresource1 = tools::create_mod_resource($course, 'Green', 'Intro Green', $newsection);
        $newsection2 = course_create_section($course->id);
        $new2 = clone($newsection2);
        $new2->name = 'Countries';
        $new2->summary = 'Countries Summary';
        course_update_section($course->id, $newsection2, $new2);
        $this->modlabel1 = tools::create_mod_label($course, 'Italy', 'Intro Italy', $newsection2);
        $newsection3 = course_create_section($course->id);
        $new3 = clone($newsection3);
        $new3->name = 'Players';
        $new3->summary = 'Players Summary';
        course_update_section($course->id, $newsection3, $new3);
    }

    /**
     * Tests restore course.
     *
     * @throws moodle_exception
     */
    public function tests_restore_course() {
        $this->create_courses();
        // 4. Test in Target Course. With Users and Groups. Merge Content and Users and Groups.
        $configuration4 = new configuration_course(
                backup::TARGET_EXISTING_ADDING,
                false,
                false,
                true,
                false,
                0
        );
        list($requesttarget4, $requestorigin4) = $this->test_restore_course(
                $configuration4, $this->targetcourse4, $this->origincourse);

        // EXECUTE TASKS.
        $this->execute_tasks();

        // CALLBACKS.
        $file4 = $this->execute_callback($requesttarget4, $requestorigin4, $this->origincourse);
        $this->validate_request_in_backup($requesttarget4);

        // 1. Test New Course. Without Users.
        $this->execute_restore($requesttarget4, $requestorigin4, $this->targetcourse4, $this->origincourse, $file4);

        // VALIDATE DATA.
        // 4. Test in Target Course. With Users and Groups. Merge Content and Users and Groups.
        $this->validate_course_not_equals($this->targetcourse4, $this->origincourse);
        $this->validate_request_completed($requesttarget4);
        $this->review_enrols($this->targetcourse4, 5, 2, [
                ['group' => $this->group1, 'count' => 2], ['group' => $this->group2, 'count' => 1]]);
        $this->review_modules4($this->targetcourse4);
    }

    /**
     * Execute Callback.
     *
     * @param stdClass $requesttarget
     * @param stdClass $requestorigin
     * @param stdClass $origincourse
     * @return bool|stored_file
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    protected function execute_callback(stdClass $requesttarget, stdClass $requestorigin, stdClass $origincourse) {
        $file = $this->get_file($origincourse, $requestorigin);
        global $USER;
        $field = get_config('local_coursetransfer', 'origin_field_search_user');
        $value = $USER->{$field};
        target_course_callback_external::target_backup_course_completed(
                $field, $value, $requesttarget->id, $file->get_filesize(), $file->get_filepath()
        );
        return $file;
    }

    /**
     * Tests backup.
     *
     * @covers coursetransfer::restore_course
     * @param configuration_course $configuration
     * @param stdClass $coursetarget
     * @param stdClass $courseorigin
     * @param array $sections
     * @return array
     * @throws base_plan_exception
     * @throws base_setting_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function test_restore_course(
            configuration_course $configuration, stdClass $coursetarget, stdClass $courseorigin, $sections = []): array {

        $requesttarget = coursetransfer_request::set_request_restore_course(
                $this->user,
                $this->siteorigin,
                $coursetarget->id,
                $courseorigin->id,
                $configuration,
                $sections,
                null);

        $requestorigin = coursetransfer_request::set_request_restore_course_response(
                $this->user,
                $requesttarget->id,
                $this->sitetarget,
                $coursetarget->id,
                $courseorigin,
                $configuration,
                $sections);

        // Create Backup.
        $restask = coursetransfer_backup::create_task_backup_course(
                $courseorigin->id,
                $this->user->id,
                $this->sitetarget,
                $requesttarget->id,
                $requestorigin->id,
                $sections,
                $configuration->originenrolusers,
                null,
                true
        );

        $this->assertTrue($restask);

        return [$requesttarget, $requestorigin];

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
     * Get File.
     *
     * @param stdClass $courseorigin
     * @param stdClass $requestorigin
     * @return bool|stored_file
     */
    protected function get_file(stdClass $courseorigin, stdClass $requestorigin) {

        $context = context_course::instance($courseorigin->id);

        $fs = get_file_storage();

        $file = $fs->get_file(
                $context->id, 'local_coursetransfer', 'backup',
                $requestorigin->id, '/', 'backup.mbz');

        $this->assertNotEmpty($file);

        return $file;
    }

    /**
     * Execute Tasks.
     *
     * @param stdClass $requesttarget
     * @param stdClass $requestorigin
     * @param stdClass $coursetarget
     * @param stdClass $courseorigin
     * @param stored_file $file
     * @throws dml_exception
     */
    protected function execute_restore(
            stdClass $requesttarget, stdClass $requestorigin, stdClass $coursetarget, stdClass $courseorigin,
            stored_file $file) {

        $requesttarget = coursetransfer_request::get($requesttarget->id);
        $requestorigin = coursetransfer_request::get($requestorigin->id);

        $this->assertNotEmpty($requestorigin->origin_backup_url);

        $resrest = coursetransfer_restore::create_task_restore_course($requesttarget, $file);

        $this->assertTrue($resrest);
        $this->assertNotEquals($courseorigin->summary, $coursetarget->summary);

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
     * @param stdClass $coursetarget
     * @param stdClass $courseorigin
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function validate_course_equals(stdClass $coursetarget, stdClass $courseorigin) {
        $targetcoursemod = get_course($coursetarget->id);
        $this->assertEquals($courseorigin->summary, $targetcoursemod->summary);
    }

    /**
     * Validate.
     *
     * @param stdClass $coursetarget
     * @param stdClass $courseorigin
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function validate_course_not_equals(stdClass $coursetarget, stdClass $courseorigin) {
        $targetcoursemod = get_course($coursetarget->id);
        $this->assertNotEquals($courseorigin->summary, $targetcoursemod->summary);
    }

    /**
     * Validate Request.
     *
     * @param stdClass $request
     * @throws dml_exception
     */
    protected function validate_request_not_started(stdClass $request) {
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_NOT_STARTED, (int)$request->status);
    }

    /**
     * Validate Request.
     *
     * @param stdClass $request
     * @throws dml_exception
     */
    protected function validate_request_in_backup(stdClass $request) {
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_BACKUP, (int)$request->status);
    }

    /**
     * Validate Request.
     *
     * @param stdClass $request
     * @throws dml_exception
     */
    protected function validate_request_in_downloaded(stdClass $request) {
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_DOWNLOADED, (int)$request->status);
    }

    /**
     * Validate Request.
     *
     * @param stdClass $request
     * @throws dml_exception
     */
    protected function validate_request_completed(stdClass $request) {
        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, (int)$request->status);
    }

    /**
     * Review modules4.
     *
     * @param stdClass $coursetarget
     * @throws moodle_exception
     */
    protected function review_modules4(stdClass $coursetarget) {
        $modinfo = get_fast_modinfo($coursetarget->id);
        $cms = $modinfo->get_cms();
        $sections = $modinfo->get_section_info_all();
        $this->assertCount(10, $cms);
        $this->assertCount(4, $sections);
    }

    /**
     * Review Enrols.
     *
     * @param stdClass $coursetarget
     * @param int $userscount
     * @param int $groupscount
     * @param array $gs
     * @throws coding_exception
     */
    protected function review_enrols(stdClass $coursetarget, int $userscount, int $groupscount, array $gs) {
        $users = enrol_get_course_users($coursetarget->id);
        $this->assertCount($userscount, $users);
        $groups = groups_get_all_groups($coursetarget->id);
        $this->assertCount($groupscount, $groups);
        foreach ($gs as $g) {
            $members = groups_get_members($g['group']->id);
            $this->assertCount($g['count'], $members);
            $members = groups_get_members($g['group']->id);
            $this->assertCount($g['count'], $members);
        }
    }

}
