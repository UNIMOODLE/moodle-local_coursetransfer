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
 * Tests for the service user factory.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use local_coursetransfer\factory\user;

/**
 * Tests for the service user factory.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursetransfer\factory\user
 */
final class factory_user_test extends advanced_testcase {
    /**
     * The service user gets an address on a reserved TLD and never receives email.
     */
    public function test_service_user_email_is_reserved_and_stopped(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'ctwstest']);
        $userid = user::create_user($roleid);
        $record = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        $this->assertSame(user::USERNAME_WS, $record->username);
        $this->assertSame('local_coursetransfer_ws@example.invalid', $record->email);
        $this->assertStringEndsWith('.invalid', $record->email);
        $this->assertEquals(1, $record->emailstop);
        $this->assertDebuggingNotCalled();
    }
}
