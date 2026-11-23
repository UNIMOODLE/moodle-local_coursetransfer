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
        $dir = make_backup_temp_directory('', false) . '/' . $tempdir;
        $users = !empty($info->root_settings['users']);
        // What a transfer must carry (LCT-063): a backup made by a peer that still follows its general backup
        // defaults may lack part of the course. Logs and grade history are left out: they depend on a setting.
        $missing = [];
        $required = coursetransfer_backup::root_settings($users ? 1 : 0);
        // Badges and competencies are left out too: a site may have them disabled.
        foreach (['logs', 'grade_histories', 'permissions', 'anonymize', 'badges', 'competencies'] as $name) {
            unset($required[$name]);
        }
        foreach ($required as $name => $value) {
            // A setting the backup does not carry does not exist in the Moodle of the origin (e.g. questionbank
            // since 5.0, where question banks are activities): only an existing setting left off is missing.
            if ($value && array_key_exists($name, $info->root_settings ?? []) && empty($info->root_settings[$name])) {
                $missing[] = $name;
            }
        }
        $manifest['settings_missing'] = $missing;
        // What the backup carries of the users, to compare with what was asked for (B2): the origin may leave them
        // out (no moodle/backup:userinfo, locked by its administrator) or anonymise them.
        $manifest['users'] = $users;
        $manifest['anonymized'] = !empty($info->root_settings['anonymize']);
        if ($users) {
            $xml = self::read($dir . '/course/enrolments.xml');
            preg_match_all('~<userid>(\d+)</userid>~', $xml, $m);
            $manifest['enrolments'] = count(array_unique($m[1]));
            // Every included activity carries its user data (LCT-033), whatever the module.
            $without = [];
            foreach ($info->activities ?? [] as $key => $activity) {
                $settings = $activity->settings ?? [];
                if (!empty($settings[$key . '_included']) && empty($settings[$key . '_userinfo'])) {
                    $without[] = $key;
                }
            }
            $manifest['userinfo_missing'] = $without;
            $manifest['userdata'] = self::userdata_of_backup($dir, $info);
        }
        return $manifest;
    }

    /**
     * Read a file of the extracted backup ('' when it is not there).
     *
     * @param string $file
     * @return string
     */
    private static function read(string $file): string {
        return is_readable($file) ? (string)file_get_contents($file) : '';
    }

    /**
     * User data carried by the backup, counted the same way for every module (LCT-063).
     *
     * Grades (course and activities), activity completion states and files of the course and its activities
     * and blocks (submissions, attachments, feedback…), per component. Question files are left out: they may
     * belong to banks outside the course.
     *
     * @param string $dir Extracted backup folder.
     * @param \stdClass $info Backup information.
     * @return array
     */
    private static function userdata_of_backup(string $dir, \stdClass $info): array {
        $grades = substr_count(self::read($dir . '/gradebook.xml'), '<grade_grade ');
        $completion = 0;
        foreach ($info->activities ?? [] as $activity) {
            $path = $dir . '/' . $activity->directory;
            $grades += substr_count(self::read($path . '/grades.xml'), '<grade_grade ');
            $completion += substr_count(self::read($path . '/completion.xml'), '<completion ');
        }
        $files = [];
        $reader = new \XMLReader();
        if (is_readable($dir . '/files.xml') && $reader->open($dir . '/files.xml')) {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'file') {
                    continue;
                }
                $file = simplexml_load_string($reader->readOuterXml());
                $component = (string)($file->component ?? '');
                if ((string)($file->filename ?? '.') === '.' || !self::counted_component($component)) {
                    continue;
                }
                $files[$component] = ($files[$component] ?? 0) + 1;
            }
            $reader->close();
        }
        ksort($files);
        return ['grades' => $grades, 'completion' => $completion, 'files' => $files];
    }

    /**
     * Whether files of this component are compared (those restored inside the course).
     *
     * @param string $component
     * @return bool
     */
    private static function counted_component(string $component): bool {
        if ($component === '' || in_array($component, ['user', 'backup', 'question'], true)) {
            return false;
        }
        return !preg_match('~^(qtype|qbank|qformat)_~', $component);
    }

    /**
     * What of the user data of the backup this restore can bring, so a hold is never a false alarm (A4).
     *
     * Into an existing course the course settings are not overwritten, so its summary and overview files are
     * not restored; badge files depend on badges being enabled; completion states on completion being enabled.
     *
     * @param array $userdata From userdata_of_backup().
     * @param int $target backup::TARGET_* asked for.
     * @return array
     */
    public static function restorable_userdata(array $userdata, int $target): array {
        global $CFG;
        require_once($CFG->dirroot . '/backup/backup.class.php');
        unset($userdata['files']['badges']);
        if ($target !== \backup::TARGET_NEW_COURSE) {
            unset($userdata['files']['course']);
        }
        if (empty($CFG->enablecompletion)) {
            $userdata['completion'] = 0;
        }
        return $userdata;
    }

    /**
     * User data of a course of this site, counted as userdata_of_backup() does.
     *
     * @param int $courseid
     * @return array
     */
    public static function userdata_of_course(int $courseid): array {
        global $DB;
        $grades = $DB->count_records_sql(
            'SELECT COUNT(gg.id)
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gi.courseid = :course',
            ['course' => $courseid]
        );
        $completion = $DB->count_records_sql(
            'SELECT COUNT(cmc.id)
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cm.course = :course',
            ['course' => $courseid]
        );
        $context = \context_course::instance($courseid);
        $rows = $DB->get_records_sql_menu(
            "SELECT f.component, COUNT(f.id)
               FROM {files} f
               JOIN {context} ctx ON ctx.id = f.contextid
              WHERE (ctx.id = :ctxid OR " . $DB->sql_like('ctx.path', ':path') . ")
                AND f.filename <> '.'
           GROUP BY f.component",
            ['ctxid' => $context->id, 'path' => $context->path . '/%']
        );
        $files = [];
        foreach ($rows as $component => $count) {
            if (self::counted_component((string)$component)) {
                $files[$component] = (int)$count;
            }
        }
        ksort($files);
        return ['grades' => (int)$grades, 'completion' => (int)$completion, 'files' => $files];
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
        if (!empty($expected['backup_unread'])) {
            $issues[] = 'backup manifest not read';
        }
        if (!empty($expected['users_missing'])) {
            $issues[] = 'user data asked for, but the backup has none';
        }
        if (!empty($expected['users_anonymized'])) {
            $issues[] = 'user data asked for, but the backup is anonymised';
        }
        // The backup itself lacked part of the course or of the user data (LCT-033, LCT-063).
        if (!empty($expected['settings_missing'])) {
            $issues[] = 'backup without: ' . implode(', ', $expected['settings_missing']);
        }
        if (!empty($expected['userinfo_missing'])) {
            $issues[] = 'activities without user data in the backup: ' . implode(', ', $expected['userinfo_missing']);
        }
        if (isset($expected['userdata'])) {
            $have = $actual['userdata'] ?? ['grades' => 0, 'completion' => 0, 'files' => []];
            foreach (['grades', 'completion'] as $key) {
                if ((int)$have[$key] < (int)$expected['userdata'][$key]) {
                    $issues[] = $key . ' ' . $have[$key] . '/' . $expected['userdata'][$key];
                }
            }
            foreach ($expected['userdata']['files'] as $component => $count) {
                $got = (int)($have['files'][$component] ?? 0);
                if ($got < $count) {
                    $issues[] = 'files ' . $component . ' ' . $got . '/' . $count;
                }
            }
        }
        return $issues;
    }

    /**
     * Whether the issues only say the check could not be made (origin unreachable, requester refused, error), not
     * that the restore does not match: such a hold must not be stored as a final result (A1).
     *
     * @param string[] $issues
     * @return bool
     */
    public static function is_inconclusive(array $issues): bool {
        if (!$issues) {
            return false;
        }
        foreach ($issues as $issue) {
            if (!preg_match('~^(origin manifest not available|manifest not checked|requester not found)~', (string)$issue)) {
                return false;
            }
        }
        return true;
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
            // Not a result: the origin did not answer or refused the requester. Nothing is stored, so a later check
            // can still pass once the origin or the account is fixed (A1).
            return ['origin manifest not available: ' . $origin];
        } else {
            $expected = $origin;
            if (!isset($stored['backup'])) {
                // Without what the backup carried nothing about user data can be checked: keep the origin (A3).
                $expected['backup_unread'] = true;
            }
            foreach (['enrolments', 'settings_missing', 'userinfo_missing', 'userdata'] as $key) {
                $expected[$key] = $stored['backup'][$key] ?? null;
            }
            if (!empty($request->origin_enrolusers) && isset($stored['backup'])) {
                // User data was asked for: a backup without it (or anonymised) would lose the work of the students (B2).
                $backup = $stored['backup'];
                $nousers = array_key_exists('users', $backup) ? empty($backup['users']) : !isset($backup['enrolments']);
                $expected['users_missing'] = $nousers;
                $expected['users_anonymized'] = !$nousers && !empty($backup['anonymized']);
            }
            if (isset($expected['userdata'])) {
                $expected['userdata'] = self::restorable_userdata($expected['userdata'], (int)($request->target_target ?? 0));
                $actual['userdata'] = self::userdata_of_course((int)$request->target_course_id);
            }
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
