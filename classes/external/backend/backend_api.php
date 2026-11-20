<?php
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

namespace local_coursetransfer\external\backend;

use context_system;
use core_external\external_api;

/**
 * Base of the web services that only a peer platform calls, with this site's service user token.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class backend_api extends external_api {
    /** @var string Capability that only the plugin's service user role holds. */
    const CAPABILITY = 'local/coursetransfer:webservice';

    /** @var string Config key of the last call made with a user lacking CAPABILITY (deprecated in 2.2). */
    const LEGACY_CONFIG = 'legacywsuser';

    /** @var int Seconds a recorded legacy call is still shown on the Summary screen. */
    const LEGACY_SHOWN = 30 * DAYSECS;

    /**
     * Require the caller to be the plugin's service user.
     *
     * These services check permissions on the user named in the request (field/value), so the
     * caller itself must be trusted: otherwise anyone could act as any user (TIPCOUTRAN-10, H8).
     * Transition (Decision 4): in 2.2 a web service token of this plugin's service whose user lacks the
     * capability is still accepted, and recorded; from a browser session (AJAX) it never is.
     *
     * @return bool Whether the call was accepted only through that deprecated path.
     * @throws \required_capability_exception
     */
    public static function validate_service_user(): bool {
        global $USER;
        $context = context_system::instance();
        external_api::validate_context($context);
        if (has_capability(self::CAPABILITY, $context)) {
            return false;
        }
        $token = (string)optional_param('wstoken', '', PARAM_ALPHANUM);
        if (self::is_legacy_service_user((int)$USER->id, $token, self::is_token_call())) {
            self::record_legacy_use((int)$USER->id);
            return true;
        }
        require_capability(self::CAPABILITY, $context);
        return false;
    }

    /**
     * Whether this request comes through the web service server with a token, not from a browser session.
     *
     * @return bool
     */
    protected static function is_token_call(): bool {
        return defined('WS_SERVER') && WS_SERVER && !(defined('AJAX_SCRIPT') && AJAX_SCRIPT);
    }

    /**
     * Whether a user lacking the capability may still call, as in 2.1: by token of this plugin's service, with REST.
     *
     * @param int $userid Calling user.
     * @param string $token Token of the call.
     * @param bool $tokencall Whether the call came through the web service server (never AJAX).
     * @return bool
     */
    public static function is_legacy_service_user(int $userid, string $token, bool $tokencall): bool {
        global $DB;
        if (!$tokencall || $token === '' || $userid <= 0 || isguestuser($userid)) {
            return false;
        }
        if (!has_capability('webservice/rest:use', context_system::instance(), $userid)) {
            return false;
        }
        return $DB->record_exists_sql(
            'SELECT 1
               FROM {external_tokens} t
               JOIN {external_services} s ON s.id = t.externalserviceid
              WHERE t.token = :token AND t.userid = :userid AND s.component = :component',
            ['token' => $token, 'userid' => $userid, 'component' => 'local_coursetransfer']
        );
    }

    /**
     * Record a call accepted through the deprecated path (never the token), shown on the Summary screen.
     *
     * @param int $userid
     */
    public static function record_legacy_use(int $userid): void {
        $last = self::get_legacy_use(PHP_INT_MAX);
        // At most once an hour per user: every write purges the plugin config cache.
        if ($last && (int)$last['userid'] === $userid && (int)$last['time'] > time() - HOURSECS) {
            return;
        }
        set_config(self::LEGACY_CONFIG, json_encode(['userid' => $userid, 'time' => time()]), 'local_coursetransfer');
    }

    /**
     * Last call accepted through the deprecated path, if recent.
     *
     * @param int $maxage Seconds.
     * @return array|null ['userid' => int, 'time' => int]
     */
    public static function get_legacy_use(int $maxage = self::LEGACY_SHOWN): ?array {
        $last = json_decode((string)get_config('local_coursetransfer', self::LEGACY_CONFIG), true);
        if (!is_array($last) || empty($last['userid']) || empty($last['time'])) {
            return null;
        }
        return time() - (int)$last['time'] <= $maxage ? $last : null;
    }
}
