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
        'core/templates',
        'local_coursetransfer/JSONutil'
    ], function($, Str, Ajax, Templates, JSONutil) {
        "use strict";

        let REGIONS = {
            CATFORNEW: '[data-region="category-for-new"]',
            COURSEFOREXIST: '[data-region="course-for-exist"]',
            CATTARGETNAME: '[data-region="cattargetname"]',
            CHANGEBUTTON: '[data-region="change-button"]',
            COURSES_EMPTY: '[data-region="courses-empty"]',
            COURSES_CONTAINER: '[data-region="courses-found-container"]'
        };

        let ACTIONS = {
            NEWOREXIST: '[data-action="new-or-exist"]',
            TARGETCAT: '[data-action="target-category"]',
            SEARCHCOURSE: '[data-acton="search-course"]',
            SELECT_COURSE: '[data-action="select-course-destination"]',
        };

        let SERVICES = {
            SEARCH_COURSE: 'local_coursetransfer_dest_search_course_name'
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
            this.node.find(REGIONS.COURSES_EMPTY).hide();
            this.node.find(ACTIONS.NEWOREXIST).on('change', this.neworexist.bind(this));
            this.node.find(ACTIONS.TARGETCAT).on('change', this.targetcat.bind(this));
            this.node.find(ACTIONS.SEARCHCOURSE).on('keyup', this.search.bind(this));
            this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_page'), JSONutil.reviver);
        }

        /**
         */
        selectcourse.prototype.neworexist = function() {
            let value = parseInt(this.node.find(ACTIONS.NEWOREXIST).val());
            if (value === 0) {
                this.node.find(REGIONS.CATFORNEW).show();
                this.node.find(REGIONS.COURSEFOREXIST).hide();
                this.node.find(REGIONS.CATTARGETNAME).show();
                this.node.find(REGIONS.CHANGEBUTTON).find('#in-new-course').show();
                this.node.find(REGIONS.CHANGEBUTTON).find('#course-selected').hide();
            } else {
                this.node.find(REGIONS.CATFORNEW).hide();
                this.node.find(REGIONS.COURSEFOREXIST).show();
                this.node.find(REGIONS.CATTARGETNAME).hide();
                this.node.find(REGIONS.CHANGEBUTTON).find('#in-new-course').hide();
                this.node.find(REGIONS.CHANGEBUTTON).find('#course-selected').show();
            }
        };

        /**
         */
        selectcourse.prototype.targetcat = function() {
            this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_page'), JSONutil.reviver);
            let name = this.node.find(ACTIONS.TARGETCAT).children("option:selected").text();
            let catid = this.node.find(ACTIONS.TARGETCAT).val();
            this.node.find(REGIONS.CATTARGETNAME).text(name);
            this.node.find(REGIONS.CHANGEBUTTON).data('categorytarget', catid);
            let that = this;
            let newcourses = [];
            this.data.courses.forEach(function(course) {
                if (parseInt(course.courseid) == that.courseid) {
                    course.categorytarget = catid;
                    course.targetid = 0;
                }
                newcourses.push(course);
            });
            this.data.courses = newcourses;
            sessionStorage.setItem('local_coursetransfer_restore_page', JSON.stringify(this.data, JSONutil.replacer));
        };

        selectcourse.prototype.selectcourse = function(e) {
            this.data = JSON.parse(sessionStorage.getItem('local_coursetransfer_restore_page'), JSONutil.reviver);
            let buttontarget = $(e.currentTarget);
            let courseid = buttontarget.data('courseid');
            let courseregion = this.node.find(REGIONS.COURSES_CONTAINER).find('[data-region="course-item-' + courseid + '"]');
            let coursename = courseregion.find('.coursename').text();
            this.node.find(ACTIONS.SEARCHCOURSE).val(coursename);
            this.node.find(REGIONS.CHANGEBUTTON).find('#course-selected').text(coursename);
            let that = this;
            let newcourses = [];
            this.data.courses.forEach(function(course) {
                if (parseInt(course.courseid) == that.courseid) {
                    course.targetid = courseid;
                    course.categorytarget = 0;
                }
                newcourses.push(course);
            });
            this.data.courses = newcourses;
            sessionStorage.setItem('local_coursetransfer_restore_page', JSON.stringify(this.data, JSONutil.replacer));
        };

        /**
         */
        selectcourse.prototype.search = function() {
            let that = this;
            let text = this.node.find(ACTIONS.SEARCHCOURSE).val();
            if (text.length > 2) {
                const request = {
                    methodname: SERVICES.SEARCH_COURSE,
                    args: {
                        text: text
                    }
                };
                Ajax.call([request])[0].done(function(response) {
                    if (response.success) {
                        if (response.data.length === 0) {
                            that.node.find(REGIONS.COURSES_EMPTY).show();
                        } else {
                            that.node.find(REGIONS.COURSES_CONTAINER).empty();
                            response.data.forEach( function(valor, indice, array) {
                                let course = '<div class="courses-item" data-region="course-item-' + valor.id + '">' +
                                    '<span class="coursename">' + valor.fullname + ' (' + valor.id + ')</span>' +
                                    '<button data-courseid="' + valor.id +
                                    '" data-action="select-course-destination-' + valor.id + '" ' +
                                    'class="btn btn-primary btn-sm" type="submit">' +
                                    '<i class="fa fa-check-square" aria-hidden="true"></i></button>' +
                                    '</div>';
                                that.node.find(REGIONS.COURSES_EMPTY).hide();
                                that.node.find(REGIONS.COURSES_CONTAINER).append(course);
                                let dataaction = '[data-action="select-course-destination-' + valor.id + '"]';
                                that.node.find(dataaction).on('click', that.selectcourse.bind(that));
                            });
                        }
                    } else {
                        console.log(response);
                    }
                }).fail(function(fail) {
                    console.log(fail);
                });
            }
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
