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

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');

$usage = 'CLI to view the request and response logs filtering by status and date.

Usage:
    # php view_logs.php
        --type=<type>
        --direction=<direction>
        --status=<status>
        --from=<from>
        --to=<to>
        --userid=<userid>

    --type=<type>  Type (int) - (0) restore course, (1) restore category
    --direction=<direction>  Direction (int) - (0) request as destiny, (1) answer as origin
    --status=<status>  Status (int)
                * (0) Error
                * (1) Not started
                * (10) In Progress
                * (30) In Backup
                * (50) Incompleted (category)
                * (70) Download
                * (100) Completed
    --from=<from>  From date (int timestamp)
    --to=<to>  To date (int timestamp)
    --userid=<userid>  User ID (int)

Options:
    -h --help                   Print this help.

Description.

Examples:

    # php local/coursetransfer/cli/view_logs.php --type=0 --direction=0 --status=100 --from=1685075232 --to=1685075800 --userid=3
';

list($options, $unrecognised) = cli_get_params([
        'help' => false,
        'type' => null,
        'direction' => null,
        'status' => null,
        'from' => null,
        'to' => null,
        'userid' => null,
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

$type = isset($options['type']) ? (int) $options['type'] : null;
$direction = isset($options['direction']) ? (int) $options['direction'] : null;
$status = isset($options['status']) ? (int) $options['status'] : null;
$from = isset($options['from']) ? (int) $options['from'] : null;
$to = isset($options['to']) ? (int) $options['to'] : null;
$userid = isset($options['userid']) ? (int) $options['userid'] : null;


try {

    $mask = "| %8.8s | %-5.5s | %-5.5s | %-30.30s | %-10.10s ".
            "| %-10.10s | %-10.10s | %-10.10s | %-14.14s | %-7.7s | %-13.13s  | %-13.13s | %-40.40s \n";
    printf($mask,
            'Req ID', 'Type', 'Dir', 'Site URL', 'Dest Course', 'Orig Course', 'Dest Cat', 'Orig Cat',
            'Status', 'UserID', 'TimeModified', 'TimeCreated', 'Error');

    $filters = [
            'type' => $type,
            'direction' => $direction,
            'status' => $status,
            'from' => $from,
            'to' => $to,
            'userid' => $userid,
    ];

    $items = \local_coursetransfer\coursetransfer_request::filters($filters);


    foreach ($items as $item) {
        $error = !empty($item->error_code) ? $item->error_code . ': ' . $item->error_message : '-';
        printf($mask,
                $item->id, $item->type, $item->direction, $item->siteurl, $item->destiny_course_id, $item->origin_course_id,
                $item->destiny_category_id, $item->origin_category_id,
                get_string('status_' . coursetransfer::STATUS[$item->status]['shortname'], 'local_coursetransfer'),
                $item->userid, $item->timemodified, $item->timecreated, $error);
    }

    if (count($items) > 200) {
        cli_writeln('****************************');
        cli_writeln('EXISTEN MÁS DE 200 RESULTADOS');
        cli_writeln('****************************');
    }
    exit(0);

} catch (moodle_exception $e) {
    cli_writeln('40004: ' . $e->getMessage());
    exit(1);
}

