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
        function originRestoreCatStep2(region) {
            this.node = $(region);
            let data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_cat_page'));
            if (data && data.catid) {
                let catid = parseInt(data.catid);
                $(ACTIONS.CAT_SELECT + '[data-id="' + catid + '"]').prop( "checked", true );
            }
            this.selectCourse();
            this.node.find(ACTIONS.CAT_SELECT).on('click', this.selectCourse.bind(this));
            this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
        }

        originRestoreCatStep2.prototype.selectCourse = function(e) {
            let selected = false;
            let items = this.node.find(ACTIONS.CAT_SELECT);
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

        originRestoreCatStep2.prototype.clickNext = function(e) {
            let items = this.node.find(ACTIONS.CAT_SELECT);
            let catid = 0;
            items.each(function(i, item) {
                if ($(item).prop('checked')) {
                    catid = $(item).data('id');
                }
            });
            let data = {
                catid: catid, destinyid: 0, configuration: []
            };
            sessionStorage.setItem('local_coursetransfer_restore_cat_page', JSON.stringify(data));

            let currentUrl = $(location).attr('href');
            let url = new URL(currentUrl);
            url.searchParams.set('step', '3');
            url.searchParams.set('restoreid', catid);
            window.location.href = url.href;
        };

        originRestoreCatStep2.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @return {originRestoreCatStep2}
             */
            initRestoreCatStep2: function(region) {
                // eslint-disable-next-line babel/new-cap
                return new originRestoreCatStep2(region);
            }
        };
    });
