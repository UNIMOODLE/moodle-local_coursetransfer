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

use core_course_category;
use DateTime;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\models\configuration_category;
use local_coursetransfer\models\configuration_course;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

class restore_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function origin_restore_step1_parameters(): external_function_parameters {
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
    public static function origin_restore_step1(int $siteurl, string $type): array {
        global $USER;
        self::validate_parameters(
            self::origin_restore_step1_parameters(),
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
            $res = $request->origin_has_user($USER);
            if ($res->success) {
                $data = $res->data;
                $nexturl = new moodle_url(
                    '/local/coursetransfer/origin_restore.php',
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
                    'code' => '10510',
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
    public static function origin_restore_step1_returns(): external_single_structure {
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
    public static function origin_restore_step4_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'siteurl' => new external_value(PARAM_INT, 'Site Url'),
                        'courses' => new external_multiple_structure(new external_single_structure(
                                array(
                                        'courseid' => new external_value(PARAM_INT, 'Origin Course ID'),
                                        'destinyid' => new external_value(PARAM_INT, 'Destiny Course ID'),
                                        'categorydestiny' => new external_value(PARAM_INT, 'Destiny Category ID')
                                )
                        )),
                        'configuration' => new external_single_structure(
                                array(
                                        'destiny_merge_activities' => new external_value(PARAM_BOOL, 'Destiny Merge Activities'),
                                        'destiny_remove_enrols' => new external_value(PARAM_BOOL, 'Destiny Remove Enrols'),
                                        'destiny_remove_groups' => new external_value(PARAM_BOOL, 'Destiny Remove Groups'),
                                        'destiny_remove_activities' => new external_value(PARAM_BOOL, 'Destiny Remove Activities'),
                                        'origin_enrol_users' => new external_value(PARAM_BOOL, 'Origin Restore User Data'),
                                        'origin_remove_course' => new external_value(PARAM_BOOL, 'Origin Remove Course'),
                                        'origin_schedule_datetime' => new external_value(PARAM_INT, 'Origin Schedule Datetime'),
                                )
                        ),
                )
        );
    }

    /**
     *
     *
     * @param int $siteurl
     * @param array $courses
     * @param array $configuration
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_restore_step4(int $siteurl, array $courses, array $configuration): array {
        global $USER;
        self::validate_parameters(
            self::origin_restore_step4_parameters(),
                [
                        'siteurl' => $siteurl,
                        'courses' => $courses,
                        'configuration' => $configuration
                ]
        );

        $success = false;
        $errors = [];
        $data = new stdClass();
        $nexturl = new moodle_url('/local/coursetransfer/logs.php');
        $data->nexturl = $nexturl->out(false);

        try {
            $site = coursetransfer::get_site_by_position($siteurl);
            $num = 1;
            foreach ($courses as $course) {
                try {
                    if ((int)$course['destinyid'] === 0) {
                        $target = \backup::TARGET_NEW_COURSE;
                        if ((int)$course['categorydestiny'] === 0) {
                            $category = core_course_category::get_default();
                        } else {
                            $category = core_course_category::get((int)$course['categorydestiny']);
                        }
                        $destinycourseid = \local_coursetransfer\factory\course::create(
                                $category, 'Remote Restoring in process...', 'IN-PROGRESS-' . time() . '-' . $num);
                    } else {
                        $target = $configuration['destiny_merge_activities'] ?
                                \backup::TARGET_EXISTING_ADDING : \backup::TARGET_EXISTING_DELETING;
                        $destinycourseid = $course['destinyid'];
                    }
                    $nextruntime = $configuration['origin_schedule_datetime'] / 1000;
                    $date = new DateTime();
                    $date->setTimestamp(intval($nextruntime));
                    $config = new configuration_course(
                            $target, $configuration['destiny_remove_enrols'], $configuration['destiny_remove_groups'],
                            $configuration['origin_enrol_users'], $configuration['origin_remove_course'], $date->getTimestamp());
                    $res = coursetransfer::restore_course($USER, $site, $destinycourseid, $course['courseid'], $config, []);
                    if (!$res['success']) {
                        $errors = $res['errors'];
                    } else {
                        $success = true;
                    }
                    $num ++;
                } catch (moodle_exception $e) {
                    $success = false;
                    $errors[] =
                            [
                                    'code' => '10501',
                                    'msg' => 'Course ID: ' . $course['courseid'] . ' - ' . $e->getMessage()
                            ];
                }
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '10500',
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
    public static function origin_restore_cat_step4_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'siteurl' => new external_value(PARAM_INT, 'Site Url'),
                        'catid' => new external_value(PARAM_INT, 'Origin Category Id'),
                        'destinyid' => new external_value(PARAM_INT, 'Destiny Category Id'),
                        'configuration' => new external_single_structure(
                                array(
                                        'origin_enrol_users' => new external_value(PARAM_BOOL, 'Origin Enrol Users'),
                                        'origin_remove_category' => new external_value(PARAM_BOOL, 'Origin Remove Category'),
                                        'origin_schedule_datetime' => new external_value(PARAM_INT, 'Origin Schedule Datetime'),
                                )
                        ),
                )
        );
    }

    /**
     *
     *
     * @param int $siteurl
     * @param int $catid
     * @param int $destinyid
     * @param array $configuration
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function origin_restore_cat_step4(
            int $siteurl, int $catid, int $destinyid, array $configuration): array {
        global $USER;
        self::validate_parameters(
            self::origin_restore_cat_step4_parameters(),
                [
                        'siteurl' => $siteurl,
                        'catid' => $catid,
                        'destinyid' => $destinyid,
                        'configuration' => $configuration
                ]
        );

        $success = false;
        $errors = [];
        $data = new stdClass();
        $nexturl = new moodle_url('/local/coursetransfer/logs.php', ['type' => coursetransfer_request::TYPE_CATEGORY]);
        $data->nexturl = $nexturl->out(false);

        try {
            $site = coursetransfer::get_site_by_position($siteurl);
            $nextruntime = $configuration['origin_schedule_datetime'] / 1000;
            $date = new DateTime();
            $date->setTimestamp(intval($nextruntime));
            $configuration = new configuration_category(\backup::TARGET_NEW_COURSE,
                    0, 0, $configuration['origin_enrol_users'],
                    $configuration['origin_remove_category'], $date->getTimestamp());

            $res = coursetransfer::restore_category($USER, $site, $destinyid, $catid, $configuration);

            if (!$res['success']) {
                $errors = $res['errors'];
            } else {
                $success = true;
            }

        } catch (moodle_exception $e) {
            $errors[] =
                [
                    'code' => '11011',
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
    public static function origin_restore_cat_step4_returns(): external_single_structure {
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
