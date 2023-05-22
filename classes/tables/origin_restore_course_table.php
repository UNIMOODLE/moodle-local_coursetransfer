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

/**
 * Class origin_restore_course_table
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\tables;

use coding_exception;
use core_user;
use DateTime;
use local_coursetransfer\coursetransfer;
use moodle_exception;
use moodle_url;
use stdClass;
use table_sql;

defined('MOODLE_INTERNAL') || die;

require_once('../../lib/tablelib.php');

/**
 * Class origin_restore_course_table
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class origin_restore_course_table extends table_sql {

    /** @var int PAGE SIZE */
    const PAGE_SIZE = 20;

    /** @var stdClass Course */
    protected $course;

    /**
     * constructor.
     *
     * @param string $uniqueid
     * @param stdClass $course
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function __construct(string $uniqueid, stdClass $course) {

        parent::__construct($uniqueid);

        $this->course = $course;

        $this->define_columns([
            'id', 'siteurl', 'origin_course_id', 'status', 'origin_activities',
                    'configuration', 'backupsize', 'userid', 'timemodified', 'timecreated'
        ]);

        $this->define_headers([
                get_string('request_id', 'local_coursetransfer'),
                get_string('siteurl', 'local_coursetransfer'),
                get_string('origin_course_id', 'local_coursetransfer'),
                get_string('status', 'local_coursetransfer'),
                get_string('origin_activities', 'local_coursetransfer'),
                get_string('configuration', 'local_coursetransfer'),
                get_string('backupsize', 'local_coursetransfer'),
                get_string('userid', 'local_coursetransfer'),
                get_string('timemodified', 'local_coursetransfer'),
                get_string('timecreated', 'local_coursetransfer'),
        ]);

        $this->sortable(false);

        $this->column_style('id', 'text-align', 'center');
        $this->column_style('configuration', 'text-align', 'center');
    }

    /**
     * Col request id
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_id(stdClass $row): string {
        return $row->id;
    }

    /**
     * Col Site URL
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_siteurl(stdClass $row): string {
        return '<a href="' . $row->siteurl . '" target="_blank">' . $row->siteurl . '</a>';
    }

    /**
     * Col Origin Course ID
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_origin_course_id(stdClass $row): string {
        $href = new moodle_url($row->siteurl . '/course/view.php', ['id' => $row->origin_course_id]);
        return '<a href="' . $href->out(false) . '" target="_blank">' . $row->origin_course_id . '</a>';
    }

    /**
     * Col Status Code
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_status(stdClass $row): string {
        if ( (int)$row->status === 0 ) {
            return '<button type="button" class="btn btn-danger label-status" data-container="body" data-toggle="popover"
             data-placement="bottom" data-content="'. $row->error_code . ': ' . $row->error_message .'">'
                . get_string('status_'.coursetransfer::STATUS[$row->status]['shortname'],
                    'local_coursetransfer') .'
            </button>';
        } else {
            return '<label class="label-status text-' . coursetransfer::STATUS[$row->status]['alert'] . '">'
                . get_string('status_'.coursetransfer::STATUS[$row->status]['shortname'],
                    'local_coursetransfer') . '</label>';
        }
    }

    /**
     * Col Origin Activities
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_origin_activities(stdClass $row): string {
        global $PAGE;
        $output = $PAGE->get_renderer('local_coursetransfer');
        $component = new \local_coursetransfer\output\activities_component($row->origin_activities);
        return $output->render($component);
    }

    /**
     * Col Configuration
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_configuration(stdClass $row): string {
        global $PAGE;
        $configuration = [$row->destiny_remove_activities, $row->destiny_merge_activities, $row->destiny_remove_enrols, $row->destiny_remove_groups];
        $output = $PAGE->get_renderer('local_coursetransfer');
        $component = new \local_coursetransfer\output\configuration_component($configuration);
        return $output->render($component);
    }

    /**
     * Col User ID
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_userid(stdClass $row): string {
        $href = new moodle_url($row->siteurl . '/user/profile.php', ['id' => $row->userid]);
        $user = core_user::get_user($row->userid);
        return '<a href="' . $href->out(false) . '" target="_blank">' . fullname($user) . '</a>';
    }

    /**
     * Col Size
     *
     * @param stdClass $row Full data of the current row.
     * @return int
     */
    public function col_backupsize(stdClass $row): int {
        return !is_null($row->origin_backup_size) ? $row->origin_backup_size : 0;
    }

    /**
     * Col Last Time modified
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_timemodified(stdClass $row): string {
        $date = new DateTime();
        $date->setTimestamp($row->timemodified);
        $date = userdate($row->timemodified, get_string("strftimedatetimeshort", "core_langconfig"));
        return $date;
    }

    /**
     * Col Created Time modified
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_timecreated(stdClass $row): string {
        $date = new DateTime();
        $date->setTimestamp($row->timecreated);
        $date = userdate($row->timecreated, get_string("strftimedatetimeshort", "core_langconfig"));
        return $date;
    }
}
