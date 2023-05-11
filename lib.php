<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Library of interface functions and constants.
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This function extends the course navigation with COURSE TRANSFER Configuration.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 * @throws coding_exception|moodle_exception
 */
function local_coursetransfer_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {

    if (has_capability('local/coursetransfer:origin_restore_course', $context)) {

        $label = get_string('origin_restore_course', 'local_coursetransfer');
        $url = new moodle_url('/local/coursetransfer/origin_restore_course.php', array('id' => $course->id));
        $icon = new pix_icon('t/restore', $label);
        $navigation->add($label, $url, navigation_node::TYPE_COURSE, null, null, $icon);

    }

}

/**
 * Defines custom file provider for downloading backup from remote site.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function local_coursetransfer_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    // Check that the filearea is sane.
    if ($filearea !== 'backup') {
        return false;
    }

    // Require authentication.
    require_login($course, true);

    // Capability check.
    if (!has_capability('moodle/backup:backupcourse', $context)) {
        return false;
    }

    // Extract the filename / filepath from the $args array.
    $itemid = array_shift($args);
    $filename = array_pop($args);
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/'.implode('/', $args).'/';
    }

    // Retrieve the file.
    $fs = get_file_storage();

    $file = $fs->get_file($context->id, 'local_coursetransfer', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
