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
 * provider
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * Personal data kept by the plugin is the user id stored in three tables:
 * - local_coursetransfer_request: who launched a transfer (or, on the origin, the local
 *   user the request was resolved to);
 * - local_coursetransfer_origin and local_coursetransfer_target: who last modified a
 *   paired platform.
 * None of them has a course or module of its own, so the data lives in the user context.
 *
 * The user is also identified to the remote platform (the configured user field and its
 * value), and a transfer with users sends the course backup with their data.
 *
 * Deleting anonymises the rows (userid = 0) instead of removing them: requests are the
 * shared log of a transfer between two sites (the remote side refers to them by id, and
 * category requests aggregate their course requests), and platform rows are site
 * configuration. Once the userid is cleared, nothing left in them identifies the person.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] Tables with a userid column. */
    const TABLES = [
        'local_coursetransfer_request',
        'local_coursetransfer_origin',
        'local_coursetransfer_target',
    ];

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_coursetransfer_request', [
            'userid' => 'privacy:metadata:local_coursetransfer_request:userid',
            'type' => 'privacy:metadata:local_coursetransfer_request:type',
            'direction' => 'privacy:metadata:local_coursetransfer_request:direction',
            'siteurl' => 'privacy:metadata:local_coursetransfer_request:siteurl',
            'origin_course_id' => 'privacy:metadata:local_coursetransfer_request:origin_course_id',
            'origin_course_fullname' => 'privacy:metadata:local_coursetransfer_request:origin_course_fullname',
            'origin_category_id' => 'privacy:metadata:local_coursetransfer_request:origin_category_id',
            'origin_category_name' => 'privacy:metadata:local_coursetransfer_request:origin_category_name',
            'target_course_id' => 'privacy:metadata:local_coursetransfer_request:target_course_id',
            'target_category_id' => 'privacy:metadata:local_coursetransfer_request:target_category_id',
            'status' => 'privacy:metadata:local_coursetransfer_request:status',
            'timecreated' => 'privacy:metadata:local_coursetransfer_request:timecreated',
            'timemodified' => 'privacy:metadata:local_coursetransfer_request:timemodified',
        ], 'privacy:metadata:local_coursetransfer_request');

        foreach (['local_coursetransfer_origin', 'local_coursetransfer_target'] as $table) {
            $collection->add_database_table($table, [
                'userid' => "privacy:metadata:{$table}:userid",
                'host' => "privacy:metadata:{$table}:host",
                'timemodified' => "privacy:metadata:{$table}:timemodified",
            ], "privacy:metadata:{$table}");
        }

        $collection->add_external_location_link('remotesite', [
            'field' => 'privacy:metadata:remotesite:field',
            'value' => 'privacy:metadata:remotesite:value',
            'userdata' => 'privacy:metadata:remotesite:userdata',
        ], 'privacy:metadata:remotesite');

        // Course backups (.mbz) on both sides may hold participants' data: they live in this
        // plugin's file areas and are purged by retention, not per user.
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (self::user_has_data($userid)) {
            $contextlist->add_user_context($userid);
        }
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        if (self::user_has_data((int) $context->instanceid)) {
            $userlist->add_user((int) $context->instanceid);
        }
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || (int) $context->instanceid !== $userid) {
                continue;
            }

            $requests = [];
            $rows = $DB->get_records('local_coursetransfer_request', ['userid' => $userid], 'id');
            foreach ($rows as $row) {
                $requests[] = [
                    'id' => $row->id,
                    'type' => $row->type,
                    'direction' => $row->direction,
                    'siteurl' => $row->siteurl,
                    'origin_course_id' => $row->origin_course_id,
                    'origin_course_fullname' => $row->origin_course_fullname,
                    'origin_category_id' => $row->origin_category_id,
                    'origin_category_name' => $row->origin_category_name,
                    'target_course_id' => $row->target_course_id,
                    'target_category_id' => $row->target_category_id,
                    'status' => $row->status,
                    'timecreated' => transform::datetime($row->timecreated),
                    'timemodified' => transform::datetime($row->timemodified),
                ];
            }

            // The token is a site secret, not personal data: it is never exported.
            $sites = [];
            foreach (['origin', 'target'] as $type) {
                $rows = $DB->get_records('local_coursetransfer_' . $type, ['userid' => $userid], 'id', 'id, host, timemodified');
                foreach ($rows as $row) {
                    $sites[] = [
                        'type' => $type,
                        'host' => $row->host,
                        'timemodified' => transform::datetime($row->timemodified),
                    ];
                }
            }

            if ($requests || $sites) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_coursetransfer')],
                    (object) ['requests' => $requests, 'sites' => $sites]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        if (!$context instanceof context_user) {
            return;
        }
        self::anonymise_user((int) $context->instanceid);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && (int) $context->instanceid === $userid) {
                self::anonymise_user($userid);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        // A user context only holds the data of its own user.
        if (in_array((int) $context->instanceid, array_map('intval', $userlist->get_userids()), true)) {
            self::anonymise_user((int) $context->instanceid);
        }
    }

    /**
     * Whether any table of the plugin refers to the user.
     *
     * @param int $userid
     * @return bool
     */
    protected static function user_has_data(int $userid): bool {
        global $DB;
        if ($userid <= 0) {
            return false;
        }
        foreach (self::TABLES as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Clear the user from every table of the plugin, keeping the rows (see the class docblock).
     *
     * @param int $userid
     */
    protected static function anonymise_user(int $userid): void {
        global $DB;
        if ($userid <= 0) {
            return;
        }
        foreach (self::TABLES as $table) {
            $DB->set_field($table, 'userid', 0, ['userid' => $userid]);
        }
    }
}
