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
 * Tests for the origin backup task.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use advanced_testcase;
use core\task\manager;
use local_coursetransfer\coursetransfer_backup;
use local_coursetransfer\coursetransfer_request;
use stdClass;

/**
 * Tests for create_backup_course_task: the origin request always ends in a final state.
 *
 * Callbacks to the target go through Moodle's curl mock: no request leaves the test.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\task\create_backup_course_task
 * @covers     \local_coursetransfer\coursetransfer::create_backupfile_url
 */
final class create_backup_course_task_test extends advanced_testcase {
    /** @var stdClass Target site. */
    private $site;

    /** @var stdClass Course to back up. */
    private $course;

    /**
     * Act as admin with a registered target.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->site = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')
            ->create_site('target', ['host' => 'https://target.example.com']);
        $this->course = $this->getDataGenerator()->create_course();
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
     * Queue the backup as origin_backup_course does and run it.
     *
     * @param int $requestoriginid
     */
    private function run_backup(int $requestoriginid): void {
        $this->assertTrue(coursetransfer_backup::create_task_backup_course(
            (int) $this->course->id,
            (int) get_admin()->id,
            $this->site,
            77,
            $requestoriginid,
            [],
            0,
            null,
            false
        ));
        foreach (manager::get_adhoc_tasks(create_backup_course_task::class) as $task) {
            ob_start();
            $task->execute();
            ob_end_clean();
        }
    }

    /**
     * Response request of this origin.
     *
     * @return stdClass
     */
    private function create_request(): stdClass {
        return $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request([
            'direction' => coursetransfer_request::DIRECTION_RESPONSE,
            'siteurl' => $this->site->host,
            'status' => coursetransfer_request::STATUS_IN_PROGRESS,
            'origin_course_id' => $this->course->id,
            'target_request_id' => 77,
        ]);
    }

    /**
     * If the backup file cannot be published, the origin request fails even though the target was told (LCT-049).
     */
    public function test_backup_file_rejected_marks_error(): void {
        // A backup over the limit (0 is no limit): 2 MB of incompressible content against a 1 MB limit.
        set_config('target_restore_course_max_size', 1, 'local_coursetransfer');
        $request = $this->create_request();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance((int) $request->origin_course_id)->id, 'component' => 'course',
            'filearea' => 'summary', 'itemid' => 0, 'filepath' => '/', 'filename' => 'big.bin',
        ], random_bytes(2 * 1024 * 1024));
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => ['id' => 77]]));

        $this->run_backup((int) $request->id);

        $request = coursetransfer_request::get($request->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $request->status);
        $this->assertNotEmpty($request->error_message);
        $this->assertCount(0, get_file_storage()->get_area_files(
            \context_course::instance($this->course->id)->id,
            'local_coursetransfer',
            'backup',
            $request->id,
            'id',
            false
        ), 'A rejected backup is not kept');
    }

    /**
     * A backup for a request that no longer exists tells the target and ends cleanly (LCT-036).
     */
    public function test_missing_request(): void {
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => ['id' => 77]]));

        $this->run_backup(999999);

        $this->assertCount(0, get_file_storage()->get_area_files(
            \context_course::instance($this->course->id)->id,
            'local_coursetransfer',
            'backup',
            false,
            'id',
            false
        ));
    }

    /**
     * A completed request is not backed up again by a retry.
     */
    public function test_completed_request_is_not_rerun(): void {
        global $DB;
        $request = $this->create_request();
        $DB->set_field('local_coursetransfer_request', 'status', coursetransfer_request::STATUS_COMPLETED, ['id' => $request->id]);
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => ['id' => 77]]));

        $this->run_backup((int) $request->id);

        $property = new \ReflectionProperty(\curl::class, 'mockresponses');
        $this->assertCount(1, $property->getValue(), 'The target is not called again');
        $property->setValue(null, []);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($request->id)->status);
    }
}
