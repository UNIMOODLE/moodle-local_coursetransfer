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
 * restore_course_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use context_system;
use core\lock\lock_config;
use core_user;
use dml_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_notification;
use local_coursetransfer\event\chained_removal_decided;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_restore;
use local_coursetransfer\restore_manifest;
use moodle_exception;
use stdClass;
use stored_file;
use Throwable;

/**
 * restore_course_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_course_task extends \core\task\adhoc_task {
    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /** @var int[] States in which a restore must not run again (LCT-035, LCT-050). */
    const FINAL_STATUSES = [
        coursetransfer_request::STATUS_COMPLETED,
        coursetransfer_request::STATUS_ERROR,
        coursetransfer_request::STATUS_INCOMPLETED,
    ];

    /** @var bool|null Result of the manifest check of this run: the origin is asked only once. */
    protected ?bool $manifestok = null;

    /**
     * Execute.
     *
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function execute(): void {
        $this->log_start("Restore Backup Course Remote Starting...");

        $fileid = (int)($this->get_custom_data()->fileid ?? 0);
        $requestid = (int)($this->get_custom_data()->requestid ?? 0);
        $request = coursetransfer_request::get($requestid);
        if (!$request) {
            $this->log_finish('Request ' . $requestid . ' not found: nothing to restore.');
            return;
        }
        if ($this->is_category_refresh()) {
            // Queued when the category could not be evaluated: nothing is restored, failures are retried by the cron.
            $this->close_category($request);
            $this->log_finish('Category of request ' . $requestid . ' evaluated again.');
            return;
        }
        if (in_array((int)$request->status, self::FINAL_STATUSES, true)) {
            $this->log_finish('Request ' . $requestid . ' already finished (status ' . $request->status . '): not restored again.');
            return;
        }
        // A retry may queue a second task while this one is still restoring the same course (LCT-038).
        $lock = lock_config::get_lock_factory('local_coursetransfer')->get_lock('restore_' . $requestid, 0);
        if (!$lock) {
            $this->log_finish('Request ' . $requestid . ' is being restored by another task.');
            return;
        }
        try {
            $this->restore($request, $fileid);
        } finally {
            $lock->release();
        }
        $this->log_finish("Restore Backup Course Remote Finishing...");
    }

    /**
     * Restore the file of a request and run what follows a completed restore.
     *
     * @param stdClass $request
     * @param int $fileid
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function restore(stdClass $request, int $fileid): void {
        $requestid = (int)$request->id;
        $file = get_file_storage()->get_file_by_id($fileid);

        // Large restores need time and memory; remove the limits for this task and
        // record any uncatchable fatal (timeout/OOM) into the request log.
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        coursetransfer_request::register_fatal_shutdown($requestid, '10499');

        if (!$file) {
            $message = 'Restore in Moodle not working beacuse File not found! :' . $fileid;
            $this->log($message);
            coursetransfer_request::update_fields($requestid, [
                'status' => coursetransfer_request::STATUS_ERROR,
                'error_code' => '11100',
                'error_message' => $message,
            ]);
            $this->refresh_category($request);
            return;
        }

        // Always kept first (same content, no extra disk): a restore over an existing course empties
        // its backup area, and an incomplete restore must not lose the .mbz.
        $this->keep_safety_copy($request, $file);
        // Mark as "restoring" so the request shows progress during the (long) restore.
        coursetransfer_request::update_fields($requestid, ['status' => coursetransfer_request::STATUS_RESTORE]);
        $result = coursetransfer_restore::restore_course_result($request, $file);
        if (!$result['success']) {
            $this->log('Restore in Moodle is Failed!');
            $this->refresh_category($request);
            return;
        }

        $this->log('Restore in Moodle Success!');
        $complete = (bool)$result['complete'];
        // Issues and manifest are recorded before COMPLETED: once every course of a category is
        // completed, another task may close it and remove the origin category.
        if (!$complete) {
            $this->log('Restore incomplete: ' . implode(', ', $result['issues']));
            coursetransfer_request::update_fields($requestid, [
                'error_code' => coursetransfer_request::CODE_RESTORE_WITH_ISSUES,
            ]);
            $this->note($requestid, 'Restore completed with issues: ' . implode(', ', $result['issues']));
        } else if ($this->removal_may_follow($request)) {
            $this->manifestok = $this->check_manifest($request);
        }
        coursetransfer_request::update_fields($requestid, ['status' => coursetransfer_request::STATUS_COMPLETED]);
        if ($complete && $this->manifestok !== false) {
            $this->delete_downloaded_backup($file);
            if (!$this->safety_copy_needed($request)) {
                $this->delete_safety_copy($requestid);
            }
        }

        // Nothing after this point may fail the task: the cron would restore the course again (LCT-035).
        try {
            $this->after_restore(coursetransfer_request::get($requestid), $complete);
        } catch (Throwable $e) {
            $this->log('After restore ERROR: ' . $e->getMessage());
            $this->note($requestid, 'After restore error: ' . $e->getMessage());
        }
    }

    /**
     * Notifications, category status and the chained removal of the origin after a completed restore.
     *
     * @param stdClass $request
     * @param bool $complete Whether the plan restored everything.
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function after_restore(stdClass $request, bool $complete): void {
        if (!is_null($request->request_category_id)) {
            $this->close_category($request);
        } else {
            $this->safely(fn() => coursetransfer_notification::send_restore_course_completed(
                $request->userid,
                $request->target_course_id
            ));
            $this->safely(fn() => coursetransfer_request::trigger_request_completed($request));
        }

        if ($request->origin_remove_course && !$request->origin_remove_category) {
            $this->remove_origin_course($request, $complete);
        }
    }

    /**
     * Evaluate the category of a course request and, when it completes now, close it.
     *
     * @param stdClass $request Course request of the category.
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function close_category(stdClass $request): void {
        $reqcat = $this->refresh_category($request);
        if (!$reqcat || !$reqcat->completednow) {
            return;
        }
        $this->safely(fn() => coursetransfer_notification::send_restore_category_completed(
            $reqcat->userid,
            $reqcat->origin_category_id
        ));
        $this->safely(fn() => coursetransfer_request::trigger_request_completed($reqcat));
        if ($reqcat->origin_remove_category) {
            $this->remove_origin_category($reqcat);
        }
    }

    /**
     * Whether this task only evaluates the category again (queued by queue_category_refresh()).
     *
     * @return bool
     */
    protected function is_category_refresh(): bool {
        return !empty($this->get_custom_data()->refreshcategory);
    }

    /**
     * Whether a complete restore of this request leads to removing its origin (so its manifest is checked).
     *
     * @param stdClass $request
     * @return bool
     */
    protected function removal_may_follow(stdClass $request): bool {
        if (!is_null($request->request_category_id)) {
            $reqcat = coursetransfer_request::get((int)$request->request_category_id);
            return $reqcat && !empty($reqcat->origin_remove_category)
                && $this->user_can((int)$reqcat->userid, 'local/coursetransfer:origin_remove_category');
        }
        return !empty($request->origin_remove_course) && empty($request->origin_remove_category)
            && $this->user_can((int)$request->userid, 'local/coursetransfer:origin_remove_course');
    }

    /**
     * Whether a user exists and holds a capability in the system context.
     *
     * @param int $userid
     * @param string $capability
     * @return bool
     */
    protected function user_can(int $userid, string $capability): bool {
        $user = core_user::get_user($userid);
        return $user && has_capability($capability, context_system::instance(), $user);
    }

    /**
     * Remove the origin course, as the user who asked for the restore, only after a complete restore.
     *
     * @param stdClass $request
     * @param bool $complete
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function remove_origin_course(stdClass $request, bool $complete): void {
        $requestid = (int)$request->id;
        if (!$complete) {
            $this->not_removed($requestid, 'Origin course not removed: the restore was not complete.');
            return;
        }
        $user = core_user::get_user((int)$request->userid);
        if (!$user || !has_capability('local/coursetransfer:origin_remove_course', context_system::instance(), $user)) {
            $this->not_removed(
                $requestid,
                'Origin course not removed: the requester lacks local/coursetransfer:origin_remove_course.'
            );
            return;
        }
        if (!($this->manifestok ?? $this->check_manifest($request))) {
            $this->not_removed($requestid, 'Origin course not removed: the restored course does not match the origin.');
            return;
        }
        $this->decided($request, 'allowed');
        $this->log('Origin Course Removing...');
        try {
            $site = coursetransfer::get_site_by_url($request->siteurl);
            $res = coursetransfer::remove_course($site, (int)$request->origin_course_id, $user);
            if (empty($res['success'])) {
                $this->not_removed($requestid, 'Origin course not removed: ' . $this->errors_text($res['errors'] ?? []));
            }
        } catch (Throwable $e) {
            $this->not_removed($requestid, 'Origin Course Removed not working. Error: ' . $e->getMessage());
        }
    }

    /**
     * Remove the origin category of a completed category request, as its requester.
     *
     * @param stdClass $reqcat
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function remove_origin_category(stdClass $reqcat): void {
        $reqcatid = (int)$reqcat->id;
        if ($reqcat->childissues > 0) {
            $this->not_removed($reqcatid, 'Origin category not removed: ' . $reqcat->childissues .
                ' course(s) restored with issues.');
            return;
        }
        if (($reqcat->childunverified ?? 0) > 0) {
            $this->not_removed($reqcatid, 'Origin category not removed: ' . $reqcat->childunverified .
                ' course(s) without a successful manifest check.');
            return;
        }
        $user = core_user::get_user((int)$reqcat->userid);
        if (!$user || !has_capability('local/coursetransfer:origin_remove_category', context_system::instance(), $user)) {
            $this->not_removed($reqcatid, 'Origin category not removed: the requester lacks ' .
                'local/coursetransfer:origin_remove_category.');
            return;
        }
        try {
            $site = coursetransfer::get_site_by_url($reqcat->siteurl);
            $changed = $this->origin_category_changed($reqcat, $site, $user);
            if ($changed !== null) {
                $this->not_removed($reqcatid, 'Origin category not removed: ' . $changed);
                return;
            }
            $this->decided($reqcat, 'allowed');
            $this->log('Origin Category Removing...');
            $res = coursetransfer::remove_category($site, (int)$reqcat->origin_category_id, $user);
            if (empty($res['success'])) {
                $this->not_removed($reqcatid, 'Origin category not removed: ' . $this->errors_text($res['errors'] ?? []));
            }
        } catch (Throwable $e) {
            $this->not_removed($reqcatid, 'Origin Category Removed not working. Error: ' . $e->getMessage());
        }
    }

    /**
     * Why the origin category no longer matches what this request copied (R3), or null when it does.
     *
     * The recursive removal would also take what was not copied: hidden subcategories or courses, and
     * courses created after the request. An origin that cannot be checked is a reason too.
     *
     * @param stdClass $reqcat
     * @param stdClass $site Origin site.
     * @param stdClass $user Requester.
     * @return string|null
     */
    protected function origin_category_changed(stdClass $reqcat, stdClass $site, stdClass $user): ?string {
        $tree = json_decode((string)$reqcat->origin_category_tree);
        $expected = coursetransfer_request::snapshot_course_ids($reqcat);
        if ($expected === null) {
            return 'there is no snapshot of the origin category to compare with.';
        }
        $hidden = coursetransfer_request::hidden_text($tree);
        if ($hidden !== '') {
            return 'when it was copied, the origin category had ' . $hidden . ' the requester could not see.';
        }
        try {
            $res = (new request($site))->origin_get_category_detail((int)$reqcat->origin_category_id, $user);
        } catch (Throwable $e) {
            return 'the origin category could not be checked: ' . $e->getMessage();
        }
        if (!$res->success || !is_object($res->data) || !isset($res->data->courses)) {
            return 'the origin category could not be checked: ' . $this->errors_text($res->errors ?? []);
        }
        $hidden = coursetransfer_request::hidden_text($res->data);
        if ($hidden !== '') {
            return 'the origin category has ' . $hidden . ' the requester cannot see.';
        }
        $new = [];
        foreach ((array)$res->data->courses as $course) {
            if (!in_array((int)($course->id ?? 0), $expected, true)) {
                $new[] = (int)($course->id ?? 0);
            }
        }
        return $new ? 'the origin category has courses that were not copied (ids ' . implode(', ', $new) . ').' : null;
    }

    /**
     * Compare the restored course with the origin manifest (verifymanifest) and record a mismatch.
     *
     * @param stdClass $request
     * @return bool Whether the origin may be removed.
     */
    protected function check_manifest(stdClass $request): bool {
        if (!restore_manifest::enabled()) {
            return true;
        }
        try {
            $user = core_user::get_user((int)$request->userid);
            $site = coursetransfer::get_site_by_url($request->siteurl);
            $issues = $user ? restore_manifest::verify(coursetransfer_request::get((int)$request->id), $site, $user)
                : ['requester not found'];
        } catch (Throwable $e) {
            $issues = ['manifest not checked: ' . $e->getMessage()];
        }
        if (!$issues) {
            return true;
        }
        coursetransfer_request::update_fields((int)$request->id, [
            'error_code' => coursetransfer_request::CODE_MANIFEST_MISMATCH,
        ]);
        $this->note((int)$request->id, 'Manifest mismatch: ' . implode(', ', $issues));
        return false;
    }

    /**
     * Refresh the status of the category request of a course request, if any.
     *
     * When it cannot be evaluated now (lock taken, database error) a refresh is queued: otherwise the
     * last course to finish could leave its category open, or its removal undecided, for good.
     *
     * @param stdClass $request
     * @return stdClass|null
     */
    protected function refresh_category(stdClass $request): ?stdClass {
        if (is_null($request->request_category_id)) {
            return null;
        }
        try {
            $reqcat = $this->evaluate_category((int)$request->request_category_id);
            $this->log('Update Status Category Request');
            return $reqcat;
        } catch (Throwable $e) {
            $this->log('Update Status Category Request ERROR: ' . $e->getMessage());
            if ($this->is_category_refresh()) {
                throw $e;
            }
            $this->queue_category_refresh($request);
            return null;
        }
    }

    /**
     * Evaluate a category request under its lock (coursetransfer_request::update_status_request_cat()).
     *
     * @param int $reqcatid
     * @return stdClass|null
     */
    protected function evaluate_category(int $reqcatid): ?stdClass {
        return coursetransfer_request::update_status_request_cat($reqcatid);
    }

    /**
     * Queue a task that evaluates the category of a course request again (retried by the cron if it fails).
     *
     * @param stdClass $request Course request.
     */
    protected function queue_category_refresh(stdClass $request): void {
        try {
            $task = new self();
            $task->set_custom_data(['requestid' => (int)$request->id, 'fileid' => 0, 'refreshcategory' => 1]);
            $task->set_next_run_time(time() + MINSECS);
            \core\task\manager::queue_adhoc_task($task, true);
            $this->log('Category of request ' . $request->id . ' will be evaluated again.');
        } catch (Throwable $e) {
            $this->log('Category refresh not queued: ' . $e->getMessage());
        }
    }

    /**
     * Whether this restore (or its category) asked to remove the origin afterwards.
     *
     * @param stdClass $request
     * @return bool
     */
    protected function origin_removal_requested(stdClass $request): bool {
        if (!empty($request->origin_remove_course)) {
            return true;
        }
        if (!is_null($request->request_category_id)) {
            $reqcat = coursetransfer_request::get((int)$request->request_category_id);
            return $reqcat && !empty($reqcat->origin_remove_category);
        }
        return false;
    }

    /**
     * Whether the safety copy must outlive a complete restore: origin removal or keepsafetycopy asked.
     *
     * @param stdClass $request
     * @return bool
     */
    protected function safety_copy_needed(stdClass $request): bool {
        return $this->origin_removal_requested($request) || coursetransfer_request::keeps_safety_copy($request);
    }

    /**
     * Keep a copy of the backup outside the course before restoring it.
     *
     * The restore over an existing course empties its file areas, the downloaded .mbz included. The copy
     * shares the stored content (no extra disk) and lasts as cleanup_backup_files_task decides.
     *
     * @param stdClass $request
     * @param stored_file $file
     */
    protected function keep_safety_copy(stdClass $request, stored_file $file): void {
        try {
            $fs = get_file_storage();
            $record = ['contextid' => context_system::instance()->id, 'component' => 'local_coursetransfer',
                'filearea' => 'safetycopy', 'itemid' => (int)$request->id, 'filepath' => '/',
                'filename' => $file->get_filename()];
            if (
                !$fs->file_exists(
                    $record['contextid'],
                    $record['component'],
                    $record['filearea'],
                    $record['itemid'],
                    $record['filepath'],
                    $record['filename']
                )
            ) {
                $fs->create_file_from_storedfile($record, $file);
            }
        } catch (Throwable $e) {
            $this->log('Safety copy not kept: ' . $e->getMessage());
        }
    }

    /**
     * Delete the safety copy of a request whose restore is complete and verified, when nothing needs it.
     *
     * @param int $requestid
     */
    protected function delete_safety_copy(int $requestid): void {
        try {
            get_file_storage()->delete_area_files(
                context_system::instance()->id,
                'local_coursetransfer',
                'safetycopy',
                $requestid
            );
        } catch (Throwable $e) {
            $this->log('Safety copy not deleted: ' . $e->getMessage());
        }
    }

    /**
     * Delete the .mbz the download task stored in the target course, once the restore is complete and verified.
     *
     * @param stored_file $file
     */
    protected function delete_downloaded_backup(stored_file $file): void {
        if (
            $file->get_component() !== 'backup' || $file->get_filearea() !== 'course'
                || strpos($file->get_filename(), 'local_coursetransfer_') !== 0
        ) {
            return;
        }
        try {
            $file->delete();
        } catch (Throwable $e) {
            $this->log('Downloaded backup not deleted: ' . $e->getMessage());
        }
    }

    /**
     * Record that the origin was not removed, keeping the request completed.
     *
     * @param int $requestid
     * @param string $message
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function not_removed(int $requestid, string $message): void {
        $request = coursetransfer_request::get($requestid);
        if ($request && empty($request->error_code)) {
            coursetransfer_request::update_fields($requestid, ['error_code' => coursetransfer_request::CODE_ORIGIN_NOT_REMOVED]);
        }
        $this->note($requestid, $message);
        if ($request) {
            $this->decided($request, 'held: ' . $message);
        }
    }

    /**
     * Audit the decision on the chained removal of the origin.
     *
     * @param stdClass $request Course or category request.
     * @param string $result allowed, or held with the reason.
     */
    protected function decided(stdClass $request, string $result): void {
        $iscat = (int)$request->type === coursetransfer_request::TYPE_CATEGORY;
        $this->safely(fn() => chained_removal_decided::fire((int)$request->id, (int)$request->userid, [
            'kind' => $iscat ? 'category' : 'course',
            'id' => (int)($iscat ? $request->origin_category_id : $request->origin_course_id),
            'site' => (string)$request->siteurl,
            'result' => $result,
        ]));
    }

    /**
     * Append a message to the request log, keeping what was already there.
     *
     * @param int $requestid
     * @param string $message
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function note(int $requestid, string $message): void {
        $this->log($message);
        $request = coursetransfer_request::get($requestid);
        if (!$request) {
            return;
        }
        $previous = trim((string)$request->error_message);
        coursetransfer_request::update_fields($requestid, [
            'error_message' => $previous === '' ? $message : $previous . ' | ' . $message,
        ]);
    }

    /**
     * Errors of a remote response as text.
     *
     * @param array $errors
     * @return string
     */
    protected function errors_text(array $errors): string {
        $texts = [];
        foreach ($errors as $error) {
            $error = (object)$error;
            $texts[] = ($error->code ?? '') . ': ' . ($error->msg ?? '');
        }
        return $texts ? implode(' | ', $texts) : 'unknown error';
    }

    /**
     * Run a step whose failure must not stop the rest (notifications, observers).
     *
     * @param callable $step
     */
    protected function safely(callable $step): void {
        try {
            $step();
        } catch (Throwable $e) {
            $this->log('ERROR: ' . $e->getMessage());
        }
    }
}
