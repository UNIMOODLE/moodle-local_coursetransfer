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
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright   3iPunt <https://www.tresipunt.com/>
 */

namespace local_coursetransfer\external;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\models\configuration_course;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

class remove_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function origin_remove_step1_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'siteurl' => new external_value(PARAM_INT, 'Site Url'),
                        'type' => new external_value(PARAM_TEXT, 'Type restore')
                )
        );
    }

    /**
     *
     *
     * @param int $siteurl
     * @param string $type
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_remove_step1(int $siteurl, string $type): array {
        self::validate_parameters(
                self::origin_remove_step1_parameters(),
                [
                        'siteurl' => $siteurl,
                        'type' => $type
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
            $res = $request->origin_has_user();
            if ($res->success) {
                $data = $res->data;
                $nexturl = new moodle_url(
                        '/local/coursetransfer/origin_remove.php',
                        ['step' => 2, 'site' => $siteurl, 'type' => $type]
                );
                $data->nexturl = $nexturl->out(false);
                $success = true;
            } else {
                $errors = $res->errors;
            }
        } catch (moodle_exception $e) {
            $errors[] =
                    [
                            'code' => '200301',
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
    public static function origin_remove_step1_returns(): external_single_structure {
        return new external_single_structure(
                array(
                        'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                        'errors' => new external_multiple_structure(new external_single_structure(
                                array(
                                        'code' => new external_value(PARAM_TEXT, 'Code'),
                                        'msg' => new external_value(PARAM_RAW, 'Message')
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
    public static function origin_remove_step3_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'siteurl' => new external_value(PARAM_INT, 'Site Url'),
                        'courses' => new external_multiple_structure(new external_single_structure(
                                array(
                                        'id' => new external_value(PARAM_INT, 'Origin Course ID')
                                )
                        )),
                )
        );
    }

    /**
     *
     *
     * @param int $siteurl
     * @param array $courses
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_remove_step3(int $siteurl, array $courses): array {
        self::validate_parameters(
                self::origin_remove_step3_parameters(),
                [
                        'siteurl' => $siteurl,
                        'courses' => $courses
                ]
        );

        $success = false;
        $errors = [];
        $data = new stdClass();
        $nexturl = new moodle_url('/local/coursetransfer/origin_remove.php');
        $data->nexturl = $nexturl->out(false);

        try {
            $site = coursetransfer::get_site_by_position($siteurl);
            foreach ($courses as $course) {
                $res = coursetransfer::remove_course($site, $course->id);
                if (!$res['success']) {
                    $errors = $res['errors'];
                } else {
                    $success = true;
                }
            }
        } catch (moodle_exception $e) {
            $errors[] =
                    [
                            'code' => '201101',
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
    public static function origin_restore_step4_returns(): external_single_structure {
        return new external_single_structure(
                array(
                        'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                        'data' => new external_single_structure(
                                array(
                                        'nexturl' => new external_value(PARAM_RAW, 'Next URL', VALUE_OPTIONAL, '#')
                                )
                        ),
                        'errors' => new external_multiple_structure(new external_single_structure(
                                array(
                                        'code' => new external_value(PARAM_TEXT, 'Code'),
                                        'msg' => new external_value(PARAM_RAW, 'Message')
                                )
                        ))
                )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function origin_remove_cat_step3_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'siteurl' => new external_value(PARAM_INT, 'Site Url'),
                        'catid' => new external_value(PARAM_INT, 'Origin Course Category ID')
                )
        );
    }

    /**
     *
     *
     * @param int $siteurl
     * @param int $catid
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_remove_cat_step3(int $siteurl, int $catid): array {
        self::validate_parameters(
                self::origin_remove_cat_step3_parameters(),
                [
                        'siteurl' => $siteurl,
                        'catid' => $catid
                ]
        );

        $success = false;
        $errors = [];
        $data = new stdClass();
        $nexturl = new moodle_url('/local/coursetransfer/origin_remove.php');
        $data->nexturl = $nexturl->out(false);

        try {
            $site = coursetransfer::get_site_by_position($siteurl);
            $res = coursetransfer::remove_category($site, $catid);
            if (!$res['success']) {
                $errors = $res['errors'];
            } else {
                $success = true;
            }
        } catch (moodle_exception $e) {
            $errors[] =
                    [
                            'code' => '202101',
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
    public static function origin_remove_cat_step3_returns(): external_single_structure {
        return new external_single_structure(
                array(
                        'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                        'data' => new external_single_structure(
                                array(
                                        'nexturl' => new external_value(PARAM_RAW, 'Next URL', VALUE_OPTIONAL, '#')
                                )
                        ),
                        'errors' => new external_multiple_structure(new external_single_structure(
                                array(
                                        'code' => new external_value(PARAM_TEXT, 'Code'),
                                        'msg' => new external_value(PARAM_RAW, 'Message')
                                )
                        ))
                )
        );
    }
};
