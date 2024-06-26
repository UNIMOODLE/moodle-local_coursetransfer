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
    'core/templates',
    'local_coursetransfer/JSONutil'
], function($, Str, Ajax, Templates, JSONutil) {
    "use strict";

    let SERVICES = {
        ORIGIN_RESTORE_STEP4: 'local_coursetransfer_origin_restore_step4'
    };

    let ACTIONS = {
        RESTORE: '[data-action="execute-restore"]',
        CATEGORIES: '[data-action="target-category"]'
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
        this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_page'), JSONutil.reviver);
        console.log('Step 4 data: ', this.data);
        if (this.data) {
            if (this.data.configuration) {
                this.data.configuration.forEach(function(config) {
                    let item = $('#' + config.name);
                    if (config.name === 'origin_schedule_datetime') {
                        item.val(config.value);
                    } else {
                        item.prop('disabled', true);
                        item.prop('checked', config.selected);
                    }
                });
                this.data.configuration.forEach(function(config) {
                    if (config.name === 'origin_schedule') {
                        if (!config.selected) {
                            $('#origin_schedule_datetime').val(null);
                        }
                    }
                });
                this.data.configuration.forEach(function(config) {
                    if (config.name === 'origin_schedule_datetime') {
                        if (!config.value) {
                            $('#origin_schedule').prop('checked', false);
                        }
                    }
                });
            }
            this.node.find(ACTIONS.RESTORE).on('click', this.clickNext.bind(this));
        } else {
            let alertbox = this.node.find(".alert");
            let errors = [{code: '100078', msg: 'error_session_cache'}];
            this.renderErrors(errors, alertbox);
        }
    }

    originRestoreStep4.prototype.clickNext = function(e) {
        this.node.find(ACTIONS.RESTORE).prop('disabled', true);

        let configuration = [];
        this.data.configuration.forEach(function(config) {
            if (config.name === 'origin_schedule_datetime') {
                configuration[config.name] = config.value;
            } else {
                configuration[config.name] = config.selected;
            }
        });
        console.log('type of configuration: ', typeof configuration);
        let alertbox = this.node.find(".alert");
        let config = {
            target_merge_activities: false,
            target_remove_activities: false,
            target_remove_groups: false,
            target_remove_enrols: false,
            origin_enrol_users: false,
            origin_remove_course: false,
            origin_schedule_datetime: 0
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
        if (configuration['origin_schedule']) {
            config.origin_schedule_datetime = new Date(configuration['origin_schedule_datetime']).getTime();
        }
        const request = {
            methodname: SERVICES.ORIGIN_RESTORE_STEP4,
            args: {
                siteurl: this.site,
                courses: Array.from(this.data.courses.values()),
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