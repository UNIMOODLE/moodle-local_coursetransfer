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
 * Manifest of a transferred course, checked before the origin is removed.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use backup_general_helper;
use local_coursetransfer\api\request;
use stdClass;
use Throwable;

/**
 * What the origin course had, compared with what the restore left on this site.
 *
 * Compared: sections and activities per module (from the origin, through origin_get_course_detail, when the
 * removal is decided) and, when users were included, enrolled users (from the backup: no service of 2.1 gives
 * them). The restored course may hold more (merge, sections added by the format); never less.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_manifest {
    /**
     * Whether the manifest is checked before removing the origin (verifymanifest, on by default).
     *
     * @return bool
     */
    public static function enabled(): bool {
        $value = get_config('local_coursetransfer', 'verifymanifest');
        return $value === false || $value === '' || (bool)$value;
    }

    /**
     * Read from an extracted backup what it carries (before the restore deletes the folder).
     *
     * @param string $tempdir Backup temp dir name.
     * @return array
     */
    public static function from_backup(string $tempdir): array {
        $info = backup_general_helper::get_backup_information($tempdir);
        $activities = [];
        foreach ($info->activities ?? [] as $activity) {
            $activities[$activity->modulename] = ($activities[$activity->modulename] ?? 0) + 1;
        }
        ksort($activities);
        $manifest = ['sections' => count($info->sections ?? []), 'activities' => $activities, 'enrolments' => null];
        if (!empty($info->root_settings['users'])) {
            $file = make_backup_temp_directory('', false) . '/' . $tempdir . '/course/enrolments.xml';
            $xml = is_readable($file) ? (string)file_get_contents($file) : '';
            preg_match_all('~<userid>(\d+)</userid>~', $xml, $m);
            $manifest['enrolments'] = count(array_unique($m[1]));
        }
        return $manifest;
    }

    /**
     * Ask the origin what its course has now.
     *
     * @param stdClass $site Origin site.
     * @param int $courseid Origin course id.
     * @param stdClass $user Requester.
     * @return array|string The manifest, or why it is not available.
     */
    public static function from_origin(stdClass $site, int $courseid, stdClass $user) {
        try {
            $res = (new request($site))->origin_get_course_detail($courseid, $user);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        if (!$res->success || !isset($res->data->sections)) {
            $error = $res->errors[0] ?? null;
            return $error ? ($error->code ?? '') . ': ' . ($error->msg ?? '') : 'no course detail';
        }
        $activities = [];
        foreach ($res->data->sections as $section) {
            foreach ($section->activities ?? [] as $activity) {
                $activities[$activity->modname] = ($activities[$activity->modname] ?? 0) + 1;
            }
        }
        ksort($activities);
        return ['sections' => count($res->data->sections), 'activities' => $activities];
    }

    /**
     * What a course of this site has.
     *
     * @param int $courseid
     * @return array
     */
    public static function of_course(int $courseid): array {
        global $DB;
        $activities = $DB->get_records_sql_menu(
            'SELECT m.name, COUNT(cm.id)
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = :course AND cm.deletioninprogress = 0
           GROUP BY m.name',
            ['course' => $courseid]
        );
        $activities = array_map('intval', $activities);
        ksort($activities);
        $enrolments = $DB->count_records_sql(
            'SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :course',
            ['course' => $courseid]
        );
        return [
            'sections' => $DB->count_records('course_sections', ['course' => $courseid]),
            'activities' => $activities,
            'enrolments' => (int)$enrolments,
        ];
    }

    /**
     * What the restored course lacks compared with the expected manifest.
     *
     * @param array $expected Manifest of the origin (sections, activities) plus enrolments of the backup.
     * @param array $actual Manifest of the restored course.
     * @return string[] Empty when nothing is missing.
     */
    public static function compare(array $expected, array $actual): array {
        $issues = [];
        if ((int)($actual['sections'] ?? 0) < (int)($expected['sections'] ?? 0)) {
            $issues[] = 'sections ' . $actual['sections'] . '/' . $expected['sections'];
        }
        $installed = \core_component::get_plugin_list('mod');
        foreach ($expected['activities'] ?? [] as $modname => $count) {
            $have = (int)($actual['activities'][$modname] ?? 0);
            if ($have < $count) {
                // Not tolerated: removing the origin would lose these activities for good.
                $why = isset($installed[$modname]) ? '' : ' (module not installed on this site)';
                $issues[] = 'mod_' . $modname . ' ' . $have . '/' . $count . $why;
            }
        }
        if (isset($expected['enrolments']) && (int)$actual['enrolments'] < (int)$expected['enrolments']) {
            $issues[] = 'enrolled users ' . $actual['enrolments'] . '/' . $expected['enrolments'];
        }
        return $issues;
    }

    /**
     * Store the manifest read from the backup on the request.
     *
     * @param int $requestid
     * @param array $backup
     */
    public static function save_backup(int $requestid, array $backup): void {
        // Merged: the request may already hold other keys (keepsafetycopy).
        $request = coursetransfer_request::get($requestid);
        $stored = $request ? (json_decode((string)($request->origin_manifest ?? ''), true) ?: []) : [];
        $stored['backup'] = $backup;
        coursetransfer_request::update_fields($requestid, ['origin_manifest' => json_encode($stored)]);
    }

    /**
     * Check a completed course restore against its origin before removing it, and record the result.
     *
     * @param stdClass $request Course request (target side).
     * @param stdClass $site Origin site.
     * @param stdClass $user Requester.
     * @return string[] Issues; empty when the origin can be removed.
     */
    public static function verify(stdClass $request, stdClass $site, stdClass $user): array {
        $stored = json_decode((string)($request->origin_manifest ?? ''), true) ?: [];
        $origin = self::from_origin($site, (int)$request->origin_course_id, $user);
        $actual = self::of_course((int)$request->target_course_id);
        if (is_string($origin)) {
            $issues = ['origin manifest not available: ' . $origin];
        } else {
            $expected = $origin;
            $expected['enrolments'] = $stored['backup']['enrolments'] ?? null;
            $issues = self::compare($expected, $actual);
            $stored['origin'] = $origin;
        }
        $stored['restored'] = $actual;
        $stored['issues'] = $issues;
        $stored['verified'] = time();
        coursetransfer_request::update_fields((int)$request->id, ['origin_manifest' => json_encode($stored)]);
        return $issues;
    }
}
