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
 * upgrade_test
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/local/coursetransfer/db/upgrade.php');

/**
 * The upgrade can be run again over a database that already has its changes.
 *
 * An upgrade interrupted halfway (a failure, a timeout, stale caches) is relaunched over a schema that already has
 * some of its columns and data: no step may fail because of that.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     ::xmldb_local_coursetransfer_upgrade
 */
final class upgrade_test extends advanced_testcase {
    /**
     * Every step from the oldest supported version runs over the current schema without failing or changing it.
     */
    public function test_upgrade_steps_are_rerunnable(): void {
        global $DB;
        $this->resetAfterTest();

        $request = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_request(
            ['siteurl' => 'https://origin.example.com', 'fileurl' => 'https://origin.example.com/x.mbz']
        );
        $columns = array_keys($DB->get_columns('local_coursetransfer_request'));

        // Pretend the site is on the oldest version this upgrade starts from (2.0, before its first step).
        set_config('version', 2024040400, 'local_coursetransfer');
        ob_start();
        try {
            $result = xmldb_local_coursetransfer_upgrade(2024040400);
        } finally {
            ob_end_clean();
        }
        // The 2.0 steps report with debugging() the old tables and fields they no longer find.
        $this->resetDebugging();

        $this->assertTrue($result);
        $this->assertSame($columns, array_keys($DB->get_columns('local_coursetransfer_request', false)));
        $row = $DB->get_record('local_coursetransfer_request', ['id' => $request->id]);
        $this->assertSame('https://origin.example.com', $row->siteurl);
        $this->assertSame('https://origin.example.com/x.mbz', $row->fileurl);
        $this->assertTrue($DB->record_exists('capabilities', ['name' => 'local/coursetransfer:webservice']));
    }
}
