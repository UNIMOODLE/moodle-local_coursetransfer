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
 * Request detail page (Tresipunt redesign, TIPGOODLE-352).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use backup;
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
 * detail_page
 *
 * Renders a single request as a progress timeline plus grouped fields.
 * Receives the request record and the launcher username by constructor:
 * no database access here (Tresipunt MVC rule).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class detail_page implements renderable, templatable {

    /** @var stdClass Request record */
    protected stdClass $record;

    /** @var string Launcher username */
    protected string $username;

    /**
     * Constructor.
     *
     * @param stdClass $record request row
     * @param string $username launcher username
     */
    public function __construct(stdClass $record, string $username) {
        $this->record = $record;
        $this->username = $username;
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception|moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $r = $this->record;
        $data = new stdClass();
        $data->id = $r->id;
        $data->back = (new moodle_url('/local/coursetransfer/logs.php', ['tab' => 'registro']))->out(false);

        // Status badge.
        $statuses = coursetransfer::STATUS;
        $shortname = $statuses[(int)$r->status]['shortname'] ?? 'not_started';
        $data->badgeclass = 'ct-badge--' . str_replace('_', '-', $shortname);
        $data->statuslabel = get_string('exec_status_' . $shortname, 'local_coursetransfer');

        $type = (int)$r->type;
        $iscategory = in_array($type, [coursetransfer_request::TYPE_CATEGORY,
                coursetransfer_request::TYPE_REMOVE_CATEGORY], true);
        $isrestore = in_array($type, [coursetransfer_request::TYPE_COURSE,
                coursetransfer_request::TYPE_CATEGORY], true);
        $isrequest = (int)$r->direction === coursetransfer_request::DIRECTION_REQUEST;
        $dirin = ($isrestore && $isrequest) || (!$isrestore && !$isrequest);

        $data->coursetitle = $iscategory
            ? ($r->origin_category_name ?: '#' . $r->origin_category_id)
            : ($r->origin_course_fullname ?: '#' . $r->origin_course_id);
        $data->headertitle = $data->coursetitle;
        $data->headerdesc = get_string('exec_request_num', 'local_coursetransfer') . ' #' . $r->id;

        // Origin / destination quick links.
        $originid = $iscategory ? (int)$r->origin_category_id : (int)$r->origin_course_id;
        $originpath = $iscategory ? '/course/index.php?categoryid=' : '/course/view.php?id=';
        $data->originurl = '';
        if ($originid > 0) {
            $data->originurl = ($dirin === $isrestore)
                ? rtrim($r->siteurl, '/') . $originpath . $originid
                : (new moodle_url($originpath . $originid))->out(false);
        }
        // Origin link label: for a delete request the target IS what gets
        // deleted, so read "Course/Category to delete" instead of "origin".
        $isremove = in_array($type, [coursetransfer_request::TYPE_REMOVE_COURSE,
                coursetransfer_request::TYPE_REMOVE_CATEGORY], true);
        if ($isremove) {
            $data->openoriginlabel = get_string($iscategory ? 'exec_g_removecat' : 'exec_g_removecourse',
                    'local_coursetransfer');
        } else {
            $data->openoriginlabel = get_string($iscategory ? 'exec_g_origincat' : 'exec_g_origincourse',
                    'local_coursetransfer');
        }
        $data->desturl = '';
        if ($isrestore && !empty($r->target_course_id)) {
            $data->desturl = $dirin
                ? (new moodle_url('/course/view.php', ['id' => (int)$r->target_course_id]))->out(false)
                : rtrim($r->siteurl, '/') . '/course/view.php?id=' . (int)$r->target_course_id;
        }

        $data->timeline = $this->build_timeline($shortname, $r);
        $data->groups = $this->build_groups($r, $type, $dirin);

        // Pending scheduled (deferred) execution: highlight it so the user sees
        // this task has not run yet and when it will.
        $sched = (int)($r->origin_schedule_datetime ?? 0);
        $data->scheduled = $sched > 0 && $sched > time();
        $data->scheduledtext = $data->scheduled
                ? get_string('exec_scheduled', 'local_coursetransfer',
                        userdate($sched, get_string('strftimedatetime', 'langconfig')))
                : '';

        // Error block.
        $data->haserror = in_array((int)$r->status, [coursetransfer_request::STATUS_ERROR,
                coursetransfer_request::STATUS_INCOMPLETED], true);
        if ($data->haserror) {
            $data->errcause = trim((string)$r->error_message);
            if ($data->errcause === '') {
                $data->errcause = get_string('exec_err_nomessage', 'local_coursetransfer');
            }
            $data->erraction = get_string('platform_error_action', 'local_coursetransfer');
            $data->errcode = $r->error_code;
            $data->canretry = $type === coursetransfer_request::TYPE_COURSE;
            $data->retryurl = (new moodle_url('/local/coursetransfer/retry.php', ['id' => $r->id]))->out(false);
        }

        // Request content (sections + activities).
        $data->sections = $this->build_sections($r);
        $data->hassections = !empty($data->sections);

        // Related scheduled/adhoc tasks (shown inline here instead of a separate page).
        $data->tasks = $this->build_tasks((int)$r->id);
        $data->hastasks = !empty($data->tasks);
        // Aggregate diagnostics so the admin can spot (and mitigate) problems.
        $data->hastaskfailing = false;
        $data->hastaskdue = false;
        foreach ($data->tasks as $t) {
            $data->hastaskfailing = $data->hastaskfailing || $t->failing;
            $data->hastaskdue = $data->hastaskdue || $t->due;
        }
        $data->tasksurl = (new moodle_url('/admin/tool/task/scheduledtasks.php'))->out(false);

        // Age / stalled detection (audit): a request still in progress whose last
        // change is old is likely stuck (remote platform or cron not running).
        $iscompleted = (int)$r->status === coursetransfer_request::STATUS_COMPLETED;
        $data->inprogress = !$iscompleted && !$data->haserror;
        $data->agetext = '';
        $data->stalled = false;
        if ($data->inprogress) {
            $idle = time() - (int)$r->timemodified;
            $data->agetext = get_string('exec_age', 'local_coursetransfer', (object)[
                'age' => format_time(time() - (int)$r->timecreated),
                'idle' => format_time($idle),
            ]);
            $data->stalled = $idle > HOURSECS;
        }

        // Cross-site correlation: the ids needed to find the same operation on the
        // peer Moodle (request id, origin platform, origin/target object ids).
        $originid = $iscategory ? (int)$r->origin_category_id : (int)$r->origin_course_id;
        $targetid = $iscategory ? (int)($r->target_category_id ?? 0) : (int)($r->target_course_id ?? 0);
        $sitelogs = $r->siteurl ? rtrim($r->siteurl, '/') . '/local/coursetransfer/logs.php' : '';
        $selfurl = (new moodle_url('/local/coursetransfer/log.php', ['id' => (int)$r->id]))->out(false);
        $data->corr = [
            (object)['label' => get_string('exec_corr_request', 'local_coursetransfer'),
                    'value' => '#' . (int)$r->id, 'url' => $selfurl],
            (object)['label' => get_string('exec_corr_site', 'local_coursetransfer'),
                    'value' => $r->siteurl, 'url' => $sitelogs],
            (object)['label' => get_string('exec_corr_origin', 'local_coursetransfer'),
                    'value' => $originid, 'url' => $data->originurl],
            (object)['label' => get_string('exec_corr_target', 'local_coursetransfer'),
                    'value' => $targetid ?: '—', 'url' => $targetid ? $data->desturl : ''],
        ];

        // If this course request was created as part of a category restore, link to
        // the parent category request.
        $catreqid = (int)($r->request_category_id ?? 0);
        $data->hascatparent = $catreqid > 0;
        if ($data->hascatparent) {
            $data->catparentid = $catreqid;
            $data->catparenturl = (new moodle_url('/local/coursetransfer/log.php', ['id' => $catreqid]))->out(false);
        }

        // Category subtree snapshot persisted at request time (category requests only).
        $data->tree = null;
        $data->hastree = false;
        if (!empty($r->origin_category_tree)) {
            $decodedtree = json_decode((string)$r->origin_category_tree);
            if ($decodedtree && isset($decodedtree->name)) {
                $data->tree = $decodedtree;
                $data->hastree = true;
            }
        }
        return $data;
    }

    /**
     * Build the vertical progress timeline for a request.
     *
     * @param string $shortname current status shortname
     * @param stdClass $r
     * @return stdClass[]
     * @throws coding_exception
     */
    protected function build_timeline(string $shortname, stdClass $r): array {
        $steps = ['created', 'accepted', 'backup', 'download', 'restore', 'completed'];
        // Index of the step the request has reached.
        $stepindex = [
            'not_started' => 1, 'in_progress' => 1, 'in_backup' => 2,
            'download' => 3, 'downloaded' => 3, 'restore' => 4, 'completed' => 5, 'incompleted' => 4,
        ];
        $iserror = $shortname === 'error';
        $curidx = $iserror ? -1 : ($stepindex[$shortname] ?? 0);
        $completed = $shortname === 'completed';

        $timeline = [];
        $count = count($steps);
        foreach ($steps as $i => $step) {
            $done = $completed || !$iserror && $i < $curidx;
            $current = !$iserror && $i === $curidx && !$completed;
            $item = new stdClass();
            $item->label = get_string('exec_step_' . $step, 'local_coursetransfer');
            $item->done = $done && !$current;
            $item->current = $current;
            $item->pending = !$done && !$current;
            $item->first = $i === 0;
            $item->last = $i === ($count - 1);
            $item->hasbar = $i < ($count - 1);
            $item->bardone = $done && !$current;
            if ($current) {
                $item->time = get_string('exec_step_current', 'local_coursetransfer');
            } else if ($done) {
                $item->time = get_string('exec_step_done', 'local_coursetransfer');
            } else {
                $item->time = get_string('exec_step_pending', 'local_coursetransfer');
            }
            $timeline[] = $item;
        }
        return $timeline;
    }

    /**
     * Build the grouped field cards.
     *
     * @param stdClass $r
     * @param int $type
     * @param bool $dirin
     * @return stdClass[]
     * @throws coding_exception
     */
    protected function build_groups(stdClass $r, int $type, bool $dirin): array {
        $dash = '—';
        $bool = function($value) {
            if ($value === null || $value === '') {
                return get_string('no');
            }
            return (int)$value === 1 ? get_string('yes') : get_string('no');
        };
        $size = function($bytes) {
            return number_format(((int)$bytes) / 1000000, 3, ',', ' ') . ' MB';
        };

        $general = [
            $this->field(get_string('exec_f_type', 'local_coursetransfer'),
                    get_string('exec_type_' . $type, 'local_coursetransfer')),
            $this->field(get_string('exec_f_direction', 'local_coursetransfer'),
                    get_string($dirin ? 'platforms_role_origin' : 'platforms_role_target', 'local_coursetransfer')),
            $this->field(get_string('exec_f_mode', 'local_coursetransfer'), $this->target_label($r->target_target)),
            $this->field(get_string('exec_f_scheduled', 'local_coursetransfer'),
                    empty($r->origin_schedule_datetime) ? $dash : userdate((int)$r->origin_schedule_datetime), true),
            $this->field(get_string('exec_f_launchedby', 'local_coursetransfer'), $this->username ?: $dash),
            $this->field(get_string('exec_f_date', 'local_coursetransfer'), userdate((int)$r->timemodified)),
        ];
        $remote = [
            $this->field(get_string('exec_f_url', 'local_coursetransfer'), $r->siteurl ?: $dash),
            $this->field(get_string('exec_f_targetreq', 'local_coursetransfer'),
                    $r->target_request_id ?: $dash, empty($r->target_request_id)),
        ];
        $origincourse = [
            $this->field(get_string('exec_f_coursename', 'local_coursetransfer'), $r->origin_course_fullname ?: $dash),
            $this->field(get_string('exec_f_courseid', 'local_coursetransfer'), $r->origin_course_id ?: $dash),
            $this->field(get_string('exec_f_shortname', 'local_coursetransfer'), $r->origin_course_shortname ?: $dash),
            $this->field(get_string('exec_f_idnumber', 'local_coursetransfer'),
                    $r->origin_course_idnumber ?: $dash, empty($r->origin_course_idnumber)),
        ];
        $origincat = [
            $this->field(get_string('exec_f_catname', 'local_coursetransfer'), $r->origin_category_name ?: $dash),
            $this->field(get_string('exec_f_catid', 'local_coursetransfer'), $r->origin_category_id ?: $dash),
            $this->field(get_string('exec_f_idnumber', 'local_coursetransfer'),
                    $r->origin_category_idnumber ?: $dash, empty($r->origin_category_idnumber)),
        ];
        $dest = [
            $this->field(get_string('exec_f_targetcourse', 'local_coursetransfer'),
                    $r->target_course_id ?: $dash, empty($r->target_course_id)),
            $this->field(get_string('exec_f_targetcat', 'local_coursetransfer'),
                    $r->target_category_id ?: $dash, empty($r->target_category_id)),
            $this->field(get_string('exec_f_removeenrols', 'local_coursetransfer'), $bool($r->target_remove_enrols)),
            $this->field(get_string('exec_f_removegroups', 'local_coursetransfer'), $bool($r->target_remove_groups)),
        ];
        $config = [
            $this->field(get_string('exec_f_enrolusers', 'local_coursetransfer'), $bool($r->origin_enrolusers)),
            $this->field(get_string('exec_f_removecourse', 'local_coursetransfer'), $bool($r->origin_remove_course)),
            $this->field(get_string('exec_f_removecat', 'local_coursetransfer'), $bool($r->origin_remove_category)),
        ];
        $backup = [
            $this->field(get_string('exec_f_backupsize', 'local_coursetransfer'), $size($r->origin_backup_size)),
            $this->field(get_string('exec_f_backupsizeest', 'local_coursetransfer'),
                    $size($r->origin_backup_size_estimated)),
        ];

        // For remove requests the origin course/category IS what gets deleted,
        // so label those groups "… to delete" instead of "origin …".
        $isremove = in_array($type, [coursetransfer_request::TYPE_REMOVE_COURSE,
                coursetransfer_request::TYPE_REMOVE_CATEGORY], true);
        $coursetitle = get_string($isremove ? 'exec_g_removecourse' : 'exec_g_origincourse', 'local_coursetransfer');
        $cattitle = get_string($isremove ? 'exec_g_removecat' : 'exec_g_origincat', 'local_coursetransfer');

        return [
            $this->group(get_string('exec_g_general', 'local_coursetransfer'), $general),
            $this->group(get_string('exec_g_remote', 'local_coursetransfer'), $remote),
            $this->group($coursetitle, $origincourse),
            $this->group($cattitle, $origincat),
            $this->group(get_string('exec_g_dest', 'local_coursetransfer'), $dest),
            $this->group(get_string('exec_g_config', 'local_coursetransfer'), $config),
            $this->group(get_string('exec_g_backup', 'local_coursetransfer'), $backup),
        ];
    }

    /**
     * Build the selected sections / activities tree.
     *
     * @param stdClass $r
     * @return stdClass[]
     */
    protected function build_sections(stdClass $r): array {
        $decoded = json_decode((string)$r->origin_activities);
        if (!is_array($decoded)) {
            return [];
        }
        $sections = [];
        foreach ($decoded as $section) {
            $item = new stdClass();
            $item->name = $section->sectionname ?? ($section->name ?? '');
            $item->on = !empty($section->selected);
            $item->activities = [];
            $acts = $section->activities ?? [];
            if (is_array($acts)) {
                foreach ($acts as $act) {
                    $item->activities[] = (object)[
                        'name' => $act->name ?? '',
                        'type' => $act->modulename ?? ($act->type ?? ''),
                    ];
                }
            }
            $sections[] = $item;
        }
        return $sections;
    }

    /**
     * Related scheduled/adhoc tasks for this request, formatted for the template.
     *
     * @param int $requestid
     * @return stdClass[]
     */
    protected function build_tasks(int $requestid): array {
        $now = time();
        $tasks = [];
        foreach (coursetransfer_request::get_related_adhoc_tasks($requestid) as $task) {
            $shortclass = ltrim(strrchr($task->classname, '\\'), '\\') ?: $task->classname;
            $tasks[] = (object)[
                'classname' => $shortclass,
                'faildelay' => (int)$task->faildelay,
                // Diagnostics: a task retrying after failure, or one already due but
                // still queued (a sign the cron may be stalled).
                'failing' => (int)$task->faildelay > 0,
                'due' => !empty($task->nextruntime) && (int)$task->nextruntime <= $now,
                'nextrun' => $task->nextruntime ? userdate($task->nextruntime) : '-',
                'timecreated' => !empty($task->timecreated) ? userdate($task->timecreated) : '-',
            ];
        }
        return $tasks;
    }

    /**
     * Field row helper.
     *
     * @param string $label
     * @param string|int $value
     * @param bool $muted
     * @return stdClass
     */
    protected function field(string $label, $value, bool $muted = false): stdClass {
        return (object)['label' => $label, 'value' => (string)$value, 'muted' => $muted];
    }

    /**
     * Field group helper.
     *
     * @param string $title
     * @param stdClass[] $fields
     * @return stdClass
     */
    protected function group(string $title, array $fields): stdClass {
        return (object)['title' => $title, 'fields' => $fields];
    }

    /**
     * Restore mode label.
     *
     * @param int|null $target
     * @return string
     * @throws coding_exception
     */
    protected function target_label(?int $target): string {
        switch ((int)$target) {
            case backup::TARGET_NEW_COURSE:
                return get_string('in_new_course', 'local_coursetransfer');
            case backup::TARGET_EXISTING_DELETING:
                return get_string('remove_content', 'local_coursetransfer');
            case backup::TARGET_EXISTING_ADDING:
                return get_string('merge_content', 'local_coursetransfer');
            default:
                return '—';
        }
    }
}
