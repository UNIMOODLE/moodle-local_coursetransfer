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
 * Teacher course-restore assistant (SPA-lite orchestrator).
 *
 * Drives the landing + 5-step wizard + done screens rendered by the
 * restore_course_page template. It reuses the shared restore_wizard web
 * services (get_sites, list_origin, get_sections) and submits through
 * restore_wizard_submit_course, which validates the course-context
 * capabilities. Every value coming from the web services is injected as a text
 * node (never as HTML) to avoid injection.
 *
 * @module     local_coursetransfer/restore_course_wizard
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/str'
], function($, Ajax, Str) {
    "use strict";

    var PERPAGE = 10;
    var TOTALSTEPS = 5;
    var SEARCHDEBOUNCE = 350;

    // FontAwesome icon per Moodle module name (fallback: puzzle piece).
    var MODICON = {
        forum: 'fa-comments-o', resource: 'fa-file-o', url: 'fa-link',
        page: 'fa-file-text-o', quiz: 'fa-question-circle-o', assign: 'fa-pencil-square-o',
        book: 'fa-book', folder: 'fa-folder-o', label: 'fa-tag', lesson: 'fa-graduation-cap',
        choice: 'fa-check-square-o', feedback: 'fa-commenting-o', glossary: 'fa-list-alt',
        wiki: 'fa-pencil', scorm: 'fa-cube', h5pactivity: 'fa-cube', data: 'fa-database',
        workshop: 'fa-users', chat: 'fa-comment-o', survey: 'fa-list-ol'
    };

    var STRINGKEYS = [
        'rw_loading', 'rw_cancel', 'rw_back', 'rw_next', 'rw_run', 'rw_clear',
        'rw_noresults', 'rw_pageinfo', 'rw_step_of', 'rw_submit_error',
        'rw_results', 'rw_results_note', 'rw_courses_pl', 'rw_recommended', 'rw_destructive',
        'rw_lbl_idnumber', 'rw_lbl_category', 'rw_users_on', 'rw_users_off',
        'rct_q_course_desc', 'rct_stat_sections', 'rct_stat_activities', 'rct_stat_size',
        'rct_review_from', 'rct_mode_merge', 'rct_mode_merge_desc', 'rct_mode_replace',
        'rct_mode_replace_desc', 'rct_confirm_destroy', 'rct_all', 'rct_selcount',
        'rct_summary', 'rct_sum_from', 'rct_sum_course', 'rct_sum_brings', 'rct_sum_how',
        'rct_brings_val', 'rct_with_users', 'rct_run',
        'rct_mode_merge_short', 'rct_mode_replace_short', 'rct_sec_empty'
    ];

    var Wizard = {

        /**
         * Entry point.
         *
         * @param {String} selector Root selector.
         */
        init: function(selector) {
            var self = this;
            this.$root = $(selector);
            if (!this.$root.length) {
                return;
            }
            this.courseid = parseInt(this.$root.attr('data-courseid'), 10) || 0;
            this.canmerge = this.$root.attr('data-canmerge') === '1';
            this.canreplace = this.$root.attr('data-canreplace') === '1';
            this.courseurl = this.$root.attr('data-courseurl') || '#';
            // Search page size from the plugin setting (default 5).
            this.perpage = parseInt(this.$root.attr('data-pagesize'), 10) || PERPAGE;
            this.S = {};
            this.sites = [];
            this.items = [];
            this.searchtimer = null;
            this.resetState();

            var strrequests = STRINGKEYS.map(function(key) {
                return {key: key, component: 'local_coursetransfer'};
            });
            Str.get_strings(strrequests).then(function(values) {
                STRINGKEYS.forEach(function(key, i) {
                    self.S[key] = values[i];
                });
                return self.bind();
            }).catch(function() {
                self.bind();
            });
        },

        /**
         * Reset in-memory state.
         */
        resetState: function() {
            var mode = '';
            if (this.canmerge) {
                mode = 'merge';
            } else if (this.canreplace) {
                mode = 'replace';
            }
            this.state = {
                view: 'landing',
                step: 0,
                siteid: null,
                sitename: '',
                courseid: null,
                coursename: '',
                coursemeta: '',
                coursecat: '',
                search: '',
                page: 0,
                pages: 1,
                total: 0,
                // Content.
                sections: [],
                sectionsloaded: false,
                sectionsloading: false,
                checked: {},
                // Sections with no activities are selected on their own (their
                // structure/summary is brought), keyed by section index.
                emptysecs: {},
                // Options.
                mode: mode,
                confirmdestroy: false,
                includeusers: false
            };
        },

        /**
         * Bind delegated events and show the landing.
         */
        bind: function() {
            var self = this;
            var $root = this.$root;

            $root.on('click', '[data-action="start"]', function() {
                self.start();
            });
            $root.on('click', '[data-action="cancel"]', function() {
                self.cancel();
            });
            $root.on('click', '[data-action="go-landing"]', function() {
                self.reloadLanding();
            });
            $root.on('click', '[data-action="back"]', function() {
                self.back();
            });
            $root.on('click', '[data-action="primary"]', function() {
                self.primary();
            });

            // Step 0.
            $root.on('click', '[data-action="toggle-origin"]', function() {
                self.toggleOrigin($(this));
            });

            // Step 1.
            $root.on('input', '[data-action="search"]', function() {
                var value = $(this).val();
                window.clearTimeout(self.searchtimer);
                self.searchtimer = window.setTimeout(function() {
                    self.state.search = value;
                    self.state.page = 0;
                    self.loadList();
                }, SEARCHDEBOUNCE);
            });
            $root.on('click', '[data-action="toggle-course"]', function(e) {
                if ($(e.target).closest('[data-noselect]').length) {
                    return;
                }
                self.toggleCourse($(this));
            });
            $root.on('keydown', '[data-action="toggle-course"]', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    self.toggleCourse($(this));
                }
            });
            $root.on('click', '[data-action="prev"]', function() {
                if (self.state.page > 0) {
                    self.state.page -= 1;
                    self.loadList();
                }
            });
            $root.on('click', '[data-action="next-page"]', function() {
                if (self.state.page < self.state.pages - 1) {
                    self.state.page += 1;
                    self.loadList();
                }
            });

            // Step 3.
            $root.on('click', '[data-action="mode"]', function() {
                self.setMode($(this).attr('data-mode'));
            });
            $root.on('click', '[data-action="toggle-confirm"]', function() {
                self.state.confirmdestroy = !self.state.confirmdestroy;
                $(this).attr('aria-checked', self.state.confirmdestroy ? 'true' : 'false');
                self.renderModes();
                self.refreshFooter();
            });
            $root.on('click', '[data-action="toggle-users"]', function() {
                self.state.includeusers = !self.state.includeusers;
                $(this).attr('aria-checked', self.state.includeusers ? 'true' : 'false');
            });

            // Step 4.
            $root.on('click', '[data-action="toggle-all"]', function() {
                self.toggleAll();
            });
            $root.on('click', '[data-action="toggle-section"]', function() {
                self.toggleSection($(this).attr('data-secidx'));
            });
            $root.on('click', '[data-action="toggle-act"]', function() {
                self.toggleAct($(this).attr('data-cmid'));
            });

            this.showView('landing');
        },

        /**
         * Switch the visible top-level region.
         *
         * @param {String} view landing|wizard|done
         */
        showView: function(view) {
            this.state.view = view;
            this.region('landing').prop('hidden', view !== 'landing');
            this.region('wizard').prop('hidden', view !== 'wizard');
            this.region('done').prop('hidden', view !== 'done');
        },

        /**
         * Find a data-region node under the root.
         *
         * @param {String} name
         * @return {jQuery}
         */
        region: function(name) {
            return this.$root.find('[data-region="' + name + '"]');
        },

        /**
         * Begin the wizard.
         */
        start: function() {
            this.resetState();
            this.showView('wizard');
            this.goStep(0);
            if (!this.sites.length) {
                this.loadSites();
            }
        },

        /**
         * Cancel the wizard: return to the landing in-page (SPA).
         */
        cancel: function() {
            this.resetState();
            this.showView('landing');
        },

        /**
         * After a submit, reload the page so the landing shows the fresh recent
         * list (the new restoration appears as "In progress").
         */
        reloadLanding: function() {
            window.location.reload();
        },

        /**
         * Show a wizard step.
         *
         * @param {Number} n
         */
        goStep: function(n) {
            this.state.step = n;
            this.$root.find('[data-step]').each(function() {
                var idx = parseInt($(this).attr('data-step'), 10);
                $(this).prop('hidden', idx !== n);
            });
            this.$root.find('.ct-step').each(function() {
                var idx = parseInt($(this).attr('data-step-index'), 10);
                var st = 'pending';
                if (idx < n) {
                    st = 'done';
                } else if (idx === n) {
                    st = 'current';
                }
                $(this).attr('data-state', st);
            });
            this.renderSitebar();
            if (n === 1) {
                this.renderCourseDesc();
                this.loadList();
            }
            if (n === 2) {
                this.renderReview();
            }
            if (n === 3) {
                this.renderModes();
            }
            if (n === 4) {
                this.renderContent();
            }
            this.refreshFooter();
        },

        /**
         * Persistent origin-site header (from step 1 on).
         */
        renderSitebar: function() {
            var show = this.state.step > 0 && !!this.state.siteid;
            this.region('sitebar').prop('hidden', !show);
            if (show) {
                this.region('sitebar-name').text(this.state.sitename || '');
            }
        },

        /**
         * Footer: back state, primary label/enabled, step number.
         */
        refreshFooter: function() {
            var step = this.state.step;
            var self = this;
            this.$root.find('[data-action="back"]').prop('disabled', step === 0);

            var $primary = this.$root.find('[data-action="primary"]');
            var islast = step === TOTALSTEPS - 1;
            var label = islast ? (this.S.rct_run || this.S.rw_run || 'Run') : (this.S.rw_next || 'Next');
            $primary.empty().text(label);
            if (!islast) {
                $primary.append($('<i>').addClass('fa fa-chevron-right').attr('aria-hidden', 'true'));
            } else {
                $primary.prepend($('<i>').addClass('fa fa-play').attr('aria-hidden', 'true'));
            }
            $primary.prop('disabled', !this.canProceed());

            var stepinfo = (this.S.rw_step_of || 'Step {$a->n} of {$a->total}')
                .replace('{$a->n}', step + 1).replace('{$a->total}', TOTALSTEPS);
            this.region('stepinfo').text(stepinfo);

            window.setTimeout(function() {
                self.$root.find('[data-action="primary"]').prop('disabled', !self.canProceed());
            }, 0);
        },

        /**
         * Whether the current step is complete enough to advance.
         *
         * @return {Boolean}
         */
        canProceed: function() {
            var s = this.state;
            if (s.step === 0) {
                return !!s.siteid;
            }
            if (s.step === 1) {
                return !!s.courseid;
            }
            if (s.step === 2) {
                return s.sectionsloaded;
            }
            if (s.step === 3) {
                if (!s.mode) {
                    return false;
                }
                return s.mode !== 'replace' || s.confirmdestroy;
            }
            if (s.step === 4) {
                return this.hasSelection();
            }
            return true;
        },

        /**
         * Primary button: advance, or submit on the last step.
         */
        primary: function() {
            if (!this.canProceed()) {
                return;
            }
            if (this.state.step === TOTALSTEPS - 1) {
                this.submit();
            } else {
                this.goStep(this.state.step + 1);
            }
        },

        /**
         * Back button.
         */
        back: function() {
            if (this.state.step > 0) {
                this.goStep(this.state.step - 1);
            }
        },

        // ---- Step 0: sites --------------------------------------------

        /**
         * Load the connected origin sites.
         */
        loadSites: function() {
            var self = this;
            Ajax.call([{
                methodname: 'local_coursetransfer_restore_wizard_get_sites',
                args: {}
            }])[0].then(function(resp) {
                self.sites = resp.sites || [];
                return self.renderSites();
            }).catch(function() {
                self.region('sites').empty().append(
                    $('<div>').addClass('ct-selempty').text(self.S.rw_submit_error || 'Error'));
            });
        },

        /**
         * Render site cards (single-select).
         */
        renderSites: function() {
            var self = this;
            var $box = this.region('sites').empty();
            if (!this.sites.length) {
                $box.append($('<div>').addClass('ct-selempty').text(self.S.rw_noresults || '—'));
                return;
            }
            this.sites.forEach(function(site) {
                var offline = !site.connected;
                var $card = $('<button>')
                    .attr('type', 'button')
                    .addClass('ct-sitecard' + (offline ? ' ct-sitecard--offline' : ''))
                    .attr('data-action', 'toggle-origin')
                    .attr('data-siteid', site.id)
                    .attr('data-sitename', site.name)
                    .attr('aria-pressed', 'false')
                    .prop('disabled', offline);
                $card.append($('<span>').addClass('ct-sitecard-icon')
                    .append($('<i>').addClass('fa fa-globe').attr('aria-hidden', 'true')));
                var $body = $('<span>').css({flex: 1, 'min-width': 0});
                $body.append($('<span>').addClass('ct-sitecard-name').text(site.name));
                if (site.host) {
                    $body.append($('<span>').addClass('ct-sitecard-url').text(site.host));
                }
                $body.append($('<span>').addClass('ct-sitecard-status '
                    + (offline ? 'ct-sitecard-status--ko' : 'ct-sitecard-status--ok'))
                    .text(site.status || ''));
                $card.append($body);
                $card.append($('<span>').addClass('ct-sitecard-check')
                    .append($('<i>').addClass('fa fa-check').attr('aria-hidden', 'true')));
                $box.append($card);
            });
        },

        /**
         * Select an origin site.
         *
         * @param {jQuery} $card
         */
        toggleOrigin: function($card) {
            if ($card.prop('disabled')) {
                return;
            }
            this.state.siteid = parseInt($card.attr('data-siteid'), 10);
            this.state.sitename = $card.attr('data-sitename') || '';
            this.region('sites').find('[data-action="toggle-origin"]').attr('aria-pressed', 'false');
            $card.attr('aria-pressed', 'true');
            // Changing the site invalidates the previous course + sections.
            this.state.courseid = null;
            this.state.coursename = '';
            this.state.sections = [];
            this.state.sectionsloaded = false;
            this.state.checked = {};
            this.state.page = 0;
            this.refreshFooter();
        },

        // ---- Step 1: course listing -----------------------------------

        /**
         * Set the step-1 lead with the selected site name.
         */
        renderCourseDesc: function() {
            this.region('course-desc').text(
                (this.S.rct_q_course_desc || 'Courses on {$a}.').replace('{$a}', this.state.sitename || ''));
        },

        /**
         * Load a page of origin courses.
         */
        loadList: function() {
            var self = this;
            if (!this.state.siteid) {
                return;
            }
            this.region('rows').empty().append(
                $('<div>').addClass('ct-selempty').text('…'));
            Ajax.call([{
                methodname: 'local_coursetransfer_restore_wizard_list_origin',
                args: {
                    siteid: this.state.siteid,
                    type: 'course',
                    page: this.state.page,
                    perpage: this.perpage,
                    query: this.state.search
                }
            }])[0].then(function(resp) {
                self.items = resp.items || [];
                self.state.total = resp.total || 0;
                self.state.pages = resp.pages || 1;
                self.state.page = resp.page || 0;
                return self.renderRows();
            }).catch(function() {
                self.region('rows').empty().append(
                    $('<div>').addClass('ct-selempty').text(self.S.rw_submit_error || 'Error'));
            });
        },

        /**
         * Render the current page of course rows (single-select radios).
         */
        renderRows: function() {
            var self = this;
            var $rows = this.region('rows').empty();
            if (!this.items.length) {
                $rows.append($('<div>').addClass('ct-selempty').text(self.S.rw_noresults || '—'));
            } else {
                this.items.forEach(function(item) {
                    var on = self.state.courseid === item.id;
                    var $row = $('<div>')
                        .addClass('ct-selrow ct-selrow--radio')
                        .attr('role', 'button')
                        .attr('tabindex', '0')
                        .attr('data-action', 'toggle-course')
                        .attr('data-id', item.id)
                        .attr('data-name', item.name)
                        .attr('data-meta', item.meta || '')
                        .attr('data-cat', item.category || '')
                        .attr('aria-pressed', on ? 'true' : 'false');
                    $row.append($('<span>').addClass('ct-radio'));
                    var $body = $('<span>').addClass('ct-selrow-body');
                    var $name = $('<span>').addClass('ct-selrow-name').attr('title', item.name);
                    if (item.url) {
                        $name.append($('<a>').addClass('ct-selrow-id')
                            .attr('href', item.url).attr('target', '_blank')
                            .attr('rel', 'noopener').attr('data-noselect', '1')
                            .text('#' + item.id));
                        $name.append(document.createTextNode(' '));
                    }
                    $name.append(document.createTextNode(item.name));
                    $body.append($name);
                    var $sub = self.buildSubline(item);
                    if ($sub) {
                        $body.append($sub);
                    }
                    $row.append($body);
                    $row.append($('<span>').addClass('ct-selrow-meta').text(item.meta || ''));
                    $rows.append($row);
                });
            }
            this.renderPager();
            this.renderResultInfo();
        },

        /**
         * Course sub-line: [shortname] · IdNumber · Category.
         *
         * @param {Object} item
         * @return {jQuery|null}
         */
        buildSubline: function(item) {
            var $sub = $('<span>').addClass('ct-selrow-sub');
            var has = false;
            if (item.shortname) {
                $sub.append($('<span>').addClass('ct-meta-code').text('[ ' + item.shortname + ' ]'));
                has = true;
            }
            if (item.idnumber) {
                $sub.append($('<b>').addClass('ct-meta-lbl').text(this.S.rw_lbl_idnumber || 'IdNumber'));
                $sub.append(document.createTextNode(' ' + item.idnumber));
                has = true;
            }
            if (item.category) {
                if (item.idnumber) {
                    $sub.append($('<span>').addClass('ct-meta-sep').text(' · '));
                }
                $sub.append($('<b>').addClass('ct-meta-lbl').text(this.S.rw_lbl_category || 'Category'));
                $sub.append(document.createTextNode(' ' + item.category));
                if (item.categoryid) {
                    $sub.append($('<span>').addClass('ct-meta-id').text(' #' + item.categoryid));
                }
                has = true;
            }
            return has ? $sub : null;
        },

        /**
         * Select a single origin course and (re)load its sections.
         *
         * @param {jQuery} $row
         */
        toggleCourse: function($row) {
            var id = parseInt($row.attr('data-id'), 10);
            this.state.courseid = id;
            this.state.coursename = $row.attr('data-name') || '';
            this.state.coursemeta = $row.attr('data-meta') || '';
            this.state.coursecat = $row.attr('data-cat') || '';
            this.region('rows').find('[data-action="toggle-course"]').attr('aria-pressed', 'false');
            $row.attr('aria-pressed', 'true');
            // Reset and (re)load the sections for the review + content steps.
            this.state.sections = [];
            this.state.sectionsloaded = false;
            this.state.checked = {};
            this.loadSections();
            this.refreshFooter();
        },

        /**
         * Load the sections/activities of the chosen origin course.
         */
        loadSections: function() {
            var self = this;
            if (!this.state.siteid || !this.state.courseid) {
                return;
            }
            this.state.sectionsloading = true;
            Ajax.call([{
                methodname: 'local_coursetransfer_restore_wizard_get_sections',
                args: {
                    siteid: this.state.siteid,
                    courseid: this.state.courseid,
                    targetcourseid: this.courseid
                }
            }])[0].then(function(resp) {
                self.state.sections = (resp && resp.sections) ? resp.sections : [];
                self.state.sectionsloaded = true;
                self.state.sectionsloading = false;
                // Default: bring the whole course (all activities checked, and
                // every empty section — e.g. topic 0 — selected too).
                var checked = {};
                var emptysecs = {};
                self.state.sections.forEach(function(sec, i) {
                    var acts = sec.activities || [];
                    if (acts.length === 0) {
                        emptysecs[i] = true;
                    }
                    acts.forEach(function(act) {
                        checked[act.cmid] = true;
                    });
                });
                self.state.checked = checked;
                self.state.emptysecs = emptysecs;
                self.refreshFooter();
                if (self.state.step === 2) {
                    self.renderReview();
                }
                return resp;
            }).catch(function() {
                self.state.sectionsloaded = false;
                self.state.sectionsloading = false;
                self.refreshFooter();
            });
        },

        /**
         * "Showing N of TOTAL courses you can access on SITE".
         */
        renderResultInfo: function() {
            var s = this.state;
            var $info = this.region('resultinfo');
            var $note = this.$root.find('[data-region="resultnote"]');
            if (!this.items.length) {
                $info.text('');
                $note.prop('hidden', true);
                return;
            }
            var txt = (this.S.rw_results || '{$a->shown}/{$a->total}')
                .replace('{$a->shown}', this.items.length)
                .replace('{$a->total}', s.total || this.items.length)
                .replace('{$a->kind}', this.S.rw_courses_pl || 'courses')
                .replace('{$a->site}', s.sitename || '');
            $info.text(txt);
            $note.prop('hidden', false);
        },

        /**
         * Pager.
         */
        renderPager: function() {
            var s = this.state;
            var $pager = this.region('pager');
            if (s.pages <= 1) {
                $pager.prop('hidden', true);
                return;
            }
            $pager.prop('hidden', false);
            var info = (this.S.rw_pageinfo || '{$a->page}/{$a->pages}')
                .replace('{$a->page}', s.page + 1).replace('{$a->pages}', s.pages);
            this.region('pageinfo').text(info);
            this.$root.find('[data-action="prev"]').prop('disabled', s.page <= 0);
            this.$root.find('[data-action="next-page"]').prop('disabled', s.page >= s.pages - 1);
        },

        // ---- Step 2: review -------------------------------------------

        /**
         * Count the activities across all sections.
         *
         * @return {Number}
         */
        totalActs: function() {
            var n = 0;
            this.state.sections.forEach(function(sec) {
                n += (sec.activities || []).length;
            });
            return n;
        },

        /**
         * Render the review card (course head + 3 headline stats).
         */
        renderReview: function() {
            var s = this.state;
            var $review = this.region('review').empty();

            var $head = $('<div>').addClass('ct-revhead');
            $head.append($('<span>').addClass('ct-revhead-icon')
                .append($('<i>').addClass('fa fa-book').attr('aria-hidden', 'true')));
            var $hb = $('<div>').css({flex: 1, 'min-width': 0});
            $hb.append($('<div>').addClass('ct-revhead-name').text(s.coursename || ''));
            // Category · from <origin platform> (platform in the origin colour).
            var $sub = $('<div>').addClass('ct-revhead-sub');
            $sub.append(document.createTextNode(
                (s.coursecat ? (s.coursecat + ' · ') : '') + (this.S.rct_review_from || 'from') + ' '));
            $sub.append($('<span>').addClass('ct-review-env ct-review-env--origin').text(s.sitename || ''));
            $hb.append($sub);
            $head.append($hb);
            $review.append($head);

            var $stats = $('<div>').addClass('ct-revstats');
            var stat = function(num, label) {
                var $c = $('<div>').addClass('ct-revstat');
                $c.append($('<div>').addClass('ct-revstat-num').text(num));
                $c.append($('<div>').addClass('ct-revstat-label').text(label));
                return $c;
            };
            $stats.append(stat(s.sections.length, this.S.rct_stat_sections || 'Sections'));
            $stats.append(stat(this.totalActs(), this.S.rct_stat_activities || 'Activities'));
            $stats.append(stat(s.coursemeta || '—', this.S.rct_stat_size || 'Size'));
            $review.append($stats);
        },

        // ---- Step 3: options ------------------------------------------

        /**
         * Choose merge/replace.
         *
         * @param {String} mode
         */
        setMode: function(mode) {
            this.state.mode = mode;
            if (mode !== 'replace') {
                this.state.confirmdestroy = false;
            }
            this.renderModes();
            this.refreshFooter();
        },

        /**
         * Render the merge/replace options honouring the teacher's rights.
         */
        renderModes: function() {
            var self = this;
            var $box = this.region('modes').empty();
            var defs = [];
            if (this.canmerge) {
                defs.push({id: 'merge', title: this.S.rct_mode_merge || 'Add to what I have',
                    desc: this.S.rct_mode_merge_desc || '', danger: false});
            }
            if (this.canreplace) {
                defs.push({id: 'replace', title: this.S.rct_mode_replace || 'Replace all content',
                    desc: this.S.rct_mode_replace_desc || '', danger: true});
            }
            this.region('nomode').prop('hidden', defs.length > 0);

            defs.forEach(function(m) {
                var on = self.state.mode === m.id;
                var $opt = $('<div>').addClass('ct-modeopt' + (m.danger ? ' ct-modeopt--danger' : ''))
                    .attr('aria-pressed', on ? 'true' : 'false');
                var $btn = $('<button>').attr('type', 'button').addClass('ct-modeopt-btn')
                    .attr('data-action', 'mode').attr('data-mode', m.id).attr('aria-pressed', on ? 'true' : 'false');
                $btn.append($('<span>').addClass('ct-radio'));
                var $txt = $('<span>');
                var $title = $('<span>').addClass('ct-modeopt-title').text(m.title);
                if (m.danger) {
                    $title.append($('<span>').addClass('ct-tag-danger').text(self.S.rw_destructive || 'DESTRUCTIVE'));
                } else {
                    $title.append($('<span>').addClass('ct-tag-rec').text(self.S.rw_recommended || 'RECOMMENDED'));
                }
                $txt.append($title);
                $txt.append($('<span>').addClass('ct-modeopt-desc').text(m.desc));
                $btn.append($txt);
                $opt.append($btn);

                if (m.danger && on) {
                    var $confirm = $('<div>').addClass('ct-modeopt-confirm');
                    var $cbtn = $('<button>').attr('type', 'button').addClass('ct-modeopt-confirm-btn')
                        .attr('data-action', 'toggle-confirm')
                        .attr('aria-checked', self.state.confirmdestroy ? 'true' : 'false');
                    $cbtn.append($('<span>').addClass('ct-modeopt-confirm-box')
                        .append($('<i>').addClass('fa fa-check').attr('aria-hidden', 'true')));
                    $cbtn.append($('<span>').text(self.S.rct_confirm_destroy || ''));
                    $confirm.append($cbtn);
                    $opt.append($confirm);
                }
                $box.append($opt);
            });

            // Safe-mode reassurance (merge only).
            this.region('mode-safe').prop('hidden', this.state.mode !== 'merge');
        },

        // ---- Step 4: content ------------------------------------------

        /**
         * Selected cmids as ints.
         *
         * @return {Number[]}
         */
        selectedCmids: function() {
            var self = this;
            return Object.keys(this.state.checked).filter(function(cmid) {
                return self.state.checked[cmid];
            }).map(function(cmid) {
                return parseInt(cmid, 10);
            });
        },

        /**
         * Toggle every activity.
         */
        toggleAll: function() {
            var self = this;
            var total = this.totalActs();
            var sel = this.selectedCmids().length;
            // "Everything" = all activities + every empty section selected.
            var emptyidx = [];
            this.state.sections.forEach(function(sec, i) {
                if ((sec.activities || []).length === 0) {
                    emptyidx.push(i);
                }
            });
            var allemptyon = emptyidx.every(function(i) {
                return self.state.emptysecs[i];
            });
            var everything = (total === 0 || sel === total) && allemptyon;
            var turnon = !everything;
            var checked = {};
            this.state.sections.forEach(function(sec) {
                (sec.activities || []).forEach(function(act) {
                    checked[act.cmid] = turnon;
                });
            });
            emptyidx.forEach(function(i) {
                self.state.emptysecs[i] = turnon;
            });
            this.state.checked = checked;
            this.renderContent();
            this.refreshFooter();
        },

        /**
         * Toggle a whole section.
         *
         * @param {String} idx section index
         */
        toggleSection: function(idx) {
            var i = parseInt(idx, 10);
            var sec = this.state.sections[i];
            if (!sec) {
                return;
            }
            var ids = (sec.activities || []).map(function(a) {
                return a.cmid;
            });
            if (ids.length === 0) {
                // Empty section (e.g. topic 0): toggle it on its own so its
                // structure/summary can still be brought.
                this.state.emptysecs[i] = !this.state.emptysecs[i];
            } else {
                var allon = ids.every(function(c) {
                    return this.state.checked[c];
                }, this);
                ids.forEach(function(c) {
                    this.state.checked[c] = !allon;
                }, this);
            }
            this.renderContent();
            this.refreshFooter();
        },

        /**
         * Whether anything is selected to bring: at least one activity or one
         * (empty) section.
         *
         * @return {Boolean}
         */
        hasSelection: function() {
            if (this.selectedCmids().length > 0) {
                return true;
            }
            var e = this.state.emptysecs || {};
            return Object.keys(e).some(function(k) {
                return e[k];
            });
        },

        /**
         * Toggle a single activity.
         *
         * @param {String} cmid
         */
        toggleAct: function(cmid) {
            this.state.checked[cmid] = !this.state.checked[cmid];
            this.renderContent();
            this.refreshFooter();
        },

        /**
         * Render the content step: master toggle + section/activity tree +
         * summary + destructive/async alerts.
         */
        renderContent: function() {
            var self = this;
            var s = this.state;
            var total = this.totalActs();
            var sel = this.selectedCmids().length;

            // Master toggle reflects activities + empty sections as one whole.
            var emptyidx = [];
            s.sections.forEach(function(sec, i) {
                if ((sec.activities || []).length === 0) {
                    emptyidx.push(i);
                }
            });
            var emptyon = emptyidx.filter(function(i) {
                return s.emptysecs[i];
            }).length;
            var units = total + emptyidx.length;
            var selunits = sel + emptyon;
            var $mbox = this.region('master-box');
            $mbox.removeClass('ct-selmaster-box--on ct-selmaster-box--some');
            $mbox.find('.fa').attr('class', 'fa fa-check');
            if (units > 0 && selunits === units) {
                $mbox.addClass('ct-selmaster-box--on');
            } else if (selunits > 0) {
                $mbox.addClass('ct-selmaster-box--some');
                $mbox.find('.fa').attr('class', 'fa fa-minus');
            }
            this.region('master-count').text(
                (this.S.rct_selcount || '{$a->sel}/{$a->total}')
                    .replace('{$a->sel}', sel).replace('{$a->total}', total));

            // Tree.
            var $tree = this.region('sectree').empty();
            s.sections.forEach(function(sec, i) {
                var ids = (sec.activities || []).map(function(a) {
                    return a.cmid;
                });
                var empty = ids.length === 0;
                var on = ids.filter(function(c) {
                    return self.state.checked[c];
                }).length;
                // Empty sections (e.g. topic 0) are selected on their own.
                var isall = empty ? !!self.state.emptysecs[i] : (ids.length > 0 && on === ids.length);
                var issome = !empty && on > 0 && !isall;

                var $sec = $('<div>').addClass('ct-sectree-section');
                var $head = $('<button>').attr('type', 'button').addClass('ct-sectree-head')
                    .attr('data-action', 'toggle-section').attr('data-secidx', i);
                var checkmod = '';
                if (isall) {
                    checkmod = ' ct-sectree-check--on';
                } else if (issome) {
                    checkmod = ' ct-sectree-check--some';
                }
                var $check = $('<span>').addClass('ct-sectree-check' + checkmod);
                $check.append($('<i>').addClass('fa ' + (issome ? 'fa-minus' : 'fa-check'))
                    .attr('aria-hidden', 'true'));
                $head.append($check);
                $head.append($('<span>').addClass('ct-sectree-name').css('flex', '1').text(sec.sectionname || ''));
                // Activity count, or an "empty section" hint for sections with none.
                $head.append($('<span>').addClass('ct-sectree-count')
                    .text(empty ? (self.S.rct_sec_empty || '') : (on + '/' + ids.length)));
                $sec.append($head);

                (sec.activities || []).forEach(function(act) {
                    var acton = !!self.state.checked[act.cmid];
                    var $act = $('<button>').attr('type', 'button').addClass('ct-sectree-act')
                        .attr('data-action', 'toggle-act').attr('data-cmid', act.cmid)
                        .attr('aria-pressed', acton ? 'true' : 'false');
                    var $acheck = $('<span>').addClass('ct-sectree-actcheck' + (acton ? ' ct-sectree-actcheck--on' : ''));
                    $acheck.append($('<i>').addClass('fa fa-check').attr('aria-hidden', 'true'));
                    $act.append($acheck);
                    $act.append($('<span>').addClass('ct-sectree-acticon')
                        .append($('<i>').addClass('fa ' + (MODICON[act.modname] || 'fa-puzzle-piece'))
                            .attr('aria-hidden', 'true')));
                    $act.append($('<span>').addClass('ct-sectree-actname').text(act.name || ''));
                    if (act.modname) {
                        $act.append($('<span>').addClass('ct-sectree-acttype').text(act.modname));
                    }
                    $sec.append($act);
                });
                $tree.append($sec);
            });

            this.renderSummary(sel);

            var danger = s.mode === 'replace';
            this.region('content-danger').prop('hidden', !danger);
        },

        /**
         * Render the summary field rows.
         *
         * @param {Number} sel selected activity count
         */
        renderSummary: function(sel) {
            var self = this;
            var s = this.state;
            var $sum = this.region('summary').empty();
            $sum.append($('<div>').addClass('ct-tsummary-title').text(this.S.rct_summary || 'Summary'));

            var selsecs = s.sections.filter(function(sec, i) {
                var acts = sec.activities || [];
                return acts.length
                    ? acts.some(function(a) {
                        return self.state.checked[a.cmid];
                    })
                    : !!self.state.emptysecs[i];
            }).length;

            var isdanger = s.mode === 'replace';
            var brings = (this.S.rct_brings_val || '{$a->secs} sections · {$a->acts} activities')
                .replace('{$a->secs}', selsecs).replace('{$a->acts}', sel)
                + (s.includeusers ? (this.S.rct_with_users || '') : '');
            var how = isdanger
                ? (this.S.rct_mode_replace_short || this.S.rct_mode_replace || 'Replace')
                : (this.S.rct_mode_merge_short || this.S.rct_mode_merge || 'Merge');

            var row = function(label, value, danger) {
                var $r = $('<div>').addClass('ct-tsummary-row');
                $r.append($('<span>').addClass('ct-tsummary-label').text(label));
                $r.append($('<span>').addClass('ct-tsummary-value' + (danger ? ' ct-tsummary-value--danger' : ''))
                    .text(value));
                $sum.append($r);
            };
            row(this.S.rct_sum_from || 'From', s.sitename || '');
            row(this.S.rct_sum_course || 'Course', s.coursename || '');
            row(this.S.rct_sum_brings || 'Bringing', brings);
            row(this.S.rct_sum_how || 'How', how, isdanger);
        },

        // ---- Submit ---------------------------------------------------

        /**
         * Build the sections payload from the current selection and submit.
         */
        submit: function() {
            var self = this;
            var s = this.state;
            var $primary = this.$root.find('[data-action="primary"]');
            $primary.prop('disabled', true);
            this.region('submit-error').prop('hidden', true);

            var sections = s.sections.map(function(sec, i) {
                var acts = (sec.activities || []).map(function(act) {
                    return {
                        cmid: act.cmid,
                        name: act.name || '',
                        instance: act.instance || 0,
                        modname: act.modname || '',
                        selected: !!self.state.checked[act.cmid]
                    };
                });
                // A section is selected if any activity is selected, or — when it
                // has none — if the empty section itself was ticked.
                var selected = acts.length
                    ? acts.some(function(a) {
                        return a.selected;
                    })
                    : !!self.state.emptysecs[i];
                return {
                    sectionnum: sec.sectionnum || 0,
                    sectionid: sec.sectionid || 0,
                    sectionname: sec.sectionname || '',
                    selected: selected,
                    activities: acts
                };
            });

            Ajax.call([{
                methodname: 'local_coursetransfer_restore_wizard_submit_course',
                args: {
                    siteid: s.siteid,
                    origincourseid: s.courseid,
                    targetcourseid: this.courseid,
                    mode: s.mode || 'merge',
                    includeusers: s.includeusers,
                    sections: sections
                }
            }])[0].then(function(resp) {
                if (resp && resp.success) {
                    self.showView('done');
                } else {
                    self.submitError(resp);
                }
                return resp;
            }).catch(function() {
                self.submitError(null);
            });
        },

        /**
         * Report a submit failure inline on the content step.
         *
         * @param {Object|null} resp
         */
        submitError: function(resp) {
            var msg = this.S.rw_submit_error || 'Error';
            if (resp && resp.errors && resp.errors.length && resp.errors[0].msg) {
                msg = resp.errors[0].msg;
            }
            this.region('submit-error').prop('hidden', false);
            this.region('submit-error-text').text(msg);
            this.$root.find('[data-action="primary"]').prop('disabled', false);
        }
    };

    return {
        init: function(selector) {
            Wizard.init(selector);
        }
    };
});
