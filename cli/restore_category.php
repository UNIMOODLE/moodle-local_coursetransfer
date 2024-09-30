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
use local_coursetransfer\coursetransfer_category;
use local_coursetransfer\factory\user;
use local_coursetransfer\models\configuration_category;

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

$usage = 'CLI for restore origin category.

Usage:
    # php restore_category.php
        --site_url=<site_url>
        --origin_category_id=<courseid>
        --target_category_id=<categoryid>
        --origin_enrolusers=<enrolusers>
        --target_remove_enrols=<target_remove_enrols>
        --target_remove_groups=<target_remove_groups>
        --origin_remove_category=<origin_remove_category>
        --origin_schedule_datetime=<origin_schedule_datetime>

    --site_url=<site_url> Origin Site URL (string)
    --origin_category_id=<courseid> Origin Category ID (int).
    --target_category_id=<courseid> Target Category ID (int). (Optional - New Category)
    --origin_enrolusers=<enrolusers> Origin Enrol users (Boolean).
    --target_remove_enrols=<target_remove_enrols> Target Remove Enrols (Boolean).
    --target_remove_groups=<target_remove_groups> Target Remove Groups (Boolean).
    --origin_remove_category=<origin_remove_category> Origin Remove Category (Boolean).
    --origin_schedule_datetime=<origin_schedule_datetime>
            Date in UNIX timestamp (int). Max deferral 30 days, 0 (default) to execute ASAP.

Options:
    -h --help                   Print this help.

Description.

Examples:

    # php local/coursetransfer/restore_category.php
        --site_url=https://origen.dominio
        --origin_category_id=12
        --target_category_id=12
        --origin_enrolusers=true
        --origin_remove_category=false
        --origin_schedule_datetime=1679404952
';

list($options, $unrecognised) = cli_get_params([
        'help' => false,
        'site_url' => null,
        'origin_category_id' => null,
        'target_category_id' => null,
        'origin_enrolusers' => false,
        'origin_remove_category' => false,
        'origin_schedule_datetime' => 0,
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
$origincategoryid = !is_null($options['origin_category_id']) ? (int) $options['origin_category_id'] : null;
$targetcategoryid = !is_null($options['target_category_id']) ? (int) $options['target_category_id'] : null;
$originenrolusers = $options['origin_enrolusers'] === 'true' ? 1 : 0;
$originremovecategory = $options['origin_remove_category'] === 'true' ? 1 : 0;
$originscheduledatetime = intval($options['origin_schedule_datetime']);

if (empty($siteurl)) {
    cli_writeln( get_string('site_url_required', 'local_coursetransfer') );
    exit(128);
}

if ( $origincategoryid === null ) {
    cli_writeln( get_string('origin_category_id_require', 'local_coursetransfer') );
    exit(128);
} else if ( $origincategoryid <= 0 ) {
    cli_writeln( get_string('origin_category_id_integer', 'local_coursetransfer') );
    exit(128);
}

if ($targetcategoryid !== null) {
    try {
        $category = core_course_category::get($targetcategoryid);
        $targetcategoryid = $category->id;
    } catch (moodle_exception $e) {
        cli_writeln('40011: ' . $e->getMessage());
        exit(1);
    }
} else {
    $targetcategoryid = 0;
}


if ( !in_array((int)$originenrolusers, [0, 1])) {
    cli_writeln( get_string('origin_enrolusers_boolean', 'local_coursetransfer') );
    exit(128);
}
$now = time();
// 30 days of maximun deferred execution.
$maxtimerange = $now + (60 * 60 * 24 * 30);
if ($originscheduledatetime != 0  && ($originscheduledatetime < $now || $originscheduledatetime > $maxtimerange )) {
    cli_writeln( 'origin_schedule_datetime is not valid format');
    exit(128);
} else {
    $date = new DateTime();
    $date->setTimestamp(intval($originscheduledatetime));
    cli_writeln( 'Scheduler Time: ' . userdate($date->getTimestamp()));
}

$errors = [];

try {

    // 1. Setup Configuration.
    $configuration = new configuration_category(
            backup::TARGET_NEW_COURSE, false, false, $originenrolusers,
            $originremovecategory, $originscheduledatetime);

    // 2. User Login.
    $user = core_user::get_user_by_username(user::USERNAME_WS);

    // 3. Restore Category.
    // 3. Restore Category.
    if ($targetcategoryid === 0) {
        $targetid = $targetcategoryid;
    } else {
        $target = core_course_category::get($targetcategoryid);
        $targetid = $target->id;
    }
    $site = coursetransfer::get_site_by_url($siteurl);

    $res = coursetransfer_category::restore_tree($user, $site, $targetid, $origincategoryid, $configuration);

    // 4. Success or Errors.
    $errors = array_merge($errors, $res['errors']);
    $success = $res['success'];
    if ($success) {
        cli_writeln('THE RESTORATION HAS STARTED - VIEW LOG IN: view_log_request.php --requestid=' .
                $res['data']['requestid']);
        exit(0);
    } else {
        cli_writeln(json_encode($errors));
        exit(1);
    }

} catch (moodle_exception $e) {
    cli_writeln('40001: ' . $e->getMessage());
    exit(1);
}


