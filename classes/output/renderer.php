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
 * Local Course Transfer Renderer.
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use moodle_exception;
use plugin_renderer_base;

/**
 * Local Course Transfer Renderer.
 *
 * @package    local_coursetransfera
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Defer to template.
     *
     * @param index_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_index_page(index_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/index_page', $data);
    }

    /**
     * Defer to template.
     *
     * @param origin_restore_course_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_course_page(origin_restore_course_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_restore_course_page', $data);
    }

    /**
     * Defer to template.
     *
     * @param new_origin_restore_course_step1_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_course_step1_page(new_origin_restore_course_step1_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/new_origin_restore_course_step1_page', $data);
    }

    /**
     * Defer to template.
     *
     * @param new_origin_restore_course_step2_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_course_step2_page(new_origin_restore_course_step2_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/new_origin_restore_course_step2_page', $data);
    }

    /**
     * Defer to template.
     *
     * @param new_origin_restore_course_step3_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_course_step3_page(new_origin_restore_course_step3_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/new_origin_restore_course_step3_page', $data);
    }

    /**
     * Defer to template.
     *
     * @param new_origin_restore_course_step4_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_course_step4_page(new_origin_restore_course_step4_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/new_origin_restore_course_step4_page', $data);
    }
    /**
     * Defer to template.
     *
     * @param new_origin_restore_course_step5_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_course_step5_page(new_origin_restore_course_step5_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/new_origin_restore_course_step5_page', $data);
    }

    /**
     * Defer to template.
     *
     * @param activities_component $component
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_activities_component(activities_component $component) {
        $data = $component->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/table_activities_component', $data);
    }

    /**
     * Defer to template.
     *
     * @param category_course_component $component
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_category_course_component(category_course_component $component) {
        $data = $component->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/table_category_course_component', $data);
    }

    /**
     * Defer to template.
     *
     * @param configuration_component $component
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_configuration_component(configuration_component $component) {
        $data = $component->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/table_configuration_component', $data);
    }

    /**
     * Defer to template.
     *
     * @param origin_restore_category_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_category_page(origin_restore_category_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_restore_category_page', $data);
    }

    /**
     * Defer to template.
     *
     * @param origin_restore_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_page(origin_restore_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_restore_page', $data);
    }

}
