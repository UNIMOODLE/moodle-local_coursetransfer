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
 * Request.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\api;

use coding_exception;
use dml_exception;
use local_coursetransfer\models\configuration_course;
use stdClass;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Class request
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request {
    /** @var int Timeout */
    const TIMEOUT = 20;

    /** @var string Error code: cURL/transport failure. */
    const ERROR_CURL = '12001';

    /** @var string Error code: unexpected response payload (not the success/errors envelope). */
    const ERROR_UNEXPECTED = '12002';

    /** @var string Error code: response could not be JSON-decoded. */
    const ERROR_DECODE = '12003';

    /** @var string Error code: HTTP error status (4xx/5xx) from the remote. */
    const ERROR_HTTP = '12004';

    /** @var string Error code: empty response body from the remote. */
    const ERROR_EMPTY = '12005';

    /** @var string Error code: request was redirected (3xx) — URL/scheme mismatch. */
    const ERROR_REDIRECT = '12006';

    /** @var string Host */
    public string $host;

    /** @var string Token */
    public string $token;

    /** @var int Timeout */
    public int $timeout;

    /**
     * request constructor.
     *
     * @param stdClass $site
     * @throws dml_exception
     */
    public function __construct(stdClass $site) {
        $this->host = $site->host;
        $this->token = $site->token;
        $this->timeout = empty(get_config('local_coursetransfer', 'request_timeout')) ? self::TIMEOUT :
                (int)get_config('local_coursetransfer', 'request_timeout');
    }

    /**
     * Get Request Params.
     *
     * @param stdClass|null $user
     * @param null $page
     * @param null $perpage
     * @param string $search
     * @return array
     * @throws dml_exception
     */
    protected function get_request_params(?stdClass $user = null, $page = null, $perpage = null, string $search = ''): array {
        global $USER;
        $user = is_null($user) ? $USER : $user;
        $params = [];
        // Note that get_config() returns the value, or false if unset; fall back to 'username'.
        $field = get_config('local_coursetransfer', 'origin_field_search_user') ?: 'username';
        // The configurable field 'userid' is exposed in the UI but the real user property is 'id'.
        $userprop = ($field === 'userid') ? 'id' : $field;
        $value = $user->{$userprop} ?? '';
        $params['field'] = $field;
        $params['value'] = (string)$value;
        if (!empty($perpage)) {
            $params['page'] = $page ?? 0;
            $params['perpage'] = $perpage;
        }
        if (!empty($search)) {
            $params['search'] = $search;
        }
        return $params;
    }

    /**
     * Origen Has User?
     *
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function origin_has_user(?stdClass $user = null): response {
        $params = $this->get_request_params($user);
        return $this->req('local_coursetransfer_origin_has_user', $params);
    }

    /**
     * Origen Get courses.
     *
     * @param stdClass|null $user
     * @param int|null $page
     * @param int|null $perpage
     * @return response
     * @throws dml_exception
     */
    public function origin_get_categories(?stdClass $user = null, ?int $page = null, ?int $perpage = null): response {
        $params = $this->get_request_params($user, $page, $perpage);
        return $this->req('local_coursetransfer_origin_get_categories', $params);
    }

    /**
     * Origen Get courses.
     *
     * @param stdClass|null $user
     * @param int|null $page
     * @param int|null $perpage
     * @param string $search
     * @return response
     * @throws dml_exception
     */
    public function origin_get_courses(
        ?stdClass $user = null,
        ?int $page = null,
        ?int $perpage = null,
        string $search = ''
    ): response {
        $params = $this->get_request_params($user, $page, $perpage, $search);
        return $this->req('local_coursetransfer_origin_get_courses', $params);
    }

    /**
     * Origin get courses by ids.
     *
     * @param array $courseids
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function origin_get_courses_by_ids(array $courseids, ?stdClass $user = null): response {
        $params = $this->get_request_params($user);
        $params['courseids'] = json_encode($courseids);
        return $this->req('local_coursetransfer_origin_get_courses_by_ids', $params);
    }

    /**
     * Origen Get course detail.
     *
     * @param int $courseid
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function origin_get_course_detail(int $courseid, ?stdClass $user = null): response {
        $params = $this->get_request_params($user);
        $params['courseid'] = $courseid;
        return $this->req('local_coursetransfer_origin_get_course_detail', $params);
    }

    /**
     * Origen Get category detail.
     *
     * @param int $categoryid
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function origin_get_category_detail(int $categoryid, ?stdClass $user = null): response {
        $params = $this->get_request_params($user);
        $params['categoryid'] = $categoryid;
        return $this->req('local_coursetransfer_origin_get_category_detail', $params);
    }

    /**
     * Origin get category detail tree.
     *
     * Retrieves the full category subtree (nested categories + courses) from the origin,
     * used by the restore wizard to preview and import preserving the hierarchy.
     *
     * @param int $categoryid
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function origin_get_category_detail_tree(int $categoryid, ?stdClass $user = null): response {
        $params = $this->get_request_params($user);
        $params['categoryid'] = $categoryid;
        return $this->req('local_coursetransfer_origin_get_category_detail_tree', $params);
    }

    /**
     * Origin back up course remote.
     *
     * @param stdClass $user
     * @param int $requestid
     * @param int $origincourseid
     * @param int $targetcourseid
     * @param configuration_course $configuration
     * @param array $sections If array empty [], all sections and all activities will be backup.
     * @return response
     * @throws dml_exception
     */
    public function origin_backup_course(
        stdClass $user,
        int $requestid,
        int $origincourseid,
        int $targetcourseid,
        configuration_course $configuration,
        array $sections = []
    ): response {
        global $CFG;
        $params = $this->get_request_params($user);
        $params['courseid'] = $origincourseid;
        $params['targetcourseid'] = $targetcourseid;
        $params['requestid'] = $requestid;
        $params['targetsite'] = $CFG->wwwroot;
        $params = array_merge($params, $this->serialize_configuration($configuration));
        $params = array_merge($params, $this->serialize_sections($sections));
        return $this->req('local_coursetransfer_origin_backup_course', $params);
    }

    /**
     * Target Backup Course Completed.
     *
     * @param string $fileurl
     * @param int $requestid
     * @param int $filesize
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function target_backup_course_completed(
        string $fileurl,
        int $requestid,
        int $filesize,
        ?stdClass $user = null
    ): response {
        $params = $this->get_request_params($user);
        $params['requestid'] = $requestid;
        $params['backupsize'] = $filesize;
        $params['fileurl'] = $fileurl;
        return $this->req('local_coursetransfer_target_backup_course_completed', $params);
    }

    /**
     * Target Remove Course Completed.
     *
     * @param int $requestid
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function target_remove_course_completed(int $requestid, ?stdClass $user = null): response {
        $params = $this->get_request_params($user);
        $params['requestid'] = $requestid;
        return $this->req('local_coursetransfer_target_remove_course_completed', $params);
    }

    /**
     * Origin back up course remote.
     *
     * @param stdClass $user
     * @param int $requestid
     * @param string $error
     * @param array $result
     * @param int $filesize
     * @return response
     * @throws dml_exception
     */
    public function target_backup_course_error(
        stdClass $user,
        int $requestid,
        string $error,
        array $result = [],
        int $filesize = 0
    ): response {
        $params = $this->get_request_params($user);
        $params['requestid'] = $requestid;
        $params['backupsize'] = $filesize;
        $params['errorcode'] = '10201';
        if (empty($result)) {
            $params['errormsg'] = $error;
        } else {
            $params['errormsg'] = json_encode($result);
        }
        return $this->req('local_coursetransfer_target_backup_course_error', $params);
    }

    /**
     * Target Remove Course Error.
     *
     * @param stdClass $user
     * @param int $requestid
     * @param string $error
     * @param string $code
     * @return response
     * @throws dml_exception
     */
    public function target_remove_course_error(
        stdClass $user,
        int $requestid,
        string $error,
        string $code
    ): response {
        $params = $this->get_request_params($user);
        $params['requestid'] = $requestid;
        $params['errorcode'] = $code;
        $params['errormsg'] = $error;
        return $this->req('local_coursetransfer_target_remove_course_error', $params);
    }

    /**
     * Site Origin test.
     *
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function site_origin_test(?stdClass $user = null): response {
        global $CFG;
        $params = $this->get_request_params($user);
        $params['targetsite'] = $CFG->wwwroot;
        return $this->req('local_coursetransfer_site_origin_test', $params);
    }

    /**
     * Site Target test.
     *
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function site_target_test(?stdClass $user = null): response {
        $params = $this->get_request_params($user);
        return $this->req('local_coursetransfer_site_target_test', $params);
    }

    /**
     * Origin remove course remote.
     *
     * @param int $requestid
     * @param int $origincourseid
     * @param int|null $nextruntime
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function origin_remove_course(
        int $requestid,
        int $origincourseid,
        ?int $nextruntime = null,
        ?stdClass $user = null
    ): response {
        global $CFG;
        $params = $this->get_request_params($user);
        $params['courseid'] = $origincourseid;
        $params['requestid'] = $requestid;
        $params['targetsite'] = $CFG->wwwroot;
        $params['nextruntime'] = is_null($nextruntime) ? 0 : $nextruntime;
        return $this->req('local_coursetransfer_origin_remove_course', $params);
    }

    /**
     * Origin remove category remote.
     *
     * @param int $requestid
     * @param int $origincatid
     * @param int|null $nextruntime
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function origin_remove_category(
        int $requestid,
        int $origincatid,
        ?int $nextruntime = null,
        ?stdClass $user = null
    ): response {
        global $CFG;
        $params = $this->get_request_params($user);
        $params['catid'] = $origincatid;
        $params['requestid'] = $requestid;
        $params['targetsite'] = $CFG->wwwroot;
        $params['nextruntime'] = is_null($nextruntime) ? 0 : $nextruntime;
        return $this->req('local_coursetransfer_origin_remove_category', $params);
    }

    /**
     * Request.
     *
     * @param string $wsname
     * @param array $params
     * @return response
     * @throws coding_exception
     */
    protected function req(string $wsname, array $params): response {
        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $wsname;
        $params['moodlewsrestformat'] = 'json';

        try {
            // The peer is an admin-registered platform authenticated by token, so
            // (when enabled) we bypass the cURL security helper, which otherwise
            // blocks internal/loopback/private hosts common in site-to-site setups.
            $ignoresecurity = (bool) get_config('local_coursetransfer', 'ignorecurlsecurity');
            $curl = new \curl(['ignoresecurity' => $ignoresecurity]);
            $raw = $curl->post($this->host . '/webservice/rest/server.php', $params, [
                'CURLOPT_TIMEOUT' => $this->timeout,
                'CURLOPT_CONNECTTIMEOUT' => $this->timeout,
            ]);
            $info = is_array($curl->info) ? $curl->info : [];
            $cerror = $curl->error;
            $cerrno = $curl->errno;
        } catch (\Throwable $e) {
            return $this->error_response(self::ERROR_CURL, $wsname . ': ' . $e->getMessage());
        }

        // Layered error coverage so the admin sees exactly what happened on the
        // remote call (critical for cross-site debugging).
        $httpcode = (int)($info['http_code'] ?? 0);

        // 1. Transport failure: DNS, connection refused, SSL, timeout...
        if ($cerrno !== 0) {
            $this->log_request($wsname, $info, $cerror, $cerrno, $raw);
            return $this->error_response(
                self::ERROR_CURL,
                $wsname . ': ' . get_string(
                    'error_ws_curl',
                    'local_coursetransfer',
                    (object)['msg' => $cerror !== '' ? $cerror : 'cURL', 'errno' => $cerrno]
                )
            );
        }

        // 2. Redirect (3xx): never reached the REST endpoint — usually an http/https,
        // trailing-slash or www mismatch in the registered platform URL.
        if ($httpcode >= 300 && $httpcode < 400) {
            $this->log_request($wsname, $info, $cerror, $cerrno, $raw);
            return $this->error_response(
                self::ERROR_REDIRECT,
                $wsname . ': ' . get_string('error_ws_redirect', 'local_coursetransfer', $httpcode)
            );
        }

        // 3. HTTP error status: endpoint reached but failed (WS disabled, wrong path,
        // remote 5xx...).
        if ($httpcode >= 400) {
            $this->log_request($wsname, $info, $cerror, $cerrno, $raw);
            return $this->error_response(
                self::ERROR_HTTP,
                $wsname . ': ' . get_string('error_ws_http', 'local_coursetransfer', $httpcode)
            );
        }

        // 4. Empty body (often WS REST not enabled on the remote).
        if (trim((string)$raw) === '') {
            $this->log_request($wsname, $info, $cerror, $cerrno, $raw);
            return $this->error_response(
                self::ERROR_EMPTY,
                $wsname . ': ' . get_string('error_ws_empty', 'local_coursetransfer')
            );
        }

        // 5. Non-JSON body.
        try {
            $response = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->log_request($wsname, $info, $cerror, $cerrno, $raw);
            return $this->error_response(
                self::ERROR_DECODE,
                $wsname . ': ' . get_string(
                    'error_ws_decode',
                    'local_coursetransfer',
                    (object)['http' => $httpcode, 'snippet' => mb_substr(trim((string)$raw), 0, 200)]
                )
            );
        }

        // Expected envelope from the remote plugin: { success, errors, data, paging }.
        if (isset($response->success) && isset($response->errors)) {
            return new response(
                $response->success,
                $response->data ?? null,
                $response->errors,
                $response->paging ?? null
            );
        }

        // Anything else (Moodle exception envelope, HTML error page, ...).
        $this->log_request($wsname, $info, $cerror, $cerrno, $response);
        return $this->error_response(self::ERROR_UNEXPECTED, $wsname . ' - ' . $this->extract_message($response));
    }

    /**
     * Build a failed response carrying a single error.
     *
     * @param string $code
     * @param string $msg
     * @return response
     */
    private function error_response(string $code, string $msg): response {
        $error = new stdClass();
        $error->code = $code;
        $error->msg = $msg;
        return new response(false, null, [$error]);
    }

    /**
     * Extract a human-readable message from an unexpected remote response.
     *
     * @param mixed $response Decoded response (object, array or scalar).
     * @return string
     * @throws coding_exception
     */
    private function extract_message(mixed $response): string {
        $message = '';
        if (is_object($response)) {
            if (!empty($response->message)) {
                $message = $response->message;
            } else if (!empty($response->exception)) {
                $message = $response->exception;
            } else if (!empty($response->msg)) {
                $message = $response->msg;
            }
        }
        if ($message === '') {
            $message = get_string('error_not_controlled', 'local_coursetransfer');
        }
        if (is_object($response) && !empty($response->debuginfo)) {
            $message .= ' [' . $response->debuginfo . ']';
        }
        if (is_object($response) && !empty($response->errorcode)) {
            $message .= ' (errorcode: ' . $response->errorcode . ')';
        }
        return $message;
    }

    /**
     * Log request diagnostics (developer debugging only, never shown to users).
     *
     * @param string $wsname
     * @param array $info curl_getinfo() output
     * @param string $cerror
     * @param int $cerrno
     * @param mixed $payload raw or decoded response
     */
    private function log_request(string $wsname, array $info, string $cerror, int $cerrno, mixed $payload): void {
        debugging('API Request ' . $wsname . ' - payload: ' . json_encode($payload));
        debugging('API Request Info: ' . json_encode($info));
        debugging('API Request Error: ' . json_encode($cerror) . ' - ' . json_encode($cerrno));
    }

    /**
     * Serialize Configuration.
     *
     * @param configuration_course $configuration $configuration
     * @return array
     */
    public function serialize_configuration(configuration_course $configuration): array {
        $res = [];
        $res['configuration[target_target]'] = (int)$configuration->targettarget;
        $res['configuration[target_remove_enrols]'] = (int)$configuration->targetremoveenrols;
        $res['configuration[target_remove_groups]'] = (int)$configuration->targetremovegroups;
        $res['configuration[origin_remove_course]'] = (int)$configuration->originremovecourse;
        $res['configuration[origin_enrol_users]'] = (int)$configuration->originenrolusers;
        $res['configuration[target_notremove_activities]'] = $configuration->targetnotremoveactivities;
        $res['configuration[nextruntime]'] = (int)$configuration->nextruntime;
        return $res;
    }

    /**
     * Serializce Sections.
     *
     * @param array $sections
     * @return array
     */
    public function serialize_sections(array $sections): array {
        $res = [];
        $sectionindex = 0;
        foreach ($sections as $section) {
            $activitiesindex = 1;
            foreach ($section as $key => $param) {
                if ($key === 'activities') {
                    foreach ($param as $activity) {
                        foreach ($activity as $act => $actparams) {
                            $insertparamact = $actparams;
                            if ($act === 'selected') {
                                $insertparamact = (string)(int)$actparams;
                            }
                            $res['sections[' . $sectionindex .
                            '][activities][' . $activitiesindex . '][' . $act . ']'] = $insertparamact;
                        }
                        ++$activitiesindex;
                    }
                } else {
                    $insertparamsec = $param;
                    if ($key === 'selected') {
                        $insertparamsec = (string)(int)$param;
                    }
                    $res['sections[' . $sectionindex . '][' . $key . ']'] = $insertparamsec;
                }
            }
            ++$sectionindex;
        }
        return $res;
    }
}
