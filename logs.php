<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Origin Remove Course.
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\coursetransfer_request;

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

switch ($type) {
    case coursetransfer_request::TYPE_COURSE:
        if ($direction === coursetransfer_request::DIRECTION_REQUEST) {
            $page = new \local_coursetransfer\output\logs_course_request_page();
        } else {
            $page = new \local_coursetransfer\output\logs_course_response_page();
        }
        break;
    case coursetransfer_request::TYPE_CATEGORY:
        if ($direction === coursetransfer_request::DIRECTION_REQUEST) {
            $page = new \local_coursetransfer\output\logs_category_request_page();
        } else {
            $page = new \local_coursetransfer\output\logs_category_response_page();
        }
        break;
    case coursetransfer_request::TYPE_REMOVE_COURSE:
        if ($direction === coursetransfer_request::DIRECTION_REQUEST) {
            $page = new \local_coursetransfer\output\logs_course_remove_request_page();
        } else {
            $page = new \local_coursetransfer\output\logs_course_remove_response_page();
        }
        break;
    case coursetransfer_request::TYPE_REMOVE_CATEGORY:
        if ($direction === coursetransfer_request::DIRECTION_REQUEST) {
            $page = new \local_coursetransfer\output\logs_category_remove_request_page();
        } else {
            $page = new \local_coursetransfer\output\logs_category_remove_response_page();
        }
        break;
    default:
        throw new moodle_exception('TYPE NOT VALID');
}

echo $output->render($page);
echo $OUTPUT->footer();
