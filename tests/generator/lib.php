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
 * Data generator for local_coursetransfer.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_sites;

/**
 * Creates the plugin's own records: remote sites (origin and target) and transfer requests.
 *
 * Shared by PHPUnit, Behat and the stack seeders, so a test never builds these rows by hand.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_coursetransfer_generator extends component_generator_base {
    /** @var int Sequence for default site hosts and tokens. */
    protected int $sitecount = 0;

    /**
     * Reset the generator state between tests.
     */
    public function reset(): void {
        $this->sitecount = 0;
    }

    /**
     * Create an origin or target site.
     *
     * The host is stored without its trailing slash, the same as the site form does, so
     * lookups by host find it.
     *
     * @param string $type 'origin' or 'target'
     * @param array|stdClass $record Any column of the site table; the rest get defaults
     * @return stdClass the inserted row
     */
    public function create_site(string $type, $record = []): stdClass {
        global $DB, $USER;

        if (!in_array($type, ['origin', 'target'], true)) {
            throw new coding_exception("Site type must be 'origin' or 'target', got '$type'");
        }
        $record = (array) $record;
        $this->sitecount++;
        $now = time();

        $row = (object) array_merge([
            'name' => "Site {$this->sitecount}",
            'host' => "https://site{$this->sitecount}.example.com",
            'token' => md5("local_coursetransfer_generator_{$this->sitecount}"),
            'userid' => $USER->id ?: get_admin()->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $record);
        $row->host = coursetransfer_sites::clean_host($row->host);

        $row->id = $DB->insert_record('local_coursetransfer_' . $type, $row);
        return $DB->get_record('local_coursetransfer_' . $type, ['id' => $row->id], '*', MUST_EXIST);
    }

    /**
     * Create a transfer request.
     *
     * Defaults describe a course restore requested from this site (direction request),
     * not started yet.
     *
     * @param array|stdClass $record Any column of the request table; the rest get defaults
     * @return stdClass the inserted row
     */
    public function create_request($record = []): stdClass {
        global $DB, $USER;

        $record = (array) $record;
        $now = time();

        $row = (object) array_merge([
            'type' => coursetransfer_request::TYPE_COURSE,
            'siteurl' => 'https://origin.example.com',
            'direction' => coursetransfer_request::DIRECTION_REQUEST,
            'userid' => $USER->id ?: get_admin()->id,
            'status' => coursetransfer_request::STATUS_NOT_STARTED,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $record);
        $row->siteurl = coursetransfer_sites::clean_host($row->siteurl);

        $row->id = $DB->insert_record('local_coursetransfer_request', $row);
        return $DB->get_record('local_coursetransfer_request', ['id' => $row->id], '*', MUST_EXIST);
    }
}
