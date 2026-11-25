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
 * Guards of the wizards: replace targets, schedules and deleting requests.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use local_coursetransfer\event\request_deleted;
use local_coursetransfer\external\frontend\restore_wizard_external;

/**
 * Nothing restored is emptied twice, nothing is removed before its date, nothing running is deleted (R2, R4, R5).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\frontend\restore_wizard_external
 * @covers     \local_coursetransfer\coursetransfer_request::can_delete
 */
final class wizard_guards_test extends advanced_testcase {
    /**
     * Act as admin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * The same existing target for two origins with replace is rejected before anything is launched (R2).
     */
    public function test_duplicate_replace_target(): void {
        $target = $this->getDataGenerator()->create_course(['fullname' => 'Target X']);
        $courses = [
            ['origincourseid' => 11, 'targetid' => (int) $target->id, 'mode' => 'replace'],
            ['origincourseid' => 12, 'targetid' => (int) $target->id, 'mode' => 'merge'],
        ];

        $res = restore_wizard_external::submit(1, 'course', $courses);

        $this->assertFalse($res['success']);
        $this->assertSame('10030', $res['errors'][0]['code']);
        $this->assertStringContainsString('Target X', $res['errors'][0]['msg']);
        $this->assertSame([], $res['data']['requestids']);
    }

    /**
     * A replace over a course with a restore still running is rejected too (R2).
     */
    public function test_replace_over_running_restore(): void {
        $target = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request([
            'status' => coursetransfer_request::STATUS_IN_PROGRESS,
            'target_course_id' => $target->id,
        ]);

        $res = restore_wizard_external::submit(1, 'course', [
            ['origincourseid' => 11, 'targetid' => (int) $target->id, 'mode' => 'replace'],
        ]);

        $this->assertFalse($res['success']);
        $this->assertSame('10030', $res['errors'][0]['code']);
    }

    /**
     * A schedule in the past or beyond the limit is rejected, not run at once (R5).
     */
    public function test_invalid_schedule(): void {
        $past = (time() - HOURSECS) * 1000;
        $res = restore_wizard_external::remove_submit(1, 'course', [10], $past);
        $this->assertFalse($res['success']);
        $this->assertSame('10031', $res['errors'][0]['code']);
        $this->assertSame([], $res['data']['requestids']);

        $far = (time() + 60 * DAYSECS) * 1000;
        $courses = [['origincourseid' => 11, 'targetid' => 0]];
        $res = restore_wizard_external::submit(1, 'course', $courses, [], 0, 'merge', false, false, $far);
        $this->assertSame('10031', $res['errors'][0]['code']);
    }

    /**
     * Any request can be deleted, also one in progress, and the deletion is logged (LCT-126).
     */
    public function test_delete_request(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $running = $generator->create_request(['status' => coursetransfer_request::STATUS_IN_PROGRESS]);
        $res = restore_wizard_external::delete_request((int) $running->id);
        $this->assertTrue($res['success']);
        $this->assertEmpty(coursetransfer_request::get($running->id));

        $done = $generator->create_request(['status' => coursetransfer_request::STATUS_COMPLETED]);
        $sink = $this->redirectEvents();
        $res = restore_wizard_external::delete_request((int) $done->id);
        $this->assertTrue($res['success']);
        $this->assertEmpty(coursetransfer_request::get($done->id));
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof request_deleted);
        $this->assertCount(1, $events);
        $this->assertEquals($done->id, reset($events)->objectid);
    }

    /**
     * Deleting a course of a category restore in progress marks the category as failed: it is never taken as
     * complete without that course, so the origin category is never removed because of it (LCT-126).
     */
    public function test_delete_course_of_a_running_category(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $cat = $generator->create_request(['type' => coursetransfer_request::TYPE_CATEGORY,
            'status' => coursetransfer_request::STATUS_IN_PROGRESS, 'origin_remove_category' => 1]);
        $first = $generator->create_request(['request_category_id' => $cat->id,
            'status' => coursetransfer_request::STATUS_COMPLETED]);
        $second = $generator->create_request(['request_category_id' => $cat->id,
            'status' => coursetransfer_request::STATUS_DOWNLOAD]);

        $res = restore_wizard_external::delete_request((int) $second->id);
        $this->assertTrue($res['success']);

        $reqcat = coursetransfer_request::update_status_request_cat((int) $cat->id);
        $this->assertSame(coursetransfer_request::STATUS_INCOMPLETED, (int) $reqcat->status);
        $this->assertFalse($reqcat->completednow);
        $this->assertSame(11003, (int) $reqcat->error_code);
        $this->assertStringContainsString('Course request ' . $second->id . ' deleted', $reqcat->error_message);
        $this->assertNotEmpty(coursetransfer_request::get($first->id));
    }
}
