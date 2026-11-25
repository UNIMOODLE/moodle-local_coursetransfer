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
 * CLI helpers.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;

/**
 * The CLI never prints a web service token (H4).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\cli_helper
 */
final class cli_helper_test extends advanced_testcase {
    /**
     * The token query parameter is masked; the rest of the URL is kept.
     */
    public function test_mask_token(): void {
        $url = 'https://o.example.com/webservice/pluginfile.php/1/backup/course/0/x.mbz';
        $this->assertSame($url . '?token=***', cli_helper::mask_token($url . '?token=0123456789abcdef'));
        $this->assertSame($url . '?a=1&wstoken=***&b=2', cli_helper::mask_token($url . '?a=1&wstoken=abc&b=2'));
        $this->assertSame($url, cli_helper::mask_token($url));
        $this->assertSame('42', cli_helper::mask_token('42'));
    }
}
