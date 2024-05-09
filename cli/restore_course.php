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

use local_coursetransfer\coursetransfer;
use local_coursetransfer\factory\user;
use local_coursetransfer\models\configuration_course;

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

$usage = 'CLI for restore origin course.

Usage:
    # php restore_course.php
        --site_url=<site_url>
        --target_target=<target_target>
        --origin_course_id=<origin_course_id>
        --target_course_id=<target_course_id>
        --target_category_id=<target_category_id>
        --origin_enrolusers=<origin_enrolusers>
        --target_remove_enrols=<target_remove_enrols>
        --target_remove_groups=<target_remove_groups>
        --origin_remove_course=<origin_remove_course>
        --origin_schedule_datetime=<origin_schedule_datetime>

    --site_url=<site_url> Origin Site URL (string)
    --target_target=<target_target>   2: In New Course,
                                      3: Remove Content (Section & Activities),
                                      4: Merge the backup course into this course
                                      (Int Enum)
    --origin_course_id=<origin_course_id>  Origin Course ID (int).
    --target_course_id=<target_course_id>  Target Course ID (int). (Optional - New Course)
    --target_category_id=<target_category_id>  Category ID (int). (Optional - Superior Category)
    --origin_enrolusers=<origin_enrolusers>  Include enrolled users data. Default: false (Boolean).
    --target_remove_enrols=<target_remove_enrols> Remove Enrols (only in target: 4 - Remove Content) (Boolean).
    --target_remove_groups=<target_remove_groups> Remove Groups (only in target: 4 - Remove Content) (Boolean).
    --origin_remove_course=<origin_remove_course>   Remove Origin Course (Boolean).
    --origin_schedule_datetime=<origin_schedule_datetime>
            Date in UNIX timestamp (int). Max deferral 30 days, 0 (default) to execute ASAP.

Options:
    -h --help                   Print this help.

Description.

Examples:

    # php local/coursetransfer/cli/restore_course.php
        --site_url=https://origen.dominio
        --target_target=2
        --origin_course_id=12
        --target_course_id=12
        --target_category_id=101
        --origin_enrolusers=true
        --target_remove_enrols=false
        --target_remove_groups=false
        --origin_remove_course=false
        --origin_schedule_datetime=1679404952
        --target_not_remove_activities=[3,234,234]
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
    exit(2);
}

$siteurl = $options['site_url'];
$origincourseid = !is_null($options['origin_course_id']) ? (int) $options['origin_course_id'] : null;
$targetcourseid = !is_null($options['target_course_id']) ? (int) $options['target_course_id'] : null;
$targetcategoryid = !is_null($options['target_category_id']) ? (int) $options['target_category_id'] : null;
$originenrolusers = ($options['origin_enrolusers'] === 'true' || (int)$options['origin_enrolusers'] === 1) ? 1 : 0;
$targettarget = !is_null($options['target_target']) ? (int) $options['target_target'] : null;
$targetremoveenrols = ($options['target_remove_enrols'] === 'true' || (int)$options['target_remove_enrols'] === 1) ? 1 : 0;
$targetremovegroups = ($options['target_remove_groups'] === 'true' || (int)$options['target_remove_groups'] === 1) ? 1 : 0;
$originremovecourse = ($options['origin_remove_course'] === 'true' ||(int) $options['origin_remove_course'] === 1) ? 1 : 0;
$targetnotremoveactivities = '';
$originscheduledatetime = intval($options['origin_schedule_datetime']);

if (empty($siteurl)) {
    cli_writeln( get_string('site_url_required', 'local_coursetransfer') );
    exit(128);
}

if ( $origincourseid === null ) {
    cli_writeln( get_string('origin_course_id_require', 'local_coursetransfer') );
    exit(128);
} else if ( $origincourseid <= 0 ) {
    cli_writeln( get_string('origin_course_id_integer', 'local_coursetransfer') );
    exit(128);
}

if ( !in_array($targettarget, [backup::TARGET_NEW_COURSE, backup::TARGET_EXISTING_DELETING, backup::TARGET_EXISTING_ADDING]) ) {
    cli_writeln( get_string('target_target_is_incorrect', 'local_coursetransfer') );
    exit(128);
}

if ( empty($targetcourseid) && ($targettarget === backup::TARGET_NEW_COURSE)) {
    if ($targetcategoryid !== null) {
        try {
            $category = core_course_category::get($targetcategoryid);
        } catch (moodle_exception $e) {
            cli_writeln('40001: ' . $e->getMessage());
            exit(1);
        }
    } else {
        $category = core_course_category::get_default();
    }
    // Create new course.
    $targetcourseid = \local_coursetransfer\factory\course::create(
            $category, 'Remote Restoring in process...', 'IN-PROGRESS-' . time());
} else if ( empty($targetcourseid) && $targettarget !== backup::TARGET_NEW_COURSE ) {
    cli_writeln( get_string('target_course_id_is_required', 'local_coursetransfer') );
    exit(128);
} else if ( !empty($targetcourseid) && $targettarget === backup::TARGET_NEW_COURSE ) {
    cli_writeln( get_string('target_course_id_isnot_correct', 'local_coursetransfer') );
    exit(128);
}

if ( !in_array((int)$originenrolusers, [0, 1])) {
    cli_writeln( get_string('origin_enrolusers_boolean', 'local_coursetransfer') );
    exit(128);
}

if ( !in_array((int)$targetremoveenrols, [0, 1])) {
    cli_writeln( get_string('target_remove_enrols_boolean', 'local_coursetransfer') );
    exit(128);
}

if ( !in_array((int)$targetremovegroups, [0, 1])) {
    cli_writeln( get_string('target_remove_groups_booelan', 'local_coursetransfer') );
    exit(128);
}

if ( !in_array((int)$originremovecourse, [0, 1])) {
    cli_writeln( get_string('origin_remove_course_boolean', 'local_coursetransfer') );
    exit(128);
}
$now = time();
// 30 days of maximun deferred execution.
$maxtimerange = $now + (60 * 60 * 24 * 30);
if ($originscheduledatetime != 0  && ($originscheduledatetime < $now || $originscheduledatetime > $maxtimerange )) {
    cli_writeln( 'origin_schedule_datetime is not valid');
    exit(128);
} else {
    $date = new DateTime();
    $date->setTimestamp($originscheduledatetime);
    cli_writeln( 'Scheduler Time: ' . userdate($date->getTimestamp()));
}

if ($targettarget === backup::TARGET_EXISTING_ADDING && $targetremovegroups === 1) {
    cli_writeln( get_string('in_target_adding_not_remove_groups', 'local_coursetransfer'));
    exit(128);
}

if ($targettarget === backup::TARGET_EXISTING_ADDING && $targetremovegroups === 1) {
    cli_writeln( get_string('in_target_adding_not_remove_groups', 'local_coursetransfer'));
    exit(128);
}

if ($targettarget === backup::TARGET_EXISTING_ADDING && $targetremoveenrols === 1) {
    cli_writeln( get_string('in_target_adding_not_remove_enrols', 'local_coursetransfer'));
    exit(128);
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

    // 2. User Login.
    $user = core_user::get_user_by_username(user::USERNAME_WS);

    // 3. Restore Course.
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
        if ($targettarget === backup::TARGET_NEW_COURSE) {
            // 5b. Remove new course.
            delete_course($targetcourseid, false);
        }
        cli_writeln(json_encode($errors));
        exit(1);
    }

} catch (moodle_exception $e) {
    // 5b. Remove new course.
    delete_course($targetcourseid, false);
    cli_writeln('40000: ' . $e->getMessage());
    exit(1);
}
