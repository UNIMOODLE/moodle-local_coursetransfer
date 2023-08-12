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
            NEXT: '[data-action="next"]',
            DESTINY: '[data-action="destiny"]',
            CHECK: '[data-action="check"]',
            CHECK_ACT: '[data-action="act-check"]'
        };

        /**
         * @param {String} region
         *
         * @constructor
         */
        function originRestoreStep2(region) {
            this.node = $(region);
            let data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_page'));
            if (data.courses) {
                data.courses.forEach(function(course) {
                    let courseid = parseInt(course.courseid);
                    let destinyid = parseInt(course.destinyid);
                    $(ACTIONS.COURSE_SELECT + '[data-courseid="' + courseid + '"]').prop( "checked", true );
                    let seldestiny = '[data-action="destiny"][data-courseid="' + courseid + '"] option[value="' + destinyid + '"]';
                    $(seldestiny).prop('selected', true);
                });
            }
            this.selectCourse();
            this.node.find(ACTIONS.COURSE_SELECT).on('click', this.selectCourse.bind(this));
            this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
        }

        originRestoreStep2.prototype.selectCourse = function(e) {
            let selected = false;
            let items = this.node.find(ACTIONS.COURSE_SELECT);
            items.each(function(i, item) {
                if($(item).prop('checked')) {
                    selected = true;
                }
            });
            if (selected) {
                this.node.find(ACTIONS.NEXT).removeAttr('disabled');
            } else {
                this.node.find(ACTIONS.NEXT).prop( "disabled", true );
            }
        };

        originRestoreStep2.prototype.clickNext = function(e) {
            let courses = [];
            let items = this.node.find(ACTIONS.COURSE_SELECT);
            items.each(function(i, item) {
                let courseid = $(item).data('courseid');
                if ($(item).prop('checked')) {
                    let destiny = $('[data-action="destiny"][data-courseid="' + courseid + '"]').val();
                    let course = {
                        courseid: courseid, destinyid: destiny
                    };
                    courses.push(course);
                }
            });
            let data = {
                courses: courses, config: { }
            };
            sessionStorage.setItem('local_coursetransfer_restore_page', JSON.stringify(data));

            let currentUrl = $(location).attr('href');
            let url = new URL(currentUrl);
            url.searchParams.set('step', '3');
            window.location.href = url.href;
        };


        originRestoreStep2.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @return {originRestoreStep2}
             */
            initRestoreStep2: function(region) {
                // eslint-disable-next-line babel/new-cap
                return new originRestoreStep2(region);
            }
        };
    });
