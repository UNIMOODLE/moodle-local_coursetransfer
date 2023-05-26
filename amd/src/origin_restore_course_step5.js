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
        let SERVICES = {
            RESTORE_COURSE_STEP5: 'local_coursetransfer_new_origin_restore_course_step5'
        };

        let ACTIONS = {
            RESTORE: '[data-action="execute-restore"]'
        };

        /**
         * @param {String} region
         * @param {Integer} site
         * @constructor
         */
        function restoreCourseStep5(region, site) {
            this.node = $(region);
            this.restoreid = $("[data-restoreid]").attr("data-restoreid");
            this.destinyid = $("[data-destinyid]").attr("data-destinyid");
            this.site = site;
            this.node.find(ACTIONS.RESTORE).on('click', this.clickNext.bind(this));
            let sessiondata = JSON.parse(sessionStorage.getItem($("[data-course-sessionStorageId]")
                .attr("data-course-sessionStorageId")));
            let sections = sessiondata.course.sections;
            $('input[type="checkbox"]').prop('disabled', true);
            $('input[type="radio"]').prop('disabled', true);
            sections.forEach(function(section) {
                if (section.selected) {
                    let sectionrow = $("#section-" + section.sectionid);
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

        restoreCourseStep5.prototype.clickNext = function(e) {
            let self = this; // Store the reference of this.
            let alertbox = this.node.find(".alert");
            let siteurl = this.site;
            let courseid = this.restoreid;
            let destinyid = this.destinyid;
            let sessiondata = JSON.parse(sessionStorage.getItem($("[data-course-sessionStorageId]")
                .attr("data-course-sessionStorageId")));
            let configuration = {};
            sessiondata.course.configuration.forEach(function(config) {
                configuration[config.name] = config.selected;
            });
            const request = {
                methodname: SERVICES.RESTORE_COURSE_STEP5,
                args: {
                    siteurl: siteurl,
                    courseid: courseid,
                    destinyid: destinyid,
                    configuration: configuration,
                    sections: sessiondata.course.sections,
                }
            };
            Ajax.call([request])[0].done(function(response) {
                if (response.success) {
                    window.location.href = response.data.nexturl;
                } else if (!response.success) {
                    self.renderErrors(response.errors, alertbox);
                } else {
                    let errors = [{'code': '100031', 'msg': 'error_not_controlled'}];
                    self.renderErrors(errors, alertbox);
                }
            }).fail(function(fail) {
                let errors = [{'code': '100032', 'msg': fail.message}];
                self.renderErrors(errors, alertbox);
            });
        };

        /**
         *
         * @param {String[]} errors
         * @param {String} alertbox
         */
        restoreCourseStep5.prototype.renderErrors = function(errors, alertbox) {
            let errorString = "";
            alertbox.removeClass("hidden");
            errors.forEach(error => {
                errorString += 'Error (' + error.code + '): ' + error.msg + '<br>';
            });
            alertbox.append(errorString);
        };

        restoreCourseStep5.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @param {Integer} site
             * @return {restoreCourseStep5}
             */
            initRestoreCourseStep5: function(region, site) {
                // eslint-disable-next-line babel/new-cap
                return new restoreCourseStep5(region, site);
            }
        };
    });
