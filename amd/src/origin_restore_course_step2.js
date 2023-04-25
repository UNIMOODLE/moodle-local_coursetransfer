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
 * @package
 * @author  2022 3iPunt <https://www.tresipunt.com/>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
            NEXT: '[data-action="next"]'
        };

        /**
         * @param {String} region
         * @param {String} nexturl
         *
         * @constructor
         */
        function restoreCourseStep2(region, nexturl) {
            this.node = $(region);
            this.nextURL = nexturl;
            this.node.find(ACTIONS.COURSE_SELECT).on('click', this.selectCourse.bind(this));
            this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
        }

        restoreCourseStep2.prototype.selectCourse = function(e) {
            // Remove selected class from all courses.
            this.node.find(ACTIONS.COURSE).removeClass('selected');
            // Add selected class to the course selected.
            let checked = $("input:checked");
            let td = checked.parent().addClass('selected');
            td.parent().addClass('selected');
            this.node.find(ACTIONS.NEXT).removeAttr('disabled');
        };

        restoreCourseStep2.prototype.clickNext = function(e) {
            let selectedcourse = $('tr.selected');
            let courseid = selectedcourse.find('#courseid').text();
            let url = new URL(this.nextURL);
            url.searchParams.append('restoreid', courseid);
            window.location.href = url.href.replace(/&amp%3B/g, "&");
        };

        restoreCourseStep2.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @param {String} nexturl
             * @return {restoreCourseStep2}
             */
            initRestoreCourseStep2: function(region, nexturl) {
                // eslint-disable-next-line babel/new-cap
                return new restoreCourseStep2(region, nexturl);
            }
        };
    });
