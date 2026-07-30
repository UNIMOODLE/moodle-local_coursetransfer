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
 * Executions log: active migrations and unified filtered history.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $PAGE, $OUTPUT;

use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\output\executions_page;

require_login();
require_capability('local/coursetransfer:view_logs', context_system::instance());

$tab = optional_param('tab', 'encurso', PARAM_ALPHA);
$pagenum = optional_param('page', 0, PARAM_INT);
$q = trim(optional_param('q', '', PARAM_TEXT));
$festado = optional_param('festado', '', PARAM_ALPHA);
$ftipo = optional_param('ftipo', -1, PARAM_INT);
$fdir = optional_param('fdir', '', PARAM_ALPHA);
$fsite = trim(optional_param('fsite', '', PARAM_URL));
$ffrom = optional_param('ffrom', '', PARAM_RAW_TRIMMED);
$fto = optional_param('fto', '', PARAM_RAW_TRIMMED);

$perpage = 10;

// Date inputs arrive as YYYY-MM-DD strings.
$fromts = null;
$tots = null;
if ($ffrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ffrom)) {
    $fromts = strtotime($ffrom . ' 00:00:00');
} else {
    $ffrom = '';
}
if ($fto !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fto)) {
    $tots = strtotime($fto . ' 23:59:59');
} else {
    $fto = '';
}

$filters = [
    'q' => $q,
    'statusgroup' => in_array($festado, ['prog', 'wait', 'done', 'err'], true) ? $festado : '',
    'type' => ($ftipo >= 0 && $ftipo <= 3) ? $ftipo : -1,
    'dir' => in_array($fdir, ['in', 'out'], true) ? $fdir : '',
    'site' => $fsite,
    'from' => $fromts,
    'to' => $tots,
    'fromraw' => $ffrom,
    'toraw' => $fto,
];

$title = get_string('logs_page', 'local_coursetransfer');

$PAGE->set_pagelayout('standard');
$PAGE->set_context(context_system::instance());
$PAGE->set_title($title);
// Heading cleared: the page hero (component) renders the title with the icon.
$PAGE->set_heading('');
$PAGE->set_url('/local/coursetransfer/logs.php', ['tab' => $tab]);

$active = coursetransfer_request::get_active_executions();
if ($tab !== 'registro' && empty($active)) {
    $tab = 'registro';
} else if ($tab !== 'registro') {
    $tab = 'encurso';
}

$total = coursetransfer_request::count_executions($filters);
$rows = $tab === 'registro'
    ? coursetransfer_request::get_executions($filters, $pagenum, $perpage) : [];
$sites = coursetransfer_request::get_execution_sites();

$output = $PAGE->get_renderer('local_coursetransfer');

echo $OUTPUT->header();
echo $output->render(new executions_page($active, $rows, $total, $filters,
        $sites, $tab, $pagenum, $perpage));
echo $OUTPUT->footer();
