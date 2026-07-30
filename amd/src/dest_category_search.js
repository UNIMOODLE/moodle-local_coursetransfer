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
 * AJAX handler for core/form-autocomplete: searches local destination
 * categories server-side (via local_coursetransfer_dest_search_category_name)
 * so the destination-category picker scales to sites with thousands of
 * categories, instead of dumping every option into a plain <select>.
 *
 * Used as the `ajax` module argument of `core/form-autocomplete`.enhance().
 *
 * @module     local_coursetransfer/dest_category_search
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax'], function(Ajax) {
    "use strict";

    return {
        /**
         * Map the web service rows to the {value, label} shape the
         * autocomplete expects.
         *
         * @param {String} selector The autocomplete select selector (unused).
         * @param {Object[]} results Rows returned by transport() success.
         * @return {Object[]} [{value, label}]
         */
        processResults: function(selector, results) {
            return (results || []).map(function(row) {
                return {value: String(row.id), label: row.name};
            });
        },

        /**
         * Query the search web service and hand the rows to the autocomplete.
         *
         * @param {String} selector The autocomplete select selector (unused).
         * @param {String} query The text typed by the user.
         * @param {Function} success Called with the result rows.
         * @param {Function} failure Called with the error on failure.
         */
        transport: function(selector, query, success, failure) {
            // The restore type (category|course) drives the id-0 label server-side
            // (category => "Top", course => default category); read it off the
            // enhanced <select> so a course card never shows "Top".
            var type = '';
            var el = document.querySelector(selector);
            if (el && el.dataset) {
                type = el.dataset.catType || '';
            }
            Ajax.call([{
                methodname: 'local_coursetransfer_dest_search_category_name',
                args: {text: query || '', type: type}
            }])[0].then(function(response) {
                success(response && response.data ? response.data : []);
                return response;
            }).catch(failure);
        }
    };
});
