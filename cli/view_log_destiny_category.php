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

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');

$usage = 'CLI para ver los logs de restauraciones en una categoría a partir de otra categoría desde otro Moodle.

Usage:
    # php view_log_destiny_category.php
        --categoryid=<categoryid>

    --categoryid=<categoryid>  Destiny Category ID (int)

Options:
    -h --help                   Print this help.

Description.

Examples:

    # php local/coursetransfer/cli/view_log_destiny_category.php --categoryid=3
';

list($options, $unrecognised) = cli_get_params([
        'help' => false,
        'categoryid' => null,
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

$categoryid = (int) $options['categoryid'];

if ( $categoryid === null ) {
    cli_writeln( get_string('destiny_category_id_require', 'local_coursetransfer') );
    exit(128);
} else if ( $categoryid <= 0 ) {
    cli_writeln( get_string('destiny_category_id_integer', 'local_coursetransfer') );
    exit(128);
}

try {

    $mask = "| %12.15s |%-35.35s | %-14.14s | %-14.14s  | %-14.14s | %-30.30s  | %-30.30s  | %-7.7s  | %-15.15s  | %-15.15s |\n";
    printf($mask,
            'Request ID', 'Destiny Site', 'Dest Category', 'Orig Category',
            'Status', 'Courses Selected', 'Error', 'UserID', 'TimeModified', 'TimeCreated');

    foreach (\local_coursetransfer\coursetransfer_request::get_by_destiny_category_id($categoryid) as $item) {
        $error = !empty($item->error_code) ? $item->error_code . ': ' . $item->error_message : '-';
        $courses = json_decode($item->origin_category_courses);
        $coursesid = '';
        foreach ($courses as $course) {
            if (empty($coursesid)) {
                $coursesid .= $course->id;
            } else {
                $coursesid .= '-'. $course->id;
            }
        }
        printf($mask,
                $item->id, $item->siteurl, $item->destiny_category_id, $item->origin_category_id,
                get_string('status_' . coursetransfer::STATUS[$item->status]['shortname'], 'local_coursetransfer'),
                $coursesid, $error, $item->userid, $item->timemodified, $item->timecreated);
    }
    exit(0);

} catch (moodle_exception $e) {
    cli_writeln('300800: ' . $e->getMessage());
    exit(1);
}

