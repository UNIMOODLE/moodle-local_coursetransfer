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
        REMOVE_COURSE_STEP1: 'local_coursetransfer_origin_remove_step1'
    };

    let ACTIONS = {
        NEXT: '[data-action="next"]'
    };

    /**
     * @param {String} region
     *
     * @constructor
     */
    function originRemove(region) {
        this.node = $(region);
        this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
    }

    originRemove.prototype.clickNext = function(e) {
        let self = this; // Store the reference of this.
        let alertbox = this.node.find(".alert");
        let siteurl = this.node.find("#id_origin_site option:selected").val();
        let type = this.node.find("#id_course_categories option:selected").val();
        const request = {
            methodname: SERVICES.REMOVE_COURSE_STEP1,
            args: {
                siteurl: siteurl,
                type: type
            }
        };
        Ajax.call([request])[0].done(function(response) {
            if (response.success) {
                window.location.href = response.data.nexturl;
            } else if (!response.success) {
                self.renderErrors(response.errors, alertbox);
            } else {
                let errors = [{'code': '100021', 'msg': 'error_not_controlled'}];
                self.renderErrors(errors, alertbox);
            }
        }).fail(function(fail) {
            let errors = [{'code': '100022', 'msg': fail.message}];
            self.renderErrors(errors, alertbox);
        });
    };

    /**
     *
     * @param {String[]} errors
     * @param {String} alertbox
     */
    originRemove.prototype.renderErrors = function(errors, alertbox) {
        let errorString = "";
        alertbox.removeClass("hidden");
        errors.forEach(error => {
            errorString += 'Error (' + error.code + '): ' + error.msg + '<br>';
        });
        alertbox.append(errorString);
    };

    originRemove.prototype.node = null;

    return {
        /**
         * @param {String} region
         * @return {originRemove}
         */
        initOriginRemove: function(region) {
            // eslint-disable-next-line babel/new-cap
            return new originRemove(region);
        }
    };
});
