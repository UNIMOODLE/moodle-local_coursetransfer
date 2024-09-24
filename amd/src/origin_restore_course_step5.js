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
            this.targetid = $("[data-targetid]").attr("data-targetid");
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
            this.node.find(ACTIONS.RESTORE).prop('disabled', true);
            let self = this; // Store the reference of this.
            let alertbox = this.node.find(".alert");
            let siteurl = this.site;
            let courseid = this.restoreid;
            let targetid = this.targetid;
            let sessiondata = JSON.parse(sessionStorage.getItem($("[data-course-sessionStorageId]")
                .attr("data-course-sessionStorageId")));
            let configuration = {};
            sessiondata.course.configuration.forEach(function(config) {
                configuration[config.name] = config.selected;
            });

            let config = {
                target_merge_activities: false,
                target_remove_activities: false,
                target_remove_groups: false,
                target_remove_enrols: false,
                origin_enrol_users: false,
                origin_remove_course: false
            };
            if (configuration['target_merge_activities']) {
                config.target_merge_activities = configuration['target_merge_activities'];
            }
            if (configuration['target_remove_activities']) {
                config.target_remove_activities = configuration['target_remove_activities'];
            }
            if (configuration['target_remove_groups']) {
                config.target_remove_groups = configuration['target_remove_groups'];
            }
            if (configuration['target_remove_enrols']) {
                config.target_remove_enrols = configuration['target_remove_enrols'];
            }
            if (configuration['origin_enrol_users']) {
                config.origin_enrol_users = configuration['origin_enrol_users'];
            }
            if (configuration['origin_remove_course']) {
                config.origin_remove_course = configuration['origin_remove_course'];
            }
            const request = {
                methodname: SERVICES.RESTORE_COURSE_STEP5,
                args: {
                    siteurl: siteurl,
                    courseid: courseid,
                    targetid: targetid,
                    configuration: config,
                    sections: sessiondata.course.sections,
                }
            };
            console.log(request);
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
