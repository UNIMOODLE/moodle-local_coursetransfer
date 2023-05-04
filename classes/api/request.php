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

use coding_exception;
use dml_exception;
use moodle_exception;
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
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
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
        $params = new stdClass();
        $params->field = get_config('local_coursetransfer', 'origin_field_search_user');
        $params->value = $USER->{$params->field};
        return $this->req('local_coursetransfer_origin_has_user', $params);
    }

    /**
     * Origen Get courses.
     *
     * @return response
     * @throws dml_exception
     */
    public function origin_get_courses(): response {
        global $USER;
        $params = new stdClass();
        $params->field = get_config('local_coursetransfer', 'origin_field_search_user');
        $params->value = $USER->{$params->field};
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
        $params = new stdClass();
        $params->field = get_config('local_coursetransfer', 'origin_field_search_user');
        $params->value = $USER->{$params->field};
        $params->courseid = $courseid;
        return $this->req('local_coursetransfer_origin_get_course_detail', $params);
    }

    /**
     * Origin back up course remote.
     *
     * @param int $requestid
     * @param int $courseid
     * @return response
     * @throws dml_exception
     */
    public function origin_backup_course(int $requestid, int $courseid): response {
        // TODO. AÑADIR LOS PARÁMETROS NECESARIOS.
        global $USER;
        $params = new stdClass();
        $params->field = get_config('local_coursetransfer', 'origin_field_search_user');
        $params->value = $USER->{$params->field};
        $params->requestid = $requestid;
        $params->courseid = $courseid;
        // EN PARAMS AÑADIR TAMBIÉN TODOS LOS PARÁMETROS.
        return $this->req('local_coursetransfer_origin_backup_course', $params);
    }

    /**
     * Request.
     *
     * @param string $wsname
     * @param stdClass $params
     * @return response
     */
    protected function req(string $wsname, stdClass $params): response {
        $curl = curl_init();
        $params->wstoken = $this->token;
        $params->wsfunction = $wsname;
        $params->moodlewsrestformat = 'json';
        $params = (array)$params;

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
                return new response(false, null, ['code' => '12344', 'msg' => $message]);
            }
        } catch (\Exception $e) {
            return new response(false, null, ['code' => '1561343', 'msg' => $e->getMessage()]);
        }

    }
}

