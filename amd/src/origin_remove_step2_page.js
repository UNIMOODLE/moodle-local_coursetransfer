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
 * @module     local_coursetransfer/origin_remove_step2_page
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-disable no-unused-vars */
/* eslint-disable no-console */

define([
    'jquery',
    'local_coursetransfer/JSONutil'
], function($, JSONutil) {
    "use strict";

    let ACTIONS = {
        COURSE_SELECT: '[data-action="select"]',
        COURSE: '[data-action="course"]',
        NEXT: '[data-action="next"]',
        CHECK: '[data-action="check"]',
        CHECK_ACT: '[data-action="act-check"]'
    };

    /**
     * @param {String} region
     *
     * @constructor
     */
    function originRemoveStep2(region) {
        this.node = $(region);
        this.DOMregion = this.node[0];

        this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_remove_page'), JSONutil.reviver);
        if (this.data !== null) {
            this.data.courses.forEach(function(course) {
                let courseid = parseInt(course.id);
                $(ACTIONS.COURSE_SELECT + '[data-courseid="' + courseid + '"]').prop("checked", true);
            });
        } else {
            this.data = {
                courses: new Map()
            };
            sessionStorage.setItem('local_coursetransfer_remove_page', JSON.stringify(this.data, JSONutil.replacer));
        }
        console.log('Initial data:', this.data);
        if (this.data.courses.size > 0) {
            this.node.find(ACTIONS.NEXT).removeAttr('disabled');
        } else {
            this.node.find(ACTIONS.NEXT).prop("disabled", true);
        }
        this.node.find(ACTIONS.COURSE_SELECT).on('click', this.selectCourse.bind(this));
        this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
    }

    originRemoveStep2.prototype.selectCourse = function(e) {
        let item = e.target;
        let courseid = item.dataset.courseid;
        if (item.checked) {
            let course = {
                id: courseid
            };
            this.data.courses.set(courseid.toString(), course);
            this.node.find(ACTIONS.NEXT).removeAttr('disabled');
        } else {
            this.data.courses.delete(courseid.toString());
            if (this.data.courses.size < 1) {
                this.node.find(ACTIONS.NEXT).prop("disabled", true);
            }
        }
        sessionStorage.setItem('local_coursetransfer_remove_page', JSON.stringify(this.data, JSONutil.replacer));
    };

    originRemoveStep2.prototype.clickNext = function(e) {
        let form = this.generateForm();
        form.submit();
    };

    originRemoveStep2.prototype.generateForm = function() {
        let currentUrl = $(location).attr('href');
        let url = new URL(currentUrl);
        url.searchParams.set('step', '3');
        let coursesForm = document.createElement("form");
        coursesForm.action = url.href;
        coursesForm.method = "POST";
        let input = [];
        this.data.courses.forEach(function(course, index) {
            input[index] = document.createElement("INPUT");
            input[index].name = 'courseids[]';
            input[index].value = course.id;
            input[index].type = 'hidden';
            coursesForm.appendChild(input[index]);
        });

        document.body.appendChild(coursesForm);
        return coursesForm;
    };


    /**
     *
     * @param {String[]} errors
     * @param {String} alertbox
     */
    originRemoveStep2.prototype.renderErrors = function(errors, alertbox) {
        let errorString = "";
        alertbox.removeClass("hidden");
        errors.forEach(error => {
            errorString += 'Error (' + error.code + '): ' + error.msg + '<br>';
        });
        alertbox.append(errorString);
    };

    originRemoveStep2.prototype.node = null;
    originRemoveStep2.prototype.DOMregion = null;

    return {
        /**
         * @param {String} region
         * @return {originRemoveStep2}
         */
        initOriginRemoveStep2: function(region) {
            // eslint-disable-next-line babel/new-cap
            return new originRemoveStep2(region);
        }
    };
});
