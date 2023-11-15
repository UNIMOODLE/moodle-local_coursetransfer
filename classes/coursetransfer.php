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
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use backup;
use backup_controller;
use base_plan_exception;
use base_setting;
use base_setting_exception;
use cm_info;
use coding_exception;
use context;
use context_course;
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
use local_coursetransfer\task\create_backup_course_task;
use local_coursetransfer\task\download_file_course_task;
use moodle_exception;
use moodle_url;
use restore_controller;
use section_info;
use stdClass;
use stored_file;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/local/coursetransfer/classes/task/create_backup_course_task.php');

/**
 * coursetransfer
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer {

    const FIELDS_USER = ['username', 'email', 'userid', 'idnumber'];

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
    protected $course;

    /** @var request Request */
    protected $request;

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
     * @throws dml_exception|coding_exception
     */
    public function origin_get_courses(): response {
        global $USER;
        return $this->request->origin_get_courses($USER);
    }

    /**
     * Verify Destiny Site.
     *
     * @param string $destinysite
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function verify_destiny_site(string $destinysite): array {
        $res = new stdClass();
        $res->host = $destinysite;
        $record = coursetransfer_sites::get_by_host('destiny', $destinysite);
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
                    'msg' => ''
                    ]
            ];
        } else {
            return
            [
                'success' => false,
                'data' => new stdClass(),
                'error' =>
                        [
                                'code' => '110007',
                                'msg' => 'Destination site not founded'
                        ]
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
        if (in_array($field, self::FIELDS_USER)) {
            $res = $DB->get_record('user', [$field => $value]);
            if ($res) {
                $user = core_user::get_user($res->id);
                $hascourse = self::has_course($user);
                if ($hascourse) {
                    return
                        [
                            'success' => true,
                            'data' => $user,
                            'error' =>
                                [
                                    'code' => '',
                                    'msg' => ''
                                ]
                        ];
                } else {
                    return
                        [
                            'success' => false,
                            'data' => new stdClass(),
                            'error' =>
                                [
                                    'code' => '11006',
                                    'msg' => get_string('user_does_not_have_courses', 'local_coursetransfer')
                                ]
                        ];
                }

            } else {
                return
                    [
                        'success' => false,
                        'data' => new stdClass(),
                        'error' =>
                            [
                                'code' => '11005',
                                'msg' => get_string('user_not_found', 'local_coursetransfer')
                            ]
                    ];
            }

        } else {
            return
                [
                    'success' => false,
                    'data' => new stdClass(),
                    'error' =>
                        [
                            'code' => '11004',
                            'msg' => get_string('field_not_valid', 'local_coursetransfer')
                        ]
                ];
        }
    }

    /**
     * Validate Origin Site
     *
     * @param string $siteurl
     * @return bool
     */
    public static function validate_origin_site(string $siteurl): bool {
        // TODO: comprobar que en el setting la url existe (comparacion de url).
        return true;
    }

    /**
     * Get token Origin site
     *
     * @param string $originurl
     * @return string
     * @throws dml_exception|moodle_exception
     */
    public static function get_token_origin_site(string $originurl): string {
        $token = '';
        $host = parse_url($originurl, PHP_URL_HOST);
        $scheme = parse_url($originurl, PHP_URL_SCHEME);
        $reshost = $scheme . '://' . $host;
        $originsites = coursetransfer_sites::list('origin');
        if ($originsites) {
            foreach ($originsites as $site) {
                if (isset($site->host) && isset($site->token)) {
                    if ($site->host === $reshost) {
                        $token = $site->token;
                        break;
                    }
                }
            }
        }
        if (empty($token)) {
            throw new moodle_exception('DOWLOAD TOKEN IS INVALID');
        }
        return $token;
    }

    /**
     * Get Backup Size Estimated
     *
     * @param int $courseid
     * @return int
     */
    public static function get_backup_size_estimated(int $courseid): int {
        // TODO: calcular tamaño estimado del backup.
        return 0;
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
                'activities' => self::get_activities_by_section($modinfo, $section->id)
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
                    'modname' => $module->modname
                ];
                $activities[] = $activity;
            }
        }
        return $activities;
    }

    /**
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
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_site_by_url(string $url): stdClass {
        $res = new stdClass();
        $record = coursetransfer_sites::get_by_host('origin', $url);
        if (isset($record->host) && isset($record->token)) {
            $res->host = $record->host;
            $res->token = $record->token;
        } else {
            throw new moodle_exception('Origin site not valid: ' . $url);
        }
        return $res;
    }

    /**
     * Create Task to back up of Course.
     *
     * @param int $courseid
     * @param int $userid
     * @param stdClass $destinysite
     * @param int $requestid
     * @param int $requestoriginid
     * @param array $sections
     * @param int $rootusers
     * @throws base_plan_exception
     * @throws base_setting_exception
     * @throws moodle_exception
     */
    public static function create_task_backup_course(
            int $courseid, int $userid, stdClass $destinysite, int $requestid, int $requestoriginid,
            array $sections, int $rootusers = 0) {

        $bc = new backup_controller(
                backup::TYPE_1COURSE, $courseid,
                backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO,
                backup::MODE_GENERAL, $userid,
                backup::RELEASESESSION_NO);
        $bc->set_status(backup::STATUS_AWAITING);
        $bc->set_status(backup::STATUS_AWAITING);
        $bc->get_plan()->get_setting('users')->set_status(base_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($rootusers);
        $bc->get_plan()->get_setting('role_assignments')->set_status(base_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('role_assignments')->set_value($rootusers);
        $bc->get_plan()->get_setting('comments')->set_status(base_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('comments')->set_value($rootusers);
        $bc->get_plan()->get_setting('badges')->set_status(base_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('badges')->set_value($rootusers);
        $bc->get_plan()->get_setting('userscompletion')->set_status(base_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('userscompletion')->set_value($rootusers);

        self::set_value_settings_section_activities($bc, $courseid, $rootusers, $sections);

        $bc->set_execution(backup::EXECUTION_DELAYED);
        $bc->save_controller();
        $backupid = $bc->get_backupid();
        $asynctask = new create_backup_course_task();
        $asynctask->set_blocking(false);
        $asynctask->set_custom_data(
                [
                        'backupid' => $backupid,
                        'destinysite' => $destinysite->id,
                        'requestid' => $requestid,
                        'requestoriginid' => $requestoriginid
                ]);
        $asynctask->set_userid($userid);
        \core\task\manager::queue_adhoc_task($asynctask);
    }

    /**
     * Set Value in settings for sections and activities.
     *
     * @param backup_controller $bc
     * @param int $courseid
     * @param int $rootusers
     * @param array $sectionsselected
     * @throws base_plan_exception
     * @throws base_setting_exception
     * @throws moodle_exception
     */
    public static function set_value_settings_section_activities(backup_controller $bc, int $courseid, int $rootusers,
            array $sectionsselected) {
        if (!empty($sectionsselected)) {
            $bc->get_plan()->set_excluding_activities();
            $modinfo = get_fast_modinfo($courseid);
            $sections = $modinfo->get_section_info_all();
            $cms = $modinfo->get_cms();
            foreach ($sections as $section) {
                $value = self::section_is_included($section, $sectionsselected);
                $rootusers = ($value === 1) && ($rootusers === 1);
                $nameincluded = 'section_' . $section->id . '_included';
                $bc->get_plan()->get_setting($nameincluded)->set_value($value);
                $nameuserinfo = 'section_' . $section->id . '_userinfo';
                $bc->get_plan()->get_setting($nameuserinfo)->set_value($rootusers);
            }
            foreach ($cms as $cm) {
                if (!$cm->deletioninprogress) {
                    $value = self::cm_is_included($cm, $sectionsselected);
                    $rootusers = ($value === 1) && ($rootusers === 1);
                    $nameincluded = $cm->modname . '_' . $cm->id . '_included';
                    $bc->get_plan()->get_setting($nameincluded)->set_value($value);
                    $nameuserinfo = $cm->modname . '_' . $cm->id . '_userinfo';
                    $bc->get_plan()->get_setting($nameuserinfo)->set_value($rootusers);
                }

            }
        }
    }

    /**
     * Section is included?
     *
     * @param section_info $section
     * @param array $sections
     * @return int
     */
    protected static function section_is_included(section_info $section, array $sections): int {
        foreach ($sections as $s) {
            if ((int)$section->id === (int)$s['sectionid']) {
                if ((int)$s['selected'] === 1) {
                    return 1;
                }
            }
        }
        return 0;
    }

    /**
     * Course Module is included?
     *
     * @param cm_info $cm
     * @param array $sections
     * @return int
     */
    protected static function cm_is_included(cm_info $cm, array $sections): int {
        foreach ($sections as $s) {
            foreach ($s['activities'] as $activity) {
                if ((int)$cm->id === (int)$activity['cmid']) {
                    if ((int)$activity['selected'] === 1) {
                        return 1;
                    }
                }
            }
        }
        return 0;
    }

    /**
     * Create Task to dowload Course.
     *
     * @param stdClass $request
     * @param string $fileurl
     * @param int $target
     */
    public static function create_task_download_course(stdClass $request, string $fileurl) {
        $asynctask = new download_file_course_task();
        $asynctask->set_blocking(false);
        $asynctask->set_custom_data(
                array('request' => $request, 'fileurl' => $fileurl)
        );
        \core\task\manager::queue_adhoc_task($asynctask);
    }

    /**
     * Create Task to restore Course.
     *
     * @param stdClass $request
     * @param stored_file $file
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function create_task_restore_course(stdClass $request, stored_file $file) {
        try {
            $courseid = (int)$request->destiny_course_id;
            $userid = (int)$request->userid;
            $fullname = $request->origin_course_fullname;
            $shortname = $request->origin_course_shortname;
            $removeenrols = (int)$request->destiny_remove_enrols;
            $removegroups = (int)$request->destiny_remove_groups;
            $target = (int)$request->destiny_target;

            $backuptmpdir = 'local_coursetransfer';

            if (!check_dir_exists($backuptmpdir, true, true)) {
                throw new \restore_controller_exception('cannot_create_backup_temp_dir');
            }

            $filepath = restore_controller::get_tempdir_name($file->get_contextid(), $userid);
            $backuptempdir = make_backup_temp_directory('', false);
            $fb = get_file_packer('application/vnd.moodle.backup');

            $fb->extract_to_pathname($file, $backuptempdir . '/' . $filepath . '/');

            if ($target !== backup::TARGET_EXISTING_DELETING && $target !== backup::TARGET_CURRENT_DELETING) {
                $keeprolesenrolments = 0;
                $keepgroupsgroupings = 0;
            } else {
                $keeprolesenrolments = $removeenrols === 1 ? 0 : 1;
                $keepgroupsgroupings = $removegroups === 1 ? 0 : 1;
            }

            $restoreoptions = [
                    'overwrite_conf' => 0,
                    'keep_roles_and_enrolments' => $keeprolesenrolments,
                    'keep_groups_and_groupings' => $keepgroupsgroupings
            ];

            if ($target === backup::TARGET_NEW_COURSE) {
                $restoreoptions['overwrite_conf'] = 1;
                $restoreoptions['course_fullname'] = $fullname;
                $restoreoptions['course_shortname'] = $shortname;
            }

            if ($target === backup::TARGET_NEW_COURSE) {
                $target = backup::TARGET_EXISTING_DELETING;
            }

            $rc = new restore_controller($filepath, $courseid,
                    backup::INTERACTIVE_NO, backup::MODE_GENERAL, $userid, $target);

            foreach ($restoreoptions as $option => $value) {
                $rc->get_plan()->get_setting($option)->set_status(\base_setting::NOT_LOCKED);
                $rc->get_plan()->get_setting($option)->set_value($value);
            }

            if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
                $rc->convert();
            }

            // Execute restore.
            $rc->execute_precheck();
            $rc->execute_plan();
            $rc->destroy();

        } catch (\Exception $e) {
            $request->status = coursetransfer_request::STATUS_ERROR;
            $request->error_code = '110003';
            $request->error_message = $e->getMessage();
            coursetransfer_request::insert_or_update($request, $request->id);
        }
    }

    /**
     * Create backupfile.
     *
     * @param int $courseid
     * @param stored_file $file
     * @return stdClass
     */
    public static function create_backupfile_url(int $courseid, stored_file $file): stdClass {
        $res = new stdClass();
        $error = '';
        $url = '';
        $filesize = 0;

        try {
            $context = context_course::instance($courseid);
            $fs = get_file_storage();
            $timestamp = time();

            $filerecord = array(
                    'contextid' => $context->id,
                    'component' => 'local_coursetransfer',
                    'filearea' => 'backup',
                    'itemid' => $timestamp,
                    'filepath' => '/',
                    'filename' => 'backup.mbz',
                    'timecreated' => $timestamp,
                    'timemodified' => $timestamp
            );
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
            $sizeconfig = (int)get_config('local_coursetransfer', 'destiny_restore_course_max_size');
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
     * @param int $destinycourseid
     * @param int $origincourseid
     * @param configuration_course $configuration
     * @param array|null $sections
     * @return array
     */
    public static function restore_course(stdClass $user,
            stdClass $site, int $destinycourseid, int $origincourseid,
            configuration_course $configuration, array $sections = []): array {

        try {
            return self::restore_course_unity($user, $site, $destinycourseid, $origincourseid, $configuration, $sections);
        } catch (moodle_exception $e) {
            $error = [
                    'code' => '110002',
                    'msg' => $e->getMessage()
            ];
            $errors[] = $error;
            return [
                    'success' => false,
                    'errors' => $errors
            ];
        }
    }

    /**
     * Remove Course.
     *
     * @param stdClass $site
     * @param int $origincourseid
     * @param stdClass|null $user
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function remove_course(stdClass $site, int $origincourseid, stdClass $user = null): array {

        $errors = [];

        // 1. Request DB.
        $requestobject = coursetransfer_request::set_request_remove_course($site, $origincourseid, $user);

        // 2. Call CURL Origin Backup Course.
        $request = new request($site);
        $res = $request->origin_remove_course($requestobject->id, $origincourseid, $user);
        // 3. Success or Errors.
        if ($res->success) {
            // 4a. Update Request DB Completed.
            $requestobject->status = coursetransfer_request::STATUS_COMPLETED;
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
                        'requestid' => $requestobject->id
                ]
        ];
    }

    /**
     * Remove Category.
     *
     * @param stdClass $site
     * @param int $origincatid
     * @param stdClass|null $user
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function remove_category(stdClass $site, int $origincatid, stdClass $user = null): array {

        $errors = [];

        // 1. Request DB.
        $requestobject = coursetransfer_request::set_request_remove_category($site, $origincatid, $user);

        // 2. Call CURL Origin Backup Course.
        $request = new request($site);
        $res = $request->origin_remove_category($requestobject->id, $origincatid, $user);
        // 3. Success or Errors.
        if ($res->success) {
            // 4a. Update Request DB Completed.
            $requestobject->status = coursetransfer_request::STATUS_COMPLETED;
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
                        'requestid' => $requestobject->id
                ]
        ];
    }

    /**
     * Restore Category.
     *
     * @param stdClass $user
     * @param stdClass $site
     * @param int $destinycategoryid
     * @param int $origincategoryid
     * @param configuration_category $configuration $configuration
     * @param array $courses
     * @return array
     */
    public static function restore_category(
            stdClass $user, stdClass $site, int $destinycategoryid, int $origincategoryid,
            configuration_category $configuration, array $courses = []): array {

        try {
            $request = new request($site);
            $origincategoryname = '';
            if (count($courses) === 0) {
                // 1a. Call CURL Origin Get Category Detail for courses list.
                $res = $request->origin_get_category_detail($origincategoryid, $user);
                if ($res->success) {
                    $courses = $res->data->courses;
                    $origincategoryname = $res->data->name;
                } else {
                    throw new moodle_exception(json_encode($res->errors));
                }
            } else {
                // 1b. Call CURL Origin Get Course Detail from courses list.
                $courses = self::get_courses_detail($user, $site, $courses);
            }

            // 2. If destinycategoryid is new (0)
            if ($destinycategoryid === 0) {
                $destinycategoryid = category::create(get_string('newcategory', 'grades'));
            }

            // 2. Category Request DB.
            $requestobject = coursetransfer_request::set_request_restore_category(
                    $site, $destinycategoryid, $origincategoryid, $origincategoryname, $configuration, $user
            );

            $success = true;
            $errors = [];
            $catcourserequests = [];

            foreach ($courses as $course) {

                // 1. Configuration Course.
                $configurationcourse = new configuration_course(
                        $configuration->destinytarget, $configuration->destinyremovegroups,
                $configuration->destinyremoveenrols, $configuration->originenrolusers);

                // 2. Create new course in this category.
                $destinycourseid = course::create(
                        core_course_category::get($destinycategoryid),
                        $course->fullname, $course->shortname . uniqid());
                $origincourseid = $course->id;

                // 3. Request Restore Course.
                $courseres = self::restore_course_unity(
                        $user, $site, $destinycourseid, $origincourseid, $configurationcourse, [], $requestobject->id);

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
                        'requestid' => $requestobject->id
                    ]
            ];
        } catch (moodle_exception $e) {
            $error = [
                    'code' => '110001',
                    'msg' => $e->getMessage()
            ];
            $errors[] = $error;
            return [
                    'success' => false,
                    'errors' => $errors
            ];
        }
    }

    /**
     * Restore Course Unity.
     *
     * @param stdClass $user
     * @param stdClass $site
     * @param int $destinycourseid
     * @param int $origincourseid
     * @param configuration_course $configuration
     * @param array $sections
     * @param int|null $requestcatid
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected static function restore_course_unity(stdClass $user, stdClass $site, int $destinycourseid, int $origincourseid,
            configuration_course $configuration, array $sections = [], int $requestcatid = null): array {

        $errors = [];
        // 1. Request DB.
        $requestobject = coursetransfer_request::set_request_restore_course($user,
                $site, $destinycourseid, $origincourseid, $configuration, $sections, $requestcatid);

        // 2. Call CURL Origin Backup Course.
        $request = new request($site);
        $res = $request->origin_backup_course(
                $user, $requestobject->id, $origincourseid, $destinycourseid, $configuration, $sections);
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
                        'requestid' => $requestobject->id
                ]
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
    public static function get_childs(core_course_category $category, array &$categories, stdClass $user = null) {
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

        // 3. Create User.
        $userid = user::create_user($roleid);

        // 4. Create Token.
        return user::create_token($userid);

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
            $context = \context_course::instance($course->id);
            if (has_capability('moodle/backup:backupcourse', $context, $user->id) && (int)$course->id !== 1) {
                $hascourse = true;
                break;
            }
        }
        return $hascourse;
    }

    /**
     * Get Courses User.
     *
     * @param stdClass $user
     * @return array
     * @throws coding_exception
     */
    public static function get_courses_user(stdClass $user): array {
        $courses = [];
        $cs = get_courses();
        foreach ($cs as $course) {
            if (self::filter_course($course, $user)) {
                $courses[] = $course;
            }
        }
        return $courses;
    }

    /**
     * Get Categories User.
     *
     * @param stdClass $user
     * @return array
     */
    public static function get_categories_user(stdClass $user): array {
        $categories = [];
        $cs = \core_course_category::get_all();
        foreach ($cs as $cat) {
            if (self::filter_category($cat, $user)) {
                $categories[] = $cat;
            }
        }
        return $categories;
    }

    /**
     * Filter Course.
     *
     * @param stdClass $course
     * @param stdClass $user
     * @return bool
     * @throws coding_exception
     */
    protected static function filter_course(stdClass $course, stdClass $user): bool {
        $context = \context_course::instance($course->id);
        if (!has_capability('moodle/backup:backupcourse', $context, $user->id)) {
            return false;
        }
        if ((int)$course->id === 1) {
            return false;
        }
        return true;
    }

    /**
     * Filter Category.
     *
     * @param core_course_category $cat
     * @param stdClass $user
     * @return bool
     */
    protected static function filter_category(core_course_category $cat, stdClass $user): bool {
        if ($cat->is_uservisible($user)) {
            return true;
        }
        return false;
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
        $context = \context_system::instance();
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
        $context = \context_system::instance();
        return has_capability('local/coursetransfer:origin_restore_course_users', $context, $user->id);
    }

    /**
     * Can destiny restore merge?
     *
     * @param stdClass $user
     * @param context $context
     * @return false
     * @throws coding_exception
     */
    public static function can_destiny_restore_merge(stdClass $user, context $context): bool {
        return has_capability('local/coursetransfer:destiny_restore_merge', $context, $user->id);
    }

    /**
     * Can Destination restore content remove?
     *
     * @param stdClass $user
     * @param context $context
     * @return false
     * @throws coding_exception
     */
    public static function can_destiny_restore_content_remove(stdClass $user, context $context): bool {
        return has_capability('local/coursetransfer:destiny_restore_content_remove', $context, $user->id);
    }

    /**
     * Can Destination restore groups remove?
     *
     * @param stdClass $user
     * @return false
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function can_destiny_restore_groups_remove(stdClass $user): bool {
        $context = \context_system::instance();
        return has_capability('local/coursetransfer:destiny_restore_groups_remove', $context, $user->id);
    }

    /**
     * Can Destination restore enrols remove?
     *
     * @param stdClass $user
     * @return false
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function can_destiny_restore_enrol_remove(stdClass $user): bool {
        $context = \context_system::instance();
        return has_capability('local/coursetransfer:destiny_restore_enrol_remove', $context, $user->id);
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
        $context = \context_system::instance();
        if (has_capability('local/coursetransfer:destiny_restore_content_remove', $context, $user->id) &&
                has_capability('local/coursetransfer:destiny_restore_merge', $context, $user->id)) {
            return true;
        }
        return false;
    }

}
