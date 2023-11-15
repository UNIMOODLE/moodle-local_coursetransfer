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

namespace local_coursetransfer\external\frontend;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\models\configuration_category;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

class restore_category_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function new_origin_restore_category_step1_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'siteurl' => new external_value(PARAM_INT, 'Site Url'),
                'categoryid' => new external_value(PARAM_INT, 'Category ID')
            )
        );
    }

    /**
     *
     *
     * @param int $siteurl
     * @param int $categoryid
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function new_origin_restore_category_step1(int $siteurl, int $categoryid): array {
        global $USER;
        self::validate_parameters(
            self::new_origin_restore_category_step1_parameters(),
            [
                'siteurl' => $siteurl,
                'categoryid' => $categoryid
            ]
        );

        $success = false;
        $errors = [];
        $data = new stdClass();
        $data->userid = 0;
        $data->username = '';
        $data->firstname = '';
        $data->lastname = '';
        $data->email = '';
        $data->nexturl = '';

        try {
            $site = coursetransfer::get_site_by_position($siteurl);
            $request = new request($site);
            $res = $request->origin_has_user($USER);
            if ($res->success) {
                $data = $res->data;
                $nexturl = new moodle_url(
                    '/local/coursetransfer/origin_restore_category.php',
                    ['id' => $categoryid, 'new' => 1, 'step' => 2, 'site' => $siteurl]
                );
                $data->nexturl = $nexturl->out(false);
                $success = true;
            } else {
                $errors[] = $res->errors;
            }
        } catch (moodle_exception $e) {
            $errors[] =
                [
                    'code' => '45012',
                    'msg' => $e->getMessage()
                ];
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function new_origin_restore_category_step1_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    array(
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message')
                    )
                )),
                'data' => new external_single_structure(
                    array(
                        'userid' => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL),
                        'username' => new external_value(PARAM_TEXT, 'Username', VALUE_OPTIONAL),
                        'firstname' => new external_value(PARAM_TEXT, 'Firstname', VALUE_OPTIONAL),
                        'lastname' => new external_value(PARAM_TEXT, 'Lastname', VALUE_OPTIONAL),
                        'email' => new external_value(PARAM_TEXT, 'Email', VALUE_OPTIONAL),
                        'nexturl' => new external_value(PARAM_RAW, 'Next URL', VALUE_OPTIONAL)
                    )
                )
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function new_origin_restore_category_step4_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'siteurl' => new external_value(PARAM_INT, 'Site Url'),
                        'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                        'destinyid' => new external_value(PARAM_INT, 'Category Destiny ID'),
                        'courses' => new external_multiple_structure(new external_single_structure(
                                array(
                                        'id' => new external_value(PARAM_INT, 'Course ID'),
                                )
                        ))
                )
        );
    }

    /**
     *
     * @param int $siteurl
     * @param int $categoryid
     * @param int $destinyid
     * @param array $courses
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function new_origin_restore_category_step4(
            int $siteurl, int $categoryid, int $destinyid, array $courses): array {

        global $USER;

        self::validate_parameters(
                self::new_origin_restore_category_step4_parameters(),
                [
                        'siteurl' => $siteurl,
                        'categoryid' => $categoryid,
                        'destinyid' => $destinyid,
                        'courses' => $courses
                ]
        );

        $success = false;
        $errors = [];
        $data = new stdClass();
        $nexturl = new moodle_url('/local/coursetransfer/origin_restore_category.php', ['id' => $destinyid]);
        $data->nexturl = $nexturl->out(false);

        try {
            $site = coursetransfer::get_site_by_position($siteurl);
            $configuration = new configuration_category(
                    \backup::TARGET_NEW_COURSE, false, false);
            $res = coursetransfer::restore_category($USER, $site, $destinyid, $categoryid, $configuration, $courses);
            $errors = array_merge($errors, $res['errors']);
            $success = $res['success'];
        } catch (moodle_exception $e) {
            $errors[] =
                    [
                            'code' => '45011',
                            'msg' => $e->getMessage()
                    ];
        }

        return [
                'success' => $success,
                'errors' => $errors,
                'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function new_origin_restore_category_step4_returns(): external_single_structure {
        return new external_single_structure(
                array(
                        'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                        'data' => new external_single_structure(
                                array(
                                        'nexturl' => new external_value(PARAM_RAW, 'Next URL', VALUE_OPTIONAL)
                                )
                        ),
                        'errors' => new external_multiple_structure(new external_single_structure(
                                array(
                                        'code' => new external_value(PARAM_TEXT, 'Code'),
                                        'msg' => new external_value(PARAM_TEXT, 'Message')
                                )
                        ))
                )
        );
    }

};
