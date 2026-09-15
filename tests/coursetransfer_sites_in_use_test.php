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
 * Tests for coursetransfer_sites::is_in_use().
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use dml_exception;

/**
 * The siteurl column of local_coursetransfer_request is TEXT: the lookup must not use a
 * plain equality condition, which the DML layer rejects (textconditionsnotallowed).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_coursetransfer\coursetransfer_sites::is_in_use
 */
final class coursetransfer_sites_in_use_test extends advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Registers a completed request coming from the given site.
     *
     * @param string $siteurl
     * @throws dml_exception
     */
    protected function add_request(string $siteurl): void {
        global $DB;

        $DB->insert_record('local_coursetransfer_request', (object) [
            'type' => coursetransfer_request::TYPE_COURSE,
            'siteurl' => $siteurl,
            'direction' => coursetransfer_request::DIRECTION_REQUEST,
            'userid' => 2,
            'status' => coursetransfer_request::STATUS_COMPLETED,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * A host with a request row is in use; a host without one is not; trailing slashes are ignored.
     */
    public function test_is_in_use(): void {
        $this->assertFalse(coursetransfer_sites::is_in_use('https://origin.example.com'));

        $this->add_request('https://origin.example.com');

        $this->assertTrue(coursetransfer_sites::is_in_use('https://origin.example.com'));
        $this->assertTrue(coursetransfer_sites::is_in_use('https://origin.example.com/'));
        $this->assertFalse(coursetransfer_sites::is_in_use('https://other.example.com'));
    }

    /**
     * Two platforms whose URLs share their first 32 characters are still different sites: the
     * comparison must not fall back to the default length of sql_compare_text(), which would
     * truncate the URL on the database engines that cast the column.
     */
    public function test_is_in_use_compares_the_whole_url(): void {
        $this->add_request('https://campusvirtual.example.edu/2025');

        $this->assertTrue(coursetransfer_sites::is_in_use('https://campusvirtual.example.edu/2025'));
        $this->assertFalse(coursetransfer_sites::is_in_use('https://campusvirtual.example.edu/2026'));
    }
}
