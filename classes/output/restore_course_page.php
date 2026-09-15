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
 * Teacher course restore assistant page (Tresipunt redesign).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use coding_exception;
use context_course;
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
 * restore_course_page
 *
 * Single-page assistant (landing + 5-step wizard + done) for the TEACHER
 * course restore flow (course context). The teacher brings a remote course —
 * or just some of its sections/activities — into the CURRENT course.
 *
 * As with restore_admin_page it is a SPA-lite: the server renders the shell
 * and the pieces known up front (the current course's recent restorations and
 * the teacher's merge/replace capabilities); everything dynamic (sites, origin
 * listing, sections, submit) is driven by AMD through the restore_wizard web
 * services.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_course_page implements renderable, templatable {
    /** @var int How many recent restorations of this course to show. */
    const RECENT_LIMIT = 5;

    /** @var stdClass The destination (current) course. */
    protected stdClass $course;

    /**
     * Constructor.
     *
     * @param stdClass $course The destination (current) course.
     */
    public function __construct(stdClass $course) {
        $this->course = $course;
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
        global $USER;

        $context = context_course::instance($this->course->id);

        $data = new stdClass();
        $data->headertitle = get_string('rct_title', 'local_coursetransfer');
        $data->headerdesc = get_string('rct_lead', 'local_coursetransfer');

        $data->courseid = (int)$this->course->id;
        $data->coursename = format_string(
            $this->course->fullname,
            true,
            ['context' => $context]
        );

        // Which restore modes over the current course the teacher may use.
        // Mirrors the legacy flow: merge and empty-and-restore are separate
        // rights. Only the allowed ones are offered by the wizard.
        $data->canmerge = coursetransfer::can_target_restore_merge($USER, $context);
        $data->canreplace = coursetransfer::can_target_restore_content_remove($USER, $context);

        // Search page size (plugin setting, default 5).
        $data->pagesize = max(1, (int)(get_config('local_coursetransfer', 'pagesize') ?: 5));

        $data->courseurl = (new moodle_url('/course/view.php', ['id' => $this->course->id]))->out(false);
        $data->logurl = (new moodle_url(
            '/local/coursetransfer/origin_restore_course.php',
            ['id' => $this->course->id]
        ))->out(false);

        $data->recent = $this->export_recent();
        $data->hasrecent = !empty($data->recent);

        return $data;
    }

    /**
     * Recent restorations INTO this course (direction request, type course),
     * newest first, capped at RECENT_LIMIT.
     *
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function export_recent(): array {
        $rows = coursetransfer_request::get_executions(
            ['type' => coursetransfer_request::TYPE_COURSE],
            0,
            100
        );
        // Only restorations pulled INTO this course.
        $rows = array_filter($rows, function ($r) {
            return (int)$r->direction === coursetransfer_request::DIRECTION_REQUEST
                    && (int)$r->target_course_id === (int)$this->course->id;
        });
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

        $name = trim((string)$r->origin_course_fullname) !== ''
                ? $r->origin_course_fullname : '—';

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
