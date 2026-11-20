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
 * Tests for the origin category removal task.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use advanced_testcase;
use core\task\manager;
use local_coursetransfer\coursetransfer_remove;
use local_coursetransfer\coursetransfer_request;
use stdClass;

/**
 * Tests for remove_category_task.
 *
 * The callbacks to the target go through Moodle's curl mock: no request leaves the test.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\task\remove_category_task
 * @covers     \local_coursetransfer\coursetransfer_remove
 */
final class remove_category_task_test extends advanced_testcase {
    /** @var stdClass Category to remove. */
    private $category;

    /** @var stdClass Response request on this origin. */
    private $request;

    /** @var stdClass Target site. */
    private $site;

    /**
     * A category with a subcategory and a course in each, and its response request.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        // The recycle bin would back up every course before deleting it; it is not under test here.
        set_config('categorybinenable', 0, 'tool_recyclebin');

        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $this->site = $generator->create_site('target', ['host' => 'https://target.example.com']);
        $this->category = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $this->category->id]);
        $this->getDataGenerator()->create_course(['category' => $this->category->id]);
        $this->getDataGenerator()->create_course(['category' => $child->id]);
        $this->request = $generator->create_request([
            'type' => coursetransfer_request::TYPE_REMOVE_CATEGORY,
            'direction' => coursetransfer_request::DIRECTION_RESPONSE,
            'siteurl' => $this->site->host,
            'status' => coursetransfer_request::STATUS_IN_PROGRESS,
            'origin_category_id' => $this->category->id,
        ]);
    }

    /**
     * No mocked response may be left behind (it would leak into another test).
     */
    protected function tearDown(): void {
        $property = new \ReflectionProperty(\curl::class, 'mockresponses');
        $left = $property->getValue();
        $property->setValue(null, []);
        parent::tearDown();
        $this->assertSame([], $left, 'Every mocked remote response must be consumed');
    }

    /**
     * Queue the removal of a category and run it.
     *
     * @param int $catid
     */
    private function run_removal(int $catid): void {
        $adminid = (int) get_admin()->id;
        coursetransfer_remove::create_task_remove_category((int) $this->request->id, 88, $catid, $this->site, $adminid);
        $tasks = manager::get_adhoc_tasks(remove_category_task::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertEquals(88, $task->get_custom_data()->requestdestid);
        ob_start();
        $task->execute();
        ob_end_clean();
    }

    /**
     * A scheduled removal is queued for its run time.
     */
    public function test_create_task_remove_category_scheduled(): void {
        $when = time() + DAYSECS;
        coursetransfer_remove::create_task_remove_category(1, 2, (int) $this->category->id, $this->site, 3, $when);
        $tasks = manager::get_adhoc_tasks(remove_category_task::class);
        $this->assertEquals($when, reset($tasks)->get_next_run_time());
    }

    /**
     * The category tree is deleted, the request completed, the target notified and the bin cleanup queued.
     */
    public function test_remove_category(): void {
        global $DB;
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => ['id' => 88]]));

        $this->run_removal((int) $this->category->id);

        $this->assertFalse($DB->record_exists('course_categories', ['id' => $this->category->id]));
        $this->assertFalse($DB->record_exists('course_categories', ['parent' => $this->category->id]));
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($this->request->id)->status);
        $cleanups = manager::get_adhoc_tasks(cleanup_category_bin_task::class);
        $this->assertCount(1, $cleanups);
        $this->assertEquals($this->category->id, reset($cleanups)->get_custom_data()->categoryid);
    }

    /**
     * If the category cannot be deleted, the request records 19001 and the target is told.
     */
    public function test_remove_category_failed(): void {
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => ['id' => 88]]));

        $this->run_removal(999999);

        $request = coursetransfer_request::get($this->request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertSame('19001', $request->error_code);
        $this->assertCount(0, manager::get_adhoc_tasks(cleanup_category_bin_task::class));
    }

    /**
     * If the target rejects the completed callback, the request records the target's error.
     */
    public function test_remove_category_callback_failed(): void {
        \curl::mock_response(json_encode(['success' => false, 'errors' => [['code' => '14022', 'msg' => 'Request id not found']]]));

        $this->run_removal((int) $this->category->id);

        $request = coursetransfer_request::get($this->request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertSame('14022', $request->error_code);
    }

    /**
     * The removal runs with the capabilities of the user who asked for it, not the cron's.
     */
    public function test_remove_category_requester_without_capability(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        coursetransfer_remove::create_task_remove_category(
            (int) $this->request->id,
            88,
            (int) $this->category->id,
            $this->site,
            (int) $user->id
        );
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => ['id' => 88]]));
        $tasks = manager::get_adhoc_tasks(remove_category_task::class);
        ob_start();
        reset($tasks)->execute();
        ob_end_clean();

        $this->assertTrue($DB->record_exists('course_categories', ['id' => $this->category->id]));
        $request = coursetransfer_request::get($this->request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertSame('19002', $request->error_code);
    }

    /**
     * A task whose request was deleted ends without removing anything (LCT-036).
     */
    public function test_remove_category_request_deleted(): void {
        global $DB;
        $DB->delete_records('local_coursetransfer_request', ['id' => $this->request->id]);

        $this->run_removal((int) $this->category->id);

        $this->assertTrue($DB->record_exists('course_categories', ['id' => $this->category->id]));
    }
}
