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
 * Setup of the service user, role and web services.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use local_coursetransfer\factory\user;

/**
 * postinstall() configures what the plugin needs without breaking the rest of the site.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\coursetransfer
 * @covers     \local_coursetransfer\factory\user
 */
final class postinstall_test extends advanced_testcase {
    /**
     * Running it twice keeps other protocols, authorises the user once and gives the role
     * the peer capability at system level.
     */
    public function test_postinstall(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('webserviceprotocols', 'soap');

        coursetransfer::postinstall();
        coursetransfer::postinstall();

        // LCT-007: rest is added, soap stays.
        $this->assertEqualsCanonicalizing(['soap', 'rest'], explode(',', get_config('core', 'webserviceprotocols')));

        // LCT-042: the service user is authorised once.
        $wsuser = \core_user::get_user_by_username(user::USERNAME_WS);
        $serviceid = $DB->get_field('external_services', 'id', ['component' => 'local_coursetransfer']);
        $this->assertSame(1, $DB->count_records('external_services_users', ['externalserviceid' => $serviceid,
            'userid' => $wsuser->id]));

        // H8 and LCT-021: the role holds the peer capability and is assignable at system level.
        $roleid = $DB->get_field('role', 'id', ['shortname' => user::USERNAME_WS]);
        $this->assertTrue(has_capability('local/coursetransfer:webservice', \context_system::instance(), $wsuser));
        $this->assertContains(CONTEXT_SYSTEM, array_map('intval', get_role_contextlevels($roleid)));
    }
}
