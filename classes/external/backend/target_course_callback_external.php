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
 * Target Course Callback Externa.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external\backend;

use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_download;
use local_coursetransfer\coursetransfer_notification;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_sites;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/local/coursetransfer/classes/task/download_file_course_task.php');

/**
 * Class target_course_callback_external
 *
 * Status callbacks the origin sends to the target once it has run the backup or the removal
 * the target asked for. Each callback only applies to a request made from this site
 * (direction request) of its own type, and only from the statuses where the transition
 * makes sense. A repeated call (network retry, origin task run twice) does not queue the
 * download again nor overwrite the result already recorded.
 *
 * Allowed transitions (request statuses as in coursetransfer_request::STATUS_*):
 *
 * - target_backup_course_completed, on a course request:
 *   not started / in progress -> backup, and the download is queued, only when fileurl has the
 *   path of a backup served by this plugin; it is rebuilt on the registered host of the origin
 *   (else rejected with 10303, see origin_backup_url());
 *   backup / download / downloaded / restore / completed -> success, nothing changes;
 *   error or anything else -> rejected with 10302.
 * - target_backup_course_error, on a course request:
 *   not started / in progress -> error, with the origin's code and message;
 *   error -> success, the error already recorded is kept;
 *   backup and later -> rejected with 14003.
 * - target_remove_course_completed, on a remove course or remove category request:
 *   not started / in progress / error (*) -> completed, the owner is notified and request_completed fired
 *   (from error, the earlier error is kept as a note);
 *   completed -> success, nothing changes;
 *   anything else -> rejected with 14023.
 * - target_remove_course_error, on a remove course or remove category request:
 *   not started / in progress -> error, with the origin's code and message;
 *   error -> success, the error already recorded is kept;
 *   completed -> rejected with 14013.
 *
 * A wrong type or direction is rejected with the same code. The origin stores a rejection as
 * an error on its own request. (*) When the remove completed callback arrives, the origin has
 * already deleted the course or category, whatever the target recorded (typically the response
 * to origin_remove_* was lost and the target marked an error): an error answer would only turn
 * the origin's completed request into an error. A backup completed on a request in error is
 * rejected instead: that request was given up or relaunched (restore_retry), and restoring it
 * would duplicate the restore.
 *
 * @package local_coursetransfer\external\backend
 */
class target_course_callback_external extends backend_api {
    /** @var string The callback applies: change the status. */
    const TRANSITION_APPLY = 'apply';

    /** @var string The callback repeats a result already recorded: answer success, change nothing. */
    const TRANSITION_DUPLICATE = 'duplicate';

    /** @var string The callback does not apply to the request: answer an error, change nothing. */
    const TRANSITION_REJECT = 'reject';

    /**
     * Decide what a callback does with a request, following the table in the class docblock.
     *
     * @param stdClass $request
     * @param int[] $types Request types the callback applies to.
     * @param int[] $applyfrom Statuses the callback moves on.
     * @param int[] $duplicatefrom Statuses where the callback is answered with success and no change.
     * @return string One of the TRANSITION_* constants.
     */
    protected static function check_transition(
        stdClass $request,
        array $types,
        array $applyfrom,
        array $duplicatefrom
    ): string {
        if (
            !in_array((int)$request->type, $types, true)
                || (int)$request->direction !== coursetransfer_request::DIRECTION_REQUEST
        ) {
            return self::TRANSITION_REJECT;
        }
        $status = (int)$request->status;
        if (in_array($status, $applyfrom, true)) {
            return self::TRANSITION_APPLY;
        }
        if (in_array($status, $duplicatefrom, true)) {
            return self::TRANSITION_DUPLICATE;
        }
        return self::TRANSITION_REJECT;
    }

    /**
     * The backup URL to download: the registered host of the origin plus the file path the origin sent.
     *
     * The download sends the origin's token to this URL, so its host is never taken from the caller: it is
     * the host this site registered for the origin (request siteurl). From the URL sent (built by the origin
     * with make_webservice_pluginfile_url() from its wwwroot, which may be an alias of the registered host)
     * only the file path is kept, and only when it is exactly /<context>/local_coursetransfer/backup/<id>/backup.mbz
     * under /webservice/pluginfile.php: digits only, so no dot segments, encodings, query nor fragment.
     *
     * @param string $fileurl URL sent by the origin.
     * @param string $siteurl Origin host stored in the request.
     * @return string|null Null when the URL is not a backup served by this plugin (rejected with 10303).
     */
    protected static function origin_backup_url(string $fileurl, string $siteurl): ?string {
        if ($fileurl === '' || strpbrk($fileurl, "?#\\ \t\r\n") !== false) {
            return null;
        }
        $scheme = strtolower((string)parse_url($fileurl, PHP_URL_SCHEME));
        $path = parse_url($fileurl, PHP_URL_PATH);
        if (!in_array($scheme, ['http', 'https'], true) || !is_string($path)) {
            return null;
        }
        $marker = '/webservice/pluginfile.php';
        $pos = strpos($path, $marker . '/');
        $tail = $pos === false ? '' : substr($path, $pos + strlen($marker));
        if (!preg_match('~^/[0-9]+/local_coursetransfer/backup/[0-9]+/backup\.mbz$~D', $tail)) {
            return null;
        }
        $site = coursetransfer_sites::clean_host(trim($siteurl));
        $sitescheme = strtolower((string)parse_url($site, PHP_URL_SCHEME));
        if (!in_array($sitescheme, ['http', 'https'], true) || (string)parse_url($site, PHP_URL_HOST) === '') {
            return null;
        }
        return $site . $marker . $tail;
    }

    /**
     * Error entry for a callback that does not apply to the request.
     *
     * @param string $code
     * @param string $callback
     * @param stdClass $request
     * @return array
     */
    protected static function transition_error(string $code, string $callback, stdClass $request): array {
        return [
            'code' => $code,
            'msg' => 'Request ' . $request->id . ' does not accept ' . $callback . ' (type ' . (int)$request->type
                . ', direction ' . (int)$request->direction . ', status ' . (int)$request->status . ')',
        ];
    }
    /**
     * Target Backup Course Completed Parameters.
     *
     * @return external_function_parameters
     */
    public static function target_backup_course_completed_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'requestid' => new external_value(PARAM_INT, 'Request ID'),
                'backupsize' => new external_value(PARAM_INT, 'Backup Size (Bytes)'),
                'fileurl' => new external_value(PARAM_RAW, 'File URL'),
            ]
        );
    }

    /**
     * Target Backup Course Completed.
     *
     * @param string $field
     * @param string $value
     * @param int $requestid
     * @param int $backupsize
     * @param string $fileurl
     *
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function target_backup_course_completed(
        string $field,
        string $value,
        int $requestid,
        int $backupsize,
        string $fileurl
    ): array {

        $params = self::validate_parameters(
            self::target_backup_course_completed_parameters(),
            [
                'field' => $field,
                'value' => $value,
                'requestid' => $requestid,
                'backupsize' => $backupsize,
                'fileurl' => $fileurl,
            ]
        );
        self::validate_service_user();

        $field = $params['field'];
        $value = $params['value'];
        $requestid = $params['requestid'];
        $backupsize = $params['backupsize'];
        $fileurl = $params['fileurl'];

        $errors = [];
        $data = new stdClass();
        $data->id = 0;

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $request = coursetransfer_request::get($requestid);
                $transition = $request ? self::check_transition(
                    $request,
                    [coursetransfer_request::TYPE_COURSE],
                    [coursetransfer_request::STATUS_NOT_STARTED, coursetransfer_request::STATUS_IN_PROGRESS],
                    [
                        coursetransfer_request::STATUS_BACKUP,
                        coursetransfer_request::STATUS_DOWNLOAD,
                        coursetransfer_request::STATUS_DOWNLOADED,
                        coursetransfer_request::STATUS_RESTORE,
                        coursetransfer_request::STATUS_COMPLETED,
                    ]
                ) : null;
                $safeurl = $request ? self::origin_backup_url($fileurl, (string)$request->siteurl) : null;
                if ($transition === self::TRANSITION_DUPLICATE) {
                    // The download is already queued or done: queuing it again would restore twice (LCT-051).
                    $data->id = $request->id;
                    $success = true;
                } else if ($transition === self::TRANSITION_REJECT) {
                    $success = false;
                    $errors[] = self::transition_error('10302', 'target_backup_course_completed', $request);
                } else if ($request && $safeurl === null) {
                    // The origin's token goes with this URL: only a backup file of this plugin is accepted.
                    $success = false;
                    $errors[] = [
                        'code' => '10303',
                        'msg' => 'Backup file URL is not a web service file of the origin site of request ' . $request->id,
                    ];
                } else if ($request) {
                    $fileurl = $safeurl;
                    // The origin must be registered; its token is not stored with the URL: the download task
                    // reads it when it runs and sends it in the body (LCT-011).
                    coursetransfer_sites::get_by_host('origin', $request->siteurl);
                    $request->status = coursetransfer_request::STATUS_BACKUP;
                    $request->origin_backup_size = $backupsize;
                    $request->origin_backup_url = $fileurl;
                    $request->fileurl = $fileurl;
                    coursetransfer_request::insert_or_update($request, $requestid);
                    coursetransfer_download::create_task_download_course($request, $fileurl);
                    $data->id = $request->id;
                    $success = true;
                } else {
                    $success = false;
                    $errors[] =
                        [
                            'code' => '10301',
                            'msg' => 'Request id not found: ' . $requestid,
                        ];
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '10300',
                    'msg' => $e->getMessage(),
                ];
        }

        return [
            'success' => $success,
            'data' => $data,
            'errors' => $errors,
        ];
    }

    /**
     * Target Backup Course Completed Returns.
     *
     * @return external_single_structure
     */
    public static function target_backup_course_completed_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'data' => new external_single_structure(
                    [
                                'id' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL),
                        ]
                ),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message'),
                    ],
                    'Errors',
                    VALUE_OPTIONAL
                )),
            ]
        );
    }

    /**
     * Target Remove Course Completed parameters.
     *
     * @return external_function_parameters
     */
    public static function target_remove_course_completed_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'requestid' => new external_value(PARAM_INT, 'Request ID'),
            ]
        );
    }

    /**
     * Target Remove Course Completed.
     *
     * @param string $field
     * @param string $value
     * @param int $requestid
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function target_remove_course_completed(string $field, string $value, int $requestid): array {

        $params = self::validate_parameters(
            self::target_remove_course_completed_parameters(),
            [
                'field' => $field,
                'value' => $value,
                'requestid' => $requestid,
            ]
        );
        self::validate_service_user();

        $field = $params['field'];
        $value = $params['value'];
        $requestid = $params['requestid'];

        $errors = [];
        $data = new stdClass();
        $data->id = 0;

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $request = coursetransfer_request::get($requestid);
                $transition = $request ? self::check_transition(
                    $request,
                    [coursetransfer_request::TYPE_REMOVE_COURSE, coursetransfer_request::TYPE_REMOVE_CATEGORY],
                    [coursetransfer_request::STATUS_NOT_STARTED, coursetransfer_request::STATUS_IN_PROGRESS],
                    [coursetransfer_request::STATUS_COMPLETED, coursetransfer_request::STATUS_ERROR]
                ) : null;
                $recordederror = $request && (int)$request->status === coursetransfer_request::STATUS_ERROR;
                if ($transition === self::TRANSITION_DUPLICATE && !$recordederror) {
                    // Already completed: no second notification or event.
                    $data->id = $request->id;
                    $success = true;
                } else if ($transition === self::TRANSITION_REJECT) {
                    $success = false;
                    $errors[] = self::transition_error('14023', 'target_remove_course_completed', $request);
                } else if ($request) {
                    if ($recordederror) {
                        // The origin did remove it (its answer was lost and this site recorded an error): record what
                        // happened, keeping the earlier error as a note (C3).
                        $request->error_message = 'Removed by the origin after an earlier error: '
                            . trim(($request->error_code ?? '') . ' ' . ($request->error_message ?? ''));
                        $request->error_code = null;
                    }
                    $request->status = coursetransfer_request::STATUS_COMPLETED;
                    coursetransfer_request::insert_or_update($request, $requestid);
                    if ((int)$request->type === coursetransfer_request::TYPE_REMOVE_COURSE) {
                        coursetransfer_notification::send_remove_course_completed($request->userid, $request->origin_course_id);
                    } else if ((int)$request->type === coursetransfer_request::TYPE_REMOVE_CATEGORY) {
                        coursetransfer_notification::send_remove_category_completed($request->userid, $request->origin_category_id);
                    }
                    coursetransfer_request::trigger_request_completed($request);
                    $data->id = $request->id;
                    $success = true;
                } else {
                    $success = false;
                    $errors[] =
                        [
                            'code' => '14022',
                            'msg' => 'Request id not found: ' . $requestid,
                        ];
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '14021',
                    'msg' => $e->getMessage(),
                ];
        }

        return [
            'success' => $success,
            'data' => $data,
            'errors' => $errors,
        ];
    }

    /**
     * Target Remove Course Completed returns.
     *
     * @return external_single_structure
     */
    public static function target_remove_course_completed_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'data' => new external_single_structure(
                    [
                                'id' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL),
                        ]
                ),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message'),
                    ],
                    'Errors',
                    VALUE_OPTIONAL
                )),
            ]
        );
    }

    /**
     * Target Backup course error parameters.
     *
     * @return external_function_parameters
     */
    public static function target_backup_course_error_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'requestid' => new external_value(PARAM_INT, 'Request ID'),
                'backupsize' => new external_value(PARAM_INT, 'Backup Size (Bytes)'),
                'errorcode' => new external_value(PARAM_INT, 'Error Code'),
                'errormsg' => new external_value(PARAM_TEXT, 'Error Message'),
            ]
        );
    }

    /**
     * Target Backup course error.
     *
     * @param string $field
     * @param string $value
     * @param int $requestid
     * @param int $backupsize
     * @param int $errorcode
     * @param string $errormsg
     *
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function target_backup_course_error(
        string $field,
        string $value,
        int $requestid,
        int $backupsize,
        int $errorcode,
        string $errormsg
    ): array {
        $params = self::validate_parameters(
            self::target_backup_course_error_parameters(),
            [
                'field' => $field,
                'value' => $value,
                'requestid' => $requestid,
                'backupsize' => $backupsize,
                'errorcode' => $errorcode,
                'errormsg' => $errormsg,
            ]
        );
        self::validate_service_user();

        $field = $params['field'];
        $value = $params['value'];
        $requestid = $params['requestid'];
        $backupsize = $params['backupsize'];
        $errorcode = $params['errorcode'];
        $errormsg = $params['errormsg'];

        $data = new stdClass();
        $data->id = 0;
        $errors = [];

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $request = coursetransfer_request::get($requestid);
                $transition = $request ? self::check_transition(
                    $request,
                    [coursetransfer_request::TYPE_COURSE],
                    [coursetransfer_request::STATUS_NOT_STARTED, coursetransfer_request::STATUS_IN_PROGRESS],
                    [coursetransfer_request::STATUS_ERROR]
                ) : null;
                if ($transition === self::TRANSITION_DUPLICATE) {
                    // Keep the error already recorded (or the relaunch note left by restore_retry).
                    $data->id = $requestid;
                    $success = true;
                } else if ($transition === self::TRANSITION_REJECT) {
                    $success = false;
                    $errors[] = self::transition_error('14003', 'target_backup_course_error', $request);
                } else if ($request) {
                    $request->origin_backup_size = $backupsize;
                    $request->error_code = $errorcode;
                    $request->error_message = $errormsg;
                    $request->status = coursetransfer_request::STATUS_ERROR;
                    coursetransfer_request::insert_or_update($request, $requestid);
                    $data->id = $requestid;
                    $success = true;
                } else {
                    $success = false;
                    $errors[] =
                        [
                            'code' => '14002',
                            'msg' => 'Request id not found: ' . $requestid,
                        ];
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '14001',
                    'msg' => $e->getMessage(),
                ];
        }

        return [
                'success' => $success,
                'data' => $data,
                'errors' => $errors,
        ];
    }

    /**
     * Target Backup course error returns.
     *
     * @return external_single_structure
     */
    public static function target_backup_course_error_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'data' => new external_single_structure(
                    [
                                'id' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL),
                        ]
                ),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_INT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message'),
                    ],
                    'Errors',
                    VALUE_OPTIONAL
                )),
            ]
        );
    }

    /**
     * Target Remove Course Error parameters.
     *
     * @return external_function_parameters
     */
    public static function target_remove_course_error_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'field' => new external_value(PARAM_TEXT, 'Field'),
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'requestid' => new external_value(PARAM_INT, 'Request ID'),
                'errorcode' => new external_value(PARAM_INT, 'Error Code'),
                'errormsg' => new external_value(PARAM_TEXT, 'Error Message'),
            ]
        );
    }

    /**
     * Target Remove Course Error.
     *
     * @param string $field
     * @param string $value
     * @param int $requestid
     * @param int $errorcode
     * @param string $errormsg
     *
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function target_remove_course_error(
        string $field,
        string $value,
        int $requestid,
        int $errorcode,
        string $errormsg
    ): array {
        $params = self::validate_parameters(
            self::target_remove_course_error_parameters(),
            [
                'field' => $field,
                'value' => $value,
                'requestid' => $requestid,
                'errorcode' => $errorcode,
                'errormsg' => $errormsg,
            ]
        );
        self::validate_service_user();

        $field = $params['field'];
        $value = $params['value'];
        $requestid = $params['requestid'];
        $errorcode = $params['errorcode'];
        $errormsg = $params['errormsg'];

        $data = new stdClass();
        $data->id = 0;
        $errors = [];

        try {
            $authres = coursetransfer::auth_user($field, $value);
            if ($authres['success']) {
                $request = coursetransfer_request::get($requestid);
                $transition = $request ? self::check_transition(
                    $request,
                    [coursetransfer_request::TYPE_REMOVE_COURSE, coursetransfer_request::TYPE_REMOVE_CATEGORY],
                    [coursetransfer_request::STATUS_NOT_STARTED, coursetransfer_request::STATUS_IN_PROGRESS],
                    [coursetransfer_request::STATUS_ERROR]
                ) : null;
                if ($transition === self::TRANSITION_DUPLICATE) {
                    // Keep the error already recorded.
                    $data->id = $requestid;
                    $success = true;
                } else if ($transition === self::TRANSITION_REJECT) {
                    $success = false;
                    $errors[] = self::transition_error('14013', 'target_remove_course_error', $request);
                } else if ($request) {
                    $request->error_code = $errorcode;
                    $request->error_message = $errormsg;
                    $request->status = coursetransfer_request::STATUS_ERROR;
                    coursetransfer_request::insert_or_update($request, $requestid);
                    $data->id = $requestid;
                    $success = true;
                } else {
                    $success = false;
                    $errors[] =
                        [
                            'code' => '14012',
                            'msg' => 'Request id not found: ' . $requestid,
                        ];
                }
            } else {
                $success = false;
                $errors[] = $authres['error'];
            }
        } catch (moodle_exception $e) {
            $success = false;
            $errors[] =
                [
                    'code' => '14011',
                    'msg' => $e->getMessage(),
                ];
        }

        return [
                'success' => $success,
                'data' => $data,
                'errors' => $errors,
        ];
    }

    /**
     * Target Remove Course Error returns.
     *
     * @return external_single_structure
     */
    public static function target_remove_course_error_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'data' => new external_single_structure(
                    [
                                'id' => new external_value(PARAM_INT, 'Request ID', VALUE_OPTIONAL),
                        ]
                ),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_INT, 'Code'),
                        'msg' => new external_value(PARAM_TEXT, 'Message'),
                    ],
                    'Errors',
                    VALUE_OPTIONAL
                )),
            ]
        );
    }
}
