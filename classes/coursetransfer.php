<?php
// This file is part of the local_amnh plugin for Moodle - http://moodle.org/
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
 * coursetransfer
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use local_coursetransfer\api\request;
use local_coursetransfer\api\response;
use stdClass;

class coursetransfer {

    /** @var stdClass Course */
    protected $course;

    /** @var request Request */
    protected $request;

    /**
     * constructor.
     *
     * @param stdClass $course
     */
    public function __construct(stdClass $course) {
        $this->course = $course;
    }

    /**
     * Set Request.
     *
     * @param string $site
     * @param string $token
     */
    public function set_request(string $site, string $token) {
        $this->request = new request($site, $token);
    }

    /**
     * Get Origin Sites.
     *
     * @return array
     */
    public static function get_origin_sites(): array {
        // TODO. Recuperar los sitios de origen.
        $items = [
                'https://universidad1.com',
                'https://universidad2.com',
                'https://universidad3.com',
        ];
        return $items;
    }

    /**
     * Origin Has User?.
     *
     * @param string $site
     * @param string $token
     * @return response
     */
    public function origin_has_user(): response {
        return $this->request->origin_has_user();
    }

    /**
     * Origin Get Courses?.
     *
     * @return stdClass
     */
    public function origin_get_courses(): stdClass {
        return $this->request->origin_get_courses();
    }


}
