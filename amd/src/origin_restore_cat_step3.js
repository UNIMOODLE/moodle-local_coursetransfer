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
            CAT_SELECT: '[data-action="select"]',
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
        function originRestoreCatStep3(region) {
            this.node = $(region);
            this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_cat_page'));
            if (this.data) {
                if (this.data.destinyid) {
                    let destinyid = parseInt(this.data.destinyid);
                    this.node.find(ACTIONS.DESTINY).val(destinyid);
                }
                if (this.data.configuration) {
                    this.data.configuration.forEach(function(config) {
                        let item = $('#' + config.name);
                        item.prop('checked', config.selected);
                    });
                }
            }
            this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
        }

        originRestoreCatStep3.prototype.clickNext = function(e) {
            this.data.destinyid = parseInt(this.node.find(ACTIONS.DESTINY).val());
            let configuration = [];
            let checkboxes = $('.configuration-checkbox');
            checkboxes.each(function() {
                configuration.push({'name': $(this).attr('id'), 'selected': $(this).prop('checked')});
            });
            this.data.configuration = configuration;
            sessionStorage.setItem('local_coursetransfer_restore_cat_page', JSON.stringify(this.data));

            let currentUrl = $(location).attr('href');
            let url = new URL(currentUrl);
            url.searchParams.set('step', '4');
            window.location.href = url.href;
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
