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
 * Tests for the way platform hosts are stored and looked up.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use dml_exception;
use moodle_exception;

/**
 * A host is looked up without its trailing slash, so it has to be stored that way too, and the
 * comparison has to cover the whole URL and not just the prefix that fits the default length.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_coursetransfer\coursetransfer_sites::get_by_host
 * @covers \local_coursetransfer\coursetransfer_request::insert_or_update
 */
final class coursetransfer_host_normalisation_test extends advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Registers a target site.
     *
     * @param string $host
     * @return int the new site id
     * @throws dml_exception
     */
    protected function add_target_site(string $host): int {
        global $DB;

        return $DB->insert_record('local_coursetransfer_target', (object) [
            'name' => 'Site ' . $host,
            'host' => $host,
            'token' => 'a-token',
            'userid' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * A request written with a trailing slash is stored without it, so the "in use" lookup —which
     * always normalises the host it receives— finds it.
     *
     * @throws dml_exception|moodle_exception
     */
    public function test_request_siteurl_is_stored_without_its_trailing_slash(): void {
        global $DB;

        $id = coursetransfer_request::insert_or_update((object) [
            'type' => coursetransfer_request::TYPE_COURSE,
            'siteurl' => 'https://origin.example.com/',
            'direction' => coursetransfer_request::DIRECTION_REQUEST,
            'userid' => 2,
            'status' => coursetransfer_request::STATUS_COMPLETED,
        ]);

        $this->assertSame(
            'https://origin.example.com',
            $DB->get_field('local_coursetransfer_request', 'siteurl', ['id' => $id])
        );
        $this->assertTrue(coursetransfer_sites::is_in_use('https://origin.example.com'));
    }

    /**
     * The site is found whether or not the caller passes the trailing slash.
     *
     * @throws dml_exception|moodle_exception
     */
    public function test_get_by_host_ignores_the_trailing_slash(): void {
        $siteid = $this->add_target_site('https://target.example.com');

        $this->assertEquals($siteid, coursetransfer_sites::get_by_host('target', 'https://target.example.com')->id);
        $this->assertEquals($siteid, coursetransfer_sites::get_by_host('target', 'https://target.example.com/')->id);
    }

    /**
     * Two platforms whose URLs share their first 32 characters are still different sites: comparing
     * only that prefix would hand the caller the wrong row, token included.
     *
     * @throws dml_exception|moodle_exception
     */
    public function test_get_by_host_compares_the_whole_url(): void {
        $first = $this->add_target_site('https://campusvirtual.example.edu/2025');
        $second = $this->add_target_site('https://campusvirtual.example.edu/2026');

        $this->assertEquals(
            $first,
            coursetransfer_sites::get_by_host('target', 'https://campusvirtual.example.edu/2025')->id
        );
        $this->assertEquals(
            $second,
            coursetransfer_sites::get_by_host('target', 'https://campusvirtual.example.edu/2026')->id
        );
    }
}
