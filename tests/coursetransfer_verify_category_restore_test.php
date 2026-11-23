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
 * Tests for the verification of a category restore used by local_coursetransfermanager.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use stdClass;

/**
 * Tests for coursetransfer::verify_category_restore().
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\coursetransfer::verify_category_restore
 */
final class coursetransfer_verify_category_restore_test extends advanced_testcase {
    /** @var string Origin host. */
    private const ORIGIN = 'https://origin.example.com';

    /**
     * Admin, and an origin site.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_site('origin', ['host' => self::ORIGIN]);
    }

    /**
     * No mocked response may be left behind.
     */
    protected function tearDown(): void {
        $property = new \ReflectionProperty(\curl::class, 'mockresponses');
        $left = $property->getValue();
        $property->setValue(null, []);
        parent::tearDown();
        $this->assertSame([], $left, 'Every mocked remote response must be consumed');
    }

    /**
     * A category request with course requests, given as [status, error_code, origin_manifest].
     *
     * @param array $children
     * @param int $expected Courses in the snapshot of the origin category.
     * @return stdClass
     */
    private function category(array $children, int $expected): stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $list = [];
        for ($i = 1; $i <= $expected; $i++) {
            $list[] = ['id' => $i, 'fullname' => "Course $i", 'shortname' => "C$i"];
        }
        $reqcat = $generator->create_request([
            'type' => coursetransfer_request::TYPE_CATEGORY,
            'siteurl' => self::ORIGIN,
            'status' => coursetransfer_request::STATUS_COMPLETED,
            'origin_category_id' => 5000,
            'origin_category_tree' => json_encode(['name' => 'Cat', 'courses' => $list, 'categories' => []]),
        ]);
        $ids = [];
        foreach (array_values($children) as $i => [$status, $errorcode, $manifest]) {
            $target = $this->getDataGenerator()->create_course();
            $ids[] = (int) $generator->create_request([
                'request_category_id' => $reqcat->id,
                'siteurl' => self::ORIGIN,
                'status' => $status,
                'error_code' => $errorcode,
                'origin_manifest' => $manifest,
                'origin_course_id' => $i + 1,
                'origin_course_shortname' => 'C' . ($i + 1),
                'target_course_id' => $target->id,
            ])->id;
        }
        coursetransfer_request::update_fields((int) $reqcat->id, ['origin_category_requests' => json_encode($ids)]);
        return coursetransfer_request::get($reqcat->id);
    }

    /**
     * Manifest of a course checked without issues.
     *
     * @return string
     */
    private function verified(): string {
        return json_encode(['verified' => time(), 'issues' => []]);
    }

    /**
     * Every course completed and verified: ok.
     */
    public function test_ok(): void {
        $completed = coursetransfer_request::STATUS_COMPLETED;
        $reqcat = $this->category([[$completed, null, $this->verified()], [$completed, null, $this->verified()]], 2);

        $res = coursetransfer::verify_category_restore((int) $reqcat->id);

        $this->assertTrue($res->ok);
        $this->assertSame([], $res->issues);
    }

    /**
     * Each course that is not right is named: not completed, restored with issues, mismatch, missing courses.
     */
    public function test_issues(): void {
        $completed = coursetransfer_request::STATUS_COMPLETED;
        $reqcat = $this->category([
            [coursetransfer_request::STATUS_RESTORE, null, null],
            [$completed, coursetransfer_request::CODE_RESTORE_WITH_ISSUES, $this->verified()],
            [$completed, null, json_encode(['verified' => time(), 'issues' => ['mod_forum 0/1']])],
        ], 4);

        $res = coursetransfer::verify_category_restore((int) $reqcat->id);

        $this->assertFalse($res->ok);
        $this->assertCount(4, $res->issues);
        $text = implode("\n", $res->issues);
        $this->assertStringContainsString('3 of the 4 courses', $text);
        $this->assertStringContainsString('"C1" (origin id 1', $text);
        $this->assertStringContainsString('error 10410', $text);
        $this->assertStringContainsString('mod_forum 0/1', $text);
    }

    /**
     * A course whose manifest was never checked is checked now against the origin, and a mismatch recorded.
     */
    public function test_checks_missing_manifest(): void {
        $completed = coursetransfer_request::STATUS_COMPLETED;
        $reqcat = $this->category([[$completed, null, null], [$completed, null, null]], 2);
        $children = json_decode($reqcat->origin_category_requests);
        foreach ($children as $childid) {
            // As after a real restore: the manifest of the backup was read (A3).
            restore_manifest::save_backup((int) $childid, ['sections' => 1, 'activities' => [], 'enrolments' => null]);
        }
        $detail = fn(array $activities) => json_encode(['success' => true, 'errors' => [], 'data' => [
            'id' => 1, 'fullname' => 'O', 'shortname' => 'C', 'sections' => [['activities' => $activities]],
        ]]);
        // The first child is checked first: its answer is the last one pushed.
        \curl::mock_response($detail([['modname' => 'forum']]));
        \curl::mock_response($detail([]));

        $res = coursetransfer::verify_category_restore((int) $reqcat->id);

        $this->assertFalse($res->ok);
        $this->assertCount(1, $res->issues);
        $this->assertStringContainsString('mod_forum 0/1', $res->issues[0]);
        $first = coursetransfer_request::get($children[0]);
        $this->assertNotEmpty(json_decode($first->origin_manifest, true)['verified']);
        $this->assertEmpty($first->error_code);
        $this->assertEquals(coursetransfer_request::CODE_MANIFEST_MISMATCH, coursetransfer_request::get($children[1])->error_code);
    }

    /**
     * Anything but a category restore request of this site is not ok.
     */
    public function test_not_a_category(): void {
        $request = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request([]);
        $this->assertFalse(coursetransfer::verify_category_restore((int) $request->id)->ok);
        $this->assertFalse(coursetransfer::verify_category_restore(999999)->ok);
    }
}
