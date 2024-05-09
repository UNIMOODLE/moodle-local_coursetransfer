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
 * @module     local_coursetransfer/origin_restore_step2
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
            TARGET: '[data-action="target"]',
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
            this.DOMregion = this.node[0];
            this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_page'), JSONutil.reviver);
            console.log('Initial data: ', this.data);
            if (this.data !== null) {
                this.data.courses.forEach(function(course) {
                    let courseid = parseInt(course.courseid);
                    let targetid = parseInt(course.targetid);
                    $(ACTIONS.COURSE_SELECT + '[data-courseid="' + courseid + '"]').prop("checked", true);
                    let seltarget = '[data-action="target"][data-courseid="' + courseid + '"] option[value="' + targetid + '"]';
                    $(seltarget).prop('selected', true);
                });
            } else {
                this.data = {
                    courses: new Map(), configuration: []
                };
                sessionStorage.setItem('local_coursetransfer_restore_page', JSON.stringify(this.data, JSONutil.replacer));
            }
            if (this.data.courses.size > 0) {
                this.node.find(ACTIONS.NEXT).removeAttr('disabled');
            } else {
                this.node.find(ACTIONS.NEXT).prop("disabled", true);
            }
            this.node.find(ACTIONS.COURSE_SELECT).on('click', this.selectCourse.bind(this));
            this.node.find(ACTIONS.TARGET).on('change', this.selectTarget.bind(this));
            this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
        }

        originRestoreStep2.prototype.selectCourse = function(e) {
            let item = e.target;
            let courseid = item.dataset.courseid;
            if (item.checked) {
                let target = this.DOMregion.querySelector('[data-action="target"][data-courseid="' + courseid + '"]').value;
                let course = {
                    courseid: courseid, targetid: target, categorytarget: 0
                };
                this.data.courses.set(courseid.toString(), course);
                this.node.find(ACTIONS.NEXT).removeAttr('disabled');
            } else {
                this.data.courses.delete(courseid.toString());
                if (this.data.courses.size < 1) {
                    this.node.find(ACTIONS.NEXT).prop("disabled", true);
                }
            }
            sessionStorage.setItem('local_coursetransfer_restore_page', JSON.stringify(this.data, JSONutil.replacer));
        };

        originRestoreStep2.prototype.selectTarget = function(e) {
            let item = e.target;
            let courseid = item.dataset.courseid;
            let origin = this.DOMregion.querySelector('[data-action="select"][data-courseid="' + courseid + '"]');

            if (origin.checked) {
                let target = item.value;
                let course = {
                    courseid: courseid, targetid: target, categorytarget: 0
                };
                this.data.courses.set(courseid, course);
                sessionStorage.setItem('local_coursetransfer_restore_page', JSON.stringify(this.data, JSONutil.replacer));
            }
        };

        originRestoreStep2.prototype.clickNext = function(e) {
            let currentUrl = $(location).attr('href');
            let url = new URL(currentUrl);
            url.searchParams.set('step', '3');
            window.location.href = url.href;
        };


        originRestoreStep2.prototype.node = null;
        originRestoreStep2.prototype.DOMregion = null;


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
