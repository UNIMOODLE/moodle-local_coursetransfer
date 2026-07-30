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
 * Index.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\output\index_page;

require(__DIR__.'/../../config.php');
global $CFG, $DB, $PAGE, $OUTPUT;

require_login();

$PAGE->requires->css('/local/coursetransfer/styles.css');

$title = get_string('summary', 'local_coursetransfer');

if (is_siteadmin()) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_pagelayout('standard');
    // Scopes the summary-only banner styles (see styles.css .ct-summary-page).
    $PAGE->add_body_class('ct-summary-page');
    $PAGE->set_title($title);
    // The page renders its own hero heading; clear the theme one.
    $PAGE->set_heading('');
    $PAGE->set_url('/local/coursetransfer/index.php');
    $output = $PAGE->get_renderer('local_coursetransfer');
    echo $OUTPUT->header();
    $page = new index_page();
    echo $output->render($page);
    echo $OUTPUT->footer();
}

