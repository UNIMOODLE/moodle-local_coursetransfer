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
            NEXT: '[data-action="next"]'
        };

        /**
         * @param {String} region
         *
         * @constructor
         */
        function restoreCourseStep4(region) {
            this.node = $(region);
            this.restoreid = $("[data-restoreid]").attr("data-restoreid");
            this.sessionData = sessionStorage.getItem('local_coursetransfer_' + 1 + this.restoreid);
            this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
        }

        restoreCourseStep4.prototype.clickNext = function(e) {
            let configuration = [];
            let checkboxes = $('.configuration-checkbox');
            checkboxes.each(function() {
                configuration.push({"name": $(this).attr("id"), "selected": $(this).prop('checked')});
            });
            let sessionData = JSON.parse(this.sessionData);
            sessionData.course.configuration = configuration;
            sessionStorage.setItem('local_coursetransfer_' + 1 + this.restoreid, JSON.stringify(sessionData));
        };

        restoreCourseStep4.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @return {restoreCourseStep4}
             */
            initRestoreCourseStep4: function(region) {
                // eslint-disable-next-line babel/new-cap
                return new restoreCourseStep4(region);
            }
        };
    });
