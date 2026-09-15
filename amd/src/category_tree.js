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
 * Shared category-tree preview: fetches an origin category subtree via the
 * restore wizard web service and renders it as DOM (nested subcategories +
 * courses). Reused by the admin restore, per-category restore and remove
 * wizards. Built as DOM in JS on purpose: a recursive mustache partial is not
 * rendered by the JS template engine.
 *
 * @module     local_coursetransfer/category_tree
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax'
], function($, Ajax) {
    "use strict";

    /**
     * Recursively build the DOM for one category node.
     *
     * @param {Object} node Category node {name, idnumber, courses[], categories[]}.
     * @return {jQuery}
     */
    function buildNode(node) {
        var $ul = $('<ul>').addClass('ct-tree');
        var $li = $('<li>').addClass('ct-tree-cat');
        var $cat = $('<div>').addClass('ct-tree-label ct-tree-label-cat');
        $cat.append($('<i>').addClass('fa fa-folder-o').attr('aria-hidden', 'true'));
        $cat.append($('<span>').addClass('ct-tree-name').text(node.name || ''));
        if (node.idnumber) {
            $cat.append($('<span>').addClass('ct-tree-id').text(node.idnumber));
        }
        $li.append($cat);
        (node.courses || []).forEach(function(course) {
            var $c = $('<div>').addClass('ct-tree-label ct-tree-label-course');
            $c.append($('<i>').addClass('fa fa-graduation-cap').attr('aria-hidden', 'true'));
            $c.append($('<span>').addClass('ct-tree-name').text(course.fullname || ''));
            if (course.shortname) {
                $c.append($('<span>').addClass('ct-tree-id').text(course.shortname));
            }
            $li.append($c);
        });
        (node.categories || []).forEach(function(child) {
            $li.append(buildNode(child));
        });
        $ul.append($li);
        return $ul;
    }

    /**
     * Fetch an origin category subtree and render it into a container.
     *
     * @param {jQuery} $body Target container.
     * @param {Number} siteid Origin site id (position).
     * @param {Number} categoryid Origin category id.
     * @param {Object} strings Labels {loading, empty, error}.
     */
    function load($body, siteid, categoryid, strings) {
        strings = strings || {};
        $body.text(strings.loading || 'Loading…');
        Ajax.call([{
            methodname: 'local_coursetransfer_restore_wizard_get_category_tree',
            args: {siteid: siteid, categoryid: categoryid}
        }])[0].done(function(resp) {
            var node = null;
            if (resp.success && resp.tree) {
                try {
                    node = JSON.parse(resp.tree);
                } catch (e) {
                    node = null;
                }
            }
            if (!node) {
                $body.text(strings.empty || 'No subtree to show.');
                return;
            }
            $body.empty().append(buildNode(node));
        }).fail(function() {
            $body.text(strings.error || 'Could not load the tree.');
        });
    }

    return {
        build: buildNode,
        load: load
    };
});
