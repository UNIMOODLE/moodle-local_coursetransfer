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
 * Contract tests for the restore wizard services that read the origin or delete a request.
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
use local_coursetransfer\external\frontend\restore_wizard_external;
use local_coursetransfer\task\download_file_course_task;
use required_capability_exception;
use stdClass;

/**
 * Contract tests for restore_wizard_list_origin, _get_sections, _get_category_tree and _delete_request.
 *
 * The origin is answered through Moodle's curl mock: no request leaves the test, and every test
 * checks that the mocked responses were consumed.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\frontend\restore_wizard_external
 */
final class restore_wizard_remote_test extends advanced_testcase {
    /** @var stdClass Origin site registered on this target. */
    private $site;

    /**
     * Register one origin site as admin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->site = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')
            ->create_site('origin', ['host' => 'https://origin.example.com']);
    }

    /**
     * No mocked response may be left behind (it would leak into another test).
     */
    protected function tearDown(): void {
        $property = new \ReflectionProperty(\curl::class, 'mockresponses');
        $this->assertSame([], $property->getValue(), 'Every mocked remote response must be consumed');
        parent::tearDown();
    }

    /**
     * Queue the JSON envelope the origin answers with.
     *
     * @param bool $success
     * @param mixed $data
     * @param array $errors
     * @param array|null $paging
     */
    private function mock_origin(bool $success, $data, array $errors = [], ?array $paging = null): void {
        $envelope = ['success' => $success, 'errors' => $errors, 'data' => $data];
        if ($paging !== null) {
            $envelope['paging'] = $paging;
        }
        \curl::mock_response(json_encode($envelope));
    }

    /**
     * A user without any role.
     */
    private function set_unprivileged_user(): void {
        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * list_origin maps the origin courses to wizard items and computes the pages.
     */
    public function test_list_origin_courses(): void {
        $this->mock_origin(true, [
            ['id' => 11, 'fullname' => 'Maths', 'shortname' => 'M1', 'idnumber' => 'M-1', 'categoryid' => 3,
                'categoryname' => 'Year 2026', 'backupsizeestimated' => '2048'],
        ], [], ['totalcount' => 25, 'page' => 0, 'perpage' => 10]);

        $res = restore_wizard_external::list_origin((int) $this->site->id, 'course', 0, 10, 'math');
        $res = external_api::clean_returnvalue(restore_wizard_external::list_origin_returns(), $res);

        $this->assertTrue($res['success']);
        $this->assertSame(25, $res['total']);
        $this->assertSame(3, $res['pages']);
        $item = $res['items'][0];
        $this->assertSame(11, $item['id']);
        $this->assertSame('Maths', $item['name']);
        $this->assertSame('Year 2026', $item['category']);
        $this->assertSame('https://origin.example.com/course/view.php?id=11', $item['url']);
    }

    /**
     * list_origin for categories counts the courses of the whole subtree.
     */
    public function test_list_origin_categories(): void {
        $this->mock_origin(true, [
            ['id' => 4, 'name' => 'Year 2026', 'idnumber' => 'Y2026', 'parentname' => 'Top', 'totalcourses' => 1,
                'totalsubcategories' => 2, 'totalcourseschild' => 5],
        ]);

        $res = restore_wizard_external::list_origin((int) $this->site->id, 'category', 0, 10, '');
        $res = external_api::clean_returnvalue(restore_wizard_external::list_origin_returns(), $res);

        $item = $res['items'][0];
        $this->assertSame(1, $item['rootcourses']);
        $this->assertSame(4, $item['subcatcourses']);
        $this->assertSame(2, $item['subcats']);
        $this->assertSame(get_string('rw_meta_courses', 'local_coursetransfer', 5), $item['meta']);
        $this->assertSame(1, $res['total']);
        $this->assertSame(1, $res['pages']);
    }

    /**
     * An origin older than 2.2.3 rejects the category search: the whole list is asked again and filtered and
     * paged here (LCT-128).
     */
    public function test_list_origin_categories_search_old_origin(): void {
        // Mocked answers are served last in, first out. Second call: the whole list, without search.
        $this->mock_origin(true, [
            ['id' => 4, 'name' => 'Year 2026', 'parentname' => 'Top', 'totalcourses' => 1],
            ['id' => 5, 'name' => 'Term 1', 'parentname' => 'Year 2026', 'totalcourses' => 2],
            ['id' => 6, 'name' => 'Archive', 'parentname' => 'Top', 'totalcourses' => 3],
        ], [], ['totalcount' => 3, 'page' => 0, 'perpage' => 3]);
        // First call: the Moodle exception an older origin answers for the unknown "search" parameter.
        \curl::mock_response(json_encode([
            'exception' => 'invalid_parameter_exception',
            'errorcode' => 'invalidparameter',
            'message' => 'Invalid parameter value detected',
        ]));

        $res = restore_wizard_external::list_origin((int) $this->site->id, 'category', 1, 1, 'YEAR 2026');
        // The unexpected answer of the first call is logged with debugging().
        $this->resetDebugging();
        $res = external_api::clean_returnvalue(restore_wizard_external::list_origin_returns(), $res);

        $this->assertTrue($res['success']);
        // Both Year 2026 and its subcategory match; page 1 of 1 per page is the second match.
        $this->assertSame(2, $res['total']);
        $this->assertSame(2, $res['pages']);
        $this->assertSame([5], array_column($res['items'], 'id'));
    }

    /**
     * list_origin relays the origin errors and reports an unknown site.
     */
    public function test_list_origin_errors(): void {
        $this->mock_origin(false, [], [['code' => '17002', 'msg' => 'User not found']]);
        $res = restore_wizard_external::list_origin((int) $this->site->id, 'course', 0, 10, '');
        $res = external_api::clean_returnvalue(restore_wizard_external::list_origin_returns(), $res);
        $this->assertFalse($res['success']);
        $this->assertSame([['code' => '17002', 'msg' => 'User not found']], $res['errors']);

        $res = restore_wizard_external::list_origin(999999, 'course', 0, 10, '');
        $res = external_api::clean_returnvalue(restore_wizard_external::list_origin_returns(), $res);
        $this->assertSame('30001', $res['errors'][0]['code']);
    }

    /**
     * list_origin requires local/coursetransfer:origin_restore.
     */
    public function test_list_origin_requires_capability(): void {
        $this->set_unprivileged_user();
        $this->expectException(required_capability_exception::class);
        restore_wizard_external::list_origin((int) $this->site->id, 'course', 0, 10, '');
    }

    /**
     * get_sections returns the origin course sections with their activities.
     */
    public function test_get_sections(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->mock_origin(true, ['id' => 11, 'sections' => [
            ['sectionnum' => 0, 'sectionid' => 100, 'sectionname' => 'General', 'activities' => []],
            ['sectionnum' => 1, 'sectionid' => 101, 'sectionname' => 'Topic 1', 'activities' => [
                ['cmid' => 7, 'name' => 'Intro', 'instance' => 3, 'modname' => 'page'],
            ]],
        ]]);

        $res = restore_wizard_external::get_sections((int) $this->site->id, 11, (int) $course->id);
        $res = external_api::clean_returnvalue(restore_wizard_external::get_sections_returns(), $res);

        $this->assertTrue($res['success']);
        $this->assertCount(2, $res['sections']);
        $this->assertSame('Topic 1', $res['sections'][1]['sectionname']);
        $this->assertSame(
            [['cmid' => 7, 'name' => 'Intro', 'instance' => 3, 'modname' => 'page']],
            $res['sections'][1]['activities']
        );
    }

    /**
     * get_sections relays origin errors.
     */
    public function test_get_sections_errors(): void {
        $this->mock_origin(false, null, [['code' => '22001', 'msg' => 'Course not found']]);
        $res = restore_wizard_external::get_sections((int) $this->site->id, 11);
        $res = external_api::clean_returnvalue(restore_wizard_external::get_sections_returns(), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('22001', $res['errors'][0]['code']);
        $this->assertSame([], $res['sections']);
    }

    /**
     * get_sections requires origin_restore_course in the target course, or origin_restore without one.
     */
    public function test_get_sections_requires_capability(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        try {
            restore_wizard_external::get_sections((int) $this->site->id, 11, (int) $course->id);
            $this->fail('A student must not read the origin sections');
        } catch (required_capability_exception $e) {
            $capability = get_capability_string('local/coursetransfer:origin_restore_course');
            $this->assertStringContainsString($capability, $e->getMessage());
        }

        $this->expectException(required_capability_exception::class);
        restore_wizard_external::get_sections((int) $this->site->id, 11);
    }

    /**
     * get_category_tree passes the origin JSON tree through.
     */
    public function test_get_category_tree(): void {
        $tree = json_encode(['id' => 4, 'name' => 'Year 2026', 'courses' => [], 'categories' => []]);
        $this->mock_origin(true, $tree);

        $res = restore_wizard_external::get_category_tree((int) $this->site->id, 4);
        $res = external_api::clean_returnvalue(restore_wizard_external::get_category_tree_returns(), $res);

        $this->assertTrue($res['success']);
        $this->assertSame($tree, $res['tree']);
    }

    /**
     * get_category_tree relays origin errors and reports an unknown site.
     */
    public function test_get_category_tree_errors(): void {
        $this->mock_origin(false, null, [['code' => '23001', 'msg' => 'Category not found']]);
        $res = restore_wizard_external::get_category_tree((int) $this->site->id, 4);
        $res = external_api::clean_returnvalue(restore_wizard_external::get_category_tree_returns(), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('23001', $res['errors'][0]['code']);

        $res = restore_wizard_external::get_category_tree(999999, 4);
        $res = external_api::clean_returnvalue(restore_wizard_external::get_category_tree_returns(), $res);
        $this->assertSame('30601', $res['errors'][0]['code']);
    }

    /**
     * get_category_tree requires local/coursetransfer:origin_restore.
     */
    public function test_get_category_tree_requires_capability(): void {
        $this->set_unprivileged_user();
        $this->expectException(required_capability_exception::class);
        restore_wizard_external::get_category_tree((int) $this->site->id, 4);
    }

    /**
     * delete_request removes a request in any status, and nothing else; its queued task ends by itself (LCT-126).
     */
    public function test_delete_request(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');

        // A task of the request running right now holds the deletion: it would go on writing after it.
        $busy = $generator->create_request(['status' => coursetransfer_request::STATUS_DOWNLOAD]);
        $task = new download_file_course_task();
        $task->set_custom_data(['requestid' => (int) $busy->id, 'fileurl' => 'https://origin.example.com/x.mbz']);
        $taskid = manager::queue_adhoc_task($task);
        $DB->set_field('task_adhoc', 'timestarted', time(), ['id' => $taskid]);
        $res = restore_wizard_external::delete_request((int) $busy->id);
        $res = external_api::clean_returnvalue(restore_wizard_external::delete_request_returns(), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('30703', $res['errors'][0]['code']);
        $taskname = get_string('task_download_file_course', 'local_coursetransfer');
        $this->assertStringContainsString($taskname, $res['errors'][0]['msg']);
        $this->assertTrue($DB->record_exists(coursetransfer_request::TABLE, ['id' => $busy->id]));

        // Once it is not running, the request in progress is deleted; its queued task ends without doing anything.
        $DB->set_field('task_adhoc', 'timestarted', null, ['id' => $taskid]);
        $res = restore_wizard_external::delete_request((int) $busy->id);
        $res = external_api::clean_returnvalue(restore_wizard_external::delete_request_returns(), $res);
        $this->assertTrue($res['success']);
        $this->assertFalse($DB->record_exists(coursetransfer_request::TABLE, ['id' => $busy->id]));
        ob_start();
        $this->runAdhocTasks(download_file_course_task::class);
        ob_end_clean();
        $this->assertCount(0, manager::get_adhoc_tasks(download_file_course_task::class));

        // A kept safety copy holds it: the cleanup would purge the copy as orphaned.
        $kept = $generator->create_request(['status' => coursetransfer_request::STATUS_COMPLETED]);
        get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
            'component' => 'local_coursetransfer', 'filearea' => 'safetycopy', 'itemid' => (int) $kept->id,
            'filepath' => '/', 'filename' => 'backup.mbz'], 'copy');
        $res = restore_wizard_external::delete_request((int) $kept->id);
        $res = external_api::clean_returnvalue(restore_wizard_external::delete_request_returns(), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('30704', $res['errors'][0]['code']);
        $this->assertTrue($DB->record_exists(coursetransfer_request::TABLE, ['id' => $kept->id]));

        // A finished one is deleted, and only that one.
        $request = $generator->create_request(['status' => coursetransfer_request::STATUS_COMPLETED]);
        $other = $generator->create_request(['status' => coursetransfer_request::STATUS_COMPLETED]);
        $res = restore_wizard_external::delete_request((int) $request->id);
        $res = external_api::clean_returnvalue(restore_wizard_external::delete_request_returns(), $res);
        $this->assertTrue($res['success']);
        $this->assertFalse($DB->record_exists(coursetransfer_request::TABLE, ['id' => $request->id]));
        $this->assertTrue($DB->record_exists(coursetransfer_request::TABLE, ['id' => $other->id]));
    }

    /**
     * delete_request requires local/coursetransfer:view_logs.
     */
    public function test_delete_request_requires_capability(): void {
        global $DB;
        $request = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request();
        $this->set_unprivileged_user();

        try {
            restore_wizard_external::delete_request((int) $request->id);
            $this->fail('A user without view_logs must not delete requests');
        } catch (required_capability_exception $e) {
            $this->assertTrue($DB->record_exists(coursetransfer_request::TABLE, ['id' => $request->id]));
        }
    }
}
