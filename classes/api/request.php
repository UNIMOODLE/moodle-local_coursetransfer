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

use curl;
use dml_exception;
use stdClass;
use stored_file;

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

    /**
     * constructor.
     *
     */
    public function __construct($host, $token) {
        $this->host = $host;
        $this->token = $token;
    }

    /**
     * Origen Has User?
     *
     * @return response
     */
    public function origin_has_user(): response {
        $params = new stdClass();
        $params->field = 'sdfdasf';
        $params->value = 'sdfadfdf';
        return $this->req('local_coursetransfer_origin_has_user', $params);
    }

    /**
     * Origen Get courses.
     *
     * @return response
     */
    public function origin_get_courses(): response {
        $params = new stdClass();
        $params->field = 'sdfdasf';
        $params->value = 'sdfadfdf';
        return $this->req('local_coursetransfer_origin_get_courses', $params);
    }

    /**
     * Req
     *
     * @param string $wsname
     * @param stdClass $params
     * @return response
     */
    protected function req(string $wsname, stdClass $params): response {
        $curl = new curl();
        $url = $this->host . '/webservice/rest/server.php';
        $headers = array();
        $headers[] = "Content-type: application/json";
        $curl->setHeader($headers);
        $params->wstoken = $this->token;
        $params->wsname = $wsname;
        try {
            $curl->post($url, json_encode($params), $this->get_options_curl('POST'));
            $response = $curl->getResponse();
            $response = new response($response->success, $response->data);
        } catch (\Exception $e) {
            $response = new response(false, null,
                    [$e->getMessage()]);
        }
        return $response;
    }


    /**
     * Get Options CURL.
     *
     * @param string $method
     * @return array
     */
    private function get_options_curl(string $method): array {
        return [
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_TIMEOUT' => self::TIMEOUT,
                'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
                'CURLOPT_CUSTOMREQUEST' => $method,
                'CURLOPT_SSLVERSION' => CURL_SSLVERSION_TLSv1_2,
        ];
    }

}

