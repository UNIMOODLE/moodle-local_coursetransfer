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
        SITE_ADD: 'local_coursetransfer_site_add',
        SITE_EDIT: 'local_coursetransfer_site_edit',
        SITE_REMOVE: 'local_coursetransfer_site_remove',
        SITE_TEST: 'local_coursetransfer_site_test',
    };

    let ACTIONS = {
        CREATE: '[data-action="create"]',
        EDIT: '[data-action="edit"]',
        REMOVE: '[data-action="remove"]',
        TEST: '[data-action="test"]',
    };

    let REGIONS = {
        CREATE : '#createSite',
        EDIT : '#editSite-',
        TEST_OK : '[data-region="test-ok"]',
        TEST_KO : '[data-region="test-ko"]',
        ERROR_MSG : '[data-region="error-msg"]'
    };

    /**
     * @param {String} region
     * @param {String} type
     *
     * @constructor
     */
    function sites(region, type) {
        this.node = $(region);
        this.type = type;
        console.log('Sites');
        this.node.find(ACTIONS.CREATE).on('click', this.clickCreate.bind(this));
        this.node.find(ACTIONS.EDIT).on('click', this.clickEdit.bind(this));
        this.node.find(ACTIONS.REMOVE).on('click', this.clickRemove.bind(this));
        this.node.find(ACTIONS.TEST).on('click', this.clickTest.bind(this));
    }

    sites.prototype.clickCreate = function(e) {
        let button = $(e.currentTarget);
        button.attr('disabled', true);
        let createregion = this.node.find(REGIONS.CREATE);
        let host = createregion.find('#host').val();
        let token = createregion.find('#token').val();
        let errormsg = this.node.find(REGIONS.ERROR_MSG);

        const request = {
            methodname: SERVICES.SITE_ADD,
            args: {
                type: this.type,
                host: host.trim(),
                token: token.trim(),
            }
        };
        Ajax.call([request])[0].done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                console.log(response);
                errormsg.text(response.errors[0].msg);
                errormsg.show();
            }
        }).fail(function(fail) {
            console.log(fail);
        });
    };

    sites.prototype.clickEdit = function(e) {
        let button = $(e.currentTarget);
        let siteid = button.data('id');
        let editregion = this.node.find(REGIONS.EDIT + siteid);
        let host = editregion.find('#host').val();
        let token = editregion.find('#token').val();
        let errormsg = this.node.find(REGIONS.ERROR_MSG);

        button.attr('disabled', true);
        const request = {
            methodname: SERVICES.SITE_EDIT,
            args: {
                type: this.type,
                id: siteid,
                host: host.trim(),
                token: token.trim(),
            }
        };
        Ajax.call([request])[0].done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                console.log(response);
                errormsg.text(response.errors[0].msg);
                errormsg.show();
            }
        }).fail(function(fail) {
            console.log(fail);
        });
    };

    sites.prototype.clickTest = function(e) {
        let $button = $(e.currentTarget);
        let siteid = $button.data('id');
        $button.attr('disabled', true);
        $button.addClass('btn-light', true);
        $button.removeClass('btn-success', true);
        $button.removeClass('btn-danger', true);
        let $buttonerror = $('[data-target="#error-' + siteid + '"]');
        $buttonerror.addClass('hidden');
        $buttonerror.data('content', '');
        $button.find(REGIONS.TEST_OK).addClass('hidden');
        $button.find(REGIONS.TEST_KO).addClass('hidden');
        const request = {
            methodname: SERVICES.SITE_TEST,
            args: {
                type: this.type,
                id: siteid
            }
        };
        Ajax.call([request])[0].done(function(response) {
            $button.attr('disabled', false);
            console.log(response);
            if (response.success) {
                $button.find(REGIONS.TEST_OK).removeClass('hidden');
                $button.addClass('btn-success', true);
                $button.removeClass('btn-light', true);
            } else {
                $button.find(REGIONS.TEST_KO).removeClass('hidden');
                $buttonerror.removeClass('hidden');
                $button.addClass('btn-danger', true);
                $button.removeClass('btn-light', true);
                $buttonerror.data('content', response.error.msg);
            }
        }).fail(function(fail) {
            console.log(fail);
        });
    };

    sites.prototype.clickRemove = function(e) {
        let button = $(e.currentTarget);
        button.attr('disabled', true);
        let siteid = button.data('id');
        const request = {
            methodname: SERVICES.SITE_REMOVE,
            args: {
                type: this.type,
                id: siteid
            }
        };
        Ajax.call([request])[0].done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                console.log(response);
            }
        }).fail(function(fail) {
            console.log(fail);
        });
    };

    sites.prototype.node = null;

    return {
        /**
         * @param {String} region
         * @param {String} type
         * @return {sites}
         */
        initSites: function(region, type) {
            // eslint-disable-next-line babel/new-cap
            return new sites(region, type);
        }
    };
});
