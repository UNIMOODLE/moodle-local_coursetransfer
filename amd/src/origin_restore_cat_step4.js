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
            ORIGIN_RESTORE_STEP4: 'local_coursetransfer_origin_restore_cat_step4'
        };

        let ACTIONS = {
            CAT_SELECT: '[data-action="select"]',
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
        function originRestoreCatStep4(region, site) {
            this.node = $(region);
            this.site = site;
            this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_cat_page'));
            if (this.data) {
                if (this.data.destinyid) {
                    let destinyid = parseInt(this.data.destinyid);
                    this.node.find(ACTIONS.DESTINY).val(destinyid);
                    this.node.find(ACTIONS.DESTINY).prop('disabled', true);
                }
                if (this.data.configuration) {
                    this.data.configuration.forEach(function(config) {
                        let item = $('#' + config.name);
                        item.prop('checked', config.selected);
                        item.prop('disabled', true);
                    });
                }
                this.node.find(ACTIONS.RESTORE).on('click', this.clickNext.bind(this));
            }
        }

        originRestoreCatStep4.prototype.clickNext = function(e) {
            this.node.find(ACTIONS.RESTORE).prop('disabled', true);

            let configuration = [];
            this.data.configuration.forEach(function(config) {
                configuration[config.name] = config.selected;
            });
            let alertbox = this.node.find(".alert");
            const request = {
                methodname: SERVICES.ORIGIN_RESTORE_STEP4,
                args: {
                    siteurl: this.site,
                    catid: this.data.catid,
                    destinyid: this.data.destinyid,
                    configuration: {
                        destiny_merge_activities: configuration['destiny_merge_activities'],
                        destiny_remove_activities: configuration['destiny_remove_activities'],
                        destiny_remove_groups: configuration['destiny_remove_groups'],
                        destiny_remove_enrols: configuration['destiny_remove_enrols'],
                        origin_enrolusers: configuration['origin_enrolusers']
                    },
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
