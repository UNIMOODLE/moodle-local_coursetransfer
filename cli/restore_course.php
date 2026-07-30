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
 * Cli Script
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\cli_helper;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\models\configuration_course;

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

$usage = 'Restore a course from a remote (origin) platform into this site.

The origin platform must be registered as a paired "origin" site (with its
token) before running this. The restore runs asynchronously (cron); use
view_log_request.php --requestid=N to follow it.

Usage:
    php restore_course.php --site_url=<url> --target_target=<2|3|4>
        --origin_course_id=<id> [options]

Required:
    --site_url=<url>              Origin site URL, e.g. https://origin.example
    --origin_course_id=<id>       Course ID ON THE ORIGIN site (int > 0).
    --target_target=<2|3|4>       Where to restore (int enum):
                                    2 = new course (creates it; do NOT pass
                                        --target_course_id).
                                    3 = existing course, deleting its current
                                        content first (--target_course_id required).
                                    4 = existing course, merging/adding into it,
                                        keeping its content (--target_course_id
                                        required).

Target selection:
    --target_course_id=<id>       Destination course ID on THIS site. Required for
                                  target_target=3|4; must be omitted for 2.
    --target_category_id=<id>     Category to create the new course in (only for
                                  target_target=2). Optional; defaults to the
                                  default category.

Options (booleans accept true/false; default false):
    --origin_enrolusers=<bool>          Include enrolled users from the origin.
    --target_remove_enrols=<bool>       Remove existing enrolments in the target
                                        (only meaningful with target_target=3).
    --target_remove_groups=<bool>       Remove existing groups in the target
                                        (only meaningful with target_target=3).
    --origin_remove_course=<bool>       Delete the origin course after a successful
                                        restore.
    --target_not_remove_activities=<list>
                                        Comma/JSON list of origin activity IDs to
                                        keep when deleting content (target_target=3),
                                        e.g. [3,234,235]. Default: keep nothing extra.
    --origin_schedule_datetime=<ts>     UNIX timestamp to defer execution (max 30
                                        days ahead). 0 (default) = run ASAP.
    -h, --help                          Print this help.

Exit codes: 0 = started OK · 1 = runtime error · 2 = invalid arguments.
Errors are written to STDERR; the success line goes to STDOUT.

Examples:
    # New course, with users:
    php local/coursetransfer/cli/restore_course.php \\
        --site_url=https://origin.example --target_target=2 \\
        --origin_course_id=12 --target_category_id=101 --origin_enrolusers=true

    # Merge into an existing course:
    php local/coursetransfer/cli/restore_course.php \\
        --site_url=https://origin.example --target_target=4 \\
        --origin_course_id=12 --target_course_id=34
';

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'site_url' => null,
    'origin_course_id' => null,
    'target_course_id' => null,
    'target_category_id' => null,
    'origin_enrolusers' => false,
    'target_target' => null,
    'target_remove_enrols' => false,
    'target_remove_groups' => false,
    'origin_remove_course' => false,
    'origin_schedule_datetime' => 0,
    'target_not_remove_activities' => "",
], [
    'h' => 'help',
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL.'  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(0);
}

$siteurl = $options['site_url'];
$origincourseid = !is_null($options['origin_course_id']) ? (int) $options['origin_course_id'] : null;
$targetcourseid = !is_null($options['target_course_id']) ? (int) $options['target_course_id'] : null;
$targetcategoryid = !is_null($options['target_category_id']) ? (int) $options['target_category_id'] : null;
$originenrolusers = cli_helper::to_bool($options['origin_enrolusers']);
$targettarget = !is_null($options['target_target']) ? (int) $options['target_target'] : null;
$targetremoveenrols = cli_helper::to_bool($options['target_remove_enrols']);
$targetremovegroups = cli_helper::to_bool($options['target_remove_groups']);
$originremovecourse = cli_helper::to_bool($options['origin_remove_course']);
// Pass the documented option through (previously accepted but ignored).
$targetnotremoveactivities = (string) $options['target_not_remove_activities'];
$originscheduledatetime = intval($options['origin_schedule_datetime']);

if (empty($siteurl)) {
    cli_error(get_string('site_url_required', 'local_coursetransfer'), 2);
}

if ( $origincourseid === null ) {
    cli_error(get_string('origin_course_id_require', 'local_coursetransfer'), 2);
} else if ( $origincourseid <= 0 ) {
    cli_error(get_string('origin_course_id_integer', 'local_coursetransfer'), 2);
}

if ( !in_array($targettarget, [backup::TARGET_NEW_COURSE, backup::TARGET_EXISTING_DELETING, backup::TARGET_EXISTING_ADDING]) ) {
    cli_error(get_string('target_target_is_incorrect', 'local_coursetransfer'), 2);
}

// Fail fast if the service user is missing (before creating anything).
$user = \local_coursetransfer\cli_helper::require_ws_user();

// Track whether THIS run created the target course, so the error handling only
// ever deletes a course we created (never a pre-existing target = data loss).
$creatednew = false;

if ( empty($targetcourseid) && ($targettarget === backup::TARGET_NEW_COURSE)) {
    if ($targetcategoryid !== null) {
        try {
            $category = core_course_category::get($targetcategoryid);
        } catch (moodle_exception $e) {
            cli_error('40001: ' . $e->getMessage(), 1);
        }
    } else {
        $category = core_course_category::get_default();
    }
    // Create new course.
    $targetcourseid = \local_coursetransfer\factory\course::create(
            $category, 'Remote Restoring in process...', 'IN-PROGRESS-' . time());
    $creatednew = true;
} else if ( empty($targetcourseid) && $targettarget !== backup::TARGET_NEW_COURSE ) {
    cli_error(get_string('target_course_id_is_required', 'local_coursetransfer'), 2);
} else if ( !empty($targetcourseid) && $targettarget === backup::TARGET_NEW_COURSE ) {
    cli_error(get_string('target_course_id_isnot_correct', 'local_coursetransfer'), 2);
}

if ( !in_array((int)$originenrolusers, [0, 1])) {
    cli_error(get_string('origin_enrolusers_boolean', 'local_coursetransfer'), 2);
}

if ( !in_array((int)$targetremoveenrols, [0, 1])) {
    cli_error(get_string('target_remove_enrols_boolean', 'local_coursetransfer'), 2);
}

if ( !in_array((int)$targetremovegroups, [0, 1])) {
    cli_error(get_string('target_remove_groups_booelan', 'local_coursetransfer'), 2);
}

if ( !in_array((int)$originremovecourse, [0, 1])) {
    cli_error(get_string('origin_remove_course_boolean', 'local_coursetransfer'), 2);
}
cli_helper::check_schedule($originscheduledatetime);

if ($targettarget === backup::TARGET_EXISTING_ADDING && $targetremovegroups === 1) {
    cli_error(get_string('in_target_adding_not_remove_groups', 'local_coursetransfer'), 2);
}

if ($targettarget === backup::TARGET_EXISTING_ADDING && $targetremoveenrols === 1) {
    cli_error(get_string('in_target_adding_not_remove_enrols', 'local_coursetransfer'), 2);
}

$errors = [];

try {

    // 1. Setup Configuration.
    $configuration = new configuration_course(
            $targettarget,
            $targetremoveenrols,
            $targetremovegroups,
            $originenrolusers,
            $originremovecourse,
            $originscheduledatetime,
            $targetnotremoveactivities
    );

    // 3. Restore Course (service user resolved above via cli_helper).
    $target = get_course($targetcourseid);
    $site = coursetransfer::get_site_by_url($siteurl);
    $res = coursetransfer::restore_course($user, $site, $target->id, $origincourseid, $configuration);

    // 4. Success or Errors.
    $errors = array_merge($errors, $res['errors']);
    $success = $res['success'];
    if ($success) {
        // 5a. Rename new course.
        cli_writeln('THE RESTORATION HAS STARTED - VIEW LOG IN: view_log_request.php --requestid=' .
                $res['data']['requestid']);
        exit(0);
    } else {
        if ($creatednew) {
            // 5b. Remove ONLY the course we created in this run.
            delete_course($targetcourseid, false);
        }
        cli_error(json_encode($errors), 1);
    }

} catch (moodle_exception $e) {
    if ($creatednew) {
        // 5b. Remove ONLY the course we created in this run (never a pre-existing target).
        delete_course($targetcourseid, false);
    }
    cli_error('40000: ' . $e->getMessage(), 1);
}
