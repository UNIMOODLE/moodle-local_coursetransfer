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
 * Event: a course transfer request has completed.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Fired when a course transfer request (restore or remove, course or category)
 * reaches the COMPLETED status. Carries the request id, plus its type and
 * direction in `other`, so observers (e.g. local_coursetransfermanager) can
 * decide whether to notify. The plugin itself no longer sends notifications.
 *
 * @package local_coursetransfer\event
 */
class request_completed extends \core\event\base {

    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_coursetransfer_request';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_request_completed', 'local_coursetransfer');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        $type = $this->other['type'] ?? '';
        $direction = $this->other['direction'] ?? '';
        return "The course transfer request with id '{$this->objectid}' has completed " .
                "(type '{$type}', direction '{$direction}').";
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
