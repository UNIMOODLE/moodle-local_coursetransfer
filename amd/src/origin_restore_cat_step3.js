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
            CAT_SELECT: '[data-action="select"]',
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
        function originRestoreCatStep3(region) {
            this.node = $(region);
            this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_cat_page'));
            if (this.data) {
                if (this.data.targetid) {
                    let targetid = parseInt(this.data.targetid);
                    this.node.find(ACTIONS.TARGET).val(targetid);
                }
                if (this.data.configuration) {
                    this.data.configuration.forEach(function(config) {
                        let item = $('#' + config.name);
                        item.prop('checked', config.selected);
                    });
                }
            }
            this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
            this.node.find('#origin_schedule').on('click', this.clickSchedule.bind(this));

        }

        originRestoreCatStep3.prototype.clickNext = function(e) {
            this.data.targetid = parseInt(this.node.find(ACTIONS.TARGET).val());
            let configuration = [];
            let checkboxes = $('.configuration-checkbox');
            checkboxes.each(function() {
                if ($(this).attr("id") === 'origin_schedule_datetime') {
                    let datetime = $(this).val();
                    configuration.push({"name": $(this).attr("id"), "value": datetime});
                } else {
                    configuration.push({"name": $(this).attr("id"), "selected": $(this).prop('checked')});
                }
            });
            this.data.configuration = configuration;
            sessionStorage.setItem('local_coursetransfer_restore_cat_page', JSON.stringify(this.data));

            let currentUrl = $(location).attr('href');
            let url = new URL(currentUrl);
            url.searchParams.set('step', '4');
            window.location.href = url.href;
        };

        originRestoreCatStep3.prototype.clickSchedule = function(e) {
            if (this.node.find('#origin_schedule').is(':checked')) {
                this.node.find('#origin_schedule_datetime').attr('disabled', false);
            } else {
                this.node.find('#origin_schedule_datetime').attr('disabled', true);
            }
        };

        originRestoreCatStep3.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @return {originRestoreCatStep3}
             */
            initRestoreCatStep3: function(region) {
                // eslint-disable-next-line babel/new-cap
                return new originRestoreCatStep3(region);
            }
        };
    });
