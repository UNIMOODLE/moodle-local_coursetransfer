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
            CATEGORY_SELECT: '[data-action="select"]',
            CATEGORY: '[data-action="category"]',
            NEXT: '[data-action="next"]'
        };

        /**
         * @param {String} region
         * @param {String} nexturl
         *
         * @constructor
         */
        function restoreCategoryStep2(region, nexturl) {
            this.node = $(region);
            this.nextURL = nexturl;
            this.node.find(ACTIONS.CATEGORY_SELECT).on('click', this.selectCategory.bind(this));
            this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
        }

        restoreCategoryStep2.prototype.selectCategory = function(e) {
            this.node.find(ACTIONS.CATEGORY).removeClass('selected');
            let checked = $("input:checked");
            let td = checked.parent().addClass('selected');
            td.parent().addClass('selected');
            this.node.find(ACTIONS.NEXT).removeAttr('disabled');
        };

        restoreCategoryStep2.prototype.clickNext = function(e) {
            let selectedcategory = $('tr.selected');
            let categoryid = selectedcategory.find('#categoryid').text();
            let alertbox = this.node.find(".alert");
            if (!categoryid) {
                this.renderError(alertbox);
                return;
            }
            alertbox.addClass("hidden");
            let url = new URL(this.nextURL);
            url.searchParams.append('restoreid', categoryid);
            window.location.href = url.href.replace(/&amp%3B/g, "&");
        };

        restoreCategoryStep2.prototype.renderError = function(alertbox) {
            alertbox.text("Error (x): Select a category");
            alertbox.removeClass("hidden");
        };

        restoreCategoryStep2.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @param {String} nexturl
             * @return {restoreCategoryStep2}
             */
            initRestoreCategoryStep2: function(region, nexturl) {
                return new restoreCategoryStep2(region, nexturl);
            }
        };
    });
