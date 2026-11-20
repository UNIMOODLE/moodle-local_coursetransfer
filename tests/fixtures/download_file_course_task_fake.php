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
 * Test double of the download task: writes a fixed body instead of calling the origin.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

/**
 * Download task whose transfer writes a fixed body instead of calling the origin.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class download_file_course_task_fake extends download_file_course_task {
    /** @var array URL and token of each transfer. */
    public static $calls = [];

    /** @var string Body written by the transfer. */
    public static $body = '';

    /** @var string Last temporary file. */
    public static $tmpfile = '';

    /** @var int Last size limit. */
    public static $maxbytes = 0;

    /**
     * Write the body as the transfer would.
     *
     * @param string $url
     * @param string $token
     * @param string $tmpfile
     * @param int $maxbytes
     * @return array
     */
    protected function fetch(string $url, string $token, string $tmpfile, int $maxbytes): array {
        self::$calls[] = [$url, $token];
        self::$tmpfile = $tmpfile;
        self::$maxbytes = $maxbytes;
        file_put_contents($tmpfile, self::$body);
        return ['result' => true, 'httpcode' => 200, 'errno' => 0, 'error' => ''];
    }
}
