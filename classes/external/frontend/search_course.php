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
 * Search Course External.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external\frontend;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * Class search_course
 *
 * @package local_coursetransfer\external\frontend
 */
class search_course extends external_api {

    /**
     * Origin restore Step1 parameters.
     *
     * @return external_function_parameters
     */
    public static function search_by_name_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'text' => new external_value(PARAM_TEXT, 'Text for search course in destination'),
            ]
        );
    }

    /**
     * Search by name.
     *
     * @param string $text
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function search_by_name(string $text): array {
        $params = self::validate_parameters(
            self::search_by_name_parameters(), [
                'text' => $text,
            ]
        );

        $text = $params['text'];

        $success = false;
        $errors = [];
        $data = [];

        try {
            $courses = coursetransfer::get_courses($text, 0, 7);
            foreach ($courses as $course) {
                $c = new stdClass();
                $c->id = $course->id;
                $c->fullname = $course->fullname;
                $data[] = $c;
            }
            $success = true;
        } catch (moodle_exception $e) {
            $errors[] =
                [
                    'code' => '18000',
                    'msg' => $e->getMessage(),
                ];
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * Origin restore Step1 returns.
     *
     * @return external_single_structure
     */
    public static function search_by_name_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_RAW, 'Message'),
                    ]
                )),
                'data' => new external_multiple_structure(new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'Coursename ID'),
                        'fullname' => new external_value(PARAM_TEXT, 'Fullname'),
                    ]),
                ),
            ]
        );
    }

};
