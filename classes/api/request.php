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

    const TIMEOUT = 20;

    /** @var string Host */
    public $host;

    /** @var string Token */
    public $token;

    /** @var array Origin Sites */
    public $originsites;

    /**
     * request constructor.
     *
     * @param stdClass $site
     */
    public function __construct(stdClass $site) {
        $this->host = $site->host;
        $this->token = $site->token;
    }

    /**
     * Get Request Params.
     *
     * @param stdClass $user
     * @return array
     * @throws dml_exception
     */
    protected function get_request_params(stdClass $user): array {
        global $USER;
        $user = is_null($user) ? $USER : $user;
        $params = [];
        $params['field'] = get_config('local_coursetransfer', 'origin_field_search_user');
        $params['value'] = $user->{$params['field']};
        return $params;
    }

    /**
     * Origen Has User?
     *
     * @param stdClass|null $user
     * @return response
     * @throws coding_exception
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
     * @return response
     * @throws coding_exception
     * @throws dml_exception
     */
    public function origin_get_categories(stdClass $user = null): response {
        $params = $this->get_request_params($user);
        return $this->req('local_coursetransfer_origin_get_categories', $params);
    }

    /**
     * Origen Get courses.
     *
     * @param stdClass|null $user
     * @return response
     * @throws coding_exception
     * @throws dml_exception
     */
    public function origin_get_courses(stdClass $user = null): response {
        $params = $this->get_request_params($user);
        return $this->req('local_coursetransfer_origin_get_courses', $params);
    }

    /**
     * Origen Get course detail.
     *
     * @param int $courseid
     * @param stdClass|null $user
     * @return response
     * @throws coding_exception
     * @throws dml_exception
     */
    public function origin_get_course_detail(int $courseid, stdClass $user = null): response {
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
     * @throws coding_exception
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
     * @param int $destinycourseid
     * @param configuration_course $configuration
     * @param array $sections If array empty [], all sections and all activities will be backup.
     * @return response
     * @throws coding_exception
     * @throws dml_exception
     */
    public function origin_backup_course(stdClass $user, int $requestid, int $origincourseid, int $destinycourseid,
                 configuration_course $configuration, array $sections =[]): response {
        global $CFG;
        $params = $this->get_request_params($user);
        $params['courseid'] = $origincourseid;
        $params['destinycourseid'] = $destinycourseid;
        $params['requestid'] = $requestid;
        $params['destinysite'] = $CFG->wwwroot;
        $params = array_merge($params, $this->serialize_configuration($configuration));
        $params = array_merge($params, $this->serialize_sections($sections));
        return $this->req('local_coursetransfer_origin_backup_course', $params);
    }

    /**
     * Origin back up course remote.
     *
     * @param string $fileurl
     * @param int $requestid
     * @param int $filesize
     * @param stdClass|null $user
     * @return response
     * @throws coding_exception
     * @throws dml_exception
     */
    public function destiny_backup_course_completed(
            string $fileurl, int $requestid, int $filesize, stdClass $user = null): response {
        $params = $this->get_request_params($user);
        $params['requestid'] = $requestid;
        $params['backupsize'] = $filesize;
        $params['fileurl'] = $fileurl;
        return $this->req('local_coursetransfer_destiny_backup_course_completed', $params);
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
     * @throws coding_exception
     * @throws dml_exception
     */
    public function destiny_backup_course_error(
            stdClass $user, int $requestid, string $error, array $result = [], $filesize = 0): response {
        $params = $this->get_request_params($user);
        $params['requestid'] = $requestid;
        $params['backupsize'] = $filesize;
        $params['errorcode'] = '120003';
        if (empty($result)) {
            $params['errormsg'] = $error;
        } else {
            $params['errormsg'] = json_encode($result);
        }
        return $this->req('local_coursetransfer_destiny_backup_course_error', $params);
    }

    /**
     * Site Origin test.
     *
     * @param stdClass|null $user
     * @return response
     * @throws coding_exception
     * @throws dml_exception
     */
    public function site_origin_test(stdClass $user = null): response {
        global $CFG;
        $params = $this->get_request_params($user);
        $params['destinysite'] = $CFG->wwwroot;
        return $this->req('local_coursetransfer_site_origin_test', $params);
    }

    /**
     * Site Destiny test.
     *
     * @param stdClass|null $user
     * @return response
     * @throws coding_exception
     * @throws dml_exception
     */
    public function site_destiny_test(stdClass $user = null): response {
        $params = $this->get_request_params($user);
        return $this->req('local_coursetransfer_site_destiny_test', $params);
    }

    /**
     * Origin remove course remote.
     *
     * @param int $requestid
     * @param int $origincourseid
     * @param stdClass|null $user
     * @return response
     * @throws coding_exception
     * @throws dml_exception
     */
    public function origin_remove_course(int $requestid, int $origincourseid, stdClass $user = null): response {
        global $CFG;
        $params = $this->get_request_params($user);
        $params['courseid'] = $origincourseid;
        $params['requestid'] = $requestid;
        $params['destinysite'] = $CFG->wwwroot;
        return $this->req('local_coursetransfer_origin_remove_course', $params);
    }

    /**
     * Origin remove category remote.
     *
     * @param int $requestid
     * @param int $origincatid
     * @param stdClass|null $user
     * @return response
     * @throws coding_exception
     * @throws dml_exception
     */
    public function origin_remove_category(int $requestid, int $origincatid, stdClass $user = null): response {
        global $CFG;
        $params = $this->get_request_params($user);
        $params['catid'] = $origincatid;
        $params['requestid'] = $requestid;
        $params['destinysite'] = $CFG->wwwroot;
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
        $curl = curl_init();
        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $wsname;
        $params['moodlewsrestformat'] = 'json';

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->host . '/webservice/rest/server.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $params,
        ));
        $response = curl_exec($curl);
        try {
            $response = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
            curl_close($curl);
            if (isset($response->success) && isset($response->errors)) {
                $data = isset($response->data) ? $response->data : null;
                return new response($response->success, $data, $response->errors);
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
                $error->code = '120002';
                $error->msg = $wsname . ' - ' . $message;
                return new response(false, null, [$error]);
            }
        } catch (\Exception $e) {
            $message = $e->getMessage() === 'Syntax error' ?
                    get_string('site_url_invalid', 'local_coursetransfer') :
                    $e->getMessage();
            $error = new stdClass();
            $error->code = '120001';
            $error->msg = $wsname . ': ' . $message;
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
        $res['configuration[destiny_target]'] = (int)$configuration->destinytarget;
        $res['configuration[destiny_remove_enrols]'] = (int)$configuration->destinyremoveenrols;
        $res['configuration[destiny_remove_groups]'] = (int)$configuration->destinyremovegroups;
        $res['configuration[origin_remove_course]'] = (int)$configuration->originremovecourse;
        $res['configuration[origin_enrol_users]'] = (int)$configuration->originenrolusers;
        $res['configuration[destiny_notremove_activities]'] = $configuration->destinynotremoveactivities;
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

