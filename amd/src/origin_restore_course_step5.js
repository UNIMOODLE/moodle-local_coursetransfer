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

        /**
         * @param {String} region
         *
         * @constructor
         */
        function restoreCourseStep5(region) {
            this.node = $(region);
            this.restoreid = $("[data-restoreid]").attr("data-restoreid");
            let sessiondata = JSON.parse(sessionStorage.getItem('local_coursetransfer_' + 1 + this.restoreid));
            let sections = sessiondata.course.sections;
            $('input[type="checkbox"]').prop('disabled', true);
            sections.forEach(function(section) {
                if (section.selected) {
                    let sectionrow = $("#section-" + section.sectionid);
                    console.log(sectionrow[0]);
                    sectionrow.addClass('selected');
                    sectionrow[0].children[0].children[0].children[0].checked = true;
                }
                let activities = section.activities;
                activities.forEach(function(activity) {
                    if (activity.selected) {
                        let activityrow = $("#activity-" + activity.cmid);
                        activityrow.addClass('selected');
                        activityrow[0].children[0].children[0].checked = true;
                    }
                });
            });
            let configuration = sessiondata.course.configuration;
            configuration.forEach(function(config) {
                if (config.selected) {
                    $("#" + config.name).prop('checked', true);
                }
            });
        }

        restoreCourseStep5.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @return {restoreCourseStep5}
             */
            initRestoreCourseStep5: function(region) {
                // eslint-disable-next-line babel/new-cap
                return new restoreCourseStep5(region);
            }
        };
    });
