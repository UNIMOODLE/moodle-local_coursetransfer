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
 * detail_page_test
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use advanced_testcase;
use ReflectionMethod;
use stdClass;

/**
 * Tests of the request detail page.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\output\detail_page
 */
final class detail_page_test extends advanced_testcase {
    /**
     * Call a protected method of a detail page built for the given request.
     *
     * @param stdClass $record Request row.
     * @param string $method
     * @param array $args
     * @return mixed
     */
    private function call(stdClass $record, string $method, array $args) {
        $page = new detail_page($record, 'admin');
        return (new ReflectionMethod($page, $method))->invoke($page, ...$args);
    }

    /**
     * Only the activities selected in the wizard are marked, and a section with some of them is partial (LCT-121).
     *
     * The wizard stores a section as selected as soon as one of its activities is.
     */
    public function test_sections_mark_only_the_selected_activities(): void {
        $this->resetAfterTest();
        $record = (object) ['id' => 1, 'origin_activities' => json_encode([
            ['sectionname' => 'Section 1', 'selected' => false, 'activities' => [
                ['name' => 'Announcements', 'modname' => 'forum', 'selected' => false],
            ]],
            ['sectionname' => 'Section 2', 'selected' => true, 'activities' => [
                ['name' => 'Questions', 'modname' => 'forum', 'selected' => false],
                ['name' => 'Debate', 'modname' => 'forum', 'selected' => true],
            ]],
            ['sectionname' => 'Section 3', 'selected' => true, 'activities' => [
                ['name' => 'Final task', 'modname' => 'assign', 'selected' => true],
            ]],
        ])];

        $sections = $this->call($record, 'build_sections', [$record]);

        $this->assertFalse($sections[0]->on);
        $this->assertFalse($sections[0]->partial);
        $this->assertFalse($sections[0]->activities[0]->on);

        $this->assertFalse($sections[1]->on);
        $this->assertTrue($sections[1]->partial);
        $this->assertSame([false, true], array_column($sections[1]->activities, 'on'));
        // The activity type is shown with its translated name.
        $this->assertSame(get_string('modulename', 'mod_forum'), $sections[1]->activities[1]->type);

        $this->assertTrue($sections[2]->on);
        $this->assertFalse($sections[2]->partial);
        $this->assertTrue($sections[2]->activities[0]->on);
    }
}
