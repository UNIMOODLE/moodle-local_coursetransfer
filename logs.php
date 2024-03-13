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
 * Logs.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\output\logs\logs_category_remove_request_page;
use local_coursetransfer\output\logs\logs_category_remove_response_page;
use local_coursetransfer\output\logs\logs_category_request_page;
use local_coursetransfer\output\logs\logs_category_response_page;
use local_coursetransfer\output\logs\logs_course_remove_request_page;
use local_coursetransfer\output\logs\logs_course_remove_response_page;
use local_coursetransfer\output\logs\logs_course_request_page;
use local_coursetransfer\output\logs\logs_course_response_page;

require_once('../../config.php');

global $PAGE, $OUTPUT, $USER;

$title = get_string('logs_page', 'local_coursetransfer');

$type = optional_param('type', coursetransfer_request::TYPE_COURSE, PARAM_INT);
$direction = optional_param('direction', coursetransfer_request::DIRECTION_REQUEST, PARAM_INT);

require_login();

$PAGE->set_pagelayout('standard');
$PAGE->set_context(context_system::instance());
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_url('/local/coursetransfer/logs.php');

$output = $PAGE->get_renderer('local_coursetransfer');

echo $OUTPUT->header();

if (has_capability('local/coursetransfer:view_logs', context_system::instance())) {
    switch ($type) {
        case coursetransfer_request::TYPE_COURSE:
            if ($direction === coursetransfer_request::DIRECTION_REQUEST) {
                $page = new logs_course_request_page();
            } else {
                $page = new logs_course_response_page();
            }
            break;
        case coursetransfer_request::TYPE_CATEGORY:
            if ($direction === coursetransfer_request::DIRECTION_REQUEST) {
                $page = new logs_category_request_page();
            } else {
                $page = new logs_category_response_page();
            }
            break;
        case coursetransfer_request::TYPE_REMOVE_COURSE:
            if ($direction === coursetransfer_request::DIRECTION_REQUEST) {
                $page = new logs_course_remove_request_page();
            } else {
                $page = new logs_course_remove_response_page();
            }
            break;
        case coursetransfer_request::TYPE_REMOVE_CATEGORY:
            if ($direction === coursetransfer_request::DIRECTION_REQUEST) {
                $page = new logs_category_remove_request_page();
            } else {
                $page = new logs_category_remove_response_page();
            }
            break;
        default:
            throw new moodle_exception('TYPE NOT VALID');
    }
} else {
    $page = new \local_coursetransfer\output\error_page(
            get_string('forbidden', 'local_coursetransfer'),
            get_string('you_have_not_permission', 'local_coursetransfer'),
            'danger',
            get_string('error')
    );
}

echo $output->render($page);
echo $OUTPUT->footer();
