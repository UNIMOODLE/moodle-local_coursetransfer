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
 * restore_progress
 *
 * @package    local_coursetransfer
 * @copyright  2025 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use core\progress\base;
use dml_exception;

defined('MOODLE_INTERNAL') || die;

/**
 * Progress reporter that writes the live restore percentage (0-100) into the
 * request row, so the logs page can show how far the restore has advanced
 * (the restore runs in a separate adhoc-task process). LCT-022.
 *
 * @package    local_coursetransfer
 * @copyright  2025 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_progress extends base {

    /** @var int Request id to update. */
    protected int $requestid;

    /** @var int Timestamp of the next allowed DB write (throttle). */
    protected int $nextupdate = 0;

    /** @var int Minimum seconds between DB writes. */
    protected int $interval = 5;

    /**
     * Constructor.
     *
     * @param int $requestid Request id whose 'restored' field is updated.
     */
    public function __construct(int $requestid) {
        $this->requestid = $requestid;
    }

    /**
     * Persist the current progress proportion as a percentage, throttled to
     * one write every few seconds. Forces 100% once the section ends, since the
     * last in-progress callback is not guaranteed to report the final value.
     *
     * @return void
     * @throws dml_exception
     */
    protected function update_progress(): void {
        global $DB;
        $now = time();
        if ($this->is_in_progress_section()) {
            if ($now < $this->nextupdate) {
                return;
            }
            list($min) = $this->get_progress_proportion_range();
            $pct = max(0, min(100, (int) round($min * 100)));
            $this->nextupdate = $now + $this->interval;
        } else {
            $pct = 100;
        }
        $DB->set_field('local_coursetransfer_request', 'restored', $pct, ['id' => $this->requestid]);
        $DB->set_field('local_coursetransfer_request', 'timemodified', $now, ['id' => $this->requestid]);
    }
}
