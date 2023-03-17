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
 * Capability definitions for the Course Transfer plugin.
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$capabilities = array(
    'local/coursetransfer:origin_view_courses' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT
        )
    ),

    'local/coursetransfer:origin_restore_course' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT
        )
    ),

    'local/coursetransfer:origin_restore_course_users' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_PROHIBIT,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT
        )
    ),

    'local/coursetransfer:destiny_restore_content_remove' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT
        )
    ),

    'local/coursetransfer:destiny_restore_merge' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT
        )
    ),

    'local/coursetransfer:destiny_restore_enrol_remove' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT
        )
    ),

    'local/coursetransfer:destiny_restore_groups_remove' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT
        )
    ),

    'local/coursetransfer:origin_remove_course' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_PROHIBIT,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT
        )
    )
);
