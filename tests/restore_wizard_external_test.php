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
 * restore_wizard_external_test
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use local_coursetransfer\external\frontend\restore_wizard_external;
use local_coursetransfer\external\frontend\sites_external;


/**
 * Tests for the restore/remove wizard web services: get_sites happy path and
 * the per-context capability gates that guard every submit endpoint (LCT-012).
 *
 * The submit endpoints delegate the actual transfer to remote web services over
 * HTTP, which is not reachable from unit tests; the pipeline itself is covered
 * by coursetransfer_restore_course_test. Here we assert the security contract:
 * an unauthorised caller is rejected before anything happens.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\frontend\restore_wizard_external
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class restore_wizard_external_test extends advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Provision the service token and register one origin site, as admin.
     */
    protected function setup_site(): void {
        global $CFG;
        $this->setAdminUser();
        $token = coursetransfer::postinstall();
        sites_external::site_add('origin', $CFG->wwwroot, $token);
    }

    /**
     * get_sites lists the configured origin platforms with the expected shape.
     */
    public function test_get_sites_returns_configured_sites(): void {
        $this->setup_site();

        $res = restore_wizard_external::get_sites();

        $this->assertArrayHasKey('sites', $res);
        $this->assertCount(1, $res['sites']);
        $site = $res['sites'][0];
        foreach (['id', 'name', 'host', 'connected', 'status'] as $key) {
            $this->assertArrayHasKey($key, $site);
        }
    }

    /**
     * With no platforms configured, get_sites returns an empty list (not an error).
     */
    public function test_get_sites_empty(): void {
        $this->setAdminUser();

        $res = restore_wizard_external::get_sites();

        $this->assertArrayHasKey('sites', $res);
        $this->assertCount(0, $res['sites']);
    }

    /**
     * get_sites is gated by local/coursetransfer:origin_restore.
     */
    public function test_get_sites_requires_capability(): void {
        $this->setup_site();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        restore_wizard_external::get_sites();
    }

    /**
     * submit (admin restore) is gated by origin_restore in the system context.
     */
    public function test_submit_requires_capability(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        restore_wizard_external::submit(1, 'course', [], []);
    }

    /**
     * remove_submit is gated by origin_remove_* in the system context.
     */
    public function test_remove_submit_requires_capability(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        restore_wizard_external::remove_submit(1, 'course', [10]);
    }

    /**
     * submit_course is gated in the target course context (teacher flow).
     */
    public function test_submit_course_requires_capability(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\moodle_exception::class);
        restore_wizard_external::submit_course(1, 10, (int) $course->id);
    }

    /**
     * submit_category is gated in the target category context (teacher flow).
     */
    public function test_submit_category_requires_capability(): void {
        $category = $this->getDataGenerator()->create_category();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\moodle_exception::class);
        restore_wizard_external::submit_category(1, 10, (int) $category->id);
    }
}
