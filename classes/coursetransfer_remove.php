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
 * Coursetranfer Remove.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use local_coursetransfer\task\cleanup_category_bin_task;
use local_coursetransfer\task\cleanup_course_bin_task;
use local_coursetransfer\task\remove_category_task;
use local_coursetransfer\task\remove_course_task;
use stdClass;

/**
 * coursetransfer_remove
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer_remove {

    /** @var int Cleanup bin task nextruntim plus */
    const CLEANUP_BIN_TASK_NEXTRUNTIME_PLUS = 240;

    /**
     * Create task remove course.
     *
     * @param int $requestoriginid
     * @param int $requestdestid
     * @param int $courseid
     * @param stdClass $destsite
     * @param int $userid
     * @param int|null $nextruntime
     * @return bool
     */
    public static function create_task_remove_course(
            int $requestoriginid, int $requestdestid, int $courseid,
            stdClass $destsite, int $userid, int $nextruntime = null): bool {
        $resasynctask = new remove_course_task();
        $payload = [
                'targetsiteid' => $destsite->id,
                'courseid' => $courseid,
                'requestoriginid' => $requestoriginid,
                'requestdestid' => $requestdestid,
                'userid' => $userid,
        ];
        $resasynctask->set_custom_data($payload);
        if (!is_null($nextruntime)) {
            $resasynctask->set_next_run_time($nextruntime);
        }
        return \core\task\manager::queue_adhoc_task($resasynctask);
    }

    /**
     * Create task remove course.
     *
     * @param int $requestoriginid
     * @param int $requestdestid
     * @param int $catid
     * @param stdClass $destsite
     * @param int $userid
     * @param int|null $nextruntime
     * @return bool
     */
    public static function create_task_remove_category(
            int $requestoriginid, int $requestdestid, int $catid, stdClass $destsite, int $userid, int $nextruntime = null): bool {
        $resasynctask = new remove_category_task();
        $payload = [
                'targetsiteid' => $destsite->id,
                'catid' => $catid,
                'requestoriginid' => $requestoriginid,
                'requestdestid' => $requestdestid,
                'userid' => $userid,
        ];
        $resasynctask->set_custom_data($payload);
        if (!is_null($nextruntime)) {
            $resasynctask->set_next_run_time($nextruntime);
        }
        return \core\task\manager::queue_adhoc_task($resasynctask);
    }

    /**
     * Create cleanup course bin task.
     *
     * @param stdClass $course
     * @return bool
     */
    public static function create_cleanup_course_bin_task(stdClass $course): bool {
        $resasynctask = new cleanup_course_bin_task();
        $payload = [
                'courseid' => $course->id,
                'shortname' => $course->shortname,
        ];
        $resasynctask->set_custom_data($payload);
        $resasynctask->set_next_run_time(time() + self::CLEANUP_BIN_TASK_NEXTRUNTIME_PLUS);
        return \core\task\manager::queue_adhoc_task($resasynctask);
    }

    /**
     * Create cleanup category bin task.
     *
     * @param int $catid
     * @return bool
     */
    public static function create_cleanup_category_bin_task(int $catid): bool {
        $resasynctask = new cleanup_category_bin_task();
        $payload = [
                'categoryid' => $catid,
        ];
        $resasynctask->set_custom_data($payload);
        $resasynctask->set_next_run_time(time() + self::CLEANUP_BIN_TASK_NEXTRUNTIME_PLUS);
        return \core\task\manager::queue_adhoc_task($resasynctask);
    }

}
