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
 * Lib.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
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
        $url = new moodle_url('/local/coursetransfer/origin_restore_course.php', ['id' => $course->id]);
        $icon = new pix_icon('t/restore', $label);
        $navigation->add($label, $url, navigation_node::TYPE_COURSE, null, null, $icon);

    }

}

/**
 * Add shortcut to the course-reuse menu.
 * @param navigation_node $navigation
 * @param context $context
 * @return void
 */
function local_coursetransfer_extend_settings_navigation(navigation_node $navigation, context $context) {
    global $CFG, $PAGE;

    if ($context->contextlevel !== CONTEXT_COURSE) {
        return;
    }
    if (has_capability('local/coursetransfer:origin_restore_course', $context)) {
        $course = $PAGE->course;
        $coursereusenode = $navigation->find('coursereuse', \navigation_node::TYPE_CONTAINER);
        // Add the node to the settingsnav
        if ($coursereusenode) {
            $url = new moodle_url('/local/coursetransfer/origin_restore_course.php', ['id' => $course->id]);
            $coursereusenode->add(get_string('origin_restore_course', 'local_coursetransfer'), 
                                $url,
                                \navigation_node::TYPE_SETTING, null, null,
                                new pix_icon('t/restore', get_string('origin_restore_course', 'local_coursetransfer')));
        }

    }
}
/**
 * Adds to the course admin menu.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param context_coursecat $context $context The context of the course
 * @return void|null return null if we don't want to display the node.
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_coursetransfer_extend_navigation_category_settings(navigation_node $navigation, context_coursecat $context) {
    global $PAGE;

    $categoryid = $context->instanceid;

    $category = core_course_category::get($categoryid, MUST_EXIST);

    $url = new moodle_url('/local/coursetransfer/origin_restore_category.php', ['id' => $category->id]);
    $pluginname = 'local_coursetransfer';
    $label = get_string('origin_restore_category', 'local_coursetransfer');

    $node = navigation_node::create(
            $label,
            $url,
            navigation_node::NODETYPE_LEAF,
            'local_coursetransfer',
            'local_coursetransfer',
            new pix_icon('trash', $pluginname, 'tool_recyclebin')
    );

    if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
        $node->make_active();
    }

    $navigation->add_node($node);
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
 * @throws coding_exception
 */
function local_coursetransfer_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=[]): bool {
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
