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
 *
 * @module     local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-disable no-unused-vars */
/* eslint-disable no-console */

define([
        'jquery',
        'core/str',
        'core/ajax',
        'core/templates'
    ], function($, Str, Ajax, Templates) {
        "use strict";

        let ACTIONS = {
            COURSE_SELECT: '[data-action="select"]',
            COURSE: '[data-action="course"]',
            NEXT: '[data-action="next"]',
            CHECK: '[data-action="check"]',
            CHECK_ACT: '[data-action="act-check"]'
        };


        /**
         * @param {String} region
         * @param {String} nexturl
         *
         * @constructor
         */
        function restoreCategoryStep3(region, nexturl) {
            this.node = $(region);
            this.nextURL = nexturl;
            this.data = {
                category: {
                    id: parseInt($("[data-category-id]").attr("data-category-id")),
                    name: $("[data-category-name]").attr("data-category-name"),
                    idnumber: $("[data-category-idnumber]").attr("data-category-idnumber"),
                    parentname: $("[data-category-parentname]").attr("data-category-parentname"),
                    courses: []
                }
            };
            this.node.find(ACTIONS.COURSE_SELECT).on('click', this.selectCourse.bind(this));
            this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
        }

        restoreCategoryStep3.prototype.selectCourse = function(e) {
            this.node.find(ACTIONS.CATEGORY).removeClass('selected');
            let checked = $("input:checked");
            let td = checked.parent().addClass('selected');
            td.parent().addClass('selected');
            this.node.find(ACTIONS.NEXT).removeAttr('disabled');
        };

        restoreCategoryStep3.prototype.clickNext = function(e) {
            let selectedcourses = $('tr.selected');
            this.data.category.courses = [];
            let data = this.data;
            selectedcourses.each(function() {
                let course = {
                    'id': $(this).find('#courseid').text()
                };
                data.category.courses.push(course);
            });
            let storageid = this.node.find('[data-region="session-storage"]');
            sessionStorage.removeItem(storageid.data('session'));
            if (data.category.courses.length > 0) {
                sessionStorage.setItem(storageid.data('session'), JSON.stringify(data));
                let url = new URL(this.nextURL);
                url = url.href.replaceAll('&amp;', "&");
                window.location.href = url;
            } else {
                this.renderError();
            }
        };

        restoreCategoryStep3.prototype.renderError = function(alertbox) {
            alertbox.text("Error (x): Select a category");
            alertbox.removeClass("hidden");
        };

        restoreCategoryStep3.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @param {String} nexturl
             * @return {restoreCategoryStep2}
             */
            initRestoreCategoryStep3: function(region, nexturl) {
                return new restoreCategoryStep3(region, nexturl);
            }
        };
    });
