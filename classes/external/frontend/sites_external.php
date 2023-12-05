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
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

class sites_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function site_add_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'type' => new external_value(PARAM_TEXT, 'Type: destiny or origin'),
                'host' => new external_value(PARAM_RAW, 'Host Url'),
                'token' => new external_value(PARAM_RAW, 'Host Token')
            )
        );
    }

    /**
     *
     *
     * @param string $type
     * @param string $host
     * @param string $token
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function site_add(string $type, string $host, string $token): array {
        global $DB, $USER;
        self::validate_parameters(
            self::site_add_parameters(),
            [
                'type' => $type,
                'host' => $host,
                'token' => $token
            ]
        );

        $success = false;
        $errors = [];
        $data = new stdClass();
        $data->id = 0;

        if ($type !== 'destiny' && $type !== 'origin') {
            $errors[] =
                    [
                            'code' => '18042',
                            'msg' => 'TYPE INVALID'
                    ];
        } else {
            try {
                $object = new stdClass();
                $object->host = trim($host);
                $object->token = trim($token);
                $object->userid = $USER->id;
                $object->timemodified = time();
                $object->timecreated = time();
                $res = $DB->insert_record('local_coursetransfer_' . $type, $object);
                $data->id = $res;
                $success = true;
            } catch (moodle_exception $e) {
                $errors[] =
                        [
                                'code' => '18041',
                                'msg' => $e->getMessage()
                        ];
            }
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
    public static function site_add_returns(): external_single_structure {
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
                        'id' => new external_value(PARAM_INT, 'Site ID', VALUE_OPTIONAL)
                    )
                )
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function site_edit_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'type' => new external_value(PARAM_TEXT, 'Type: destiny or origin'),
                        'id' => new external_value(PARAM_INT, 'Host ID'),
                        'host' => new external_value(PARAM_RAW, 'Host Url'),
                        'token' => new external_value(PARAM_RAW, 'Host Token')
                )
        );
    }

    /**
     *
     *
     * @param string $type
     * @param int $id
     * @param string $host
     * @param string $token
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function site_edit(string $type, int $id, string $host, string $token): array {
        global $DB, $USER;
        self::validate_parameters(
                self::site_edit_parameters(),
                [
                        'type' => $type,
                        'id' => $id,
                        'host' => $host,
                        'token' => $token
                ]
        );

        $success = false;
        $errors = [];
        $data = new stdClass();
        $data->id = $id;

        if ($type !== 'destiny' && $type !== 'origin') {
            $errors[] =
                    [
                            'code' => '18032',
                            'msg' => 'TYPE INVALID'
                    ];
        } else {
            try {
                $object = new stdClass();
                $object->id = $id;
                $object->host = trim($host);
                $object->token = trim($token);
                $object->userid = $USER->id;
                $object->timemodified = time();
                $DB->update_record('local_coursetransfer_' . $type, $object);
                $success = true;
            } catch (moodle_exception $e) {
                $errors[] =
                        [
                                'code' => '18031',
                                'msg' => $e->getMessage()
                        ];
            }
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
    public static function site_edit_returns(): external_single_structure {
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
                                        'id' => new external_value(PARAM_INT, 'Site ID', VALUE_OPTIONAL)
                                )
                        )
                )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function site_remove_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'type' => new external_value(PARAM_TEXT, 'Type: destiny or origin'),
                        'id' => new external_value(PARAM_INT, 'Host ID')
                )
        );
    }

    /**
     *
     *
     * @param string $type
     * @param int $id
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function site_remove(string $type, int $id): array {
        global $DB;
        self::validate_parameters(
                self::site_remove_parameters(),
                [
                        'type' => $type,
                        'id' => $id
                ]
        );

        $success = false;
        $errors = [];
        $data = new stdClass();
        $data->id = $id;

        if ($type !== 'destiny' && $type !== 'origin') {
            $errors[] =
                    [
                            'code' => '18022',
                            'msg' => 'TYPE INVALID'
                    ];
        } else {
            try {
                $DB->delete_records('local_coursetransfer_' . $type, ['id' => $id]);
                $success = true;
            } catch (moodle_exception $e) {
                $errors[] =
                        [
                                'code' => '18021',
                                'msg' => $e->getMessage()
                        ];
            }
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
    public static function site_remove_returns(): external_single_structure {
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
                                        'id' => new external_value(PARAM_INT, 'Site ID', VALUE_OPTIONAL)
                                )
                        )
                )
        );
    }



    /**
     * @return external_function_parameters
     */
    public static function site_test_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'type' => new external_value(PARAM_TEXT, 'Type: destiny or origin'),
                        'id' => new external_value(PARAM_INT, 'Host ID')
                )
        );
    }

    /**
     *
     *
     * @param string $type
     * @param int $id
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function site_test(string $type, int $id): array {
        global $USER;
        self::validate_parameters(
                self::site_test_parameters(),
                [
                        'type' => $type,
                        'id' => $id
                ]
        );

        $success = false;
        $error = [
                'code' => '',
                'msg' => ''
        ];
        $data = new stdClass();
        $data->id = $id;

        if ($type !== 'destiny' && $type !== 'origin') {
            $error = [
                            'code' => '18011',
                            'msg' => 'TYPE INVALID'
                    ];
        } else {
            try {
                if ($type === 'origin') {
                    $site = coursetransfer::get_site_by_position($id);
                    $request = new request($site);
                    $res = $request->site_origin_test($USER);
                    if ($res->success) {
                        $success = true;
                    } else {
                        $success = false;
                        $error = empty($res->errors) ? null : $res->errors[0];
                        $error = [
                                'code' => !is_null($error) ? $error->code : '',
                                'msg' => !is_null($error) ? $error->msg : '',
                        ];
                    }
                } else if ($type === 'destiny') {
                    $site = coursetransfer::get_site_by_position($id, 'destiny');
                    $request = new request($site);
                    $res = $request->site_destiny_test($USER);
                    if ($res->success) {
                        $success = true;
                    } else {
                        $success = false;
                        $error = empty($res->errors) ? null : $res->errors[0];
                        $error = [
                                'code' => !is_null($error) ? $error->code : '',
                                'msg' => !is_null($error) ? $error->msg : '',
                        ];
                    }
                }
            } catch (moodle_exception $e) {
                $error = [
                        'code' => '18010',
                        'msg' => $e->getMessage()
                ];
            }
        }

        return [
                'success' => $success,
                'error' => $error,
                'data' => $data
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function site_test_returns(): external_single_structure {
        return new external_single_structure(
                array(
                        'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                        'error' => new external_single_structure(
                                array(
                                        'code' => new external_value(PARAM_TEXT, 'Code'),
                                        'msg' => new external_value(PARAM_RAW, 'Message')
                                )
                        ),
                        'data' => new external_single_structure(
                                array(
                                        'id' => new external_value(PARAM_INT, 'Site ID', VALUE_OPTIONAL)
                                )
                        )
                )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function origin_test_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'field' => new external_value(PARAM_TEXT, 'Field'),
                        'value' => new external_value(PARAM_TEXT, 'Value'),
                        'destinysite' => new external_value(PARAM_TEXT, 'Destiny Site URL')
                )
        );
    }

    /**
     * Origin Test
     *
     * @param string $field
     * @param string $value
     * @param string $destinysite
     *
     *
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_test(string $field, string $value, string $destinysite): array {
        global $USER;
        self::validate_parameters(
                self::origin_test_parameters(), [
                        'field' => $field,
                        'value' => $value,
                        'destinysite' => $destinysite
                ]
        );

        $errors = [];
        $data = new stdClass();

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $verifydestiny = coursetransfer::verify_destiny_site($destinysite);
                if ($verifydestiny['success']) {
                    $sitedestiny = coursetransfer::get_site_by_url($destinysite, 'destiny');
                    $request = new request($sitedestiny);
                    $res = $request->site_destiny_test($USER);
                    if ($res->success) {
                        $success = true;
                    } else {
                        $success = false;
                        $errors = $res->errors;
                    }
                } else {
                    $success = false;
                    $errors[] = $verifydestiny['error'];
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] = [
                       'code' => '20021',
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
    public static function origin_test_returns(): external_single_structure {
        return new external_single_structure(
                array(
                        'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                        'errors' => new external_multiple_structure(new external_single_structure(
                                array(
                                        'code' => new external_value(PARAM_TEXT, 'Code'),
                                        'msg' => new external_value(PARAM_TEXT, 'Message')
                                ), PARAM_TEXT, 'Errors'
                        )),
                )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function destiny_test_parameters(): external_function_parameters {
        return new external_function_parameters(
                array(
                        'field' => new external_value(PARAM_TEXT, 'Field'),
                        'value' => new external_value(PARAM_TEXT, 'Value')
                )
        );
    }

    /**
     * Origin Test
     *
     * @param string $field
     * @param string $value
     *
     *
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function destiny_test(string $field, string $value): array {

        self::validate_parameters(
                self::destiny_test_parameters(), [
                        'field' => $field,
                        'value' => $value
                ]
        );

        $errors = [];
        $data = new stdClass();

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $success = true;
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] = [
                    'code' => '18051',
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
    public static function destiny_test_returns(): external_single_structure {
        return new external_single_structure(
                array(
                        'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                        'errors' => new external_multiple_structure(new external_single_structure(
                                array(
                                        'code' => new external_value(PARAM_TEXT, 'Code'),
                                        'msg' => new external_value(PARAM_TEXT, 'Message')
                                ), PARAM_TEXT, 'Errors'
                        )),
                )
        );
    }

};
