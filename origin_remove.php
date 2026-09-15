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
 * Remote delete assistant (Tresipunt redesign).
 *
 * Single-page assistant that deletes courses or whole categories on a remote
 * origin platform. Replaces the legacy multi-step origin_remove pages while
 * keeping the same URL so the admin/navigation links (settings.php, lib.php)
 * do not change.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\output\error_page;
use local_coursetransfer\output\remove_page;

require_once('../../config.php');

global $PAGE, $OUTPUT;

$title = get_string('rmv_title', 'local_coursetransfer');

require_login();
$context = context_system::instance();

$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_title($title);
// The page renders its own hero <h1>; clear the theme heading.
$PAGE->set_heading('');
$PAGE->set_url(new moodle_url('/local/coursetransfer/origin_remove.php'));

$output = $PAGE->get_renderer('local_coursetransfer');

echo $OUTPUT->header();
// Either delete capability (course or category) may enter the assistant; each
// kind is then gated per capability inside the wizard and the web service.
if (
    has_capability('local/coursetransfer:origin_remove_course', $context)
        || has_capability('local/coursetransfer:origin_remove_category', $context)
) {
    $page = new remove_page();
} else {
    $page = new error_page(
        get_string('forbidden', 'local_coursetransfer'),
        get_string('you_have_not_permission', 'local_coursetransfer'),
        'danger',
        get_string('error')
    );
}

echo $output->render($page);
echo $OUTPUT->footer();
