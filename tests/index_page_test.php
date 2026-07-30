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
 * index_page_test
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use local_coursetransfer\factory\user;
use local_coursetransfer\output\index_page;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the summary screen renderable (integration status checks).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\output\index_page
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class index_page_test extends advanced_testcase {

    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Export the renderable through the plugin renderer.
     *
     * @return \stdClass
     */
    protected function export(): \stdClass {
        global $PAGE;
        $PAGE->set_context(\context_system::instance());
        $output = $PAGE->get_renderer('local_coursetransfer');
        return (new index_page())->export_for_template($output);
    }

    /**
     * Map the integration checks by their font-awesome icon → state.
     *
     * @param \stdClass $data
     * @return array
     */
    protected function checks_by_icon(\stdClass $data): array {
        $map = [];
        foreach ($data->checks as $check) {
            $map[$check->faicon] = $check->state;
        }
        return $map;
    }

    /**
     * With no token/user provisioned, the screen reports the empty state and
     * the relevant checks fail.
     */
    public function test_no_token_state(): void {
        $data = $this->export();

        $this->assertFalse($data->hastoken);
        $this->assertTrue($data->notoken);
        $this->assertSame('', $data->token);
        $this->assertSame(user::USERNAME_WS, $data->username_ws);

        $checks = $this->checks_by_icon($data);
        $this->assertSame('error', $checks['fa-key']);            // Token.
        $this->assertSame('error', $checks['fa-user-circle-o']);  // Service user exists.
        $this->assertSame('error', $checks['fa-user-o']);         // Service user active.
        $this->assertSame('error', $checks['fa-shield']);         // Capabilities.
    }

    /**
     * After postinstall the token exists and every core check is green.
     */
    public function test_after_postinstall_state(): void {
        coursetransfer::postinstall();

        $data = $this->export();

        $this->assertTrue($data->hastoken);
        $this->assertFalse($data->notoken);
        $this->assertNotEmpty($data->token);
        $this->assertNotEmpty($data->tokencreated);

        $checks = $this->checks_by_icon($data);
        $this->assertSame('ok', $checks['fa-key']);            // Token.
        $this->assertSame('ok', $checks['fa-plug']);           // Web services (REST).
        $this->assertSame('ok', $checks['fa-cubes']);          // External service + authorised.
        $this->assertSame('ok', $checks['fa-user-circle-o']);  // Service user exists.
        $this->assertSame('ok', $checks['fa-user-o']);         // Service user active.
        $this->assertSame('ok', $checks['fa-shield']);         // Capabilities.
    }

    /**
     * The template context always exposes the expected structure.
     */
    public function test_structure(): void {
        $data = $this->export();

        $this->assertCount(8, $data->checks);
        $this->assertCount(5, $data->pluginlinks);
        $this->assertCount(2, $data->moodlelinks);
        $this->assertNotEmpty($data->logourl);
        foreach ($data->checks as $check) {
            $this->assertContains($check->state, ['ok', 'warn', 'error']);
        }
    }
}
