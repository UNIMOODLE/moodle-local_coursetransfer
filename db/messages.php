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
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// MESSAGE_DEFAULT_ENABLED is defined in Moodle 4.0. Avoid warning on 3.11.
if (!defined('MESSAGE_DEFAULT_ENABLED')) {
    define('MESSAGE_DEFAULT_ENABLED', 0x01); // 0001.
}

$messageproviders = [

    'restore_course_completed' => [
            'defaults' => [
                    'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            ],
    ],
    'restore_category_completed' => [
            'defaults' => [
                    'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            ]],
    'remove_course_completed' => [
            'defaults' => [
                    'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            ]],
    'remove_category_completed' => [
            'defaults' => [
                    'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            ]],

];
