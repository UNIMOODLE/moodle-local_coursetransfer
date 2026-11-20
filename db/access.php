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
 * Access.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$capabilities = [

    // Only lists course and category names of the origin: no risk flag.
    'local/coursetransfer:origin_view_courses' => [
        'riskbitmask' => 0,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT,
        ],
    ],

    'local/coursetransfer:origin_restore' => [
        'riskbitmask' => RISK_XSS | RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_PROHIBIT,
            'editingteacher' => CAP_PROHIBIT,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT,
        ],
    ],

    'local/coursetransfer:origin_restore_course' => [
        'riskbitmask' => RISK_XSS | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT,
        ],
    ],

    // Restore a remote category into a category context (LCT-028): lets a
    // category manager use the contextual restore wizard, mirroring
    // origin_restore_course for the course context.
    'local/coursetransfer:origin_restore_category' => [
        'riskbitmask' => RISK_XSS | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_PROHIBIT,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT,
        ],
    ],

    'local/coursetransfer:origin_remove_course' => [
            'riskbitmask' => RISK_DATALOSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_COURSE,
            'archetypes' => [
                    'guest' => CAP_PROHIBIT,
                    'manager' => CAP_ALLOW,
                    'editingteacher' => CAP_PROHIBIT,
                    'teacher' => CAP_PROHIBIT,
                    'student' => CAP_PROHIBIT,
            ],
    ],

    'local/coursetransfer:origin_remove_category' => [
            'riskbitmask' => RISK_DATALOSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => [
                    'guest' => CAP_PROHIBIT,
                    'manager' => CAP_ALLOW,
                    'editingteacher' => CAP_PROHIBIT,
                    'teacher' => CAP_PROHIBIT,
                    'student' => CAP_PROHIBIT,
            ],
    ],

    'local/coursetransfer:origin_restore_course_users' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_PROHIBIT,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT,
        ],
    ],

    'local/coursetransfer:target_restore_enrol_remove' => [
            'riskbitmask' => RISK_DATALOSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_COURSE,
            'archetypes' => [
                    'guest' => CAP_PROHIBIT,
                    'manager' => CAP_ALLOW,
                    'editingteacher' => CAP_ALLOW,
                    'teacher' => CAP_PROHIBIT,
                    'student' => CAP_PROHIBIT,
            ],
    ],

    'local/coursetransfer:target_restore_groups_remove' => [
            'riskbitmask' => RISK_DATALOSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_COURSE,
            'archetypes' => [
                    'guest' => CAP_PROHIBIT,
                    'manager' => CAP_ALLOW,
                    'editingteacher' => CAP_ALLOW,
                    'teacher' => CAP_PROHIBIT,
                    'student' => CAP_PROHIBIT,
            ],
    ],

    'local/coursetransfer:target_restore_content_remove' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT,
        ],
    ],

    'local/coursetransfer:target_restore_merge' => [
        'riskbitmask' => RISK_XSS | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT,
        ],
    ],

    'local/coursetransfer:view_logs' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'guest' => CAP_PROHIBIT,
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_PROHIBIT,
            'teacher' => CAP_PROHIBIT,
            'student' => CAP_PROHIBIT,
        ],
    ],

    // Delete execution logs and retry a failed restore: writes, so view_logs (read) is not
    // enough. Cloned from view_logs so roles that could do it before keep it after upgrading.
    'local/coursetransfer:manage_requests' => [
        'riskbitmask' => RISK_XSS | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'local/coursetransfer:view_logs',
    ],

    // Call the services that only peer platforms use. Only the plugin's service user role
    // (local_coursetransfer_ws) gets it, in postinstall() and upgrade; no archetype.
    'local/coursetransfer:webservice' => [
        'riskbitmask' => RISK_PERSONAL | RISK_XSS | RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],

];
