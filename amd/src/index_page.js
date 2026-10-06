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
 * Summary screen: copy / create / regenerate / revoke the site token.
 *
 * @module     local_coursetransfer/index_page
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

    var STRINGKEYS = [
        'idx_copy', 'idx_copied', 'rw_submit_error', 'idx_show', 'idx_hide',
        'idx_regen_title', 'idx_regen_body', 'idx_regen_note', 'idx_regenerate',
        'idx_revoke_title', 'idx_revoke_body', 'idx_revoke_note', 'idx_revoke_confirm'
    ];

    var Page = {

        /**
         * Entry point.
         *
         * @param {String} selector
         */
        init: function(selector) {
            var self = this;
            this.$root = $(selector);
            if (!this.$root.length) {
                return;
            }
            this.token = this.$root.attr('data-token') || '';
            this.kind = null;
            this.busy = false;
            this.S = {};

            Str.get_strings(STRINGKEYS.map(function(k) {
                return {key: k, component: 'local_coursetransfer'};
            })).then(function(v) {
                STRINGKEYS.forEach(function(k, i) {
                    self.S[k] = v[i];
                });
                return self.bind();
            }).catch(function() {
                self.bind();
            });
        },

        /**
         * Find a data-region node.
         *
         * @param {String} name
         * @return {jQuery}
         */
        region: function(name) {
            return this.$root.find('[data-region="' + name + '"]');
        },

        /**
         * Bind events.
         */
        bind: function() {
            var self = this;
            var $root = this.$root;

            $root.on('click', '[data-action="copy-token"]', function() {
                self.copy();
            });
            $root.on('click', '[data-action="toggle-token"]', function() {
                self.toggleToken($(this));
            });
            $root.on('click', '[data-action="create-token"]', function() {
                self.callToken('local_coursetransfer_token_create');
            });
            $root.on('click', '[data-action="ask"]', function() {
                self.openModal($(this).attr('data-kind'));
            });
            $root.on('click', '[data-action="modal-cancel"]', function() {
                self.closeModal();
            });
            $root.on('click', '[data-action="modal-confirm"]', function() {
                self.confirmModal();
            });
            $root.on('click', '[data-action="toggle-how"]', function() {
                self.toggleHow($(this));
            });
            $root.on('click', '[data-action="toggle-pill"]', function() {
                self.togglePill($(this));
            });
        },

        /**
         * Copy the token to the clipboard with feedback.
         */
        copy: function() {
            var self = this;
            var $input = this.region('token-input');
            var value = this.token || ($input.length ? $input.val() : '');
            if (!value) {
                return;
            }
            // The navigator.clipboard API is only available in secure contexts (HTTPS or
            // localhost); on plain HTTP it is undefined, so fall back to a
            // temporary textarea + execCommand, which works everywhere.
            if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(function() {
                    self.copyFeedback();
                    return null;
                }).catch(function() {
                    self.legacyCopy(value);
                });
            } else {
                this.legacyCopy(value);
            }
        },

        /**
         * Show / hide the token value.
         *
         * @param {jQuery} $btn
         */
        toggleToken: function($btn) {
            var $input = this.region('token-input');
            if (!$input.length) {
                return;
            }
            var shown = $input.attr('type') === 'text';
            $input.attr('type', shown ? 'password' : 'text');
            var label = shown ? (this.S.idx_show || 'Show') : (this.S.idx_hide || 'Hide');
            $btn.attr('aria-pressed', shown ? 'false' : 'true')
                .attr('title', label).attr('aria-label', label)
                .find('.fa').attr('class', 'fa ' + (shown ? 'fa-eye' : 'fa-eye-slash')).attr('aria-hidden', 'true');
        },

        /**
         * Fallback copy via a temporary textarea + execCommand.
         *
         * @param {String} value
         */
        legacyCopy: function(value) {
            var ta = document.createElement('textarea');
            ta.value = value;
            ta.setAttribute('readonly', 'readonly');
            ta.style.position = 'fixed';
            ta.style.top = '-1000px';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            var ok = false;
            try {
                ok = document.execCommand('copy');
            } catch (e) {
                ok = false;
            }
            document.body.removeChild(ta);
            if (ok) {
                this.copyFeedback();
            }
        },

        /**
         * Show the "Copied!" feedback on the copy button.
         */
        copyFeedback: function() {
            var self = this;
            var $lbl = this.region('copy-label');
            var $btn = this.$root.find('[data-action="copy-token"]');
            $lbl.text(this.S.idx_copied || 'Copied');
            $btn.addClass('ct-token-copy--done');
            window.setTimeout(function() {
                $lbl.text(self.S.idx_copy || 'Copy');
                $btn.removeClass('ct-token-copy--done');
            }, 2000);
        },

        /**
         * Open the confirmation modal for regenerate|revoke.
         *
         * @param {String} kind
         */
        openModal: function(kind) {
            this.kind = kind;
            var isrevoke = kind === 'revoke';
            this.region('modal-title').text(isrevoke ? this.S.idx_revoke_title : this.S.idx_regen_title);
            this.region('modal-body').text(isrevoke ? this.S.idx_revoke_body : this.S.idx_regen_body);
            this.region('modal-note').text(isrevoke ? this.S.idx_revoke_note : this.S.idx_regen_note);
            var $confirm = this.region('modal-confirm');
            $confirm.text(isrevoke ? this.S.idx_revoke_confirm : this.S.idx_regenerate)
                .removeClass('ct-btn--primary ct-btn--danger')
                .addClass(isrevoke ? 'ct-btn--danger' : 'ct-btn--primary');
            this.region('modal-icon').removeClass('ct-idx-modal-icon--danger ct-idx-modal-icon--primary')
                .addClass(isrevoke ? 'ct-idx-modal-icon--danger' : 'ct-idx-modal-icon--primary')
                .find('.fa').attr('class', 'fa ' + (isrevoke ? 'fa-ban' : 'fa-refresh')).attr('aria-hidden', 'true');
            this.region('modal-note-wrap').removeClass('ct-alert--warning ct-alert--danger')
                .addClass(isrevoke ? 'ct-alert--danger' : 'ct-alert--warning');
            this.region('modal').prop('hidden', false);
        },

        /**
         * Close the modal.
         */
        closeModal: function() {
            this.region('modal').prop('hidden', true);
            this.kind = null;
        },

        /**
         * Confirm the modal action.
         */
        confirmModal: function() {
            if (this.kind === 'revoke') {
                this.callToken('local_coursetransfer_token_revoke');
            } else if (this.kind === 'regenerate') {
                this.callToken('local_coursetransfer_token_regenerate');
            }
        },

        /**
         * Call a token web service and reload on success.
         *
         * @param {String} methodname
         */
        callToken: function(methodname) {
            var self = this;
            if (this.busy) {
                return;
            }
            this.busy = true;
            this.region('token-error').prop('hidden', true);
            Ajax.call([{methodname: methodname, args: {}}])[0].then(function(resp) {
                if (resp && resp.success) {
                    window.location.reload();
                } else {
                    self.busy = false;
                    self.closeModal();
                    self.error(resp);
                }
                return resp;
            }).catch(function() {
                self.busy = false;
                self.closeModal();
                self.error(null);
            });
        },

        /**
         * Show an inline error.
         *
         * @param {Object|null} resp
         */
        error: function(resp) {
            var msg = this.S.rw_submit_error || 'Error';
            if (resp && resp.errors && resp.errors.length && resp.errors[0].msg) {
                msg = resp.errors[0].msg;
            }
            this.region('token-error').prop('hidden', false);
            this.region('token-error-text').text(msg);
        },

        /**
         * Toggle the "how to connect" section.
         *
         * @param {jQuery} $btn
         */
        toggleHow: function($btn) {
            var $body = this.region('how-body');
            var open = $body.prop('hidden');
            $body.prop('hidden', !open);
            $btn.attr('aria-expanded', open ? 'true' : 'false');
            this.region('how').toggleClass('ct-idx-how--open', open);
        },

        /**
         * Toggle one "how to connect" scenario pill.
         *
         * @param {jQuery} $btn
         */
        togglePill: function($btn) {
            var $pill = $btn.closest('.ct-idx-pill');
            var $body = $pill.find('.ct-idx-pill-body');
            var open = $body.prop('hidden');
            $body.prop('hidden', !open);
            $btn.attr('aria-expanded', open ? 'true' : 'false');
            $pill.toggleClass('ct-idx-pill--open', open);
        }
    };

    return {
        init: function(selector) {
            Page.init(selector);
        }
    };
});
