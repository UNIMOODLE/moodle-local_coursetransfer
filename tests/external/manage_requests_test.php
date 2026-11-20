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
 * Write actions on execution logs.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external;

use advanced_testcase;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\external\frontend\restore_wizard_external;

/**
 * Deleting a log needs local/coursetransfer:manage_requests: view_logs only reads.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\frontend\restore_wizard_external
 */
final class manage_requests_test extends advanced_testcase {
    /**
     * A user who can only view logs cannot delete them; one who manages requests can.
     */
    public function test_delete_request_capability(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $request = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request();
        $syscontext = \context_system::instance();

        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/coursetransfer:view_logs', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $viewer->id, $syscontext);
        $this->setUser($viewer);
        try {
            restore_wizard_external::delete_request((int) $request->id);
            $this->fail('view_logs must not delete logs');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('local/coursetransfer:manage_requests'), $e->getMessage());
        }
        $this->assertTrue($DB->record_exists(coursetransfer_request::TABLE, ['id' => $request->id]));

        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id);
        $this->setUser($manager);
        $this->assertTrue(restore_wizard_external::delete_request((int) $request->id)['success']);
        $this->assertFalse($DB->record_exists(coursetransfer_request::TABLE, ['id' => $request->id]));
    }
}
