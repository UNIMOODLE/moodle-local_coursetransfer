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
 * Test double of the restore task.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use local_coursetransfer\coursetransfer_request;
use stdClass;

/**
 * Restore task that records the request status when the manifest is checked, and can fail the category evaluation.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_course_task_probe extends restore_course_task {
    /** @var int|null Status of the request when its manifest was checked. */
    public static $statusatcheck = null;

    /** @var bool Fail the evaluation of the category as a lock timeout would. */
    public static $failevaluation = false;

    /**
     * Record the status, then check.
     *
     * @param stdClass $request
     * @return bool
     */
    protected function check_manifest(stdClass $request): bool {
        self::$statusatcheck = (int) coursetransfer_request::get((int) $request->id)->status;
        return parent::check_manifest($request);
    }

    /**
     * Fail like a lock timeout when asked.
     *
     * @param int $reqcatid
     * @return stdClass|null
     */
    protected function evaluate_category(int $reqcatid): ?stdClass {
        if (self::$failevaluation) {
            throw new \moodle_exception('error_locktimeout', 'local_coursetransfer', '', $reqcatid);
        }
        return parent::evaluate_category($reqcatid);
    }
}
