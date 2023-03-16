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
 * Course Transfer plugin admin settings and defaults
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_coursetransfer',
        get_string('pluginname', 'local_coursetransfer'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading('local_coursetransfer/pluginname',
        get_string('pluginname_header_general', 'local_coursetransfer'), ''));

    $settings->add(new admin_setting_configtext('local_coursetransfer/destiny_restore_course_max_size',
        get_string('setting_destiny_restore_course_max_size', 'local_coursetransfer'),
        get_string('setting_destiny_restore_course_max_size_desc', 'local_coursetransfer'), 500), PARAM_INT);

    $settings->add(new admin_setting_configtextarea('local_coursetransfer/destiny_sites',
            get_string('setting_destiny_sites', 'local_coursetransfer'),
            get_string('setting_destiny_sites_desc', 'local_coursetransfer'), ""), PARAM_RAW);

    $settings->add(new admin_setting_configtextarea('local_coursetransfer/origin_sites',
        get_string('setting_origin_sites', 'local_coursetransfer'),
        get_string('setting_origin_sites_desc', 'local_coursetransfer'), ""), PARAM_RAW);

    $CHOICES = ['username', 'email', 'userid'];
    $settings->add(new admin_setting_configselect('local_coursetransfer/origin_field_search_user',
        get_string('setting_origin_field_search_user', 'local_coursetransfer'),
        get_string('setting_origin_field_search_user_desc', 'local_coursetransfer'), 'username', $CHOICES));
}