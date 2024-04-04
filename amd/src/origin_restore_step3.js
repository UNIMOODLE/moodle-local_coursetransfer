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
 * @module     local_coursetransfer/origin_restore_step3
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
        'core/templates',
        'local_coursetransfer/JSONutil'
    ], function($, Str, Ajax, Templates, JSONutil) {
        "use strict";

        let ACTIONS = {
            COURSE_SELECT: '[data-action="select"]',
            COURSE: '[data-action="course"]',
            NEXT: '[data-action="next"]',
            DESTINY: '[data-action="target"]',
            CHECK: '[data-action="check"]',
            CHECK_ACT: '[data-action="act-check"]'
        };

        /**
         * @param {String} region
         *
         * @constructor
         */
        function originRestoreStep3(region) {
            this.node = $(region);
            this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_page'), JSONutil.reviver);
            if (this.data !== null) {
                this.data.courses.forEach(function(course) {
                    let courseid = parseInt(course.courseid);
                    let targetid = parseInt(course.targetid);
                    $(ACTIONS.COURSE_SELECT + '[data-courseid="' + courseid + '"]').prop("checked", true);
                    let seltarget = '[data-action="target"][data-courseid="' + courseid + '"] option[value="' + targetid + '"]';
                    $(seltarget).prop('selected', true);
                });
                this.data.configuration.forEach(function(config) {
                    $('#' + config.name).prop('checked', config.selected);
                });
            }
            this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
            this.node.find('#origin_schedule').on('click', this.clickSchedule.bind(this));
        }

        originRestoreStep3.prototype.clickNext = function(e) {
            let checkboxes = $('.configuration-checkbox');
            let configuration = [];
            checkboxes.each(function() {
                if ($(this).attr("id") === 'origin_schedule_datetime') {
                    let datetime = $(this).val();
                    configuration.push({"name": $(this).attr("id"), "value": datetime});
                } else {
                    configuration.push({"name": $(this).attr("id"), "selected": $(this).prop('checked')});
                }
            });
            this.data.configuration = configuration;
            sessionStorage.setItem('local_coursetransfer_restore_page', JSON.stringify(this.data, JSONutil.replacer));
            // Generate a form with selected courses so next step will return selected courses only.
            let form = this.generateForm();
            form.submit();
        };

        originRestoreStep3.prototype.generateForm = function() {
            let currentUrl = $(location).attr('href');
            let url = new URL(currentUrl);
            url.searchParams.set('step', '4');
            let coursesForm = document.createElement("form");
            coursesForm.action = url.href;
            coursesForm.method = "POST";
            let input = [];
            this.data.courses.forEach(function(course, index) {
                input[index] = document.createElement("INPUT");
                input[index].name = 'courseids[]';
                input[index].value = course.courseid;
                input[index].type = 'hidden';
                coursesForm.appendChild(input[index]);
            });

            document.body.appendChild(coursesForm);
            return coursesForm;
        };

        originRestoreStep3.prototype.clickSchedule = function(e) {
            if (this.node.find('#origin_schedule').is(':checked')) {
                this.node.find('#origin_schedule_datetime').attr('disabled', false);
            } else {
                this.node.find('#origin_schedule_datetime').attr('disabled', true);
            }
        };

        originRestoreStep3.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @return {originRestoreStep3}
             */
            initRestoreStep3: function(region) {
                // eslint-disable-next-line babel/new-cap
                return new originRestoreStep3(region);
            }
        };
    });
