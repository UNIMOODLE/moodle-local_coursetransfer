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

        let REGIONS = {
            CATFORNEW: '[data-region="category-for-new"]',
            COURSEFOREXIST: '[data-region="course-for-exist"]',
            CATTARGETNAME: '[data-region="cattargetname"]',
            CHANGEBUTTON: '[data-region="change-button"]'
        };

        let ACTIONS = {
            NEWOREXIST: '[data-action="new-or-exist"]',
            TARGETCAT: '[data-action="target-category"]'
        };

        let INPUTS = {
            VALUE: '[data-input="value"]'
        };

        /**
         * @param {String} region
         * @param {Number} courseid
         *
         * @constructor
         */
        function selectcourse(region, courseid) {
            this.node = $(region);
            this.courseid = courseid;
            this.node.find(REGIONS.COURSEFOREXIST).hide();
            this.node.find(ACTIONS.NEWOREXIST).on('change', this.neworexist.bind(this));
            this.node.find(ACTIONS.TARGETCAT).on('change', this.targetcat.bind(this));
        }

        /**
         */
        selectcourse.prototype.neworexist = function() {
            let value = parseInt(this.node.find(ACTIONS.NEWOREXIST).val());
            if (value === 0) {
                this.node.find(REGIONS.CATFORNEW).show();
                this.node.find(REGIONS.COURSEFOREXIST).hide();
                this.node.find(REGIONS.CATTARGETNAME).show();
            } else {
                this.node.find(REGIONS.CATFORNEW).hide();
                this.node.find(REGIONS.COURSEFOREXIST).show();
                this.node.find(REGIONS.CATTARGETNAME).hide();
            }
        };

        /**
         */
        selectcourse.prototype.targetcat = function() {
            let name = this.node.find(ACTIONS.TARGETCAT).children("option:selected").text();
            let id = this.node.find(ACTIONS.TARGETCAT).val();
            this.node.find(REGIONS.CATTARGETNAME).text(name);
            this.node.find(REGIONS.CHANGEBUTTON).data('categorytarget', id);
        };

        selectcourse.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @param {Number} courseid
             * @return {selectcourse}
             */
            initSelectcourse: function(region, courseid) {
                // eslint-disable-next-line babel/new-cap
                return new selectcourse(region, courseid);
            }
        };
    });
