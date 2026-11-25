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
 * Tests for the status of category restore requests.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use backup;
use local_coursetransfer\models\configuration_category;
use stdClass;

/**
 * A category restore only completes when every course of the origin category was copied.
 *
 * Calls to the origin go through Moodle's curl mock (LIFO: the last mocked answer is used first).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\coursetransfer_request::update_status_request_cat
 * @covers     \local_coursetransfer\coursetransfer::restore_category
 * @covers     \local_coursetransfer\coursetransfer::restore_category_tree
 */
final class coursetransfer_category_status_test extends advanced_testcase {
    /** @var stdClass Origin site. */
    private $site;

    /**
     * Act as admin with a registered origin.
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
        $left = $property->getValue();
        $property->setValue(null, []);
        parent::tearDown();
        $this->assertSame([], $left, 'Every mocked remote response must be consumed');
    }

    /**
     * Answer of the origin to a backup request.
     *
     * @param int $id Origin course id.
     */
    private function mock_backup_ok(int $id): void {
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => [
            'course_fullname' => "Course $id", 'course_shortname' => "C$id", 'course_idnumber' => '',
            'course_category_id' => 1, 'course_category_name' => 'Cat', 'course_category_idnumber' => '',
            'origin_backup_size_estimated' => 10,
        ]]));
    }

    /**
     * Course requests created for a category request.
     *
     * @param int $reqcatid
     * @return stdClass[]
     */
    private function children(int $reqcatid): array {
        global $DB;
        return $DB->get_records('local_coursetransfer_request', ['request_category_id' => $reqcatid]);
    }

    /**
     * Mark every course request of a category as restored.
     *
     * @param int $reqcatid
     */
    private function complete_children(int $reqcatid): void {
        global $DB;
        $DB->set_field(
            'local_coursetransfer_request',
            'status',
            coursetransfer_request::STATUS_COMPLETED,
            ['request_category_id' => $reqcatid]
        );
    }

    /**
     * Configuration that removes the origin category.
     *
     * @return configuration_category
     */
    private function configuration(): configuration_category {
        return new configuration_category(backup::TARGET_NEW_COURSE, false, false, false, true);
    }

    /**
     * A subtree deeper than the limit is not copied, so the category never completes (N2).
     */
    public function test_tree_too_deep_does_not_complete(): void {
        $leaf = ['name' => 'Leaf', 'idnumber' => '', 'courses' => [['id' => 99, 'fullname' => 'Deep', 'shortname' => 'D']],
            'categories' => []];
        for ($i = 0; $i < coursetransfer::MAX_TREE_DEPTH + 2; $i++) {
            $leaf = ['name' => "L$i", 'idnumber' => '', 'courses' => [], 'categories' => [$leaf]];
        }
        $tree = ['name' => 'Root', 'idnumber' => '', 'courses' => [['id' => 11, 'fullname' => 'Top', 'shortname' => 'T']],
            'categories' => [$leaf]];
        $this->mock_backup_ok(11);
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => json_encode($tree)]));

        $res = coursetransfer::restore_category_tree(get_admin(), $this->site, 0, 7, $this->configuration());

        $this->assertFalse($res['success']);
        $reqcatid = (int) $res['data']['requestid'];
        $this->assertCount(1, $this->children($reqcatid));
        $this->complete_children($reqcatid);
        $reqcat = coursetransfer_request::update_status_request_cat($reqcatid);
        $this->assertEquals(coursetransfer_request::STATUS_INCOMPLETED, $reqcat->status);
        $this->assertFalse($reqcat->completednow);
        $this->assertEquals('11004', coursetransfer_request::get($reqcatid)->error_code);
    }

    /**
     * A course that cannot be created on the target leaves the category incomplete (N2).
     */
    public function test_flat_course_creation_failure_does_not_complete(): void {
        $courses = [
            ['id' => 11, 'fullname' => 'One', 'shortname' => 'ONE'],
            ['id' => 12, 'fullname' => null, 'shortname' => 'TWO'],
        ];
        $this->mock_backup_ok(11);
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => [
            'id' => 7, 'name' => 'Cat', 'idnumber' => '', 'parentid' => 0, 'parentname' => 'Top', 'courses' => $courses,
        ]]));

        $res = coursetransfer::restore_category(get_admin(), $this->site, 0, 7, $this->configuration());

        $this->assertFalse($res['success']);
        $reqcatid = (int) $res['data']['requestid'];
        $this->assertCount(1, $this->children($reqcatid));
        $this->complete_children($reqcatid);
        $reqcat = coursetransfer_request::update_status_request_cat($reqcatid);
        $this->assertEquals(coursetransfer_request::STATUS_INCOMPLETED, $reqcat->status);
        $this->assertFalse($reqcat->completednow);
    }

    /**
     * The category completes once, when its last expected course is restored.
     */
    public function test_completes_once_against_snapshot(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $reqcat = $generator->create_request([
            'type' => coursetransfer_request::TYPE_CATEGORY,
            'origin_category_tree' => json_encode(['name' => 'Cat', 'courses' => [['id' => 1], ['id' => 2]], 'categories' => []]),
        ]);
        $generator->create_request(['request_category_id' => $reqcat->id, 'status' => coursetransfer_request::STATUS_COMPLETED]);

        $first = coursetransfer_request::update_status_request_cat($reqcat->id);
        $this->assertEquals(coursetransfer_request::STATUS_INCOMPLETED, $first->status);

        $generator->create_request(['request_category_id' => $reqcat->id, 'status' => coursetransfer_request::STATUS_COMPLETED]);
        $second = coursetransfer_request::update_status_request_cat($reqcat->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $second->status);
        $this->assertTrue($second->completednow);

        $third = coursetransfer_request::update_status_request_cat($reqcat->id);
        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $third->status);
        $this->assertFalse($third->completednow, 'Notifications and the origin removal are triggered once');
    }

    /**
     * Requests created before the snapshot existed keep the previous rule: all their courses restored.
     */
    public function test_legacy_request_without_snapshot(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $reqcat = $generator->create_request(['type' => coursetransfer_request::TYPE_CATEGORY]);
        $generator->create_request(['request_category_id' => $reqcat->id, 'status' => coursetransfer_request::STATUS_COMPLETED]);

        $reqcat = coursetransfer_request::update_status_request_cat($reqcat->id);

        $this->assertEquals(coursetransfer_request::STATUS_COMPLETED, $reqcat->status);
        $this->assertNull(coursetransfer_request::update_status_request_cat(999999));
    }
}
