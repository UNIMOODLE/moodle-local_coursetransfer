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
 * Contract tests for the destination search and category idnumber web services.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external;

use advanced_testcase;
use core_external\external_api;
use local_coursetransfer\external\backend\get_category_idnumber_external;
use local_coursetransfer\external\frontend\search_category;
use local_coursetransfer\external\frontend\search_course;

/**
 * Contract tests for dest_search_course_name, dest_search_category_name and get_category_idnumber.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\frontend\search_course
 * @covers     \local_coursetransfer\external\frontend\search_category
 * @covers     \local_coursetransfer\external\backend\get_category_idnumber_external
 */
final class search_external_test extends advanced_testcase {
    /**
     * Reset after each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * dest_search_course_name finds courses by name, at most 7.
     */
    public function test_dest_search_course_name(): void {
        $this->setAdminUser();
        for ($i = 1; $i <= 8; $i++) {
            $this->getDataGenerator()->create_course(['fullname' => "Algebra $i", 'shortname' => "ALG$i", 'idnumber' => "A$i"]);
        }
        $this->getDataGenerator()->create_course(['fullname' => 'History']);

        $res = external_api::clean_returnvalue(search_course::search_by_name_returns(), search_course::search_by_name('algebra'));

        $this->assertTrue($res['success']);
        $this->assertCount(7, $res['data']);
        $this->assertStringStartsWith('Algebra', $res['data'][0]['fullname']);
        $this->assertStringStartsWith('ALG', $res['data'][0]['shortname']);

        $res = search_course::search_by_name('nothing here');
        $res = external_api::clean_returnvalue(search_course::search_by_name_returns(), $res);
        $this->assertTrue($res['success']);
        $this->assertSame([], $res['data']);
    }

    /**
     * dest_search_course_name only returns courses the caller can back up.
     */
    public function test_dest_search_course_name_capability(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Algebra']);
        $this->getDataGenerator()->create_course(['fullname' => 'Algebra hidden from teacher']);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $res = external_api::clean_returnvalue(search_course::search_by_name_returns(), search_course::search_by_name('algebra'));
        $this->assertSame([(int) $course->id], array_column($res['data'], 'id'));

        $this->setUser($this->getDataGenerator()->create_user());
        $res = external_api::clean_returnvalue(search_course::search_by_name_returns(), search_course::search_by_name('algebra'));
        $this->assertSame([], $res['data']);
    }

    /**
     * dest_search_category_name lists the pseudo option and the matching categories.
     */
    public function test_dest_search_category_name(): void {
        $this->setAdminUser();
        $this->getDataGenerator()->create_category(['name' => 'Engineering']);
        $returns = search_category::search_by_name_returns();

        $res = external_api::clean_returnvalue($returns, search_category::search_by_name('', 'category'));
        $this->assertTrue($res['success']);
        $this->assertSame(['id' => 0, 'name' => get_string('top')], $res['data'][0]);
        $this->assertContains('Engineering', array_column($res['data'], 'name'));

        $res = external_api::clean_returnvalue($returns, search_category::search_by_name('engin', 'course'));
        $this->assertSame(['Engineering'], array_column($res['data'], 'name'));

        $res = external_api::clean_returnvalue($returns, search_category::search_by_name('', 'course'));
        $this->assertSame(get_string('rw_defaultcat', 'local_coursetransfer'), $res['data'][0]['name']);
    }

    /**
     * dest_search_category_name only offers categories where the caller can create courses.
     */
    public function test_dest_search_category_name_capability(): void {
        $this->getDataGenerator()->create_category(['name' => 'Engineering']);
        $this->setUser($this->getDataGenerator()->create_user());

        $res = external_api::clean_returnvalue(search_category::search_by_name_returns(), search_category::search_by_name('engin'));
        $this->assertTrue($res['success']);
        $this->assertSame([], $res['data']);
    }

    /**
     * get_category_idnumber resolves exactly one category by idnumber pattern.
     */
    public function test_get_category_idnumber(): void {
        $this->setAdminUser();
        $target = $this->getDataGenerator()->create_category(['name' => 'Year 2026', 'idnumber' => 'YEAR-2026']);
        $this->getDataGenerator()->create_category(['name' => 'Year 2025', 'idnumber' => 'YEAR-2025']);
        $returns = get_category_idnumber_external::get_category_idnumber_returns();

        $res = external_api::clean_returnvalue($returns, get_category_idnumber_external::get_category_idnumber('^YEAR-2026$'));
        $this->assertTrue($res['success']);
        $this->assertSame(['id' => (int) $target->id, 'name' => 'Year 2026', 'idnumber' => 'YEAR-2026'], $res['category']);
        $this->assertSame(1, $res['matchcount']);
        $this->assertSame('', $res['error']);
    }

    /**
     * Several or no matches are reported with their count and success false.
     */
    public function test_get_category_idnumber_no_unique_match(): void {
        $this->setAdminUser();
        $this->getDataGenerator()->create_category(['idnumber' => 'YEAR-2026']);
        $this->getDataGenerator()->create_category(['idnumber' => 'YEAR-2025']);
        $returns = get_category_idnumber_external::get_category_idnumber_returns();

        $res = external_api::clean_returnvalue($returns, get_category_idnumber_external::get_category_idnumber('^YEAR-'));
        $this->assertFalse($res['success']);
        $this->assertSame(2, $res['matchcount']);
        $this->assertSame(0, $res['category']['id']);
        $this->assertSame(get_string('categoryambiguous', 'local_coursetransfer'), $res['error']);

        $res = external_api::clean_returnvalue($returns, get_category_idnumber_external::get_category_idnumber('^NONE$'));
        $this->assertFalse($res['success']);
        $this->assertSame(0, $res['matchcount']);
        $this->assertSame(get_string('categorynotfound', 'local_coursetransfer'), $res['error']);

        // Both strings exist: an undefined one would have raised a debugging() message.
        $this->assertDebuggingNotCalled();
    }

    /**
     * A slash in the pattern is escaped instead of breaking the regex delimiter.
     */
    public function test_get_category_idnumber_slash(): void {
        $this->setAdminUser();
        $this->getDataGenerator()->create_category(['idnumber' => '2025/26']);

        $res = get_category_idnumber_external::get_category_idnumber('^2025/26$');
        $this->assertTrue($res['success']);
        $this->assertSame('2025/26', $res['category']['idnumber']);
    }

    /**
     * The idnumber lookup is for the service user only and bounds the regex it runs (LCT-010).
     */
    public function test_get_category_idnumber_requires_capability(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        try {
            get_category_idnumber_external::get_category_idnumber('^YEAR-');
            $this->fail('get_category_idnumber must require local/coursetransfer:webservice');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('local/coursetransfer:webservice'), $e->getMessage());
        }

        $this->setAdminUser();
        $this->getDataGenerator()->create_category(['idnumber' => str_repeat('a', 40) . '!']);
        $returns = get_category_idnumber_external::get_category_idnumber_returns();
        $invalid = get_string('categorypatterninvalid', 'local_coursetransfer');
        foreach (['(', str_repeat('a', 256), '^(a+)+$'] as $pattern) {
            $start = microtime(true);
            $res = external_api::clean_returnvalue($returns, get_category_idnumber_external::get_category_idnumber($pattern));
            $this->assertLessThan(5, microtime(true) - $start, 'Catastrophic backtracking must be cut short');
            $this->assertFalse($res['success']);
            $this->assertSame(0, $res['matchcount']);
            if ($pattern !== '^(a+)+$') {
                // With PCRE JIT the catastrophic pattern may simply not match: both are safe.
                $this->assertSame($invalid, $res['error'], $pattern);
            }
        }
    }
}
