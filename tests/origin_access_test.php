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
 * Who sees what of the origin: courses of a teacher and the category menu link.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use context_coursecat;
use navigation_node;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/coursetransfer/lib.php');

/**
 * A suspended teacher no longer sees the course (#60); the category menu does not break pages (#63).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\coursetransfer::can_backup_course
 * @covers     ::local_coursetransfer_extend_navigation_category_settings
 * @covers     \local_coursetransfer\coursetransfer::auth_user
 */
final class origin_access_test extends advanced_testcase {
    /**
     * Only an active enrolment (or access without one) lets a teacher back up the course.
     */
    public function test_suspended_teacher(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $active = $gen->create_and_enrol($course, 'editingteacher');
        $suspended = $gen->create_user();
        $gen->enrol_user($suspended->id, $course->id, 'editingteacher', 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $this->assertTrue(coursetransfer::can_backup_course($course, $active));
        $this->assertFalse(coursetransfer::can_backup_course($course, $suspended));
        $this->assertFalse(coursetransfer::has_course($suspended));
        $this->assertTrue(coursetransfer::can_backup_course($course, get_admin()), 'Access without enrolment');
        $this->assertFalse(coursetransfer::can_backup_course(get_site(), get_admin()), 'Never the front page');
    }

    /**
     * The link is added only for who may restore a category, without loading a hidden category.
     */
    public function test_category_menu_link(): void {
        global $PAGE;
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category(['visible' => 0]);
        $context = context_coursecat::instance($category->id);
        $PAGE->set_url('/enrol/index.php');

        $this->setUser($this->getDataGenerator()->create_user());
        $node = navigation_node::create('root');
        local_coursetransfer_extend_navigation_category_settings($node, $context);
        $this->assertFalse($node->has_children(), 'A visitor gets no link and no exception');

        $this->setAdminUser();
        $node = navigation_node::create('root');
        local_coursetransfer_extend_navigation_category_settings($node, $context);
        $this->assertTrue($node->has_children());
    }

    /**
     * Only a live, unique local account acts on the origin (B2).
     */
    public function test_auth_user_live_account(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_and_enrol($course, 'editingteacher', ['email' => 'teacher@example.invalid']);
        $res = coursetransfer::auth_user('username', $user->username);
        $this->assertTrue($res['success'], json_encode($res['error'] ?? []));

        $suspended = $gen->create_and_enrol($course, 'editingteacher', ['suspended' => 1]);
        $this->assertSame('17005', coursetransfer::auth_user('username', $suspended->username)['error']['code']);

        $deleted = $gen->create_and_enrol($course, 'editingteacher');
        delete_user($deleted);
        $this->assertSame('17002', coursetransfer::auth_user('userid', (string) $deleted->id)['error']['code']);

        $gen->create_user(['email' => 'teacher@example.invalid']);
        $this->assertSame('17004', coursetransfer::auth_user('email', 'teacher@example.invalid')['error']['code']);
    }
}
