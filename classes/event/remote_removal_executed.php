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
 * Event: This site removed a course or category asked for by a platform.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\event;

/**
 * This site removed a course or category asked for by a platform.
 *
 * Other: kind (course or category), id (on the origin), site, result (or reason).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remote_removal_executed extends \core\event\base {
    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_coursetransfer_request';
    }

    /**
     * Trigger it for a request.
     *
     * @param int $requestid
     * @param int $userid Requester.
     * @param array $other kind, id, site, result.
     */
    public static function fire(int $requestid, int $userid, array $other): void {
        static::create([
            'context' => \context_system::instance(),
            'objectid' => $requestid,
            'relateduserid' => $userid ?: null,
            'other' => $other,
        ])->trigger();
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_remote_removal_executed', 'local_coursetransfer');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        $kind = $this->other['kind'] ?? '';
        $id = $this->other['id'] ?? '';
        $site = $this->other['site'] ?? '';
        $result = $this->other['result'] ?? '';
        return "The {$kind} with id '{$id}' was removed for {$site}, as asked by the user with id "
            . "'{$this->relateduserid}' (request '{$this->objectid}').";
    }

    /**
     * URL to the request detail.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/coursetransfer/log.php', ['id' => $this->objectid]);
    }
}
