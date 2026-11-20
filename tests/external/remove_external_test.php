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
 * Contract tests for the origin remove web services.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external;

use advanced_testcase;
use core\task\manager;
use core_external\external_api;
use core_external\external_description;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\external\backend\remove_external;
use local_coursetransfer\task\remove_category_task;
use local_coursetransfer\task\remove_course_task;

/**
 * Contract tests for local_coursetransfer_origin_remove_course and _origin_remove_category.
 *
 * The services only record the request and queue the removal task: nothing is deleted until the
 * task runs (covered in tests/task).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\backend\remove_external
 */
final class remove_external_test extends advanced_testcase {
    /** @var string Host of the target site registered on this origin. */
    private const TARGET = 'https://target.example.com';

    /**
     * Register the target site as admin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')
            ->create_site('target', ['host' => self::TARGET]);
    }

    /**
     * Return description of a method.
     *
     * Its errors and data structures pass PARAM_TEXT and a label as description and $required,
     * which raises a developer debugging() message when the description is built (plugin bug,
     * reported in TIPCOUTRAN-10). It is discarded here so the contract itself can be checked.
     *
     * @param string $method
     * @return external_description
     */
    private function returns(string $method): external_description {
        $description = call_user_func([remove_external::class, $method . '_returns']);
        $this->resetDebugging();
        return $description;
    }

    /**
     * Call origin_remove_course and clean the result.
     *
     * @param string $username
     * @param int $courseid
     * @param int $nextruntime
     * @param string $targetsite
     * @return array
     */
    private function remove_course(
        string $username,
        int $courseid,
        int $nextruntime = 0,
        string $targetsite = self::TARGET
    ): array {
        $res = remove_external::origin_remove_course('username', $username, $courseid, 7, $targetsite, $nextruntime);
        return external_api::clean_returnvalue($this->returns('origin_remove_course'), $res);
    }

    /**
     * Call origin_remove_category and clean the result.
     *
     * @param string $username
     * @param int $catid
     * @param int $nextruntime
     * @return array
     */
    private function remove_category(string $username, int $catid, int $nextruntime = 0): array {
        $res = remove_external::origin_remove_category('username', $username, $catid, 8, self::TARGET, $nextruntime);
        return external_api::clean_returnvalue($this->returns('origin_remove_category'), $res);
    }

    /**
     * A valid course removal records the request and queues the task, without deleting the course yet.
     */
    public function test_origin_remove_course(): void {
        global $DB;
        $category = $this->getDataGenerator()->create_category(['idnumber' => 'CAT1']);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id, 'shortname' => 'RC1']);
        $when = time() + DAYSECS;

        $res = $this->remove_course('admin', (int) $course->id, $when);

        $this->assertTrue($res['success']);
        $this->assertSame([], $res['errors']);
        $this->assertSame(7, $res['data']['requestid']);
        $this->assertSame('RC1', $res['data']['course_shortname']);
        $this->assertSame('CAT1', $res['data']['course_category_idnumber']);
        $this->assertTrue($DB->record_exists('course', ['id' => $course->id]));

        $request = coursetransfer_request::get($res['data']['request_origin_id']);
        $this->assertEquals(coursetransfer_request::TYPE_REMOVE_COURSE, $request->type);
        $this->assertEquals(coursetransfer_request::STATUS_IN_PROGRESS, $request->status);
        $this->assertEquals($when, $request->origin_schedule_datetime);

        $tasks = manager::get_adhoc_tasks(remove_course_task::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertEquals($when, $task->get_next_run_time());
        $this->assertEquals($course->id, $task->get_custom_data()->courseid);
        $this->assertEquals(7, $task->get_custom_data()->requestdestid);
    }

    /**
     * A teacher without course:delete and origin_remove_course gets 16011 and nothing is queued.
     */
    public function test_origin_remove_course_without_capability(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $res = $this->remove_course($teacher->username, (int) $course->id);

        $this->assertFalse($res['success']);
        $this->assertSame('16011', $res['errors'][0]['code']);
        $this->assertCount(0, manager::get_adhoc_tasks(remove_course_task::class));
    }

    /**
     * Unknown users, unknown target sites and unknown courses are rejected.
     */
    public function test_origin_remove_course_rejected(): void {
        $course = $this->getDataGenerator()->create_course();

        $this->assertSame('17002', $this->remove_course('nobody', (int) $course->id)['errors'][0]['code']);
        $this->assertSame(
            '18001',
            $this->remove_course('admin', (int) $course->id, 0, 'https://other.example.com')['errors'][0]['code']
        );
        $this->assertSame('16010', $this->remove_course('admin', 999999)['errors'][0]['code']);
        $this->assertCount(0, manager::get_adhoc_tasks(remove_course_task::class));
    }

    /**
     * A valid category removal records the request and queues the task to run now.
     */
    public function test_origin_remove_category(): void {
        global $DB;
        $category = $this->getDataGenerator()->create_category(['name' => 'Old year', 'idnumber' => 'Y2020']);
        $this->getDataGenerator()->create_course(['category' => $category->id]);

        $res = $this->remove_category('admin', (int) $category->id);

        $this->assertTrue($res['success']);
        $this->assertSame('Old year', $res['data']['course_category_name']);
        $this->assertSame('Y2020', $res['data']['course_category_idnumber']);
        $this->assertTrue($DB->record_exists('course_categories', ['id' => $category->id]));

        $request = coursetransfer_request::get($res['data']['request_origin_id']);
        $this->assertEquals(coursetransfer_request::TYPE_REMOVE_CATEGORY, $request->type);
        $this->assertNull($request->origin_schedule_datetime);

        $tasks = manager::get_adhoc_tasks(remove_category_task::class);
        $this->assertCount(1, $tasks);
        $this->assertEquals($category->id, reset($tasks)->get_custom_data()->catid);
    }

    /**
     * A user without category:manage and origin_remove_category gets 16001; unknown categories 16000.
     */
    public function test_origin_remove_category_rejected(): void {
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->assertSame('16001', $this->remove_category($teacher->username, (int) $category->id)['errors'][0]['code']);
        $this->assertSame('16000', $this->remove_category('admin', 999999)['errors'][0]['code']);
        $this->assertSame('17002', $this->remove_category('nobody', (int) $category->id)['errors'][0]['code']);
        $this->assertCount(0, manager::get_adhoc_tasks(remove_category_task::class));
    }
}
