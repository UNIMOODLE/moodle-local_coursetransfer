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
use local_coursetransfer\models\configuration_category;

define('CLI_SCRIPT', 1);

require(__DIR__ . '/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

$usage = 'Restore a whole category (and its courses) from a remote (origin) platform.

The origin platform must be registered as a paired "origin" site (with its
token) beforehand. The restore runs asynchronously (cron); follow it with
view_log_request.php --requestid=N.

Usage:
    php restore_category.php --site_url=<url> --origin_category_id=<id> [options]

Required:
    --site_url=<url>              Origin site URL, e.g. https://origin.example
    --origin_category_id=<id>     Category ID ON THE ORIGIN site (int > 0).

Options (booleans accept true/false; default false):
    --target_category_id=<id>     Parent category on THIS site to restore into.
                                  Optional; defaults to the top level (a new
                                  category is created).
    --origin_enrolusers=<bool>    Include enrolled users from the origin.
    --origin_remove_category=<bool>
                                  Delete the origin category after a successful
                                  restore.
    --origin_schedule_datetime=<ts>
                                  UNIX timestamp to defer execution (max 30 days
                                  ahead). 0 (default) = run ASAP.
    -h, --help                    Print this help.

Exit codes: 0 = started OK · 1 = runtime error · 2 = invalid arguments.
Errors are written to STDERR; the success line goes to STDOUT.

Example:
    php local/coursetransfer/cli/restore_category.php \\
        --site_url=https://origin.example --origin_category_id=12 \\
        --target_category_id=5 --origin_enrolusers=true
';

[$options, $unrecognised] = cli_get_params([
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
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(0);
}

$siteurl = $options['site_url'];
$origincategoryid = !is_null($options['origin_category_id']) ? (int) $options['origin_category_id'] : null;
$targetcategoryid = !is_null($options['target_category_id']) ? (int) $options['target_category_id'] : null;
$originenrolusers = cli_helper::to_bool($options['origin_enrolusers']);
$originremovecategory = cli_helper::to_bool($options['origin_remove_category']);
$originscheduledatetime = intval($options['origin_schedule_datetime']);

if (empty($siteurl)) {
    cli_error(get_string('site_url_required', 'local_coursetransfer'), 2);
}

if ($origincategoryid === null) {
    cli_error(get_string('origin_category_id_require', 'local_coursetransfer'), 2);
} else if ($origincategoryid <= 0) {
    cli_error(get_string('origin_category_id_integer', 'local_coursetransfer'), 2);
}

if ($targetcategoryid !== null) {
    try {
        $category = core_course_category::get($targetcategoryid);
        $targetcategoryid = $category->id;
    } catch (moodle_exception $e) {
        cli_error('40012: ' . $e->getMessage(), 1);
    }
} else {
    $targetcategoryid = 0;
}


if (!in_array((int)$originenrolusers, [0, 1])) {
    cli_error(get_string('origin_enrolusers_boolean', 'local_coursetransfer'), 2);
}
cli_helper::check_schedule($originscheduledatetime);

$errors = [];

try {
    // 1. Setup Configuration.
    $configuration = new configuration_category(
        backup::TARGET_NEW_COURSE,
        false,
        false,
        $originenrolusers,
        $originremovecategory,
        $originscheduledatetime
    );

    // 2. Service user (aborts cleanly if the WS user is missing).
    $user = cli_helper::require_ws_user();

    // 3. Restore Category.
    $target = core_course_category::get($targetcategoryid);
    $site = coursetransfer::get_site_by_url($siteurl);

    $res = coursetransfer::restore_category($user, $site, $target->id, $origincategoryid, $configuration);

    // 4. Success or Errors.
    $errors = array_merge($errors, $res['errors']);
    $success = $res['success'];
    if ($success) {
        cli_writeln('THE RESTORATION HAS STARTED - VIEW LOG IN: view_log_request.php --requestid=' .
                $res['data']['requestid']);
        exit(0);
    } else {
        cli_error(json_encode($errors), 1);
    }
} catch (moodle_exception $e) {
    cli_error('40011: ' . $e->getMessage(), 1);
}
