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
    ], function ($, Str, Ajax, Templates) {
        "use strict";
        let SERVICES = {
            RESTORE_COURSE_STEP1: 'local_coursetransfer_new_origin_restore_course_step1'
        };

        let ACTIONS = {
            NEXT: '[data-action="next"]'
        };

        /**
         * @param {String} region
         *
         * @constructor
         */
        function restoreCourseStep1(region)
        {
            this.node = $(region);
            this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
        }

        restoreCourseStep1.prototype.clickNext = function (e) {
            const request = {
                methodname: SERVICES.RESTORE_COURSE_STEP1,
                args: {
                    siteurl: this.node.find("#id_origin_site option:selected").text(),
                }
            };
            Ajax.call([request])[0].done(function (response) {
                if (response.success) {
                    window.location.href = response.data.next_url;
                } else if (!response.success) {
                    this.renderErrors(response.data.errors); // TODO. Pintar errores (no entra nuna aqui se va a fail)
                } else {
                    $('#errorModal').modal("show");
                }
            }).fail(function (fail) {
                $('#errorModal').modal("show");
            });
        };

        /**
         *
         * @param {String[]} errors
         */
        restoreCourseStep1.prototype.renderErrors = function (errors) {
            console.log(errors);
        };

        restoreCourseStep1.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @return {restoreCourseStep1}
             */
            initRestoreCourseStep1: function (region) {
                // eslint-disable-next-line babel/new-cap
                return new restoreCourseStep1(region);
            }
        };
    });
