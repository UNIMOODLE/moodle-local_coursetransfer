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
 * Contract tests for the origin category read web services.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external;

use advanced_testcase;
use core_external\external_api;
use core_external\external_description;
use local_coursetransfer\external\backend\origin_category_external;
use stdClass;

/**
 * Contract tests for origin_get_categories, origin_get_category_detail and
 * origin_get_category_detail_tree.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\backend\origin_category_external
 */
final class origin_category_external_test extends advanced_testcase {
    /** @var stdClass Parent category with one course. */
    private $parent;

    /** @var stdClass Child category of $parent with one course. */
    private $child;

    /**
     * A two-level tree: parent (course P1) > child (course C1).
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $this->parent = $generator->create_category(['name' => 'Year 2026', 'idnumber' => 'Y2026']);
        $this->child = $generator->create_category(['name' => 'Term 1', 'idnumber' => 'Y2026T1', 'parent' => $this->parent->id]);
        $generator->create_course(['shortname' => 'P1', 'category' => $this->parent->id]);
        $generator->create_course(['shortname' => 'C1', 'category' => $this->child->id]);
    }

    /**
     * Return description of a method.
     *
     * Some structures pass PARAM_TEXT and a label as description and $required, which raises a
     * developer debugging() message when the description is built (plugin bug, reported in
     * TIPCOUTRAN-10). It is discarded here so the contract itself can be checked.
     *
     * @param string $method
     * @return external_description
     */
    private function returns(string $method): external_description {
        $description = call_user_func([origin_category_external::class, $method . '_returns']);
        $this->resetDebugging();
        return $description;
    }

    /**
     * origin_get_categories lists categories with their course and subcategory counts.
     */
    public function test_origin_get_categories(): void {
        $res = origin_category_external::origin_get_categories('username', 'admin');
        $res = external_api::clean_returnvalue($this->returns('origin_get_categories'), $res);

        $this->assertTrue($res['success']);
        $items = array_column($res['data'], null, 'idnumber');
        $this->assertSame(1, $items['Y2026']['totalcourses']);
        $this->assertSame(1, $items['Y2026']['totalsubcategories']);
        $this->assertSame(2, $items['Y2026']['totalcourseschild']);
        $this->assertSame('Year 2026', $items['Y2026T1']['parentname']);
        $this->assertSame(count($res['data']), $res['paging']['totalcount']);

        $res = origin_category_external::origin_get_categories('username', 'admin', 0, 1);
        $res = external_api::clean_returnvalue($this->returns('origin_get_categories'), $res);
        $this->assertCount(1, $res['data']);
        $this->assertSame(1, $res['paging']['perpage']);
    }

    /**
     * origin_get_categories rejects unknown users.
     */
    public function test_origin_get_categories_rejected(): void {
        $res = origin_category_external::origin_get_categories('username', 'nobody');
        $res = external_api::clean_returnvalue($this->returns('origin_get_categories'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('17002', $res['errors'][0]['code']);
    }

    /**
     * origin_get_category_detail returns the courses of the category and all its subcategories.
     */
    public function test_origin_get_category_detail(): void {
        $res = origin_category_external::origin_get_category_detail('username', 'admin', (int) $this->parent->id);
        $res = external_api::clean_returnvalue($this->returns('origin_get_category_detail'), $res);

        $this->assertTrue($res['success']);
        $this->assertSame('Y2026', $res['data']['idnumber']);
        $this->assertSame(get_string('top'), $res['data']['parentname']);
        $this->assertEqualsCanonicalizing(['P1', 'C1'], array_column($res['data']['courses'], 'shortname'));
        $courses = array_column($res['data']['courses'], null, 'shortname');
        $this->assertSame('Y2026T1', $courses['C1']['categoryidnumber']);
    }

    /**
     * Subcategories and courses the named user cannot see are counted, so the target does not remove them unseen.
     */
    public function test_origin_get_category_detail_hidden(): void {
        $generator = $this->getDataGenerator();
        $hidden = $generator->create_category(['name' => 'Hidden', 'parent' => $this->parent->id, 'visible' => 0]);
        $generator->create_course(['shortname' => 'H1', 'category' => $hidden->id]);
        $teacher = $generator->create_user();
        $generator->role_assign('editingteacher', $teacher->id, \context_coursecat::instance($this->parent->id));

        $res = origin_category_external::origin_get_category_detail('username', $teacher->username, (int) $this->parent->id);
        $res = external_api::clean_returnvalue($this->returns('origin_get_category_detail'), $res);
        $this->assertTrue($res['success']);
        $this->assertEqualsCanonicalizing(['P1', 'C1'], array_column($res['data']['courses'], 'shortname'));
        $this->assertSame(1, $res['data']['hiddencategories']);
        $this->assertSame(1, $res['data']['hiddencourses']);

        $res = origin_category_external::origin_get_category_detail('username', 'admin', (int) $this->parent->id);
        $res = external_api::clean_returnvalue($this->returns('origin_get_category_detail'), $res);
        $this->assertSame(0, $res['data']['hiddencategories']);
        $this->assertSame(0, $res['data']['hiddencourses']);

        $res = origin_category_external::origin_get_category_detail_tree('username', 'admin', (int) $this->parent->id);
        $tree = json_decode($res['data']);
        $this->assertSame(0, $tree->hiddencategories);
        $this->assertSame(0, $tree->hiddencourses);
    }

    /**
     * origin_get_category_detail rejects unknown users and unknown categories.
     */
    public function test_origin_get_category_detail_rejected(): void {
        $returns = $this->returns('origin_get_category_detail');

        $res = origin_category_external::origin_get_category_detail('username', 'nobody', (int) $this->parent->id);
        $this->assertSame(17002, external_api::clean_returnvalue($returns, $res)['errors'][0]['code']);

        $res = origin_category_external::origin_get_category_detail('username', 'admin', 999999);
        $res = external_api::clean_returnvalue($returns, $res);
        $this->assertFalse($res['success']);
        $this->assertSame(23001, $res['errors'][0]['code']);
    }

    /**
     * origin_get_category_detail_tree returns the subtree as JSON, each course under its own category.
     */
    public function test_origin_get_category_detail_tree(): void {
        $res = origin_category_external::origin_get_category_detail_tree('username', 'admin', (int) $this->parent->id);
        $res = external_api::clean_returnvalue($this->returns('origin_get_category_detail_tree'), $res);

        $this->assertTrue($res['success']);
        $tree = json_decode($res['data'], true);
        $this->assertSame('Y2026', $tree['idnumber']);
        $this->assertSame(['P1'], array_column($tree['courses'], 'shortname'));
        $this->assertCount(1, $tree['categories']);
        $this->assertSame('Y2026T1', $tree['categories'][0]['idnumber']);
        $this->assertSame(['C1'], array_column($tree['categories'][0]['courses'], 'shortname'));
    }

    /**
     * origin_get_category_detail_tree reports unknown users and categories within its declared structure.
     */
    public function test_origin_get_category_detail_tree_rejected(): void {
        $returns = $this->returns('origin_get_category_detail_tree');

        $res = origin_category_external::origin_get_category_detail_tree('username', 'nobody', (int) $this->parent->id);
        $res = external_api::clean_returnvalue($returns, $res);
        $this->assertFalse($res['success']);
        $this->assertSame(17002, $res['errors'][0]['code']);
        $this->assertSame('', $res['data']);

        $res = origin_category_external::origin_get_category_detail_tree('username', 'admin', 999999);
        $res = external_api::clean_returnvalue($returns, $res);
        $this->assertFalse($res['success']);
        $this->assertSame(24001, $res['errors'][0]['code']);
        $this->assertSame('', $res['data']);
    }

    /**
     * Category reads only show what the named user may back up at category level (LCT-010).
     */
    public function test_origin_category_reads_require_capability(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['shortname' => 'C2', 'category' => $this->child->id]);
        // A teacher of one course has courses, but cannot back up whole categories.
        $teacher = $generator->create_and_enrol($course, 'editingteacher', ['username' => 'teacher1']);
        // A manager of the child category only.
        $catmanager = $generator->create_user(['username' => 'catmanager']);
        $generator->role_assign('manager', $catmanager->id, \context_coursecat::instance($this->child->id));

        $returns = $this->returns('origin_get_categories');
        $res = external_api::clean_returnvalue($returns, origin_category_external::origin_get_categories('username', 'teacher1'));
        $this->assertTrue($res['success']);
        $this->assertSame([], $res['data']);
        $this->assertSame(0, $res['paging']['totalcount']);

        $res = origin_category_external::origin_get_categories('username', 'catmanager');
        $res = external_api::clean_returnvalue($returns, $res);
        $this->assertSame(['Y2026T1'], array_column($res['data'], 'idnumber'));
        $this->assertSame(1, $res['paging']['totalcount']);

        $res = origin_category_external::origin_get_category_detail('username', 'catmanager', (int) $this->parent->id);
        $res = external_api::clean_returnvalue($this->returns('origin_get_category_detail'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame(23002, $res['errors'][0]['code']);
        $this->assertSame([], $res['data']['courses']);

        $res = origin_category_external::origin_get_category_detail_tree('username', 'catmanager', (int) $this->parent->id);
        $res = external_api::clean_returnvalue($this->returns('origin_get_category_detail_tree'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame(24002, $res['errors'][0]['code']);

        $res = origin_category_external::origin_get_category_detail('username', 'catmanager', (int) $this->child->id);
        $res = external_api::clean_returnvalue($this->returns('origin_get_category_detail'), $res);
        $this->assertTrue($res['success']);
        $this->assertEqualsCanonicalizing(['C1', 'C2'], array_column($res['data']['courses'], 'shortname'));
    }
}
