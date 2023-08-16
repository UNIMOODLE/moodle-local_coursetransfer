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
        ORIGIN_REMOVE_CAT_STEP3: 'local_coursetransfer_origin_remove_cat_step3'
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
     * @param {Integer} removeid
     *
     * @constructor
     */
    function originRemoveCatStep3(region, site, removeid) {
        this.node = $(region);
        this.site = site;
        this.removeid = removeid;
        this.node.find(ACTIONS.RESTORE).on('click', this.clickNext.bind(this));
    }

    originRemoveCatStep3.prototype.clickNext = function(e) {
        this.node.find(ACTIONS.RESTORE).prop('disabled', true);
        let alertbox = this.node.find(".alert");
        const request = {
            methodname: SERVICES.ORIGIN_REMOVE_CAT_STEP3,
            args: {
                siteurl: this.site,
                catid: this.removeid,
            }
        };
        let that = this;
        Ajax.call([request])[0].done(function(response) {
            if (response.success) {
                window.location.href = response.data.nexturl;
            } else if (!response.success) {
                that.renderErrors(response.errors, alertbox);
            } else {
                let errors = [{code: '100291', msg: 'error_not_controlled'}];
                that.renderErrors(errors, alertbox);
            }
        }).fail(function(fail) {
            let errors = [];
            if (fail.error) {
                errors.push({code: '100292', msg: fail.error});
            } else if (fail.message) {
                errors.push({code: '100293', msg: fail.message});
            } else {
                errors = [{code: '100294', msg: 'error_not_controlled'}];
            }
            that.renderErrors(errors, alertbox);
        });
    };

    originRemoveCatStep3.prototype.renderErrors = function(errors, alertbox) {
        let errorString = "";
        alertbox.removeClass("hidden");
        errors.forEach(error => {
            errorString += 'Error (' + error.code + '): ' + error.msg + '<br>';
        });
        alertbox.append(errorString);
    };

    originRemoveCatStep3.prototype.node = null;

    return {
        /**
         * @param {String} region
         * @param {Integer} site
         * @param {Integer} removeid
         * @return {originRemoveCatStep3}
         */
        initOriginRemoveCatStep3: function(region, site, removeid) {
            // eslint-disable-next-line babel/new-cap
            return new originRemoveCatStep3(region, site, removeid);
        }
    };
});
