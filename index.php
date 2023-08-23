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
 * Plugin post installation configuration.
 *
 * @package     local_coursetransfer
 * @copyright   2023 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\output\index_page;

require(__DIR__.'/../../config.php');
global $CFG, $DB, $PAGE, $OUTPUT;
require($CFG->libdir . '/externallib.php');

require_login();

$PAGE->requires->css('/local/coursetransfer/styles.css');

$title = get_string('summary', 'local_coursetransfer');

if (is_siteadmin()) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title($title);
    $PAGE->set_heading($title);
    $PAGE->set_url('/local/coursetransfer/index.php');
    $output = $PAGE->get_renderer('local_coursetransfer');
    echo $OUTPUT->header();
    $page = new index_page();
    echo $output->render($page);
    echo $OUTPUT->footer();
}

