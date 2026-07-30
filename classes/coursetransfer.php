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
use core_course_category;
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
        $res = $DB->get_record('user', [$column => $value]);
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
     * Validate Origin Site
     *
     * @param string $siteurl
     * @return bool
     */
    public static function validate_origin_site(string $siteurl): bool {
        return true;
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
                    get_string('sectionname', 'format_'. $course->format) . ' ' . $section->section : $section->name,
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
            if ($filesize > ($sizeconfig * 1000000)) {
                $success = false;
                $error = get_string('backupsize_larger', 'local_coursetransfer');
                $error .= ': (' . ($filesize / 1000000) . ' MB) > ' . $sizeconfig . ' MB';
            }
        } catch (moodle_exception $e) {
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
    public static function restore_course(stdClass $user,
            stdClass $site, int $targetcourseid, int $origincourseid,
            configuration_course $configuration, array $sections = []): array {

        try {
            return self::restore_course_unity($user, $site, $targetcourseid, $origincourseid, $configuration, $sections);
        } catch (moodle_exception $e) {
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
        if ((int)$request->type !== coursetransfer_request::TYPE_COURSE
                || (int)$request->direction !== coursetransfer_request::DIRECTION_REQUEST) {
            return ['success' => false, 'errors' => [
                    ['code' => '10020', 'msg' => get_string('retry_not_supported', 'local_coursetransfer')]]];
        }
        if ((int)$request->status === coursetransfer_request::STATUS_COMPLETED) {
            return ['success' => false, 'errors' => [
                    ['code' => '10021', 'msg' => get_string('retry_already_completed', 'local_coursetransfer')]]];
        }

        // Anti-duplicate: relaunching while a healthy adhoc task is queued/running would
        // duplicate the operation (double restore, races). So (LCT-023):
        //  - block if there is an ACTIVE task (faildelay == 0: queued or running normally);
        //  - if the related tasks are only FAILING/backing off (faildelay > 0), remove them
        //    and proceed — recovering a stuck task is exactly what the retry is for.
        global $DB;
        $stucktaskids = [];
        foreach (coursetransfer_request::get_related_adhoc_tasks((int)$request->id) as $task) {
            if ((int)$task->faildelay === 0) {
                return ['success' => false, 'errors' => [
                        ['code' => '10023', 'msg' => get_string('retry_task_running', 'local_coursetransfer')]]];
            }
            $stucktaskids[] = $task->id;
        }
        if (!empty($stucktaskids)) {
            $DB->delete_records_list('task_adhoc', 'id', $stucktaskids);
        }

        // 1. Re-restore: if the .mbz is already downloaded, just re-queue the restore.
        $status = (int)$request->status;
        if ($status === coursetransfer_request::STATUS_DOWNLOADED
                || $status === coursetransfer_request::STATUS_RESTORE) {
            $file = self::find_downloaded_backup($request);
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

        // 2. Full relaunch from the stored parameters.
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
                (string)($request->origin_remove_activities ?? '')
        );
        $sections = !empty($request->origin_activities) ? (array) json_decode($request->origin_activities, true) : [];
        $res = self::restore_course(
                $user, $site, (int)$request->target_course_id, (int)$request->origin_course_id, $configuration, $sections);
        if (!empty($res['success'])) {
            // Mark the old request as superseded by the new one.
            $newid = $res['data']['requestid'] ?? '';
            $request->status = coursetransfer_request::STATUS_ERROR;
            $request->error_code = null;
            $request->error_message = 'Relaunched as request #' . $newid;
            coursetransfer_request::insert_or_update($request, $request->id);
            $res['mode'] = 'relaunch';
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
            stdClass $site, int $origincourseid, stdClass $user = null, int $nextruntime = null): array {

        $errors = [];

        // 1. Request DB.
        $requestobject = coursetransfer_request::set_request_remove_course($site, $origincourseid, $user, $nextruntime);

        // 2. Call CURL Origin Backup Course.
        $request = new request($site);
        $res = $request->origin_remove_course($requestobject->id, $origincourseid, $nextruntime, $user);
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
    public static function remove_category(stdClass $site, int $origincatid,
            stdClass $user = null, int $nextruntime = null): array {

        $errors = [];

        // 1. Request DB.
        $requestobject = coursetransfer_request::set_request_remove_category($site, $origincatid, $user, $nextruntime);

        // 2. Call CURL Origin Backup Course.
        $request = new request($site);
        $res = $request->origin_remove_category($requestobject->id, $origincatid, $nextruntime, $user);
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
            stdClass $user, stdClass $site, int $targetcategoryid, int $origincategoryid,
            configuration_category $configuration, array $courses = []): array {

        try {
            // 2. Category Request DB.
            $requestobject = coursetransfer_request::set_request_restore_category(
                    $site, $targetcategoryid, $origincategoryid, '', $configuration, $user
            );
            $request = new request($site);
            $origincategoryname = '';
            $origincategoryidnumber = '';
            $origincategordesc = '';
            if (count($courses) === 0) {
                // 1a. Call CURL Origin Get Category Detail for courses list.
                $res = $request->origin_get_category_detail($origincategoryid, $user);
                if ($res->success) {
                    $courses = $res->data->courses;
                    $origincategoryname = $res->data->name;
                    $origincategoryidnumber = $res->data->idnumber;
                } else {
                    throw new moodle_exception(json_encode($res->errors));
                }
            } else {
                // 1b. Call CURL Origin Get Course Detail from courses list.
                $courses = self::get_courses_detail($user, $site, $courses);
            }

            // 2. If targetcategoryid is new (0)
            if ($targetcategoryid === 0) {
                $targetcategoryid = category::create($origincategoryname, $origincategoryidnumber, $origincategordesc);
            } else {
                category::update($targetcategoryid, $origincategoryname, $origincategoryidnumber, $origincategordesc);
            }

            $requestobject->origin_category_name = $origincategoryname;
            coursetransfer_request::insert_or_update($requestobject, $requestobject->id);

            $success = true;
            $errors = [];
            $catcourserequests = [];

            foreach ($courses as $course) {

                // 1. Configuration Course.
                $configurationcourse = new configuration_course(
                        $configuration->targettarget,
                        $configuration->targetremoveenrols,
                        $configuration->targetremovegroups,
                        $configuration->originenrolusers,
                        false,
                        $configuration->nextruntime);

                // 2. Create new course in this category.
                $targetcourseid = course::create(
                        core_course_category::get($targetcategoryid),
                        $course->fullname, $course->shortname . uniqid());
                $origincourseid = $course->id;

                // 3. Request Restore Course.
                $courseres = self::restore_course_unity(
                        $user, $site, $targetcourseid, $origincourseid, $configurationcourse, [], $requestobject->id);

                if (!$courseres['success']) {
                    $success = false;
                    $errors = array_merge($errors, $courseres['errors']);
                }

                // Update category course requests.
                if (isset($courseres['data']['requestid'])) {
                    $catcourserequests[] = $courseres['data']['requestid'];
                    $requestobject->origin_category_requests = json_encode($catcourserequests);
                    coursetransfer_request::insert_or_update($requestobject, $requestobject->id);
                }
            }

            return [
                    'success' => $success,
                    'errors' => $errors,
                    'data' => [
                        'requestid' => $requestobject->id,
                    ],
            ];
        } catch (moodle_exception $e) {
            $error = [
                    'code' => '11001',
                    'msg' => $e->getMessage(),
            ];
            $errors[] = $error;
            return [
                    'success' => false,
                    'errors' => $errors,
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
            stdClass $user, stdClass $site, int $targetcategoryid, int $origincategoryid,
            configuration_category $configuration): array {
        $errors = [];
        try {
            // 1. Category Request DB.
            $requestobject = coursetransfer_request::set_request_restore_category(
                    $site, $targetcategoryid, $origincategoryid, '', $configuration, $user
            );
            $request = new request($site);
            $res = $request->origin_get_category_detail_tree($origincategoryid, $user);
            if (!$res->success) {
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
                        $course, $user, $site, $targetcategoryid, $configuration,
                        $requestobject, $catcourserequests, $errors) && $success;
            }
            foreach (($data->categories ?? []) as $cat) {
                $success = self::restore_tree_category(
                        $cat, $user, $site, $targetcategoryid, $configuration,
                        $requestobject, $catcourserequests, $errors) && $success;
            }

            return [
                    'success' => $success,
                    'errors' => $errors,
                    'data' => ['requestid' => $requestobject->id],
            ];
        } catch (moodle_exception $e) {
            $errors[] = ['code' => '11001', 'msg' => $e->getMessage()];
            return ['success' => false, 'errors' => $errors];
        }
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
     * @return bool
     */
    protected static function restore_tree_category(
            stdClass $cat, stdClass $user, stdClass $site, int $parentid,
            configuration_category $configuration, stdClass $requestobject,
            array &$catcourserequests, array &$errors, int $depth = 0): bool {
        // Defensive guard against a pathologically deep (or crafted) tree.
        if ($depth > self::MAX_TREE_DEPTH) {
            $errors[] = ['code' => '11004', 'msg' => 'Maximum category tree depth exceeded'];
            return false;
        }
        $name = $cat->name ?? '';
        $idnumber = $cat->idnumber ?? '';
        $desc = $cat->description ?? '';
        $targetcategoryid = category::create($name, $idnumber, $desc, $parentid);
        $success = true;
        foreach (($cat->courses ?? []) as $course) {
            $success = self::restore_tree_course(
                    $course, $user, $site, $targetcategoryid, $configuration,
                    $requestobject, $catcourserequests, $errors) && $success;
        }
        foreach (($cat->categories ?? []) as $child) {
            $success = self::restore_tree_category(
                    $child, $user, $site, $targetcategoryid, $configuration,
                    $requestobject, $catcourserequests, $errors, $depth + 1) && $success;
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
            stdClass $course, stdClass $user, stdClass $site, int $targetcategoryid,
            configuration_category $configuration, stdClass $requestobject,
            array &$catcourserequests, array &$errors): bool {
        try {
            $configurationcourse = new configuration_course(
                    $configuration->targettarget,
                    $configuration->targetremoveenrols,
                    $configuration->targetremovegroups,
                    $configuration->originenrolusers,
                    false,
                    $configuration->nextruntime);
            $targetcourseid = course::create(
                    core_course_category::get($targetcategoryid),
                    $course->fullname, $course->shortname . '_' . uniqid());
            $courseres = self::restore_course_unity(
                    $user, $site, $targetcourseid, (int)$course->id, $configurationcourse, [], $requestobject->id);
            if (!$courseres['success']) {
                $errors = array_merge($errors, $courseres['errors']);
            }
            if (isset($courseres['data']['requestid'])) {
                $catcourserequests[] = $courseres['data']['requestid'];
                $requestobject->origin_category_requests = json_encode($catcourserequests);
                coursetransfer_request::insert_or_update($requestobject, $requestobject->id);
            }
            return (bool) $courseres['success'];
        } catch (moodle_exception $e) {
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
    protected static function restore_course_unity(stdClass $user, stdClass $site, int $targetcourseid, int $origincourseid,
            configuration_course $configuration, array $sections = [], int $requestcatid = null): array {

        $errors = [];
        // 1. Request DB.
        $requestobject = coursetransfer_request::set_request_restore_course($user,
                $site, $targetcourseid, $origincourseid, $configuration, $sections, $requestcatid);

        // 2. Call CURL Origin Backup Course.
        $request = new request($site);
        $res = $request->origin_backup_course(
                $user, $requestobject->id, $origincourseid, $targetcourseid, $configuration, $sections);
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
    public static function get_subcategories(core_course_category $category, stdClass $user = null): array {
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
    public static function get_childs(core_course_category $category, array &$categories, stdClass $user = null): void {
        if ($category->get_children_count() > 0) {
            foreach ($category->get_children() as $child) {
                if ($child->is_uservisible($user)) {
                    $categories[] = $child;
                    self::get_childs($child, $categories);
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

        // 3. Create User.
        $userid = user::create_user($roleid);

        // 4. Enable webservices.
        set_config('enablewebservices', 1);

        // 5. Activate REST protocol.
        set_config('webserviceprotocols', 'rest');

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
     * Accept the current site policies (tool_policy) on behalf of the WS user.
     *
     * No-op if the site policy tool is not present or no policies are defined.
     *
     * @param int $userid WS user id.
     */
    private static function accept_site_policies(int $userid): void {
        if (!class_exists(policyapi::class)) {
            return;
        }
        try {
            $versionids = policyapi::get_current_versions_ids();
            if (!empty($versionids)) {
                policyapi::accept_policies(array_values($versionids), $userid);
            }
        } catch (Throwable $e) {
            debugging('local_coursetransfer: could not accept site policies for the WS user: ' . $e->getMessage());
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
        $hascourse = false;
        $cs = get_courses();
        foreach ($cs as $course) {
            $context = context_course::instance($course->id);
            if (has_capability('moodle/backup:backupcourse', $context, $user->id) && (int)$course->id !== 1) {
                $hascourse = true;
                break;
            }
        }
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
            $startIndex = $currentpage * $perpage;
            $courses = array_slice($courses, $startIndex, $perpage);
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
     * Count categories User.
     *
     * @return int
     */
    public static function count_categories_user(): int {
        $categories = core_course_category::get_all();

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
        $categories = core_course_category::get_all();
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
        if (has_capability('local/coursetransfer:target_restore_content_remove', $context, $user->id) &&
                has_capability('local/coursetransfer:target_restore_merge', $context, $user->id)) {
            return true;
        }
        return false;
    }

    /**
     * Cleanup Course Bin.
     *
     * @param int $courseid
     * @param string $shortname
     * @throws dml_exception
     */
    public static function cleanup_course_bin(int $courseid, string $shortname): void {
        global $DB;
        if (get_config('local_coursetransfer', 'remove_course_cleanup')) {
            mtrace("Remove course items recycle bin enabled ...");
            try {
                $items = $DB->get_records('tool_recyclebin_course', ['courseid' => $courseid]);
                foreach ($items as $item) {
                    mtrace("[tool_recyclebin] Deleting item '{$item->id}' from the course recycle bin ...");
                    try {
                        $bin = new \tool_recyclebin\course_bin($item->courseid);
                        $bin->delete_item($item);
                        mtrace("[tool_recyclebin] Deleted item '{$item->name}' from the course recycle bin ...");
                    } catch (moodle_exception $e) {
                        mtrace("Error cleanup_course_bin item: " . $e->getMessage());
                    }
                }
            } catch (moodle_exception $e) {
                mtrace("Error cleanup_course_bin: " . $e->getMessage());
            }
            try {
                $item = $DB->get_record('tool_recyclebin_category', ['shortname' => $shortname]);
                if ($item) {
                    mtrace("[tool_recyclebin] Deleting item '{$item->shortname}' from the category recycle bin ...");
                    try {
                        $bin = new \tool_recyclebin\category_bin($item->categoryid);
                        $bin->delete_item($item);
                        mtrace("[tool_recyclebin] Deleted item '{$item->shortname}' from the category recycle bin ...");
                    } catch (moodle_exception $e) {
                        mtrace("Error cleanup_course_bin item: " . $e->getMessage());
                    }
                }
            } catch (moodle_exception $e) {
                mtrace("Error cleanup_course_bin: " . $e->getMessage());
            }
        } else {
            mtrace("remove_course_cleanup is not active");
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
        $context = \context_course::instance($course->id);
        if (!has_capability('moodle/backup:backupcourse', $context, $user->id)) {
            return false;
        }
        if ((int)$course->id === 1) {
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
