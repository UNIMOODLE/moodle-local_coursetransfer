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
 * CLI script
 *
 *
 * @package     local_coursetransfer
 * @copyright   2023 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\coursetransfer;
use local_coursetransfer\factory\user;
use local_coursetransfer\models\configuration;

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');

$usage = 'CLI de restauracion de cursos.

Usage:
    # php restore_course.php
        --site_url=<site_url>
        --origin_course_id=<origin_course_id>
        --destiny_course_id=<destiny_course_id>
        --destiny_category_id=<destiny_category_id>
        --origin_enrolusers=<origin_enrolusers>
        --destiny_remove_activities=<destiny_remove_activities>
        --destiny_merge_activities=<destiny_merge_activities>
        --destiny_remove_enrols=<destiny_remove_enrols>
        --destiny_remove_groups=<destiny_remove_groups>
        --origin_remove_course=<origin_remove_course>
        --origin_schedule_datetime=<origin_schedule_datetime>
        --destiny_not_remove_activities=<destiny_not_remove_activities>

    --site_url=<site_url> Origin Site URL (string)
    --origin_course_id=<origin_course_id>  Origin Course ID (int).
    --destiny_course_id=<destiny_course_id>  Destiny Course ID (int). (Optional - New Course)
    --destiny_category_id=<destiny_category_id>  Category ID (int). (Optional - Superior Category)
    --origin_enrolusers=<origin_enrolusers>  Include enrolled users data (Boolean).
    --destiny_remove_activities=<destiny_remove_activities> Remove Content (Section & Activities) (Boolean).
    --destiny_merge_activities=<destiny_merge_activities>  Merge the backup course into this course (Boolean).
    --destiny_remove_enrols=<destiny_remove_enrols> Remove Enrols (Boolean).
    --destiny_remove_groups=<destiny_remove_groups> Remove Groups (Boolean).
    --origin_remove_course=<origin_remove_course>   Remove Origin Course (Boolean).
    --origin_schedule_datetime=<origin_schedule_datetime>   Date in UNIX timestamp (int).
    --destiny_not_remove_activities=<destiny_not_remove_activities> cmids separated by coma (string).

Options:
    -h --help                   Print this help.

Description.

Examples:

    # php local/coursetransfer/cli/restore_course.php
        --site_url=https://origen.dominio
        --origin_course_id=12
        --destiny_course_id=12
        --destiny_category_id=101
        --origin_enrolusers=true
        --destiny_remove_activities=false
        --destiny_merge_activities=true
        --destiny_remove_enrols=false
        --destiny_remove_groups=false
        --origin_remove_course=false
        --origin_schedule_datetime=1679404952
        --destiny_not_remove_activities=[]
';

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'site_url' => null,
    'origin_course_id' => null,
    'destiny_course_id' => null,
    'destiny_category_id' => null,
    'origin_enrolusers' => false,
    'destiny_remove_activities' => false,
    'destiny_merge_activities' => false,
    'destiny_remove_enrols' => false,
    'destiny_remove_groups' => false,
    'origin_remove_course' => false,
    'origin_schedule_datetime' => 0,
    'destiny_not_remove_activities' => ""
], [
    'h' => 'help'
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
$destinycourseid = !is_null($options['destiny_course_id']) ? (int) $options['destiny_course_id'] : null;
$destinycategoryid = !is_null($options['destiny_category_id']) ? (int) $options['destiny_category_id'] : null;
$originenrolusers = $options['origin_enrolusers'] === 'true' ? 1 : (int) $options['origin_enrolusers'];
$destinyremoveactivities = $options['destiny_remove_activities'] === 'true' ? 1 : (int) $options['destiny_remove_activities'];
$destinymergeactivities = $options['destiny_merge_activities'] === 'true' ? 1 : (int) $options['destiny_merge_activities'];
$destinyremoveenrols = $options['destiny_remove_enrols'] === 'true' ? 1 : (int) $options['destiny_remove_enrols'];
$destinyremovegroups = $options['destiny_remove_groups'] === 'true' ? 1 : (int) $options['destiny_remove_groups'];
$originremovecourse = $options['origin_remove_course'] === 'true' ? 1 : (int) $options['origin_remove_course'];
$destinynotremoveactivities = '';
$originscheduledatetime = explode(',', $options['origin_schedule_datetime']);

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

if ( $destinycourseid === null ) {
    if ($destinycategoryid !== null) {
        try {
            $category = core_course_category::get($destinycategoryid);
            // Create new course.
            $destinycourseid = \local_coursetransfer\factory\course::create(
                    $category, 'New Course', 'NEW-COURSE-' . time(), '');
        } catch (moodle_exception $e) {
            cli_writeln('300501: ' . $e->getMessage());
            exit(1);
        }

    } else if ( $destinycourseid <= 0 ) {
        cli_writeln( get_string('destiny_category_id_require', 'local_coursetransfer') );
        exit(128);
    }
} else if ( $destinycourseid <= 0 ) {
    cli_writeln( get_string('destiny_course_id_integer', 'local_coursetransfer') );
    exit(128);
}

if ( !in_array((int)$originenrolusers, [0, 1])) {
    cli_writeln( get_string('origin_enrolusers_boolean', 'local_coursetransfer') );
    exit(128);
}

if ( !in_array((int)$destinyremoveactivities, [0, 1])) {
    cli_writeln( get_string('destiny_remove_activities_boolean', 'local_coursetransfer') );
    exit(128);
}

if ( !in_array((int)$destinymergeactivities, [0, 1])) {
    cli_writeln( get_string('destiny_merge_activities_boolean', 'local_coursetransfer') );
    exit(128);
}

if ( !in_array((int)$destinyremoveenrols, [0, 1])) {
    cli_writeln( get_string('destiny_remove_enrols_boolean', 'local_coursetransfer') );
    exit(128);
}

if ( !in_array((int)$destinyremovegroups, [0, 1])) {
    cli_writeln( get_string('destiny_remove_groups_booelan', 'local_coursetransfer') );
    exit(128);
}

if ( !in_array((int)$originremovecourse, [0, 1])) {
    cli_writeln( get_string('origin_remove_course_boolean', 'local_coursetransfer') );
    exit(128);
}

if ( !is_array($destinynotremoveactivities)) {
    cli_writeln( get_string('destiny_not_remove_activities_invalid', 'local_coursetransfer') );
    exit(128);
}

$errors = [];

try {

    // 1. Setup Configuration.
    $configuration = new configuration($destinyremoveactivities, $destinymergeactivities, $destinyremoveenrols,
        $destinyremovegroups, $originremovecourse, $destinynotremoveactivities);

    // 2. User Login.
    $user = core_user::get_user_by_username(user::USERNAME_WS);
    complete_user_login($user);

    // 3. Restore Course.
    $destiny = get_course($destinycourseid);
    $site = coursetransfer::get_site_by_url($siteurl);
    $res = coursetransfer::restore_course($site, $destiny->id, $origincourseid, $configuration, $target);

    // 4. Success or Errors.
    $errors = array_merge($errors, $res['errors']);
    $success = $res['success'];
    if ($success) {
        cli_writeln('THE RESTORATION HAS STARTED - VIEW LOG IN: view_log_request.php --requestid=' .
                $res['data']['requestid']);
        exit(0);
    } else {
        if (isset($errors[0])) {
            foreach ($errors as $error) {
                cli_writeln($error->code . ': ' . $error->msg);
            }
        } else {
            cli_writeln($errors['code'] . ': ' . $errors['msg']);
        }
        exit(1);
    }

} catch (moodle_exception $e) {
    cli_writeln('300500: ' . $e->getMessage());
    exit(1);
}
