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
use local_coursetransfer\models\configuration_course;

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

$usage = 'CLI for delete category.

Usage:
    # php remove_category.php
        --site_url=<site_url>
        --origin_category_id=<origin_category_id>
        --origin_schedule_datetime=<origin_schedule_datetime>

    --site_url=<site_url> Origin Site URL (string)
    --origin_category_id=<origin_category_id>  Origin Category ID (int).
    --origin_schedule_datetime=<origin_schedule_datetime>  Date in UNIX timestamp (int).

Options:
    -h --help                   Print this help.

Description.

Examples:

    # php local/coursetransfer/cli/remove_category.php
        --site_url=https://origen.dominio
        --origin_category_id=12
        --origin_schedule_datetime=1679404952
';

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'site_url' => null,
    'origin_category_id' => null,
    'origin_schedule_datetime' => 0
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
$origincategoryid = !is_null($options['origin_category_id']) ? (int) $options['origin_category_id'] : null;
$originscheduledatetime = (int) $options['origin_schedule_datetime'];

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

$errors = [];

try {

    // 2. User Login.
    $user = core_user::get_user_by_username(user::USERNAME_WS);

    $site = coursetransfer::get_site_by_url($siteurl);
    $res = coursetransfer::remove_category($site, $origincategoryid, $user);

    // 4. Success or Errors.
    $errors = array_merge($errors, $res['errors']);
    $success = $res['success'];
    if ($success) {
        // 5a. Rename new course.
        cli_writeln('THE CATEGORY REMOVE HAS STARTED - VIEW LOG IN: view_log_request.php --requestid=' .
                $res['data']['requestid']);
        exit(0);
    } else {
        cli_writeln(json_encode($errors));
        exit(1);
    }

} catch (moodle_exception $e) {
    cli_writeln('300500: ' . $e->getMessage());
    exit(1);
}
