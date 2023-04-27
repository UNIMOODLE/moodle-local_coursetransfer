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

use course_modinfo;
use local_coursetransfer\api\request;
use local_coursetransfer\api\response;
use moodle_exception;
use stdClass;

class coursetransfer {

    const FIELDS_USER = ['username', 'email', 'userid'];

    const STATUS = [
        0 => ['shortname' => 'error', 'alert' => 'danger'],
        1 => ['shortname' => 'not_started', 'alert' => 'warning'],
        10 => ['shortname' => 'in_progress', 'alert' => 'primary'],
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
     * @param int $courseid
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
     * @throws \dml_exception
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
        return $res;
    }
}
