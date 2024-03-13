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
 * Coursetransfer Notification.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use coding_exception;
use core\message\message;
use dml_exception;
use moodle_exception;
use moodle_url;

/**
 * coursetransfer_notification
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer_notification {

    /**
     * Send Restore Completed
     *
     * @param int $userid
     * @param int $courseid
     * @return false|int|mixed
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function send_restore_course_completed(int $userid, int $courseid) {
        $user = \core_user::get_user($userid);
        $course = get_course($courseid);
        $courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
        $detailurl = new moodle_url('/local/coursetransfer/origin_restore_course.php', ['id' => $courseid]);
        $message = new message();
        $message->component = 'local_coursetransfer';
        $message->name = 'restore_course_completed';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $sub = get_string('messageprovider:restore_course_completed', 'local_coursetransfer')
                . ' [' . $courseid .']';
        $message->subject = $sub;
        $msghtml = get_string('notification_restore_course_completed', 'local_coursetransfer',
        '<strong>' . $course->fullname . '</strong>');
        $msghtml .= '<br>';
        $msghtml .= get_string('view_detail', 'local_coursetransfer');
        $msghtml .= '<br>';
        $msghtml .= '<a class="btn btn-link" target="_blank" href="'. $detailurl->out(false)
                . '">'. $detailurl->out(false) . '</a>';
        $message->fullmessage = $sub;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $msghtml;
        $message->smallmessage = $sub;
        $message->notification = 1;
        $message->contexturl = $courseurl->out(false);
        return message_send($message);
    }

    /**
     * Send Restore Completed
     *
     * @param int $userid
     * @param int $catid
     * @return false|int|mixed
     * @throws dml_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function send_restore_category_completed(int $userid, int $catid) {
        $user = \core_user::get_user($userid);
        $cat = \core_course_category::get($catid);
        $courseurl = new moodle_url('/course/index.php', ['categoryid' => $catid]);
        $detailurl = new moodle_url('/local/coursetransfer/origin_restore_category.php', ['id' => $catid]);
        $message = new message();
        $message->component = 'local_coursetransfer';
        $message->name = 'restore_category_completed';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $sub = get_string('messageprovider:restore_category_completed', 'local_coursetransfer')
                . ' [' . $catid .']';
        $message->subject = $sub;
        $msghtml = get_string('notification_restore_category_completed', 'local_coursetransfer',
                '<strong>' . $cat->name . '</strong>');
        $msghtml .= '<br>';
        $msghtml .= get_string('view_detail', 'local_coursetransfer');
        $msghtml .= '<br>';
        $msghtml .= '<a class="btn btn-link" target="_blank" href="'. $detailurl->out(false)
                . '">'. $detailurl->out(false) . '</a>';
        $message->fullmessage = $msghtml;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $msghtml;
        $message->smallmessage = $sub;
        $message->notification = 1;
        $message->contexturl = $courseurl->out(false);
        return message_send($message);
    }

    /**
     * Send Remove Course Completed
     *
     * @param int $userid
     * @param int $origincourseid
     * @return false|int|mixed
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function send_remove_course_completed(int $userid, int $origincourseid) {
        $user = \core_user::get_user($userid);
        $detailurl = new moodle_url('/local/coursetransfer/logs.php', ['type' => 2, 'direction' => 0]);
        $message = new message();
        $message->component = 'local_coursetransfer';
        $message->name = 'remove_course_completed';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $sub = get_string('messageprovider:remove_course_completed', 'local_coursetransfer')
                . ' [' . $origincourseid .']';
        $msghtml = get_string('notification_remove_course_completed', 'local_coursetransfer',
                '<strong>' . $origincourseid . '</strong>');
        $msghtml .= '<br>';
        $msghtml .= get_string('view_detail', 'local_coursetransfer');
        $msghtml .= '<br>';
        $msghtml .= '<a class="btn btn-link" target="_blank" href="'. $detailurl->out(false)
                . '">'. $detailurl->out(false) . '</a>';
        $message->subject = $sub;
        $message->fullmessage = $msghtml;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $msghtml;
        $message->smallmessage = $sub;
        $message->notification = 1;
        return message_send($message);
    }

    /**
     * Send Remove Category Completed
     *
     * @param int $userid
     * @param int $origincatid
     * @return false|int|mixed
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function send_remove_category_completed(int $userid, int $origincatid) {
        $user = \core_user::get_user($userid);
        $detailurl = new moodle_url('/local/coursetransfer/logs.php', ['type' => 3, 'direction' => 0]);
        $message = new message();
        $message->component = 'local_coursetransfer';
        $message->name = 'remove_category_completed';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $sub = get_string('messageprovider:remove_category_completed', 'local_coursetransfer')
                . ' [' . $origincatid .']';
        $message->subject = $sub;
        $msghtml = get_string('notification_remove_category_completed', 'local_coursetransfer',
                '<strong>' . $origincatid . '</strong>');
        $msghtml .= '<br>';
        $msghtml .= get_string('view_detail', 'local_coursetransfer');
        $msghtml .= '<br>';
        $msghtml .= '<a class="btn btn-link" target="_blank" href="'. $detailurl->out(false)
                . '">'. $detailurl->out(false) . '</a>';
        $message->fullmessage = $msghtml;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $msghtml;
        $message->smallmessage = $sub;
        $message->notification = 1;
        return message_send($message);
    }

}
