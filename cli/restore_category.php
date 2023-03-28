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


$usage = 'CLI de restauracion de categorias.

Usage:
    # php restore_category.php --origin_category_id=<courseid> --destiny_category_id=<categoryid> --origin_enrolusers=<enrolusers> --destiny_remove_activities=<destiny_remove_activities> --destiny_merge_activities=<destiny_merge_activities> --destiny_remove_enrols=<destiny_remove_enrols> --destiny_remove_groups=<destiny_remove_groups> --origin_remove_course=<origin_remove_course> --origin_schedule_datetime=<origin_schedule_datetime>

    --origin_category_id=<courseid>  Origin Category ID (int).
    --destiny_category_id=<categoryid>  Destiny Category ID (int). (Optional)
    --origin_enrolusers=<enrolusers>    Enrol users (Boolean).
    --destiny_remove_activities=<destiny_remove_activities> Remove Activities (Boolean).
    --destiny_merge_activities=<destiny_merge_activities>   Merge Activities (Boolean).
    --destiny_remove_enrols=<destiny_remove_enrols> Remove Enrols (Boolean).
    --destiny_remove_groups=<destiny_remove_groups> Remove Groups (Boolean).
    --origin_remove_course=<origin_remove_course>   Remove Course (Boolean).
    --origin_schedule_datetime=<origin_schedule_datetime>   Date in UNIX timestamp (int).
    
Options:
    -h --help                   Print this help.

Description.

Examples:

    # php local/coursetransfer/restore_course.php --origin_category_id=127 --destiny_category_id=101 --origin_enrolusers=true --destiny_remove_activities=false --destiny_merge_activities=true --destiny_remove_enrols=false --destiny_remove_groups=false --origin_remove_course=false --origin_schedule_datetime=1679404952
';

global $CFG;

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require(__DIR__.'/classes/test/test.php');

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'origin_category_id' => null,
    'destiny_category_id' => null,
    'origin_enrolusers' => false,
    'destiny_remove_activities' => false,
    'destiny_merge_activities' => false,
    'destiny_remove_enrols' => false,
    'destiny_remove_groups' => false,
    'origin_remove_course' => false,
    'origin_schedule_datetime' => 0,
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

// TODO: comprobacion de los parametros introducidos
if ( $options['origin_category_id'] === null ){
    cli_writeln( get_string('origin_category_id_require','local_coursetransfer') );
    exit(128);
} else if( gettype( $options['origin_category_id']) !== 'integer' ){
    cli_writeln( get_string('origin_category_id_integer','local_coursetransfer') );
    exit(128);
}

if ( $options['destiny_category_id'] !== null ){
    if( gettype( $options['destiny_category_id'] ) !== 'integer' ){
        cli_writeln( get_string('destiny_category_id_integer','local_coursetransfer') );
        exit(128);
    }
}

if( gettype( $options['origin_enrolusers'] ) !== 'boolean' ){
    cli_writeln( get_string('origin_enrolusers_boolean','local_coursetransfer') );
    exit(128);
}

if( gettype( $options['destiny_remove_activities'] ) !== 'boolean' ){
    cli_writeln( get_string('destiny_remove_activities_boolean','local_coursetransfer') );
    exit(128);
}

if( gettype( $options['destiny_merge_activities'] ) !== 'boolean' ){
    cli_writeln( get_string('destiny_merge_activities_boolean','local_coursetransfer') );
    exit(128);
}

if( gettype( $options['destiny_remove_enrols'] ) !== 'boolean' ){
    cli_writeln( get_string('destiny_remove_enrols_boolean','local_coursetransfer') );
    exit(128);
}

if( gettype( $options['destiny_remove_groups'] ) !== 'boolean' ){
    cli_writeln( get_string('destiny_remove_groups_boolean','local_coursetransfer') );
    exit(128);
}

if( gettype( $options['origin_remove_course'] ) !== 'boolean' ){
    cli_writeln( get_string('origin_remove_course_boolean','local_coursetransfer') );
    exit(128);
}

if( gettype( $options['origin_schedule_datetime'] ) !== 'integer' ){
    cli_writeln( get_string('origin_schedule_datetime_integer','local_coursetransfer') );
    exit(128);
}

$destinysites = get_config('local_coursetransfer', 'destiny_sites');
$destinysites = explode(PHP_EOL ,$destinysites);

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