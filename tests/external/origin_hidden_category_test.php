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

namespace local_coursetransfer\external;

use advanced_testcase;
use core_course_category;
use core_external\external_api;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\external\backend\origin_course_backup_external;
use local_coursetransfer\external\backend\origin_course_external;
use local_coursetransfer\external\backend\remove_external;
use local_coursetransfer\factory\user;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/coursetransfer/db/upgradelib.php');

/**
 * A course in a hidden category of the origin, called by a service user whose role cannot see it (LCT-068).
 *
 * Sites upgraded from 1.x kept that role until Repair. The requester's permissions are what matter: the
 * category only fills informative fields.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\coursetransfer_request
 * @covers     \local_coursetransfer\external\backend\origin_course_backup_external
 * @covers     \local_coursetransfer\external\backend\origin_course_external
 * @covers     \local_coursetransfer\external\backend\remove_external
 * @covers     ::local_coursetransfer_allow_hidden_categories
 */
final class origin_hidden_category_test extends advanced_testcase {
    /** @var string Host of the target site registered on this origin. */
    private const TARGET = 'https://target.example.com';

    /** @var stdClass Hidden category. */
    private $category;

    /** @var stdClass Course in the hidden category. */
    private $course;

    /**
     * Origin with the service user, its role without viewhiddencategories, and a course in a hidden category.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')
            ->create_site('target', ['host' => self::TARGET]);
        coursetransfer::postinstall();
        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => user::USERNAME_WS]);
        unassign_capability('moodle/category:viewhiddencategories', $roleid, \context_system::instance()->id);

        $this->category = $this->getDataGenerator()->create_category(['name' => 'Archive', 'idnumber' => 'ARCH', 'visible' => 0]);
        $this->course = $this->getDataGenerator()->create_course(['category' => $this->category->id, 'shortname' => 'HC1']);

        $this->setUser(\core_user::get_user_by_username(user::USERNAME_WS));
        // Precondition: the service user does not see the category, so the MUST_EXIST lookups of 2.2.0 threw.
        $this->assertNull(core_course_category::get($this->category->id, IGNORE_MISSING));
        try {
            core_course_category::get($this->category->id);
            $this->fail('The service user should not see the hidden category');
        } catch (moodle_exception $e) {
            $this->assertSame('cannotviewcategory', $e->errorcode);
        }
    }

    /**
     * Clean a return value against its description, discarding the debugging() its build raises (TIPCOUTRAN-10).
     *
     * @param string $class
     * @param string $method
     * @param mixed $res
     * @return array
     */
    private function clean(string $class, string $method, $res): array {
        $description = call_user_func([$class, $method . '_returns']);
        $this->resetDebugging();
        return external_api::clean_returnvalue($description, $res);
    }

    /**
     * The backup request of the target is accepted and keeps the category data.
     */
    public function test_origin_backup_course(): void {
        $res = origin_course_backup_external::origin_backup_course(
            'username',
            'admin',
            (int)$this->course->id,
            99,
            42,
            self::TARGET,
            [
                'target_target' => \backup::TARGET_NEW_COURSE,
                'target_remove_enrols' => false,
                'target_remove_groups' => false,
            ]
        );
        $res = $this->clean(origin_course_backup_external::class, 'origin_backup_course', $res);

        $this->assertTrue($res['success'], json_encode($res['errors']));
        $request = coursetransfer_request::get($res['data']['request_origin_id']);
        $this->assertSame('Archive', $request->origin_category_name);
        $this->assertSame('ARCH', $request->origin_category_idnumber);
    }

    /**
     * Removing the origin course is accepted.
     */
    public function test_origin_remove_course(): void {
        $res = remove_external::origin_remove_course('username', 'admin', (int)$this->course->id, 7, self::TARGET, 0);
        $res = $this->clean(remove_external::class, 'origin_remove_course', $res);

        $this->assertTrue($res['success'], json_encode($res['errors']));
        $this->assertSame('Archive', $res['data']['course_category_name']);
        $this->assertSame('ARCH', $res['data']['course_category_idnumber']);
    }

    /**
     * Removing the hidden origin category is accepted.
     */
    public function test_origin_remove_category(): void {
        $res = remove_external::origin_remove_category('username', 'admin', (int)$this->category->id, 8, self::TARGET, 0);
        $res = $this->clean(remove_external::class, 'origin_remove_category', $res);

        $this->assertTrue($res['success'], json_encode($res['errors']));
        $this->assertSame('Archive', $res['data']['course_category_name']);
    }

    /**
     * The deprecated listing by ids returns the course with its category name.
     */
    public function test_origin_get_courses_by_ids(): void {
        $res = origin_course_external::origin_get_courses_by_ids('username', 'admin', json_encode([(int)$this->course->id]));
        $res = $this->clean(origin_course_external::class, 'origin_get_courses_by_ids', $res);

        $this->assertTrue($res['success'], json_encode($res['errors']));
        $this->assertSame('Archive', $res['data'][0]['categoryname']);
    }

    /**
     * The upgrade step gives the role viewhiddencategories again, and does nothing without the role.
     */
    public function test_upgrade_allows_hidden_categories(): void {
        global $DB;
        $wsuser = \core_user::get_user_by_username(user::USERNAME_WS);
        $context = \context_coursecat::instance($this->category->id);
        $this->assertFalse(has_capability('moodle/category:viewhiddencategories', $context, $wsuser));

        $this->assertTrue(local_coursetransfer_allow_hidden_categories());
        $this->assertTrue(has_capability('moodle/category:viewhiddencategories', $context, $wsuser));

        $DB->set_field('role', 'shortname', 'renamed', ['shortname' => user::USERNAME_WS]);
        $this->assertFalse(local_coursetransfer_allow_hidden_categories());
    }
}
