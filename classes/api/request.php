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

    /**
     * constructor
     *
     * @param string $host
     */
    public function __construct(string $host) {
        $this->host = $host;
        $this->set_token();
    }

    protected function set_token() {
        $originsites = get_config('local_coursetransfer', 'origin_sites');
        $originsites = explode(PHP_EOL, $originsites);
        foreach ($originsites as $site) {
            $site = explode(';', $site);
            if ($site[0] === $this->host) {
                $this->token = $site[1];
                break;
            }
        }
    }

    /**
     * Origen Has User?
     *
     * @return response
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
     */
    public function origin_get_courses(): response {
        global $USER;
        $params = new stdClass();
        $params->field = get_config('local_coursetransfer', 'origin_field_search_user');
        $params->value = $USER->{$params->field};
        return $this->req('local_coursetransfer_origin_get_courses', $params);
    }

    /**
     * @throws \JsonException
     */
    protected function req(string $wsname, stdClass $params): response {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->host . '/webservice/rest/server.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'wstoken' => $this->token,
                'wsfunction' => $wsname,
                'moodlewsrestformat' => 'json',
                'field' => $params->field,
                'value' => $params->value),
        ));

        $response = curl_exec($curl);
        echo '<pre>';
        var_dump($response);
        die();
        $response = json_decode($response, false, 512, JSON_THROW_ON_ERROR);

        curl_close($curl);
        return new response($response->success, $response->data, $response->errors);
    }
}

