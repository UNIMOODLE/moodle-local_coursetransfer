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
 * Contract tests for the origin backup web services.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external;

use advanced_testcase;
use core\task\manager;
use core_external\external_api;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\external\backend\origin_course_backup_external;
use local_coursetransfer\task\create_backup_course_task;
use stdClass;

/**
 * Contract tests for local_coursetransfer_origin_backup_course and _origin_backup_category_course.
 *
 * Both services run on the origin site: the target asks for a backup and the origin queues it.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\backend\origin_course_backup_external
 */
final class origin_course_backup_external_test extends advanced_testcase {
    /** @var string Host of the target site registered on this origin. */
    private const TARGET = 'https://target.example.com';

    /**
     * Register the target site and return a small course to back up.
     *
     * @return stdClass the course
     */
    private function setup_origin(): stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')
            ->create_site('target', ['host' => self::TARGET]);
        return $this->getDataGenerator()->create_course(['fullname' => 'Origin course', 'shortname' => 'OC1']);
    }

    /**
     * Default restore configuration as sent by the target.
     *
     * @return array
     */
    private function configuration(): array {
        return [
            'target_target' => \backup::TARGET_NEW_COURSE,
            'target_remove_enrols' => false,
            'target_remove_groups' => false,
        ];
    }

    /**
     * Call the service and clean its return value against the declared structure.
     *
     * @param string $value username that identifies the requesting user
     * @param int $courseid
     * @param string $targetsite
     * @return array
     */
    private function call(string $value, int $courseid, string $targetsite = self::TARGET): array {
        $res = origin_course_backup_external::origin_backup_course(
            'username',
            $value,
            $courseid,
            99,
            42,
            $targetsite,
            $this->configuration()
        );
        return external_api::clean_returnvalue($this->returns(), $res);
    }

    /**
     * Return description of the service.
     *
     * Its errors and data structures pass PARAM_TEXT and a label as description and $required,
     * which raises a developer debugging() message when the description is built (plugin bug,
     * reported in TIPCOUTRAN-10). It is discarded here so the contract itself can be checked.
     *
     * @return \core_external\external_description
     */
    private function returns(): \core_external\external_description {
        $description = origin_course_backup_external::origin_backup_course_returns();
        $this->resetDebugging();
        return $description;
    }

    /**
     * A valid request creates the response request and queues the asynchronous backup.
     */
    public function test_origin_backup_course(): void {
        $course = $this->setup_origin();

        $res = $this->call('admin', (int) $course->id);

        $this->assertTrue($res['success']);
        $this->assertSame([], $res['errors']);
        $this->assertSame(42, $res['data']['requestid']);
        $this->assertSame('Origin course', $res['data']['course_fullname']);
        $this->assertSame('OC1', $res['data']['course_shortname']);
        $this->assertEquals($course->category, $res['data']['course_category_id']);

        $request = coursetransfer_request::get($res['data']['request_origin_id']);
        $this->assertEquals(coursetransfer_request::DIRECTION_RESPONSE, $request->direction);
        $this->assertEquals(coursetransfer_request::STATUS_IN_PROGRESS, $request->status);
        $this->assertEquals(42, $request->target_request_id);
        $this->assertEquals(99, $request->target_course_id);
        $this->assertSame(self::TARGET, $request->siteurl);

        $tasks = manager::get_adhoc_tasks(create_backup_course_task::class);
        $this->assertCount(1, $tasks);
        $data = reset($tasks)->get_custom_data();
        $this->assertEquals($request->id, $data->requestoriginid);
        $this->assertEquals(42, $data->requestid);
    }

    /**
     * The response request keeps the course idnumber and its category idnumber, each in its own column.
     */
    public function test_origin_backup_course_stores_idnumbers(): void {
        $this->setup_origin();
        $category = $this->getDataGenerator()->create_category(['idnumber' => 'CAT-IDN']);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id, 'idnumber' => 'COURSE-IDN']);

        $res = $this->call('admin', (int) $course->id);
        $this->assertTrue($res['success']);

        $request = coursetransfer_request::get($res['data']['request_origin_id']);
        $this->assertSame('CAT-IDN', $request->origin_category_idnumber);
        $this->assertSame('COURSE-IDN', $request->origin_course_idnumber);
    }

    /**
     * The category service maps to the same method and queues the same backup.
     */
    public function test_origin_backup_category_course(): void {
        $course = $this->setup_origin();

        $info = external_api::external_function_info('local_coursetransfer_origin_backup_category_course');
        $this->assertSame(origin_course_backup_external::class, $info->classname);
        $this->assertSame('origin_backup_course', $info->methodname);

        $res = $this->call('admin', (int) $course->id);
        $this->assertTrue($res['success']);
        $this->assertCount(1, manager::get_adhoc_tasks(create_backup_course_task::class));
    }

    /**
     * A user who can back up some course, but not the requested one, gets 10102 and nothing is queued.
     */
    public function test_origin_backup_course_without_capability(): void {
        $course = $this->setup_origin();
        $teacher = $this->getDataGenerator()->create_user();
        $own = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $own->id, 'editingteacher');

        $res = $this->call($teacher->username, (int) $course->id);

        $this->assertFalse($res['success']);
        $this->assertSame('10102', $res['errors'][0]['code']);
        $this->assertArrayNotHasKey('request_origin_id', array_filter($res['data']));
        $this->assertCount(0, manager::get_adhoc_tasks(create_backup_course_task::class));
    }

    /**
     * Unknown users, users without courses and unknown target sites are rejected.
     */
    public function test_origin_backup_course_rejected(): void {
        $course = $this->setup_origin();
        $nocourses = $this->getDataGenerator()->create_user();

        $this->assertSame('17002', $this->call('nobody', (int) $course->id)['errors'][0]['code']);
        $this->assertSame('17001', $this->call($nocourses->username, (int) $course->id)['errors'][0]['code']);
        $this->assertSame('18001', $this->call('admin', (int) $course->id, 'https://other.example.com')['errors'][0]['code']);
        $this->assertSame('10010', $this->call('admin', 999999)['errors'][0]['code']);
        $this->assertCount(0, manager::get_adhoc_tasks(create_backup_course_task::class));
    }

    /**
     * The queued backup runs and reports completion to the target (mocked, no network).
     *
     * @group local_coursetransfer_slow
     */
    public function test_origin_backup_course_task_completes(): void {
        $course = $this->setup_origin();
        $res = $this->call('admin', (int) $course->id);

        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => ['id' => 42]]));
        $tasks = manager::get_adhoc_tasks(create_backup_course_task::class);
        ob_start();
        reset($tasks)->execute();
        ob_end_clean();

        $request = coursetransfer_request::get($res['data']['request_origin_id']);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $request->status);
        $this->assertNotEmpty($request->origin_backup_url);
        $this->assertGreaterThan(0, (int) $request->origin_backup_size);
    }
}
