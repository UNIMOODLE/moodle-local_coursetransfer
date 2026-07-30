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
 * Services.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\external\backend\get_category_idnumber_external;
use local_coursetransfer\external\backend\target_course_callback_external;
use local_coursetransfer\external\backend\origin_category_external;
use local_coursetransfer\external\backend\origin_course_backup_external;
use local_coursetransfer\external\backend\origin_course_external;
use local_coursetransfer\external\backend\origin_user_external;
use local_coursetransfer\external\backend\remove_external;
use local_coursetransfer\external\frontend\origin_remove_external;
use local_coursetransfer\external\frontend\restore_wizard_external;
use local_coursetransfer\external\frontend\token_external;
use local_coursetransfer\external\frontend\search_category;
use local_coursetransfer\external\frontend\search_course;
use local_coursetransfer\external\frontend\sites_external;

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_coursetransfer_origin_has_user' => [
        'classname' => origin_user_external::class,
        'methodname' => 'origin_has_user',
        'description' => 'Asks to origin if user exists',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_origin_get_courses' => [
        'classname' => origin_course_external::class,
        'methodname' => 'origin_get_courses',
        'description' => 'Get all courses from user',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_origin_get_categories' => [
        'classname' => origin_category_external::class,
        'methodname' => 'origin_get_categories',
        'description' => 'Get all categories from user',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_origin_get_course_detail' => [
        'classname' => origin_course_external::class,
        'methodname' => 'origin_get_course_detail',
        'description' => 'Get specific course details',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_origin_get_category_detail' => [
        'classname' => origin_category_external::class,
        'methodname' => 'origin_get_category_detail',
        'description' => 'Get specific category details',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    // Returns the full category subtree (nested categories + courses); used by the restore
    // wizard to render the category hierarchy and import preserving the tree structure.
    'local_coursetransfer_origin_get_category_detail_tree' => [
        'classname' => origin_category_external::class,
        'methodname' => 'origin_get_category_detail_tree',
        'description' => 'Get specific category details in tree format',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_origin_backup_course' => [
        'classname' => origin_course_backup_external::class,
        'methodname' => 'origin_backup_course',
        'description' => 'Asks origin to make a backup of the course',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_origin_backup_category_course' => [
        'classname' => origin_course_backup_external::class,
        'methodname' => 'origin_backup_course',
        'description' => 'Asks origin to make a backup of the course',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_target_backup_course_completed' => [
        'classname' => target_course_callback_external::class,
        'methodname' => 'target_backup_course_completed',
        'description' => 'Notify origin that the backup is completed',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_target_backup_course_error' => [
        'classname' => target_course_callback_external::class,
        'methodname' => 'target_backup_course_error',
        'description' => 'Notify origin that an error ocurred',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_target_remove_course_completed' => [
        'classname' => target_course_callback_external::class,
        'methodname' => 'target_remove_course_completed',
        'description' => 'Notify origin that the course remove is completed',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_target_remove_course_error' => [
        'classname' => target_course_callback_external::class,
        'methodname' => 'target_remove_course_error',
        'description' => 'Notify origin that an error ocurred',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_origin_remove_step1' => [
            'classname' => origin_remove_external::class,
            'methodname' => 'origin_remove_step1',
            'description' => 'Execute courses or category remove from moodle remote in step1',
            'type' => 'write',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_origin_remove_step3' => [
            'classname' => origin_remove_external::class,
            'methodname' => 'origin_remove_step3',
            'description' => 'Execute courses remove from moodle remote in step3',
            'type' => 'write',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_origin_remove_cat_step3' => [
            'classname' => origin_remove_external::class,
            'methodname' => 'origin_remove_cat_step3',
            'description' => 'Execute category remove from moodle remote in step3',
            'type' => 'write',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_origin_remove_course' => [
            'classname' => remove_external::class,
            'methodname' => 'origin_remove_course',
            'description' => 'Remove origin course',
            'type' => 'write',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_origin_remove_category' => [
            'classname' => remove_external::class,
            'methodname' => 'origin_remove_category',
            'description' => 'Remove origin category',
            'type' => 'write',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_site_add' => [
            'classname' => sites_external::class,
            'methodname' => 'site_add',
            'description' => 'Site Add',
            'type' => 'write',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_site_edit' => [
            'classname' => sites_external::class,
            'methodname' => 'site_edit',
            'description' => 'Site Edit',
            'type' => 'write',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_site_remove' => [
            'classname' => sites_external::class,
            'methodname' => 'site_remove',
            'description' => 'Site Remove',
            'type' => 'write',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_site_check' => [
            'classname' => sites_external::class,
            'methodname' => 'site_check',
            'description' => 'Site Check (test connection before saving)',
            'type' => 'read',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_site_test' => [
            'classname' => sites_external::class,
            'methodname' => 'site_test',
            'description' => 'Site Test',
            'type' => 'read',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_site_origin_test' => [
            'classname' => sites_external::class,
            'methodname' => 'origin_test',
            'description' => 'Site Origin Test',
            'type' => 'read',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_site_target_test' => [
            'classname' => sites_external::class,
            'methodname' => 'target_test',
            'description' => 'Site Target Test',
            'type' => 'read',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_dest_search_course_name' => [
            'classname' => search_course::class,
            'methodname' => 'search_by_name',
            'description' => 'Search course by name in destination',
            'type' => 'read',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_dest_search_category_name' => [
            'classname' => search_category::class,
            'methodname' => 'search_by_name',
            'description' => 'Search destination category by name (autocomplete)',
            'type' => 'read',
            'ajax' => true,
            'loginrequired' => true,
    ],

    // DEPRECATED since 2.0.0 (kept for backward compatibility with UNIMOODLE peers).
    // Superseded by the restore wizard flow (origin_get_courses + origin_get_course_detail).
    // Deprecation is signalled via origin_course_external::origin_get_courses_by_ids_is_deprecated().
    'local_coursetransfer_origin_get_courses_by_ids' => [
        'classname' => origin_course_external::class,
        'methodname' => 'origin_get_courses_by_ids',
        'description' => 'Get courses by ids from user',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_get_category_idnumber' => [
        'classname' => get_category_idnumber_external::class,
        'methodname' => 'get_category_idnumber',
        'description' => 'Resolve a category by idnumber regex pattern',
        'type' => 'read',
        'ajax' => false,
        'loginrequired' => true,
    ],

    'local_coursetransfer_restore_wizard_get_sites' => [
        'classname' => restore_wizard_external::class,
        'methodname' => 'get_sites',
        'description' => 'Restore wizard: list origin sites',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_restore_wizard_list_origin' => [
        'classname' => restore_wizard_external::class,
        'methodname' => 'list_origin',
        'description' => 'Restore wizard: list origin courses or categories',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_restore_wizard_get_sections' => [
        'classname' => restore_wizard_external::class,
        'methodname' => 'get_sections',
        'description' => 'Restore wizard: get sections/activities of an origin course (teacher flow)',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_restore_wizard_get_category_tree' => [
        'classname' => restore_wizard_external::class,
        'methodname' => 'get_category_tree',
        'description' => 'Restore wizard: get the subtree (nested subcategories + courses) of an origin category',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_restore_wizard_delete_request' => [
        'classname' => restore_wizard_external::class,
        'methodname' => 'delete_request',
        'description' => 'Delete a request (execution log) record',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_restore_wizard_submit' => [
        'classname' => restore_wizard_external::class,
        'methodname' => 'submit',
        'description' => 'Restore wizard: submit restore request',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_restore_wizard_submit_course' => [
        'classname' => restore_wizard_external::class,
        'methodname' => 'submit_course',
        'description' => 'Restore wizard: submit a teacher course restore over the current course',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_restore_wizard_submit_category' => [
        'classname' => restore_wizard_external::class,
        'methodname' => 'submit_category',
        'description' => 'Restore wizard: submit a teacher/manager category restore into the current category',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_restore_wizard_remove_submit' => [
        'classname' => restore_wizard_external::class,
        'methodname' => 'remove_submit',
        'description' => 'Remove wizard: delete selected courses or categories on a remote platform',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_token_create' => [
        'classname' => token_external::class,
        'methodname' => 'create',
        'description' => 'Summary: create this site service-user token',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_token_revoke' => [
        'classname' => token_external::class,
        'methodname' => 'revoke',
        'description' => 'Summary: revoke this site service-user token',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_token_regenerate' => [
        'classname' => token_external::class,
        'methodname' => 'regenerate',
        'description' => 'Summary: regenerate this site service-user token',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

];

$services = [
    'local_coursetransfer' => [
        'functions' => [
            'local_coursetransfer_origin_has_user',
            'local_coursetransfer_origin_get_courses',
            'local_coursetransfer_origin_get_categories',
            'local_coursetransfer_origin_get_course_detail',
            'local_coursetransfer_origin_get_category_detail',
            'local_coursetransfer_origin_get_category_detail_tree',
            'local_coursetransfer_origin_backup_course',
            'local_coursetransfer_target_backup_course_completed',
            'local_coursetransfer_target_backup_course_error',
            'local_coursetransfer_target_remove_course_completed',
            'local_coursetransfer_target_remove_course_error',
            'local_coursetransfer_origin_remove_step1',
            'local_coursetransfer_origin_remove_step3',
            'local_coursetransfer_origin_remove_cat_step3',
            'local_coursetransfer_origin_remove_course',
            'local_coursetransfer_origin_remove_category',
            'local_coursetransfer_site_add',
            'local_coursetransfer_site_edit',
            'local_coursetransfer_site_remove',
            'local_coursetransfer_site_check',
            'local_coursetransfer_site_test',
            'local_coursetransfer_site_origin_test',
            'local_coursetransfer_site_target_test',
            'local_coursetransfer_dest_search_course_name',
            'local_coursetransfer_origin_get_courses_by_ids',
            'local_coursetransfer_get_category_idnumber',
            'local_coursetransfer_restore_wizard_get_sites',
            'local_coursetransfer_restore_wizard_list_origin',
            'local_coursetransfer_restore_wizard_get_sections',
            'local_coursetransfer_restore_wizard_submit',
            'local_coursetransfer_restore_wizard_submit_course',
            'local_coursetransfer_restore_wizard_submit_category',
            'local_coursetransfer_restore_wizard_remove_submit',
        ],
        'downloadfiles' => 1,
        'restrictedusers' => 1,
        'enabled' => 1,
    ],
];
