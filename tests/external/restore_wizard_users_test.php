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
 * User data requested from the restore wizards.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external;

use advanced_testcase;
use local_coursetransfer\external\frontend\restore_wizard_external;
use required_capability_exception;

/**
 * Bringing origin user data requires local/coursetransfer:origin_restore_course_users (N4).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\frontend\restore_wizard_external
 */
final class restore_wizard_users_test extends advanced_testcase {
    /**
     * Give a user a new role with these capabilities in a context.
     *
     * @param int $userid
     * @param string[] $capabilities
     * @param \context $context
     */
    private function grant(int $userid, array $capabilities, \context $context): void {
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id);
        }
        role_assign($roleid, $userid, $context->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * Assert the call is rejected for lack of the user data capability.
     *
     * @param callable $call
     */
    private function assert_users_rejected(callable $call): void {
        try {
            $call();
            $this->fail('User data must require origin_restore_course_users');
        } catch (required_capability_exception $e) {
            $capability = get_capability_string('local/coursetransfer:origin_restore_course_users');
            $this->assertStringContainsString($capability, $e->getMessage());
        }
    }

    /**
     * A teacher who may restore over the course cannot ask for user data.
     */
    public function test_submit_course_users(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->assert_users_rejected(fn() => restore_wizard_external::submit_course(1, 10, (int) $course->id, 'merge', true));

        // Without user data it gets past the capability checks and fails on the unknown site.
        $res = restore_wizard_external::submit_course(1, 10, (int) $course->id, 'merge', false);
        $this->assertSame('30601', (string) $res['errors'][0]['code']);
    }

    /**
     * Category and admin restores need the capability too.
     */
    public function test_submit_and_submit_category_users(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $user = $this->getDataGenerator()->create_user();
        $this->grant((int) $user->id, ['local/coursetransfer:origin_restore'], \context_system::instance());
        $this->grant(
            (int) $user->id,
            ['local/coursetransfer:origin_restore_category', 'moodle/restore:restorecourse'],
            \context_coursecat::instance($category->id)
        );
        $this->setUser($user);

        $this->assert_users_rejected(fn() => restore_wizard_external::submit_category(1, 10, (int) $category->id, true));
        $this->assert_users_rejected(fn() => restore_wizard_external::submit(1, 'course', [], [], 0, 'merge', true));
    }
}
