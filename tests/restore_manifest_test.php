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
 * Tests for the manifest checked before removing an origin course.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;

/**
 * Tests for restore_manifest.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\restore_manifest
 */
final class restore_manifest_test extends advanced_testcase {
    /**
     * The restored course may hold more than the origin, never less; unknown modules are named.
     */
    public function test_compare(): void {
        $expected = ['sections' => 3, 'activities' => ['forum' => 2, 'nosuchmod' => 1], 'enrolments' => 5];

        $this->assertSame([], restore_manifest::compare(
            $expected,
            ['sections' => 4, 'activities' => ['forum' => 3, 'nosuchmod' => 1, 'page' => 1], 'enrolments' => 6]
        ));

        $issues = restore_manifest::compare($expected, ['sections' => 2, 'activities' => ['forum' => 2], 'enrolments' => 4]);
        $this->assertSame(
            ['sections 2/3', 'mod_nosuchmod 0/1 (module not installed on this site)', 'enrolled users 4/5'],
            $issues
        );
        $this->assertSame([], restore_manifest::compare(
            ['sections' => 1, 'activities' => [], 'enrolments' => null],
            ['sections' => 1, 'activities' => [], 'enrolments' => 0]
        ), 'Without users the enrolments are not compared');
    }

    /**
     * What a course of this site has.
     */
    public function test_of_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $this->getDataGenerator()->enrol_user($this->getDataGenerator()->create_user()->id, $course->id);

        $manifest = restore_manifest::of_course((int) $course->id);

        $this->assertSame(3, $manifest['sections']);
        $this->assertSame(['forum' => 1], $manifest['activities']);
        $this->assertSame(1, $manifest['enrolments']);
    }
}
