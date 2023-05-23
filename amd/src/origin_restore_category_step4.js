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
            RESTORE_CATEGORY_STEP4: 'local_coursetransfer_new_origin_restore_category_step4'
        };

        let ACTIONS = {
            RESTORE: '[data-action="execute-restore"]'
        };

        /**
         * @param {String} region
         * @param {Integer} site
         * @constructor
         */
        function restoreCategoryStep4(region, site) {
            this.node = $(region);
            this.restoreid = $("[data-restoreid]").attr("data-restoreid");
            this.destinyid = $("[data-destinyid]").attr("data-destinyid");
            this.site = site;
            this.node.find(ACTIONS.RESTORE).on('click', this.clickNext.bind(this));
            let storageid = this.node.find('[data-region="session-storage"]');
            this.sessiondata = JSON.parse(sessionStorage.getItem(storageid.data('session')));
            let courses = this.sessiondata.category.courses;
            let courseinputs = $('input[type="checkbox"]');
            courseinputs.prop('disabled', true);
            courseinputs.prop('checked', false);
            courses.forEach(function(course) {
                let selector = '[data-courseid="' + course.id + '"]';
                let courserow = $(selector);
                courserow.prop('checked', true);
            });
        }

        restoreCategoryStep4.prototype.clickNext = function(e) {
            let self = this; // Store the reference of this.
            let alertbox = this.node.find(".alert");
            let siteurl = this.site;
            let categoryid = this.restoreid;
            let destinyid = this.destinyid;
            const request = {
                methodname: SERVICES.RESTORE_CATEGORY_STEP4,
                args: {
                    siteurl: siteurl,
                    categoryid: categoryid,
                    destinyid: destinyid,
                    courses: this.sessiondata.category.courses,
                }
            };
            Ajax.call([request])[0].done(function(response) {
                if (response.success) {
                    window.location.href = response.data.nexturl;
                } else if (!response.success) {
                    self.renderErrors(response.errors, alertbox);
                } else {
                    let errors = [{'code': '064893', 'msg': 'error_not_controlled'}];
                    self.renderErrors(errors, alertbox);
                }
            }).fail(function(fail) {
                let errors = [{'code': '064896', 'msg': fail.message}];
                self.renderErrors(errors, alertbox);
            });
        };

        /**
         *
         * @param {String[]} errors
         * @param {String} alertbox
         */
        restoreCategoryStep4.prototype.renderErrors = function(errors, alertbox) {
            let errorString = "";
            alertbox.removeClass("hidden");
            errors.forEach(error => {
                errorString += 'Error (' + error.code + '): ' + error.msg + '<br>';
            });
            alertbox.append(errorString);
        };

        restoreCategoryStep4.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @param {Integer} site
             * @return {restoreCategoryStep4}
             */
            initRestoreCategoryStep4: function(region, site) {
                // eslint-disable-next-line babel/new-cap
                return new restoreCategoryStep4(region, site);
            }
        };
    });
