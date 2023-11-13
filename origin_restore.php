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

require_once('../../config.php');

global $PAGE, $OUTPUT, $USER;

$title = get_string('restore_page', 'local_coursetransfer');

require_login();

$PAGE->set_pagelayout('standard');
$PAGE->set_context(context_system::instance());
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_url('/local/coursetransfer/origin_restore.php');

$output = $PAGE->get_renderer('local_coursetransfer');

echo $OUTPUT->header();

$step = optional_param('step', null, PARAM_INT);
switch ($step) {
    case 2:
        $type = required_param('type', PARAM_TEXT);
        if ($type === 'categories') {
            $page = new \local_coursetransfer\output\origin_restore_cat_step2_page();
        } else {
            $page = new \local_coursetransfer\output\origin_restore_step2_page();
        }
        break;
    case 3:
        $type = required_param('type', PARAM_TEXT);
        if ($type === 'categories') {
            $page = new \local_coursetransfer\output\origin_restore_cat_step3_page();
        } else {
            $page = new \local_coursetransfer\output\origin_restore_step3_page();
        }
        break;
    case 4:
        $type = required_param('type', PARAM_TEXT);
        if ($type === 'categories') {
            $page = new \local_coursetransfer\output\origin_restore_cat_step4_page();
        } else {
            $page = new \local_coursetransfer\output\origin_restore_step4_page();
        }
        break;
    default:
        $page = new \local_coursetransfer\output\origin_restore_page();
}

echo $output->render($page);

echo $OUTPUT->footer();
