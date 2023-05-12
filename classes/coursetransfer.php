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
use context_course;
use course_modinfo;
use dml_exception;
use file_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\api\response;
use local_coursetransfer\task\create_backup_course_task;
use moodle_exception;
use moodle_url;
use stdClass;
use stored_file;
use stored_file_creation_exception;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/local/coursetransfer/classes/task/create_backup_course_task.php');

class coursetransfer {

    const FIELDS_USER = ['username', 'email', 'userid'];

    const STATUS = [
        0 => ['shortname' => 'error', 'alert' => 'danger'],
        1 => ['shortname' => 'not_started', 'alert' => 'warning'],
        10 => ['shortname' => 'in_progress', 'alert' => 'primary'],
        30 => ['shortname' => 'in_backup', 'alert' => 'primary'],
        50 => ['shortname' => 'incompleted', 'alert' => 'secondary'],
        70 => ['shortname' => 'download', 'alert' => 'info'],
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
     * Set Request.
     *
     * @param string $site
     * @param string $token
     */
    public function set_request(string $site, string $token) {
        $this->request = new request($site, $token);
    }

    /**
     * Get Origin Sites.
     *
     * @return array
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
     * @param string $site
     * @param string $token
     * @return response
     */
    public function origin_has_user(): response {
        return $this->request->origin_has_user();
    }

    /**
     * Origin Get Courses?.
     *
     * @return response
     */
    public function origin_get_courses(): response {
        return $this->request->origin_get_courses();
    }

    /**
     * @param string $destinysite
     * @return array
     */
    public static function verify_destiny_site(string $destinysite) {
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
                            'code' => '34375',
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
     * @param string $field
     * @param string $value
     * @return array
     */
    public static function auth_user(string $field, string $value): array {
        global $DB;

        if (in_array($field, self::FIELDS_USER)) {
            $res = $DB->get_record('user', [$field => $value]);
            if ($res) {
                $courses = enrol_get_users_courses($res->id);
                $hascourse = false;
                foreach ($courses as $course) {
                    $context = \context_course::instance($course->id);
                    if (has_capability('moodle/backup:backupcourse', $context, $res->id)) {
                        $hascourse = true;
                        break;
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
                                    'code' => '030343',
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
                                'code' => '030341',
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
                            'code' => '030342',
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
            if ((int)$module->section === $section) {
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
     * Create Task to back up of Course.
     *
     * @param int $courseid
     * @param int $userid
     * @param stdClass $destinysite
     * @param int $requestid
     * @param stdClass $destinysite;
     */
    public static function create_task_backup_course(int $courseid, int $userid, stdClass $destinysite, int $requestid) {
        $bc = new backup_controller(backup::TYPE_1COURSE, $courseid, backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $userid, backup::RELEASESESSION_NO);
        $bc->set_status(backup::STATUS_AWAITING);
        $bc->set_execution(backup::EXECUTION_DELAYED);
        $bc->save_controller();
        $backupid = $bc->get_backupid();
        $asynctask = new create_backup_course_task();
        $asynctask->set_blocking(false);
        $asynctask->set_custom_data(array('backupid' => $backupid, 'destinysite' => $destinysite, 'requestid' => $requestid));
        $asynctask->set_userid($userid);
        \core\task\manager::queue_adhoc_task($asynctask);
    }

    /**
     * Create backupfile.
     *
     * @param int $courseid
     * @param stored_file $file
     * @return array
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
}
