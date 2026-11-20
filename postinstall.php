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
 * Postinstall.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core\output\notification;

require_login();
require_capability('moodle/site:config', context_system::instance());

$url = new moodle_url('/local/coursetransfer/postinstall.php');
$returnurl = new moodle_url('/local/coursetransfer/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title(get_string('pluginname', 'local_coursetransfer'));

// It reconfigures web services and the service user: never on a bare link (CSRF, LCT-030).
$sesskey = optional_param('sesskey', '', PARAM_RAW);
if ($sesskey === '' || !confirm_sesskey($sesskey)) {
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('postinstall_confirm', 'local_coursetransfer'),
        new moodle_url($url, ['sesskey' => sesskey()]),
        $returnurl
    );
    echo $OUTPUT->footer();
    die;
}

try {
    \local_coursetransfer\coursetransfer::postinstall();
    redirect($returnurl);
} catch (moodle_exception $e) {
    redirect(
        $returnurl,
        get_string('postinstall_error', 'local_coursetransfer', $e->getMessage()),
        null,
        notification::NOTIFY_ERROR
    );
}
