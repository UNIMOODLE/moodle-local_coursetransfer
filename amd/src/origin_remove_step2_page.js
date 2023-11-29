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

    let ACTIONS = {
        COURSE_SELECT: '[data-action="select"]',
        COURSE: '[data-action="course"]',
        NEXT: '[data-action="next"]',
        DESTINY: '[data-action="destiny"]',
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
        let data = JSON.parse(sessionStorage.getItem('local_coursetransfer_remove_page'));
        if (data) {
            $(ACTIONS.CAT_SELECT + '[data-id="' + data.categoryid + '"]').prop( "checked", true );
        }
        this.selectCourse();
        this.node.find(ACTIONS.COURSE_SELECT).on('click', this.selectCourse.bind(this));
        this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
    }

    originRemoveStep2.prototype.selectCourse = function(e) {
        let selected = false;
        let items = this.node.find(ACTIONS.COURSE_SELECT);
        items.each(function(i, item) {
            if($(item).prop('checked')) {
                selected = true;
            }
        });
        if (selected) {
            this.node.find(ACTIONS.NEXT).removeAttr('disabled');
        } else {
            this.node.find(ACTIONS.NEXT).prop( "disabled", true );
        }
    };

    originRemoveStep2.prototype.clickNext = function(e) {
        let courses = [];
        let items = this.node.find(ACTIONS.COURSE_SELECT);
        items.each(function(i, item) {
            let courseid = $(item).data('courseid');
            if ($(item).prop('checked')) {
                let course = {
                    id: courseid
                };
                courses.push(course);
            }
        });
        let data = {
            courses: courses
        };
        sessionStorage.setItem('local_coursetransfer_remove_page', JSON.stringify(data));

        let currentUrl = $(location).attr('href');
        let url = new URL(currentUrl);
        url.searchParams.set('step', '3');
        window.location.href = url.href;
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
