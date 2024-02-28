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

namespace local_coursetransfer\task;

use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_sites;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/course/externallib.php');

/**
 * remove_course_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_course_task extends \core\task\adhoc_task {

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Execute.
     *
     */
    public function execute() {

        try {

            $this->log_start("Remove Course Remote Starting...");

            $destinysiteid = $this->get_custom_data()->destinysiteid;
            $courseid = $this->get_custom_data()->courseid;
            $requestoriginid = $this->get_custom_data()->requestoriginid;
            $requestdestid = $this->get_custom_data()->requestdestid;
            $userid = $this->get_custom_data()->userid;

            $user = \core_user::get_user($userid);
            $site = coursetransfer_sites::get('destiny', $destinysiteid);
            $request = new request($site);

            $requestorigin = coursetransfer_request::get($requestoriginid);

            $res = \core_course_external::delete_courses([$courseid]);

            if (count($res['warnings']) > 0) {
                $errors[] = json_encode($res['warnings']);
                $requestorigin->status = coursetransfer_request::STATUS_ERROR;
                $requestorigin->error_code = '15001';
                $requestorigin->error_message = json_encode($res['warnings']);
                coursetransfer_request::insert_or_update($requestorigin, $requestoriginid);
                $res = $request->destiny_remove_course_error(
                        $user, $requestdestid, $requestorigin->error_message, $requestorigin->error_code);
                if (!$res->success) {
                    mtrace('Remove Course Remote in Error Callback ERROR: ' . $res->errors[0]->msg);
                    $this->log(json_encode($res));
                }
            } else {
                $requestorigin->status = coursetransfer_request::STATUS_COMPLETED;
                coursetransfer_request::insert_or_update($requestorigin, $requestoriginid);

                $res = $request->destiny_remove_course_completed($requestdestid, $user);

                if (!$res->success) {
                    $requestorigin->status = coursetransfer_request::STATUS_ERROR;
                    $requestorigin->error_code = $res->errors[0]->code;
                    $requestorigin->error_message = $res->errors[0]->msg;
                    coursetransfer_request::insert_or_update($requestorigin, $requestorigin->id);
                    mtrace('Remove Course Remote in Completed Callback ERROR: ' . $res->errors[0]->msg);
                    $this->log(json_encode($res));
                    coursetransfer_request::insert_or_update($requestorigin, $requestoriginid);
                }
            }

        } catch (moodle_exception $e) {
            mtrace('Remove Course Remote ERROR: ' . $e->getMessage());
            $this->log($e->getMessage());
        }

        $this->log_finish("Remove Course Remote Finishing...");

    }
}
