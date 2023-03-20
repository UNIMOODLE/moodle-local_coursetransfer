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

define('CLI_SCRIPT', true);

//defined('MOODLE_INTERNAL') || die();

global $CFG;

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require(__DIR__.'/classes/test/test.php');


$usage = 'CLI para ver logs de la categoria de destino.

Usage:
    # php view_log_destiny_category.php --categoryid=<courseid>

    --categoryid=<categoryid>  Category ID (int).

Options:
    -h --help                   Print this help.

Description.

Examples:

    # php local/coursetransfer/view_log_destiny_category.php --categoryid=1
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

// TODO. Validar parametros, tipados y permisos de usuario
if ( $options['categoryid'] == null )  {
    cli_writeln("Course ID es obligatorio");
    exit(128);
} else {
    if( !gettype( $options['categoryid']) == 'integer' ) {
        cli_writeln("Course ID tiene que ser entero");
        exit(128);
    }
}

$destinysites = get_config('local_coursetransfer', 'destiny_sites');
$destinysites = explode(PHP_EOL ,$destinysites);

$destinies = [];

foreach($destinysites as $destiny) {
    $destiny = explode(',', $destiny);
    $item = [];
    $item['host'] = trim($destiny[0]);
    $item['token'] = trim($destiny[1]);
    $destinies[] = $item;
}
var_dump($destinies);

// Step 1: Recuperar curso
//\local_coursetransfer\test\test::execute();

