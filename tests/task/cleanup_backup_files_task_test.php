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
 * Tests for the scheduled cleanup of the backups served by the origin.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use advanced_testcase;
use local_coursetransfer\coursetransfer_request;
use stdClass;

/**
 * Tests for cleanup_backup_files_task.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\task\cleanup_backup_files_task
 * @covers     \local_coursetransfer\coursetransfer_remove::has_pending_removal
 * @covers     \local_coursetransfer\coursetransfer_remove::preserve_served_backups
 */
final class cleanup_backup_files_task_test extends advanced_testcase {
    /** @var stdClass Origin course. */
    private $course;

    /**
     * Act as admin with a course whose backups the origin serves.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * A response request with its backup file.
     *
     * @param int $status
     * @param int $age Days since the request last changed.
     * @return stdClass
     */
    private function served_backup(int $status, int $age): stdClass {
        $request = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request([
            'direction' => coursetransfer_request::DIRECTION_RESPONSE,
            'status' => $status,
            'origin_course_id' => $this->course->id,
            'timemodified' => time() - $age * DAYSECS,
        ]);
        $this->store_file((int) $request->id, $age);
        return $request;
    }

    /**
     * Store a backup in the area the origin serves.
     *
     * @param int $itemid
     * @param int $age Days since it was created.
     */
    private function store_file(int $itemid, int $age): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($this->course->id)->id, 'component' => 'local_coursetransfer',
            'filearea' => 'backup', 'itemid' => $itemid, 'filepath' => '/', 'filename' => 'backup.mbz',
            'timecreated' => time() - $age * DAYSECS,
        ], 'x');
    }

    /**
     * Whether the backup of an item is still there.
     *
     * @param int $itemid
     * @return bool
     */
    private function has_file(int $itemid): bool {
        return (bool) get_file_storage()->get_area_files(
            \context_course::instance($this->course->id)->id,
            'local_coursetransfer',
            'backup',
            $itemid,
            'id',
            false
        );
    }

    /**
     * Run the scheduled task.
     */
    private function run_task(): void {
        ob_start();
        (new cleanup_backup_files_task())->execute();
        ob_end_clean();
    }

    /**
     * Finished requests past the retention lose their backup; running or recent ones keep it.
     */
    public function test_cleanup(): void {
        $completed = $this->served_backup(coursetransfer_request::STATUS_COMPLETED, 10);
        $failed = $this->served_backup(coursetransfer_request::STATUS_ERROR, 10);
        $running = $this->served_backup(coursetransfer_request::STATUS_IN_PROGRESS, 10);
        $recent = $this->served_backup(coursetransfer_request::STATUS_COMPLETED, 2);
        $this->store_file(999999, 10);

        $this->run_task();

        $this->assertFalse($this->has_file((int) $completed->id));
        $this->assertFalse($this->has_file((int) $failed->id));
        $this->assertTrue($this->has_file((int) $running->id));
        $this->assertTrue($this->has_file((int) $recent->id));
        $this->assertFalse($this->has_file(999999), 'A backup whose request was deleted is removed too');
    }

    /**
     * The retention is configurable, with a minimum of one day.
     */
    public function test_retention(): void {
        $request = $this->served_backup(coursetransfer_request::STATUS_COMPLETED, 2);
        set_config('backup_files_retention', 3, 'local_coursetransfer');
        $this->run_task();
        $this->assertTrue($this->has_file((int) $request->id));

        set_config('backup_files_retention', 0, 'local_coursetransfer');
        $this->run_task();
        $this->assertFalse($this->has_file((int) $request->id));
    }

    /**
     * The task has a translated name.
     */
    public function test_get_name(): void {
        $expected = get_string('cleanup_backup_files_task', 'local_coursetransfer');
        $this->assertSame($expected, (new cleanup_backup_files_task())->get_name());
    }

    /**
     * On the target, a safety copy lasts the recycle bin retention after its request finished.
     */
    public function test_cleanup_safety_copies(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $fs = get_file_storage();
        $copy = function (int $status, int $age) use ($generator, $fs): int {
            $request = $generator->create_request(['status' => $status, 'timemodified' => time() - $age * DAYSECS]);
            $fs->create_file_from_string([
                'contextid' => \context_system::instance()->id, 'component' => 'local_coursetransfer',
                'filearea' => 'safetycopy', 'itemid' => $request->id, 'filepath' => '/', 'filename' => 'b.mbz',
                'timecreated' => time() - $age * DAYSECS,
            ], 'x');
            return (int) $request->id;
        };
        $old = $copy(coursetransfer_request::STATUS_COMPLETED, 40);
        $young = $copy(coursetransfer_request::STATUS_COMPLETED, 10);
        $running = $copy(coursetransfer_request::STATUS_RESTORE, 40);

        $this->run_task();

        $has = fn(int $id) => (bool) $fs->get_area_files(
            \context_system::instance()->id,
            'local_coursetransfer',
            'safetycopy',
            $id,
            'id',
            false
        );
        $this->assertFalse($has($old));
        $this->assertTrue($has($young), 'Within the 30 days of the recycle bin');
        $this->assertTrue($has($running), 'Its restore is still running');
    }

    /**
     * A safety copy of the target with its age and, optionally, a removal of its origin course.
     *
     * @param array $record Extra fields of the restore request.
     * @param int|null $removalstatus Status of a removal of the origin course (null: none).
     * @param int $removalage Days since that removal changed.
     * @return int Request id.
     */
    private function safety_copy(array $record = [], ?int $removalstatus = null, int $removalage = 0): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $request = $generator->create_request($record + [
            'status' => coursetransfer_request::STATUS_COMPLETED,
            'origin_course_id' => 77,
            'origin_category_id' => 7,
            'timemodified' => time() - 400 * DAYSECS,
        ]);
        if ($removalstatus !== null) {
            $generator->create_request([
                'type' => coursetransfer_request::TYPE_REMOVE_COURSE,
                'status' => $removalstatus,
                'origin_course_id' => 77,
                'timemodified' => time() - $removalage * DAYSECS,
            ]);
        }
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id, 'component' => 'local_coursetransfer',
            'filearea' => 'safetycopy', 'itemid' => $request->id, 'filepath' => '/', 'filename' => 'b.mbz',
            'timecreated' => time() - 400 * DAYSECS,
        ], 'x');
        return (int) $request->id;
    }

    /**
     * Whether the safety copy of a request is still there.
     *
     * @param int $id
     * @return bool
     */
    private function has_copy(int $id): bool {
        return (bool) get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_coursetransfer',
            'safetycopy',
            $id,
            'id',
            false
        );
    }

    /**
     * On the target, no safety copy goes before its origin is removed plus the protection period.
     */
    public function test_safety_copy_waits_for_origin_removal(): void {
        global $DB;
        $pending = $this->safety_copy([], coursetransfer_request::STATUS_IN_PROGRESS, 400);
        $this->run_task();
        $this->assertTrue($this->has_copy($pending), 'Its origin has a removal pending');

        $DB->delete_records('local_coursetransfer_request', ['type' => coursetransfer_request::TYPE_REMOVE_COURSE]);
        $DB->delete_records('files', ['filearea' => 'safetycopy']);
        $recent = $this->safety_copy([], coursetransfer_request::STATUS_COMPLETED, 10);
        $this->run_task();
        $this->assertTrue($this->has_copy($recent), 'Its origin was removed 10 days ago (30 of protection)');

        $DB->delete_records('local_coursetransfer_request', ['type' => coursetransfer_request::TYPE_REMOVE_COURSE]);
        $old = $this->safety_copy([], coursetransfer_request::STATUS_COMPLETED, 40);
        $this->run_task();
        $this->assertFalse($this->has_copy($old), 'Its origin was removed 40 days ago');

        // A removal of another platform's course with the same id does not count.
        $DB->delete_records('local_coursetransfer_request', ['type' => coursetransfer_request::TYPE_REMOVE_COURSE]);
        $other = $this->safety_copy(['siteurl' => 'https://other.example.com'], coursetransfer_request::STATUS_IN_PROGRESS);
        $this->run_task();
        $this->assertFalse($this->has_copy($other));
    }

    /**
     * A copy kept on request (keepsafetycopy) stays until its origin is removed, then the protection period.
     */
    public function test_keepsafetycopy(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $reqcat = $generator->create_request([
            'type' => coursetransfer_request::TYPE_CATEGORY,
            'status' => coursetransfer_request::STATUS_COMPLETED,
            'origin_category_id' => 5000,
            'origin_manifest' => json_encode(['keepsafetycopy' => true]),
        ]);
        $kept = $this->safety_copy(['request_category_id' => $reqcat->id]);
        $this->run_task();
        $this->assertTrue($this->has_copy($kept), 'No removal of its origin yet');

        $generator->create_request([
            'type' => coursetransfer_request::TYPE_REMOVE_CATEGORY,
            'status' => coursetransfer_request::STATUS_COMPLETED,
            'origin_category_id' => 5000,
            'timemodified' => time() - 40 * DAYSECS,
        ]);
        $this->run_task();
        $this->assertFalse($this->has_copy($kept), 'Its origin category was removed 40 days ago');
        $this->assertTrue($DB->record_exists('local_coursetransfer_request', ['id' => $kept]));
    }

    /**
     * On the origin, a served backup stays while its course (or a parent category) has a removal pending.
     */
    public function test_served_backup_waits_for_pending_removal(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $request = $this->served_backup(coursetransfer_request::STATUS_COMPLETED, 10);
        $removal = $generator->create_request([
            'type' => coursetransfer_request::TYPE_REMOVE_CATEGORY,
            'direction' => coursetransfer_request::DIRECTION_RESPONSE,
            'status' => coursetransfer_request::STATUS_IN_PROGRESS,
            'origin_category_id' => $this->course->category,
        ]);
        $this->run_task();
        $this->assertTrue($this->has_file((int) $request->id), 'Its category has a removal pending');

        coursetransfer_request::update_fields((int) $removal->id, ['status' => coursetransfer_request::STATUS_ERROR]);
        $this->run_task();
        $this->assertFalse($this->has_file((int) $request->id), 'The removal failed: nothing pending');
    }

    /**
     * On the origin, a served backup outlives the removal of its course for the protection period.
     */
    public function test_served_backup_survives_course_removal(): void {
        global $DB;
        $request = $this->served_backup(coursetransfer_request::STATUS_COMPLETED, 10);
        $sysid = \context_system::instance()->id;

        $this->assertSame(1, \local_coursetransfer\coursetransfer_remove::preserve_served_backups([$this->course->id]));
        ob_start();
        delete_course($this->course->id, false);
        ob_end_clean();
        $files = get_file_storage()->get_area_files($sysid, 'local_coursetransfer', 'backup', $request->id, 'id', false);
        $this->assertCount(1, $files, 'Moved out of the course context before its removal');

        $this->run_task();
        $files = get_file_storage()->get_area_files($sysid, 'local_coursetransfer', 'backup', $request->id, 'id', false);
        $this->assertCount(1, $files, 'Removed less than the protection period ago');

        $DB->set_field('files', 'timemodified', time() - 40 * DAYSECS, ['contextid' => $sysid, 'filearea' => 'backup']);
        $this->run_task();
        $files = get_file_storage()->get_area_files($sysid, 'local_coursetransfer', 'backup', $request->id, 'id', false);
        $this->assertCount(0, $files);
    }
}
