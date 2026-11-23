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

namespace local_coursetransfer;

use advanced_testcase;
use local_coursetransfer\factory\user;
use local_coursetransfer\output\index_page;
use moodle_exception;
use stdClass;
use tool_policy\api as policyapi;
use tool_policy\policy_version;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/coursetransfer/db/upgradelib.php');

/**
 * The service user keeps the site policies accepted (LCT-074).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\coursetransfer
 * @covers     \local_coursetransfer\output\index_page
 * @covers     ::local_coursetransfer_accept_ws_site_policies
 */
final class site_policies_test extends advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Publish a compulsory policy for logged-in users, or a new version of it, as an admin does in tool_policy.
     *
     * @param policy_version|null $previous Version to replace; null for a new policy.
     * @return policy_version The version now current.
     */
    private function publish_policy(?policy_version $previous = null): policy_version {
        $formdata = policyapi::form_policydoc_data($previous ?? new policy_version(0));
        if ($previous) {
            $formdata->revision = 'v' . ((int)$previous->get('id') + 1);
            $version = policyapi::form_policydoc_update_new($formdata);
        } else {
            $formdata->name = 'Terms of use';
            $formdata->type = policy_version::TYPE_SITE;
            $formdata->audience = policy_version::AUDIENCE_LOGGEDIN;
            $formdata->optional = policy_version::AGREEMENT_COMPULSORY;
            $version = policyapi::form_policydoc_add($formdata);
        }
        policyapi::make_current($version->get('id'));
        return $version;
    }

    /**
     * The service user record, as read from the database.
     *
     * @return stdClass
     */
    private function wsuser(): stdClass {
        return \core_user::get_user_by_username(user::USERNAME_WS);
    }

    /**
     * The site policies check of the status page.
     *
     * @return stdClass
     */
    private function policies_check(): stdClass {
        global $PAGE;
        $PAGE->set_context(\context_system::instance());
        $data = (new index_page())->export_for_template($PAGE->get_renderer('local_coursetransfer'));
        foreach ($data->checks as $check) {
            if ($check->faicon === 'fa-file-text-o') {
                return $check;
            }
        }
        $this->fail('The status page has no site policies check');
    }

    /**
     * The error a peer gets when it calls this site as the service user.
     *
     * @return string|null Error code, or null if the call may go on.
     */
    private function peer_call_error(): ?string {
        $this->setUser($this->wsuser());
        try {
            coursetransfer::require_site_policies_agreed();
            return null;
        } catch (moodle_exception $e) {
            return $e->errorcode;
        } finally {
            $this->setAdminUser();
        }
    }

    /**
     * A new policy version blocks the service user: the status page says so and Repair fixes it.
     */
    public function test_new_policy_version(): void {
        global $DB;
        set_config('sitepolicyhandler', 'tool_policy');
        $v1 = $this->publish_policy();
        coursetransfer::postinstall();
        $this->assertEquals(1, $this->wsuser()->policyagreed);
        $this->assertSame('ok', $this->policies_check()->state);
        $this->assertNull($this->peer_call_error());

        // Publishing a new version resets the acceptance of every user (tool_policy\api::make_current).
        $v2 = $this->publish_policy($v1);
        $this->assertEquals(0, $this->wsuser()->policyagreed);
        $this->assertTrue(coursetransfer::site_policies_block($this->wsuser()));
        $check = $this->policies_check();
        $this->assertSame('error', $check->state);
        $this->assertSame(get_string('idx_check_policies_ko', 'local_coursetransfer'), $check->status);
        $this->assertStringContainsString('/local/coursetransfer/postinstall.php', $check->actionurl);
        $this->assertSame('error_wspolicynotagreed', $this->peer_call_error());

        // Repair (postinstall.php) accepts the new version for the service user.
        coursetransfer::postinstall();
        $this->assertEquals(1, $this->wsuser()->policyagreed);
        $this->assertTrue($DB->record_exists('tool_policy_acceptances', [
            'userid' => $this->wsuser()->id,
            'policyversionid' => $v2->get('id'),
            'status' => 1,
        ]));
        $this->assertSame('ok', $this->policies_check()->state);
        $this->assertNull($this->peer_call_error());
    }

    /**
     * Policies created after installing the plugin are accepted by the upgrade step.
     */
    public function test_upgrade_accepts_policies_created_later(): void {
        set_config('sitepolicyhandler', 'tool_policy');
        coursetransfer::postinstall();
        $this->publish_policy();
        $this->assertSame('error', $this->policies_check()->state);

        $this->assertTrue(local_coursetransfer_accept_ws_site_policies());

        $this->assertEquals(1, $this->wsuser()->policyagreed);
        $this->assertSame([], coursetransfer::pending_site_policies((int)$this->wsuser()->id));
        $this->assertSame('ok', $this->policies_check()->state);
    }

    /**
     * Accepted versions with the flag kept are fine; a current version not accepted yet is a warning.
     */
    public function test_pending_version_without_block(): void {
        global $DB;
        set_config('sitepolicyhandler', 'tool_policy');
        $this->publish_policy();
        coursetransfer::postinstall();
        $DB->delete_records('tool_policy_acceptances', ['userid' => $this->wsuser()->id]);

        $this->assertFalse(coursetransfer::site_policies_block($this->wsuser()));
        $this->assertSame('warn', $this->policies_check()->state);

        coursetransfer::postinstall();
        $this->assertSame('ok', $this->policies_check()->state);
    }

    /**
     * The core site policy ($CFG->sitepolicy) has no acceptance records: Repair sets policyagreed.
     */
    public function test_core_site_policy(): void {
        global $DB;
        coursetransfer::postinstall();
        $DB->set_field('user', 'policyagreed', 0, ['id' => $this->wsuser()->id]);
        $this->assertSame('ok', $this->policies_check()->state, 'No site policy: nothing to accept');

        set_config('sitepolicy', 'https://example.invalid/policy');
        $this->assertSame('error', $this->policies_check()->state);
        $this->assertSame('error_wspolicynotagreed', $this->peer_call_error());

        coursetransfer::postinstall();
        $this->assertEquals(1, $this->wsuser()->policyagreed);
        $this->assertSame('ok', $this->policies_check()->state);
    }

    /**
     * A user without the permissions to accept on behalf of others accepts nothing.
     */
    public function test_accept_requires_permission(): void {
        global $DB;
        coursetransfer::postinstall();
        $wsuserid = (int)$this->wsuser()->id;
        $this->setUser($this->getDataGenerator()->create_user());

        // Core site policy: moodle/site:config is required.
        set_config('sitepolicy', 'https://example.invalid/policy');
        $DB->set_field('user', 'policyagreed', 0, ['id' => $wsuserid]);
        $this->assertFalse(coursetransfer::accept_site_policies($wsuserid));
        $this->assertEquals(0, $this->wsuser()->policyagreed);

        // Tool_policy: its API requires tool/policy:acceptbehalf.
        set_config('sitepolicy', '');
        set_config('sitepolicyhandler', 'tool_policy');
        $this->setAdminUser();
        $this->publish_policy();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(coursetransfer::accept_site_policies($wsuserid));
        $this->assertDebuggingCalled();
        $this->assertEquals(0, $this->wsuser()->policyagreed);
        $this->assertFalse($DB->record_exists('tool_policy_acceptances', ['userid' => $wsuserid]));
    }

    /**
     * The upgrade step does not fail on a site without the service user.
     */
    public function test_upgrade_without_service_user(): void {
        set_config('sitepolicyhandler', 'tool_policy');
        $this->publish_policy();
        $this->assertFalse(local_coursetransfer_accept_ws_site_policies());
    }
}
