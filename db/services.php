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
 * @package     local_coursetransfer
 * @copyright   3iPunt <https://www.tresipunt.com/>
 */

use local_coursetransfer\external\destiny_course_callback_external;
use local_coursetransfer\external\origin_course_backup_external;
use local_coursetransfer\external\origin_course_external;
use local_coursetransfer\external\origin_user_external;
use local_coursetransfer\external\restore_course_external;

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_coursetransfer_origin_has_user' => [
        'classname' => origin_user_external::class,
        'methodname' => 'origin_has_user',
        'description' => 'Asks to origin if user exists',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_origin_get_courses' => [
        'classname' => origin_course_external::class,
        'methodname' => 'origin_get_courses',
        'description' => 'Get all courses from user',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_origin_get_course_detail' => [
        'classname' => origin_course_external::class,
        'methodname' => 'origin_get_course_detail',
        'description' => 'Get specific course details',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_origin_backup_course' => [
        'classname' => origin_course_backup_external::class,
        'methodname' => 'origin_backup_course',
        'description' => 'Asks origin to make a backup of the course',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_destiny_backup_course_completed' => [
        'classname' => destiny_course_callback_external::class,
        'methodname' => 'destiny_backup_course_completed',
        'description' => 'Notify origin that the backup is completed',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_destiny_backup_course_error' => [
        'classname' => destiny_course_callback_external::class,
        'methodname' => 'destiny_backup_course_error',
        'description' => 'Notify origin that an error ocurred',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_new_origin_restore_course_step1' => [
        'classname' => restore_course_external::class,
        'methodname' => 'new_origin_restore_course_step1',
        'description' => 'Verify that user exists in origin and check if it has courses as a teacher',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_new_origin_restore_course_step5' => [
        'classname' => restore_course_external::class,
        'methodname' => 'new_origin_restore_course_step5',
        'description' => 'Execute restauration from moodle remote in step5',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => false
    ],

];

$services = [
    'local_coursetransfer' => [
        'functions' => [
            'local_coursetransfer_origin_has_user',
            'local_coursetransfer_origin_get_courses',
            'local_coursetransfer_origin_get_course_detail',
            'local_coursetransfer_origin_backup_course',
            'local_coursetransfer_destiny_backup_course_completed',
            'local_coursetransfer_destiny_backup_course_error',
            'local_coursetransfer_new_origin_restore_course_step1',
            'local_coursetransfer_new_origin_restore_course_step5'
        ],
        'restrictedusers' => 1,
        'enabled' => 1
    ]
];
