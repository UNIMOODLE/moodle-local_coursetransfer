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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * Event: a course transfer request has been deleted from the history.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\event;

/**
 * Fired when a user deletes a finished request from the history, so it is known who did it and what it was (R4).
 *
 * @package local_coursetransfer\event
 */
class request_deleted extends \core\event\base {
    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_coursetransfer_request';
    }

    /**
     * Event name.
     * @return string
     */
    public static function get_name() {
        return get_string('event_request_deleted', 'local_coursetransfer');
    }

    /**
     * Description.
     * @return string
     */
    public function get_description() {
        $type = $this->other['type'] ?? '';
        $direction = $this->other['direction'] ?? '';
        $status = $this->other['status'] ?? '';
        return "The user with id '{$this->userid}' deleted the course transfer request with id '{$this->objectid}' " .
                "(type '{$type}', direction '{$direction}', status '{$status}') from the history.";
    }
}
