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
 * Origin Restore Course.
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\output\new_origin_restore_category_step1_page;
use local_coursetransfer\output\new_origin_restore_category_step2_page;
use local_coursetransfer\output\new_origin_restore_category_step3_page;
use local_coursetransfer\output\new_origin_restore_category_step4_page;

require_once('../../config.php');

global $PAGE, $OUTPUT, $USER;

$categoryid = required_param('id', PARAM_INT);
$isnew = optional_param('new', false, PARAM_INT);
$isnew = $isnew === 1;

$title = get_string('origin_restore_category', 'local_coursetransfer');

$category = core_course_category::get($categoryid, MUST_EXIST);

require_login();

$PAGE->set_pagelayout('coursecategory');
$PAGE->set_context(context_coursecat::instance($categoryid));
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_url('/local/coursetransfer/origin_restore_category.php');


$output = $PAGE->get_renderer('local_coursetransfer');

echo $OUTPUT->header();
if ($isnew) {
    $step = required_param('step', PARAM_INT);
    switch ($step) {
        case 1:
            $page = new new_origin_restore_category_step1_page($category);
            break;
        case 2:
            $page = new new_origin_restore_category_step2_page($category);
            break;
        case 3:
            $page = new new_origin_restore_category_step3_page($category);
            break;
        case 4:
            $page = new new_origin_restore_category_step4_page($category);
            break;
        default:
            throw new moodle_exception('STEP NOT VALID');
    }
} else {
    $page = new \local_coursetransfer\output\origin_restore_category_page($category);
}
echo $output->render($page);
echo $OUTPUT->footer();
