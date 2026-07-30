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

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

$usage = 'Delete a course on a remote (origin) platform.

DESTRUCTIVE: the course is removed on the origin site. The origin must be
registered as a paired "origin" site (with its token) beforehand. Runs
asynchronously (cron); follow it with view_log_request.php --requestid=N.

Usage:
    php remove_course.php --site_url=<url> --origin_course_id=<id> [options]

Required:
    --site_url=<url>              Origin site URL, e.g. https://origin.example
    --origin_course_id=<id>       Course ID ON THE ORIGIN site (int > 0).

Options:
    --origin_schedule_datetime=<ts>
                                  UNIX timestamp to defer execution (max 30 days
                                  ahead). 0 (default) = run ASAP.
    -h, --help                    Print this help.

Exit codes: 0 = started OK · 1 = runtime error · 2 = invalid arguments.
Errors are written to STDERR; the success line goes to STDOUT.

Example:
    php local/coursetransfer/cli/remove_course.php \\
        --site_url=https://origin.example --origin_course_id=12
';

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'site_url' => null,
    'origin_course_id' => null,
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
    exit(0);
}

$siteurl = $options['site_url'];
$origincourseid = !is_null($options['origin_course_id']) ? (int) $options['origin_course_id'] : null;
$originscheduledatetime = intval($options['origin_schedule_datetime']);

if (empty($siteurl)) {
    cli_error(get_string('site_url_required', 'local_coursetransfer'), 2);
}

if ( $origincourseid === null ) {
    cli_error(get_string('origin_course_id_require', 'local_coursetransfer'), 2);
} else if ( $origincourseid <= 0 ) {
    cli_error(get_string('origin_course_id_integer', 'local_coursetransfer'), 2);
}
cli_helper::check_schedule($originscheduledatetime);

$errors = [];

try {

    // 2. User Login.
    $user = cli_helper::require_ws_user();

    $site = coursetransfer::get_site_by_url($siteurl);
    $res = coursetransfer::remove_course($site, $origincourseid, $user, $originscheduledatetime);

    // 4. Success or Errors.
    $errors = array_merge($errors, $res['errors']);
    $success = $res['success'];
    if ($success) {
        // 5a. Rename new course.
        cli_writeln('THE COURSE REMOVE HAS STARTED - VIEW LOG IN: view_log_request.php --requestid=' .
                $res['data']['requestid']);
        exit(0);
    } else {
        cli_error(json_encode($errors), 1);
    }

} catch (moodle_exception $e) {
    cli_error('40002: ' . $e->getMessage(), 1);
}
