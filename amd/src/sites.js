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
        SITE_ADD: 'local_coursetransfer_site_add',
        SITE_EDIT: 'local_coursetransfer_site_edit',
        SITE_REMOVE: 'local_coursetransfer_site_remove',
    };

    let ACTIONS = {
        CREATE: '[data-action="create"]',
        EDIT: '[data-action="edit"]',
        REMOVE: '[data-action="remove"]',
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
    }

    sites.prototype.clickCreate = function(e) {
        let button = $(e.currentTarget);
        button.attr('disabled', true);
        let createregion = this.node.find('#createDestiny');
        let host = createregion.find('#host').val();
        let token = createregion.find('#token').val();

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
            }
        }).fail(function(fail) {
            console.log(fail);
        });
    };

    sites.prototype.clickEdit = function(e) {
        let button = $(e.currentTarget);
        let siteid = button.data('id');
        let editregion = this.node.find('#destinyEdit' + siteid);
        let host = editregion.find('#host').val();
        let token = editregion.find('#token').val();
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
