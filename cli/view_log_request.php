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
use local_coursetransfer\coursetransfer_request;

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');

$usage = 'CLI to view the logs of a request.

Usage:
    # php view_log_request.php
        --requestid=<requestid>

    --requestid=<requestid>  Request ID (int)

Options:
    -h --help                   Print this help.

Description.

Examples:

    # php local/coursetransfer/cli/view_log_request.php --requestid=3
';

list($options, $unrecognised) = cli_get_params([
        'help' => false,
        'requestid' => null,
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

$requestid = (int) $options['requestid'];

if ( $requestid === null ) {
    cli_writeln( get_string('requestid_require', 'local_coursetransfer') );
    exit(128);
} else if ( $requestid <= 0 ) {
    cli_writeln( get_string('requestid_integer', 'local_coursetransfer') );
    exit(128);
}

try {

    $request = coursetransfer_request::get($requestid);
    if ($request) {
        foreach ($request as $key => $item) {
            if ($key === 'origin_category_courses') {
                $coursesid = '';
                if (!is_null($item)) {
                    $courses = json_decode($item);
                    foreach ($courses as $course) {
                        if (empty($coursesid)) {
                            $coursesid .= $course->id;
                        } else {
                            $coursesid .= '-'. $course->id;
                        }
                    }
                }
                cli_writeln( $key . ': ' . $coursesid);
            } else if ($key === 'type') {
                $type = (int)$item === coursetransfer_request::TYPE_COURSE ? 'restore course' : 'restore category';
                cli_writeln( $key . ': ' . $type);
            } else if ($key === 'direction') {
                $type = (int)$item === coursetransfer_request::DIRECTION_REQUEST ? 'request' : 'answer';
                cli_writeln( $key . ': ' . $type);
            } else if ($key === 'origin_activities') {
                cli_writeln( $key . ': ' . 'view more details in view_log_request_activities_detail');
            } else if ($key === 'destiny_target' ) {
                switch ($item) {
                    case 2:
                        $target = 'In New Course';
                        break;
                    case 3:
                        $target = 'Remove Content';
                        break;
                    case 4:
                        $target = 'Merge the backup into this course';
                        break;
                    default:
                        $target = '-';
                }
                cli_writeln( $key . ': ' . $target);
            } else if ($key === 'timemodified' || $key === 'timecreated' ) {
                cli_writeln( $key . ': ' . userdate($item));
            } else if ($key === 'origin_enrolusers' || $key === 'origin_remove_course' ||
                    $key === 'origin_remove_category' || $key === 'destiny_remove_enrols' ||
                    $key === 'destiny_remove_groups' ) {
                $bool = (int)$item === 1 ? 'true' : 'false';
                cli_writeln( $key . ': ' . $bool);
            } else if ($key === 'status') {
                cli_writeln( $key . ': ' . get_string('status_' .
                                coursetransfer::STATUS[$item]['shortname'], 'local_coursetransfer'));
            } else {
                cli_writeln( $key . ': ' . $item );
            }
        }
    } else {
        cli_writeln( get_string('request_not_found', 'local_coursetransfer') );
    }
    exit(0);

} catch (moodle_exception $e) {
    cli_writeln('40009: ' . $e->getMessage());
    exit(1);
}

