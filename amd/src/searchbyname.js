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
            SEARCH: '[data-action="search"]',
            RESET: '[data-action="reset"]'
        };

        let INPUTS = {
            VALUE: '[data-input="value"]'
        };

        /**
         * @param {String} region
         *
         * @constructor
         */
        function searchbyname(region) {
            this.node = $(region);
            this.node.find(ACTIONS.SEARCH).on('click', this.search.bind(this));
            this.node.find(ACTIONS.RESET).on('click', this.reset.bind(this));
            this.node.find(INPUTS.VALUE).on('keyup', this.change.bind(this));
        }

        /**
         */
        searchbyname.prototype.search = function() {
            let url = new URL(window.location.href);
            let params = new URLSearchParams(url.search);
            params.set('search', this.node.find(INPUTS.VALUE).val());
            params.delete('page');
            url.search = params.toString();
            window.location.href = url.toString();
        };

        /**
         */
        searchbyname.prototype.reset = function() {
            let url = new URL(window.location.href);
            let params = new URLSearchParams(url.search);
            params.delete('search');
            params.delete('page');
            url.search = params.toString();
            window.location.href = url.toString();
        };

        /**
         */
        searchbyname.prototype.change = function() {
            if(this.node.find(INPUTS.VALUE).val().length > 2) {
                this.node.find(ACTIONS.SEARCH).attr('disabled', false);
            } else {
                this.node.find(ACTIONS.SEARCH).attr('disabled', true);
            }
        };

        searchbyname.prototype.node = null;

        return {
            /**
             * @param {String} region
             * @return {searchbyname}
             */
            initSearchbyname: function(region) {
                // eslint-disable-next-line babel/new-cap
                return new searchbyname(region);
            }
        };
    });
