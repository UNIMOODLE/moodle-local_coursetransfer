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
 * Tests for the scheduled cleanup of failing adhoc tasks.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use advanced_testcase;
use core\task\manager;
use local_coursetransfer\coursetransfer_request;
use stdClass;

/**
 * Tests for clean_adhoc_failed_task.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\task\clean_adhoc_failed_task
 */
final class clean_adhoc_failed_task_test extends advanced_testcase {
    /**
     * Act as admin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Queue a download task for a request and give it the failure delay of a task that keeps failing.
     *
     * @param stdClass $request
     * @param int $faildelay
     */
    private function queue_failing_task(stdClass $request, int $faildelay): void {
        global $DB;
        $task = new download_file_course_task();
        $task->set_custom_data(['requestid' => (int) $request->id, 'fileurl' => 'https://origin.example.com/b.mbz']);
        $taskid = manager::queue_adhoc_task($task);
        $DB->set_field('task_adhoc', 'faildelay', $faildelay, ['id' => $taskid]);
    }

    /**
     * Run the scheduled task.
     */
    private function run_task(): void {
        ob_start();
        (new clean_adhoc_failed_task())->execute();
        ob_end_clean();
    }

    /**
     * Tasks failing for longer than the threshold are removed and their open requests marked as failed.
     */
    public function test_clean_failed_tasks(): void {
        set_config('clean_adhoc_faildelay', 1800, 'local_coursetransfer');
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $stuck = $generator->create_request(['status' => coursetransfer_request::STATUS_DOWNLOAD]);
        $completed = $generator->create_request(['status' => coursetransfer_request::STATUS_COMPLETED]);
        $recent = $generator->create_request(['status' => coursetransfer_request::STATUS_DOWNLOAD]);
        $this->queue_failing_task($stuck, 3600);
        $this->queue_failing_task($completed, 3600);
        $this->queue_failing_task($recent, 60);

        $this->run_task();

        $left = manager::get_adhoc_tasks(download_file_course_task::class);
        $this->assertSame([(int) $recent->id], array_map(fn($t) => (int) $t->get_custom_data()->requestid, array_values($left)));

        $stuck = coursetransfer_request::get($stuck->id);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $stuck->status);
        $this->assertStringContainsString('clean_adhoc_failed_task', $stuck->error_message);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::get($completed->id)->status);
        $this->assertEquals(coursetransfer_request::STATUS_DOWNLOAD, coursetransfer_request::get($recent->id)->status);
    }

    /**
     * The threshold is configurable, and 0 disables the cleanup.
     */
    public function test_clean_failed_tasks_threshold(): void {
        $request = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')
            ->create_request(['status' => coursetransfer_request::STATUS_DOWNLOAD]);
        $this->queue_failing_task($request, 600);

        set_config('clean_adhoc_faildelay', 0, 'local_coursetransfer');
        $this->run_task();
        $this->assertCount(1, manager::get_adhoc_tasks(download_file_course_task::class));

        set_config('clean_adhoc_faildelay', 900, 'local_coursetransfer');
        $this->run_task();
        $this->assertCount(1, manager::get_adhoc_tasks(download_file_course_task::class));

        set_config('clean_adhoc_faildelay', 300, 'local_coursetransfer');
        $this->run_task();
        $this->assertCount(0, manager::get_adhoc_tasks(download_file_course_task::class));
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, coursetransfer_request::get($request->id)->status);
    }

    /**
     * The task has a translated name.
     */
    public function test_get_name(): void {
        $expected = get_string('clean_adhoc_failed_task', 'local_coursetransfer');
        $this->assertSame($expected, (new clean_adhoc_failed_task())->get_name());
    }

    /**
     * Core caps the fail delay at one day: the default threshold (one day) must still clean (N1).
     */
    public function test_clean_at_core_maximum_delay(): void {
        set_config('clean_adhoc_faildelay', DAYSECS, 'local_coursetransfer');
        $request = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')
            ->create_request(['status' => coursetransfer_request::STATUS_DOWNLOAD]);
        $this->queue_failing_task($request, DAYSECS);

        $this->run_task();

        $this->assertCount(0, manager::get_adhoc_tasks(download_file_course_task::class));
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, coursetransfer_request::get($request->id)->status);
    }

    /**
     * Tasks with no attempts left never run again and are cleaned; running tasks are kept.
     */
    public function test_clean_exhausted_and_running(): void {
        global $DB;
        set_config('clean_adhoc_faildelay', DAYSECS, 'local_coursetransfer');
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $exhausted = $generator->create_request(['status' => coursetransfer_request::STATUS_DOWNLOAD]);
        $running = $generator->create_request(['status' => coursetransfer_request::STATUS_DOWNLOAD]);
        $this->queue_failing_task($exhausted, 600);
        $this->queue_failing_task($running, DAYSECS);
        $DB->set_field_select(
            'task_adhoc',
            'attemptsavailable',
            0,
            $DB->sql_like('customdata', ':n'),
            ['n' => '%"requestid":' . $exhausted->id . ',%']
        );
        $DB->set_field_select(
            'task_adhoc',
            'timestarted',
            time(),
            $DB->sql_like('customdata', ':n'),
            ['n' => '%"requestid":' . $running->id . ',%']
        );

        $this->run_task();

        $left = manager::get_adhoc_tasks(download_file_course_task::class);
        $this->assertSame([(int) $running->id], array_map(fn($t) => (int) $t->get_custom_data()->requestid, array_values($left)));
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, coursetransfer_request::get($exhausted->id)->status);
        $this->assertEquals(coursetransfer_request::STATUS_DOWNLOAD, coursetransfer_request::get($running->id)->status);
    }

    /**
     * A backup task carries the target's request id too: only the local request is marked (N4).
     */
    public function test_backup_task_marks_local_request(): void {
        global $DB;
        set_config('clean_adhoc_faildelay', 300, 'local_coursetransfer');
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $local = $generator->create_request(['status' => coursetransfer_request::STATUS_IN_PROGRESS,
            'direction' => coursetransfer_request::DIRECTION_RESPONSE]);
        $unrelated = $generator->create_request(['status' => coursetransfer_request::STATUS_DOWNLOAD]);
        $task = new create_backup_course_task();
        $task->set_custom_data(['backupid' => 'x', 'targetsite' => 1, 'requestid' => (int) $unrelated->id,
            'requestoriginid' => (int) $local->id, 'istest' => false]);
        $taskid = manager::queue_adhoc_task($task);
        $DB->set_field('task_adhoc', 'faildelay', 600, ['id' => $taskid]);

        $this->run_task();

        $this->assertEquals(coursetransfer_request::STATUS_ERROR, coursetransfer_request::get($local->id)->status);
        $this->assertEquals(coursetransfer_request::STATUS_DOWNLOAD, coursetransfer_request::get($unrelated->id)->status);
    }

    /**
     * The task data (which may hold a token) is not written to the cron log.
     */
    public function test_log_has_no_custom_data(): void {
        global $DB;
        set_config('clean_adhoc_faildelay', 300, 'local_coursetransfer');
        $request = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')
            ->create_request(['status' => coursetransfer_request::STATUS_DOWNLOAD]);
        $task = new download_file_course_task();
        $task->set_custom_data(['requestid' => (int) $request->id, 'fileurl' => 'https://o.example.com/b.mbz?token=SECRET42']);
        $taskid = manager::queue_adhoc_task($task);
        $DB->set_field('task_adhoc', 'faildelay', 600, ['id' => $taskid]);

        ob_start();
        (new clean_adhoc_failed_task())->execute();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('SECRET42', $output);
        $this->assertStringContainsString((string) $taskid, $output);
    }
}
