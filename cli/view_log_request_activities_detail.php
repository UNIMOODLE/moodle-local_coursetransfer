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

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');

$usage = 'CLI para ver los logs detallados de una petición con las actividades seleccionadas.

Usage:
    # php view_log_request_activities_detail.php
        --requestid=<requestid>

    --requestid=<requestid>  Request ID (int)

Options:
    -h --help                   Print this help.

Description.

Examples:

    # php local/coursetransfer/cli/view_log_request_activities_detail.php --requestid=3
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

    $request = \local_coursetransfer\coursetransfer_request::get($requestid);
    if ($request) {
        $decode = json_decode($request->origin_activities);
        if (empty($decode)) {
            cli_writeln('All sections and activities');
        } else {
            cli_writeln(json_encode($decode, JSON_PRETTY_PRINT));
        }
    } else {
        cli_writeln( get_string('request_not_found', 'local_coursetransfer') );
    }
    exit(0);

} catch (moodle_exception $e) {
    cli_writeln('300600: ' . $e->getMessage());
    exit(1);
}

