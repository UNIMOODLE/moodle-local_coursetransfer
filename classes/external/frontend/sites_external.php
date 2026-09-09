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
 * Sites External.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external\frontend;

use coding_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use dml_exception;
use invalid_parameter_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_sites;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * Class sites_external
 *
 * @package local_coursetransfer\external\frontend
 */
class sites_external extends external_api {

    /**
     * Site add parameters.
     *
     * @return external_function_parameters
     */
    public static function site_add_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'type' => new external_value(PARAM_TEXT, 'Type: target or origin'),
                'host' => new external_value(PARAM_RAW, 'Host Url'),
                'token' => new external_value(PARAM_RAW, 'Host Token'),
                'name' => new external_value(PARAM_TEXT, 'Platform name', VALUE_DEFAULT, ''),
            ]
        );
    }

    /**
     * Site add.
     *
     * @param string $type
     * @param string $host
     * @param string $token
     * @param string $name
     * @return array
     * @throws invalid_parameter_exception
     * @throws coding_exception
     */
    public static function site_add(string $type, string $host, string $token, string $name = ''): array {
        global $DB, $USER;
        $params = self::validate_parameters(
            self::site_add_parameters(), [
                'type' => $type,
                'host' => $host,
                'token' => $token,
                'name' => $name,
            ]
        );

        $type = $params['type'];
        $host = trim($params['host']);
        $token = trim($params['token']);
        $name = trim($params['name']);

        $success = false;
        $errors = [];
        $data = new stdClass();
        $data->id = 0;

        if ($type !== 'target' && $type !== 'origin') {
            $errors[] = [
                'code' => '18044',
                'msg' => get_string('type_invalid', 'local_coursetransfer'),
            ];
        } else if ($host === '' || $token === '') {
            $errors[] = [
                'code' => '18043',
                'msg' => get_string('host_token_empty', 'local_coursetransfer'),
            ];
        } else if (!self::is_valid_url($host)) {
            $errors[] = [
                'code' => '18045',
                'msg' => get_string('host_url_invalid', 'local_coursetransfer'),
            ];
        } else {
            try {
                $object = new stdClass();
                $object->name = $name;
                $object->host = coursetransfer_sites::clean_host($host);
                $object->token = $token;
                $object->userid = $USER->id;
                $object->timemodified = time();
                $object->timecreated = time();
                $recordselect = $DB->get_record_select('local_coursetransfer_' . $type,
                    "host = :host", ['host' => $object->host]);
                if ($recordselect) {
                    $errors[] = [
                        'code' => '18042',
                        'msg' => get_string('site_exist', 'local_coursetransfer'),
                    ];
                } else {
                    $res = $DB->insert_record('local_coursetransfer_' . $type, $object);
                    $data->id = $res;
                    $success = true;
                }
            } catch (moodle_exception $e) {
                $errors[] = [
                    'code' => '18041',
                    'msg' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * Validate that a value is a syntactically correct HTTP/HTTPS URL.
     *
     * @param string $url
     * @return bool
     */
    protected static function is_valid_url(string $url): bool {
        if ($url === '') {
            return false;
        }
        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Site add returns.
     *
     * @return external_single_structure
     */
    public static function site_add_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_RAW, 'Message'),
                    ]
                )),
                'data' => new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'Site ID', VALUE_OPTIONAL),
                    ]
                ),
            ]
        );
    }

    /**
     * Site edit parameters.
     *
     * @return external_function_parameters
     */
    public static function site_edit_parameters(): external_function_parameters {
        return new external_function_parameters(
                [
                    'type' => new external_value(PARAM_TEXT, 'Type: target or origin'),
                    'id' => new external_value(PARAM_INT, 'Host ID'),
                    'host' => new external_value(PARAM_RAW, 'Host Url'),
                    'token' => new external_value(PARAM_RAW, 'Host Token'),
                    'name' => new external_value(PARAM_TEXT, 'Platform name', VALUE_DEFAULT, ''),
                ]
        );
    }

    /**
     * Site edit.
     *
     * @param string $type
     * @param int $id
     * @param string $host
     * @param string $token
     * @param string $name
     * @return array
     * @throws invalid_parameter_exception
     * @throws coding_exception|dml_exception
     */
    public static function site_edit(string $type, int $id, string $host, string $token, string $name = ''): array {
        global $DB, $USER;
        $params = self::validate_parameters(
            self::site_edit_parameters(), [
                'type' => $type,
                'id' => $id,
                'host' => $host,
                'token' => $token,
                'name' => $name,
            ]
        );

        $type = $params['type'];
        $id = $params['id'];
        $host = trim($params['host']);
        $token = trim($params['token']);
        $name = trim($params['name']);

        $success = false;
        $errors = [];
        $data = new stdClass();
        $data->id = $id;

        if ($type !== 'target' && $type !== 'origin') {
            $errors[] = [
                'code' => '18032',
                'msg' => get_string('type_invalid', 'local_coursetransfer'),
            ];
        } else if ($host === '' || $token === '') {
            $errors[] = [
                'code' => '18043',
                'msg' => get_string('host_token_empty', 'local_coursetransfer'),
            ];
        } else if (!self::is_valid_url($host)) {
            $errors[] = [
                'code' => '18045',
                'msg' => get_string('host_url_invalid', 'local_coursetransfer'),
            ];
        } else if (!$DB->record_exists('local_coursetransfer_' . $type, ['id' => $id])) {
            $errors[] = [
                'code' => '18033',
                'msg' => get_string('site_not_found', 'local_coursetransfer'),
            ];
        } else {
            try {
                $object = new stdClass();
                $object->id = $id;
                $object->name = $name;
                $object->host = coursetransfer_sites::clean_host($host);
                $object->token = $token;
                $object->userid = $USER->id;
                $object->timemodified = time();
                $recordselect = $DB->get_record_select('local_coursetransfer_' . $type,
                    "host = :host", ['host' => $object->host]);
                if ($recordselect && (int)$recordselect->id !== $object->id) {
                    $errors[] = [
                        'code' => '18032',
                        'msg' => get_string('site_exist', 'local_coursetransfer'),
                    ];
                } else {
                    $DB->update_record('local_coursetransfer_' . $type, $object);
                    $success = true;
                }
            } catch (moodle_exception $e) {
                $errors[] = [
                    'code' => '18031',
                    'msg' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * Site edit returns.
     *
     * @return external_single_structure
     */
    public static function site_edit_returns(): external_single_structure {
        return new external_single_structure(
                [
                    'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                    'errors' => new external_multiple_structure(new external_single_structure(
                        [
                            'code' => new external_value(PARAM_TEXT, 'Code'),
                            'msg' => new external_value(PARAM_RAW, 'Message'),
                        ]
                    )),
                    'data' => new external_single_structure(
                        [
                            'id' => new external_value(PARAM_INT, 'Site ID', VALUE_OPTIONAL),
                        ]
                    ),
                ]
        );
    }

    /**
     * Site remove parameters.
     *
     * @return external_function_parameters
     */
    public static function site_remove_parameters(): external_function_parameters {
        return new external_function_parameters(
                [
                    'type' => new external_value(PARAM_TEXT, 'Type: target or origin'),
                    'id' => new external_value(PARAM_INT, 'Host ID'),
                ]
        );
    }

    /**
     * Site remove.
     *
     * @param string $type
     * @param int $id
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
    */
    public static function site_remove(string $type, int $id): array {
        global $DB;
        $params = self::validate_parameters(
            self::site_remove_parameters(), [
                'type' => $type,
                'id' => $id,
            ]
        );

        $type = $params['type'];
        $id = $params['id'];

        $success = false;
        $errors = [];
        $data = new stdClass();
        $data->id = $id;

        if ($type !== 'target' && $type !== 'origin') {
            $errors[] = [
                'code' => '18022',
                'msg' => get_string('type_invalid', 'local_coursetransfer'),
            ];
        } else {
            $record = $DB->get_record('local_coursetransfer_' . $type, ['id' => $id]);
            if (!$record) {
                $errors[] = [
                    'code' => '18023',
                    'msg' => get_string('site_not_found', 'local_coursetransfer'),
                ];
            } else if (coursetransfer_sites::is_in_use($record->host)) {
                $errors[] = [
                    'code' => '18024',
                    'msg' => get_string('site_in_use', 'local_coursetransfer'),
                ];
            } else {
                try {
                    $DB->delete_records('local_coursetransfer_' . $type, ['id' => $id]);
                    $success = true;
                } catch (moodle_exception $e) {
                    $errors[] = [
                        'code' => '18021',
                        'msg' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * Site remove returns.
     *
     * @return external_single_structure
     */
    public static function site_remove_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                        [
                            'code' => new external_value(PARAM_TEXT, 'Code'),
                            'msg' => new external_value(PARAM_RAW, 'Message'),
                        ]
                )),
                'data' => new external_single_structure(
                        [
                            'id' => new external_value(PARAM_INT, 'Site ID', VALUE_OPTIONAL),
                        ]
                ),
            ]
        );
    }

    /**
     * Site test parameters.
     *
     * @return external_function_parameters
     */
    public static function site_test_parameters(): external_function_parameters {
        return new external_function_parameters(
                [
                    'type' => new external_value(PARAM_TEXT, 'Type: target or origin'),
                    'id' => new external_value(PARAM_INT, 'Host ID'),
                ]
        );
    }

    /**
     * Site test.
     *
     * @param string $type
     * @param int $id
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function site_test(string $type, int $id): array {
        global $USER;
        $params = self::validate_parameters(
            self::site_test_parameters(), [
                'type' => $type,
                'id' => $id,
            ]
        );

        $type = $params['type'];
        $id = $params['id'];

        $success = false;
        $errors = [];
        $data = new stdClass();
        $data->id = $id;

        $sitefound = false;
        if ($type !== 'target' && $type !== 'origin') {
            $errors[] = [
                'code' => '18011',
                'msg' => get_string('type_invalid', 'local_coursetransfer'),
            ];
        } else {
            try {
                $site = coursetransfer::get_site_by_position($id, $type);
                $sitefound = true;
                $request = new request($site);
                $res = ($type === 'origin')
                    ? $request->site_origin_test($USER)
                    : $request->site_target_test($USER);
                if ($res->success) {
                    $success = true;
                } else {
                    foreach ((array)$res->errors as $err) {
                        $errors[] = [
                            'code' => isset($err->code) ? (string)$err->code : '',
                            'msg' => isset($err->msg) ? (string)$err->msg : '',
                        ];
                    }
                    if (empty($errors)) {
                        $errors[] = [
                            'code' => '18012',
                            'msg' => get_string('unknown_error', 'local_coursetransfer'),
                        ];
                    }
                }
            } catch (moodle_exception $e) {
                $errors[] = [
                    'code' => '18010',
                    'msg' => $e->getMessage(),
                ];
            }
        }

        $primary = !empty($errors) ? $errors[0] : ['code' => '', 'msg' => ''];

        if ($sitefound) {
            // Persist the outcome so the platforms page can show the last test result.
            $errormsg = $success ? '' : trim($primary['code'] . ': ' . $primary['msg'], ': ');
            coursetransfer_sites::save_test_result($type, $id, $success, $errormsg);
        }

        return [
            'success' => $success,
            'error' => $primary,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * Site test returns.
     *
     * @return external_single_structure
     */
    public static function site_test_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'error' => new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_RAW, 'Message'),
                    ]
                ),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_RAW, 'Message'),
                    ]
                ), 'List of errors', VALUE_OPTIONAL),
                'data' => new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'Site ID', VALUE_OPTIONAL),
                    ]
                ),
            ]
        );
    }

    /**
     * Site check parameters.
     *
     * @return external_function_parameters
     */
    public static function site_check_parameters(): external_function_parameters {
        return new external_function_parameters(
                [
                    'type' => new external_value(PARAM_TEXT, 'Type: target or origin'),
                    'host' => new external_value(PARAM_RAW, 'Host Url'),
                    'token' => new external_value(PARAM_RAW, 'Host Token'),
                ]
        );
    }

    /**
     * Site check: test a connection BEFORE the site is persisted.
     *
     * Same test as site_test but on raw host/token, so the platforms
     * wizard can require a successful test prior to saving.
     *
     * @param string $type
     * @param string $host
     * @param string $token
     * @return array
     * @throws invalid_parameter_exception
     * @throws coding_exception
     */
    public static function site_check(string $type, string $host, string $token): array {
        global $USER;
        $params = self::validate_parameters(
            self::site_check_parameters(), [
                'type' => $type,
                'host' => $host,
                'token' => $token,
            ]
        );

        $type = $params['type'];
        $host = trim($params['host']);
        $token = trim($params['token']);

        $success = false;
        $errors = [];

        if ($type !== 'target' && $type !== 'origin') {
            $errors[] = [
                'code' => '18011',
                'msg' => get_string('type_invalid', 'local_coursetransfer'),
            ];
        } else if (!self::is_valid_url($host)) {
            $errors[] = [
                'code' => '18045',
                'msg' => get_string('host_url_invalid', 'local_coursetransfer'),
            ];
        } else {
            try {
                $site = new stdClass();
                $site->host = coursetransfer_sites::clean_host($host);
                $site->token = $token;
                $request = new request($site);
                $res = ($type === 'origin')
                    ? $request->site_origin_test($USER)
                    : $request->site_target_test($USER);
                if ($res->success) {
                    $success = true;
                } else {
                    foreach ((array)$res->errors as $err) {
                        $errors[] = [
                            'code' => isset($err->code) ? (string)$err->code : '',
                            'msg' => isset($err->msg) ? (string)$err->msg : '',
                        ];
                    }
                    if (empty($errors)) {
                        $errors[] = [
                            'code' => '18012',
                            'msg' => get_string('unknown_error', 'local_coursetransfer'),
                        ];
                    }
                }
            } catch (moodle_exception $e) {
                $errors[] = [
                    'code' => '18010',
                    'msg' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => $success,
            'errors' => $errors,
        ];
    }

    /**
     * Site check returns.
     *
     * @return external_single_structure
     */
    public static function site_check_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_RAW, 'Message'),
                    ]
                )),
            ]
        );
    }

    /**
     * Origin test parameters.
     *
     * @return external_function_parameters
     */
    public static function origin_test_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'field' => new external_value(PARAM_ALPHANUMEXT, 'Field'),
                'value' => new external_value(PARAM_RAW, 'Value'),
                'targetsite' => new external_value(PARAM_RAW, 'Target Site URL'),
            ]
        );
    }

    /**
     * Origin test.
     *
     * @param string $field
     * @param string $value
     * @param string $targetsite
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function origin_test(string $field, string $value, string $targetsite): array {
        global $USER;
        $params = self::validate_parameters(
            self::origin_test_parameters(), [
                'field' => $field,
                'value' => $value,
                'targetsite' => $targetsite,
            ]
        );

        $field = $params['field'];
        $value = $params['value'];
        $targetsite = $params['targetsite'];

        $errors = [];
        $data = new stdClass();

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $verifytarget = coursetransfer::verify_target_site($targetsite);
                if ($verifytarget['success']) {
                    $sitetarget = coursetransfer::get_site_by_url($targetsite, 'target');
                    $request = new request($sitetarget);
                    $res = $request->site_target_test($USER);
                    if ($res->success) {
                        $success = true;
                    } else {
                        $success = false;
                        $errors = $res->errors;
                    }
                } else {
                    $success = false;
                    $errors[] = $verifytarget['error'];
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] = [
                       'code' => '20021',
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
     * Origin test returns.
     *
     * @return external_single_structure
     */
    public static function origin_test_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                        [
                            'code' => new external_value(PARAM_TEXT, 'Code'),
                            'msg' => new external_value(PARAM_TEXT, 'Message'),
                        ], PARAM_TEXT, 'Errors'
                )),
            ]
        );
    }

    /**
     * Target test parameters.
     *
     * @return external_function_parameters
     */
    public static function target_test_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'field' => new external_value(PARAM_ALPHANUMEXT, 'Field'),
                'value' => new external_value(PARAM_RAW, 'Value'),
            ]
        );
    }

    /**
     * Target test.
     *
     * @param string $field
     * @param string $value
     *
     *
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function target_test(string $field, string $value): array {

        $params = self::validate_parameters(
            self::target_test_parameters(), [
                'field' => $field,
                'value' => $value,
            ]
        );

        $field = $params['field'];
        $value = $params['value'];

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
     * Target test returns.
     *
     * @return external_single_structure
     */
    public static function target_test_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message'),
                    ], PARAM_TEXT, 'Errors'
                )),
            ]
        );
    }

};
