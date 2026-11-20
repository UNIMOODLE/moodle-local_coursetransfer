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
 * Tests for the local_coursetransfer data generator.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use coding_exception;

/**
 * Tests for the local_coursetransfer data generator.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursetransfer_generator
 */
final class generator_test extends advanced_testcase {
    /**
     * Sites get defaults, a host without trailing slash and are found by the plugin's own lookup.
     */
    public function test_create_site(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \local_coursetransfer_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');

        $origin = $generator->create_site('origin', ['host' => 'https://origin.example.com/']);
        $target = $generator->create_site('target');

        $this->assertSame('https://origin.example.com', $origin->host);
        $this->assertNotEmpty($origin->token);
        $this->assertEquals(get_admin()->id, $origin->userid);
        $this->assertEquals($origin->id, coursetransfer_sites::get_by_host('origin', 'https://origin.example.com/')->id);
        $this->assertEquals($target->id, coursetransfer_sites::get('target', $target->id)->id);
        $this->assertNotSame($origin->token, $target->token);
    }

    /**
     * An unknown site type is a programming error.
     */
    public function test_create_site_rejects_unknown_type(): void {
        $this->resetAfterTest();
        $this->expectException(coding_exception::class);
        $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_site('destiny');
    }

    /**
     * Requests default to a course restore not started yet, and keep any column given.
     */
    public function test_create_request(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \local_coursetransfer_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');

        $default = $generator->create_request();
        $this->assertEquals(coursetransfer_request::TYPE_COURSE, $default->type);
        $this->assertEquals(coursetransfer_request::DIRECTION_REQUEST, $default->direction);
        $this->assertEquals(coursetransfer_request::STATUS_NOT_STARTED, $default->status);

        $error = $generator->create_request([
            'type' => coursetransfer_request::TYPE_CATEGORY,
            'siteurl' => 'https://origin.example.com/',
            'status' => coursetransfer_request::STATUS_ERROR,
            'error_code' => '1',
        ]);
        $this->assertEquals(coursetransfer_request::TYPE_CATEGORY, $error->type);
        $this->assertSame('https://origin.example.com', $error->siteurl);
        $this->assertEquals(coursetransfer_request::STATUS_ERROR, $error->status);
        $this->assertTrue(coursetransfer_sites::is_in_use('https://origin.example.com'));
    }
}
