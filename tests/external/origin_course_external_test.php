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
 * Contract tests for the origin user and course read web services.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external;

use advanced_testcase;
use core_external\external_api;
use core_external\external_description;
use local_coursetransfer\external\backend\origin_course_external;
use local_coursetransfer\external\backend\origin_user_external;
use stdClass;

/**
 * Contract tests for origin_has_user, origin_get_courses, origin_get_course_detail and
 * origin_get_courses_by_ids.
 *
 * The origin resolves the requesting user from field/value and lists what that user may back up.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\backend\origin_user_external
 * @covers     \local_coursetransfer\external\backend\origin_course_external
 */
final class origin_course_external_test extends advanced_testcase {
    /** @var stdClass Teacher of course1 and course2 only. */
    private $teacher;

    /** @var stdClass[] Courses: course1, course2 (teacher's) and other (not the teacher's). */
    private $courses = [];

    /**
     * Three courses, the teacher edits two of them.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category(['name' => 'Year 2026']);
        $this->teacher = $generator->create_user(['username' => 'teacher1', 'email' => 'teacher1@example.com']);
        foreach (['course1' => 'Maths', 'course2' => 'Physics', 'other' => 'History'] as $key => $name) {
            $this->courses[$key] = $generator->create_course([
                'fullname' => $name,
                'shortname' => $key,
                'category' => $category->id,
            ]);
        }
        $generator->enrol_user($this->teacher->id, $this->courses['course1']->id, 'editingteacher');
        $generator->enrol_user($this->teacher->id, $this->courses['course2']->id, 'editingteacher');
    }

    /**
     * Return description of a method.
     *
     * Some structures pass PARAM_TEXT and a label as description and $required, which raises a
     * developer debugging() message when the description is built (plugin bug, reported in
     * TIPCOUTRAN-10). It is discarded here so the contract itself can be checked.
     *
     * @param string $class
     * @param string $method
     * @return external_description
     */
    private function returns(string $class, string $method): external_description {
        $description = call_user_func([$class, $method . '_returns']);
        $this->resetDebugging();
        return $description;
    }

    /**
     * origin_has_user resolves the user by any allowed field.
     */
    public function test_origin_has_user(): void {
        $values = ['username' => 'teacher1', 'email' => 'teacher1@example.com', 'userid' => $this->teacher->id];
        foreach ($values as $field => $value) {
            $res = origin_user_external::origin_has_user($field, (string) $value);
            $res = external_api::clean_returnvalue($this->returns(origin_user_external::class, 'origin_has_user'), $res);
            $this->assertTrue($res['success'], $field);
            $this->assertSame((int) $this->teacher->id, $res['data']['userid'], $field);
            $this->assertSame('teacher1', $res['data']['username'], $field);
        }
    }

    /**
     * origin_has_user rejects fields outside the allowed list, unknown users and users without courses.
     */
    public function test_origin_has_user_rejected(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->courses['other']->id, 'student');
        $cases = [
            '17001' => ['password', 'x'],
            '17002' => ['username', 'nobody'],
            '17003' => ['username', ''],
        ];
        foreach ($cases as $code => [$field, $value]) {
            $res = origin_user_external::origin_has_user($field, $value);
            $res = external_api::clean_returnvalue($this->returns(origin_user_external::class, 'origin_has_user'), $res);
            $this->assertFalse($res['success'], "Case $code");
            $this->assertSame((string) $code, $res['errors'][0]['code'], "Case $code");
            $this->assertSame([], $res['data'], "Case $code");
        }

        $res = origin_user_external::origin_has_user('username', $student->username);
        $this->assertSame('17001', $res['errors'][0]['code']);
    }

    /**
     * origin_get_courses lists only the courses the user can back up, with search and paging.
     */
    public function test_origin_get_courses(): void {
        $returns = $this->returns(origin_course_external::class, 'origin_get_courses');

        $res = external_api::clean_returnvalue($returns, origin_course_external::origin_get_courses('username', 'teacher1'));
        $this->assertTrue($res['success']);
        $this->assertEqualsCanonicalizing(['course1', 'course2'], array_column($res['data'], 'shortname'));
        $this->assertSame(2, $res['paging']['totalcount']);
        $this->assertSame('Year 2026', $res['data'][0]['categoryname']);
        $this->assertStringContainsString('/course/view.php?id=', $res['data'][0]['url']);

        $res = external_api::clean_returnvalue($returns, origin_course_external::origin_get_courses('username', 'teacher1', 1, 1));
        $this->assertCount(1, $res['data']);
        $this->assertSame(['totalcount' => 2, 'page' => 1, 'perpage' => 1], $res['paging']);

        $res = origin_course_external::origin_get_courses('username', 'teacher1', 0, 0, 'phys');
        $res = external_api::clean_returnvalue($returns, $res);
        $this->assertSame(['course2'], array_column($res['data'], 'shortname'));
    }

    /**
     * origin_get_courses rejects unknown users.
     */
    public function test_origin_get_courses_rejected(): void {
        $res = origin_course_external::origin_get_courses('username', 'nobody');
        $res = external_api::clean_returnvalue($this->returns(origin_course_external::class, 'origin_get_courses'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('17002', $res['errors'][0]['code']);
        $this->assertSame([], $res['data']);
    }

    /**
     * origin_get_course_detail returns the course with its sections and activities.
     */
    public function test_origin_get_course_detail(): void {
        $course = $this->courses['course1'];
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Intro']);

        $res = origin_course_external::origin_get_course_detail('username', 'teacher1', (int) $course->id);
        $res = external_api::clean_returnvalue($this->returns(origin_course_external::class, 'origin_get_course_detail'), $res);

        $this->assertTrue($res['success']);
        $this->assertSame((int) $course->id, $res['data']['id']);
        $this->assertSame('Maths', $res['data']['fullname']);
        $this->assertSame('Year 2026', $res['data']['categoryname']);
        $section = $res['data']['sections'][1];
        $this->assertSame(1, $section['sectionnum']);
        $this->assertSame(
            [['cmid' => (int) $page->cmid, 'name' => 'Intro', 'instance' => (int) $page->id, 'modname' => 'page']],
            $section['activities']
        );
    }

    /**
     * origin_get_course_detail rejects unknown users and unknown courses.
     */
    public function test_origin_get_course_detail_rejected(): void {
        $returns = $this->returns(origin_course_external::class, 'origin_get_course_detail');

        $res = origin_course_external::origin_get_course_detail('username', 'nobody', (int) $this->courses['course1']->id);
        $res = external_api::clean_returnvalue($returns, $res);
        $this->assertFalse($res['success']);
        $this->assertSame(17002, $res['errors'][0]['code']);

        $res = origin_course_external::origin_get_course_detail('username', 'teacher1', 999999);
        $res = external_api::clean_returnvalue($returns, $res);
        $this->assertSame(22001, $res['errors'][0]['code']);
    }

    /**
     * origin_get_course_detail should not describe a course the user cannot back up.
     */
    public function test_origin_get_course_detail_requires_capability(): void {
        $returns = $this->returns(origin_course_external::class, 'origin_get_course_detail');

        $res = origin_course_external::origin_get_course_detail('username', 'teacher1', (int) $this->courses['other']->id);
        $res = external_api::clean_returnvalue($returns, $res);
        $this->assertFalse($res['success']);
        $this->assertSame(22002, $res['errors'][0]['code']);
        $this->assertSame('', $res['data']['fullname']);

        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        // A hidden course the user may not see is neither listed nor described (LCT-010).
        $course = $this->courses['course1'];
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_course::instance($course->id);
        assign_capability('moodle/course:viewhiddencourses', CAP_PROHIBIT, $roleid, $context);
        role_assign($roleid, $this->teacher->id, $context);
        course_change_visibility($course->id, false);

        $res = origin_course_external::origin_get_course_detail('username', 'teacher1', (int) $course->id);
        $res = external_api::clean_returnvalue($returns, $res);
        $this->assertSame(22002, $res['errors'][0]['code']);
        $res = origin_course_external::origin_get_courses('username', 'teacher1');
        $res = external_api::clean_returnvalue($this->returns(origin_course_external::class, 'origin_get_courses'), $res);
        $this->assertSame(['course2'], array_column($res['data'], 'shortname'));
    }

    /**
     * origin_get_courses_by_ids (deprecated) keeps only the given courses the user can back up.
     */
    public function test_origin_get_courses_by_ids(): void {
        $this->assertTrue(origin_course_external::origin_get_courses_by_ids_is_deprecated());
        $ids = json_encode([(int) $this->courses['course1']->id, (int) $this->courses['other']->id]);

        $res = origin_course_external::origin_get_courses_by_ids('username', 'teacher1', $ids);
        $res = external_api::clean_returnvalue($this->returns(origin_course_external::class, 'origin_get_courses_by_ids'), $res);

        $this->assertTrue($res['success']);
        $this->assertSame(['course1'], array_column($res['data'], 'shortname'));
        $this->assertSame('Year 2026', $res['data'][0]['categoryname']);
    }

    /**
     * origin_get_courses_by_ids rejects unknown users and reports unknown course ids.
     */
    public function test_origin_get_courses_by_ids_rejected(): void {
        $returns = $this->returns(origin_course_external::class, 'origin_get_courses_by_ids');

        $res = origin_course_external::origin_get_courses_by_ids('username', 'nobody', '[]');
        $res = external_api::clean_returnvalue($returns, $res);
        $this->assertSame('17002', $res['errors'][0]['code']);

        $res = origin_course_external::origin_get_courses_by_ids('username', 'teacher1', '[999999]');
        $res = external_api::clean_returnvalue($returns, $res);
        $this->assertFalse($res['success']);
        $this->assertSame('22011', $res['errors'][0]['code']);
    }
}
