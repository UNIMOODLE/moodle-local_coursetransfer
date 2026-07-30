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
 * Request detail.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $PAGE, $OUTPUT, $DB;

use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\output\detail_page;

$id = required_param('id', PARAM_INT);

require_login();
require_capability('local/coursetransfer:view_logs', context_system::instance());

$record = coursetransfer_request::get($id);
if (!$record) {
    throw new moodle_exception('invalidrecord', 'error');
}

$user = $DB->get_record('user', ['id' => $record->userid], 'id, username');
$username = $user ? $user->username : '';

$coursename = $record->origin_course_fullname ?: ($record->origin_category_name ?: ('#' . $id));
$title = get_string('log_page', 'local_coursetransfer') . ': ' . $coursename;

$PAGE->set_pagelayout('standard');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('log_page', 'local_coursetransfer') . ': ' . $id);
// Heading cleared: the page hero (component) renders the course name with the icon.
$PAGE->set_heading('');
$PAGE->set_url('/local/coursetransfer/log.php', ['id' => $id]);

$output = $PAGE->get_renderer('local_coursetransfer');

echo $OUTPUT->header();
echo $output->render(new detail_page($record, $username));
echo $OUTPUT->footer();
