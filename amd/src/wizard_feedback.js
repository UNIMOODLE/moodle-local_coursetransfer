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
 * Shared feedback for the wizards: load errors from the origin (code, hint and
 * retry), submit results that must not be repeated blindly (partial or not
 * confirmed) and the deferred-execution date check.
 *
 * @module     local_coursetransfer/wizard_feedback
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/str'
], function($, Str) {
    "use strict";

    // Same limit as cli_helper::SCHEDULE_MAX_DAYS, which the server enforces.
    var SCHEDULE_MAX_DAYS = 30;
    var DAYMS = 24 * 60 * 60 * 1000;

    // Suggested action per transport error code: same catalogue as the
    // platforms page, so a failure reads the same everywhere.
    var HINTS = {
        '12001': 'platforms_hint_12001',
        '12002': 'platforms_hint_12002',
        '12003': 'platforms_hint_12003',
        '18001': 'platforms_hint_18001'
    };

    var KEYS = [
        'platforms_hint_12001', 'platforms_hint_12002', 'platforms_hint_12003', 'platforms_hint_18001',
        'retry', 'rw_done_gotolog', 'rw_load_error', 'rw_load_error_hint',
        'rw_submit_error', 'rw_submit_partial', 'rw_loading', 'rw_searching'
    ];

    var cache = null;

    /**
     * Lang strings of this module, fetched once.
     *
     * @return {Promise} Resolves to a key => text map (empty if they fail).
     */
    function strings() {
        if (!cache) {
            cache = Str.get_strings(KEYS.map(function(key) {
                return {key: key, component: 'local_coursetransfer'};
            })).then(function(values) {
                var map = {};
                KEYS.forEach(function(key, i) {
                    map[key] = values[i];
                });
                return map;
            }).catch(function() {
                return {};
            });
        }
        return cache;
    }

    /**
     * Every error of a WS response as "code: msg".
     *
     * @param {Object} failure WS response with errors[].
     * @return {String[]}
     */
    function errorLines(failure) {
        var list = (failure && failure.errors) ? failure.errors : [];
        return list.map(function(e) {
            return (e.code ? e.code + ': ' : '') + (e.msg || '');
        });
    }

    /**
     * First error as "code: msg", or the message of a core/ajax exception.
     *
     * @param {Object} failure WS response or exception.
     * @return {String}
     */
    function detail(failure) {
        var lines = errorLines(failure);
        if (lines.length) {
            return lines[0];
        }
        if (failure && typeof failure.message === 'string') {
            return failure.message;
        }
        return '';
    }

    /**
     * Code of the first error of a WS response.
     *
     * @param {Object} failure
     * @return {String}
     */
    function firstCode(failure) {
        if (failure && failure.errors && failure.errors.length && failure.errors[0].code) {
            return String(failure.errors[0].code);
        }
        return '';
    }

    /**
     * Suggested action for an error code (own lang strings, may carry <strong>).
     *
     * @param {Object} S Strings.
     * @param {String} code
     * @param {String} fallback Text when the code has no specific hint.
     * @return {String}
     */
    function hintFor(S, code, fallback) {
        var key = HINTS[code];
        if (!key || !S[key]) {
            return fallback || '';
        }
        // The remote must register OUR site as target: show our wwwroot.
        return S[key].replace('{$a}', M.cfg.wwwroot);
    }

    /**
     * Error block skeleton (styles.css .ct-error-block).
     *
     * @param {String} title Plain text.
     * @return {Object} {$block, $content}
     */
    function errorBlock(title) {
        var $block = $('<div>').addClass('ct-error-block').attr('role', 'alert');
        $block.append($('<i>').addClass('fa fa-exclamation-triangle ct-error-icon').attr('aria-hidden', 'true'));
        var $content = $('<div>').addClass('ct-error-content');
        $content.append($('<p>').addClass('ct-error-title').text(title));
        $block.append($content);
        return {$block: $block, $content: $content};
    }

    /**
     * Show a load failure (WS success=false or transport exception) with the
     * error code, what to do and a retry button, instead of an empty list.
     *
     * @param {jQuery} $box Container to fill.
     * @param {Object} failure WS response or exception.
     * @param {Function|null} retry Called by the "Retry" button.
     * @param {String} [title] Heading; defaults to rw_load_error.
     * @return {Promise}
     */
    function showLoadError($box, failure, retry, title) {
        // Empty now so no stale "no results" shows while strings resolve.
        $box.empty();
        return strings().then(function(S) {
            var head = title || S.rw_load_error || 'Error';
            var info = detail(failure);
            var parts = errorBlock(info ? (head + ' — ' + info) : head);
            var hint = hintFor(S, firstCode(failure), S.rw_load_error_hint);
            if (hint) {
                // Own lang string (may carry <strong>); remote values stay text.
                parts.$content.append($('<p>').addClass('ct-error-body').html(hint));
            }
            if (retry) {
                var $retry = $('<button>').attr('type', 'button')
                    .addClass('ct-btn ct-btn--secondary ct-btn--sm')
                    .append($('<i>').addClass('fa fa-refresh').attr('aria-hidden', 'true'))
                    .append(document.createTextNode(' ' + (S.retry || 'Retry')));
                $retry.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    retry();
                });
                parts.$content.append($('<div>').addClass('ct-error-actions').append($retry));
            }
            $box.empty().append(parts.$block);
            return parts.$block;
        });
    }

    /**
     * Request ids the server created, even when it reports success=false.
     *
     * @param {Object} resp Submit response.
     * @return {Number[]}
     */
    function launched(resp) {
        return (resp && resp.data && resp.data.requestids) ? resp.data.requestids : [];
    }

    /**
     * Classify a submit response.
     *
     * A response with created requests is never "failed", even with
     * success=false: running the wizard again would launch them twice.
     *
     * @param {Object} resp Submit response.
     * @return {String} done|partial|failed|unconfirmed
     */
    function outcome(resp) {
        if (!resp) {
            return 'unconfirmed';
        }
        if (resp.success) {
            return 'done';
        }
        if (launched(resp).length) {
            return 'partial';
        }
        return errorLines(resp).length ? 'failed' : 'unconfirmed';
    }

    /**
     * Show the outcome of a submit that did not fully succeed.
     *
     * - failed: nothing was created; errors with their code (the wizard lets
     *   the user fix and run again).
     * - partial: some requests were created; count, every error and the log.
     * - unconfirmed: no answer (timeout, 504, session): the server may have
     *   created requests, so check the log before repeating.
     *
     * @param {jQuery} $box Container to fill (shown).
     * @param {Object|null} failure WS response or exception.
     * @param {String} kind failed|partial|unconfirmed
     * @param {String} logurl Executions log of this wizard.
     * @return {Promise}
     */
    function showSubmitFailure($box, failure, kind, logurl) {
        $box.empty().prop('hidden', false);
        return strings().then(function(S) {
            var lines = errorLines(failure);
            var title;
            if (kind === 'partial') {
                title = (S.rw_submit_partial || '{$a}').replace('{$a}', launched(failure).length);
            } else if (kind === 'unconfirmed') {
                title = S.rw_submit_error || 'Error';
                if (failure && typeof failure.message === 'string' && failure.message) {
                    lines = [failure.message];
                }
            } else {
                title = lines.shift();
            }
            var parts = errorBlock(title);
            if (lines.length) {
                var $list = $('<ul>').addClass('ct-error-list');
                lines.forEach(function(line) {
                    $list.append($('<li>').text(line));
                });
                parts.$content.append($list);
            }
            if (kind === 'failed') {
                var hint = hintFor(S, firstCode(failure), '');
                if (hint) {
                    parts.$content.append($('<p>').addClass('ct-error-body').html(hint));
                }
            } else if (logurl && logurl !== '#') {
                parts.$content.append($('<div>').addClass('ct-error-actions').append(
                    $('<a>').addClass('ct-btn ct-btn--secondary ct-btn--sm').attr('href', logurl)
                        .append($('<i>').addClass('fa fa-list').attr('aria-hidden', 'true'))
                        .append(document.createTextNode(' ' + (S.rw_done_gotolog || 'Log')))));
            }
            $box.empty().append(parts.$block);
            return parts.$block;
        });
    }

    /**
     * Validate the deferred-execution date: when on, a valid future date no
     * further than SCHEDULE_MAX_DAYS (the server rejects anything else).
     *
     * @param {Boolean} on Whether "schedule" is switched on.
     * @param {String} value datetime-local value (local time).
     * @return {Object} {valid, ts}; ts in ms, 0 = run now.
     */
    function scheduleCheck(on, value) {
        if (!on) {
            return {valid: true, ts: 0};
        }
        var ts = Date.parse(value || '') || 0;
        var now = Date.now();
        var valid = ts > now && ts <= now + SCHEDULE_MAX_DAYS * DAYMS;
        return {valid: valid, ts: valid ? ts : 0};
    }

    /**
     * Format a timestamp as a datetime-local value (local time).
     *
     * @param {Number} ms
     * @return {String} YYYY-MM-DDTHH:MM
     */
    function localValue(ms) {
        var d = new Date(ms);
        var pad = function(n) {
            return (n < 10 ? '0' : '') + n;
        };
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
            + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    /**
     * Limit the date picker to the accepted window (now .. now + max days).
     *
     * @param {jQuery} $input datetime-local input.
     */
    function scheduleBounds($input) {
        var now = Date.now();
        $input.attr('min', localValue(now)).attr('max', localValue(now + SCHEDULE_MAX_DAYS * DAYMS));
    }

    /**
     * Show that a listing is being loaded or searched: a spinner line under the search box and the rows already
     * shown dimmed, so it is clear the list below is about to change (LCT-120).
     *
     * @param {jQuery} $root Wizard root.
     * @param {jQuery} $rows Rows container.
     * @param {String} query Text searched ('' for a plain load).
     * @param {String} site Platform being asked.
     */
    function searchBusy($root, $rows, query, site) {
        var $status = $root.find('[data-region="search-status"]').first();
        if (!$status.length) {
            $status = $('<div>').addClass('ct-searchstatus')
                .attr({'data-region': 'search-status', 'role': 'status', 'aria-live': 'polite'});
            $root.find('.ct-search').first().after($status);
        }
        $rows.addClass('ct-rows--busy').attr('aria-busy', 'true');
        strings().then(function(s) {
            if (!$rows.hasClass('ct-rows--busy')) {
                // The answer arrived first.
                return null;
            }
            var text = query ? (s.rw_searching || '…').replace('{$a}', site || '') : (s.rw_loading || '…');
            $status.empty().append(
                $('<i>').addClass('fa fa-spinner fa-spin').attr('aria-hidden', 'true'),
                $('<span>').text(text)
            ).prop('hidden', false);
            return null;
        }).catch(function() {
            return null;
        });
    }

    /**
     * The listing has its answer: remove the spinner line and the dimming.
     *
     * @param {jQuery} $root Wizard root.
     * @param {jQuery} $rows Rows container.
     */
    function searchDone($root, $rows) {
        $rows.removeClass('ct-rows--busy').attr('aria-busy', 'false');
        $root.find('[data-region="search-status"]').prop('hidden', true).empty();
    }

    return {
        launched: launched,
        outcome: outcome,
        scheduleBounds: scheduleBounds,
        scheduleCheck: scheduleCheck,
        searchBusy: searchBusy,
        searchDone: searchDone,
        showLoadError: showLoadError,
        showSubmitFailure: showSubmitFailure
    };
});
