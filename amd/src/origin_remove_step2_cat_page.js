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
    function originRemoveCatStep2(region) {
        this.node = $(region);
        let data = JSON.parse(sessionStorage.getItem('local_coursetransfer_remove_cat_page'));
        if (data) {
            $(ACTIONS.CAT_SELECT + '[data-id="' + data.categoryid + '"]').prop( "checked", true );
        }
        this.selectCat();
        this.node.find(ACTIONS.CAT_SELECT).on('click', this.selectCat.bind(this));
        this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
    }

    originRemoveCatStep2.prototype.selectCat = function(e) {
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

    originRemoveCatStep2.prototype.clickNext = function(e) {
        let category = null;
        let items = this.node.find(ACTIONS.CAT_SELECT);
        items.each(function(i, item) {
            let catid = $(item).data('id');
            if ($(item).prop('checked')) {
                category = catid;
            }
        });
        let data = {
            categoryid: category
        };
        sessionStorage.setItem('local_coursetransfer_remove_cat_page', JSON.stringify(data));

        let currentUrl = $(location).attr('href');
        let url = new URL(currentUrl);
        url.searchParams.set('step', '3');
        url.searchParams.set('removeid', category);
        window.location.href = url.href;
    };

    /**
     *
     * @param {String[]} errors
     * @param {String} alertbox
     */
    originRemoveCatStep2.prototype.renderErrors = function(errors, alertbox) {
        let errorString = "";
        alertbox.removeClass("hidden");
        errors.forEach(error => {
            errorString += 'Error (' + error.code + '): ' + error.msg + '<br>';
        });
        alertbox.append(errorString);
    };

    originRemoveCatStep2.prototype.node = null;

    return {
        /**
         * @param {String} region
         * @return {originRemoveCatStep2}
         */
        initOriginRemoveCatStep2: function(region) {
            // eslint-disable-next-line babel/new-cap
            return new originRemoveCatStep2(region);
        }
    };
});
