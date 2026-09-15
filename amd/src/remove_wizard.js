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
 * Remote DELETE assistant (SPA-lite orchestrator).
 *
 * Drives the landing + 3-step wizard + done screens rendered by the remove_page
 * template. Lists the origin content with the shared restore_wizard web
 * services (get_sites, list_origin) and deletes through
 * restore_wizard_remove_submit. All web-service values are injected as text
 * nodes (never HTML).
 *
 * @module     local_coursetransfer/remove_wizard
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
    var TOTALSTEPS = 3;
    var SEARCHDEBOUNCE = 350;

    var STRINGKEYS = [
        'rw_loading', 'rw_cancel', 'rw_back', 'rw_next', 'rw_clear', 'rw_selectall',
        'rw_noresults', 'rw_pageinfo', 'rw_step_of', 'rw_submit_error', 'rw_results',
        'rw_results_note', 'rw_courses_pl', 'rw_categories_pl', 'rw_lbl_idnumber',
        'rw_lbl_category', 'rw_lbl_subcats', 'rw_cat_root', 'rw_cat_insub', 'rw_cat_breakdown',
        'rw_col_size', 'rw_col_import',
        'rmv_selected', 'rmv_selected_none', 'rmv_sel_title_course', 'rmv_sel_title_category',
        'rmv_sel_desc_course', 'rmv_sel_desc_category', 'rmv_search_ph_course',
        'rmv_search_ph_category', 'rmv_col_course', 'rmv_col_category', 'rmv_col_meta',
        'rmv_will_delete_in', 'rmv_list_heading', 'rmv_more', 'rmv_confirm_check', 'rmv_run',
        // Category subtree preview.
        'rw_loading', 'rcc_tree_title', 'rcc_tree_empty', 'rcc_tree_error'
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
            this.logurl = this.$root.attr('data-logurl') || '#';
            this.perpage = parseInt(this.$root.attr('data-pagesize'), 10) || PERPAGE;
            this.cancourse = this.$root.attr('data-canremovecourse') === '1';
            this.cancategory = this.$root.attr('data-canremovecategory') === '1';
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
         * Reset the in-memory state.
         */
        resetState: function() {
            // Default kind = the only one allowed, or course.
            var kind = 'course';
            if (!this.cancourse && this.cancategory) {
                kind = 'category';
            }
            this.state = {
                view: 'landing',
                step: 0,
                siteid: null,
                sitename: '',
                kind: kind,
                checked: {},
                search: '',
                page: 0,
                pages: 1,
                total: 0,
                scheduleon: false,
                scheduledate: '',
                confirm: false
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
                self.goLanding();
            });
            $root.on('click', '[data-action="go-landing"]', function() {
                // After an execution, reload so the "recent" list shows it.
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
            $root.on('click', '[data-action="toggle-kind"]', function() {
                self.toggleKind($(this).attr('data-kind'));
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
            $root.on('click', '[data-action="toggle-row"]', function(e) {
                // Clicking the grey #id link opens the remote page; don't select.
                if ($(e.target).closest('[data-noselect]').length) {
                    return;
                }
                self.toggleRow($(this));
            });
            $root.on('keydown', '[data-action="toggle-row"]', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    self.toggleRow($(this));
                }
            });
            $root.on('click', '[data-action="toggle-page"]', function() {
                self.togglePage();
            });
            $root.on('click', '[data-action="clear"]', function() {
                self.state.checked = {};
                self.renderCounter();
                self.renderRowsChecked();
                self.renderPageBox();
                self.refreshFooter();
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
            $root.on('click', '[data-action="toggle-confirm"]', function() {
                self.state.confirm = !self.state.confirm;
                $(this).attr('aria-checked', self.state.confirm ? 'true' : 'false');
                self.refreshFooter();
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
            // Preselect the kind when only one is available.
            this.syncKinds();
        },

        /**
         * Return to the landing screen.
         */
        goLanding: function() {
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
                this.renderStep1Header();
                this.loadList();
            }
            if (n === 2) {
                this.renderConfirm();
            }
            this.refreshFooter();
        },

        /**
         * Persistent origin-site header from step 1 on.
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
            var label = islast ? (this.S.rmv_run || 'Delete') : (this.S.rw_next || 'Next');
            $primary.empty().text(label);
            if (!islast) {
                $primary.append($('<i>').addClass('fa fa-chevron-right').attr('aria-hidden', 'true'));
            } else {
                $primary.prepend($('<i>').addClass('fa fa-trash').attr('aria-hidden', 'true'));
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
                return !!s.siteid && !!s.kind;
            }
            if (s.step === 1) {
                return this.selectedIds().length > 0;
            }
            if (s.step === 2) {
                return !!s.confirm;
            }
            return true;
        },

        /**
         * Primary button: advance, or delete on the last step.
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

        // ---- Step 0: sites + kind --------------------------------------

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
                var $card = $('<button>').attr('type', 'button')
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
                    + (offline ? 'ct-sitecard-status--ko' : 'ct-sitecard-status--ok')).text(site.status || ''));
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
            this.state.checked = {};
            this.state.page = 0;
            this.refreshFooter();
        },

        /**
         * Choose course/category kind.
         *
         * @param {String} kind
         */
        toggleKind: function(kind) {
            this.state.kind = kind;
            this.state.checked = {};
            this.state.page = 0;
            this.syncKinds();
            this.refreshFooter();
        },

        /**
         * Reflect the chosen kind on the radiocards (preselecting when only one).
         */
        syncKinds: function() {
            var self = this;
            this.$root.find('[data-action="toggle-kind"]').each(function() {
                $(this).attr('aria-pressed', $(this).attr('data-kind') === self.state.kind ? 'true' : 'false');
            });
        },

        // ---- Step 1: selection -----------------------------------------

        /**
         * Set the step-1 heading, search placeholder and column labels.
         */
        renderStep1Header: function() {
            var cat = this.state.kind === 'category';
            this.region('sel-title').text(cat
                ? (this.S.rmv_sel_title_category || '') : (this.S.rmv_sel_title_course || ''));
            var desc = (cat ? this.S.rmv_sel_desc_category : this.S.rmv_sel_desc_course) || '';
            this.region('sel-desc').text(desc.replace('{$a}', this.state.sitename || ''));
            this.region('search-input').attr('placeholder',
                cat ? (this.S.rmv_search_ph_category || '') : (this.S.rmv_search_ph_course || ''));
            this.region('col-name').text(cat ? (this.S.rmv_col_category || '') : (this.S.rmv_col_course || ''));
            this.region('col-meta').text(cat ? (this.S.rw_col_import || '') : (this.S.rw_col_size || ''));
            this.region('cat-cascade').prop('hidden', !cat);
        },

        /**
         * Load a page of origin items.
         */
        loadList: function() {
            var self = this;
            if (!this.state.siteid || !this.state.kind) {
                return;
            }
            this.region('rows').empty().append($('<div>').addClass('ct-selempty').text('…'));
            Ajax.call([{
                methodname: 'local_coursetransfer_restore_wizard_list_origin',
                args: {
                    siteid: this.state.siteid,
                    type: this.state.kind,
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
         * Render the current page of rows (multi-select checkboxes).
         */
        renderRows: function() {
            var self = this;
            var $rows = this.region('rows').empty();
            if (!this.items.length) {
                $rows.append($('<div>').addClass('ct-selempty').text(self.S.rw_noresults || '—'));
            } else {
                this.items.forEach(function(item) {
                    var on = !!self.state.checked[item.id];
                    var $row = $('<div>').addClass('ct-selrow')
                        .attr('role', 'button').attr('tabindex', '0')
                        .attr('data-action', 'toggle-row')
                        .attr('data-id', item.id)
                        .attr('data-name', item.name)
                        .attr('data-meta', item.meta || '')
                        .attr('aria-pressed', on ? 'true' : 'false');
                    $row.append($('<span>').addClass('ct-checkbox')
                        .append($('<i>').addClass('fa fa-check').attr('aria-hidden', 'true')));
                    var $body = $('<span>').addClass('ct-selrow-body');
                    var $name = $('<span>').addClass('ct-selrow-name');
                    $name.attr('title', (self.state.kind === 'category' && item.parent)
                        ? (item.parent + ' › ' + item.name) : item.name);
                    // Grey #id link to the remote course/category (same as restore).
                    if (item.url) {
                        $name.append($('<a>').addClass('ct-selrow-id')
                            .attr('href', item.url).attr('target', '_blank')
                            .attr('rel', 'noopener').attr('data-noselect', '1')
                            .text('#' + item.id));
                        $name.append(document.createTextNode(' '));
                    }
                    if (self.state.kind === 'category' && item.parent) {
                        $name.append($('<span>').addClass('ct-selrow-parent').text(item.parent + ' › '));
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
            this.renderCounter();
            this.renderPageBox();
            this.renderResultInfo();
        },

        /**
         * Row sub-line (course: shortname/idnumber/category; category: idnumber/subcats).
         *
         * @param {Object} item
         * @return {jQuery|null}
         */
        buildSubline: function(item) {
            var self = this;
            var $sub = $('<span>').addClass('ct-selrow-sub');
            // Category: same layout as the restore wizard — IdNumber, number of
            // subcategories, and the root/subcategory breakdown.
            if (this.state.kind === 'category') {
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
                field(self.S.rw_cat_breakdown || 'Breakdown',
                    root + ' ' + (self.S.rw_cat_root || 'in the root')
                    + ' · ' + insub + ' ' + (self.S.rw_cat_insub || 'in subcategories'));
                return $sub;
            }
            // Course: [shortname] IdNumber · Category #catid (same as restore).
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
         * Toggle a single row selection.
         *
         * @param {jQuery} $row
         */
        toggleRow: function($row) {
            var id = $row.attr('data-id');
            if (this.state.checked[id]) {
                delete this.state.checked[id];
                $row.attr('aria-pressed', 'false');
            } else {
                this.state.checked[id] = {name: $row.attr('data-name'), meta: $row.attr('data-meta')};
                $row.attr('aria-pressed', 'true');
            }
            this.renderCounter();
            this.renderPageBox();
            this.refreshFooter();
        },

        /**
         * Select / deselect every row on the visible page.
         */
        togglePage: function() {
            var self = this;
            var ids = this.items.map(function(i) {
                return String(i.id);
            });
            var allon = ids.length > 0 && ids.every(function(id) {
                return !!self.state.checked[id];
            });
            this.items.forEach(function(item) {
                if (allon) {
                    delete self.state.checked[item.id];
                } else {
                    self.state.checked[item.id] = {name: item.name, meta: item.meta || ''};
                }
            });
            this.renderRowsChecked();
            this.renderCounter();
            this.renderPageBox();
            this.refreshFooter();
        },

        /**
         * Reflect the checked map onto the visible rows.
         */
        renderRowsChecked: function() {
            var self = this;
            this.region('rows').find('[data-action="toggle-row"]').each(function() {
                var id = $(this).attr('data-id');
                $(this).attr('aria-pressed', self.state.checked[id] ? 'true' : 'false');
            });
        },

        /**
         * Update the select-all checkbox state.
         */
        renderPageBox: function() {
            var self = this;
            var ids = this.items.map(function(i) {
                return String(i.id);
            });
            var on = ids.filter(function(id) {
                return !!self.state.checked[id];
            }).length;
            var $box = this.$root.find('[data-action="toggle-page"]');
            $box.removeClass('ct-checkbox--on ct-checkbox--some');
            if (ids.length > 0 && on === ids.length) {
                $box.addClass('ct-checkbox--on');
            } else if (on > 0) {
                $box.addClass('ct-checkbox--some');
            }
        },

        /**
         * Selected ids as an array of ints.
         *
         * @return {Number[]}
         */
        selectedIds: function() {
            return Object.keys(this.state.checked).map(function(id) {
                return parseInt(id, 10);
            });
        },

        /**
         * Update the selection counter bar.
         */
        renderCounter: function() {
            var count = this.selectedIds().length;
            this.region('selcount').text(count);
            var label = count === 0
                ? (this.S.rmv_selected_none || '—')
                : (this.S.rmv_selected || '{$a}').replace('{$a}', count);
            this.region('sellabel').text(label);
            this.region('selcounter').toggleClass('ct-selcounter--active', count > 0);
            this.$root.find('[data-action="clear"]').prop('hidden', count === 0);
        },

        /**
         * "Showing N of TOTAL … on SITE".
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
            var kind = s.kind === 'category'
                ? (this.S.rw_categories_pl || 'categories') : (this.S.rw_courses_pl || 'courses');
            var txt = (this.S.rw_results || '{$a->shown}/{$a->total}')
                .replace('{$a->shown}', this.items.length)
                .replace('{$a->total}', s.total || this.items.length)
                .replace('{$a->kind}', kind)
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

        // ---- Step 2: confirm -------------------------------------------

        /**
         * Human "3 cursos" / "1 categoría" text for the current selection.
         *
         * @return {String}
         */
        whatText: function() {
            var count = this.selectedIds().length;
            var kind = this.state.kind === 'category'
                ? (this.S.rw_categories_pl || 'categories') : (this.S.rw_courses_pl || 'courses');
            return count + ' ' + kind;
        },

        /**
         * Build the destructive confirmation card + confirm-check text.
         */
        renderConfirm: function() {
            var self = this;
            var s = this.state;
            var ids = Object.keys(this.state.checked);
            var count = ids.length;

            var $card = this.region('confirm-card').empty();

            // Header: "will be deleted in <site>" + count.
            var $head = $('<div>').addClass('ct-removecard-head');
            $head.append($('<span>').addClass('ct-removecard-icon')
                .append($('<i>').addClass('fa fa-trash').attr('aria-hidden', 'true')));
            var $hb = $('<div>').css({flex: 1, 'min-width': 0});
            $hb.append($('<div>').addClass('ct-removecard-eyebrow').text(this.S.rmv_will_delete_in || ''));
            $hb.append($('<div>').addClass('ct-removecard-site').text(s.sitename || ''));
            $head.append($hb);
            $head.append($('<span>').addClass('ct-removecard-count').text(count));
            $card.append($head);

            // Body: list of items to delete (first 8).
            var $body = $('<div>').addClass('ct-removecard-body');
            $body.append($('<div>').addClass('ct-removecard-listlbl').text(this.S.rmv_list_heading || ''));
            var $list = $('<div>').addClass('ct-removecard-list');
            ids.slice(0, 8).forEach(function(id) {
                var info = self.state.checked[id] || {};
                var $it = $('<div>').addClass('ct-removecard-item');
                $it.append($('<i>').addClass('fa fa-times').attr('aria-hidden', 'true'));
                $it.append($('<span>').addClass('ct-removecard-item-name').text(info.name || ('#' + id)));
                if (info.meta) {
                    $it.append($('<span>').addClass('ct-removecard-item-meta').text(info.meta));
                }
                $list.append($it);
            });
            if (count > 8) {
                $list.append($('<div>').addClass('ct-removecard-more')
                    .text((this.S.rmv_more || '+{$a}').replace('{$a}', count - 8)));
            }
            $body.append($list);
            $card.append($body);

            // Confirm-check text: "… will delete <what> in <site> …".
            var confirmtxt = (this.S.rmv_confirm_check || '')
                .replace('{$a->what}', this.whatText())
                .replace('{$a->site}', s.sitename || '');
            this.region('confirm-text').text(confirmtxt);

            // Reset the confirm checkbox each time we (re)enter the step.
            this.state.confirm = false;
            this.$root.find('[data-action="toggle-confirm"]').attr('aria-checked', 'false');
            this.region('submit-error').prop('hidden', true);

            this.renderTree();
        },

        /**
         * Category subtree preview (only for a single selected category): shows the
         * hierarchy that will be deleted, so the destructive action is explicit.
         */
        renderTree: function() {
            var s = this.state;
            var $wrap = this.region('tree');
            var $body = this.region('tree-body');
            if (!$wrap.length) {
                return;
            }
            var ids = this.selectedIds();
            if (s.kind !== 'category' || ids.length !== 1) {
                $wrap.prop('hidden', true);
                return;
            }
            $wrap.prop('hidden', false);
            CategoryTree.load($body, s.siteid, parseInt(ids[0], 10), {
                loading: this.S.rw_loading,
                empty: this.S.rcc_tree_empty,
                error: this.S.rcc_tree_error
            });
        },

        // ---- Submit ----------------------------------------------------

        /**
         * Delete the selected items and move to the done screen.
         */
        submit: function() {
            var self = this;
            var s = this.state;
            var $primary = this.$root.find('[data-action="primary"]');
            $primary.prop('disabled', true);

            var schedule = (s.scheduleon && s.scheduledate) ? (Date.parse(s.scheduledate) || 0) : 0;

            Ajax.call([{
                methodname: 'local_coursetransfer_restore_wizard_remove_submit',
                args: {
                    siteid: s.siteid,
                    type: s.kind,
                    ids: this.selectedIds(),
                    schedule: schedule
                }
            }])[0].then(function(resp) {
                if (resp && resp.success) {
                    if (resp.data && resp.data.nexturl && resp.data.nexturl !== '#') {
                        self.region('done-log').attr('href', resp.data.nexturl);
                    }
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
         * Report a submit failure inline on the confirm step.
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
