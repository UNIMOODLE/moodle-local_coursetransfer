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

use local_coursetransfer\external\coursetransfer_external;


defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_coursetransfer_remote_has_user' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'remote_has_user',
        'description' => 'Asks to remote if user exists',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_origin_has_user' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'origin_has_user',
        'description' => 'Asks to origin if user with capabilities exists',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_remote_get_courses' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'remote_get_courses',
        'description' => 'Get all courses from user',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_origin_get_courses' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'origin_get_courses',
        'description' => 'Get all courses from user',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_remote_get_course_detail' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'remote_get_courses_detail',
        'description' => 'Get all details from a course in remote',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_remote_get_course_size' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'remote_get_courses_size',
        'description' => 'Get all size of a course in remote',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_remote_get_site_space_available' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'remote_get_site_space_available',
        'description' => 'Get available disk space in remote',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_remote_course_backup' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'remote_course_backup',
        'description' => 'Execute a backup of an specific course at remote',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_remote_category_backup' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'remote_category_backup',
        'description' => 'Execute a backup of all courses in a specific category at remote',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_origin_course_restore' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'origin_course_restore',
        'description' => 'Get a course from remote to origin',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_remote_course_remove' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'remote_course_remove',
        'description' => 'Remove a course from remote',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_remote_course_backup_remove' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'remote_course_backup_remove',
        'description' => 'Remove the backup of a course at remote',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],

    'local_coursetransfer_origin_send_notification' => [
        'classname' => coursetransfer_external::class,
        'methodname' => 'origin_send_notification',
        'description' => 'Notification from remote to origin (success or fail)',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false
    ],
];

$services = [
    'local_coursetransfer' => [
        'functions' => [
            'local_coursetransfer_remote_has_user',
            'local_coursetransfer_origin_has_user',
            'local_coursetransfer_remote_get_courses',
            'local_coursetransfer_origin_get_courses',
            'local_coursetransfer_remote_get_course_detail',
            'local_coursetransfer_remote_get_course_size',
            'local_coursetransfer_remote_get_site_space_available',
            'local_coursetransfer_remote_course_backup',
            'local_coursetransfer_remote_category_backup',
            'local_coursetransfer_origin_course_restore',
            'local_coursetransfer_remote_course_remove',
            'local_coursetransfer_remote_course_backup_remove',
            'local_coursetransfer_origin_send_notification'
        ],
        'restrictedusers' => 0,
        'enabled' => 1
    ]
];
