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
 * renderer
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use local_coursetransfer\output\components\actions_site_component;
use local_coursetransfer\output\components\activities_component;
use local_coursetransfer\output\components\category_course_component;
use local_coursetransfer\output\components\configuration_component;
use local_coursetransfer\output\logs\log_page;
use local_coursetransfer\output\logs\logs_category_remove_request_page;
use local_coursetransfer\output\logs\logs_category_remove_response_page;
use local_coursetransfer\output\logs\logs_category_request_page;
use local_coursetransfer\output\logs\logs_category_response_page;
use local_coursetransfer\output\logs\logs_course_remove_request_page;
use local_coursetransfer\output\logs\logs_course_remove_response_page;
use local_coursetransfer\output\logs\logs_course_request_page;
use local_coursetransfer\output\logs\logs_course_response_page;
use local_coursetransfer\output\origin_remove\origin_remove_page;
use local_coursetransfer\output\origin_remove\origin_remove_page_cat_step2;
use local_coursetransfer\output\origin_remove\origin_remove_page_cat_step3;
use local_coursetransfer\output\origin_remove\origin_remove_page_step2;
use local_coursetransfer\output\origin_remove\origin_remove_page_step3;
use local_coursetransfer\output\origin_restore\origin_restore_cat_step2_page;
use local_coursetransfer\output\origin_restore\origin_restore_cat_step3_page;
use local_coursetransfer\output\origin_restore\origin_restore_cat_step4_page;
use local_coursetransfer\output\origin_restore\origin_restore_page;
use local_coursetransfer\output\origin_restore\origin_restore_step2_page;
use local_coursetransfer\output\origin_restore\origin_restore_step3_page;
use local_coursetransfer\output\origin_restore\origin_restore_step4_page;
use local_coursetransfer\output\origin_restore_category\new_origin_restore_category_step1_page;
use local_coursetransfer\output\origin_restore_category\new_origin_restore_category_step2_page;
use local_coursetransfer\output\origin_restore_category\new_origin_restore_category_step3_page;
use local_coursetransfer\output\origin_restore_category\new_origin_restore_category_step4_page;
use local_coursetransfer\output\origin_restore_category\origin_restore_category_page;
use local_coursetransfer\output\origin_restore_course\new_origin_restore_course_step1_page;
use local_coursetransfer\output\origin_restore_course\new_origin_restore_course_step2_page;
use local_coursetransfer\output\origin_restore_course\new_origin_restore_course_step3_page;
use local_coursetransfer\output\origin_restore_course\new_origin_restore_course_step4_page;
use local_coursetransfer\output\origin_restore_course\new_origin_restore_course_step5_page;
use local_coursetransfer\output\origin_restore_course\origin_restore_course_page;
use moodle_exception;
use paging_bar;
use plugin_renderer_base;
use stdClass;

/**
 * renderer
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render Index Page.
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
     * Render Origin Restore page.
     *
     * @param origin_restore_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_page(origin_restore_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_restore/origin_restore_page', $data);
    }

    /**
     * Render Origin Restore Step2 Page.
     *
     * @param origin_restore_step2_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_step2_page(origin_restore_step2_page $page) {
        $data = $page->export_for_template($this);
        $data->htmlpagingbar = $this->get_html_paging_bar($data->paging, $page->get_paging_url(), 'page');
        return parent::render_from_template('local_coursetransfer/origin_restore/origin_restore_step2_page', $data);
    }

    /**
     * Render Origin Restore Step3 Page.
     *
     * @param origin_restore_step3_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_step3_page(origin_restore_step3_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_restore/origin_restore_step3_page', $data);
    }

    /**
     * Render Origin Restore Step4 Page.
     *
     * @param origin_restore_step4_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_step4_page(origin_restore_step4_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_restore/origin_restore_step4_page', $data);
    }

    /**
     * Render Origin Restore Category Step2 Page.
     *
     * @param origin_restore_cat_step2_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_cat_step2_page(origin_restore_cat_step2_page $page) {
        $data = $page->export_for_template($this);
        $data->htmlpagingbar = $this->get_html_paging_bar($data->paging, $page->get_paging_url(), 'page');
        return parent::render_from_template('local_coursetransfer/origin_restore/origin_restore_cat_step2_page', $data);
    }

    /**
     * Render Origin Restore Category Step3 Page.
     *
     * @param origin_restore_cat_step3_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_cat_step3_page(origin_restore_cat_step3_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_restore/origin_restore_cat_step3_page', $data);
    }

    /**
     * Render Origin Restore Category Step4 Page.
     *
     * @param origin_restore_cat_step4_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_cat_step4_page(origin_restore_cat_step4_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_restore/origin_restore_cat_step4_page', $data);
    }

    /**
     * Render Origin Restore Course Page.
     *
     * @param origin_restore_course_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_course_page(origin_restore_course_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template(
                'local_coursetransfer/origin_restore_course/origin_restore_course_page', $data);
    }

    /**
     * Render New Origin Restore Course Step1 Page.
     *
     * @param new_origin_restore_course_step1_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_course_step1_page(new_origin_restore_course_step1_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template(
                'local_coursetransfer/origin_restore_course/new_origin_restore_course_step1_page', $data);
    }

    /**
     * Render New Origin Restore Course Step2 Page.
     *
     * @param new_origin_restore_course_step2_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_course_step2_page(new_origin_restore_course_step2_page $page) {
        $data = $page->export_for_template($this);
        $data->htmlpagingbar = $this->get_html_paging_bar($data->paging, $page->get_paging_url(), 'page');
        return parent::render_from_template(
                'local_coursetransfer/origin_restore_course/new_origin_restore_course_step2_page', $data);
    }

    /**
     * Render New Origin Restore Course Step3 Page.
     *
     * @param new_origin_restore_course_step3_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_course_step3_page(new_origin_restore_course_step3_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template(
                'local_coursetransfer/origin_restore_course/new_origin_restore_course_step3_page', $data);
    }

    /**
     * Render New Origin Restore Course Step4 Page.
     *
     * @param new_origin_restore_course_step4_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_course_step4_page(new_origin_restore_course_step4_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template(
                'local_coursetransfer/origin_restore_course/new_origin_restore_course_step4_page', $data);
    }

    /**
     * Render New Origin Restore Course Step5 Page.
     *
     * @param new_origin_restore_course_step5_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_course_step5_page(new_origin_restore_course_step5_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template(
                'local_coursetransfer/origin_restore_course/new_origin_restore_course_step5_page', $data);
    }

    /**
     * Render Origin Restore Category Page.
     *
     * @param origin_restore_category_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_restore_category_page(origin_restore_category_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_restore_category/origin_restore_category_page', $data);
    }

    /**
     * Render New Origin Restore Category Step1 Page.
     *
     * @param new_origin_restore_category_step1_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_category_step1_page(new_origin_restore_category_step1_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template(
                'local_coursetransfer/origin_restore_category/new_origin_restore_category_step1_page', $data);
    }

    /**
     * Render New Origin Restore Category Step2 Page.
     *
     * @param new_origin_restore_category_step2_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_category_step2_page(new_origin_restore_category_step2_page $page) {
        $data = $page->export_for_template($this);
        $data->htmlpagingbar = $this->get_html_paging_bar($data->paging, $page->get_paging_url(), 'page');
        return parent::render_from_template(
                'local_coursetransfer/origin_restore_category/new_origin_restore_category_step2_page', $data);
    }

    /**
     * Render New Origin Restore Category Step3 Page.
     *
     * @param new_origin_restore_category_step3_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_category_step3_page(new_origin_restore_category_step3_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template(
                'local_coursetransfer/origin_restore_category/new_origin_restore_category_step3_page', $data);
    }

    /**
     * Render New Origin Restore Category Step4 Page.
     *
     * @param new_origin_restore_category_step4_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_new_origin_restore_category_step4_page(new_origin_restore_category_step4_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template(
                'local_coursetransfer/origin_restore_category/new_origin_restore_category_step4_page', $data);
    }

    /**
     * Render Activities Component.
     *
     * @param activities_component $component
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_activities_component(activities_component $component) {
        $data = $component->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/tables/table_activities_component', $data);
    }

    /**
     * Render Category Course Component.
     *
     * @param category_course_component $component
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_category_course_component(category_course_component $component) {
        $data = $component->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/tables/table_category_course_component', $data);
    }

    /**
     * Render Configuration Component.
     *
     * @param configuration_component $component
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_configuration_component(configuration_component $component) {
        $data = $component->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/tables/table_configuration_component', $data);
    }

    /**
     * Render Actions Site Component.
     *
     * @param actions_site_component $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_actions_site_component(actions_site_component $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/components/actions_site_component', $data);
    }

    /**
     * Render Origin Remove Page.
     *
     * @param origin_remove_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_remove_page(origin_remove_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_remove/origin_remove_page', $data);
    }

    /**
     * Render Origin Remove Page Step2.
     *
     * @param origin_remove_page_step2 $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_remove_page_step2(origin_remove_page_step2 $page) {
        $data = $page->export_for_template($this);
        $data->htmlpagingbar = $this->get_html_paging_bar($data->paging, $page->get_paging_url(), 'page');
        return parent::render_from_template('local_coursetransfer/origin_remove/origin_remove_page_step2', $data);
    }

    /**
     * Render Origin Remove Page Step3.
     *
     * @param origin_remove_page_step3 $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_remove_page_step3(origin_remove_page_step3 $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_remove/origin_remove_page_step3', $data);
    }

    /**
     * Render Origin Remove Page Category Step2.
     *
     * @param origin_remove_page_cat_step2 $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_remove_page_cat_step2(origin_remove_page_cat_step2 $page) {
        $data = $page->export_for_template($this);
        $data->htmlpagingbar = $this->get_html_paging_bar($data->paging, $page->get_paging_url(), 'page');
        return parent::render_from_template('local_coursetransfer/origin_remove/origin_remove_page_cat_step2', $data);
    }

    /**
     * Render Origin Remove Page Category Step3.
     *
     * @param origin_remove_page_cat_step3 $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_origin_remove_page_cat_step3(origin_remove_page_cat_step3 $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/origin_remove/origin_remove_page_cat_step3', $data);
    }

    /**
     * Render Log Page.
     *
     * @param log_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_log_page(log_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/logs/log_page', $data);
    }

    /**
     * Render Logs Category Remove Request page.
     *
     * @param logs_category_remove_request_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_logs_category_remove_request_page(logs_category_remove_request_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/logs/logs_category_remove_request_page', $data);
    }

    /**
     * Render Logs Category Remove Response page.
     *
     * @param logs_category_remove_response_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_logs_category_remove_response_page(logs_category_remove_response_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/logs/logs_category_remove_response_page', $data);
    }

    /**
     * Render Logs Category Request page.
     *
     * @param logs_category_request_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_logs_category_request_page(logs_category_request_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/logs/logs_category_request_page', $data);
    }

    /**
     * Render Logs Category Response page.
     *
     * @param logs_category_response_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_logs_category_response_page(logs_category_response_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/logs/logs_category_response_page', $data);
    }

    /**
     * Render Logs Course Remove Request page.
     *
     * @param logs_course_remove_request_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_logs_course_remove_request_page(logs_course_remove_request_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/logs/logs_course_remove_request_page', $data);
    }

    /**
     * Render Logs Course Request page.
     *
     * @param logs_course_request_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_logs_course_request_page(logs_course_request_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/logs/logs_course_request_page', $data);
    }

    /**
     * Render Logs Course Remove Response page.
     *
     * @param logs_course_remove_response_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_logs_course_remove_response_page(logs_course_remove_response_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/logs/logs_course_remove_response_page', $data);
    }
    /**
     * Render Logs Course Remove Response page.
     *
     * @param logs_course_response_page $page
     * @return bool|string
     * @throws moodle_exception
     */
    public function render_logs_course_response_page(logs_course_response_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_coursetransfer/logs/logs_course_response_page', $data);
    }

    /**
     * Returns the html for a paging bar or empty string if no need for paging.
     *
     * @param stdClass $paging
     * @param string $url
     * @param string $pageparam
     * @return string|null
     */
    public function get_html_paging_bar(stdClass $paging, string $url, string $pageparam): ?string {
        if (! $paging) {
            return null;
        }
        $pagingbar = new paging_bar($paging->totalcount, $paging->page, $paging->perpage, $url, $pageparam);
        return $this->render($pagingbar);
    }
}
