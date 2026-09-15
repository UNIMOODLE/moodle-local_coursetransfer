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
 * Unified paired-platforms page: wizard, row test and delete modal.
 *
 * @module     local_coursetransfer/platforms_page
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/str',
    'core/ajax'
], function($, Str, Ajax) {
    "use strict";

    var SERVICES = {
        SITE_ADD: 'local_coursetransfer_site_add',
        SITE_EDIT: 'local_coursetransfer_site_edit',
        SITE_REMOVE: 'local_coursetransfer_site_remove',
        SITE_CHECK: 'local_coursetransfer_site_check',
        SITE_TEST: 'local_coursetransfer_site_test',
    };

    var BANNERKEY = 'local_coursetransfer_platforms_banner';
    var AUTOTESTKEY = 'local_coursetransfer_platforms_autotest';

    var state = {};
    var strings = {};
    var $root = null;

    /**
     * Reset the wizard state.
     */
    function resetState() {
        state = {
            wStep: 0,
            roles: {origin: false, target: false},
            originid: 0,
            targetid: 0,
            name: '',
            host: '',
            token: '',
            testok: false,
            saving: false,
        };
    }

    /**
     * Escape a dynamic value before inserting it into banner HTML.
     *
     * @param {String} value
     * @return {String}
     */
    function escapeHtml(value) {
        return $('<span>').text(value || '').html();
    }

    /**
     * First error message of a WS response.
     *
     * @param {Object} response
     * @return {String}
     */
    function errorMsg(response) {
        if (response && response.errors && response.errors.length > 0) {
            var e = response.errors[0];
            return (e.code ? e.code + ': ' : '') + (e.msg || '');
        }
        return strings.unknown;
    }

    /**
     * Show the action banner. The text is rendered as HTML so our lang
     * strings can carry <strong>: every DYNAMIC value (names, remote
     * messages) must be passed through escapeHtml() by the caller.
     *
     * @param {String} tone success|error
     * @param {String} html
     */
    function showBanner(tone, html) {
        var $banner = $root.find('[data-region="banner"]');
        $banner.removeClass('ct-banner--success ct-banner--error')
            .addClass(tone === 'error' ? 'ct-banner--error' : 'ct-banner--success');
        $banner.find('[data-region="banner-text"]').html(html);
        $banner.prop('hidden', false);
    }

    /**
     * Store a banner to show after the page reloads.
     *
     * @param {String} tone
     * @param {String} text
     */
    function bannerAfterReload(tone, text) {
        try {
            sessionStorage.setItem(BANNERKEY, JSON.stringify({tone: tone, text: text}));
        } catch (e) {
            // Session storage unavailable: the refreshed list is feedback enough.
        }
        window.location.reload();
    }

    /**
     * Whether the current step allows moving on.
     *
     * @return {Boolean}
     */
    function canProceed() {
        if (state.wStep === 0) {
            return state.roles.origin || state.roles.target;
        }
        if (state.wStep === 1) {
            return state.name.trim() !== '' && state.host.trim() !== '' && state.token.trim() !== '';
        }
        // Step 3: the test is recommended but never blocks saving — cross
        // pairing means one side always has to register first (and its
        // origin test will fail with 18001 until the other side does too).
        return true;
    }

    /**
     * Repaint the wizard from state.
     */
    function renderWizard() {
        var $wizard = $root.find('[data-region="wizard"]');
        ['w0', 'w1', 'w2'].forEach(function(region, i) {
            $wizard.find('[data-region="' + region + '"]').prop('hidden', state.wStep !== i);
        });
        $wizard.find('.ct-step').each(function() {
            var i = parseInt($(this).attr('data-step'), 10);
            var stepstate = 'pending';
            if (i < state.wStep) {
                stepstate = 'done';
            } else if (i === state.wStep) {
                stepstate = 'current';
            }
            $(this).attr('data-state', stepstate);
        });
        $wizard.find('[data-region="stepnum"]').text(state.wStep + 1);
        $wizard.find('[data-action="toggle-origin"]').attr('aria-pressed', state.roles.origin ? 'true' : 'false');
        $wizard.find('[data-action="toggle-target"]').attr('aria-pressed', state.roles.target ? 'true' : 'false');
        $wizard.find('[data-region="w2-host"]').text(state.host);
        if (!state.testok) {
            $wizard.find('[data-region="test-results"]').prop('hidden', true).empty();
            $wizard.find('[data-region="test-idle"]').prop('hidden', false);
        }
        var $back = $wizard.find('[data-action="back"]');
        $back.prop('disabled', state.wStep === 0).toggleClass('ct-btn--disabled', state.wStep === 0);
        var $primary = $wizard.find('[data-action="primary"]');
        $primary.find('[data-region="primary-label"]').text(state.wStep === 2 ? strings.save : strings.next);
        $primary.find('[data-region="primary-arrow"]').prop('hidden', state.wStep === 2);
        refreshPrimary();
    }

    /**
     * Enable/disable the primary button from validation state.
     */
    function refreshPrimary() {
        var can = canProceed() && !state.saving;
        $root.find('[data-action="primary"]').prop('disabled', !can).toggleClass('ct-btn--disabled', !can);
    }

    /**
     * Open the wizard, optionally loading a platform card for edition.
     *
     * @param {jQuery|null} $card
     */
    function openWizard($card) {
        resetState();
        if ($card) {
            state.originid = parseInt($card.attr('data-originid'), 10) || 0;
            state.targetid = parseInt($card.attr('data-targetid'), 10) || 0;
            state.roles.origin = state.originid > 0;
            state.roles.target = state.targetid > 0;
            state.name = $card.attr('data-name') || '';
            state.host = $card.attr('data-host') || '';
            // Keep the stored token so editing other fields never loses it.
            state.token = $card.attr('data-token') || '';
        }
        var $wizard = $root.find('[data-region="wizard"]');
        $wizard.find('[data-input="name"]').val(state.name);
        $wizard.find('[data-input="host"]').val(state.host);
        $wizard.find('[data-input="token"]').val(state.token).attr('type', 'password');
        $root.find('[data-region="list"]').prop('hidden', true);
        $wizard.prop('hidden', false);
        renderWizard();
    }

    /**
     * Close the wizard and show the list again.
     */
    function closeWizard() {
        $root.find('[data-region="wizard"]').prop('hidden', true);
        $root.find('[data-region="list"]').prop('hidden', false);
    }

    /**
     * Build the site_check calls for every selected role.
     *
     * @return {Array}
     */
    function checkCalls() {
        var calls = [];
        ['origin', 'target'].forEach(function(type) {
            if (state.roles[type]) {
                calls.push({
                    methodname: SERVICES.SITE_CHECK,
                    args: {type: type, host: state.host.trim(), token: state.token.trim()},
                });
            }
        });
        return calls;
    }

    /**
     * Whether a host is this very platform (self-pairing to import
     * courses within the same site).
     *
     * @param {String} host
     * @return {Boolean}
     */
    function isSelfHost(host) {
        var normalize = function(url) {
            return (url || '').trim().replace(/\/+$/, '').toLowerCase();
        };
        return normalize(host) === normalize(M.cfg.wwwroot);
    }

    /**
     * Suggested action for a plugin error code (transport catalog).
     *
     * @param {String} code
     * @return {String}
     */
    function hintFor(code) {
        if (code === '12001') {
            return strings.hint12001;
        }
        if (code === '12002') {
            return strings.hint12002;
        }
        if (code === '12003') {
            return strings.hint12003;
        }
        if (code === '18001') {
            // The remote must register OUR site as target: show our wwwroot.
            return strings.hint18001.replace('{$a}', M.cfg.wwwroot);
        }
        return strings.hintdefault;
    }

    /**
     * Render one diagnostic row per tested role. Every remote value is
     * inserted as TEXT (never HTML): the remote response is untrusted.
     *
     * @param {Array} results [{role, ok, code, msg}]
     */
    function renderTestResults(results) {
        var $container = $root.find('[data-region="test-results"]');
        $container.empty().prop('hidden', false);
        results.forEach(function(result) {
            var rolelabel = result.role === 'origin' ? strings.roleorigin : strings.roletarget;
            // Own lang strings may carry <strong> markup and are rendered as
            // HTML; anything coming from the remote response stays as TEXT.
            if (result.pending) {
                var $pending = $('<div>').addClass('ct-alert ct-alert--warning ct-mt');
                $pending.append($('<i>').addClass('fa fa-clock-o ct-alert-icon').attr('aria-hidden', 'true'));
                $pending.append($('<p>').html(rolelabel + ' — ' + strings.rolepending));
                $container.append($pending);
            } else if (result.ok) {
                var $ok = $('<div>').addClass('ct-alert ct-alert--success ct-mt');
                $ok.append($('<i>').addClass('fa fa-check-circle ct-alert-icon').attr('aria-hidden', 'true'));
                $ok.append($('<p>').text(rolelabel + ' — ' + strings.roleok));
                $container.append($ok);
            } else {
                var detail = (result.code ? result.code + ': ' : '') + (result.msg || strings.unknown);
                var $ko = $('<div>').addClass('ct-error-block ct-mt');
                $ko.append($('<i>').addClass('fa fa-exclamation-triangle ct-error-icon').attr('aria-hidden', 'true'));
                var $content = $('<div>').addClass('ct-error-content');
                $content.append($('<p>').addClass('ct-error-title').text(rolelabel + ' — ' + detail));
                $content.append($('<p>').addClass('ct-error-body').html(hintFor(result.code)));
                $ko.append($content);
                $container.append($ko);
            }
        });
    }

    /**
     * Run the pre-save connection test (wizard step 3): one check per
     * selected role, reporting exactly what the remote platform returned.
     */
    function runTest() {
        var $wizard = $root.find('[data-region="wizard"]');
        var $label = $wizard.find('[data-region="testbtn-label"]');
        $label.text(strings.testing);
        state.testok = false;
        var roles = ['origin', 'target'].filter(function(type) {
            return state.roles[type];
        });
        var promises = Ajax.call(checkCalls());
        $.when.apply($, promises).then(function() {
            var responses = roles.length === 1 ? [arguments[0]] : Array.prototype.slice.call(arguments);
            var results = responses.map(function(response, i) {
                var first = (response.errors && response.errors[0]) || {};
                var result = {
                    role: roles[i],
                    ok: !!response.success,
                    code: first.code || '',
                    msg: first.msg || '',
                };
                // Self-pairing: the origin check needs the "I send to it"
                // registration of this very platform, which will be created
                // on save. Not an error: report it as pending.
                if (!result.ok && result.role === 'origin' && result.code === '18001'
                        && state.roles.target && isSelfHost(state.host)) {
                    result.pending = true;
                }
                return result;
            });
            state.testok = results.every(function(r) {
                return r.ok;
            });
            $label.text(strings.retest);
            $wizard.find('[data-region="test-idle"]').prop('hidden', true);
            renderTestResults(results);
            refreshPrimary();
            return null;
        }).catch(function(e) {
            $label.text(strings.retest);
            $wizard.find('[data-region="test-idle"]').prop('hidden', true);
            var detail = (e && e.message) ? e.message : strings.unknown;
            if (e && e.debuginfo) {
                detail += ' — ' + e.debuginfo;
            }
            renderTestResults(roles.map(function(role) {
                return {role: role, ok: false, code: (e && e.errorcode) || '', msg: detail};
            }));
            refreshPrimary();
        });
    }

    /**
     * Persist the platform: add/edit/remove per role.
     */
    function save() {
        var calls = [];
        ['origin', 'target'].forEach(function(type) {
            var id = type === 'origin' ? state.originid : state.targetid;
            var args = {
                type: type,
                host: state.host.trim(),
                token: state.token.trim(),
                name: state.name.trim(),
            };
            if (state.roles[type]) {
                if (id > 0) {
                    args.id = id;
                    calls.push({methodname: SERVICES.SITE_EDIT, args: args});
                } else {
                    calls.push({methodname: SERVICES.SITE_ADD, args: args});
                }
            } else if (id > 0) {
                calls.push({methodname: SERVICES.SITE_REMOVE, args: {type: type, id: id}});
            }
        });
        state.saving = true;
        refreshPrimary();
        var promises = Ajax.call(calls);
        $.when.apply($, promises).then(function() {
            var responses = calls.length === 1 ? [arguments[0]] : Array.prototype.slice.call(arguments);
            var failed = responses.filter(function(r) {
                return !r.success;
            });
            if (failed.length === 0) {
                if (!state.testok && isSelfHost(state.host)) {
                    // Self-pairing: the origin check only needed the row we
                    // just saved. Re-test automatically after the reload so
                    // the card turns green without manual action.
                    try {
                        sessionStorage.setItem(AUTOTESTKEY, state.host.trim().replace(/\/+$/, ''));
                    } catch (err) {
                        // Storage unavailable: the admin can test manually.
                    }
                }
                bannerAfterReload('success', state.testok ? strings.saved : strings.saveduntested);
            } else {
                state.saving = false;
                showBanner('error', escapeHtml(errorMsg(failed[0])));
                closeWizard();
            }
            return null;
        }).catch(function(e) {
            state.saving = false;
            showBanner('error', escapeHtml(e.message || strings.unknown));
            closeWizard();
        });
    }

    /**
     * Test a platform row (persists the result server-side) and reload.
     *
     * @param {jQuery} $card
     * @param {jQuery} $button
     */
    function testRow($card, $button) {
        if ($button.hasClass('ct-spin')) {
            return;
        }
        $button.addClass('ct-spin ct-iconbtn--active');
        var calls = [];
        var originid = parseInt($card.attr('data-originid'), 10) || 0;
        var targetid = parseInt($card.attr('data-targetid'), 10) || 0;
        if (originid > 0) {
            calls.push({methodname: SERVICES.SITE_TEST, args: {type: 'origin', id: originid}});
        }
        if (targetid > 0) {
            calls.push({methodname: SERVICES.SITE_TEST, args: {type: 'target', id: targetid}});
        }
        var promises = Ajax.call(calls);
        $.when.apply($, promises).then(function() {
            var responses = calls.length === 1 ? [arguments[0]] : Array.prototype.slice.call(arguments);
            var ok = responses.every(function(r) {
                return r.success;
            });
            var name = '<strong>' + escapeHtml($card.attr('data-name')) + '</strong>';
            bannerAfterReload(ok ? 'success' : 'error',
                ok ? strings.testrowok.replace('{$a}', name) : strings.testrowko.replace('{$a}', name));
            return null;
        }).catch(function(e) {
            $button.removeClass('ct-spin ct-iconbtn--active');
            showBanner('error', escapeHtml(e.message || strings.unknown));
        });
    }

    /**
     * Open the delete confirmation modal.
     *
     * @param {jQuery} $card
     */
    function openDelete($card) {
        var $modal = $root.find('[data-region="delete-modal"]');
        $modal.data('card', $card);
        $modal.find('[data-region="delete-host"]').text($card.attr('data-name') + ' (' + $card.attr('data-host') + ')');
        $modal.find('[data-region="delete-inuse"]').prop('hidden', !$card.attr('data-inuse'));
        $modal.prop('hidden', false);
    }

    /**
     * Delete both role rows of a platform.
     */
    function confirmDelete() {
        var $modal = $root.find('[data-region="delete-modal"]');
        var $card = $modal.data('card');
        if (!$card) {
            return;
        }
        var calls = [];
        var originid = parseInt($card.attr('data-originid'), 10) || 0;
        var targetid = parseInt($card.attr('data-targetid'), 10) || 0;
        if (originid > 0) {
            calls.push({methodname: SERVICES.SITE_REMOVE, args: {type: 'origin', id: originid}});
        }
        if (targetid > 0) {
            calls.push({methodname: SERVICES.SITE_REMOVE, args: {type: 'target', id: targetid}});
        }
        var promises = Ajax.call(calls);
        $.when.apply($, promises).then(function() {
            var responses = calls.length === 1 ? [arguments[0]] : Array.prototype.slice.call(arguments);
            var failed = responses.filter(function(r) {
                return !r.success;
            });
            $modal.prop('hidden', true);
            if (failed.length === 0) {
                bannerAfterReload('success', strings.deleted);
            } else {
                showBanner('error', escapeHtml(errorMsg(failed[0])));
            }
            return null;
        }).catch(function(e) {
            $modal.prop('hidden', true);
            showBanner('error', escapeHtml(e.message || strings.unknown));
        });
    }

    /**
     * Bind all events.
     */
    function bind() {
        $root.on('click', '[data-action="add"]', function() {
            openWizard(null);
        });
        $root.on('click', '[data-region="platform"] [data-action="edit"]', function() {
            openWizard($(this).closest('[data-region="platform"]'));
        });
        $root.on('click', '[data-region="platform"] [data-action="test"]', function() {
            testRow($(this).closest('[data-region="platform"]'), $(this));
        });
        $root.on('click', '[data-region="platform"] [data-action="delete"]', function() {
            openDelete($(this).closest('[data-region="platform"]'));
        });
        $root.on('click', '[data-action="dismissbanner"]', function() {
            $root.find('[data-region="banner"]').prop('hidden', true);
        });
        $root.on('click', '[data-action="toggle-origin"]', function() {
            state.roles.origin = !state.roles.origin;
            renderWizard();
        });
        $root.on('click', '[data-action="toggle-target"]', function() {
            state.roles.target = !state.roles.target;
            renderWizard();
        });
        $root.on('input', '[data-input]', function() {
            state[$(this).attr('data-input')] = $(this).val();
            state.testok = false;
            refreshPrimary();
        });
        $root.on('click', '[data-action="reveal"]', function() {
            var $token = $root.find('[data-input="token"]');
            $token.attr('type', $token.attr('type') === 'password' ? 'text' : 'password');
        });
        $root.on('click', '[data-action="gostep"]', function() {
            var step = parseInt($(this).attr('data-step'), 10);
            if (step < state.wStep) {
                state.wStep = step;
                renderWizard();
            }
        });
        $root.on('click', '[data-action="back"]', function() {
            if (state.wStep > 0) {
                state.wStep--;
                renderWizard();
            }
        });
        $root.on('click', '[data-action="cancel"]', function() {
            closeWizard();
        });
        $root.on('click', '[data-action="primary"]', function() {
            if (!canProceed() || state.saving) {
                return;
            }
            if (state.wStep < 2) {
                state.wStep++;
                renderWizard();
            } else {
                save();
            }
        });
        $root.on('click', '[data-action="runtest"]', function() {
            runTest();
        });
        $root.on('click', '[data-action="canceldelete"]', function() {
            $root.find('[data-region="delete-modal"]').prop('hidden', true);
        });
        $root.on('click', '[data-action="confirmdelete"]', function() {
            confirmDelete();
        });
    }

    /**
     * Show a banner stored before the last reload.
     */
    function restoreBanner() {
        try {
            var stored = sessionStorage.getItem(BANNERKEY);
            if (stored) {
                sessionStorage.removeItem(BANNERKEY);
                var banner = JSON.parse(stored);
                showBanner(banner.tone, banner.text);
            }
        } catch (e) {
            // Nothing to restore.
        }
    }

    /**
     * Run the pending auto-test scheduled before the last reload
     * (self-pairing save).
     */
    function runAutoTest() {
        var host = null;
        try {
            host = sessionStorage.getItem(AUTOTESTKEY);
            if (host) {
                sessionStorage.removeItem(AUTOTESTKEY);
            }
        } catch (e) {
            return;
        }
        if (!host) {
            return;
        }
        $root.find('[data-region="platform"]').each(function() {
            var $card = $(this);
            if (($card.attr('data-host') || '') === host) {
                testRow($card, $card.find('[data-action="test"]'));
            }
        });
    }

    return {
        /**
         * @param {String} selector
         */
        init: function(selector) {
            $root = $(selector);
            resetState();
            var keys = [
                {key: 'platforms_next', component: 'local_coursetransfer'},
                {key: 'platforms_save', component: 'local_coursetransfer'},
                {key: 'platforms_testing', component: 'local_coursetransfer'},
                {key: 'platforms_retest', component: 'local_coursetransfer'},
                {key: 'platforms_saved', component: 'local_coursetransfer'},
                {key: 'platforms_deleted', component: 'local_coursetransfer'},
                {key: 'platforms_testrow_ok', component: 'local_coursetransfer'},
                {key: 'platforms_testrow_ko', component: 'local_coursetransfer'},
                {key: 'unknown_error', component: 'local_coursetransfer'},
                {key: 'platforms_role_origin', component: 'local_coursetransfer'},
                {key: 'platforms_role_target', component: 'local_coursetransfer'},
                {key: 'platforms_test_role_ok', component: 'local_coursetransfer'},
                {key: 'platforms_hint_12001', component: 'local_coursetransfer'},
                {key: 'platforms_hint_12002', component: 'local_coursetransfer'},
                {key: 'platforms_hint_12003', component: 'local_coursetransfer'},
                {key: 'platforms_hint_18001', component: 'local_coursetransfer'},
                {key: 'platform_error_action', component: 'local_coursetransfer'},
                {key: 'platforms_saved_untested', component: 'local_coursetransfer'},
                {key: 'platforms_test_role_pending', component: 'local_coursetransfer'},
            ];
            Str.get_strings(keys).then(function(results) {
                strings.next = results[0];
                strings.save = results[1];
                strings.testing = results[2];
                strings.retest = results[3];
                strings.saved = results[4];
                strings.deleted = results[5];
                strings.testrowok = results[6];
                strings.testrowko = results[7];
                strings.unknown = results[8];
                strings.roleorigin = results[9];
                strings.roletarget = results[10];
                strings.roleok = results[11];
                strings.hint12001 = results[12];
                strings.hint12002 = results[13];
                strings.hint12003 = results[14];
                strings.hint18001 = results[15];
                strings.hintdefault = results[16];
                strings.saveduntested = results[17];
                strings.rolepending = results[18];
                bind();
                restoreBanner();
                runAutoTest();
                return null;
            }).catch(function() {
                bind();
            });
        },
    };
});
