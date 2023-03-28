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

    const FIELDS_USER = ['username', 'email', 'userid'];

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

    /**
     * @param string $field
     * @param string $value
     * @return array
     */
    public static function auth_user(string $field, string $value): array {
        global $DB;
        //TODO: saber si tiene cursos como professor.

        if (in_array($field, coursetransfer::FIELDS_USER)) {
            $res = $DB->get_record('user', [$field => $value]);
            if ($res) {
                $courses = enrol_get_users_courses($res->id);
                $hascourse = false;
                foreach ($courses as $course){
                    $context = \context_course::instance($course->id);
                    if(has_capability('moodle/backup:backupcourse', $context, $res->id)){
                        $hascourse = true;
                        break;
                    }
                }
                //var_dump($courses);
                if($hascourse){
                    return
                        [
                            'success' => true,
                            'data' => $res,
                            'error' =>
                                [
                                    'code' => '',
                                    'msg' => ''
                                ]
                        ];
                }else{
                    return
                        [
                            'success' => false,
                            'data' => new stdClass(),
                            'error' =>
                                [
                                    'code' => '030343',
                                    'msg' => 'USER DOES NOT HAVE COURSES'
                                ]
                        ];
                }

            } else {
                return
                    [
                        'success' => false,
                        'data' => new stdClass(),
                        'error' =>
                            [
                                'code' => '030341',
                                'msg' => 'USER NOT FOUND'
                            ]
                    ];
            }
        } else {
            return
                [
                    'success' => false,
                    'data' => new stdClass(),
                    'error' =>
                        [
                            'code' => '030342',
                            'msg' => 'FIELD NOT VALID'
                        ]
                ];

        }
    }

}
