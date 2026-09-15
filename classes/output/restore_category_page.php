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
 * Teacher/manager category restore assistant page (Tresipunt redesign).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use coding_exception;
use context_coursecat;
use core\exception\moodle_exception;
use core_course_category;
use dml_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * restore_category_page
 *
 * Single-page assistant (landing + 4-step wizard + done) for the
 * TEACHER/MANAGER category restore flow (category context). It brings a remote
 * category — all its courses, including subcategories — into the CURRENT
 * category. Reuses the shared restore_wizard web services and submits through
 * restore_wizard_submit_category (course-category context capabilities).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_category_page implements renderable, templatable {
    /** @var int How many recent restorations of this category to show. */
    const RECENT_LIMIT = 5;

    /** @var core_course_category The destination (current) category. */
    protected core_course_category $category;

    /**
     * Constructor.
     *
     * @param core_course_category $category The destination (current) category.
     */
    public function __construct(core_course_category $category) {
        $this->category = $category;
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $context = context_coursecat::instance($this->category->id);

        $data = new stdClass();
        $data->headertitle = get_string('rcc_title', 'local_coursetransfer');
        $data->headerdesc = get_string('rcc_lead', 'local_coursetransfer');

        $data->catid = (int)$this->category->id;
        $data->catname = format_string($this->category->name, true, ['context' => $context]);
        // The destination environment is this Moodle site (shown in the review).
        $data->localsite = format_string(get_site()->fullname);

        $data->caturl = (new moodle_url('/course/index.php', ['categoryid' => $this->category->id]))->out(false);
        $data->logurl = (new moodle_url(
            '/local/coursetransfer/origin_restore_category.php',
            ['id' => $this->category->id]
        ))->out(false);

        // Search page size (plugin setting, default 5).
        $data->pagesize = max(1, (int)(get_config('local_coursetransfer', 'pagesize') ?: 5));

        $data->recent = $this->export_recent();
        $data->hasrecent = !empty($data->recent);

        return $data;
    }

    /**
     * Recent category restorations INTO this category, newest first.
     *
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function export_recent(): array {
        $rows = coursetransfer_request::get_by_target_category_id($this->category->id);
        $rows = is_array($rows) ? array_values($rows) : [];
        usort($rows, static function ($a, $b) {
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
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function map_recent(stdClass $r): stdClass {
        $status = (int)$r->status;
        $shortname = coursetransfer::STATUS[$status]['shortname'] ?? 'not_started';

        $name = trim((string)$r->origin_category_name) !== ''
                ? $r->origin_category_name : '—';

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
            'date' => userdate(
                (int)$r->timemodified,
                get_string('strftimedatetimeshort', 'langconfig')
            ),
            'iserror' => $iserror,
            'errcause' => $errcause,
            'erraction' => $erraction,
            'detailurl' => (new moodle_url('/local/coursetransfer/log.php', ['id' => (int)$r->id]))->out(false),
        ];
    }
}
