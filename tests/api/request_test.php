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
 * Tests for the TLS options and transport errors of the calls to paired platforms.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\api;

use advanced_testcase;

/**
 * Tests for request::ssl_options() and request::transport_error().
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\api\request
 */
final class request_test extends advanced_testcase {
    /**
     * The peer certificate is verified unless sslverifypeer is turned off.
     */
    public function test_ssl_options(): void {
        $this->resetAfterTest();
        $on = ['CURLOPT_SSL_VERIFYPEER' => 1, 'CURLOPT_SSL_VERIFYHOST' => 2];
        $this->assertSame($on, request::ssl_options(), 'Not set yet (site not upgraded): on');
        set_config('sslverifypeer', 1, 'local_coursetransfer');
        $this->assertSame($on, request::ssl_options());
        set_config('sslverifypeer', 0, 'local_coursetransfer');
        $this->assertSame(['CURLOPT_SSL_VERIFYPEER' => 0, 'CURLOPT_SSL_VERIFYHOST' => 0], request::ssl_options());
    }

    /**
     * Certificate errors say what happened and what to do; the others keep the generic message.
     */
    public function test_transport_error(): void {
        foreach ([60, 51, 77, 58, 83, 90, 91] as $errno) {
            $text = request::transport_error($errno, 'SSL certificate problem', 'https://origin.example.com');
            $this->assertSame(get_string('error_ws_tls', 'local_coursetransfer', (object) [
                'msg' => 'SSL certificate problem', 'errno' => $errno, 'host' => 'https://origin.example.com',
            ]), $text, (string) $errno);
            $this->assertStringContainsString('sslverifypeer', $text);
            $this->assertStringContainsString('https://origin.example.com', $text);
        }
        foreach ([6, 7, 28, 35] as $errno) {
            $this->assertSame(get_string('error_ws_curl', 'local_coursetransfer', (object) [
                'msg' => 'x', 'errno' => $errno,
            ]), request::transport_error($errno, 'x', 'https://origin.example.com'), (string) $errno);
        }
    }
}
