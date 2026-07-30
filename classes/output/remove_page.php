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
 * Remote delete assistant page (Tresipunt redesign).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use coding_exception;
use context_system;
use dml_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * remove_page
 *
 * Single-page assistant (landing + 3-step wizard + done) for the ADMIN remote
 * DELETE flow: it removes courses or whole categories on a remote origin
 * platform. Destructive and remote — the design and copy stress that the effect
 * is on the OTHER platform, not this site.
 *
 * Dynamic parts (sites, origin listing, delete) are driven by AMD through the
 * restore_wizard web services (get_sites, list_origin, remove_submit).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_page implements renderable, templatable {

    /** @var int How many recent deletions to show on the landing. */
    const RECENT_LIMIT = 5;

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception|dml_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $context = context_system::instance();

        $data = new stdClass();
        $data->headertitle = get_string('rmv_title', 'local_coursetransfer');
        $data->headerdesc = get_string('rmv_lead', 'local_coursetransfer');

        $data->canremovecourse = has_capability('local/coursetransfer:origin_remove_course', $context);
        $data->canremovecategory = has_capability('local/coursetransfer:origin_remove_category', $context);

        $data->logurl = (new moodle_url('/local/coursetransfer/logs.php'))->out(false);
        $data->pagesize = max(1, (int)(get_config('local_coursetransfer', 'pagesize') ?: 5));

        $data->recent = $this->export_recent();
        $data->hasrecent = !empty($data->recent);

        return $data;
    }

    /**
     * Recent deletions (remove course + remove category requests initiated from
     * here), newest first, capped at RECENT_LIMIT.
     *
     * @return array
     * @throws coding_exception|dml_exception
     */
    protected function export_recent(): array {
        $rows = array_merge(
                coursetransfer_request::get_executions(
                        ['type' => coursetransfer_request::TYPE_REMOVE_COURSE], 0, self::RECENT_LIMIT),
                coursetransfer_request::get_executions(
                        ['type' => coursetransfer_request::TYPE_REMOVE_CATEGORY], 0, self::RECENT_LIMIT)
        );
        $rows = array_filter($rows, static function($r) {
            return (int)$r->direction === coursetransfer_request::DIRECTION_REQUEST;
        });
        usort($rows, static function($a, $b) {
            return (int)$b->timemodified <=> (int)$a->timemodified;
        });
        $rows = array_slice($rows, 0, self::RECENT_LIMIT);

        $recent = [];
        foreach ($rows as $r) {
            $recent[] = $this->map_recent($r);
        }
        return $recent;
    }

    /**
     * Map a request row to the recent card context.
     *
     * @param stdClass $r
     * @return stdClass
     * @throws coding_exception|moodle_exception
     */
    protected function map_recent(stdClass $r): stdClass {
        $status = (int)$r->status;
        $shortname = coursetransfer::STATUS[$status]['shortname'] ?? 'not_started';

        $iscategory = (int)$r->type === coursetransfer_request::TYPE_REMOVE_CATEGORY;
        $name = $iscategory ? (string)$r->origin_category_name : (string)$r->origin_course_fullname;
        $name = trim($name) !== '' ? $name : '—';

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
            'date' => userdate((int)$r->timemodified,
                    get_string('strftimedatetimeshort', 'langconfig')),
            'iserror' => $iserror,
            'errcause' => $errcause,
            'erraction' => $erraction,
            'detailurl' => (new moodle_url('/local/coursetransfer/log.php', ['id' => (int)$r->id]))->out(false),
        ];
    }
}
