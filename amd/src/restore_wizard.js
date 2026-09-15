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
 * Admin restore assistant (SPA-lite orchestrator).
 *
 * Drives the landing + 4-step wizard + done screens rendered by the
 * restore_admin_page template, calling the restore_wizard web services.
 * All values coming from the web services are injected as text nodes
 * (never as HTML) to avoid injection.
 *
 * @module     local_coursetransfer/restore_wizard
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/str',
    'core/form-autocomplete',
    'local_coursetransfer/category_tree'
], function($, Ajax, Str, AutoComplete, CategoryTree) {
    "use strict";

    var PERPAGE = 10;
    var TOTALSTEPS = 4;
    var SEARCHDEBOUNCE = 350;

    // String keys fetched once at init; filled into S.
    var STRINGKEYS = [
        'rw_selected', 'rw_selected_none', 'rw_step_of', 'rw_next', 'rw_run',
        'rw_pageinfo', 'rw_noresults', 'rw_done_desc', 'rw_users_on', 'rw_users_off',
        'rw_submit_error', 'rw_review_selected', 'rw_review_from', 'rw_review_to',
        'rw_review_ifexists', 'rw_review_users', 'rw_mode_merge', 'rw_mode_replace',
        'rw_kind_course', 'rw_kind_category', 'rw_more',
        'rw_results', 'rw_courses_pl', 'rw_categories_pl',
        'rw_lbl_idnumber', 'rw_lbl_category',
        'rw_sel_title_course', 'rw_sel_title_category',
        'rw_sel_desc_course', 'rw_sel_desc_category',
        'rw_col_size', 'rw_col_count',
        // Step 2 "Destination per course".
        'rw_dest_new', 'rw_dest_default_badge', 'rw_dest_cat_label',
        'rw_dest_configure', 'rw_dest_collapse', 'rw_defaultcat', 'rw_defcat_search_ph',
        'rw_dest_inherited', 'rw_dest_existing', 'rw_dest_search_ph',
        'rw_dest_no_results', 'rw_dest_pick_target', 'rw_dest_change',
        'rw_exmode_merge', 'rw_exmode_merge_desc', 'rw_exmode_replace',
        'rw_exmode_replace_desc', 'rw_exmode_confirm', 'rw_del_enrol',
        'rw_del_groups', 'rw_tag_new', 'rw_tag_notarget', 'rw_tag_replace',
        'rw_tag_merge', 'rw_bd_new', 'rw_bd_existing', 'rw_bd_replace',
        'rw_recommended', 'rw_destructive',
        'rw_review_new_in', 'rw_review_over', 'rw_review_removeorigin',
        'rw_tag_unconfigured', 'rw_dest_unconfigured',
        'rw_review_platform', 'rw_review_removeorigin_field',
        'rw_review_schedule_field', 'rw_review_sched_now', 'rw_review_sched_at',
        'rw_yes', 'rw_no',
        // Persistent origin-site bar.
        'rw_sitebar_from',
        // Category listing extra columns.
        'rw_lbl_subcats', 'rw_col_import', 'rw_cat_root', 'rw_cat_insub', 'rw_cat_breakdown',
        // Step-2 labels swapped by type (course base + category variants).
        'rw_dest_title', 'rw_dest_desc', 'rw_defcat_title', 'rw_defcat_desc',
        'rw_users', 'rw_users_desc', 'rw_removeorigin', 'rw_removeorigin_desc',
        'rw_removeorigin_confirm',
        'rw_dest_title_cat', 'rw_dest_desc_cat', 'rw_defcat_title_cat', 'rw_defcat_desc_cat',
        'rw_users_cat', 'rw_users_cat_desc', 'rw_removeorigin_cat', 'rw_removeorigin_cat_desc',
        'rw_removeorigin_confirm_cat', 'rw_review_removeorigin_field_cat', 'rw_review_dest_cat',
        'platforms_role_origin', 'platforms_role_target',
        // Category subtree preview + self-pairing label.
        'rw_loading', 'rcc_tree_title', 'rcc_tree_empty', 'rcc_tree_error', 'platforms_this_site'
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
            // Search page size comes from the plugin setting (default 5).
            this.perpage = parseInt(this.$root.attr('data-pagesize'), 10) || PERPAGE;
            this.S = {};
            this.sites = [];
            this.items = [];
            this.searchtimer = null;
            this.resetState();

            var strrequests = STRINGKEYS.map(function(key) {
                return {key: key, component: 'local_coursetransfer'};
            });
            // Moodle's own "Top" label (root category) for the category-restore
            // default option, so we use core terminology.
            strrequests.push({key: 'top', component: 'core'});
            Str.get_strings(strrequests).then(function(values) {
                STRINGKEYS.forEach(function(key, i) {
                    self.S[key] = values[i];
                });
                self.S.top = values[STRINGKEYS.length];
                return self.bind();
            }).catch(function() {
                // Even without strings the UI must respond.
                self.bind();
            });
        },

        /**
         * Reset the in-memory state.
         */
        resetState: function() {
            this.state = {
                view: 'landing',
                step: 0,
                siteid: null,
                sitename: '',
                type: null,
                checked: {},
                search: '',
                page: 0,
                pages: 1,
                total: 0,
                destcat: 0,
                mode: 'merge',
                confirmdestroy: false,
                includeusers: false,
                // Step 2 "Destination per course".
                dest: {},
                removeorigin: false,
                removeoriginconfirm: false,
                scheduleon: false,
                scheduledate: ''
            };
        },

        /**
         * Bind delegated events and render the initial view.
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
            $root.on('click', '[data-action="toggle-type"]', function() {
                self.toggleType($(this).attr('data-type'));
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
            // Remove a single item from the cart (works across pages).
            $root.on('click', '[data-action="unpick"]', function(e) {
                e.stopPropagation();
                var id = $(this).attr('data-id');
                delete self.state.checked[id];
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

            // Step 2: default destination category.
            $root.on('change', '[data-action="default-cat"]', function() {
                var cat = parseInt($(this).val(), 10) || 0;
                self.rememberCatLabel(cat, $(this).find('option:selected').text());
                self.onDefaultCat(cat);
            });

            // Step 2: per-course destination cards.
            $root.on('click', '[data-action="pick-new"]', function() {
                self.setCardMode(self.cidOf($(this)), 'new');
            });
            $root.on('click', '[data-action="pick-existing"]', function() {
                self.setCardMode(self.cidOf($(this)), 'existing');
            });
            $root.on('change', '[data-action="card-cat"]', function() {
                var cat = parseInt($(this).val(), 10) || 0;
                self.rememberCatLabel(cat, $(this).find('option:selected').text());
                self.onCardCat(self.cidOf($(this)), cat);
            });
            $root.on('input', '[data-action="target-search"]', function() {
                var id = self.cidOf($(this));
                var value = $(this).val();
                if (!self.state.dest[id]) {
                    return;
                }
                self.state.dest[id].search = value;
                self.state.dest[id].open = true;
                window.clearTimeout(self.dtsearchtimer);
                self.dtsearchtimer = window.setTimeout(function() {
                    self.doTargetSearch(id);
                }, SEARCHDEBOUNCE);
            });
            $root.on('focusin', '[data-action="target-search"]', function() {
                var id = self.cidOf($(this));
                if (self.state.dest[id]) {
                    self.state.dest[id].open = true;
                    // Load the first courses so the user sees they can search.
                    self.doTargetSearch(id);
                }
            });
            $root.on('focusout', '[data-action="target-search"]', function() {
                var id = self.cidOf($(this));
                // Delay so a mousedown on a result can fire first.
                window.setTimeout(function() {
                    if (self.state.dest[id]) {
                        self.state.dest[id].open = false;
                        self.renderResults(id);
                    }
                }, 180);
            });
            $root.on('mousedown', '[data-action="pick-target"]', function(e) {
                e.preventDefault();
                self.pickTarget(self.cidOf($(this)), $(this));
            });
            $root.on('click', '[data-action="clear-target"]', function() {
                self.clearTarget(self.cidOf($(this)));
            });
            $root.on('click', '[data-action="ex-mode"]', function() {
                self.setExMode(self.cidOf($(this)), $(this).attr('data-mode'));
            });
            $root.on('click', '[data-action="toggle-replace-confirm"]', function() {
                self.toggleReplaceConfirm(self.cidOf($(this)));
            });
            $root.on('click', '[data-action="toggle-delenrol"]', function() {
                self.toggleDel(self.cidOf($(this)), 'delenrol', $(this));
            });
            $root.on('click', '[data-action="toggle-delgroups"]', function() {
                self.toggleDel(self.cidOf($(this)), 'delgroups', $(this));
            });

            // Step 2: global options.
            $root.on('click', '[data-action="toggle-users"]', function() {
                self.state.includeusers = !self.state.includeusers;
                $(this).attr('aria-checked', self.state.includeusers ? 'true' : 'false');
            });
            $root.on('click', '[data-action="toggle-removeorigin"]', function() {
                self.state.removeorigin = !self.state.removeorigin;
                $(this).attr('aria-checked', self.state.removeorigin ? 'true' : 'false');
                // Show the mandatory confirmation; reset it when turning off.
                self.region('removeorigin-confirm').prop('hidden', !self.state.removeorigin);
                if (!self.state.removeorigin) {
                    self.state.removeoriginconfirm = false;
                    self.$root.find('[data-action="toggle-removeorigin-confirm"]')
                        .attr('aria-checked', 'false');
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
            // Expand / collapse a course destination card.
            $root.on('click', '[data-action="toggle-card"]', function() {
                var id = $(this).closest('.ct-destcard').attr('data-cid');
                if (id && self.state.dest[id]) {
                    self.state.dest[id].expanded = !self.state.dest[id].expanded;
                    self.replaceCard(id);
                }
            });
            $root.on('keydown', '[data-action="toggle-card"]', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });

            // A fixed-positioned target dropdown would drift on scroll/resize:
            // close any open one so it never floats out of place.
            $(window).on('scroll.ctrw resize.ctrw', function() {
                Object.keys(self.state.dest || {}).forEach(function(id) {
                    if (self.state.dest[id].open) {
                        self.state.dest[id].open = false;
                        self.renderResults(id);
                    }
                });
            });

            // Destination-category autocomplete: seed the label cache. The
            // selects are enhanced when step 2 is shown (the default one) and as
            // cards render (the per-course ones) — enhancing a still-hidden step
            // here would mismeasure the widget.
            this.acseq = 0;
            this.catLabels = {'0': this.S.rw_defaultcat || ''};

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
            this.state.view = 'wizard';
            this.showView('wizard');
            this.goStep(0);
            if (!this.sites.length) {
                this.loadSites();
            }
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
            // Stepper markers.
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
                this.initStep2();
            }
            if (n === 3) {
                this.renderReview();
            }
            this.refreshFooter();
        },

        /**
         * Show the persistent origin-site header on every step after the site
         * is chosen (hidden on step 0, where the site is picked).
         */
        renderSitebar: function() {
            var show = this.state.step > 0 && !!this.state.siteid;
            this.region('sitebar').prop('hidden', !show);
            if (show) {
                this.region('sitebar-name').text(this.state.sitename || '');
            }
        },

        /**
         * Update footer: back state, primary label/enabled, step number.
         */
        refreshFooter: function() {
            var step = this.state.step;
            var self = this;

            this.$root.find('[data-action="back"]').prop('disabled', step === 0);

            var $primary = this.$root.find('[data-action="primary"]');
            var islast = step === TOTALSTEPS - 1;
            var label = islast ? (this.S.rw_run || 'Run') : (this.S.rw_next || 'Next');
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

            // Re-evaluate proceed capability lazily on step 2 confirm changes.
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
                return !!s.siteid && !!s.type;
            }
            if (s.step === 1) {
                return this.selectedIds().length > 0;
            }
            if (s.step === 2) {
                // Destructive "delete origin" needs its explicit confirmation.
                if (s.removeorigin && !s.removeoriginconfirm) {
                    return false;
                }
                // Category type keeps the single-target behaviour and is always ready.
                if (s.type !== 'course') {
                    return true;
                }
                var self = this;
                return this.selectedIds().every(function(id) {
                    var d = self.state.dest[id];
                    if (!d) {
                        return false;
                    }
                    if (d.mode !== 'existing') {
                        return true;
                    }
                    if (!d.targetid) {
                        return false;
                    }
                    if (d.exmode === 'replace' && !d.confirm) {
                        return false;
                    }
                    return true;
                });
            }
            return true;
        },

        /**
         * Primary button: advance or, on the last step, submit.
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

        // ---- Step 0: sites ---------------------------------------------

        /**
         * Load the connected origin sites via web service.
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
         * Render site cards (values injected as text).
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

                // Selected indicator: styled by .ct-sitecard-check in styles.css
                // (shown when the card has aria-pressed="true").
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
            // Changing the site invalidates any previous selection.
            this.state.checked = {};
            this.state.page = 0;
            this.refreshFooter();
        },

        /**
         * Choose course/category.
         *
         * @param {String} type
         */
        toggleType: function(type) {
            this.state.type = type;
            this.state.checked = {};
            this.state.page = 0;
            this.$root.find('[data-action="toggle-type"]').each(function() {
                $(this).attr('aria-pressed', $(this).attr('data-type') === type ? 'true' : 'false');
            });
            this.region('catnote').prop('hidden', type !== 'category');
            this.refreshFooter();
        },

        // ---- Step 1: origin listing ------------------------------------

        /**
         * Set the selection-step heading and table column labels according to
         * the chosen type (course / category) and the selected site.
         */
        renderStep1Header: function() {
            var cat = this.state.type === 'category';
            this.region('sel-title').text(cat
                ? (this.S.rw_sel_title_category || '')
                : (this.S.rw_sel_title_course || ''));
            var desc = (cat ? this.S.rw_sel_desc_category : this.S.rw_sel_desc_course) || '';
            this.region('sel-desc').text(desc.replace('{$a}', this.state.sitename || ''));
            this.region('col-name').text(cat
                ? (this.S.rw_kind_category || '')
                : (this.S.rw_kind_course || ''));
            this.region('col-meta').text(cat
                ? (this.S.rw_col_import || '')
                : (this.S.rw_col_size || ''));
            // Category is single-select: no "select all" and no cart.
            this.$root.find('[data-action="toggle-page"]')
                .css('visibility', cat ? 'hidden' : '');
        },

        /**
         * Load a page of origin items via web service.
         */
        loadList: function() {
            var self = this;
            if (!this.state.siteid || !this.state.type) {
                return;
            }
            var $rows = this.region('rows').empty();
            $rows.append($('<div>').addClass('ct-selempty').attr('data-region', 'rows-loading')
                .text((self.S.rw_pageinfo && '…') || '…'));

            Ajax.call([{
                methodname: 'local_coursetransfer_restore_wizard_list_origin',
                args: {
                    siteid: this.state.siteid,
                    type: this.state.type,
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
         * Render the current page of rows (values injected as text).
         */
        renderRows: function() {
            var self = this;
            var $rows = this.region('rows').empty();

            if (!this.items.length) {
                $rows.append($('<div>').addClass('ct-selempty').text(self.S.rw_noresults || '—'));
            } else {
                this.items.forEach(function(item) {
                    var on = !!self.state.checked[item.id];
                    // A div (not a button) so we can nest a real link for the
                    // remote #id without invalid markup; still keyboard-usable.
                    var $row = $('<div>')
                        .addClass('ct-selrow')
                        .attr('role', 'button')
                        .attr('tabindex', '0')
                        .attr('data-action', 'toggle-row')
                        .attr('data-id', item.id)
                        .attr('data-name', item.name)
                        .attr('data-meta', item.meta || '')
                        .attr('aria-pressed', on ? 'true' : 'false');

                    $row.append($('<span>').addClass('ct-checkbox')
                        .append($('<i>').addClass('fa fa-check').attr('aria-hidden', 'true')));

                    var $body = $('<span>').addClass('ct-selrow-body');
                    var $name = $('<span>').addClass('ct-selrow-name');
                    // Layout norm: the name is truncated with an ellipsis, so
                    // carry the full text (with breadcrumb for categories) in a
                    // title tooltip.
                    $name.attr('title', (self.state.type === 'category' && item.parent)
                        ? (item.parent + ' › ' + item.name) : item.name);
                    // Grey #id linking to the remote course/category. The click
                    // opens the remote page without toggling the row selection.
                    if (item.url) {
                        $name.append($('<a>')
                            .addClass('ct-selrow-id')
                            .attr('href', item.url)
                            .attr('target', '_blank')
                            .attr('rel', 'noopener')
                            .attr('data-noselect', '1')
                            .text('#' + item.id));
                        $name.append(document.createTextNode(' '));
                    }
                    // For categories, show the parent as a light-grey breadcrumb
                    // inside the name: "Parent › Category name".
                    if (self.state.type === 'category' && item.parent) {
                        $name.append($('<span>').addClass('ct-selrow-parent')
                            .text(item.parent + ' › '));
                    }
                    $name.append(document.createTextNode(item.name));
                    $body.append($name);

                    // Sub line: [ shortname ]  **IdNumber** value · **Categoría** value.
                    // Labels are ours (bold); remote values are appended as text.
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
         * Show "Showing N of TOTAL courses/categories you can access on SITE".
         * TOTAL is what the origin returns for THIS user (equivalent-user auth),
         * not every course on the remote site.
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
            var kind = s.type === 'category'
                ? (this.S.rw_categories_pl || 'categories')
                : (this.S.rw_courses_pl || 'courses');
            var txt = (this.S.rw_results || '{$a->shown}/{$a->total}')
                .replace('{$a->shown}', this.items.length)
                .replace('{$a->total}', s.total || this.items.length)
                .replace('{$a->kind}', kind)
                .replace('{$a->site}', s.sitename || '');
            $info.text(txt);
            $note.prop('hidden', false);
        },

        /**
         * Build the row sub-line with a grey shortname code and bold labels
         * for idnumber / category. Remote values are added as text nodes.
         *
         * @param {Object} item
         * @return {jQuery|null}
         */
        buildSubline: function(item) {
            if (this.state.type === 'category') {
                return this.buildCategorySubline(item);
            }
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
                $sub.append($('<b>').addClass('ct-meta-lbl').text(this.S.rw_lbl_category || 'Categoría'));
                $sub.append(document.createTextNode(' ' + item.category));
                // Category id in grey, so all origin identifiers are visible.
                if (item.categoryid) {
                    $sub.append($('<span>').addClass('ct-meta-id').text(' #' + item.categoryid));
                }
                has = true;
            }
            return has ? $sub : null;
        },

        /**
         * Build the category row sub-line with bold labels (ours) and escaped
         * remote values: IdNumber, parent category, number of subcategories and
         * number of courses in subcategories. The course count is shown in the
         * meta column.
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
            // Breakdown of the courses that will be imported: those directly in
            // the category root + those in its subcategories. The meta column
            // shows the grand total; this explains where they come from.
            var root = item.rootcourses || 0;
            var insub = item.subcatcourses || 0;
            var breakdown = root + ' ' + (self.S.rw_cat_root || 'in the root')
                + ' · ' + insub + ' ' + (self.S.rw_cat_insub || 'in subcategories');
            field(self.S.rw_cat_breakdown || 'Breakdown', breakdown);
            return $sub;
        },

        /**
         * Toggle a single row selection.
         *
         * @param {jQuery} $row
         */
        toggleRow: function($row) {
            var id = $row.attr('data-id');
            // Category restore targets a SINGLE origin category: selecting one
            // replaces any previous selection.
            var single = this.state.type === 'category';
            if (this.state.checked[id]) {
                delete this.state.checked[id];
                $row.attr('aria-pressed', 'false');
            } else {
                if (single) {
                    this.state.checked = {};
                    this.region('rows').find('[data-action="toggle-row"]').attr('aria-pressed', 'false');
                }
                this.state.checked[id] = {
                    name: $row.attr('data-name'),
                    meta: $row.attr('data-meta')
                };
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
                ? (this.S.rw_selected_none || '—')
                : (this.S.rw_selected || '{$a}').replace('{$a}', count);
            this.region('sellabel').text(label);
            this.region('selcounter').toggleClass('ct-selcounter--active', count > 0);
            this.$root.find('[data-action="clear"]').prop('hidden', count === 0);
            this.renderCart();
        },

        /**
         * Render the "cart": a removable chip per selected item, kept visible
         * across pages. Names come from state.checked (added as text).
         */
        renderCart: function() {
            var self = this;
            var $cart = this.region('cart').empty();
            var ids = Object.keys(this.state.checked);
            // Category is single-select: the cart of chips is redundant.
            if (!ids.length || this.state.type === 'category') {
                $cart.prop('hidden', true);
                return;
            }
            $cart.prop('hidden', false);
            ids.forEach(function(id) {
                var info = self.state.checked[id];
                var $chip = $('<span>').addClass('ct-cart-chip');
                $chip.append($('<span>').addClass('ct-cart-chip-name')
                    .text(info && info.name ? info.name : '#' + id));
                $chip.append($('<button>')
                    .attr('type', 'button')
                    .addClass('ct-cart-chip-x')
                    .attr('data-action', 'unpick')
                    .attr('data-id', id)
                    .attr('aria-label', (self.S.rw_clear || 'Remove'))
                    .append($('<i>').addClass('fa fa-times').attr('aria-hidden', 'true')));
                $cart.append($chip);
            });
        },

        /**
         * Update the pager.
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

        // ---- Step 2: destination per course ----------------------------

        /**
         * Read the origin course id from the card that contains an element.
         *
         * @param {jQuery} $el
         * @return {String}
         */
        cidOf: function($el) {
            return $el.closest('.ct-destcard').attr('data-cid');
        },

        /**
         * Enhance every not-yet-enhanced destination-category <select> with the
         * core/form-autocomplete widget backed by the server-side search web
         * service. Safe to call after any render: each render produces fresh
         * <select> elements, and the data-ac-done flag stops the same element
         * being enhanced twice (enhance is not idempotent).
         */
        enhanceCatSelects: function() {
            var self = this;
            this.$root.find('select.ct-catac').each(function() {
                var el = this;
                if (el.getAttribute('data-ac-done')) {
                    return;
                }
                el.setAttribute('data-ac-done', '1');
                if (!el.id) {
                    el.id = 'ct-catac-' + (self.acseq++);
                }
                var ph = self.S.rw_defcat_search_ph || '';
                AutoComplete.enhance(
                    '#' + el.id, false, 'local_coursetransfer/dest_category_search',
                    ph, false, true, ph, true
                ).catch(function() {
                    // If enhancement fails the plain <select> still works.
                    return null;
                });
            });
        },

        /**
         * Cache a category id -> label so the review/summary and inheriting
         * cards can show the chosen category name without every option being
         * present in the DOM (the whole point of the autocomplete).
         *
         * @param {Number} id
         * @param {String} label
         */
        rememberCatLabel: function(id, label) {
            if (label) {
                this.catLabels[String(id)] = label;
            }
        },

        /**
         * Seed / prune the per-course destination state and render the step.
         */
        initStep2: function() {
            var self = this;
            var s = this.state;
            var ids = this.selectedIds().map(String);

            // Swap the step-2 labels for the chosen type (course vs category).
            this.applyStep2Labels();

            // Drop config for courses no longer selected.
            Object.keys(this.state.dest).forEach(function(id) {
                if (ids.indexOf(id) < 0) {
                    delete self.state.dest[id];
                }
            });
            // Seed newly selected courses with sensible defaults.
            ids.forEach(function(id) {
                if (!self.state.dest[id]) {
                    self.state.dest[id] = {
                        mode: 'new',
                        categorytarget: s.destcat,
                        inherited: true,
                        targetid: 0,
                        targetname: '',
                        targetsub: '',
                        exmode: 'merge',
                        confirm: false,
                        delenrol: false,
                        delgroups: false,
                        search: '',
                        results: [],
                        open: false,
                        expanded: false
                    };
                }
            });

            // Sync the common select and global switches to the state.
            this.$root.find('#ct-destcat').val(String(s.destcat));
            this.$root.find('[data-action="toggle-users"]')
                .attr('aria-checked', s.includeusers ? 'true' : 'false');
            this.$root.find('[data-action="toggle-removeorigin"]')
                .attr('aria-checked', s.removeorigin ? 'true' : 'false');
            this.region('removeorigin-confirm').prop('hidden', !s.removeorigin);
            this.$root.find('[data-action="toggle-removeorigin-confirm"]')
                .attr('aria-checked', s.removeoriginconfirm ? 'true' : 'false');
            this.$root.find('[data-action="toggle-schedule"]')
                .attr('aria-checked', s.scheduleon ? 'true' : 'false');
            this.region('schedule-date').prop('hidden', !s.scheduleon);

            this.renderDestCards();
            this.renderBreakdown();
            this.renderTree();
            // Enhance the default-category select now the step is visible (covers
            // the category type, where renderDestCards early-returns).
            this.enhanceCatSelects();
        },

        /**
         * Category subtree preview (only for the category type, single selection).
         * Shown in the destination step so the admin sees the hierarchy that will
         * be recreated on the target.
         */
        renderTree: function() {
            var s = this.state;
            var $wrap = this.region('tree');
            var $body = this.region('tree-body');
            if (!$wrap.length) {
                return;
            }
            var ids = this.selectedIds();
            if (s.type !== 'category' || ids.length !== 1) {
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

        /**
         * Swap the step-2 texts depending on the restore type. For a category
         * restore the destination category is the single target where the
         * origin category courses are created, "delete origin" refers to the
         * category, and there is no per-course merge/replace.
         */
        applyStep2Labels: function() {
            var cat = this.state.type === 'category';
            var pick = function(base, catval) {
                return cat ? catval : base;
            };
            this.region('dest-title').text(pick(this.S.rw_dest_title, this.S.rw_dest_title_cat) || '');
            this.region('dest-desc').text(pick(this.S.rw_dest_desc, this.S.rw_dest_desc_cat) || '');
            this.region('defcat-title').text(pick(this.S.rw_defcat_title, this.S.rw_defcat_title_cat) || '');
            this.region('defcat-desc').text(pick(this.S.rw_defcat_desc, this.S.rw_defcat_desc_cat) || '');
            this.region('users-title').text(pick(this.S.rw_users, this.S.rw_users_cat) || '');
            this.region('users-desc').text(pick(this.S.rw_users_desc, this.S.rw_users_cat_desc) || '');
            this.region('removeorigin-title').text(pick(this.S.rw_removeorigin, this.S.rw_removeorigin_cat) || '');
            this.region('removeorigin-desc').text(pick(this.S.rw_removeorigin_desc, this.S.rw_removeorigin_cat_desc) || '');
            this.region('removeorigin-confirm-text')
                .text(pick(this.S.rw_removeorigin_confirm, this.S.rw_removeorigin_confirm_cat) || '');

            // Id-0 label of the destination autocomplete depends on the type: for
            // a category restore it means the top level (Moodle's "Top"); for a
            // course, the default category. Set the option text + cache + the
            // data-cat-type the transport reads, BEFORE the select is enhanced.
            var deflabel = pick(this.S.rw_defaultcat, this.S.top) || '';
            this.catLabels['0'] = deflabel;
            var $dc = this.$root.find('#ct-destcat');
            $dc.attr('data-cat-type', cat ? 'category' : 'course');
            $dc.find('option[value="0"]').text(deflabel);
        },

        /**
         * Change the default destination category and propagate it to every
         * course card that still inherits it.
         *
         * @param {Number} cat
         */
        onDefaultCat: function(cat) {
            var self = this;
            this.state.destcat = cat;
            Object.keys(this.state.dest).forEach(function(id) {
                var d = self.state.dest[id];
                if (d.inherited) {
                    d.categorytarget = cat;
                }
            });
            this.renderDestCards();
            this.renderBreakdown();
            this.refreshFooter();
        },

        /**
         * Render every per-course card (course type only).
         */
        renderDestCards: function() {
            var self = this;
            var $box = this.region('dest-cards').empty();
            if (this.state.type !== 'course') {
                this.region('dest-warning').prop('hidden', true);
                return;
            }
            this.selectedIds().forEach(function(id) {
                $box.append(self.buildCard(id));
            });
            // Warn about courses that are not fully configured yet.
            var pending = this.selectedIds().filter(function(id) {
                return !self.cardReady(id);
            }).length;
            var $w = this.region('dest-warning');
            if (pending > 0) {
                this.$root.find('[data-region="dest-warning-text"]')
                    .text((this.S.rw_dest_unconfigured || '{$a}').replace('{$a}', pending));
                $w.prop('hidden', false);
            } else {
                $w.prop('hidden', true);
            }
            // Enhance any per-course category selects just rendered.
            this.enhanceCatSelects();
        },

        /**
         * Rebuild a single card in place (keeps the rest untouched).
         *
         * @param {String} id
         */
        replaceCard: function(id) {
            var $old = this.$root.find('.ct-destcard[data-cid="' + id + '"]');
            if ($old.length) {
                $old.replaceWith(this.buildCard(id));
                // Enhance the category select if this card now shows one.
                this.enhanceCatSelects();
            }
        },

        /**
         * Build a course destination card. Remote values are text nodes.
         *
         * @param {String} id
         * @return {jQuery}
         */
        /**
         * Whether a course card is fully configured (mirrors canProceed).
         *
         * @param {String} id
         * @return {Boolean}
         */
        cardReady: function(id) {
            var d = this.state.dest[id] || {mode: 'new'};
            if (d.mode !== 'existing') {
                return true;
            }
            if (!d.targetid) {
                return false;
            }
            return d.exmode !== 'replace' || !!d.confirm;
        },

        /**
         * Work out the status tag of a destination card.
         *
         * @param {Boolean} ready Whether the card is fully configured.
         * @param {Boolean} isNew Whether the destination is a new course.
         * @param {Boolean} isReplace Whether the destination course is replaced.
         * @return {Object} Tag text and its modifier class.
         */
        cardTag: function(ready, isNew, isReplace) {
            if (!ready) {
                return {text: this.S.rw_tag_unconfigured || 'Not configured', cls: 'ct-destcard-tag--warn'};
            }
            if (isNew) {
                return {text: this.S.rw_tag_new || 'New', cls: 'ct-destcard-tag--new'};
            }
            if (isReplace) {
                return {text: this.S.rw_tag_replace || 'Replaces', cls: 'ct-destcard-tag--danger'};
            }
            return {text: this.S.rw_tag_merge || 'Merges', cls: 'ct-destcard-tag--merge'};
        },

        /**
         * Work out the class list of a destination card.
         *
         * @param {Boolean} ready Whether the card is fully configured.
         * @param {Boolean} isExisting Whether the destination is an existing course.
         * @param {Boolean} isReplace Whether the destination course is replaced.
         * @param {Boolean} expanded Whether the card is expanded.
         * @return {String} The class list.
         */
        cardClasses: function(ready, isExisting, isReplace, expanded) {
            var cls = 'ct-destcard';
            if (!ready) {
                cls += ' ct-destcard--warn';
            } else if (isExisting && isReplace) {
                cls += ' ct-destcard--danger';
            }
            if (expanded) {
                cls += ' ct-destcard--open';
            }
            return cls;
        },

        buildCard: function(id) {
            var d = this.state.dest[id] || {};
            var info = this.state.checked[id] || {};
            var isNew = d.mode !== 'existing';
            var isExisting = d.mode === 'existing';
            var hasTarget = isExisting && !!d.targetid;
            var isReplace = d.exmode === 'replace';
            var ready = this.cardReady(id);
            var expanded = !!d.expanded;

            // Card status tag. Not-ready cards read as "unconfigured" (warn).
            var tag = this.cardTag(ready, isNew, isReplace);

            var $card = $('<div>').addClass(this.cardClasses(ready, isExisting, isReplace, expanded))
                .attr('data-cid', id);

            // Header (click to expand/collapse).
            var $head = $('<div>').addClass('ct-destcard-head')
                .attr('data-action', 'toggle-card').attr('role', 'button').attr('tabindex', '0');
            $head.append($('<span>').addClass('ct-destcard-cid').text('#' + id));
            var $hinfo = $('<div>').addClass('ct-destcard-info');
            $hinfo.append($('<div>').addClass('ct-destcard-name').text(info.name || ('#' + id)));
            if (info.meta) {
                $hinfo.append($('<div>').addClass('ct-destcard-sub').text(info.meta));
            }
            $head.append($hinfo);
            $head.append($('<span>').addClass('ct-destcard-tag ' + tag.cls).text(tag.text));
            // Explicit call-to-action so the user sees the card is expandable
            // and where to configure the destination. Collapsed => "Configure",
            // expanded => "Collapse".
            $head.append($('<span>').addClass('ct-destcard-cta')
                .text(expanded ? (this.S.rw_dest_collapse || 'Collapse') : (this.S.rw_dest_configure || 'Configure')));
            $head.append($('<i>').addClass('fa fa-chevron-' + (expanded ? 'up' : 'down') + ' ct-destcard-chevron')
                .attr('aria-hidden', 'true'));
            $card.append($head);

            // Body (only when expanded).
            if (expanded) {
                var $body = $('<div>').addClass('ct-destcard-body');
                $body.append(this.buildNewOption(d, isNew));
                $body.append(this.buildExistingOption(id, d, isExisting, hasTarget));
                $card.append($body);
            }

            return $card;
        },

        /**
         * Build the "create new course" option block.
         *
         * @param {Object} d
         * @param {Boolean} isNew
         * @return {jQuery}
         */
        buildNewOption: function(d, isNew) {
            var $opt = $('<div>').addClass('ct-destopt').attr('aria-pressed', isNew ? 'true' : 'false');
            var $btn = $('<button>').attr('type', 'button').addClass('ct-destopt-btn')
                .attr('data-action', 'pick-new').attr('aria-pressed', isNew ? 'true' : 'false');
            $btn.append($('<span>').addClass('ct-radio'));
            $btn.append($('<span>').addClass('ct-destopt-label').text(this.S.rw_dest_new || 'Create a new course'));
            $btn.append($('<span>').addClass('ct-badge-default').text(this.S.rw_dest_default_badge || 'DEFAULT'));
            $opt.append($btn);

            if (isNew) {
                var $extra = $('<div>').addClass('ct-destopt-extra');
                $extra.append($('<label>').addClass('ct-destopt-cat-label')
                    .text(this.S.rw_dest_cat_label || 'Destination category'));
                // Autocomplete-backed select: only the current option is seeded;
                // the widget searches the rest server-side (scales to thousands).
                // Wrapped so form-autocomplete's generated markup stays grouped.
                var cat = String(d.categorytarget || 0);
                var $acwrap = $('<div>').addClass('ct-acwrap');
                var $sel = $('<select>').addClass('ct-input ct-catac')
                    .attr('data-action', 'card-cat').attr('data-cat-type', 'course');
                $sel.append($('<option>').attr('value', cat).attr('selected', 'selected')
                    .text(this.catLabel(cat)));
                $acwrap.append($sel);
                $extra.append($acwrap);
                if (d.inherited) {
                    var $note = $('<div>').addClass('ct-inherit-note');
                    $note.append($('<i>').addClass('fa fa-link').attr('aria-hidden', 'true'));
                    $note.append(document.createTextNode(' ' + (this.S.rw_dest_inherited || 'Inherits the default destination')));
                    $extra.append($note);
                }
                $opt.append($extra);
            }
            return $opt;
        },

        /**
         * Build the "restore over an existing course" option block.
         *
         * @param {String} id
         * @param {Object} d
         * @param {Boolean} isExisting
         * @param {Boolean} hasTarget
         * @return {jQuery}
         */
        buildExistingOption: function(id, d, isExisting, hasTarget) {
            var self = this;
            var $opt = $('<div>').addClass('ct-destopt').attr('aria-pressed', isExisting ? 'true' : 'false');
            var $btn = $('<button>').attr('type', 'button').addClass('ct-destopt-btn')
                .attr('data-action', 'pick-existing').attr('aria-pressed', isExisting ? 'true' : 'false');
            $btn.append($('<span>').addClass('ct-radio'));
            $btn.append($('<span>').addClass('ct-destopt-label')
                .text(this.S.rw_dest_existing || 'Restore over an existing course'));
            $opt.append($btn);

            if (!isExisting) {
                return $opt;
            }

            var $extra = $('<div>').addClass('ct-destopt-extra');

            if (!hasTarget) {
                // Search box + dropdown + warning.
                var $wrap = $('<div>').addClass('ct-target-search');
                var $search = $('<div>').addClass('ct-search');
                $search.append($('<i>').addClass('fa fa-search').attr('aria-hidden', 'true'));
                $search.append($('<input>').attr('type', 'text').attr('data-action', 'target-search')
                    .attr('placeholder', this.S.rw_dest_search_ph || 'Search…').val(d.search || ''));
                $wrap.append($search);
                $wrap.append($('<div>').addClass('ct-target-dropdown').attr('data-region', 'results').prop('hidden', true));
                var $warn = $('<div>').addClass('ct-pick-warn');
                $warn.append($('<i>').addClass('fa fa-exclamation-triangle').attr('aria-hidden', 'true'));
                var warntext = this.S.rw_dest_pick_target || 'Choose the destination course to continue';
                $warn.append(document.createTextNode(' ' + warntext));
                $wrap.append($warn);
                $extra.append($wrap);
            } else {
                // Chosen target.
                var $chosen = $('<div>').addClass('ct-target-chosen');
                $chosen.append($('<span>').addClass('ct-target-chosen-check')
                    .append($('<i>').addClass('fa fa-check').attr('aria-hidden', 'true')));
                var $cbody = $('<div>').addClass('ct-target-chosen-body');
                $cbody.append($('<div>').addClass('ct-target-chosen-name').text(d.targetname || ''));
                var chosensub = '#' + d.targetid + (d.targetsub ? (' · ' + d.targetsub) : '');
                $cbody.append($('<div>').addClass('ct-target-chosen-sub').text(chosensub));
                $chosen.append($cbody);
                $chosen.append($('<button>').attr('type', 'button').addClass('ct-target-change')
                    .attr('data-action', 'clear-target').text(this.S.rw_dest_change || 'Change'));
                $extra.append($chosen);

                // "If it exists" modes (reuses the ct-modeopt component).
                $extra.append(this.buildExModes(d));

                // Deletion options (only when replacing).
                if (d.exmode === 'replace') {
                    $extra.append(this.buildDelOptions(d));
                }
            }

            $opt.append($extra);

            // Populate the dropdown if it should be visible.
            if (!hasTarget) {
                window.setTimeout(function() {
                    self.renderResults(id);
                }, 0);
            }
            return $opt;
        },

        /**
         * Build the merge/replace selector for an existing target.
         *
         * @param {Object} d
         * @return {jQuery}
         */
        buildExModes: function(d) {
            var self = this;
            var $box = $('<div>').addClass('ct-exmodes');
            var modes = [
                {id: 'merge', title: this.S.rw_exmode_merge || 'Merge content',
                    desc: this.S.rw_exmode_merge_desc || '', danger: false},
                {id: 'replace', title: this.S.rw_exmode_replace || 'Replace content',
                    desc: this.S.rw_exmode_replace_desc || '', danger: true}
            ];
            modes.forEach(function(m) {
                var on = d.exmode === m.id;
                var $opt = $('<div>').addClass('ct-modeopt' + (m.danger ? ' ct-modeopt--danger' : ''))
                    .attr('aria-pressed', on ? 'true' : 'false');
                var $btn = $('<button>').attr('type', 'button').addClass('ct-modeopt-btn')
                    .attr('data-action', 'ex-mode').attr('data-mode', m.id).attr('aria-pressed', on ? 'true' : 'false');
                $btn.append($('<span>').addClass('ct-radio'));
                var $txt = $('<span>');
                var $title = $('<span>').addClass('ct-modeopt-title').text(m.title);
                if (!m.danger) {
                    $title.append($('<span>').addClass('ct-tag-rec').text(self.S.rw_recommended || 'RECOMMENDED'));
                } else {
                    $title.append($('<span>').addClass('ct-tag-danger').text(self.S.rw_destructive || 'DESTRUCTIVE'));
                }
                $txt.append($title);
                $txt.append($('<span>').addClass('ct-modeopt-desc').text(m.desc));
                $btn.append($txt);
                $opt.append($btn);

                // Destructive confirmation checkbox.
                if (m.danger && on) {
                    var $confirm = $('<div>').addClass('ct-modeopt-confirm');
                    var confirmtpl = self.S.rw_exmode_confirm || 'I understand…';
                    var $cbtn = $('<button>').attr('type', 'button').addClass('ct-modeopt-confirm-btn')
                        .attr('data-action', 'toggle-replace-confirm')
                        .attr('aria-checked', d.confirm ? 'true' : 'false');
                    $cbtn.append($('<span>').addClass('ct-modeopt-confirm-box')
                        .append($('<i>').addClass('fa fa-check').attr('aria-hidden', 'true')));
                    // Target name is injected as text (never HTML).
                    $cbtn.append($('<span>').text(confirmtpl.replace('{$a}', d.targetname || '')));
                    $confirm.append($cbtn);
                    $opt.append($confirm);
                }
                $box.append($opt);
            });
            return $box;
        },

        /**
         * Build the "remove enrolments / groups" checkboxes.
         *
         * @param {Object} d
         * @return {jQuery}
         */
        buildDelOptions: function(d) {
            var $box = $('<div>').addClass('ct-delopts');
            var rows = [
                {action: 'toggle-delenrol', on: d.delenrol, label: this.S.rw_del_enrol || 'Remove enrolments'},
                {action: 'toggle-delgroups', on: d.delgroups, label: this.S.rw_del_groups || 'Remove groups'}
            ];
            rows.forEach(function(r) {
                var $btn = $('<button>').attr('type', 'button').addClass('ct-delcheck')
                    .attr('role', 'checkbox').attr('data-action', r.action)
                    .attr('aria-checked', r.on ? 'true' : 'false');
                $btn.append($('<span>').addClass('ct-checkbox')
                    .append($('<i>').addClass('fa fa-check').attr('aria-hidden', 'true')));
                $btn.append($('<span>').addClass('ct-delcheck-label').text(r.label));
                $box.append($btn);
            });
            return $box;
        },

        /**
         * Render the search-results dropdown of a card (text nodes only).
         *
         * @param {String} id
         */
        renderResults: function(id) {
            var self = this;
            var d = this.state.dest[id];
            if (!d) {
                return;
            }
            var $card = this.$root.find('.ct-destcard[data-cid="' + id + '"]');
            var $dd = $card.find('[data-region="results"]');
            if (!$dd.length) {
                return;
            }
            $dd.empty();
            if (!d.open) {
                $dd.prop('hidden', true);
                return;
            }
            $dd.prop('hidden', false);
            // Fixed positioning: anchor to the input so overflow:hidden on any
            // ancestor cannot clip the dropdown.
            var $input = $card.find('[data-action="target-search"]');
            if ($input.length) {
                var rect = $input[0].getBoundingClientRect();
                $dd.css({top: (rect.bottom + 4) + 'px', left: rect.left + 'px', width: rect.width + 'px'});
            }
            if (!d.results.length) {
                $dd.append($('<div>').addClass('ct-target-empty')
                    .text(self.S.rw_dest_no_results || 'No results.'));
                return;
            }
            d.results.forEach(function(r) {
                var sub = self.targetSub(r);
                var $b = $('<button>').attr('type', 'button').addClass('ct-target-result')
                    .attr('data-action', 'pick-target')
                    .attr('data-tid', r.id)
                    .attr('data-tname', r.fullname || '')
                    .attr('data-tsub', sub);
                $b.append($('<span>').addClass('ct-target-result-cid').text('#' + r.id));
                var $bd = $('<span>').addClass('ct-target-result-body');
                $bd.append($('<span>').addClass('ct-target-result-name').text(r.fullname || ''));
                if (sub) {
                    $bd.append($('<span>').addClass('ct-target-result-sub').text(sub));
                }
                $b.append($bd);
                $dd.append($b);
            });
        },

        /**
         * Compose the "shortname · idnumber" sub-line for a target result.
         *
         * @param {Object} r
         * @return {String}
         */
        targetSub: function(r) {
            var parts = [];
            if (r.shortname) {
                parts.push(r.shortname);
            }
            if (r.idnumber) {
                parts.push(r.idnumber);
            }
            return parts.join(' · ');
        },

        /**
         * Query the destination site for candidate target courses.
         *
         * @param {String} id
         */
        doTargetSearch: function(id) {
            var self = this;
            var d = this.state.dest[id];
            if (!d) {
                return;
            }
            // Empty text is allowed: the WS returns the first courses so the
            // user sees a starting list and understands they can search.
            var text = (d.search || '').trim();
            Ajax.call([{
                methodname: 'local_coursetransfer_dest_search_course_name',
                args: {text: text}
            }])[0].then(function(resp) {
                d.results = (resp && resp.data) ? resp.data : [];
                d.open = true;
                self.renderResults(id);
                return resp;
            }).catch(function() {
                d.results = [];
                self.renderResults(id);
            });
        },

        /**
         * Switch a card between "new" and "existing".
         *
         * @param {String} id
         * @param {String} mode
         */
        setCardMode: function(id, mode) {
            if (!this.state.dest[id]) {
                return;
            }
            this.state.dest[id].mode = mode;
            this.replaceCard(id);
            this.renderBreakdown();
            this.refreshFooter();
        },

        /**
         * Set the destination category of a single card (stops inheriting).
         *
         * @param {String} id
         * @param {Number} cat
         */
        onCardCat: function(id, cat) {
            var d = this.state.dest[id];
            if (!d) {
                return;
            }
            d.categorytarget = cat;
            d.inherited = false;
            this.replaceCard(id);
        },

        /**
         * Set the chosen existing target from a result button.
         *
         * @param {String} id
         * @param {jQuery} $btn
         */
        pickTarget: function(id, $btn) {
            var d = this.state.dest[id];
            if (!d) {
                return;
            }
            d.targetid = parseInt($btn.attr('data-tid'), 10) || 0;
            d.targetname = $btn.attr('data-tname') || '';
            d.targetsub = $btn.attr('data-tsub') || '';
            d.open = false;
            d.results = [];
            this.replaceCard(id);
            this.renderBreakdown();
            this.refreshFooter();
        },

        /**
         * Clear the chosen target and reset its mode-related flags.
         *
         * @param {String} id
         */
        clearTarget: function(id) {
            var d = this.state.dest[id];
            if (!d) {
                return;
            }
            d.targetid = 0;
            d.targetname = '';
            d.targetsub = '';
            d.search = '';
            d.results = [];
            d.open = false;
            d.exmode = 'merge';
            d.confirm = false;
            d.delenrol = false;
            d.delgroups = false;
            this.replaceCard(id);
            this.renderBreakdown();
            this.refreshFooter();
        },

        /**
         * Choose merge/replace for an existing target.
         *
         * @param {String} id
         * @param {String} mode
         */
        setExMode: function(id, mode) {
            var d = this.state.dest[id];
            if (!d) {
                return;
            }
            d.exmode = mode;
            if (mode !== 'replace') {
                d.confirm = false;
                d.delenrol = false;
                d.delgroups = false;
            }
            this.replaceCard(id);
            this.renderBreakdown();
            this.refreshFooter();
        },

        /**
         * Toggle the destructive-replace confirmation of a card.
         *
         * @param {String} id
         */
        toggleReplaceConfirm: function(id) {
            var d = this.state.dest[id];
            if (!d) {
                return;
            }
            d.confirm = !d.confirm;
            this.replaceCard(id);
            this.refreshFooter();
        },

        /**
         * Toggle one of the deletion checkboxes of a card.
         *
         * @param {String} id
         * @param {String} key delenrol|delgroups
         * @param {jQuery} $btn
         */
        toggleDel: function(id, key, $btn) {
            var d = this.state.dest[id];
            if (!d) {
                return;
            }
            d[key] = !d[key];
            $btn.attr('aria-checked', d[key] ? 'true' : 'false');
        },

        /**
         * Render the "N new / N over existing / N replace" chips.
         */
        renderBreakdown: function() {
            var self = this;
            var $bd = this.region('breakdown');
            if (this.state.type !== 'course') {
                $bd.prop('hidden', true).empty();
                return;
            }
            var ids = this.selectedIds().map(String);
            if (!ids.length) {
                $bd.prop('hidden', true).empty();
                return;
            }
            var nnew = 0;
            var nexisting = 0;
            var nreplace = 0;
            ids.forEach(function(id) {
                var d = self.state.dest[id] || {};
                if (d.mode === 'existing') {
                    nexisting += 1;
                    if (d.exmode === 'replace') {
                        nreplace += 1;
                    }
                } else {
                    nnew += 1;
                }
            });
            var chip = function(tpl, n, cls) {
                return $('<span>').addClass('ct-chip ' + cls).text((tpl || '{$a}').replace('{$a}', n));
            };
            $bd.empty().prop('hidden', false);
            if (nnew > 0) {
                $bd.append(chip(self.S.rw_bd_new, nnew, 'ct-chip--new'));
            }
            if (nexisting > 0) {
                $bd.append(chip(self.S.rw_bd_existing, nexisting, 'ct-chip--existing'));
            }
            if (nreplace > 0) {
                $bd.append(chip(self.S.rw_bd_replace, nreplace, 'ct-chip--replace'));
            }
        },

        // ---- Step 3: review --------------------------------------------

        /**
         * Build the review summary (values injected as text).
         */
        /**
         * Human label of a destination category id, from the label cache
         * populated as the user picks categories in the autocomplete (id 0 is
         * the default category). Falls back to any option still in the DOM, then
         * to "#id".
         *
         * @param {Number} catid
         * @return {String}
         */
        catLabel: function(catid) {
            var key = String(catid || 0);
            if (this.catLabels && this.catLabels[key]) {
                return this.catLabels[key];
            }
            var $opt = this.$root.find('#ct-destcat option[value="' + catid + '"]');
            return $opt.length ? $opt.text() : ('#' + catid);
        },

        /**
         * Resolved destination text for a course in the review list:
         * "→ new in <category>" or "→ over <course> (merge|replace)".
         *
         * @param {Number|String} id origin course id
         * @return {String}
         */
        destText: function(id) {
            var d = this.state.dest[id] || {mode: 'new'};
            if (d.mode === 'existing') {
                var m = d.exmode === 'replace'
                    ? (this.S.rw_exmode_replace || 'Replace')
                    : (this.S.rw_exmode_merge || 'Merge');
                var name = d.targetname || ('#' + d.targetid);
                return (this.S.rw_review_over || '→ {$a}').replace('{$a}', name) + ' (' + m + ')';
            }
            return (this.S.rw_review_new_in || '→ {$a}').replace('{$a}', this.catLabel(d.categorytarget));
        },

        /**
         * A review row: origin course name + destination line + mode chip.
         *
         * @param {String} id origin course id
         * @param {Boolean} iscat
         * @return {jQuery}
         */
        buildReviewItem: function(id, iscat) {
            var info = this.state.checked[id] || {};
            var d = this.state.dest[id] || {mode: 'new'};
            var $row = $('<div>').addClass('ct-review-item');

            var self = this;
            var $body = $('<div>').addClass('ct-review-item-body');
            $body.append($('<div>').addClass('ct-review-item-name').text(info.name || ('#' + id)));
            // Destination line, labelled with the canonical direction term
            // ("Envío a") in the target colour so origin→destination is clear.
            var destLine = function(destname) {
                var $d = $('<div>').addClass('ct-review-item-dest');
                $d.append($('<i>').addClass('fa fa-long-arrow-right').attr('aria-hidden', 'true'));
                $d.append($('<span>').addClass('ct-review-env ct-review-env--target')
                    .text(self.S.platforms_role_target || 'To'));
                $d.append(document.createTextNode(' ' + destname));
                return $d;
            };
            if (!iscat) {
                var isexisting = d.mode === 'existing';
                var destname = isexisting
                    ? (d.targetname || ('#' + d.targetid))
                    : this.catLabel(d.categorytarget);
                $body.append(destLine(destname));
            } else {
                // Category: destination is the single target category.
                $body.append(destLine(this.catLabel(this.state.destcat)));
            }
            $row.append($body);

            if (!iscat) {
                var chipclass;
                var chiptext;
                if (d.mode !== 'existing') {
                    chipclass = 'ct-chip--new';
                    chiptext = this.S.rw_tag_new || 'New';
                } else if (d.exmode === 'replace') {
                    chipclass = 'ct-chip--replace';
                    chiptext = this.S.rw_exmode_replace || 'Replace';
                } else {
                    chipclass = 'ct-chip--existing';
                    chiptext = this.S.rw_exmode_merge || 'Merge';
                }
                $row.append($('<span>').addClass('ct-chip ' + chipclass).text(chiptext));
            }
            return $row;
        },

        /**
         * Cross-cutting options summary as a homogeneous field grid:
         * origin platform, category mode (category type), include users,
         * delete origin, scheduling.
         *
         * @param {Boolean} iscat
         * @return {jQuery}
         */
        buildGlobalsGrid: function(iscat) {
            var s = this.state;
            var self = this;
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

            cell('fa-globe', self.S.rw_review_platform || 'Origin platform', s.sitename || '—');
            // Category restore has no merge/replace concept (it always creates
            // new courses), so the "if it exists" field is course-only.
            cell('fa-users', self.S.rw_review_users || 'Users and groups',
                s.includeusers ? (self.S.rw_users_on || 'Yes') : (self.S.rw_users_off || 'No'),
                s.includeusers ? 'ok' : 'off');
            var removeoriginlabel = iscat
                ? (self.S.rw_review_removeorigin_field_cat || 'Delete origin category')
                : (self.S.rw_review_removeorigin_field || 'Delete origin');
            cell('fa-trash', removeoriginlabel,
                s.removeorigin ? (self.S.rw_yes || 'Yes') : (self.S.rw_no || 'No'),
                s.removeorigin ? 'danger' : 'off');
            var sched = (s.scheduleon && s.scheduledate)
                ? (self.S.rw_review_sched_at || 'Scheduled: {$a}').replace('{$a}', self.formatSchedule(s.scheduledate))
                : (self.S.rw_review_sched_now || 'Immediate');
            cell('fa-clock-o', self.S.rw_review_schedule_field || 'Execution', sched);
            return $grid;
        },

        /**
         * Human-friendly local datetime label for the schedule value.
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

        /**
         * Count the per-course destinations of the selection.
         *
         * @param {Array} items Selected course ids.
         * @param {Boolean} iscat Whether this is a category restore.
         * @return {Object} Counts of new, existing and replace destinations.
         */
        destBreakdown: function(items, iscat) {
            var self = this;
            var bd = {nnew: 0, nexisting: 0, nreplace: 0};
            if (iscat) {
                return bd;
            }
            items.forEach(function(id) {
                var d = self.state.dest[id] || {mode: 'new'};
                if (d.mode === 'existing') {
                    bd.nexisting++;
                    if (d.exmode === 'replace') {
                        bd.nreplace++;
                    }
                } else {
                    bd.nnew++;
                }
            });
            return bd;
        },

        /**
         * Build the breakdown row, with one chip per destination kind.
         *
         * @param {Object} bd Counts as returned by destBreakdown().
         * @return {jQuery} The breakdown row.
         */
        buildBreakdownRow: function(bd) {
            var $bd = $('<div>').addClass('ct-breakdown ct-mt');
            $bd.append($('<span>').addClass('ct-breakdown-lbl').text(this.S.rw_cat_breakdown || 'Breakdown'));
            if (bd.nnew > 0) {
                $bd.append($('<span>').addClass('ct-chip ct-chip--new')
                    .text((this.S.rw_bd_new || '{$a}').replace('{$a}', bd.nnew)));
            }
            if (bd.nexisting > 0) {
                $bd.append($('<span>').addClass('ct-chip ct-chip--existing')
                    .text((this.S.rw_bd_existing || '{$a}').replace('{$a}', bd.nexisting)));
            }
            if (bd.nreplace > 0) {
                $bd.append($('<span>').addClass('ct-chip ct-chip--replace')
                    .text((this.S.rw_bd_replace || '{$a}').replace('{$a}', bd.nreplace)));
            }
            return $bd;
        },

        /**
         * Show or hide the notice warning that the origins will be deleted.
         */
        renderRemoveOrigin: function() {
            var s = this.state;
            var $ro = this.region('review-removeorigin');
            if (!s.removeorigin) {
                $ro.prop('hidden', true);
                return;
            }
            var kind = s.type === 'category'
                ? (this.S.rw_categories_pl || '')
                : (this.S.rw_courses_pl || '');
            var txt = (this.S.rw_review_removeorigin || '{$a->count} {$a->kind} — {$a->site}')
                .replace('{$a->count}', this.selectedIds().length)
                .replace('{$a->kind}', kind)
                .replace('{$a->site}', s.sitename || '');
            this.$root.find('[data-region="review-removeorigin-text"]').text(txt);
            $ro.prop('hidden', false);
        },

        renderReview: function() {
            var s = this.state;
            var self = this;
            var items = this.selectedIds();
            var count = items.length;
            var iscat = s.type === 'category';
            var kindlabel = iscat
                ? (this.S.rw_kind_category || 'Category')
                : (this.S.rw_kind_course || 'Course');
            var bd = this.destBreakdown(items, iscat);

            var $review = this.region('review').empty();

            // Hero.
            var $hero = $('<div>').addClass('ct-review-hero');
            $hero.append($('<span>').addClass('ct-review-hero-icon')
                .append($('<i>').addClass('fa fa-download').attr('aria-hidden', 'true')));
            var $hbody = $('<div>').addClass('ct-review-hero-body');
            $hbody.append($('<div>').addClass('ct-review-eyebrow').text(this.S.rw_review_selected || ''));
            $hbody.append($('<div>').addClass('ct-review-headline').text(count + ' · ' + kindlabel));
            // Origin platform in the origin colour (teal), consistent everywhere.
            var $hsub = $('<div>').addClass('ct-review-sub');
            $hsub.append(document.createTextNode((this.S.rw_review_from || '') + ' '));
            $hsub.append($('<span>').addClass('ct-review-env ct-review-env--origin').text(s.sitename || ''));
            $hbody.append($hsub);
            $hero.append($hbody);
            $hero.append($('<span>').addClass('ct-review-count').text(count));
            $review.append($hero);

            // Per-item list: origin course to destination, plus the mode chip.
            var $list = $('<div>').addClass('ct-review-list');
            items.slice(0, 8).forEach(function(id) {
                $list.append(self.buildReviewItem(id, iscat));
            });
            if (count > 8) {
                $list.append($('<div>').addClass('ct-review-more ct-text-muted')
                    .text((this.S.rw_more || '+{$a}').replace('{$a}', count - 8)));
            }
            $review.append($list);

            // Breakdown (course only) is only meaningful with several courses;
            // for a single course the item's own chip already says it.
            if (!iscat && count > 1) {
                $review.append(this.buildBreakdownRow(bd));
            }

            // Cross-cutting options summary (homogeneous field grid).
            $review.append(this.buildGlobalsGrid(iscat));

            var anyreplace = iscat ? (s.mode === 'replace') : (bd.nreplace > 0);
            this.region('review-danger').prop('hidden', !anyreplace);

            this.renderRemoveOrigin();
        },

        // ---- Submit -----------------------------------------------------

        /**
         * Send the restore request and move to the done screen.
         */
        submit: function() {
            var self = this;
            var s = this.state;
            var $primary = this.$root.find('[data-action="primary"]');
            $primary.prop('disabled', true);

            // Build the per-course destination config from the step-2 cards.
            var destcat = s.destcat;
            var courses = [];
            var catids = [];
            if (s.type === 'category') {
                catids = this.selectedIds();
            } else {
                courses = this.selectedIds().map(function(id) {
                    var d = self.state.dest[id] || {};
                    var existing = d.mode === 'existing';
                    var replace = existing && d.exmode === 'replace';
                    return {
                        origincourseid: id,
                        targetid: existing ? (d.targetid || 0) : 0,
                        categorytarget: existing ? 0 : (d.categorytarget || 0),
                        mode: d.exmode || 'merge',
                        removeenrols: replace ? !!d.delenrol : false,
                        removegroups: replace ? !!d.delgroups : false
                    };
                });
            }

            // Deferred execution: the WS expects an epoch in milliseconds (0 = now).
            var schedule = (s.scheduleon && s.scheduledate) ? (Date.parse(s.scheduledate) || 0) : 0;

            Ajax.call([{
                methodname: 'local_coursetransfer_restore_wizard_submit',
                args: {
                    siteid: s.siteid,
                    type: s.type,
                    courses: courses,
                    catids: catids,
                    targetcatid: destcat,
                    catmode: (s.mode === 'replace') ? 'replace' : 'merge',
                    includeusers: s.includeusers,
                    removeorigin: !!s.removeorigin,
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
         * Report a submit failure inline on the review step.
         *
         * @param {Object|null} resp
         */
        submitError: function(resp) {
            var msg = this.S.rw_submit_error || 'Error';
            if (resp && resp.errors && resp.errors.length && resp.errors[0].msg) {
                msg = resp.errors[0].msg;
            }
            var $danger = this.region('review-danger').prop('hidden', false);
            $danger.find('p').text(msg);
            this.$root.find('[data-action="primary"]').prop('disabled', false);
        }
    };

    return {
        init: function(selector) {
            Wizard.init(selector);
        }
    };
});
