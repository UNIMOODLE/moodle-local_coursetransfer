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
 * Upgrade.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 *
 * XMLDB xmldb_local_coursetransfer_upgrade.

 * @param $oldversion
 * @return bool
 * @throws ddl_exception
 * @throws ddl_table_missing_exception
 */
function xmldb_local_coursetransfer_upgrade($oldversion): bool {
    global $CFG, $DB;

    require_once($CFG->libdir.'/db/upgradelib.php'); // Core Upgrade-related functions.

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2024040500) {

        $destinytable = new xmldb_table('local_coursetransfer_destiny');

        if ($dbman->table_exists($destinytable)) {
            // Rename table local_coursetransfer_destiny to local_coursetransfer_target
            $dbman->rename_table($destinytable, 'local_coursetransfer_target');
            error_log('Table local_coursetransfer_destiny change name');
        } else {
            error_log('Table local_coursetransfer_destiny not exists');
        }

        $requesttable = new xmldb_table('local_coursetransfer_request');

        if ($dbman->table_exists($requesttable)) {
            if ($dbman->field_exists($requesttable, 'destiny_request_id')) {
                $field = new xmldb_field('destiny_request_id', XMLDB_TYPE_INTEGER,
                        '10', null, false, null, null);
                $dbman->rename_field($requesttable, $field, 'target_request_id');
                error_log('Field destiny_request_id change name');
            } else {
                error_log('Field destiny_request_id not exists');
            }
            if ($dbman->field_exists($requesttable, 'destiny_course_id')) {
                $field = new xmldb_field('destiny_course_id', XMLDB_TYPE_INTEGER,
                        '10', null, false, null, null);
                $dbman->rename_field($requesttable, $field, 'target_course_id');
                error_log('Field destiny_course_id change name');
            } else {
                error_log('Field destiny_course_id not exists');
            }
            if ($dbman->field_exists($requesttable, 'destiny_category_id')) {
                $field = new xmldb_field('destiny_category_id', XMLDB_TYPE_INTEGER,
                        '10', null, false, null, null);
                $dbman->rename_field($requesttable, $field, 'target_category_id');
                error_log('Field destiny_category_id change name');
            } else {
                error_log('Field destiny_category_id not exists');
            }
            if ($dbman->field_exists($requesttable, 'destiny_remove_enrols')) {
                $field = new xmldb_field('destiny_remove_enrols', XMLDB_TYPE_INTEGER,
                        '1', null, false, null, '0');
                $dbman->rename_field($requesttable, $field, 'target_remove_enrols');
                error_log('Field destiny_remove_enrols change name');
            } else {
                error_log('Field destiny_remove_enrols not exists');
            }
            if ($dbman->field_exists($requesttable, 'destiny_remove_groups')) {
                $field = new xmldb_field('destiny_remove_groups', XMLDB_TYPE_INTEGER,
                        '1', null, false, null, '0');
                $dbman->rename_field($requesttable, $field, 'target_remove_groups');
                error_log('Field destiny_remove_groups change name');
            } else {
                error_log('Field destiny_remove_groups not exists');
            }
            if ($dbman->field_exists($requesttable, 'destiny_target')) {
                $field = new xmldb_field('destiny_target', XMLDB_TYPE_INTEGER,
                        '1', null, false, null, '3');
                $dbman->rename_field($requesttable, $field, 'target_target');
                error_log('Field destiny_target change name');
            } else {
                error_log('Field destiny_target not exists');
            }
        } else {
            error_log('Table local_coursetransfer_request not exists');
        }

    }

    return true;
}
