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

use local_coursetransfer\external\backend\target_course_callback_external;
use local_coursetransfer\external\backend\origin_category_external;
use local_coursetransfer\external\backend\origin_course_backup_external;
use local_coursetransfer\external\backend\origin_course_external;
use local_coursetransfer\external\backend\origin_user_external;
use local_coursetransfer\external\backend\remove_external;
use local_coursetransfer\external\frontend\origin_remove_external;
use local_coursetransfer\external\frontend\restore_category_external;
use local_coursetransfer\external\frontend\restore_course_external;
use local_coursetransfer\external\frontend\restore_external;
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

    'local_coursetransfer_new_origin_restore_course_step1' => [
        'classname' => restore_course_external::class,
        'methodname' => 'new_origin_restore_course_step1',
        'description' => 'Verify that user exists in origin and check if it has courses as a teacher',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_new_origin_restore_course_step5' => [
        'classname' => restore_course_external::class,
        'methodname' => 'new_origin_restore_course_step5',
        'description' => 'Execute course restauration from moodle remote in step5',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],

    'local_coursetransfer_new_origin_restore_category_step1' => [
            'classname' => restore_category_external::class,
            'methodname' => 'new_origin_restore_category_step1',
            'description' => 'Verify that user exists in origin and check if it has courses as a teacher',
            'type' => 'read',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_new_origin_restore_category_step4' => [
            'classname' => restore_category_external::class,
            'methodname' => 'new_origin_restore_category_step4',
            'description' => 'Execute category restauration from moodle remote in step4',
            'type' => 'write',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_origin_restore_step1' => [
            'classname' => restore_external::class,
            'methodname' => 'origin_restore_step1',
            'description' => 'Execute restauration from moodle remote in step1',
            'type' => 'write',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_origin_restore_step4' => [
            'classname' => restore_external::class,
            'methodname' => 'origin_restore_step4',
            'description' => 'Execute courses restauration from moodle remote in step4',
            'type' => 'write',
            'ajax' => true,
            'loginrequired' => true,
    ],

    'local_coursetransfer_origin_restore_cat_step4' => [
            'classname' => restore_external::class,
            'methodname' => 'origin_restore_cat_step4',
            'description' => 'Execute category restauration from moodle remote in step4',
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

    'local_coursetransfer_origin_get_courses_by_ids' => [
            'classname' => origin_course_external::class,
            'methodname' => 'origin_get_courses_by_ids',
            'description' => 'Get courses by ids from user',
            'type' => 'read',
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
            'local_coursetransfer_origin_backup_course',
            'local_coursetransfer_target_backup_course_completed',
            'local_coursetransfer_target_backup_course_error',
            'local_coursetransfer_target_remove_course_completed',
            'local_coursetransfer_target_remove_course_error',
            'local_coursetransfer_new_origin_restore_course_step1',
            'local_coursetransfer_new_origin_restore_course_step5',
            'local_coursetransfer_new_origin_restore_category_step1',
            'local_coursetransfer_new_origin_restore_category_step4',
            'local_coursetransfer_origin_restore_step1',
            'local_coursetransfer_origin_restore_step4',
            'local_coursetransfer_origin_restore_cat_step4',
            'local_coursetransfer_origin_remove_step1',
            'local_coursetransfer_origin_remove_step3',
            'local_coursetransfer_origin_remove_cat_step3',
            'local_coursetransfer_origin_remove_course',
            'local_coursetransfer_origin_remove_category',
            'local_coursetransfer_site_add',
            'local_coursetransfer_site_edit',
            'local_coursetransfer_site_remove',
            'local_coursetransfer_site_test',
            'local_coursetransfer_site_origin_test',
            'local_coursetransfer_site_target_test',
            'local_coursetransfer_dest_search_course_name',
            'local_coursetransfer_origin_get_courses_by_ids',
        ],
        'downloadfiles' => 1,
        'restrictedusers' => 1,
        'enabled' => 1,
    ],
];
