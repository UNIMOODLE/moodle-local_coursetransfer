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
 * Paired platforms page: unified management of origin and target sites.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $PAGE, $OUTPUT;

use local_coursetransfer\coursetransfer_sites;
use local_coursetransfer\output\platforms_page;

require_login();
require_capability('moodle/site:config', context_system::instance());

$title = get_string('platforms_title', 'local_coursetransfer');

$PAGE->set_pagelayout('standard');
$PAGE->set_context(context_system::instance());
$PAGE->set_title($title);
// Heading cleared: the page hero (component) renders the title with the icon.
$PAGE->set_heading('');
$PAGE->set_url('/local/coursetransfer/sites.php');

$platforms = coursetransfer_sites::get_platforms();
$inuse = [];
foreach ($platforms as $platform) {
    $inuse[$platform->host] = coursetransfer_sites::is_in_use($platform->host);
}

$output = $PAGE->get_renderer('local_coursetransfer');

echo $OUTPUT->header();
echo $output->render(new platforms_page($platforms, $inuse));
echo $OUTPUT->footer();
