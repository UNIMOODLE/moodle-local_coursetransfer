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
 * Class request
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\api;

use dml_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\models\configuration_course;
use stdClass;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Class request
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request {

    const TIMEOUT = 10;

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
     * Origen Has User?
     *
     * @return response
     * @throws dml_exception
     */
    public function origin_has_user(): response {
        global $USER;
        $params = [];
        $params['field'] = get_config('local_coursetransfer', 'origin_field_search_user');
        $params['value'] = $USER->{$params['field']};
        return $this->req('local_coursetransfer_origin_has_user', $params);
    }

    /**
     * Origen Get courses.
     *
     * @return response
     * @throws dml_exception
     */
    public function origin_get_categories(): response {
        global $USER;
        $params = [];
        $params['field'] = get_config('local_coursetransfer', 'origin_field_search_user');
        $params['value'] = $USER->{$params['field']};
        return $this->req('local_coursetransfer_origin_get_categories', $params);
    }


    /**
     * Origen Get courses.
     *
     * @return response
     * @throws dml_exception
     */
    public function origin_get_courses(): response {
        global $USER;
        $params = [];
        $params['field'] = get_config('local_coursetransfer', 'origin_field_search_user');
        $params['value'] = $USER->{$params['field']};
        return $this->req('local_coursetransfer_origin_get_courses', $params);
    }

    /**
     * Origen Get course detail.
     *
     * @param int $courseid
     * @return response
     * @throws dml_exception
     */
    public function origin_get_course_detail(int $courseid): response {
        global $USER;
        $params = [];
        $params['field'] = get_config('local_coursetransfer', 'origin_field_search_user');
        $params['value'] = $USER->{$params['field']};
        $params['courseid'] = $courseid;
        return $this->req('local_coursetransfer_origin_get_course_detail', $params);
    }

    /**
     * Origen Get category detail.
     *
     * @param int $categoryid
     * @return response
     * @throws dml_exception
     */
    public function origin_get_category_detail(int $categoryid): response {
        global $USER;
        $params = [];
        $params['field'] = get_config('local_coursetransfer', 'origin_field_search_user');
        $params['value'] = $USER->{$params['field']};
        $params['categoryid'] = $categoryid;
        return $this->req('local_coursetransfer_origin_get_category_detail', $params);
    }

    /**
     * Origin back up course remote.
     *
     * @param int $requestid
     * @param int $origincourseid
     * @param int $destinycourseid
     * @param configuration_course $configuration
     * @param array $sections If array empty [], all sections and all activities will be backup.
     * @return response
     * @throws dml_exception
     */
    public function origin_backup_course(int $requestid, int $origincourseid, int $destinycourseid,
                 configuration_course $configuration, array $sections =[]): response {
        global $USER, $CFG;
        $params = [];
        $params['field'] = get_config('local_coursetransfer', 'origin_field_search_user');
        $params['value'] = $USER->{$params['field']};
        $params['courseid'] = $origincourseid;
        $params['destinycourseid'] = $destinycourseid;
        $params['requestid'] = $requestid;
        $params['destinysite'] = $CFG->wwwroot;
        $params = array_merge($params, $this->serialize_configuration($configuration));
        $params = array_merge($params, $this->serialize_sections($sections));
        return $this->req('local_coursetransfer_origin_backup_course', $params);
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

    /**
     * Origin back up course remote.
     *
     * @param string $fileurl
     * @param int $requestid
     * @return response
     * @throws dml_exception
     */
    public function destiny_backup_course_completed(string $fileurl, int $requestid): response {
        global $USER;
        $params = [];
        $params['field'] = get_config('local_coursetransfer', 'origin_field_search_user');
        $params['value'] = $USER->{$params['field']};
        $params['requestid'] = $requestid;
        $params['backupsize'] = coursetransfer::get_backup_size();
        $params['fileurl'] = $fileurl;
        return $this->req('local_coursetransfer_destiny_backup_course_completed', $params);
    }

    /**
     * Origin back up course remote.
     *
     * @param int $requestid
     * @param array $result
     * @return response
     * @throws dml_exception
     */
    public function destiny_backup_course_error(int $requestid, array $result): response {
        global $USER;
        $params = [];
        $params['field'] = get_config('local_coursetransfer', 'origin_field_search_user');
        $params['value'] = $USER->{$params['field']};
        $params['requestid'] = $requestid;
        $params['errorcode'] = '200001';
        $params['errormsg'] = json_encode($result);
        return $this->req('local_coursetransfer_destiny_backup_course_error', $params);
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
            if (isset($response->success) && isset($response->data) && isset($response->errors)) {
                return new response($response->success, $response->data, $response->errors);
            } else {
                if (!empty($response->message)) {
                    $message = $response->message;
                } else {
                    $message = get_string('error_not_controlled', 'local_coursetransfer');
                }
                $error = new stdClass();
                $error->code = '200002';
                $error->msg = $message;
                return new response(false, null, [$error]);
            }
        } catch (\Exception $e) {
            $error = new stdClass();
            $error->code = '200003';
            $error->msg = $wsname . ': ' . $e->getMessage();
            return new response(false, null, [$error]);
        }
    }
}

