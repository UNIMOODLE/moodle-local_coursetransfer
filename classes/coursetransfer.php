<?php
// This file is part of the local_amnh plugin for Moodle - http://moodle.org/
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
 * coursetransfer
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use backup;
use backup_controller;
use base_plan_exception;
use base_setting_exception;
use cm_info;
use coding_exception;
use context_course;
use core_course_category;
use course_modinfo;
use dml_exception;
use file_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\api\response;
use local_coursetransfer\factory\course;
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
use stored_file_creation_exception;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/local/coursetransfer/classes/task/create_backup_course_task.php');

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
        $originsites = get_config('local_coursetransfer', 'origin_sites');
        $originsites = explode(PHP_EOL, $originsites);
        foreach ($originsites as $site) {
            $site = explode(';', $site);
            $items[] = $site[0];
        }
        return $items;
    }

    /**
     * Origin Has User?.
     *
     * @return response
     * @throws dml_exception
     */
    public function origin_has_user(): response {
        return $this->request->origin_has_user();
    }

    /**
     * Origin Get Courses?.
     *
     * @return response
     * @throws dml_exception
     */
    public function origin_get_courses(): response {
        return $this->request->origin_get_courses();
    }

    /**
     * Verify Destiny Site.
     *
     * @param string $destinysite
     * @return array
     * @throws dml_exception
     */
    public static function verify_destiny_site(string $destinysite): array {
        $res = new stdClass();
        $res->host = $destinysite;
        $founded = false;
        $originsites = get_config('local_coursetransfer', 'destiny_sites');
        $originsites = explode(PHP_EOL, $originsites);
        foreach ($originsites as $site) {
            $site = explode(';', $site);
            if ($destinysite === $site[0]) {
                $res->token = $site[1];
                $founded = true;
                break;
            }
        }
        if (!$founded) {
            return
                [
                    'success' => false,
                    'data' => new stdClass(),
                    'error' =>
                        [
                            'code' => '200300',
                            'msg' => 'Destiny site not founded'
                        ]
                ];
        }

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
                if ($res->username === user::USERNAME_WS) {
                    $hascourse = true;
                } else {
                    $courses = enrol_get_users_courses($res->id);
                    $hascourse = false;
                    foreach ($courses as $course) {
                        $context = \context_course::instance($course->id);
                        if (has_capability('moodle/backup:backupcourse', $context, $res->id)) {
                            $hascourse = true;
                            break;
                        }
                    }
                }

                if ($hascourse) {
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
                                    'code' => '200310',
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
                                'code' => '200311',
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
                            'code' => '200312',
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
     * @throws dml_exception
     */
    public static function get_token_origin_site(string $originurl): string {
        $token = '';
        $host = parse_url($originurl, PHP_URL_HOST);
        $scheme = parse_url($originurl, PHP_URL_SCHEME);
        $reshost = $scheme . '://' . $host;
        $originsites = get_config('local_coursetransfer', 'origin_sites');
        $originsites = explode(PHP_EOL, $originsites);
        foreach ($originsites as $site) {
            $site = explode(';', $site);
            if ($site[0] === $reshost) {
                $token = $site[1];
                break;
            }
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
        return 320;
    }

    /**
     * Get Backup Size Estimated
     *
     * @return int
     */
    public static function get_backup_size(): int {
        // TODO: calcular tamaño del backup.
        return 150;
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
     * @param int $position
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_site_by_position(int $position): stdClass {
        $originsites = get_config('local_coursetransfer', 'origin_sites');
        $originsites = explode(PHP_EOL, $originsites);
        $res = new stdClass();
        if (isset($originsites[$position])) {
            $site = $originsites[$position];
            $site = explode(';', $site);
            if (isset($site[0]) && isset($site[1])) {
                $res->host = $site[0];
                $res->token = $site[1];
            }
        }
        if (empty($res->host) || empty($res->token)) {
            throw new moodle_exception('SITE NOT VALID');
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
        $originsites = get_config('local_coursetransfer', 'origin_sites');
        $originsites = explode(PHP_EOL, $originsites);
        $res = new stdClass();
        foreach ($originsites as $originsite) {
            $site = explode(';', $originsite);
            if ($site[0] === $url) {
                $res->host = $site[0];
                $res->token = $site[1];
                break;
            }
        }
        if (empty($res->host) || empty($res->token)) {
            throw new moodle_exception('SITE NOT VALID');
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
        $bc->get_plan()->get_setting('users')->set_value($rootusers);
        $bc->get_plan()->get_setting('role_assignments')->set_value($rootusers);
        $bc->get_plan()->get_setting('comments')->set_value($rootusers);
        $bc->get_plan()->get_setting('badges')->set_value($rootusers);
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
                        'destinysite' => $destinysite,
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
                //error_log('** sec name included: ' . $nameincluded);
                $bc->get_plan()->get_setting($nameincluded)->set_value($value);
                $nameuserinfo = 'section_' . $section->id . '_userinfo';
                //error_log('** sec name userinfo: ' . $nameuserinfo);
                $bc->get_plan()->get_setting($nameuserinfo)->set_value($rootusers);
            }
            foreach ($cms as $cm) {
                if (!$cm->deletioninprogress) {
                    $value = self::cm_is_included($cm, $sectionsselected);
                    $rootusers = ($value === 1) && ($rootusers === 1);
                    $nameincluded = $cm->modname . '_' . $cm->id . '_included';
                    //error_log('** cm name included: ' . $nameincluded);
                    $bc->get_plan()->get_setting($nameincluded)->set_value($value);
                    $nameuserinfo = $cm->modname . '_' . $cm->id . '_userinfo';
                    //error_log('** cm name userinfo: ' . $nameuserinfo);
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
            $request->error_code = '200320';
            $request->error_message = $e->getMessage();
            coursetransfer_request::insert_or_update($request, $request->id);
        }
    }

    /**
     * Create backupfile.
     *
     * @param int $courseid
     * @param stored_file $file
     * @return string
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    public static function create_backupfile_url(int $courseid, stored_file $file): string {
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
        return $fileurl->out(true);
    }

    /**
     * Restore Course.
     *
     * @param stdClass $site
     * @param int $destinycourseid
     * @param int $origincourseid
     * @param configuration_course $configuration
     * @param array|null $sections
     * @return array
     */
    public static function restore_course(
            stdClass $site, int $destinycourseid, int $origincourseid,
            configuration_course $configuration, array $sections = []): array {

        try {
            return self::restore_course_unity($site, $destinycourseid, $origincourseid, $configuration, $sections);
        } catch (moodle_exception $e) {
            $error = [
                    'code' => '200330',
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
     * Restore Category.
     *
     * @param stdClass $site
     * @param int $destinycategoryid
     * @param int $origincategoryid
     * @param configuration_category $configuration $configuration
     * @param array $courses
     * @return array
     */
    public static function restore_category(
            stdClass $site, int $destinycategoryid, int $origincategoryid,
            configuration_category $configuration, array $courses = []): array {

        try {
            $request = new request($site);
            $origincategoryname = '';
            if (count($courses) === 0) {
                // 1a. Call CURL Origin Get Category Detail for courses list.
                $res = $request->origin_get_category_detail($origincategoryid);
                if ($res->success) {
                    $courses = $res->data->courses;
                    $origincategoryname = $res->data->name;
                } else {
                    throw new moodle_exception(json_encode($res->errors));
                }
            } else {
                // 1b. Call CURL Origin Get Course Detail from courses list.
                $courses = self::get_courses_detail($site, $courses);
            }

            // 2. Category Request DB.
            $requestobject = coursetransfer_request::set_request_restore_category(
                    $site, $destinycategoryid, $origincategoryid, $origincategoryname, $configuration
            );

            $success = true;
            $errors = [];
            $catcourserequests = [];

            foreach ($courses as $course) {

                // 1. Configuration Course.
                $configurationcourse = new configuration_course($configuration->destinytarget, $configuration->destinyremovegroups,
                $configuration->destinyremoveenrols, $configuration->originenrolusers);

                // 2. Create new course in this category.
                $destinycourseid = course::create(
                        core_course_category::get($destinycategoryid),
                        $course->fullname, $course->shortname . uniqid());
                $origincourseid = $course->id;

                // 3. Request Restore Course.
                $courseres = self::restore_course_unity(
                        $site, $destinycourseid, $origincourseid, $configurationcourse, [], $requestobject->id);

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
                    'code' => '200340',
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
    protected static function restore_course_unity(stdClass $site, int $destinycourseid, int $origincourseid,
            configuration_course $configuration, array $sections = [], int $requestcatid = null): array {

        $errors = [];

        // 1. Request DB.
        $requestobject = coursetransfer_request::set_request_restore_course(
                $site, $destinycourseid, $origincourseid, $configuration, $sections, $requestcatid);

        // 2. Call CURL Origin Backup Course.
        $request = new request($site);
        $res = $request->origin_backup_course($requestobject->id, $origincourseid, $destinycourseid, $configuration, $sections);
        // 3. Success or Errors.
        if ($res->success) {
            // 4a. Update Request DB Completed.
            $requestobject->status = coursetransfer_request::STATUS_IN_PROGRESS;
            $requestobject->origin_course_fullname = $res->data->course_fullname;
            $requestobject->origin_course_shortname = $res->data->course_shortname;
            $requestobject->origin_course_idnumber = $res->data->course_idnumber;
            $requestobject->origin_category_id = $res->data->course_category_id;
            $requestobject->origin_category_name = $res->data->course_category_name;
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
     * @param stdClass $site
     * @param array $courses
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_courses_detail(stdClass $site, array $courses): array {
        $request = new request($site);
        $items = [];
        foreach ($courses as $course) {
            $res = $request->origin_get_course_detail($course['id']);
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
     * @return core_course_category[]
     */
    public static function get_subcategories(core_course_category $category): array {
        $categories = [];
        self::get_childs($category, $categories);
        return $categories;
    }

    /**
     * Get Subcategories.
     *
     * @param core_course_category $category
     * @param array $categories
     */
    public static function get_childs(core_course_category $category, array &$categories) {
        if ($category->get_children_count() > 0) {
            foreach ($category->get_children() as $child) {
                $categories[] = $child;
                self::get_childs($child, $categories);
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

}
