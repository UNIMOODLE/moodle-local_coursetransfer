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
        ORIGIN_REMOVE_STEP3: 'local_coursetransfer_origin_remove_step3'
    };

    let ACTIONS = {
        SELECT: '[data-action="select"]',
        COURSE: '[data-action="course"]',
        NEXT: '[data-action="next"]',
        DESTINY: '[data-action="destiny"]',
        CHECK: '[data-action="check"]',
        CHECK_ACT: '[data-action="act-check"]',
        RESTORE: '[data-action="execute-restore"]'
    };

    /**
     * @param {String} region
     * @param {Integer} site
     *
     * @constructor
     */
    function originRemoveStep3(region, site) {
        this.node = $(region);
        this.site = site;
        this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_remove_page'));
        if (this.data) {
            this.data.courses.forEach(function(course) {
                let courseid = parseInt(course.id);
                let row = 'tr[data-action="course"][data-courseid="' + courseid + '"]';
                $(row).removeClass('hidden');
            });
            this.node.find(ACTIONS.RESTORE).on('click', this.clickNext.bind(this));
        } else {
            let alertbox = this.node.find(".alert");
            let errors = [{code: '100088', msg: 'error_session_cache'}];
            this.renderErrors(errors, alertbox);
            this.node.find(ACTIONS.RESTORE).prop('disabled', true);
        }
        this.node.find('#origin_schedule').on('click', this.clickSchedule.bind(this));
    }

    originRemoveStep3.prototype.clickNext = function(e) {
        this.node.find(ACTIONS.RESTORE).prop('disabled', true);
        let alertbox = this.node.find(".alert");
        let nextruntime = $('#origin_schedule_datetime').val();
        if ($('#origin_schedule').prop('checked')) {
            if (nextruntime === '') {
                nextruntime  = 0;
            } else {
                nextruntime  = new Date(nextruntime).getTime();
            }
        } else {
            nextruntime  = 0;
        }
        const request = {
            methodname: SERVICES.ORIGIN_REMOVE_STEP3,
            args: {
                siteurl: parseInt(this.site),
                courses: this.data.courses,
                nextruntime: nextruntime
            }
        };
        let that = this;
        Ajax.call([request])[0].done(function(response) {
            if (response.success) {
                window.location.href = response.data.nexturl;
            } else if (!response.success) {
                that.renderErrors(response.errors, alertbox);
            } else {
                let errors = [{code: '100091', msg: 'error_not_controlled'}];
                that.renderErrors(errors, alertbox);
            }
        }).fail(function(fail) {
            let errors = [];
            if (fail.error) {
                errors.push({code: '100092', msg: fail.error});
            } else if (fail.message) {
                errors.push({code: '100093', msg: fail.message});
            } else {
                errors = [{code: '100094', msg: 'error_not_controlled'}];
            }
            that.renderErrors(errors, alertbox);
        });
    };

    originRemoveStep3.prototype.renderErrors = function(errors, alertbox) {
        let errorString = "";
        alertbox.removeClass("hidden");
        errors.forEach(error => {
            errorString += 'Error (' + error.code + '): ' + error.msg + '<br>';
        });
        alertbox.append(errorString);
    };

    originRemoveStep3.prototype.clickSchedule = function(e) {
        if (this.node.find('#origin_schedule').is(':checked')) {
            this.node.find('#origin_schedule_datetime').attr('disabled', false);
        } else {
            this.node.find('#origin_schedule_datetime').attr('disabled', true);
        }
    };

    originRemoveStep3.prototype.node = null;

    return {
        /**
         * @param {String} region
         * @param {Integer} site
         * @return {originRemoveStep3}
         */
        initOriginRemoveStep3: function(region, site) {
            // eslint-disable-next-line babel/new-cap
            return new originRemoveStep3(region, site);
        }
    };
});
