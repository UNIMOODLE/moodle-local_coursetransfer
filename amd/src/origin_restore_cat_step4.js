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
            ORIGIN_RESTORE_STEP4: 'local_coursetransfer_origin_restore_cat_step4'
        };

        let ACTIONS = {
            CAT_SELECT: '[data-action="select"]',
            COURSE: '[data-action="course"]',
            NEXT: '[data-action="next"]',
            TARGET: '[data-action="target"]',
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
        function originRestoreCatStep4(region, site) {
            this.node = $(region);
            this.site = site;
            this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_cat_page'));
            if (this.data) {
                if (this.data.targetid) {
                    let targetid = parseInt(this.data.targetid);
                    this.node.find(ACTIONS.TARGET).val(targetid);
                    this.node.find(ACTIONS.TARGET).prop('disabled', true);
                }
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
            }
        }

        originRestoreCatStep4.prototype.clickNext = function(e) {
            this.node.find(ACTIONS.RESTORE).prop('disabled', true);

            let configuration = [];
            this.data.configuration.forEach(function(config) {
                if (config.name === 'origin_schedule_datetime') {
                    configuration[config.name] = config.value;
                } else {
                    configuration[config.name] = config.selected;
                }
            });
            let alertbox = this.node.find(".alert");
            let config = {
                origin_enrol_users: false,
                origin_remove_category: false,
                origin_schedule_datetime: 0
            };
            if (configuration['origin_enrol_users']) {
                config.origin_enrol_users = configuration['origin_enrol_users'];
            }
            if (configuration['origin_remove_category']) {
                config.origin_remove_category = configuration['origin_remove_category'];
            }
            if (configuration['origin_schedule']) {
                config.origin_schedule_datetime = new Date(configuration['origin_schedule_datetime']).getTime();
            }
            const request = {
                methodname: SERVICES.ORIGIN_RESTORE_STEP4,
                args: {
                    siteurl: this.site,
                    catid: this.data.catid,
                    targetid: this.data.targetid,
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
                    let errors = [{code: '100081', msg: 'error_not_controlled'}];
                    that.renderErrors(errors, alertbox);
                }
            }).fail(function(fail) {
                let errors = [];
                if (fail.error) {
                    errors.push({code: '100082', msg: fail.error});
                } else if (fail.message) {
                    errors.push({code: '100083', msg: fail.message});
                } else {
                    errors = [{code: '100084', msg: 'error_not_controlled'}];
                }
                that.renderErrors(errors, alertbox);
            });
        };

        /**
         *
         * @param {String[]} errors
         * @param {String} alertbox
         */
        originRestoreCatStep4.prototype.renderErrors = function(errors, alertbox) {
            let errorString = "";
            alertbox.removeClass("hidden");
            errors.forEach(error => {
                errorString += 'Error (' + error.code + '): ' + error.msg + '<br>';
            });
            alertbox.append(errorString);
        };

        originRestoreCatStep4.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @param {Integer} site
             * @return {originRestoreCatStep4}
             */
            initRestoreCatStep4: function(region, site) {
                // eslint-disable-next-line babel/new-cap
                return new originRestoreCatStep4(region, site);
            }
        };
    });
