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

    /** @var string Host */
    public $host;

    /** @var string Token */
    public $token;

    /** @var array Origin Sites */
    public $originsites;

    /** @var int Timeout */
    public $timeout;

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
    protected function get_request_params(stdClass $user = null, $page = null, $perpage = null, string $search = ''): array {
        global $USER;
        $user = is_null($user) ? $USER : $user;
        $params = [];
        $params['field'] = get_config('local_coursetransfer', 'origin_field_search_user');
        $params['value'] = $user->{$params['field']};
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
    public function origin_has_user(stdClass $user = null): response {
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
    public function origin_get_categories(stdClass $user = null, int $page = null, int $perpage = null): response {
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
    public function origin_get_courses(stdClass $user = null, int $page = null,
            int $perpage = null, string $search = ''): response {
        $params = $this->get_request_params($user, $page, $perpage, $search);
        return $this->req('local_coursetransfer_origin_get_courses', $params);
    }

    /**
     * Origen Get courses.
     *
     * @param array $courseids
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function origin_get_courses_by_ids(array $courseids, stdClass $user = null): response {
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
    public function origin_get_course_detail(int $courseid, stdClass $user = null): response {
        $params = $this->get_request_params($user);
        $params['courseid'] = $courseid;
        return $this->req('local_coursetransfer_origin_get_course_detail', $params);
    }

    /**
     * Origen Get course detail.
     *
     * @param array $courseid
     * @param stdClass|null $user
     * @return response
     * @throws dml_exception
     */
    public function origin_get_courses_detail(array $courseid, stdClass $user = null): response {
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
    public function origin_get_category_detail(int $categoryid, stdClass $user = null): response {
        $params = $this->get_request_params($user);
        $params['categoryid'] = $categoryid;
        return $this->req('local_coursetransfer_origin_get_category_detail', $params);
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
    public function origin_backup_course(stdClass $user, int $requestid, int $origincourseid, int $targetcourseid,
                 configuration_course $configuration, array $sections =[]): response {
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
            string $fileurl, int $requestid, int $filesize, stdClass $user = null): response {
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
    public function target_remove_course_completed(int $requestid, stdClass $user = null): response {
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
            stdClass $user, int $requestid, string $error, array $result = [], $filesize = 0): response {
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
            stdClass $user, int $requestid, string $error, string $code): response {
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
    public function site_origin_test(stdClass $user = null): response {
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
    public function site_target_test(stdClass $user = null): response {
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
            int $requestid, int $origincourseid, int $nextruntime = null, stdClass $user = null): response {
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
            int $requestid, int $origincatid, int $nextruntime = null, stdClass $user = null): response {
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
     */
    protected function req(string $wsname, array $params): response {
        $curl = curl_init();
        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $wsname;
        $params['moodlewsrestformat'] = 'json';

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->host . '/webservice/rest/server.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $params,
        ]);
        try {
            $response = curl_exec($curl);
            $info  = curl_getinfo($curl);
            $cerror = curl_error($curl);
            $cerrno = curl_errno($curl);

            try {
                $response = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
                curl_close($curl);
                if (isset($response->success) && isset($response->errors)) {
                    $data = isset($response->data) ? $response->data : null;
                    $paging = $response->paging ?? null;
                    return new response($response->success, $data, $response->errors, $paging);
                } else {
                    if (!empty($response->message)) {
                        $message = $response->message;
                    } else if (!empty($response->exception)) {
                        $message = $response->exception;
                    } else if (!empty($response->msg)) {
                        $message = $response->msg;
                    } else {
                        $message = get_string('error_not_controlled', 'local_coursetransfer');
                    }
                    $error = new stdClass();
                    $error->code = '12002';
                    $error->msg = $wsname . ' - ' . $message;
                    debugging('API Request: ' . json_encode($error) . ' - ' . json_encode($response));
                    debugging('API Request Info: ' . json_encode($info));
                    debugging('API Request Error: ' . json_encode($cerror) . ' - ' . json_encode($cerrno));
                    return new response(false, null, [$error]);
                }
            } catch (\Exception $e) {
                $message = $e->getMessage() . ': ' . $cerror . ' ('. $cerrno . ')';
                $error = new stdClass();
                $error->code = '12003';
                $error->msg = $wsname . ': ' . $message;
                debugging('API Request: ' . json_encode($error) . ' - ' . json_encode($response));
                debugging('API Request Info: ' . json_encode($info));
                debugging('API Request Error: ' . json_encode($cerror) . ' - ' . json_encode($cerrno));
                return new response(false, null, [$error]);
            }
        } catch (\Exception $e) {
            $error = new stdClass();
            $error->code = '12001';
            $error->msg = $wsname . ': ' . $e->getMessage();
            debugging('API Request: ' . $e->getMessage() . ' - ' . json_encode($error));
            return new response(false, null, [$error]);
        }
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
