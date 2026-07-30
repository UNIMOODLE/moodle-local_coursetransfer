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
 * Admin restore assistant page (Tresipunt redesign).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use coding_exception;
use core\exception\moodle_exception;
use dml_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * restore_admin_page
 *
 * Single-page assistant (landing + 4-step wizard + done) for the ADMIN
 * (system context) remote restore flow. It is a SPA-lite: the server only
 * renders the shell and the pieces that are known up front (destination
 * categories, recent restorations); everything dynamic (sites, origin
 * listing, submit) is driven by AMD through the restore_wizard web services.
 *
 * MVC: this renderable takes no request input and performs no direct $DB
 * access; it delegates to model helpers (coursetransfer_request,
 * core_course_category).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_admin_page implements renderable, templatable {

    /** @var int How many recent restorations to show on the landing. */
    const RECENT_LIMIT = 5;

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();

        $data->headertitle = get_string('rw_title', 'local_coursetransfer');
        $data->headerdesc = get_string('rw_lead', 'local_coursetransfer');

        $data->back = (new moodle_url('/admin/settings.php',
                ['section' => 'local_coursetransfer']))->out(false);
        $data->summaryurl = (new moodle_url('/local/coursetransfer/index.php'))->out(false);
        $data->logurl = (new moodle_url('/local/coursetransfer/logs.php'))->out(false);

        // Search page size (plugin setting, default 5).
        $data->pagesize = max(1, (int)(get_config('local_coursetransfer', 'pagesize') ?: 5));

        // Destination categories are no longer dumped here: the step-2 picker is
        // an autocomplete (core/form-autocomplete) that searches them server-side
        // via local_coursetransfer_dest_search_category_name, so it scales to
        // thousands of categories.
        $data->recent = $this->export_recent();
        $data->hasrecent = !empty($data->recent);

        return $data;
    }

    /**
     * Recent restorations (course + category requests initiated from here),
     * newest first, capped at RECENT_LIMIT.
     *
     * NOTE: coursetransfer_request::get_executions() does not filter by
     * launching user, so this is the site-wide recent restore activity, not
     * strictly "my" restorations. That is acceptable for the admin landing;
     * a per-user filter would require a model change (out of scope here).
     *
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function export_recent(): array {
        // Restores are the course (type 0) and category (type 1) request rows.
        $rows = array_merge(
                coursetransfer_request::get_executions(['type' => coursetransfer_request::TYPE_COURSE], 0, self::RECENT_LIMIT),
                coursetransfer_request::get_executions(['type' => coursetransfer_request::TYPE_CATEGORY], 0, self::RECENT_LIMIT)
        );
        // Only requests initiated from this site (we pull the content in).
        $rows = array_filter($rows, static function($r) {
            return (int)$r->direction === coursetransfer_request::DIRECTION_REQUEST;
        });
        // Newest first, then cap.
        usort($rows, static function($a, $b) {
            return (int)$b->timemodified <=> (int)$a->timemodified;
        });
        $rows = array_slice($rows, 0, self::RECENT_LIMIT);

        $recent = [];
        $logurl = (new moodle_url('/local/coursetransfer/logs.php'))->out(false);
        foreach ($rows as $r) {
            $recent[] = $this->map_recent($r, $logurl);
        }
        return $recent;
    }

    /**
     * Map a request row to the recent card context, reusing the executions
     * badge/status conventions.
     *
     * @param stdClass $r
     * @param string $logurl
     * @return stdClass
     * @throws coding_exception|moodle_exception
     */
    protected function map_recent(stdClass $r, string $logurl): stdClass {
        $status = (int)$r->status;
        $shortname = coursetransfer::STATUS[$status]['shortname'] ?? 'not_started';

        $iscategory = (int)$r->type === coursetransfer_request::TYPE_CATEGORY;
        $name = $iscategory ? (string)$r->origin_category_name : (string)$r->origin_course_fullname;
        $name = trim($name) !== '' ? $name : '—';

        $dest = '';
        if (!empty($r->targetcoursename)) {
            $dest = $r->targetcoursename;
        } else if (!empty($r->target_course_id)) {
            $dest = '#' . (int)$r->target_course_id;
        }

        $iserror = $status === coursetransfer_request::STATUS_ERROR
                || $status === coursetransfer_request::STATUS_INCOMPLETED;
        $errcause = '';
        $erraction = '';
        if ($iserror) {
            $errcause = trim((!empty($r->error_code) ? $r->error_code . ': ' : '')
                    . (string)$r->error_message);
            if ($errcause === '') {
                $errcause = get_string('exec_err_nomessage', 'local_coursetransfer');
            }
            $erraction = get_string('platform_error_action', 'local_coursetransfer');
        }

        return (object)[
            'statuslabel' => get_string('exec_status_' . $shortname, 'local_coursetransfer'),
            'badgeclass' => 'ct-badge--' . str_replace('_', '-', $shortname),
            'summary' => $name,
            'site' => $r->siteurl,
            'dest' => $dest,
            'hasdest' => $dest !== '',
            'date' => userdate((int)$r->timemodified,
                    get_string('strftimedatetimeshort', 'langconfig')),
            'iserror' => $iserror,
            'errcause' => $errcause,
            'erraction' => $erraction,
            'logurl' => $logurl,
            'detailurl' => (new moodle_url('/local/coursetransfer/log.php', ['id' => (int)$r->id]))->out(false),
        ];
    }
}
