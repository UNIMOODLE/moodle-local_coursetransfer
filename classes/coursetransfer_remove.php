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

use core\task\manager;
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
    /** @var int Default days a removed course stays in the recycle bin before the plugin purges it. */
    const RECYCLEBIN_RETENTION_DAYS = 30;

    /**
     * Seconds a removed course stays in the recycle bin before the plugin purges it (at least one day).
     *
     * @return int
     */
    public static function get_recyclebin_retention(): int {
        $days = get_config('local_coursetransfer', 'recyclebinretentiondays');
        $days = ($days === false || $days === '') ? self::RECYCLEBIN_RETENTION_DAYS : (int)$days;
        return max(1, $days) * DAYSECS;
    }

    /**
     * Whether a course of this (origin) site has a removal asked by a target still pending, itself or a parent category.
     *
     * @param int $courseid
     * @return bool
     */
    public static function has_pending_removal(int $courseid): bool {
        global $DB;
        $catids = [0];
        $categoryid = $DB->get_field('course', 'category', ['id' => $courseid]);
        $path = $categoryid ? $DB->get_field('course_categories', 'path', ['id' => $categoryid]) : '';
        foreach (explode('/', (string)$path) as $id) {
            if ((int)$id > 0) {
                $catids[] = (int)$id;
            }
        }
        [$insql, $params] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'cat');
        [$stsql, $stparams] = $DB->get_in_or_equal(
            [coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::STATUS_ERROR],
            SQL_PARAMS_NAMED,
            'st',
            false
        );
        return $DB->record_exists_select(
            coursetransfer_request::TABLE,
            "direction = :dir AND status $stsql
             AND ((type = :tcourse AND origin_course_id = :course) OR (type = :tcat AND origin_category_id $insql))",
            $params + $stparams + [
                'dir' => coursetransfer_request::DIRECTION_RESPONSE,
                'tcourse' => coursetransfer_request::TYPE_REMOVE_COURSE,
                'course' => $courseid,
                'tcat' => coursetransfer_request::TYPE_REMOVE_CATEGORY,
            ]
        );
    }

    /**
     * Move the backups served from these courses to the system context before removing them.
     *
     * Deleting a course deletes every file of its context: moved, they last the protection period of
     * cleanup_backup_files_task, counted from now (the removal).
     *
     * @param int[] $courseids
     * @return int Files moved.
     */
    public static function preserve_served_backups(array $courseids): int {
        $fs = get_file_storage();
        $sysid = \context_system::instance()->id;
        $moved = 0;
        foreach ($courseids as $courseid) {
            $context = \context_course::instance((int)$courseid, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            foreach ($fs->get_area_files($context->id, 'local_coursetransfer', 'backup', false, 'id', false) as $file) {
                $record = ['contextid' => $sysid, 'timemodified' => time()];
                if (
                    !$fs->file_exists(
                        $sysid,
                        'local_coursetransfer',
                        'backup',
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename()
                    )
                ) {
                    $fs->create_file_from_storedfile($record, $file);
                }
                $file->delete();
                $moved++;
            }
        }
        return $moved;
    }

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
        int $requestoriginid,
        int $requestdestid,
        int $courseid,
        stdClass $destsite,
        int $userid,
        ?int $nextruntime = null
    ): bool {
        $resasynctask = new remove_course_task();
        $payload = [
                'targetsiteid' => (int)$destsite->id,
                'courseid' => $courseid,
                'requestoriginid' => $requestoriginid,
                'requestdestid' => $requestdestid,
                'userid' => $userid,
        ];
        $resasynctask->set_custom_data($payload);
        if (!is_null($nextruntime)) {
            $resasynctask->set_next_run_time($nextruntime);
        }
        return manager::queue_adhoc_task($resasynctask);
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
        int $requestoriginid,
        int $requestdestid,
        int $catid,
        stdClass $destsite,
        int $userid,
        ?int $nextruntime = null
    ): bool {
        $resasynctask = new remove_category_task();
        $payload = [
                'targetsiteid' => (int)$destsite->id,
                'catid' => $catid,
                'requestoriginid' => $requestoriginid,
                'requestdestid' => $requestdestid,
                'userid' => $userid,
        ];
        $resasynctask->set_custom_data($payload);
        if (!is_null($nextruntime)) {
            $resasynctask->set_next_run_time($nextruntime);
        }
        return manager::queue_adhoc_task($resasynctask);
    }

    /**
     * Queue the purge of a removed course from the recycle bins, once the retention has passed.
     *
     * @param stdClass $course The removed course (id, shortname, category).
     * @param int|null $timeremoved When it was removed (now by default).
     * @return bool
     */
    public static function create_cleanup_course_bin_task(stdClass $course, ?int $timeremoved = null): bool {
        $timeremoved = $timeremoved ?? time();
        $resasynctask = new cleanup_course_bin_task();
        $payload = [
                'courseid' => (int)$course->id,
                'shortname' => $course->shortname,
                'categoryid' => isset($course->category) ? (int)$course->category : null,
                'timeremoved' => $timeremoved,
        ];
        $resasynctask->set_custom_data($payload);
        // Until then the course can still be restored from the recycle bin by hand.
        $resasynctask->set_next_run_time($timeremoved + self::get_recyclebin_retention());
        return (bool)manager::queue_adhoc_task($resasynctask);
    }

    /**
     * Queue the purge of a removed category from the recycle bin, once the retention has passed.
     *
     * @param int $catid
     * @param int|null $timeremoved When it was removed (now by default).
     * @return bool
     */
    public static function create_cleanup_category_bin_task(int $catid, ?int $timeremoved = null): bool {
        $timeremoved = $timeremoved ?? time();
        $resasynctask = new cleanup_category_bin_task();
        $payload = [
                'categoryid' => $catid,
                'timeremoved' => $timeremoved,
        ];
        $resasynctask->set_custom_data($payload);
        $resasynctask->set_next_run_time($timeremoved + self::get_recyclebin_retention());
        return (bool)manager::queue_adhoc_task($resasynctask);
    }
}
