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
 *
 * Coursetransfer.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use coding_exception;
use context;
use context_course;
use context_module;
use context_system;
use core_course_category;
use core_privacy\local\sitepolicy\manager as sitepolicy_manager;
use core_user;
use course_modinfo;
use dml_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\api\response;
use local_coursetransfer\factory\category;
use local_coursetransfer\factory\course;
use local_coursetransfer\factory\role;
use local_coursetransfer\factory\user;
use local_coursetransfer\models\configuration_category;
use local_coursetransfer\models\configuration_course;
use moodle_exception;
use moodle_url;
use stdClass;
use stored_file;
use Throwable;
use tool_policy\api as policyapi;
use tool_policy\policy_version;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/local/coursetransfer/classes/task/create_backup_course_task.php');

/**
 * Coursetransfer.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer {
    /** @var int Maximum category subtree depth walked/recreated (guards against cycles). */
    const MAX_TREE_DEPTH = 25;

    /** @var string[] Fields User */
    const FIELDS_USER = ['username', 'email', 'userid', 'idnumber'];

    /** @var string[][] Status */
    const STATUS = [
        0 => ['shortname' => 'error', 'alert' => 'danger'],
        1 => ['shortname' => 'not_started', 'alert' => 'warning'],
        10 => ['shortname' => 'in_progress', 'alert' => 'primary'],
        30 => ['shortname' => 'in_backup', 'alert' => 'primary'],
        50 => ['shortname' => 'download', 'alert' => 'info'],
        70 => ['shortname' => 'downloaded', 'alert' => 'info'],
        80 => ['shortname' => 'restore', 'alert' => 'secondary'],
        90 => ['shortname' => 'incompleted', 'alert' => 'warning'],
        100 => ['shortname' => 'completed', 'alert' => 'success'],
    ];

    /** @var stdClass Course */
    protected stdClass $course;

    /** @var request Request */
    protected request $request;

    /**
     * constructor.
     *
     * @param stdClass $course
     */
    public function __construct(stdClass $course) {
        $this->course = $course;
    }

    /**
     * Get Origin Sites.
     *
     * @return array
     * @throws dml_exception
     */
    public static function get_origin_sites(): array {
        $items = [];
        $records = coursetransfer_sites::list('origin');
        foreach ($records as $record) {
            $items[$record->id] = $record->host;
        }
        return $items;
    }

    /**
     * Origin Get Courses?.
     *
     * @return response
     * @throws dml_exception
     */
    public function origin_get_courses(): response {
        global $USER;
        return $this->request->origin_get_courses($USER);
    }

    /**
     * Verify Target Site.
     *
     * @param string $targetsite
     * @return array
     * @throws moodle_exception
     */
    public static function verify_target_site(string $targetsite): array {
        $res = new stdClass();
        $res->host = $targetsite;
        try {
            // Throws when the host is not registered: turn it into the
            // structured 18001 error instead of leaking the raw exception.
            $record = coursetransfer_sites::get_by_host('target', $targetsite);
        } catch (moodle_exception $e) {
            $record = false;
        }
        if ($record && isset($record->token)) {
            $res->token = $record->token;
            $res->id = $record->id;
            return
            [
                'success' => true,
                'data' => $res,
                'error' =>
                    [
                    'code' => '',
                    'msg' => '',
                    ],
            ];
        } else {
            return
            [
                'success' => false,
                'data' => new stdClass(),
                'error' =>
                        [
                                'code' => '18001',
                                'msg' => get_string('site_not_found', 'local_coursetransfer'),
                        ],
            ];
        }
    }

    /**
     * Auth User.
     *
     * @param string $field
     * @param string $value
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function auth_user(string $field, string $value): array {
        global $DB;
        if (!in_array($field, self::FIELDS_USER)) {
            return [
                'success' => false,
                'data' => new stdClass(),
                'error' => [
                    'code' => '17001',
                    'msg' => get_string('field_not_valid', 'local_coursetransfer'),
                ],
            ];
        }
        if ($value === '' || $value === null) {
            return [
                'success' => false,
                'data' => new stdClass(),
                'error' => [
                    'code' => '17003',
                    'msg' => get_string('user_not_found', 'local_coursetransfer'),
                ],
            ];
        }
        // The 'userid' field exposed in the UI maps to the 'id' column in the user table.
        $column = ($field === 'userid') ? 'id' : $field;
        // Only a live local account: a deleted, remote or duplicated one would act as somebody else (B2).
        global $CFG;
        $found = $DB->get_records(
            'user',
            [$column => $value, 'deleted' => 0, 'mnethostid' => $CFG->mnet_localhost_id],
            'id',
            '*',
            0,
            2
        );
        if (count($found) > 1) {
            return [
                'success' => false,
                'data' => new stdClass(),
                'error' => [
                    'code' => '17004',
                    'msg' => get_string('user_not_unique', 'local_coursetransfer', $field),
                ],
            ];
        }
        $res = reset($found);
        if ($res && (!empty($res->suspended) || $res->auth === 'nologin')) {
            // A suspended account can not log in here: it can not back up or remove courses from a peer either.
            return [
                'success' => false,
                'data' => new stdClass(),
                'error' => [
                    'code' => '17005',
                    'msg' => get_string('user_not_active', 'local_coursetransfer'),
                ],
            ];
        }
        if (!$res) {
            return [
                'success' => false,
                'data' => new stdClass(),
                'error' => [
                    'code' => '17002',
                    'msg' => get_string('user_not_found', 'local_coursetransfer'),
                ],
            ];
        }
        $user = core_user::get_user($res->id);
        if (!self::has_course($user)) {
            return [
                'success' => false,
                'data' => new stdClass(),
                'error' => [
                    'code' => '17001',
                    'msg' => get_string('user_does_not_have_courses', 'local_coursetransfer'),
                ],
            ];
        }
        return [
            'success' => true,
            'data' => $user,
            'error' => [
                'code' => '',
                'msg' => '',
            ],
        ];
    }

    /**
     * Get the estimated backup size of a course, in bytes.
     *
     * Approximation of the backup payload: the storage footprint of the course files
     * (course context + all descendant module/block contexts). Files are deduplicated by
     * contenthash (mirroring how Moodle stores files on disk and pools them inside the
     * .mbz), and backup/automated-backup fileareas are excluded. It ignores DB/XML content
     * and compression, so it will not match the real .mbz size exactly; it is meant as an
     * orientation before selecting a course. The real size is recorded after the backup
     * completes (see create_backup_course_task).
     *
     * @param int $courseid
     * @return int Estimated size in bytes.
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_backup_size_estimated_int(int $courseid): int {
        global $DB;
        $context = context_course::instance($courseid);
        // Cover the course context and every descendant context (modules, blocks) in a
        // single query, deduplicating identical files by contenthash.
        $sql = "SELECT COALESCE(SUM(sub.filesize), 0)
                  FROM (
                        SELECT DISTINCT f.contenthash, f.filesize
                          FROM {files} f
                          JOIN {context} ctx ON ctx.id = f.contextid
                         WHERE (ctx.id = :ctxid OR " . $DB->sql_like('ctx.path', ':ctxpath') . ")
                           AND f.filename <> '.'
                           AND f.filearea NOT IN ('backup', 'automated')
                       ) sub";
        $params = [
            'ctxid' => $context->id,
            'ctxpath' => $context->path . '/%',
        ];
        return (int) $DB->get_field_sql($sql, $params);
    }

    /**
     * Get the estimated backup size of a course, formatted in MB.
     *
     * Thin formatting wrapper around {@see get_backup_size_estimated_int()}.
     *
     * @param int $courseid
     * @return string Estimated size in MB (e.g. "12,345"), or "0" when empty.
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_backup_size_estimated(int $courseid): string {
        $filesize = self::get_backup_size_estimated_int($courseid);
        if ($filesize === 0) {
            return '0';
        }
        return number_format($filesize / 1000000, 3, ',', ' ');
    }

    /**
     * Get Sections With Activities
     *
     * @param int $courseid
     * @return array
     * @throws moodle_exception
     */
    public static function get_sections_with_activities(int $courseid): array {
        $finalsections = [];
        /** @var course_modinfo $modinfo */
        $modinfo = get_fast_modinfo($courseid);
        $sections = $modinfo->get_section_info_all();
        $course = get_course($courseid);
        foreach ($sections as $section) {
            $finalsection = [
                'sectionnum' => $section->section,
                'sectionid' => $section->id,
                'sectionname' => is_null($section->name) ?
                    get_string('sectionname', 'format_' . $course->format) . ' ' . $section->section : $section->name,
                'activities' => self::get_activities_by_section($modinfo, $section->id),
            ];
            $finalsections[] = $finalsection;
        }
        return $finalsections;
    }

    /**
     * Get Activities by Section
     *
     * @param course_modinfo $modinfo
     * @param int $section
     * @return array
     */
    public static function get_activities_by_section(course_modinfo $modinfo, int $section): array {
        $activities = [];
        $modules = $modinfo->get_cms();
        foreach ($modules as $module) {
            if ((int)$module->section === $section && !$module->deletioninprogress) {
                $activity = [
                    'cmid' => $module->id,
                    'name' => $module->name,
                    'instance' => $module->instance,
                    'modname' => $module->modname,
                ];
                $activities[] = $activity;
            }
        }
        return $activities;
    }

    /**
     * Get Site by position.
     *
     * @param int $id
     * @param string $type
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_site_by_position(int $id, string $type = 'origin'): stdClass {
        $res = new stdClass();
        $record = coursetransfer_sites::get($type, $id);
        if (isset($record->host) && isset($record->token)) {
            $res->host = $record->host;
            $res->token = $record->token;
        } else {
            throw new moodle_exception($type . ' site not valid: ' . $id);
        }
        return $res;
    }

    /**
     * Get site by URL
     *
     * @param string $url
     * @param string $type
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_site_by_url(string $url, string $type = 'origin'): stdClass {
        $res = new stdClass();
        $record = coursetransfer_sites::get_by_host($type, $url);
        if (isset($record->host) && isset($record->token)) {
            $res->host = $record->host;
            $res->token = $record->token;
        } else {
            throw new moodle_exception('Origin site not valid: ' . $url);
        }
        return $res;
    }

    /**
     * Create backupfile.
     *
     * @param int $courseid
     * @param stored_file $file
     * @param int $requestoriginid
     * @return stdClass
     */
    public static function create_backupfile_url(int $courseid, stored_file $file, int $requestoriginid): stdClass {
        $res = new stdClass();
        $error = '';
        $url = '';
        $filesize = 0;

        try {
            $context = context_course::instance($courseid);
            $fs = get_file_storage();
            $timestamp = time();

            $filerecord = [
                    'contextid' => $context->id,
                    'component' => 'local_coursetransfer',
                    'filearea' => 'backup',
                    'itemid' => $requestoriginid,
                    'filepath' => '/',
                    'filename' => 'backup.mbz',
                    'timecreated' => $timestamp,
                    'timemodified' => $timestamp,
            ];
            $storedfile = $fs->create_file_from_storedfile($filerecord, $file);

            $filesize = $storedfile->get_filesize();
            $file->delete();

            // Make the link.
            $fileurl = moodle_url::make_webservice_pluginfile_url(
                $storedfile->get_contextid(),
                $storedfile->get_component(),
                $storedfile->get_filearea(),
                $storedfile->get_itemid(),
                $storedfile->get_filepath(),
                $storedfile->get_filename()
            );
            $success = true;
            $url = $fileurl->out(true);
            $sizeconfig = (int)get_config('local_coursetransfer', 'target_restore_course_max_size');
            // 0 (or empty) is no limit, as the target reads it: it rejected every backup here.
            if ($sizeconfig > 0 && $filesize > ($sizeconfig * 1000000)) {
                $success = false;
                $error = get_string('backupsize_larger', 'local_coursetransfer');
                $error .= ': (' . ($filesize / 1000000) . ' MB) > ' . $sizeconfig . ' MB';
                // Nobody will download a rejected backup: do not keep it.
                $storedfile->delete();
                $url = '';
            }
        } catch (Throwable $e) {
            $success = false;
            $error = $e->getMessage();
        }

        $res->fileurl = $url;
        $res->success = $success;
        $res->error = $error;
        $res->filesize = $filesize;

        return $res;
    }

    /**
     * Restore Course.
     *
     * @param stdClass $user
     * @param stdClass $site
     * @param int $targetcourseid
     * @param int $origincourseid
     * @param configuration_course $configuration
     * @param array $sections
     * @return array
     */
    public static function restore_course(
        stdClass $user,
        stdClass $site,
        int $targetcourseid,
        int $origincourseid,
        configuration_course $configuration,
        array $sections = []
    ): array {

        try {
            return self::restore_course_unity($user, $site, $targetcourseid, $origincourseid, $configuration, $sections);
        } catch (Throwable $e) {
            $error = [
                    'code' => '10010',
                    'msg' => $e->getMessage(),
            ];
            $errors[] = $error;
            return [
                    'success' => false,
                    'errors' => $errors,
            ];
        }
    }

    /** @var int Seconds without movement after which a course request in progress may be retried (#45). */
    const RETRY_STALLED_AFTER = HOURSECS;

    /** @var int The same while the request waits for the backup of the origin, which may take hours (A5). */
    const RETRY_STALLED_ORIGIN_AFTER = 6 * HOURSECS;

    /**
     * Whether Retry applies to a course request of this site: in error, incomplete or stalled.
     *
     * A request still moving is left alone. One without changes for RETRY_STALLED_AFTER is offered too: the
     * origin may still be busy with its backup, but its late answer is ignored once the request is relaunched.
     *
     * @param stdClass $request
     * @return bool
     */
    public static function can_retry(stdClass $request): bool {
        if (
            (int)$request->type !== coursetransfer_request::TYPE_COURSE
                || (int)$request->direction !== coursetransfer_request::DIRECTION_REQUEST
        ) {
            return false;
        }
        $status = (int)$request->status;
        if ($status === coursetransfer_request::STATUS_COMPLETED) {
            return false;
        }
        // A course of a category restore is relaunched as a loose request and its category would never close:
        // only the re-restore of its downloaded backup applies to it (A6).
        $downloaded = in_array($status, [coursetransfer_request::STATUS_DOWNLOADED, coursetransfer_request::STATUS_RESTORE], true);
        if (!empty($request->request_category_id) && !$downloaded) {
            return false;
        }
        if (in_array($status, [coursetransfer_request::STATUS_ERROR, coursetransfer_request::STATUS_INCOMPLETED], true)) {
            return true;
        }
        // Stalled: counted from the scheduled time when there is one, and longer while the origin makes its backup,
        // which has no progress to report (A5).
        $since = max((int)$request->timemodified, self::schedule_seconds($request));
        $waitingorigin = in_array($status, [coursetransfer_request::STATUS_NOT_STARTED,
            coursetransfer_request::STATUS_IN_PROGRESS], true);
        return time() - $since > ($waitingorigin ? self::RETRY_STALLED_ORIGIN_AFTER : self::RETRY_STALLED_AFTER);
    }

    /**
     * Scheduled time of a request in seconds (0 when not scheduled); older requests stored milliseconds.
     *
     * @param stdClass $request
     * @return int
     */
    public static function schedule_seconds(stdClass $request): int {
        $value = (int)($request->origin_schedule_datetime ?? 0);
        return $value > 100000000000 ? intdiv($value, 1000) : $value;
    }

    /**
     * Retry ("refresh") a non-completed course restore request.
     *
     * Behaviour depends on the current state (LCT-023): if a downloaded `.mbz`
     * is still available (states downloaded/restore) the restore is re-queued
     * with it; otherwise the whole operation is relaunched from the parameters
     * stored in the request. Only course restore requests (type course,
     * direction request) are supported.
     *
     * @param stdClass $request The request to retry.
     * @return array {success: bool, mode?: string, errors: array, data?: array}
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function restore_retry(stdClass $request): array {
        // One retry at a time per request: a double click or two administrators would relaunch twice (B3).
        $lock = \core\lock\lock_config::get_lock_factory('local_coursetransfer')->get_lock('retry_' . (int)$request->id, 0);
        if (!$lock) {
            return ['success' => false, 'errors' => [
                    ['code' => '10026', 'msg' => get_string('retry_in_progress', 'local_coursetransfer')]]];
        }
        try {
            // Decided on the stored request, not on the copy the page loaded before the confirmation.
            $current = coursetransfer_request::get((int)$request->id);
            return $current ? self::restore_retry_locked($current) : ['success' => false, 'errors' => [
                    ['code' => '10022', 'msg' => get_string('request_not_found', 'local_coursetransfer')]]];
        } finally {
            $lock->release();
        }
    }

    /**
     * Retry a request, holding its retry lock.
     *
     * @param stdClass $request
     * @return array
     */
    private static function restore_retry_locked(stdClass $request): array {
        if (
            (int)$request->type !== coursetransfer_request::TYPE_COURSE
                || (int)$request->direction !== coursetransfer_request::DIRECTION_REQUEST
        ) {
            return ['success' => false, 'errors' => [
                    ['code' => '10020', 'msg' => get_string('retry_not_supported', 'local_coursetransfer')]]];
        }
        if ((int)$request->status === coursetransfer_request::STATUS_COMPLETED) {
            return ['success' => false, 'errors' => [
                    ['code' => '10021', 'msg' => get_string('retry_already_completed', 'local_coursetransfer')]]];
        }

        // Anti-duplicate: relaunching while a healthy adhoc task is queued/running would
        // duplicate the operation (double restore, races). So (LCT-023):
        // - block if there is an ACTIVE task (faildelay == 0: queued or running normally);
        // - if the related tasks are only FAILING/backing off (faildelay > 0), remove them
        // and proceed — recovering a stuck task is exactly what the retry is for.
        global $DB;
        $stucktaskids = [];
        foreach (coursetransfer_request::get_related_adhoc_tasks((int)$request->id) as $task) {
            // A failing task may be running its next attempt right now (timestarted): replacing it
            // would restore twice at the same time (LCT-038).
            if ((int)$task->faildelay === 0 || !empty($task->timestarted)) {
                return ['success' => false, 'errors' => [
                        ['code' => '10023', 'msg' => get_string('retry_task_running', 'local_coursetransfer')]]];
            }
            $stucktaskids[] = $task->id;
        }
        if (empty($stucktaskids) && !self::can_retry($request)) {
            // A request still moving (no failing task, recent changes) is not relaunched: it would run the
            // same transfer twice (#45).
            return ['success' => false, 'errors' => [
                    ['code' => '10024', 'msg' => get_string('retry_not_stalled', 'local_coursetransfer')]]];
        }
        if (!empty($stucktaskids)) {
            $DB->delete_records_list('task_adhoc', 'id', $stucktaskids);
        }

        // 1. Re-restore: if the .mbz is already downloaded, just re-queue the restore.
        $status = (int)$request->status;
        if (
            $status === coursetransfer_request::STATUS_DOWNLOADED
                || $status === coursetransfer_request::STATUS_RESTORE
        ) {
            // A restore over an existing course empties its backup area: the safety copy is the same .mbz.
            $file = self::find_downloaded_backup($request) ?? self::find_safety_copy($request);
            if ($file) {
                $request->status = coursetransfer_request::STATUS_DOWNLOADED;
                $request->error_code = null;
                $request->error_message = null;
                coursetransfer_request::insert_or_update($request, $request->id);
                coursetransfer_restore::create_task_restore_course($request, $file);
                return ['success' => true, 'mode' => 'rerestore',
                        'data' => ['requestid' => (int)$request->id], 'errors' => []];
            }
        }

        // 2. Full relaunch from the stored parameters: not for a course of a category restore (A6).
        if (!empty($request->request_category_id)) {
            return ['success' => false, 'errors' => [
                    ['code' => '10025', 'msg' => get_string('retry_category_course', 'local_coursetransfer')]]];
        }
        $user = core_user::get_user((int)$request->userid);
        if (!$user) {
            return ['success' => false, 'errors' => [
                    ['code' => '10022', 'msg' => get_string('user_not_found', 'local_coursetransfer')]]];
        }
        $site = self::get_site_by_url($request->siteurl);
        $configuration = new configuration_course(
            (int)$request->target_target,
            (bool)$request->target_remove_enrols,
            (bool)$request->target_remove_groups,
            (bool)$request->origin_enrolusers,
            (bool)$request->origin_remove_course,
            !empty($request->origin_schedule_datetime) ? (int)$request->origin_schedule_datetime : null,
            (string)($request->origin_remove_activities ?? ''),
            coursetransfer_request::keeps_safety_copy($request)
        );
        $sections = !empty($request->origin_activities) ? (array) json_decode($request->origin_activities, true) : [];
        // Superseded before relaunching: a late answer of the origin for it is then ignored (B3).
        $request->status = coursetransfer_request::STATUS_ERROR;
        $request->error_code = null;
        $request->error_message = 'Relaunching';
        coursetransfer_request::insert_or_update($request, $request->id);
        $res = self::restore_course(
            $user,
            $site,
            (int)$request->target_course_id,
            (int)$request->origin_course_id,
            $configuration,
            $sections
        );
        if (!empty($res['success'])) {
            $newid = $res['data']['requestid'] ?? '';
            coursetransfer_request::update_fields((int)$request->id, ['error_message' => 'Relaunched as request #' . $newid]);
            $res['mode'] = 'relaunch';
        } else {
            // Not relaunched: the request keeps its error, with why the relaunch failed, and can be retried again.
            $first = $res['errors'][0] ?? [];
            $why = is_array($first) ? trim(($first['code'] ?? '') . ' ' . ($first['msg'] ?? '')) : '';
            coursetransfer_request::update_fields((int)$request->id, ['error_message' => 'Relaunch failed: ' . $why]);
        }
        return $res;
    }

    /**
     * Find the most recent `.mbz` downloaded into the target course for a request.
     *
     * @param stdClass $request
     * @return stored_file|null
     * @throws dml_exception|coding_exception
     */
    private static function find_downloaded_backup(stdClass $request): ?stored_file {
        $context = context_course::instance((int)$request->target_course_id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'backup', 'course', false, 'timemodified DESC', false);
        $prefix = 'local_coursetransfer_' . (int)$request->origin_course_id . '_';
        foreach ($files as $file) {
            if (str_starts_with($file->get_filename(), $prefix)) {
                return $file;
            }
        }
        return null;
    }

    /**
     * The safety copy of the backup of a request, if it is still kept.
     *
     * @param stdClass $request
     * @return stored_file|null
     */
    private static function find_safety_copy(stdClass $request): ?stored_file {
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_coursetransfer',
            'safetycopy',
            (int)$request->id,
            'timemodified DESC',
            false
        );
        return $files ? reset($files) : null;
    }

    /**
     * Remove Course.
     *
     * @param stdClass $site
     * @param int $origincourseid
     * @param stdClass|null $user
     * @param int|null $nextruntime
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function remove_course(
        stdClass $site,
        int $origincourseid,
        ?stdClass $user = null,
        ?int $nextruntime = null
    ): array {

        $errors = [];

        // 1. Request DB.
        $requestobject = coursetransfer_request::set_request_remove_course($site, $origincourseid, $user, $nextruntime);

        // 2. Call CURL Origin Backup Course.
        $request = new request($site);
        $res = $request->origin_remove_course($requestobject->id, $origincourseid, $nextruntime, $user);
        // Audit every removal asked of another platform, accepted or not.
        $first = $res->errors[0] ?? null;
        try {
            \local_coursetransfer\event\remote_removal_requested::fire((int)$requestobject->id, (int)$requestobject->userid, [
                'kind' => 'course', 'id' => $origincourseid, 'site' => (string)$site->host,
                'result' => $res->success ? 'accepted' : 'rejected: ' . ($first->code ?? '') . ' ' . ($first->msg ?? ''),
            ]);
        } catch (Throwable $e) {
            debugging('remote_removal_requested not triggered: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        // 3. Success or Errors.
        if ($res->success) {
            // 4a. Update Request DB Completed.
            $requestobject->status = coursetransfer_request::STATUS_IN_PROGRESS;
            $requestobject->origin_course_fullname = $res->data->course_fullname;
            $requestobject->origin_course_shortname = $res->data->course_shortname;
            $requestobject->origin_course_idnumber = $res->data->course_idnumber;
            $requestobject->origin_category_id = $res->data->course_category_id;
            $requestobject->origin_category_name = $res->data->course_category_name;
            $requestobject->origin_category_idnumber = $res->data->course_category_idnumber;
            coursetransfer_request::insert_or_update($requestobject, $requestobject->id);
            $success = true;
        } else {
            // 4b. Update Request DB Errors.
            $err = $res->errors;
            $errors = $res->errors;
            $requestobject->status = coursetransfer_request::STATUS_ERROR;
            $requestobject->error_code = $err[0]->code;
            $requestobject->error_message = $err[0]->msg;
            coursetransfer_request::insert_or_update($requestobject, $requestobject->id);
            $success = false;
        }
        return [
                'success' => $success,
                'errors' => $errors,
                'data' => [
                        'requestid' => $requestobject->id,
                ],
        ];
    }

    /**
     * Remove Category.
     *
     * @param stdClass $site
     * @param int $origincatid
     * @param stdClass|null $user
     * @param int|null $nextruntime
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function remove_category(
        stdClass $site,
        int $origincatid,
        ?stdClass $user = null,
        ?int $nextruntime = null
    ): array {

        $errors = [];

        // 1. Request DB.
        $requestobject = coursetransfer_request::set_request_remove_category($site, $origincatid, $user, $nextruntime);

        // 2. Call CURL Origin Backup Course.
        $request = new request($site);
        $res = $request->origin_remove_category($requestobject->id, $origincatid, $nextruntime, $user);
        // Audit every removal asked of another platform, accepted or not.
        $first = $res->errors[0] ?? null;
        try {
            \local_coursetransfer\event\remote_removal_requested::fire((int)$requestobject->id, (int)$requestobject->userid, [
                'kind' => 'category', 'id' => $origincatid, 'site' => (string)$site->host,
                'result' => $res->success ? 'accepted' : 'rejected: ' . ($first->code ?? '') . ' ' . ($first->msg ?? ''),
            ]);
        } catch (Throwable $e) {
            debugging('remote_removal_requested not triggered: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        // 3. Success or Errors.
        if ($res->success) {
            // 4a. Update Request DB Completed.
            $requestobject->status = coursetransfer_request::STATUS_IN_PROGRESS;
            $requestobject->origin_category_id = $origincatid;
            $requestobject->origin_category_name = $res->data->course_category_name;
            $requestobject->origin_category_idnumber = $res->data->course_category_idnumber;
            coursetransfer_request::insert_or_update($requestobject, $requestobject->id);
            $success = true;
        } else {
            // 4b. Update Request DB Errors.
            $err = $res->errors;
            $errors = $res->errors;
            $requestobject->status = coursetransfer_request::STATUS_ERROR;
            $requestobject->error_code = $err[0]->code;
            $requestobject->error_message = $err[0]->msg;
            coursetransfer_request::insert_or_update($requestobject, $requestobject->id);
            $success = false;
        }
        return [
                'success' => $success,
                'errors' => $errors,
                'data' => [
                        'requestid' => $requestobject->id,
                ],
        ];
    }

    /**
     * Restore Category.
     *
     * @param stdClass $user
     * @param stdClass $site
     * @param int $targetcategoryid
     * @param int $origincategoryid
     * @param configuration_category $configuration $configuration
     * @param array $courses
     * @return array
     */
    public static function restore_category(
        stdClass $user,
        stdClass $site,
        int $targetcategoryid,
        int $origincategoryid,
        configuration_category $configuration,
        array $courses = []
    ): array {

        try {
            // 2. Category Request DB.
            $requestobject = coursetransfer_request::set_request_restore_category(
                $site,
                $targetcategoryid,
                $origincategoryid,
                '',
                $configuration,
                $user
            );
            $request = new request($site);
            $origincategoryname = '';
            $origincategoryidnumber = '';
            $origincategordesc = '';
            $hidden = ['hiddencategories' => 0, 'hiddencourses' => 0];
            if (count($courses) === 0) {
                // 1a. Call CURL Origin Get Category Detail for courses list.
                $res = $request->origin_get_category_detail($origincategoryid, $user);
                if ($res->success) {
                    $courses = $res->data->courses;
                    $origincategoryname = $res->data->name;
                    $origincategoryidnumber = $res->data->idnumber;
                    // What the requester cannot see is not copied: it holds the removal of the origin (R3).
                    $hidden = [
                        'hiddencategories' => (int)($res->data->hiddencategories ?? 0),
                        'hiddencourses' => (int)($res->data->hiddencourses ?? 0),
                    ];
                } else {
                    throw new moodle_exception(json_encode($res->errors));
                }
            } else {
                // 1b. Call CURL Origin Get Course Detail from courses list.
                $courses = self::get_courses_detail($user, $site, $courses);
            }

            // Snapshot of what has to be copied: the category only completes when all of it was (N2).
            $requestobject->origin_category_name = $origincategoryname;
            $requestobject->origin_category_tree = json_encode([
                'name' => $origincategoryname,
                'idnumber' => $origincategoryidnumber,
                'courses' => array_map(fn($c) => [
                    'id' => $c->id ?? null,
                    'fullname' => $c->fullname ?? '',
                    'shortname' => $c->shortname ?? '',
                ], array_values((array)$courses)),
                'categories' => [],
            ] + $hidden);
            coursetransfer_request::insert_or_update($requestobject, $requestobject->id);

            // 2. If targetcategoryid is new (0)
            if ($targetcategoryid === 0) {
                $targetcategoryid = category::create($origincategoryname, $origincategoryidnumber, $origincategordesc);
            } else {
                category::update($targetcategoryid, $origincategoryname, $origincategoryidnumber, $origincategordesc);
            }

            $success = true;
            $errors = [];
            $catcourserequests = [];

            foreach ($courses as $course) {
                try {
                    // 1. Configuration Course.
                    $configurationcourse = new configuration_course(
                        $configuration->targettarget,
                        $configuration->targetremoveenrols,
                        $configuration->targetremovegroups,
                        $configuration->originenrolusers,
                        false,
                        $configuration->nextruntime
                    );

                    // 2. Create new course in this category.
                    $targetcourseid = course::create(
                        core_course_category::get($targetcategoryid),
                        $course->fullname,
                        $course->shortname . uniqid()
                    );
                    $origincourseid = $course->id;

                    // 3. Request Restore Course.
                    $courseres = self::restore_course_unity(
                        $user,
                        $site,
                        $targetcourseid,
                        $origincourseid,
                        $configurationcourse,
                        [],
                        $requestobject->id
                    );
                } catch (Throwable $e) {
                    // One course that cannot be created must not stop the others, nor go unnoticed.
                    $courseres = ['success' => false, 'errors' => [['code' => '11301', 'msg' => $e->getMessage()]]];
                }

                if (!$courseres['success']) {
                    $success = false;
                    $errors = array_merge($errors, $courseres['errors']);
                }

                // Update category course requests.
                if (isset($courseres['data']['requestid'])) {
                    $catcourserequests[] = $courseres['data']['requestid'];
                    coursetransfer_request::update_fields((int)$requestobject->id, [
                        'origin_category_requests' => json_encode($catcourserequests),
                    ]);
                }
            }
            if (!$success) {
                coursetransfer_request::mark_category_failed((int)$requestobject->id, $errors);
            }

            return [
                    'success' => $success,
                    'errors' => $errors,
                    'data' => [
                        'requestid' => $requestobject->id,
                    ],
            ];
        } catch (Throwable $e) {
            $error = [
                    'code' => '11001',
                    'msg' => $e->getMessage(),
            ];
            $errors[] = $error;
            if (isset($requestobject->id)) {
                coursetransfer_request::mark_category_failed((int)$requestobject->id, $errors);
            }
            return [
                    'success' => false,
                    'errors' => $errors,
                    'data' => isset($requestobject->id) ? ['requestid' => $requestobject->id] : [],
            ];
        }
    }

    /**
     * Restore a category preserving its subcategory tree.
     *
     * Like restore_category(), but pulls the full subtree via
     * origin_get_category_detail_tree and recreates the nested subcategories +
     * their courses on the target site, preserving the hierarchy.
     *
     * @param stdClass $user
     * @param stdClass $site
     * @param int $targetcategoryid Target category id (0 = create a new top-level category).
     * @param int $origincategoryid
     * @param configuration_category $configuration
     * @return array
     */
    public static function restore_category_tree(
        stdClass $user,
        stdClass $site,
        int $targetcategoryid,
        int $origincategoryid,
        configuration_category $configuration
    ): array {
        $errors = [];
        try {
            // 1. Category Request DB.
            $requestobject = coursetransfer_request::set_request_restore_category(
                $site,
                $targetcategoryid,
                $origincategoryid,
                '',
                $configuration,
                $user
            );
            $request = new request($site);
            $res = $request->origin_get_category_detail_tree($origincategoryid, $user);
            if (!$res->success) {
                coursetransfer_request::mark_category_failed((int)$requestobject->id, $res->errors);
                return [
                        'success' => false,
                        'errors' => $res->errors,
                        'data' => ['requestid' => $requestobject->id],
                ];
            }
            // The tree WS returns its payload as a JSON string.
            $data = is_string($res->data) ? json_decode($res->data) : $res->data;
            if (!isset($data->name)) {
                $errors[] = ['code' => '11002', 'msg' => 'Name not found in response data'];
                coursetransfer_request::mark_category_failed((int)$requestobject->id, $errors);
                return [
                        'success' => false,
                        'errors' => $errors,
                        'data' => ['requestid' => $requestobject->id],
                ];
            }

            // 2. Root category: create (0) or update.
            $origincategoryname = $data->name;
            $origincategoryidnumber = $data->idnumber ?? '';
            $origincategordesc = $data->description ?? '';
            if ($targetcategoryid === 0) {
                $targetcategoryid = category::create($origincategoryname, $origincategoryidnumber, $origincategordesc);
            } else {
                category::update($targetcategoryid, $origincategoryname, $origincategoryidnumber, $origincategordesc);
            }
            $requestobject->origin_category_name = $origincategoryname;
            // Persist a snapshot of the subtree so the request detail can show it later.
            $requestobject->origin_category_tree = is_string($res->data) ? $res->data : json_encode($data);
            coursetransfer_request::insert_or_update($requestobject, $requestobject->id);

            // 3. Recreate the tree: root courses + nested subcategories.
            $success = true;
            $catcourserequests = [];
            foreach (($data->courses ?? []) as $course) {
                $success = self::restore_tree_course(
                    $course,
                    $user,
                    $site,
                    $targetcategoryid,
                    $configuration,
                    $requestobject,
                    $catcourserequests,
                    $errors
                ) && $success;
            }
            foreach (($data->categories ?? []) as $cat) {
                $success = self::restore_tree_category(
                    $cat,
                    $user,
                    $site,
                    $targetcategoryid,
                    $configuration,
                    $requestobject,
                    $catcourserequests,
                    $errors
                ) && $success;
            }

            if (!$success) {
                coursetransfer_request::mark_category_failed((int)$requestobject->id, $errors);
            }
            return [
                    'success' => $success,
                    'errors' => $errors,
                    'data' => ['requestid' => $requestobject->id],
            ];
        } catch (Throwable $e) {
            $errors[] = ['code' => '11001', 'msg' => $e->getMessage()];
            if (isset($requestobject->id)) {
                coursetransfer_request::mark_category_failed((int)$requestobject->id, $errors);
            }
            return ['success' => false, 'errors' => $errors,
                    'data' => isset($requestobject->id) ? ['requestid' => $requestobject->id] : []];
        }
    }

    /**
     * Whether every course of a category restore (target side) arrived complete and, with verifymanifest, matches its origin.
     *
     * A manifest not checked yet is checked now (asks the origin). Used by local_coursetransfermanager before removing.
     *
     * @param int $requestid Category restore request id.
     * @return stdClass {ok: bool, issues: string[]} with one text per course that is not right.
     */
    public static function verify_category_restore(int $requestid): stdClass {
        $issues = [];
        $reqcat = coursetransfer_request::get($requestid);
        if (
            !$reqcat || (int)$reqcat->type !== coursetransfer_request::TYPE_CATEGORY
                || (int)$reqcat->direction !== coursetransfer_request::DIRECTION_REQUEST
        ) {
            return (object)['ok' => false, 'issues' => ['Request ' . $requestid . ' is not a category restore of this site.']];
        }
        $childids = json_decode((string)$reqcat->origin_category_requests, true);
        $childids = is_array($childids) ? array_map('intval', $childids) : [];
        if (!$childids) {
            $issues[] = 'Request ' . $requestid . ' has no course requests.';
        }
        $expected = coursetransfer_request::count_expected_courses($reqcat);
        if ($expected !== null && count($childids) < $expected) {
            $issues[] = 'Only ' . count($childids) . ' of the ' . $expected . ' courses of the origin category were requested.';
        }
        $verify = restore_manifest::enabled();
        foreach ($childids as $childid) {
            $child = coursetransfer_request::get($childid);
            if (!$child) {
                $issues[] = 'Course request ' . $childid . ' not found.';
                continue;
            }
            $label = 'Course "' . ($child->origin_course_shortname ?: $child->origin_course_fullname) . '" (origin id '
                . (int)$child->origin_course_id . ', request ' . $childid . '): ';
            if ((int)$child->status !== coursetransfer_request::STATUS_COMPLETED) {
                $issues[] = $label . 'not completed (status ' . (int)$child->status . ').';
                continue;
            }
            if (!empty($child->error_code) && (int)$child->error_code !== coursetransfer_request::CODE_ORIGIN_NOT_REMOVED) {
                $issues[] = $label . 'error ' . (int)$child->error_code . ': ' . shorten_text((string)$child->error_message, 200);
                continue;
            }
            if (!$verify) {
                continue;
            }
            $manifest = json_decode((string)($child->origin_manifest ?? ''), true) ?: [];
            if (empty($manifest['verified'])) {
                $manifest['issues'] = self::verify_child_manifest($child);
            }
            if (!empty($manifest['issues'])) {
                $issues[] = $label . 'does not match the origin: ' . implode(', ', (array)$manifest['issues']) . '.';
            }
        }
        return (object)['ok' => empty($issues), 'issues' => $issues];
    }

    /**
     * Check a completed course of a category against its origin now, recording a mismatch as restore_course_task does.
     *
     * @param stdClass $child Course request.
     * @return string[] Issues; empty when it matches.
     */
    protected static function verify_child_manifest(stdClass $child): array {
        try {
            $user = core_user::get_user((int)$child->userid);
            $issues = $user ? restore_manifest::verify($child, self::get_site_by_url($child->siteurl), $user)
                : ['requester not found'];
        } catch (Throwable $e) {
            $issues = ['manifest not checked: ' . $e->getMessage()];
        }
        if ($issues && empty($child->error_code)) {
            $previous = trim((string)$child->error_message);
            $message = 'Manifest mismatch: ' . implode(', ', $issues);
            coursetransfer_request::update_fields((int)$child->id, [
                'error_code' => coursetransfer_request::CODE_MANIFEST_MISMATCH,
                'error_message' => $previous === '' ? $message : $previous . ' | ' . $message,
            ]);
        }
        return $issues;
    }

    /**
     * Recreate a subcategory (and its whole subtree) under a parent category.
     *
     * @param stdClass $cat
     * @param stdClass $user
     * @param stdClass $site
     * @param int $parentid
     * @param configuration_category $configuration
     * @param stdClass $requestobject
     * @param array $catcourserequests
     * @param array $errors
     * @param int $depth Current recursion depth.
     * @return bool
     */
    protected static function restore_tree_category(
        stdClass $cat,
        stdClass $user,
        stdClass $site,
        int $parentid,
        configuration_category $configuration,
        stdClass $requestobject,
        array &$catcourserequests,
        array &$errors,
        int $depth = 0
    ): bool {
        // Defensive guard against a pathologically deep (or crafted) tree.
        if ($depth > self::MAX_TREE_DEPTH) {
            $errors[] = ['code' => '11004', 'msg' => 'Maximum category tree depth exceeded'];
            return false;
        }
        $name = $cat->name ?? '';
        $idnumber = $cat->idnumber ?? '';
        $desc = $cat->description ?? '';
        try {
            $targetcategoryid = category::create($name, $idnumber, $desc, $parentid);
        } catch (Throwable $e) {
            // The subtree is skipped; its courses stay counted in the snapshot, so the category never completes.
            $errors[] = ['code' => '11005', 'msg' => 'Category ' . $name . ' not created: ' . $e->getMessage()];
            return false;
        }
        $success = true;
        foreach (($cat->courses ?? []) as $course) {
            $success = self::restore_tree_course(
                $course,
                $user,
                $site,
                $targetcategoryid,
                $configuration,
                $requestobject,
                $catcourserequests,
                $errors
            ) && $success;
        }
        foreach (($cat->categories ?? []) as $child) {
            $success = self::restore_tree_category(
                $child,
                $user,
                $site,
                $targetcategoryid,
                $configuration,
                $requestobject,
                $catcourserequests,
                $errors,
                $depth + 1
            ) && $success;
        }
        return $success;
    }

    /**
     * Create a course under a target category and enqueue its restore.
     *
     * @param stdClass $course
     * @param stdClass $user
     * @param stdClass $site
     * @param int $targetcategoryid
     * @param configuration_category $configuration
     * @param stdClass $requestobject
     * @param array $catcourserequests
     * @param array $errors
     * @return bool
     */
    protected static function restore_tree_course(
        stdClass $course,
        stdClass $user,
        stdClass $site,
        int $targetcategoryid,
        configuration_category $configuration,
        stdClass $requestobject,
        array &$catcourserequests,
        array &$errors
    ): bool {
        try {
            $configurationcourse = new configuration_course(
                $configuration->targettarget,
                $configuration->targetremoveenrols,
                $configuration->targetremovegroups,
                $configuration->originenrolusers,
                false,
                $configuration->nextruntime
            );
            $targetcourseid = course::create(
                core_course_category::get($targetcategoryid),
                $course->fullname,
                $course->shortname . '_' . uniqid()
            );
            $courseres = self::restore_course_unity(
                $user,
                $site,
                $targetcourseid,
                (int)$course->id,
                $configurationcourse,
                [],
                $requestobject->id
            );
            if (!$courseres['success']) {
                $errors = array_merge($errors, $courseres['errors']);
            }
            if (isset($courseres['data']['requestid'])) {
                $catcourserequests[] = $courseres['data']['requestid'];
                coursetransfer_request::update_fields((int)$requestobject->id, [
                    'origin_category_requests' => json_encode($catcourserequests),
                ]);
            }
            return (bool) $courseres['success'];
        } catch (Throwable $e) {
            $errors[] = ['code' => '11301', 'msg' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Restore Course Unity.
     *
     * @param stdClass $user
     * @param stdClass $site
     * @param int $targetcourseid
     * @param int $origincourseid
     * @param configuration_course $configuration
     * @param array $sections
     * @param int|null $requestcatid
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected static function restore_course_unity(
        stdClass $user,
        stdClass $site,
        int $targetcourseid,
        int $origincourseid,
        configuration_course $configuration,
        array $sections = [],
        ?int $requestcatid = null
    ): array {

        $errors = [];
        // 1. Request DB.
        $requestobject = coursetransfer_request::set_request_restore_course(
            $user,
            $site,
            $targetcourseid,
            $origincourseid,
            $configuration,
            $sections,
            $requestcatid
        );

        // 2. Call CURL Origin Backup Course.
        $request = new request($site);
        $res = $request->origin_backup_course(
            $user,
            $requestobject->id,
            $origincourseid,
            $targetcourseid,
            $configuration,
            $sections
        );
        // 3. Success or Errors.
        if ($res->success) {
            // 4a. Update Request DB Completed.
            $requestobject->status = coursetransfer_request::STATUS_IN_PROGRESS;
            $requestobject->origin_course_fullname = $res->data->course_fullname;
            $requestobject->origin_course_shortname = $res->data->course_shortname;
            $requestobject->origin_course_idnumber = $res->data->course_idnumber;
            $requestobject->origin_category_id = $res->data->course_category_id;
            $requestobject->origin_category_name = $res->data->course_category_name;
            $requestobject->origin_category_idnumber = $res->data->course_category_idnumber;
            $requestobject->origin_backup_size_estimated = $res->data->origin_backup_size_estimated;
            coursetransfer_request::insert_or_update($requestobject, $requestobject->id);
            $success = true;
        } else {
            // 4b. Update Request DB Errors.
            $err = $res->errors;
            $errors = $res->errors;
            $requestobject->status = coursetransfer_request::STATUS_ERROR;
            $requestobject->error_code = $err[0]->code;
            $requestobject->error_message = $err[0]->msg;
            coursetransfer_request::insert_or_update($requestobject, $requestobject->id);
            $success = false;
        }
        return [
                'success' => $success,
                'errors' => $errors,
                'data' => [
                        'requestid' => $requestobject->id,
                ],
        ];
    }

    /**
     * Get Courses Detail.
     *
     * @param stdClass $user
     * @param stdClass $site
     * @param array $courses
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_courses_detail(stdClass $user, stdClass $site, array $courses): array {
        $request = new request($site);
        $items = [];
        foreach ($courses as $course) {
            $res = $request->origin_get_course_detail($course['id'], $user);
            if ($res->success) {
                $items[] = $res->data;
            } else {
                throw new moodle_exception(json_encode($res->errors));
            }
        }
        return $items;
    }

    /**
     * Get Subcategories.
     *
     * @param core_course_category $category
     * @param stdClass|null $user
     * @return core_course_category[]
     */
    public static function get_subcategories(core_course_category $category, ?stdClass $user = null): array {
        $categories = [];
        self::get_childs($category, $categories, $user);
        return $categories;
    }

    /**
     * Get Subcategories.
     *
     * @param core_course_category $category
     * @param array $categories
     * @param stdClass|null $user
     */
    public static function get_childs(core_course_category $category, array &$categories, ?stdClass $user = null): void {
        if ($category->get_children_count() > 0) {
            foreach ($category->get_children() as $child) {
                if ($child->is_uservisible($user)) {
                    $categories[] = $child;
                    self::get_childs($child, $categories, $user);
                }
            }
        };
    }

    /**
     * Get Subcategories.
     *
     * @param int $courses
     * @param core_course_category[] $categories
     * @return int
     */
    public static function get_subcategories_numcourses(int $courses, array $categories): int {
        foreach ($categories as $cat) {
            $courses += $cat->get_courses_count();
        }
        return $courses;
    }

    /**
     * Postinstall.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function postinstall(): ?string {
        // 1. Create Role.
        $roleid = role::create_roles();

        // 2. Add Permission.
        role::add_capability($roleid, 'moodle/category:viewcourselist');
        role::add_capability($roleid, 'moodle/category:viewhiddencategories');
        role::add_capability($roleid, 'moodle/course:view');
        role::add_capability($roleid, 'moodle/course:create');
        role::add_capability($roleid, 'moodle/course:viewhiddencourses');
        role::add_capability($roleid, 'moodle/backup:backuptargetimport');
        role::add_capability($roleid, 'moodle/backup:backupcourse');
        role::add_capability($roleid, 'moodle/backup:backupactivity');
        role::add_capability($roleid, 'moodle/backup:backupsection');
        role::add_capability($roleid, 'moodle/backup:downloadfile');
        role::add_capability($roleid, 'moodle/backup:userinfo');
        role::add_capability($roleid, 'moodle/backup:anonymise');
        role::add_capability($roleid, 'moodle/backup:configure');
        role::add_capability($roleid, 'webservice/rest:use');
        role::add_capability($roleid, 'moodle/restore:restoreactivity');
        role::add_capability($roleid, 'moodle/restore:restorecourse');
        role::add_capability($roleid, 'moodle/restore:restoresection');
        role::add_capability($roleid, 'moodle/restore:restoretargetimport');
        role::add_capability($roleid, 'moodle/restore:uploadfile');
        role::add_capability($roleid, 'moodle/restore:rolldates');
        role::add_capability($roleid, 'moodle/restore:userinfo');
        role::add_capability($roleid, 'moodle/restore:viewautomatedfilearea');
        role::add_capability($roleid, 'moodle/restore:createuser');
        role::add_capability($roleid, 'moodle/site:maintenanceaccess');
        role::add_capability($roleid, 'moodle/course:delete');
        role::add_capability($roleid, 'moodle/category:manage');
        role::add_capability($roleid, 'local/coursetransfer:origin_remove_course');
        role::add_capability($roleid, 'local/coursetransfer:origin_remove_category');
        // Only this role may call the peer-only services (TIPCOUTRAN-10, H8).
        role::add_capability($roleid, 'local/coursetransfer:webservice');
        // Assignable at system level, where the capabilities above apply (LCT-021).
        set_role_contextlevels($roleid, array_unique(array_merge(get_role_contextlevels($roleid), [CONTEXT_SYSTEM])));

        // 3. Create User.
        $userid = user::create_user($roleid);

        // 4. Enable webservices.
        set_config('enablewebservices', 1);

        // 5. Activate REST protocol, keeping the protocols already enabled (LCT-007).
        $protocols = array_filter(explode(',', (string) get_config('core', 'webserviceprotocols')));
        if (!in_array('rest', $protocols, true)) {
            $protocols[] = 'rest';
            set_config('webserviceprotocols', implode(',', $protocols));
        }

        // 6. Enable webservices documentation.
        set_config('enablewsdocumentation', 1);

        // 7. Accept site policies for the WS user: otherwise pluginfile downloads
        // are rejected with 'sitepolicynotagreed' and surface as a corrupt backup
        // ("Plan is NULL") on the target. See fix LLAOMW-107.
        self::accept_site_policies($userid);

        // 8. Create Token.
        return user::create_token($userid);
    }

    /**
     * Accept the current site policies on behalf of the WS user (LLAOMW-107, LCT-074).
     *
     * It runs as the user who installs, repairs or upgrades the plugin, and only with that user's permissions:
     * tool_policy versions go through its API, which requires tool/policy:acceptbehalf; the core site policy
     * ($CFG->sitepolicy) has no acceptance records, its acceptance is the user's policyagreed flag (as in the
     * core default handler), set only by a user with moodle/site:config. Policies published later reset that
     * flag for every user, so the status page checks it and Repair calls this again.
     *
     * @param int $userid WS user id.
     * @return bool Whether the site policies no longer block the user.
     */
    public static function accept_site_policies(int $userid): bool {
        global $DB;
        try {
            if (class_exists(policyapi::class)) {
                $versionids = policyapi::get_current_versions_ids();
                if (!empty($versionids)) {
                    policyapi::accept_policies(array_values($versionids), $userid);
                }
            }
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id, auth, policyagreed', MUST_EXIST);
            if (
                self::site_policies_block($user) && !self::pending_site_policies($userid)
                && has_capability('moodle/site:config', context_system::instance())
            ) {
                $DB->set_field('user', 'policyagreed', 1, ['id' => $userid]);
                $user->policyagreed = 1;
            }
            return !self::site_policies_block($user) && !self::pending_site_policies($userid);
        } catch (Throwable $e) {
            debugging('local_coursetransfer: could not accept site policies for the WS user: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Whether the site policies stop the user from using this site, with the same rule as require_login().
     *
     * Then other platforms get 'sitepolicynotagreed' when they download a backup or call a service (LCT-074).
     *
     * @param stdClass $user User record with id, auth and policyagreed.
     * @return bool
     */
    public static function site_policies_block(stdClass $user): bool {
        if (!empty($user->policyagreed) || ($user->auth ?? '') === 'webservice' || is_siteadmin($user)) {
            return false;
        }
        return (bool)(new sitepolicy_manager())->is_defined(false);
    }

    /**
     * Current tool_policy versions the user still has to accept (LCT-074).
     *
     * Same rule as tool_policy\api::update_policyagreed(): compulsory versions not accepted and optional
     * versions not answered, for logged-in users. Empty when tool_policy is not the site policy handler.
     *
     * @param int $userid
     * @return int[] Policy version ids.
     */
    public static function pending_site_policies(int $userid): array {
        global $CFG, $DB;
        if (!class_exists(policyapi::class) || ($CFG->sitepolicyhandler ?? '') !== 'tool_policy') {
            return [];
        }
        $sql = "SELECT v.id
                  FROM {tool_policy} d
                  JOIN {tool_policy_versions} v ON v.id = d.currentversionid
             LEFT JOIN {tool_policy_acceptances} a ON a.policyversionid = v.id AND a.userid = :userid
                 WHERE v.audience IN (:loggedin, :all)
                       AND (a.id IS NULL OR (a.status = 0 AND v.optional = :compulsory))";
        return array_map('intval', $DB->get_fieldset_sql($sql, [
            'userid' => $userid,
            'loggedin' => policy_version::AUDIENCE_LOGGEDIN,
            'all' => policy_version::AUDIENCE_ALL,
            'compulsory' => policy_version::AGREEMENT_COMPULSORY,
        ]));
    }

    /**
     * Stop with a clear error when the site policies block the user of a request without session (LCT-074).
     *
     * Peer services (webservice/rest/server.php) and backup downloads (webservice/pluginfile.php) authenticate by
     * token, without session cookies, and would otherwise fail in require_login() with 'sitepolicynotagreed',
     * which does not say that the fix is Repair on this site. The message names no user nor policy. Browser
     * sessions keep the core behaviour (redirect to the policies).
     *
     * @throws moodle_exception
     */
    public static function require_site_policies_agreed(): void {
        global $USER;
        if (!defined('NO_MOODLE_COOKIES') || !NO_MOODLE_COOKIES) {
            return;
        }
        if (isloggedin() && !isguestuser() && self::site_policies_block($USER)) {
            throw new moodle_exception('error_wspolicynotagreed', 'local_coursetransfer');
        }
    }

    /**
     * Has course?
     *
     * @param stdClass $user
     * @return bool
     * @throws coding_exception
     */
    public static function has_course(stdClass $user): bool {
        global $DB;
        // Read from the database: get_courses() lists only what the user of the session may see (on 5.x, none
        // in a web service call), not what this user may back up.
        $hascourse = false;
        $rs = $DB->get_recordset_select('course', 'id <> :siteid', ['siteid' => SITEID], 'id', 'id, visible');
        foreach ($rs as $course) {
            if (self::can_backup_course($course, $user)) {
                $hascourse = true;
                break;
            }
        }
        $rs->close();
        return $hascourse;
    }

    /**
     * Get Courses by User.
     *
     * @param stdClass $user
     * @param int $page
     * @param int $perpage
     * @param string $search
     * @return array
     * @throws coding_exception
     */
    public static function get_courses_user(stdClass $user, int $page = 0, int $perpage = 0, string $search = ''): array {
        $courses = [];
        $cs = get_courses();
        foreach ($cs as $course) {
            if (self::filter_course($course, $user, $search)) {
                $courses[] = $course;
            }
        }
        $total = count($courses);
        if ($perpage > 0) {
            $currentpage = max(0, $page);
            $startindex = $currentpage * $perpage;
            $courses = array_slice($courses, $startindex, $perpage);
        }
        return ['total' => $total, 'courses' => $courses];
    }

    /**
     * Get courses of a user filtered by a list of ids.
     *
     * Note: the returned 'total' mirrors the historical UNIMOODLE behaviour and is
     * kept as-is for backward compatibility with peers calling
     * {@see \local_coursetransfer\external\backend\origin_course_external::origin_get_courses_by_ids()}.
     *
     * @param stdClass $user
     * @param array $courseids
     * @return array
     */
    public static function get_courses_user_by_ids(stdClass $user, array $courseids): array {
        $total = 0;
        $courses = [];
        foreach ($courseids as $courseid) {
            $course = get_course($courseid);
            if (self::filter_course($course, $user, '')) {
                $courses[] = $course;
            }
        }
        return ['total' => $total, 'courses' => $courses];
    }

    /**
     * Get Courses.
     *
     * @param string $search
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function get_courses(string $search = '', int $page = 0, int $perpage = 0): array {
        // Prepare the search API options.
        // Empty search criteria returns all.
        $searchcriteria = ['search' => $search];

        $options = [];
        if ($perpage != 0) {
            $offset = $page * $perpage;
            $options = ['offset' => $offset, 'limit' => $perpage];
        }
        $requiredcapabilities = ['moodle/backup:backupcourse'];

        // Search the courses.
        return core_course_category::search_courses($searchcriteria, $options, $requiredcapabilities);
    }

    /**
     * Count the categories the user may back up (all of them when no user is given).
     *
     * @param stdClass|null $user
     * @return int
     */
    public static function count_categories_user(?stdClass $user = null): int {
        $categories = core_course_category::get_all();
        if ($user !== null) {
            $categories = array_filter($categories, fn($category) => self::can_backup_category($category, $user));
        }
        return count($categories);
    }

    /**
     * Get Categories User.
     *
     * @param stdClass $user
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function get_categories_user(stdClass $user, int $page = 0, int $perpage = 0): array {
        // Only what the user could back up by hand: a peer must not list the whole tree (LCT-010).
        $categories = array_filter(
            core_course_category::get_all(),
            fn($category) => self::can_backup_category($category, $user)
        );
        // Categories are not sorted as are listed to user using nested name.
        if ($perpage != 0) {
            $offset = $page * $perpage;
            $categories = array_slice($categories, $offset, $perpage, true);
        }

        return $categories;
    }

    /**
     * Get Categories.
     *
     * @return array
     */
    public static function get_categories(): array {
        return core_course_category::get_all();
    }

    /**
     * Can the user back up this course, as from the course itself (hidden courses included)?
     *
     * @param stdClass $course
     * @param stdClass $user
     * @return bool
     */
    public static function can_backup_course(stdClass $course, stdClass $user): bool {
        $context = context_course::instance($course->id);
        // SITEID may be a string (it comes from the database): compare as integers, or the front page is offered.
        if ((int) $course->id === (int) SITEID || !has_capability('moodle/backup:backupcourse', $context, $user->id)) {
            return false;
        }
        // As require_login() does for the manual backup: an active enrolment or access without one
        // (managers, admins). A suspended teacher keeps the role, so the capability alone was not enough (#60).
        if (!is_enrolled($context, $user, '', true) && !is_viewing($context, $user)) {
            return false;
        }
        return !empty($course->visible) || has_capability('moodle/course:viewhiddencourses', $context, $user->id);
    }

    /**
     * Can the user back up the courses of a whole category (capability at category level)?
     *
     * @param core_course_category $category
     * @param stdClass $user
     * @return bool
     */
    public static function can_backup_category(core_course_category $category, stdClass $user): bool {
        return $category->is_uservisible($user)
            && has_capability('moodle/backup:backupcourse', $category->get_context(), $user->id);
    }

    /**
     * Can remove origin course?
     *
     * @param stdClass $user
     * @return false
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function can_remove_origin_course(stdClass $user): bool {
        $context = context_system::instance();
        return has_capability('local/coursetransfer:origin_remove_course', $context, $user->id);
    }

    /**
     * Has origin user data?
     *
     * @param stdClass $user
     * @return false
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function has_origin_user_data(stdClass $user): bool {
        $context = context_system::instance();
        return has_capability('local/coursetransfer:origin_restore_course_users', $context, $user->id);
    }

    /**
     * Can target restore merge?
     *
     * @param stdClass $user
     * @param context $context
     * @return false
     * @throws coding_exception
     */
    public static function can_target_restore_merge(stdClass $user, context $context): bool {
        return has_capability('local/coursetransfer:target_restore_merge', $context, $user->id);
    }

    /**
     * Can Target restore content remove?
     *
     * @param stdClass $user
     * @param context $context
     * @return false
     * @throws coding_exception
     */
    public static function can_target_restore_content_remove(stdClass $user, context $context): bool {
        return has_capability('local/coursetransfer:target_restore_content_remove', $context, $user->id);
    }

    /**
     * Can Target restore groups remove?
     *
     * @param stdClass $user
     * @return false
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function can_target_restore_groups_remove(stdClass $user): bool {
        $context = context_system::instance();
        return has_capability('local/coursetransfer:target_restore_groups_remove', $context, $user->id);
    }

    /**
     * Can Target restore enrols remove?
     *
     * @param stdClass $user
     * @return false
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function can_target_restore_enrol_remove(stdClass $user): bool {
        $context = context_system::instance();
        return has_capability('local/coursetransfer:target_restore_enrol_remove', $context, $user->id);
    }

    /**
     * Can restore in not new course?
     *
     * @param stdClass $user
     * @return false
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function can_restore_in_not_new_course(stdClass $user): bool {
        $context = context_system::instance();
        if (
            has_capability('local/coursetransfer:target_restore_content_remove', $context, $user->id) &&
                has_capability('local/coursetransfer:target_restore_merge', $context, $user->id)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Purge a removed course from the recycle bins (if remove_course_cleanup is on).
     *
     * @param int $courseid
     * @param string $shortname
     * @param int|null $categoryid Category the course was in (null for tasks queued by 2.1).
     * @param int|null $timeremoved When it was removed (null for tasks queued by 2.1).
     * @throws dml_exception
     */
    public static function cleanup_course_bin(
        int $courseid,
        string $shortname,
        ?int $categoryid = null,
        ?int $timeremoved = null
    ): void {
        global $DB;
        if (!get_config('local_coursetransfer', 'remove_course_cleanup')) {
            mtrace("remove_course_cleanup is not active");
            return;
        }
        mtrace("Remove course items recycle bin enabled ...");
        try {
            $items = $DB->get_records('tool_recyclebin_course', ['courseid' => $courseid]);
            foreach ($items as $item) {
                mtrace("[tool_recyclebin] Deleting item '{$item->id}' from the course recycle bin ...");
                try {
                    $bin = new \tool_recyclebin\course_bin($item->courseid);
                    $bin->delete_item($item);
                    mtrace("[tool_recyclebin] Deleted item '{$item->name}' from the course recycle bin ...");
                } catch (Throwable $e) {
                    mtrace("Error cleanup_course_bin item: " . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            mtrace("Error cleanup_course_bin: " . $e->getMessage());
        }
        try {
            // The category bin knows the course only by shortname: narrow it to this removal, and
            // leave it alone if it is still ambiguous (another course may have had the same shortname).
            $conditions = ['shortname' => $shortname];
            if ($categoryid) {
                $conditions['categoryid'] = $categoryid;
            }
            $items = $DB->get_records('tool_recyclebin_category', $conditions);
            if ($timeremoved && count($items) > 1) {
                $items = array_filter($items, fn($i) => abs((int)$i->timecreated - $timeremoved) <= HOURSECS);
            }
            if (count($items) > 1) {
                mtrace("[tool_recyclebin] Several items named '{$shortname}' in the category recycle bin: none deleted.");
                return;
            }
            foreach ($items as $item) {
                mtrace("[tool_recyclebin] Deleting item '{$item->shortname}' from the category recycle bin ...");
                $bin = new \tool_recyclebin\category_bin($item->categoryid);
                $bin->delete_item($item);
                mtrace("[tool_recyclebin] Deleted item '{$item->shortname}' from the category recycle bin ...");
            }
        } catch (Throwable $e) {
            mtrace("Error cleanup_course_bin: " . $e->getMessage());
        }
    }

    /**
     * Cleanup Category Bin.
     *
     * @param int $catid
     * @throws dml_exception
     */
    public static function cleanup_category_bin(int $catid): void {
        global $DB;
        if (get_config('local_coursetransfer', 'remove_cat_cleanup')) {
            mtrace("Remove category items recycle bin enabled ...");
            try {
                $items = $DB->get_records('tool_recyclebin_category', ['categoryid' => $catid]);
                foreach ($items as $item) {
                    mtrace("[tool_recyclebin] Deleting item '{$item->id}' from the category recycle bin ...");
                    try {
                        $bin = new \tool_recyclebin\category_bin($item->categoryid);
                        $bin->delete_item($item);
                        mtrace("[tool_recyclebin] Deleted item '{$item->shortname}' from the category recycle bin ...");
                    } catch (moodle_exception $e) {
                        mtrace("Error cleanup_category_bin item: " . $e->getMessage());
                    }
                }
            } catch (moodle_exception $e) {
                mtrace("Error cleanup_category_bin: " . $e->getMessage());
            }
        } else {
            mtrace("remove_cat_cleanup is not active");
        }
    }

    /**
     * Filter Course.
     *
     * @param stdClass $course
     * @param stdClass $user
     * @param string $search
     * @return bool
     * @throws coding_exception
     */
    protected static function filter_course(stdClass $course, stdClass $user, string $search): bool {
        if (!self::can_backup_course($course, $user)) {
            return false;
        }
        if (!empty($search)) {
            if (!str_contains(strtolower($course->fullname), strtolower($search))) {
                return false;
            }
        }
        return true;
    }
}
