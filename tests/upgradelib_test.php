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
 * Tests for the upgrade helpers.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/coursetransfer/db/upgradelib.php');

/**
 * Tests for db/upgradelib.php.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     ::local_coursetransfer_strip_legacy_tokens
 * @covers     ::local_coursetransfer_strip_token
 */
final class upgradelib_test extends advanced_testcase {
    /**
     * The step of 2026112000 removes the token 2.1 stored in fileurl and nothing else.
     */
    public function test_strip_legacy_tokens(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
        $url = 'https://origin.example.com/webservice/pluginfile.php/1/local_coursetransfer/backup/9/backup.mbz';
        $legacy = $generator->create_request(['fileurl' => $url . '?token=abc123', 'origin_backup_url' => $url,
            'error_message' => 'keep ?token=me']);
        $other = $generator->create_request(['fileurl' => $url . '?forcedownload=1&TOKEN=abc&x=2']);
        $clean = $generator->create_request(['fileurl' => $url]);
        $mytoken = $generator->create_request(['fileurl' => $url . '?mytoken=1']);

        $this->assertSame(2, local_coursetransfer_strip_legacy_tokens());

        $row = coursetransfer_request::get($legacy->id);
        $this->assertSame($url, $row->fileurl);
        $this->assertSame('keep ?token=me', $row->error_message, 'Only fileurl changes');
        $this->assertSame($legacy->timemodified, $row->timemodified);
        $this->assertSame($url . '?forcedownload=1&x=2', coursetransfer_request::get($other->id)->fileurl);
        $this->assertSame($url, coursetransfer_request::get($clean->id)->fileurl);
        $this->assertSame($url . '?mytoken=1', coursetransfer_request::get($mytoken->id)->fileurl);
        $this->assertSame(0, local_coursetransfer_strip_legacy_tokens(), 'Running it again changes nothing');
    }

    /**
     * Token parameters are removed wherever they are in the query; the fragment is kept.
     */
    public function test_strip_token(): void {
        $this->assertSame('https://a/b', local_coursetransfer_strip_token('https://a/b?token=x'));
        $this->assertSame('https://a/b?y=1', local_coursetransfer_strip_token('https://a/b?token=x&y=1'));
        $this->assertSame('https://a/b?y=1#f', local_coursetransfer_strip_token('https://a/b?y=1&token=x#f'));
        $this->assertSame('https://a/b', local_coursetransfer_strip_token('https://a/b'));
    }
}
