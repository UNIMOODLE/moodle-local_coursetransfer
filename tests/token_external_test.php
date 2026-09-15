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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * token_external_test
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use local_coursetransfer\external\frontend\token_external;
use local_coursetransfer\factory\user;


/**
 * Tests for the token lifecycle web services of the summary screen.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\frontend\token_external
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class token_external_test extends advanced_testcase {
    /**
     * Set up: reset DB and act as a site administrator (the token web services
     * require moodle/site:config).
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Get the web service user record (false if it does not exist yet).
     *
     * @return \stdClass|false
     */
    protected function ws_user() {
        return \core_user::get_user_by_username(user::USERNAME_WS);
    }

    /**
     * Count the permanent tokens of the web service user for this plugin's service.
     *
     * @return int
     */
    protected function token_count(): int {
        global $DB;
        $wsuser = $this->ws_user();
        if (!$wsuser) {
            return 0;
        }
        $serviceid = $DB->get_field('external_services', 'id', ['component' => 'local_coursetransfer']);
        if (!$serviceid) {
            return 0;
        }
        return $DB->count_records('external_tokens', [
                'userid' => $wsuser->id,
                'tokentype' => EXTERNAL_TOKEN_PERMANENT,
                'externalserviceid' => $serviceid,
        ]);
    }

    /**
     * Creating the token provisions the service user and a single permanent token.
     */
    public function test_create(): void {
        $this->assertFalse($this->ws_user());

        $res = token_external::create();

        $this->assertTrue($res['success']);
        $this->assertNotEmpty($res['token']);
        $this->assertEmpty($res['errors']);
        $this->assertNotFalse($this->ws_user());
        $this->assertEquals(1, $this->token_count());
    }

    /**
     * Calling create twice reuses the same token (does not duplicate it).
     */
    public function test_create_is_idempotent(): void {
        $first = token_external::create();
        $second = token_external::create();

        $this->assertTrue($second['success']);
        $this->assertSame($first['token'], $second['token']);
        $this->assertEquals(1, $this->token_count());
    }

    /**
     * Revoking removes the token, leaving the site unreachable until re-created.
     */
    public function test_revoke(): void {
        token_external::create();
        $this->assertEquals(1, $this->token_count());

        $res = token_external::revoke();

        $this->assertTrue($res['success']);
        $this->assertEmpty($res['token']);
        $this->assertEquals(0, $this->token_count());
    }

    /**
     * Regenerating replaces the current token with a different one.
     */
    public function test_regenerate(): void {
        $create = token_external::create();
        $old = $create['token'];

        $res = token_external::regenerate();

        $this->assertTrue($res['success']);
        $this->assertNotEmpty($res['token']);
        $this->assertNotSame($old, $res['token']);
        $this->assertEquals(1, $this->token_count());
    }

    /**
     * Regenerating when no token exists yet still provisions a working token.
     */
    public function test_regenerate_from_scratch(): void {
        $this->assertFalse($this->ws_user());

        $res = token_external::regenerate();

        $this->assertTrue($res['success']);
        $this->assertNotEmpty($res['token']);
        $this->assertEquals(1, $this->token_count());
    }

    /**
     * The user factory revoke/regenerate helpers behave as expected directly.
     */
    public function test_user_factory_helpers(): void {
        token_external::create();
        $wsuser = $this->ws_user();

        $regenerated = user::regenerate_token((int) $wsuser->id);
        $this->assertNotEmpty($regenerated);
        $this->assertEquals(1, $this->token_count());

        $removed = user::revoke_token((int) $wsuser->id);
        $this->assertTrue($removed);
        $this->assertEquals(0, $this->token_count());

        // Revoking again reports nothing was removed.
        $this->assertFalse(user::revoke_token((int) $wsuser->id));
    }
}
