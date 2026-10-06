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
 * Export the (filtered) course transfer logs as CSV/Excel/ODS.
 *
 * @package    local_coursetransfer
 * @copyright  2025 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;

require_once('../../config.php');

global $DB;

// Same filter params as the logs page (logs.php), so "Export" respects the
// current view.
$q = trim(optional_param('q', '', PARAM_TEXT));
$festado = optional_param('festado', '', PARAM_ALPHA);
$ftipo = optional_param('ftipo', -1, PARAM_INT);
$fdir = optional_param('fdir', '', PARAM_ALPHA);
$fsite = trim(optional_param('fsite', '', PARAM_URL));
$ffrom = optional_param('ffrom', '', PARAM_RAW_TRIMMED);
$fto = optional_param('fto', '', PARAM_RAW_TRIMMED);
$dataformat = optional_param('dataformat', 'csv', PARAM_ALPHA);

require_login();
require_capability('local/coursetransfer:view_logs', context_system::instance());

$fromts = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ffrom) ? strtotime($ffrom . ' 00:00:00') : null;
$tots = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fto) ? strtotime($fto . ' 23:59:59') : null;
$filters = [
    'q' => $q,
    'statusgroup' => in_array($festado, ['prog', 'wait', 'done', 'err'], true) ? $festado : '',
    'type' => ($ftipo >= 0 && $ftipo <= 3) ? $ftipo : -1,
    'dir' => in_array($fdir, ['in', 'out'], true) ? $fdir : '',
    'site' => $fsite,
    'from' => $fromts,
    'to' => $tots,
];
[$where, $params] = coursetransfer_request::get_executions_filter_sql($filters);

$columns = [
        'id' => get_string('request_id', 'local_coursetransfer'),
        'siteurl' => get_string('siteurl', 'local_coursetransfer'),
        'origin_course_id' => get_string('origin_course_id', 'local_coursetransfer'),
        'origin_course_fullname' => get_string('step2_course_name', 'local_coursetransfer'),
        'target_course_id' => get_string('target_course_id', 'local_coursetransfer'),
        'status' => get_string('status', 'local_coursetransfer'),
        'backupsize_mb' => get_string('backupsize', 'local_coursetransfer'),
        'downloaded_mb' => get_string('progress_download', 'local_coursetransfer'),
        'restored_pct' => get_string('progress_restore', 'local_coursetransfer'),
        'timecreated' => get_string('timecreated', 'local_coursetransfer'),
        'timemodified' => get_string('timemodified', 'local_coursetransfer'),
        'error_code' => get_string('log_page_error_code', 'local_coursetransfer'),
        'error_message' => get_string('log_page_error_msg', 'local_coursetransfer'),
];

$statusmap = coursetransfer::STATUS;
// Note that get_executions_filter_sql aliases the request table as `r` and may reference
// the joined course as `c` (search by target course name).
$sql = "SELECT r.*, c.fullname AS targetcoursename
          FROM {local_coursetransfer_request} r
     LEFT JOIN {course} c ON c.id = r.target_course_id
         WHERE $where
      ORDER BY r.timemodified DESC, r.id DESC";
$rs = $DB->get_recordset_sql($sql, $params);

\core\dataformat::download_data(
    'coursetransfer_logs',
    $dataformat,
    $columns,
    $rs,
    function ($r) use ($statusmap) {
        $statuslabel = isset($statusmap[$r->status])
                ? get_string('status_' . $statusmap[$r->status]['shortname'], 'local_coursetransfer')
                : $r->status;
        return (object) [
                'id' => $r->id,
                'siteurl' => $r->siteurl,
                'origin_course_id' => $r->origin_course_id,
                'origin_course_fullname' => $r->origin_course_fullname,
                'target_course_id' => $r->target_course_id,
                'status' => $statuslabel,
                'backupsize_mb' => !is_null($r->origin_backup_size) ? round($r->origin_backup_size / 1000000, 2) : '',
                'downloaded_mb' => !empty($r->downloaded) ? round($r->downloaded / 1000000, 2) : '',
                'restored_pct' => !empty($r->restored) ? $r->restored . '%' : '',
                'timecreated' => $r->timecreated ? userdate($r->timecreated) : '',
                'timemodified' => $r->timemodified ? userdate($r->timemodified) : '',
                'error_code' => $r->error_code,
                'error_message' => $r->error_message,
        ];
    }
);
