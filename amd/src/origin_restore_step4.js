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
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v4 or later
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
            ORIGIN_RESTORE_STEP4: 'local_coursetransfer_origin_restore_step4'
        };

        let ACTIONS = {
            RESTORE: '[data-action="execute-restore"]',
            CATEGORIES: '[data-action="destiny-category"]'
        };

        /**
         * @param {String} region
         * @param {Integer} site
         *
         * @constructor
         */
        function originRestoreStep4(region, site) {
            this.node = $(region);
            this.site = site;
            this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_page'));
            if (this.data) {
                this.data.courses.forEach(function(course) {
                    let courseid = parseInt(course.courseid);
                    let destinyid = parseInt(course.destinyid);
                    let seldestiny = '[data-action="destiny"][data-courseid="' + courseid + '"] option[value="' + destinyid + '"]';
                    $(seldestiny).prop('selected', true);
                    let row = 'tr[data-action="course"][data-courseid="' + courseid + '"]';
                    $(row).prop('selected', true).removeClass('hidden');
                    if (destinyid === 0) {
                        $('[data-action="destiny-category"][data-courseid="' + courseid + '"]').removeClass('hidden');
                    }
                });
                this.data.configuration.forEach(function(config) {
                    let item = $('#' + config.name);
                    item.prop('disabled', true);
                    item.prop('checked', config.selected);
                });
                this.node.find(ACTIONS.RESTORE).on('click', this.clickNext.bind(this));
            } else {
                let alertbox = this.node.find(".alert");
                let errors = [{code: '100078', msg: 'error_session_cache'}];
                this.renderErrors(errors, alertbox);
            }
            this.node.find('[data-action="destiny-category"]').on('change', this.changeCategory.bind(this));
        }

        originRestoreStep4.prototype.changeCategory = function(e) {
            let $select = $(e.currentTarget);
            let catvalue = $select.val();
            let catcourse = $select.data('courseid');
            let courses = this.data.courses;
            let newcourses = [];
            courses.forEach(function(course) {
                if (course.courseid === catcourse) {
                    course.categorydestiny = catvalue;
                }
                newcourses.push(course);
            });
            this.data.courses = newcourses;
            sessionStorage.setItem('local_coursetransfer_restore_page', JSON.stringify(this.data));
        };

        originRestoreStep4.prototype.clickNext = function(e) {
            this.node.find(ACTIONS.RESTORE).prop('disabled', true);

            let configuration = [];
            this.data.configuration.forEach(function(config) {
                configuration[config.name] = config.selected;
            });

            let alertbox = this.node.find(".alert");
            let config = {
                destiny_merge_activities: false,
                destiny_remove_activities: false,
                destiny_remove_groups: false,
                destiny_remove_enrols: false,
                origin_enrol_users: false,
                origin_remove_course: false
            };
            if (configuration['destiny_merge_activities']) {
                config.destiny_merge_activities = configuration['destiny_merge_activities'];
            }
            if (configuration['destiny_remove_activities']) {
                config.destiny_remove_activities = configuration['destiny_remove_activities'];
            }
            if (configuration['destiny_remove_groups']) {
                config.destiny_remove_groups = configuration['destiny_remove_groups'];
            }
            if (configuration['destiny_remove_enrols']) {
                config.destiny_remove_enrols = configuration['destiny_remove_enrols'];
            }
            if (configuration['origin_enrol_users']) {
                config.origin_enrol_users = configuration['origin_enrol_users'];
            }
            if (configuration['origin_remove_course']) {
                config.origin_remove_course = configuration['origin_remove_course'];
            }
            const request = {
                methodname: SERVICES.ORIGIN_RESTORE_STEP4,
                args: {
                    siteurl: this.site,
                    courses: this.data.courses,
                    configuration: config,
                }
            };
            let that = this;
            Ajax.call([request])[0].done(function(response) {
                if (response.success) {
                    window.location.href = response.data.nexturl;
                } else if (!response.success) {
                    that.renderErrors(response.errors, alertbox);
                } else {
                    let errors = [{code: '100071', msg: 'error_not_controlled'}];
                    that.renderErrors(errors, alertbox);
                }
            }).fail(function(fail) {
                let errors = [];
                if (fail.error) {
                    errors.push({code: '100072', msg: fail.error});
                } else if (fail.message) {
                    errors.push({code: '100073', msg: fail.message});
                } else {
                    errors = [{code: '100074', msg: 'error_not_controlled'}];
                }
                that.renderErrors(errors, alertbox);
            });
        };

        /**
         *
         * @param {String[]} errors
         * @param {String} alertbox
         */
        originRestoreStep4.prototype.renderErrors = function(errors, alertbox) {
            let errorString = "";
            alertbox.removeClass("hidden");
            errors.forEach(error => {
                errorString += 'Error (' + error.code + '): ' + error.msg + '<br>';
            });
            alertbox.append(errorString);
        };

        originRestoreStep4.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @param {Integer} site
             * @return {originRestoreStep4}
             */
            initRestoreStep4: function(region, site) {
                // eslint-disable-next-line babel/new-cap
                return new originRestoreStep4(region, site);
            }
        };
    });
