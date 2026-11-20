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
 * XMLDB xmldb_local_coursetransfer_upgrade.
 *
 * @param int $oldversion
 * @return bool
 * @throws ddl_exception
 * @throws ddl_field_missing_exception
 * @throws ddl_table_missing_exception
 * @throws downgrade_exception
 * @throws moodle_exception
 * @throws upgrade_exception
 */
function xmldb_local_coursetransfer_upgrade($oldversion): bool {
    global $CFG, $DB;

    require_once($CFG->libdir . '/db/upgradelib.php'); // Core Upgrade-related functions.

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2024040500) {
        $destinytable = new xmldb_table('local_coursetransfer_destiny');

        if ($dbman->table_exists($destinytable)) {
            // Rename table local_coursetransfer_destiny to local_coursetransfer_target.
            $dbman->rename_table($destinytable, 'local_coursetransfer_target');
            debugging('Table local_coursetransfer_destiny change name');
        } else {
            debugging('Table local_coursetransfer_destiny not exists');
        }

        $requesttable = new xmldb_table('local_coursetransfer_request');

        if ($dbman->table_exists($requesttable)) {
            if ($dbman->field_exists($requesttable, 'destiny_request_id')) {
                $field = new xmldb_field(
                    'destiny_request_id',
                    XMLDB_TYPE_INTEGER,
                    '10',
                    null,
                    false,
                    null,
                    null
                );
                $dbman->rename_field($requesttable, $field, 'target_request_id');
                debugging('Field destiny_request_id change name');
            } else {
                debugging('Field destiny_request_id not exists');
            }
            if ($dbman->field_exists($requesttable, 'destiny_course_id')) {
                $field = new xmldb_field(
                    'destiny_course_id',
                    XMLDB_TYPE_INTEGER,
                    '10',
                    null,
                    false,
                    null,
                    null
                );
                $dbman->rename_field($requesttable, $field, 'target_course_id');
                debugging('Field destiny_course_id change name');
            } else {
                debugging('Field destiny_course_id not exists');
            }
            if ($dbman->field_exists($requesttable, 'destiny_category_id')) {
                $field = new xmldb_field(
                    'destiny_category_id',
                    XMLDB_TYPE_INTEGER,
                    '10',
                    null,
                    false,
                    null,
                    null
                );
                $dbman->rename_field($requesttable, $field, 'target_category_id');
                debugging('Field destiny_category_id change name');
            } else {
                debugging('Field destiny_category_id not exists');
            }
            if ($dbman->field_exists($requesttable, 'destiny_remove_enrols')) {
                $field = new xmldb_field(
                    'destiny_remove_enrols',
                    XMLDB_TYPE_INTEGER,
                    '1',
                    null,
                    false,
                    null,
                    '0'
                );
                $dbman->rename_field($requesttable, $field, 'target_remove_enrols');
                debugging('Field destiny_remove_enrols change name');
            } else {
                debugging('Field destiny_remove_enrols not exists');
            }
            if ($dbman->field_exists($requesttable, 'destiny_remove_groups')) {
                $field = new xmldb_field(
                    'destiny_remove_groups',
                    XMLDB_TYPE_INTEGER,
                    '1',
                    null,
                    false,
                    null,
                    '0'
                );
                $dbman->rename_field($requesttable, $field, 'target_remove_groups');
                debugging('Field destiny_remove_groups change name');
            } else {
                debugging('Field destiny_remove_groups not exists');
            }
            if ($dbman->field_exists($requesttable, 'destiny_target')) {
                $field = new xmldb_field(
                    'destiny_target',
                    XMLDB_TYPE_INTEGER,
                    '1',
                    null,
                    false,
                    null,
                    '3'
                );
                $dbman->rename_field($requesttable, $field, 'target_target');
                debugging('Field destiny_target change name');
            } else {
                debugging('Field destiny_target not exists');
            }
        } else {
            debugging('Table local_coursetransfer_request not exists');
        }
        upgrade_plugin_savepoint(true, 2024040500, 'local', 'coursetransfer');
    }

    if ($oldversion < 2026061002) {
        // Add 'downloaded' field to track live download progress.
        $table = new xmldb_table('local_coursetransfer_request');
        $field = new xmldb_field('downloaded', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'origin_backup_url');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026061002, 'local', 'coursetransfer');
    }

    if ($oldversion < 2026061101) {
        // Add 'restored' field to track live restore progress (percent 0-100).
        $table = new xmldb_table('local_coursetransfer_request');
        $field = new xmldb_field('restored', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'downloaded');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026061101, 'local', 'coursetransfer');
    }

    if ($oldversion < 2026072000) {
        // Platform name and persisted connection test result on both site tables.
        foreach (['local_coursetransfer_origin', 'local_coursetransfer_target'] as $tablename) {
            $table = new xmldb_table($tablename);
            $fields = [
                new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'id'),
                new xmldb_field('lasttest', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'userid'),
                new xmldb_field('lastteststatus', XMLDB_TYPE_INTEGER, '2', null, null, null, null, 'lasttest'),
                new xmldb_field('lasttesterror', XMLDB_TYPE_TEXT, null, null, null, null, null, 'lastteststatus'),
            ];
            foreach ($fields as $field) {
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                }
            }
        }
        upgrade_plugin_savepoint(true, 2026072000, 'local', 'coursetransfer');
    }

    if ($oldversion < 2026072400) {
        // Persisted snapshot of the origin category subtree at request time.
        $table = new xmldb_table('local_coursetransfer_request');
        $field = new xmldb_field(
            'origin_category_tree',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null,
            'origin_category_requests'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026072400, 'local', 'coursetransfer');
    }

    if ($oldversion < 2026090900) {
        // Hosts are stored without their trailing slash, which is how they are looked up. Rows
        // written before that was enforced keep the slash and never match, so they are cleaned here.
        $fields = [
                'local_coursetransfer_origin' => 'host',
                'local_coursetransfer_target' => 'host',
                'local_coursetransfer_request' => 'siteurl',
        ];
        foreach ($fields as $table => $field) {
            $select = $DB->sql_like($field, ':slash');
            $rs = $DB->get_recordset_select($table, $select, ['slash' => '%/'], '', 'id, ' . $field);
            foreach ($rs as $row) {
                $DB->set_field($table, $field, rtrim($row->{$field}, '/'), ['id' => $row->id]);
            }
            $rs->close();
        }
        upgrade_plugin_savepoint(true, 2026090900, 'local', 'coursetransfer');
    }

    if ($oldversion < 2026111900) {
        // Peer-only services now require local/coursetransfer:webservice (TIPCOUTRAN-10, H8). Give
        // it to the existing service user role, or peers could no longer call this site. The
        // capabilities are created here because Moodle only does it after this function.
        update_capabilities('local_coursetransfer');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'local_coursetransfer_ws']);
        if ($roleid) {
            assign_capability('local/coursetransfer:webservice', CAP_ALLOW, $roleid, context_system::instance(), true);
            set_role_contextlevels($roleid, array_unique(array_merge(get_role_contextlevels($roleid), [CONTEXT_SYSTEM])));
        }
        upgrade_plugin_savepoint(true, 2026111900, 'local', 'coursetransfer');
    }

    if ($oldversion < 2026111901) {
        // Manifest of the origin course, checked against the restored course before removing the origin.
        $table = new xmldb_table('local_coursetransfer_request');
        $field = new xmldb_field('origin_manifest', XMLDB_TYPE_TEXT, null, null, null, null, null, 'origin_category_tree');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026111901, 'local', 'coursetransfer');
    }

    if ($oldversion < 2026111902) {
        // The service user was created with an address on a real TLD (.xxx): move it to a reserved
        // one and stop all email to it. Only the account this plugin created is touched.
        $wsuser = $DB->get_record('user', ['username' => 'local_coursetransfer_ws', 'deleted' => 0]);
        if ($wsuser && $wsuser->email === 'coursetransfer@test.xxx') {
            $DB->update_record('user', (object) [
                'id' => $wsuser->id,
                'email' => 'local_coursetransfer_ws@example.invalid',
                'emailstop' => 1,
                'timemodified' => time(),
            ]);
        } else if ($wsuser) {
            $DB->set_field('user', 'emailstop', 1, ['id' => $wsuser->id]);
        }
        upgrade_plugin_savepoint(true, 2026111902, 'local', 'coursetransfer');
    }

    if ($oldversion < 2026112001) {
        // 2.1 stored the origin's token in fileurl (?token=...): remove it from existing rows (LCT-011).
        // Only that parameter of that column changes; the download task no longer needs it.
        require_once(__DIR__ . '/upgradelib.php');
        local_coursetransfer_strip_legacy_tokens();
        upgrade_plugin_savepoint(true, 2026112001, 'local', 'coursetransfer');
    }

    return true;
}
