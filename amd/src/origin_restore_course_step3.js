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
        COURSE_SELECT: '[data-action="select"]',
        COURSE: '[data-action="course"]',
        NEXT: '[data-action="next"]',
        CHECK: '[data-action="check"]',
        CHECK_ACT: '[data-action="act-check"]',
        SELECT_ALL: '[data-action="select-all"]'
    };

    /**
     * @param {String} region
     *
     * @constructor
     */
    function restoreCourseStep3(region) {
        this.node = $(region);
        this.data = {
            course: {
                id: parseInt($("[data-course-id]").attr("data-course-id")),
                fullname: $("[data-course-fullname]").attr("data-course-fullname"),
                shortname: $("[data-course-shortname]").attr("data-course-shortname"),
                idnumber: $("[data-course-idnumber]").attr("data-course-idnumber"),
                categoryid:  parseInt($("[data-course-categoryid]").attr("data-course-categoryid")),
                categoryname: $("[data-course-categoryname]").attr("data-course-categoryname"),
                backupsizeestimated:  parseInt($("[data-course-backupsizeestimated]").attr("data-course-backupsizeestimated")),
                sections: []
            }
        };
        this.node.find(ACTIONS.NEXT).on('click', this.clickNext.bind(this));
        this.node.find(ACTIONS.CHECK).on('click', this.clickCheck.bind(this));
        this.node.find(ACTIONS.CHECK_ACT).on('click', this.clickActCheck.bind(this));
        this.node.find(ACTIONS.SELECT_ALL).on('click', this.clickSelectAll.bind(this));
    }

    restoreCourseStep3.prototype.clickActCheck = (e) => {
        if (e.currentTarget.checked) {
            e.currentTarget.parentElement.parentElement.parentElement.parentElement.parentElement.children[0]
                .children[0].children[0].checked = true;
        }
    };

    restoreCourseStep3.prototype.clickCheck = (e) => {
        if ([...e.currentTarget.parentElement.parentElement.parentElement.children].length > 1) {
            let activities = [...e.currentTarget.parentElement.parentElement.parentElement.children[1].children[0].children];
            activities.shift();
            activities.forEach(v => {
                v.children[0].children[0].checked = e.currentTarget.checked;
            });
        }
    };

    restoreCourseStep3.prototype.clickNext = function(e) {
        let rawSections = [...$(".sections-table").children()];
        rawSections.shift();
        this.data.course.sections = rawSections.map(v => {
            let finalActivities = [];
            if (v.children.length >= 2) {
                let children = [...v.children];
                let activities = [...children[1].children[0].children];
                activities.shift();
                finalActivities = activities.map(v => {
                    return {
                        selected: v.children[0].children[0].checked,
                        cmid: v.getAttribute("data-activity-cmid"),
                        name: v.getAttribute("data-activity-name"),
                        instance: v.getAttribute("data-activity-instance"),
                        modname: v.getAttribute("data-activity-modname")
                    };
                });
            }
            return {
                selected: v.children[0].children[0].children[0].checked,
                sectionnum: v.getAttribute("data-section-sectionnum"),
                sectionid: v.getAttribute("data-section-sectionid"),
                sectionname: v.getAttribute("data-section-sectionname"),
                activities: finalActivities
            };
        });
        console.log(this.data);
        let userid = 1;
        sessionStorage.setItem($("[data-course-sessionStorageId]").attr("data-course-sessionStorageId"), JSON.stringify(this.data));
    };

    restoreCourseStep3.prototype.clickSelectAll = function(e) {
        if (e.currentTarget.checked) {
            $('.sections-table :checkbox').each(function() {
                this.checked = true;
            });
        } else {
            $('.sections-table :checkbox').each(function() {
                this.checked = false;
            });
        }
    };

    restoreCourseStep3.prototype.node = null;

    return {
        /**
         * @param {String} region
         * @return {restoreCourseStep3}
         */
        initRestoreCourseStep3: function(region) {
            // eslint-disable-next-line babel/new-cap
            return new restoreCourseStep3(region);
        }
    };
});
