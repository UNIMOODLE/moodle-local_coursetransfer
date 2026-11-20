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
 * Scheduled purge of the course backups the plugin keeps.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use context;
use local_coursetransfer\coursetransfer_remove;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_sites;
use stdClass;
use Throwable;

/**
 * Deletes the .mbz files of finished transfers once nothing can need them.
 *
 * On the origin, the backup served to a target (area local_coursetransfer/backup): kept while its course has
 * a removal pending and, once removed (the file moves to the system context), for the protection period.
 * On the target, the safety copy of each restore (area local_coursetransfer/safetycopy): kept while its
 * origin has a removal pending, for the protection period after it, and, when the request asked to keep it,
 * until its origin is removed. The protection period is max(backup_files_retention, recyclebinretentiondays).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_backup_files_task extends \core\task\scheduled_task {
    use \core\task\logging_trait;

    /** @var int Default days a finished transfer keeps its backup. */
    const RETENTION_DAYS = 7;

    /** @var int[] Statuses of a removal that will not change any more. */
    const REMOVAL_FINAL = [coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::STATUS_ERROR];

    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('cleanup_backup_files_task', 'local_coursetransfer');
    }

    /**
     * Seconds a finished transfer keeps its backup (at least one day).
     *
     * @return int
     */
    public static function get_retention(): int {
        $days = get_config('local_coursetransfer', 'backup_files_retention');
        $days = ($days === false || $days === '') ? self::RETENTION_DAYS : (int)$days;
        return max(1, $days) * DAYSECS;
    }

    /**
     * Seconds a copy is kept after its origin was removed: at least as long as the recycle bin.
     *
     * @return int
     */
    public static function get_protection(): int {
        return max(self::get_retention(), coursetransfer_remove::get_recyclebin_retention());
    }

    /**
     * Execute.
     */
    public function execute(): void {
        $this->log_start('Cleanup backup files - Starting...');
        $this->cleanup_served(time() - self::get_retention());
        $this->cleanup_downloaded(time() - self::get_protection());
        $this->log_finish('Cleanup backup files - Finishing...');
    }

    /**
     * Origin: backups of finished (completed or failed) or deleted requests, never of one in progress.
     *
     * @param int $cutoff
     */
    protected function cleanup_served(int $cutoff): void {
        global $DB;
        $sql = "SELECT DISTINCT f.contextid, f.itemid
                  FROM {files} f
             LEFT JOIN {local_coursetransfer_request} r ON r.id = f.itemid
                 WHERE f.component = 'local_coursetransfer' AND f.filearea = 'backup' AND f.filename <> '.'
                   AND ((r.id IS NULL AND f.timecreated < :cutoff1)
                        OR (r.direction = :direction AND r.status IN (:completed, :error) AND r.timemodified < :cutoff2))";
        $rs = $DB->get_recordset_sql($sql, [
            'cutoff1' => $cutoff, 'cutoff2' => $cutoff, 'direction' => coursetransfer_request::DIRECTION_RESPONSE,
            'completed' => coursetransfer_request::STATUS_COMPLETED, 'error' => coursetransfer_request::STATUS_ERROR,
        ]);
        $fs = get_file_storage();
        foreach ($rs as $area) {
            try {
                $reason = $this->served_kept($area);
                if ($reason !== null) {
                    $this->log('Backup of request ' . $area->itemid . ' kept: ' . $reason);
                    continue;
                }
                $fs->delete_area_files($area->contextid, 'local_coursetransfer', 'backup', $area->itemid);
                $this->log('Backup of request ' . $area->itemid . ' deleted.');
            } catch (Throwable $e) {
                $this->log('Backup of request ' . $area->itemid . ' not deleted: ' . $e->getMessage());
            }
        }
        $rs->close();
    }

    /**
     * Why a served backup must stay, or null.
     *
     * @param stdClass $area contextid and itemid of the file area.
     * @return string|null
     */
    protected function served_kept(stdClass $area): ?string {
        global $DB;
        if ((int)$area->contextid === (int)\context_system::instance()->id) {
            // Moved here when its course was removed (coursetransfer_remove::preserve_served_backups()).
            $moved = (int)$DB->get_field_sql(
                "SELECT MAX(timemodified) FROM {files}
                  WHERE contextid = :ctx AND component = 'local_coursetransfer' AND filearea = 'backup' AND itemid = :item",
                ['ctx' => $area->contextid, 'item' => $area->itemid]
            );
            return $moved > time() - self::get_protection() ? 'its course was removed less than the protection period ago' : null;
        }
        $context = context::instance_by_id((int)$area->contextid, IGNORE_MISSING);
        if (!$context || (int)$context->contextlevel !== CONTEXT_COURSE) {
            return null;
        }
        return coursetransfer_remove::has_pending_removal((int)$context->instanceid) ? 'its course has a removal pending' : null;
    }

    /**
     * Target: safety copies of finished restores, unless their origin still needs them (copy_kept()).
     *
     * @param int $cutoff
     */
    protected function cleanup_downloaded(int $cutoff): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal([coursetransfer_request::STATUS_COMPLETED,
            coursetransfer_request::STATUS_ERROR, coursetransfer_request::STATUS_INCOMPLETED], SQL_PARAMS_NAMED, 'st');
        $sql = "SELECT DISTINCT f.contextid, f.itemid
                  FROM {files} f
             LEFT JOIN {local_coursetransfer_request} r ON r.id = f.itemid
                 WHERE f.component = 'local_coursetransfer' AND f.filearea = 'safetycopy' AND f.filename <> '.'
                   AND ((r.id IS NULL AND f.timecreated < :cutoff1) OR (r.status $insql AND r.timemodified < :cutoff2))";
        $rs = $DB->get_recordset_sql($sql, $params + ['cutoff1' => $cutoff, 'cutoff2' => $cutoff]);
        $fs = get_file_storage();
        foreach ($rs as $area) {
            try {
                $request = coursetransfer_request::get((int)$area->itemid);
                $reason = $request ? $this->copy_kept($request) : null;
                if ($reason !== null) {
                    $this->log('Safety copy of request ' . $area->itemid . ' kept: ' . $reason);
                    continue;
                }
                $fs->delete_area_files($area->contextid, 'local_coursetransfer', 'safetycopy', $area->itemid);
                $this->log('Safety copy of request ' . $area->itemid . ' deleted.');
            } catch (Throwable $e) {
                $this->log('Safety copy of request ' . $area->itemid . ' not deleted: ' . $e->getMessage());
            }
        }
        $rs->close();
    }

    /**
     * Why the safety copy of a restore request must stay, or null.
     *
     * @param stdClass $request Course restore request of this (target) site.
     * @return string|null
     */
    protected function copy_kept(stdClass $request): ?string {
        global $DB;
        $catids = [(int)$request->origin_category_id];
        if (!empty($request->request_category_id)) {
            $reqcat = coursetransfer_request::get((int)$request->request_category_id);
            $catids[] = $reqcat ? (int)$reqcat->origin_category_id : 0;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_unique($catids), SQL_PARAMS_NAMED, 'cat');
        $removals = $DB->get_records_select(
            'local_coursetransfer_request',
            "direction = :dir
             AND ((type = :tcourse AND origin_course_id = :course) OR (type = :tcat AND origin_category_id $insql))",
            $params + [
                'dir' => coursetransfer_request::DIRECTION_REQUEST,
                'tcourse' => coursetransfer_request::TYPE_REMOVE_COURSE,
                'course' => (int)$request->origin_course_id,
                'tcat' => coursetransfer_request::TYPE_REMOVE_CATEGORY,
            ],
            '',
            'id, siteurl, status, timemodified'
        );
        $site = coursetransfer_sites::clean_host((string)$request->siteurl);
        $removed = false;
        foreach ($removals as $removal) {
            if (coursetransfer_sites::clean_host((string)$removal->siteurl) !== $site) {
                continue;
            }
            if (!in_array((int)$removal->status, self::REMOVAL_FINAL, true)) {
                return 'its origin has a removal pending (request ' . $removal->id . ')';
            }
            if ((int)$removal->status === coursetransfer_request::STATUS_COMPLETED) {
                if ((int)$removal->timemodified > time() - self::get_protection()) {
                    return 'its origin was removed less than the protection period ago (request ' . $removal->id . ')';
                }
                $removed = true;
            }
        }
        if (!$removed && coursetransfer_request::keeps_safety_copy($request)) {
            return 'the request asked to keep it until its origin is removed';
        }
        return null;
    }
}
