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
 * Unified executions log page (Tresipunt redesign).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use coding_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * executions_page
 *
 * Renders the unified executions dashboard (active migrations + full
 * log). Receives every dataset by constructor: no database access here.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class executions_page implements renderable, templatable {
    /** @var int Minutes without movement before an active request is flagged as stuck */
    const STUCK_MINUTES = 30;

    /** @var stdClass[] Active requests */
    protected array $active;

    /** @var stdClass[] Current log page rows */
    protected array $rows;

    /** @var int Total rows matching the filters */
    protected int $total;

    /** @var array Current filters (raw values from the page) */
    protected array $filters;

    /** @var string[] Site URLs for the site filter */
    protected array $sites;

    /** @var string Active tab: encurso|registro */
    protected string $tab;

    /** @var int Current page (zero-based) */
    protected int $page;

    /** @var int Rows per page */
    protected int $perpage;

    /**
     * Constructor.
     *
     * @param stdClass[] $active
     * @param stdClass[] $rows
     * @param int $total
     * @param array $filters
     * @param string[] $sites
     * @param string $tab
     * @param int $page
     * @param int $perpage
     */
    public function __construct(
        array $active,
        array $rows,
        int $total,
        array $filters,
        array $sites,
        string $tab,
        int $page,
        int $perpage
    ) {
        $this->active = $active;
        $this->rows = $rows;
        $this->total = $total;
        $this->filters = $filters;
        $this->sites = $sites;
        $this->tab = $tab;
        $this->page = $page;
        $this->perpage = $perpage;
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception|moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->headertitle = get_string('logs_page', 'local_coursetransfer');
        $data->headerdesc = get_string('exec_lead', 'local_coursetransfer');
        $data->baseurl = (new moodle_url('/local/coursetransfer/logs.php'))->out(false);
        $data->exporturl = (new moodle_url('/local/coursetransfer/export.php'))->out(false);
        $data->back = (new moodle_url('/admin/settings.php', ['section' => 'local_coursetransfer']))->out(false);
        $data->summary = (new moodle_url('/local/coursetransfer/index.php'))->out(false);

        $data->active = [];
        foreach ($this->active as $request) {
            $data->active[] = $this->export_active($request);
        }
        $data->hasactive = !empty($data->active);
        $data->activecount = count($data->active);
        $data->tabencurso = $data->hasactive && $this->tab === 'encurso';
        $data->tabregistro = !$data->tabencurso;

        $data->rows = [];
        foreach ($this->rows as $row) {
            $data->rows[] = $this->export_row($row);
        }
        $data->norows = empty($data->rows);
        $data->total = $this->total;
        $data->resultlabel = get_string(
            $this->total === 1 ? 'exec_result_one' : 'exec_results',
            'local_coursetransfer',
            $this->total
        );

        $data->filters = $this->export_filters();
        $data->pagination = $this->export_pagination();
        return $data;
    }

    /**
     * Map an active request to a progress card.
     *
     * @param stdClass $request
     * @return stdClass
     * @throws coding_exception
     */
    protected function export_active(stdClass $request): stdClass {
        $item = $this->export_common($request);

        // Five phases: accepted, backup, download, restore, completed.
        $status = (int)$request->status;
        $phasemap = [
            coursetransfer_request::STATUS_NOT_STARTED => 0,
            coursetransfer_request::STATUS_IN_PROGRESS => 0,
            coursetransfer_request::STATUS_BACKUP => 1,
            coursetransfer_request::STATUS_DOWNLOAD => 2,
            coursetransfer_request::STATUS_DOWNLOADED => 2,
            coursetransfer_request::STATUS_RESTORE => 3,
        ];
        $phase = $phasemap[$status] ?? 0;

        $pct = 0;
        if ($status === coursetransfer_request::STATUS_DOWNLOAD && !empty($request->origin_backup_size)) {
            $pct = min(100, (int)round(((int)$request->downloaded / (int)$request->origin_backup_size) * 100));
        } else if ($status === coursetransfer_request::STATUS_DOWNLOADED) {
            $pct = 100;
        } else if ($status === coursetransfer_request::STATUS_RESTORE) {
            $pct = min(100, (int)$request->restored);
        }

        // One segment per phase: 0% until that phase starts, its live % while it
        // is the current phase, 100% once finished. Each segment carries its
        // index so the template can tint it progressively darker as it advances,
        // and an "active" flag so the current phase pulses even at 0% (e.g. the
        // backup phase, where no live percentage is available).
        $item->segments = [];
        for ($i = 0; $i < 5; $i++) {
            if ($i < $phase) {
                $width = 100;
            } else if ($i === $phase) {
                $width = $pct;
            } else {
                $width = 0;
            }
            $item->segments[] = (object)[
                'width' => $width,
                'idx' => $i,
                'active' => ($i === $phase),
                'done' => ($i < $phase),
            ];
        }

        if ($status === coursetransfer_request::STATUS_DOWNLOAD && !empty($request->origin_backup_size)) {
            $item->caption = get_string('exec_caption_download', 'local_coursetransfer', (object)[
                'pct' => $pct,
                'done' => display_size((int)$request->downloaded),
                'total' => display_size((int)$request->origin_backup_size),
            ]);
        } else if ($status === coursetransfer_request::STATUS_RESTORE) {
            $item->caption = get_string('exec_caption_restore', 'local_coursetransfer', $pct);
        } else {
            $item->caption = get_string('exec_caption_' . $item->statusshort, 'local_coursetransfer');
        }

        $age = time() - (int)$request->timemodified;
        $item->stuck = $age > (self::STUCK_MINUTES * 60);
        $item->since = format_time($age);
        return $item;
    }

    /**
     * Map a log row.
     *
     * @param stdClass $request
     * @return stdClass
     * @throws coding_exception|moodle_exception
     */
    protected function export_row(stdClass $request): stdClass {
        $item = $this->export_common($request);
        $item->size = empty($request->origin_backup_size) ? '—' : display_size((int)$request->origin_backup_size);
        $isincomplete = (int)$request->status === coursetransfer_request::STATUS_INCOMPLETED;
        $item->iserror = (int)$request->status === coursetransfer_request::STATUS_ERROR || $isincomplete;
        if ($item->iserror) {
            $item->errcause = trim(($request->error_code ? $request->error_code . ': ' : '')
                    . (string)$request->error_message);
            if ($item->errcause === '') {
                // No message stored: give a state-specific fallback rather
                // than a generic "unknown error" (common on incomplete
                // category restores where per-course errors live elsewhere).
                $item->errcause = get_string(
                    $isincomplete ? 'exec_err_incomplete' : 'exec_err_nomessage',
                    'local_coursetransfer'
                );
            }
            $item->erraction = get_string(
                $isincomplete ? 'exec_err_incomplete_action' : 'platform_error_action',
                'local_coursetransfer'
            );
            // Retry is only implemented for course restores (LCT-023).
            $item->canretry = (int)$request->type === coursetransfer_request::TYPE_COURSE;
            $item->retryurl = (new moodle_url(
                '/local/coursetransfer/retry.php',
                ['id' => $request->id]
            ))->out(false);
        }
        return $item;
    }

    /**
     * Fields shared by cards and rows: badge, direction tags, names, links.
     *
     * @param stdClass $request
     * @return stdClass
     * @throws coding_exception|moodle_exception
     */
    protected function export_common(stdClass $request): stdClass {
        $item = new stdClass();
        $item->id = $request->id;
        $item->detailurl = (new moodle_url(
            '/local/coursetransfer/log.php',
            ['id' => $request->id]
        ))->out(false);

        $statuses = coursetransfer::STATUS;
        $shortname = $statuses[(int)$request->status]['shortname'] ?? 'not_started';
        $item->statusshort = $shortname;
        $item->badgeclass = 'ct-badge--' . str_replace('_', '-', $shortname);
        $item->statuslabel = get_string('exec_status_' . $shortname, 'local_coursetransfer');

        $type = (int)$request->type;
        $item->typelabel = get_string('exec_type_' . $type, 'local_coursetransfer');
        $isrestore = in_array($type, [coursetransfer_request::TYPE_COURSE,
                coursetransfer_request::TYPE_CATEGORY], true);
        $isrequest = (int)$request->direction === coursetransfer_request::DIRECTION_REQUEST;
        $item->isremove = !$isrestore;
        $item->isrestore = $isrestore;
        $item->dirin = ($isrestore && $isrequest) || (!$isrestore && !$isrequest);
        $item->dirlabel = get_string(
            $item->dirin ? 'platforms_role_origin' : 'platforms_role_target',
            'local_coursetransfer'
        );
        // Direction of the record itself: request (this site initiated) vs response
        // (this site received a request from a peer). Shown as a compact icon.
        $item->isrequest = $isrequest;
        $item->dirtip = get_string($isrequest ? 'exec_dir_request' : 'exec_dir_response', 'local_coursetransfer');
        $item->typedir = '#' . $request->id . ' · ' . $item->typelabel;

        // Course requests created as part of a category restore point back to the
        // parent category request via request_category_id.
        $catreqid = (int)($request->request_category_id ?? 0);
        $item->iscatchild = $catreqid > 0;
        if ($item->iscatchild) {
            $item->catrequestid = $catreqid;
            $item->catrequesturl = (new moodle_url('/local/coursetransfer/log.php', ['id' => $catreqid]))->out(false);
        }

        $item->site = $request->siteurl;
        $item->date = userdate(
            (int)$request->timemodified,
            get_string('strftimedatetimeshort', 'langconfig')
        );

        // Deferred execution: a pending scheduled task (future run time) so the
        // user can see it will run later. origin_schedule_datetime is a unix ts.
        $sched = (int)($request->origin_schedule_datetime ?? 0);
        $item->scheduled = $sched > 0 && $sched > time();
        $item->scheduledwhen = $item->scheduled
                ? userdate($sched, get_string('strftimedatetime', 'langconfig'))
                : '';
        $item->scheduledtooltip = $item->scheduled
                ? get_string('exec_scheduled', 'local_coursetransfer', $item->scheduledwhen)
                : '';
        $item->scheduledlabel = get_string('exec_scheduled_label', 'local_coursetransfer');

        // Origin / destination names and links.
        $iscategory = in_array($type, [coursetransfer_request::TYPE_CATEGORY,
                coursetransfer_request::TYPE_REMOVE_CATEGORY], true);
        $originname = $iscategory ? $request->origin_category_name : $request->origin_course_fullname;
        $item->originname = trim((string)$originname) !== '' ? $originname : '—';
        $originid = $iscategory ? (int)$request->origin_category_id : (int)$request->origin_course_id;
        $originpath = $iscategory ? '/course/index.php?categoryid=' : '/course/view.php?id=';
        if ($item->dirin === $isrestore) {
            // The origin lives on the remote platform.
            $item->originurl = $originid > 0 ? rtrim($request->siteurl, '/') . $originpath . $originid : '';
        } else {
            $item->originurl = $originid > 0
                ? (new moodle_url($originpath . $originid))->out(false) : '';
        }

        $item->destname = '';
        $item->desturl = '';
        if ($isrestore) {
            if (!empty($request->targetcoursename)) {
                $item->destname = $request->targetcoursename;
                $item->desturl = (new moodle_url(
                    '/course/view.php',
                    ['id' => (int)$request->target_course_id]
                ))->out(false);
            } else if (!empty($request->target_course_id)) {
                $item->destname = '#' . $request->target_course_id;
                if (!$item->dirin) {
                    $item->desturl = rtrim($request->siteurl, '/')
                            . '/course/view.php?id=' . (int)$request->target_course_id;
                }
            } else {
                $item->destname = '—';
            }
        }
        return $item;
    }

    /**
     * Filter form context.
     *
     * @return stdClass
     * @throws coding_exception
     */
    protected function export_filters(): stdClass {
        $filters = new stdClass();
        $filters->q = $this->filters['q'] ?? '';
        $filters->from = $this->filters['fromraw'] ?? '';
        $filters->to = $this->filters['toraw'] ?? '';

        $filters->statusoptions = [];
        foreach (
            ['' => 'exec_filter_all', 'prog' => 'exec_group_prog', 'wait' => 'exec_group_wait',
                'done' => 'exec_group_done', 'err' => 'exec_group_err'] as $value => $key
        ) {
            $filters->statusoptions[] = (object)[
                'value' => $value,
                'label' => get_string($key, 'local_coursetransfer'),
                'selected' => ($this->filters['statusgroup'] ?? '') === (string)$value,
            ];
        }
        $filters->typeoptions = [];
        foreach ([-1, 0, 1, 2, 3] as $value) {
            $filters->typeoptions[] = (object)[
                'value' => $value,
                'label' => $value === -1
                    ? get_string('exec_filter_all', 'local_coursetransfer')
                    : get_string('exec_type_' . $value, 'local_coursetransfer'),
                'selected' => (int)($this->filters['type'] ?? -1) === $value,
            ];
        }
        $filters->diroptions = [];
        foreach (
            ['' => 'exec_filter_all_f', 'in' => 'platforms_role_origin',
                'out' => 'platforms_role_target'] as $value => $key
        ) {
            $filters->diroptions[] = (object)[
                'value' => $value,
                'label' => get_string($key, 'local_coursetransfer'),
                'selected' => ($this->filters['dir'] ?? '') === (string)$value,
            ];
        }
        $filters->siteoptions = [(object)[
            'value' => '',
            'label' => get_string('exec_filter_all', 'local_coursetransfer'),
            'selected' => empty($this->filters['site']),
        ]];
        foreach ($this->sites as $site) {
            $filters->siteoptions[] = (object)[
                'value' => $site,
                'label' => $site,
                'selected' => ($this->filters['site'] ?? '') === $site,
            ];
        }
        $filters->any = !empty($this->filters['q']) || !empty($this->filters['statusgroup'])
                || (isset($this->filters['type']) && (int)$this->filters['type'] >= 0)
                || !empty($this->filters['dir']) || !empty($this->filters['site'])
                || !empty($this->filters['fromraw']) || !empty($this->filters['toraw']);
        return $filters;
    }

    /**
     * Pagination context.
     *
     * @return stdClass
     */
    protected function export_pagination(): stdClass {
        $pagination = new stdClass();
        $pages = max(1, (int)ceil($this->total / $this->perpage));
        $current = min($this->page + 1, $pages);
        $pagination->haspages = $pages > 1;
        $pagination->page = $current;
        $pagination->pages = $pages;
        $pagination->hasprev = $current > 1;
        $pagination->hasnext = $current < $pages;
        $pagination->prevpage = max(0, $current - 2);
        $pagination->nextpage = min($pages - 1, $current);
        return $pagination;
    }
}
