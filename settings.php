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
 * Settings.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\coursetransfer;

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    global $ADMIN, $CFG;

    $ADMIN->add('modules', new admin_category('local_coursetransfer_category',
            new lang_string('pluginname', 'local_coursetransfer')));

    $ADMIN->add('local_coursetransfer_category', new admin_externalpage('local_coursetransfer_config',
            get_string('configuration', 'local_coursetransfer'),
            $CFG->wwwroot . '/admin/settings.php?section=local_coursetransfer'));

    $ADMIN->add('local_coursetransfer_category', new admin_externalpage('local_coursetransfer_summary',
            get_string('summary', 'local_coursetransfer'),
            $CFG->wwwroot . '/local/coursetransfer/index.php'));

    $ADMIN->add('local_coursetransfer_category', new admin_externalpage('local_coursetransfer_restore',
            get_string('restore_page', 'local_coursetransfer'),
            $CFG->wwwroot . '/local/coursetransfer/origin_restore.php'));

    $ADMIN->add('local_coursetransfer_category', new admin_externalpage('local_coursetransfer_remove',
            get_string('remove_page', 'local_coursetransfer'),
            $CFG->wwwroot . '/local/coursetransfer/origin_remove.php'));

    $ADMIN->add('local_coursetransfer_category', new admin_externalpage('local_coursetransfer_logs',
            get_string('logs_page', 'local_coursetransfer'),
            $CFG->wwwroot . '/local/coursetransfer/logs.php'));

    $settings = new admin_settingpage('local_coursetransfer',
        get_string('pluginname', 'local_coursetransfer'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading('local_coursetransfer/pluginname',
        get_string('pluginname_header_general', 'local_coursetransfer'), ''));

    $settings->add(new admin_setting_configtext('local_coursetransfer/target_restore_course_max_size',
        get_string('setting_target_restore_course_max_size', 'local_coursetransfer'),
        get_string('setting_target_restore_course_max_size_desc', 'local_coursetransfer'),
            500, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_coursetransfer/request_timeout',
        get_string('request_timeout', 'local_coursetransfer'),
        get_string('request_timeout_desc', 'local_coursetransfer'),
            20, PARAM_INT));

    $settings->add(new admin_setting_configempty('local_coursetransfer/target_sites',
            new lang_string('setting_target_sites', 'local_coursetransfer'),
            html_writer::link(new moodle_url('/local/coursetransfer/targetsites.php'),
                    new lang_string('setting_target_sites_link', 'local_coursetransfer'))));

    $settings->add(new admin_setting_configempty('local_coursetransfer/origin_sites',
            new lang_string('setting_origin_sites', 'local_coursetransfer'),
            html_writer::link(new moodle_url('/local/coursetransfer/originsites.php'),
                    new lang_string('setting_origin_sites_link', 'local_coursetransfer'))));

    $settings->add(new admin_setting_configcheckbox('local_coursetransfer/remove_course_cleanup',
            get_string('remove_course_cleanup', 'local_coursetransfer'),
            get_string('remove_course_cleanup_desc', 'local_coursetransfer'),
            false));

    $settings->add(new admin_setting_configcheckbox('local_coursetransfer/remove_cat_cleanup',
            get_string('remove_cat_cleanup', 'local_coursetransfer'),
            get_string('remove_cat_cleanup_desc', 'local_coursetransfer'),
            false));

    $settings->add(new admin_setting_configcheckbox('local_coursetransfer/restore_courses_hidden',
            get_string('restore_courses_hidden', 'local_coursetransfer'),
            get_string('restore_courses_hidden_desc', 'local_coursetransfer'),
            false));

    $choices = coursetransfer::FIELDS_USER;
    $options = [];
    foreach ($choices as $choice) {
        $options[$choice] = $choice;
    }

    $item = new admin_setting_configselect('local_coursetransfer/origin_field_search_user',
            get_string('setting_origin_field_search_user', 'local_coursetransfer'),
            get_string('setting_origin_field_search_user_desc', 'local_coursetransfer'),
            'username', $options );

    $settings->add($item);

    $item->set_updatedcallback(function () {
        redirect(new moodle_url('/local/coursetransfer/postinstall.php'));
    });

}
