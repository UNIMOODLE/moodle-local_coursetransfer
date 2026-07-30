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
 * Teacher/manager category-restore assistant (SPA-lite orchestrator).
 *
 * Drives the landing + 4-step wizard + done screens rendered by the
 * restore_category_page template. Reuses the shared restore_wizard web
 * services (get_sites, list_origin) and submits through
 * restore_wizard_submit_category, which validates the course-category context
 * capabilities. Values from the web services are injected as text nodes.
 *
 * @module     local_coursetransfer/restore_category_wizard
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/str',
    'local_coursetransfer/category_tree'
], function($, Ajax, Str, CategoryTree) {
    "use strict";

    var PERPAGE = 5;
    var TOTALSTEPS = 4;
    var SEARCHDEBOUNCE = 350;

    var STRINGKEYS = [
        'rw_loading', 'rw_cancel', 'rw_back', 'rw_next', 'rw_run',
        'rw_noresults', 'rw_pageinfo', 'rw_step_of', 'rw_submit_error',
        'rw_results', 'rw_results_note', 'rw_categories_pl', 'rw_kind_category',
        'rw_lbl_idnumber', 'rw_lbl_subcats', 'rw_col_import',
        'rw_cat_root', 'rw_cat_insub', 'rw_cat_breakdown',
        'rw_users_on', 'rw_users_off', 'rw_yes', 'rw_no',
        'rw_review_platform', 'rw_review_users', 'rw_review_removeorigin_field_cat',
        'rw_review_schedule_field', 'rw_review_sched_now', 'rw_review_sched_at',
        'rw_review_from', 'rw_review_selected', 'rw_review_dest_cat',
        'rw_review_removeorigin', 'rct_run', 'rcc_q_cat_desc',
        // Canonical direction terms (reused, not duplicated) + intro paragraph.
        'platforms_role_origin', 'platforms_role_target', 'rcc_review_intro',
        // Category subtree preview.
        'rcc_tree_title', 'rcc_tree_empty', 'rcc_tree_error',
        // "This site" label when the origin is the current platform (self-pairing).
        'platforms_this_site'
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
            this.targetcatid = parseInt(this.$root.attr('data-catid'), 10) || 0;
            this.targetcatname = this.$root.attr('data-catname') || '';
            this.localsitename = this.$root.attr('data-localsite') || '';
            this.caturl = this.$root.attr('data-caturl') || '#';
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
            this.state = {
                view: 'landing',
                step: 0,
                siteid: null,
                sitename: '',
                siteiscurrent: false,
                catid: null,
                catname: '',
                catmeta: '',
                catparent: '',
                search: '',
                page: 0,
                pages: 1,
                total: 0,
                includeusers: false,
                removeorigin: false,
                removeoriginconfirm: false,
                scheduleon: false,
                scheduledate: ''
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
                window.location.reload();
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
            $root.on('click', '[data-action="toggle-cat"]', function(e) {
                if ($(e.target).closest('[data-noselect]').length) {
                    return;
                }
                self.toggleCat($(this));
            });
            $root.on('keydown', '[data-action="toggle-cat"]', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    self.toggleCat($(this));
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

            // Step 2.
            $root.on('click', '[data-action="toggle-users"]', function() {
                self.state.includeusers = !self.state.includeusers;
                $(this).attr('aria-checked', self.state.includeusers ? 'true' : 'false');
            });
            $root.on('click', '[data-action="toggle-removeorigin"]', function() {
                self.state.removeorigin = !self.state.removeorigin;
                $(this).attr('aria-checked', self.state.removeorigin ? 'true' : 'false');
                self.region('removeorigin-confirm').prop('hidden', !self.state.removeorigin);
                if (!self.state.removeorigin) {
                    self.state.removeoriginconfirm = false;
                    self.$root.find('[data-action="toggle-removeorigin-confirm"]').attr('aria-checked', 'false');
                }
                self.refreshFooter();
            });
            $root.on('click', '[data-action="toggle-removeorigin-confirm"]', function() {
                self.state.removeoriginconfirm = !self.state.removeoriginconfirm;
                $(this).attr('aria-checked', self.state.removeoriginconfirm ? 'true' : 'false');
                self.refreshFooter();
            });
            $root.on('click', '[data-action="toggle-schedule"]', function() {
                self.state.scheduleon = !self.state.scheduleon;
                $(this).attr('aria-checked', self.state.scheduleon ? 'true' : 'false');
                self.region('schedule-date').prop('hidden', !self.state.scheduleon);
                if (!self.state.scheduleon) {
                    self.state.scheduledate = '';
                }
            });
            $root.on('change input', '[data-action="schedule-date"]', function() {
                self.state.scheduledate = $(this).val() || '';
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
         * Cancel: return to the landing in-page.
         */
        cancel: function() {
            this.resetState();
            this.showView('landing');
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
                var st = idx < n ? 'done' : (idx === n ? 'current' : 'pending');
                $(this).attr('data-state', st);
            });
            this.renderSitebar();
            if (n === 1) {
                this.renderCatDesc();
                this.loadList();
            }
            if (n === 2) {
                this.syncOptions();
                this.renderTree();
            }
            if (n === 3) {
                this.renderReview();
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
                return !!s.catid;
            }
            if (s.step === 2) {
                // Destructive "delete origin category" needs its confirmation.
                return !(s.removeorigin && !s.removeoriginconfirm);
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
                    .attr('data-siteiscurrent', site.iscurrent ? '1' : '0')
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
            this.state.siteiscurrent = $card.attr('data-siteiscurrent') === '1';
            if (this.state.siteiscurrent && this.S.platforms_this_site) {
                this.state.sitename = this.S.platforms_this_site;
            }
            this.region('sites').find('[data-action="toggle-origin"]').attr('aria-pressed', 'false');
            $card.attr('aria-pressed', 'true');
            this.state.catid = null;
            this.state.catname = '';
            this.state.page = 0;
            this.refreshFooter();
        },

        // ---- Step 1: category listing ---------------------------------

        /**
         * Set the step-1 lead with the selected site name.
         */
        renderCatDesc: function() {
            this.region('cat-desc').text(
                (this.S.rcc_q_cat_desc || 'Categories on {$a}.').replace('{$a}', this.state.sitename || ''));
        },

        /**
         * Load a page of origin categories.
         */
        loadList: function() {
            var self = this;
            if (!this.state.siteid) {
                return;
            }
            this.region('rows').empty().append($('<div>').addClass('ct-selempty').text('…'));
            Ajax.call([{
                methodname: 'local_coursetransfer_restore_wizard_list_origin',
                args: {
                    siteid: this.state.siteid,
                    type: 'category',
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
         * Render the current page of category rows (single-select radios).
         */
        renderRows: function() {
            var self = this;
            var $rows = this.region('rows').empty();
            if (!this.items.length) {
                $rows.append($('<div>').addClass('ct-selempty').text(self.S.rw_noresults || '—'));
            } else {
                this.items.forEach(function(item) {
                    var on = self.state.catid === item.id;
                    var $row = $('<div>')
                        .addClass('ct-selrow ct-selrow--radio')
                        .attr('role', 'button')
                        .attr('tabindex', '0')
                        .attr('data-action', 'toggle-cat')
                        .attr('data-id', item.id)
                        .attr('data-name', item.name)
                        .attr('data-meta', item.meta || '')
                        .attr('data-parent', item.parent || '')
                        .attr('aria-pressed', on ? 'true' : 'false');
                    $row.append($('<span>').addClass('ct-radio'));
                    var $body = $('<span>').addClass('ct-selrow-body');
                    var $name = $('<span>').addClass('ct-selrow-name')
                        .attr('title', (item.parent ? (item.parent + ' › ') : '') + item.name);
                    if (item.url) {
                        $name.append($('<a>').addClass('ct-selrow-id')
                            .attr('href', item.url).attr('target', '_blank')
                            .attr('rel', 'noopener').attr('data-noselect', '1')
                            .text('#' + item.id));
                        $name.append(document.createTextNode(' '));
                    }
                    if (item.parent) {
                        $name.append($('<span>').addClass('ct-selrow-parent').text(item.parent + ' › '));
                    }
                    $name.append(document.createTextNode(item.name));
                    $body.append($name);
                    $body.append(self.buildCategorySubline(item));
                    $row.append($body);
                    $row.append($('<span>').addClass('ct-selrow-meta').text(item.meta || ''));
                    $rows.append($row);
                });
            }
            this.renderPager();
            this.renderResultInfo();
        },

        /**
         * Category sub-line: IdNumber · Subcategories · root/subcat breakdown.
         *
         * @param {Object} item
         * @return {jQuery}
         */
        buildCategorySubline: function(item) {
            var self = this;
            var $sub = $('<span>').addClass('ct-selrow-sub');
            var first = true;
            var field = function(label, value) {
                if (!first) {
                    $sub.append($('<span>').addClass('ct-meta-sep').text(' · '));
                }
                $sub.append($('<b>').addClass('ct-meta-lbl').text(label));
                $sub.append(document.createTextNode(' ' + value));
                first = false;
            };
            if (item.idnumber) {
                field(self.S.rw_lbl_idnumber || 'IdNumber', item.idnumber);
            }
            field(self.S.rw_lbl_subcats || 'Subcategories', item.subcats || 0);
            var root = item.rootcourses || 0;
            var insub = item.subcatcourses || 0;
            var breakdown = root + ' ' + (self.S.rw_cat_root || 'in the root')
                + ' · ' + insub + ' ' + (self.S.rw_cat_insub || 'in subcategories');
            field(self.S.rw_cat_breakdown || 'Breakdown', breakdown);
            return $sub;
        },

        /**
         * Select a single origin category.
         *
         * @param {jQuery} $row
         */
        toggleCat: function($row) {
            this.state.catid = parseInt($row.attr('data-id'), 10);
            this.state.catname = $row.attr('data-name') || '';
            this.state.catmeta = $row.attr('data-meta') || '';
            this.state.catparent = $row.attr('data-parent') || '';
            this.region('rows').find('[data-action="toggle-cat"]').attr('aria-pressed', 'false');
            $row.attr('aria-pressed', 'true');
            this.refreshFooter();
        },

        /**
         * "Showing N of TOTAL categories you can access on SITE".
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
                .replace('{$a->kind}', this.S.rw_categories_pl || 'categories')
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

        // ---- Step 2: options ------------------------------------------

        /**
         * Sync the option controls to the state.
         */
        syncOptions: function() {
            var s = this.state;
            this.$root.find('[data-action="toggle-users"]').attr('aria-checked', s.includeusers ? 'true' : 'false');
            this.$root.find('[data-action="toggle-removeorigin"]').attr('aria-checked', s.removeorigin ? 'true' : 'false');
            this.region('removeorigin-confirm').prop('hidden', !s.removeorigin);
            this.$root.find('[data-action="toggle-removeorigin-confirm"]')
                .attr('aria-checked', s.removeoriginconfirm ? 'true' : 'false');
            this.$root.find('[data-action="toggle-schedule"]').attr('aria-checked', s.scheduleon ? 'true' : 'false');
            this.region('schedule-date').prop('hidden', !s.scheduleon);
        },

        /**
         * Human-friendly local datetime label.
         *
         * @param {String} value datetime-local string
         * @return {String}
         */
        formatSchedule: function(value) {
            var t = Date.parse(value);
            if (!t) {
                return value;
            }
            var d = new Date(t);
            var pad = function(n) {
                return (n < 10 ? '0' : '') + n;
            };
            return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear()
                + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        },

        // ---- Step 3: review -------------------------------------------

        /**
         * Fetch the origin category subtree and render it in the options step
         * (preview of the hierarchy that will be recreated on the target). Built as
         * DOM in JS to avoid a recursive mustache partial, which the JS template
         * engine does not render.
         */
        renderTree: function() {
            var s = this.state;
            var $wrap = this.region('tree');
            var $body = this.region('tree-body');
            if (!$wrap.length || !s.catid) {
                if ($wrap.length) {
                    $wrap.prop('hidden', true);
                }
                return;
            }
            $wrap.prop('hidden', false);
            CategoryTree.load($body, s.siteid, s.catid, {
                loading: this.S.rw_loading,
                empty: this.S.rcc_tree_empty,
                error: this.S.rcc_tree_error
            });
        },

        /**
         * Render the review summary (values injected as text).
         */
        renderReview: function() {
            var s = this.state;
            var self = this;
            var $review = this.region('review').empty();

            // Plain-language explanation of what running will do (Nielsen: help
            // users understand system status before a consequential action).
            var intro = (this.S.rcc_review_intro || '')
                .replace('{$a->origin}', s.catname)
                .replace('{$a->dest}', this.targetcatname);
            if (intro) {
                $review.append($('<p>').addClass('ct-step-lead').text(intro));
            }

            // A single consolidated card with labelled rows. Uses the same
            // direction terms as the rest of the app ("Traigo de" / "Envío a"),
            // not new wording.
            var $card = $('<div>').addClass('ct-review-item');
            $card.append($('<span>').addClass('ct-review-hero-icon')
                .append($('<i>').addClass('fa fa-folder-open-o').attr('aria-hidden', 'true')));
            var $ibody = $('<div>').addClass('ct-review-item-body');

            var kv = function(label, buildvalue) {
                var $row = $('<div>').addClass('ct-review-kv');
                $row.append($('<span>').addClass('ct-review-kv-lbl').text(label));
                var $val = $('<span>').addClass('ct-review-kv-val');
                buildvalue($val);
                $row.append($val);
                $ibody.append($row);
            };
            // Origin: the environment (remote site) in the origin colour (teal),
            // then the category (with parent breadcrumb).
            kv(this.S.platforms_role_origin || 'From', function($val) {
                if (s.sitename) {
                    $val.append($('<span>').addClass('ct-review-env ct-review-env--origin').text(s.sitename));
                    $val.append($('<span>').addClass('ct-review-env-sep').text(' · '));
                }
                if (s.catparent) {
                    $val.append($('<span>').addClass('ct-selrow-parent').text(s.catparent + ' › '));
                }
                $val.append(document.createTextNode(s.catname));
            });
            // Destination: this environment (local site) in the target colour
            // (orange), then the current category.
            kv(this.S.platforms_role_target || 'To', function($val) {
                if (self.localsitename) {
                    $val.append($('<span>').addClass('ct-review-env ct-review-env--target').text(self.localsitename));
                    $val.append($('<span>').addClass('ct-review-env-sep').text(' · '));
                }
                $val.append(document.createTextNode(self.targetcatname));
            });
            $card.append($ibody);
            if (s.catmeta) {
                $card.append($('<span>').addClass('ct-chip ct-chip--existing').text(s.catmeta));
            }
            $review.append($card);

            // Cross-cutting options grid.
            var $grid = $('<div>').addClass('ct-review-grid ct-review-grid--wrap ct-mt');
            // Each option as a scannable card: icon + label + value. State
            // ('danger'|'ok') colours icon and value.
            var cell = function(icon, label, value, state) {
                var $c = $('<div>').addClass('ct-review-grid-cell');
                var istate = state ? (' ct-review-cell-icon--' + state) : '';
                $c.append($('<span>').addClass('ct-review-cell-icon' + istate)
                    .append($('<i>').addClass('fa ' + icon).attr('aria-hidden', 'true')));
                var $b = $('<div>').addClass('ct-review-cell-body');
                $b.append($('<div>').addClass('ct-review-cell-lbl').text(label));
                // Only ok/danger colour the value text; "off" just mutes the icon.
                var vstate = (state === 'ok' || state === 'danger') ? (' ct-review-cell-val--' + state) : '';
                $b.append($('<div>').addClass('ct-review-cell-val' + vstate).text(value));
                $c.append($b);
                $grid.append($c);
            };
            // Platform is already shown in the "Traigo de" row above.
            cell('fa-users', self.S.rw_review_users || 'Users and groups',
                s.includeusers ? (self.S.rw_users_on || 'Yes') : (self.S.rw_users_off || 'No'),
                s.includeusers ? 'ok' : 'off');
            cell('fa-trash', self.S.rw_review_removeorigin_field_cat || 'Delete origin category',
                s.removeorigin ? (self.S.rw_yes || 'Yes') : (self.S.rw_no || 'No'),
                s.removeorigin ? 'danger' : 'off');
            var sched = (s.scheduleon && s.scheduledate)
                ? (self.S.rw_review_sched_at || 'Scheduled: {$a}').replace('{$a}', self.formatSchedule(s.scheduledate))
                : (self.S.rw_review_sched_now || 'Immediate');
            cell('fa-clock-o', self.S.rw_review_schedule_field || 'Execution', sched);
            $review.append($grid);

            // Destructive: origin category will be deleted.
            var $ro = this.region('review-removeorigin');
            if (s.removeorigin) {
                var txt = (this.S.rw_review_removeorigin || '{$a->count} {$a->kind} — {$a->site}')
                    .replace('{$a->count}', 1)
                    .replace('{$a->kind}', this.S.rw_categories_pl || '')
                    .replace('{$a->site}', s.sitename || '');
                this.region('review-removeorigin-text').text(txt);
                $ro.prop('hidden', false);
            } else {
                $ro.prop('hidden', true);
            }
        },

        // ---- Submit ---------------------------------------------------

        /**
         * Send the category restore and move to the done screen.
         */
        submit: function() {
            var self = this;
            var s = this.state;
            var $primary = this.$root.find('[data-action="primary"]');
            $primary.prop('disabled', true);
            this.region('submit-error').prop('hidden', true);

            var schedule = (s.scheduleon && s.scheduledate) ? (Date.parse(s.scheduledate) || 0) : 0;

            Ajax.call([{
                methodname: 'local_coursetransfer_restore_wizard_submit_category',
                args: {
                    siteid: s.siteid,
                    origincatid: s.catid,
                    targetcatid: this.targetcatid,
                    includeusers: s.includeusers,
                    removeorigin: !!s.removeorigin,
                    schedule: schedule
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
         * Report a submit failure inline on the review step.
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
