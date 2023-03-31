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
class origin_restore_course_table extends table_sql
{

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
    public function __construct(string $uniqueid, stdClass $course)
    {

        parent::__construct($uniqueid);

        $this->course = $course;

        $this->define_columns([
            'id', 'siteurl', 'origin_course_id', 'status', 'origin_activities',
                    'configuration', 'error', 'backupsize', 'userid', 'timemodified', 'timecreated'
        ]);

        $this->define_headers([
                get_string('request_id', 'local_coursetransfer'),
                get_string('siteurl', 'local_coursetransfer'),
                get_string('origin_course_id', 'local_coursetransfer'),
                get_string('status', 'local_coursetransfer'),
                get_string('origin_activities', 'local_coursetransfer'),
                get_string('configuration', 'local_coursetransfer'),
                get_string('error', 'local_coursetransfer'),
                get_string('backupsize', 'local_coursetransfer'),
                get_string('userid', 'local_coursetransfer'),
                get_string('timemodified', 'local_coursetransfer'),
                get_string('timecreated', 'local_coursetransfer'),
        ]);

        $this->sortable(false);

        $this->column_style('id', 'text-align', 'center');

    }

    /**
     * Col request id
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_id(stdClass $row): string
    {
        return $row->id;
    }

    /**
     * Col Site URL
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_siteurl(stdClass $row): string
    {
        return '<a href="' . $row->siteurl . '" target="_blank">' . $row->siteurl . '</a>';
    }

    /**
     * Col Origin Course ID
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_origin_course_id(stdClass $row): string
    {
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
    public function col_status(stdClass $row): string
    {
        return '<label class="text-' . coursetransfer::STATUS[$row->status]['alert'] . '">'
            . coursetransfer::STATUS[$row->status]['shortname'] . '</label>';
    }

    /**
     * Col Origin Activities
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_origin_activities(stdClass $row): string
    {
        return
            '
            <button type="button" class="btn btn-dark origin-activity" data-toggle="modal" data-target="#exampleModalCenter">
              Activities
            </button>
            
            <!-- Modal -->
            <div class="modal fade bd-example-modal-lg" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
              <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle">Activities</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                    <!--. ($row->origin_activities ??) .-->
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>
        ';
    }

    /**
     * Col Configuration
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_configuration(stdClass $row): string
    {
        return
            '
            <button type="button" class="btn btn-light configuration" data-toggle="modal" data-target="#exampleModalCenter">
              Configuration
            </button>
            
            <!-- Modal -->
            <div class="modal fade bd-example-modal-lg" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
              <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle">Configuration</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">'
                    . ($row->configuration ?? '') .
                '</div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>
        ';
    }

    /**
     * Col Errors
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_error(stdClass $row): string
    {
        return
            '
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#exampleModalCenter">
              Errors
            </button>
            
            <!-- Modal -->
            <div class="modal fade bd-example-modal-lg" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
              <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle">Errors</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">'
            . $row->error_message . $row->error_code .
            '</div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>
        ';
    }

    /**
     * Col User ID
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_userid(stdClass $row): string
    {
        $href = new moodle_url($row->siteurl . '/user/profile.php', ['id' => $row->userid]);
        return '<a href="' . $href->out(false) . '" target="_blank">' . $row->userid . '</a>';
    }

    /**
     * Col Size
     *
     * @param stdClass $row Full data of the current row.
     * @return int
     * @throws moodle_exception
     */
    public function col_backupsize(stdClass $row): int
    {
        return $row->origin_backup_size;
    }

    /**
     * Col Last Time modified
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_timemodified(stdClass $row): string
    {
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
    public function col_timecreated(stdClass $row): string
    {
        $date = new DateTime();
        $date->setTimestamp($row->timecreated);
        $date = userdate($row->timecreated, get_string("strftimedatetimeshort", "core_langconfig"));
        return $date;
    }
}
