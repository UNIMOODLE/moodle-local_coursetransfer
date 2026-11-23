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

/**
 * Upgrade helpers, kept apart from the plugin classes so a later change of those does not alter old steps.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove the origin token that 2.1 appended to local_coursetransfer_request.fileurl, leaving the rest as it was.
 *
 * @return int Rows cleaned.
 */
function local_coursetransfer_strip_legacy_tokens(): int {
    global $DB;
    $select = $DB->sql_like('fileurl', ':token', false);
    $rs = $DB->get_recordset_select('local_coursetransfer_request', $select, ['token' => '%token=%'], '', 'id, fileurl');
    $count = 0;
    foreach ($rs as $row) {
        $clean = local_coursetransfer_strip_token((string)$row->fileurl);
        if ($clean !== $row->fileurl) {
            $DB->set_field('local_coursetransfer_request', 'fileurl', $clean, ['id' => $row->id]);
            $count++;
        }
    }
    $rs->close();
    return $count;
}

/**
 * A URL without its token query parameter (other parameters and the fragment are kept).
 *
 * @param string $url
 * @return string
 */
function local_coursetransfer_strip_token(string $url): string {
    $parts = explode('?', $url, 2);
    if (count($parts) < 2) {
        return $url;
    }
    [$query, $fragment] = array_pad(explode('#', $parts[1], 2), 2, null);
    $kept = array_filter(explode('&', $query), fn($p) => $p !== '' && strtolower((string)strtok($p, '=')) !== 'token');
    return $parts[0] . ($kept ? '?' . implode('&', $kept) : '') . ($fragment !== null ? '#' . $fragment : '');
}

/**
 * Accept the current site policies for the plugin's service user, if it exists (LCT-074).
 *
 * Runs as the user who upgrades (an admin), with the same rules as Repair: see
 * \local_coursetransfer\coursetransfer::accept_site_policies(). It never fails the upgrade.
 *
 * @return bool Whether the service user exists and the site policies no longer block it.
 */
function local_coursetransfer_accept_ws_site_policies(): bool {
    global $CFG, $DB;
    $userid = $DB->get_field('user', 'id', [
        'username' => 'local_coursetransfer_ws',
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ]);
    if (!$userid) {
        return false;
    }
    return \local_coursetransfer\coursetransfer::accept_site_policies((int)$userid);
}

/**
 * Let the service user role see hidden categories, as a new installation does (LCT-068).
 *
 * Sites upgraded from 1.x kept a role without moodle/category:viewhiddencategories until Repair.
 *
 * @return bool Whether the role exists.
 */
function local_coursetransfer_allow_hidden_categories(): bool {
    global $DB;
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'local_coursetransfer_ws']);
    if (!$roleid) {
        return false;
    }
    assign_capability('moodle/category:viewhiddencategories', CAP_ALLOW, $roleid, context_system::instance(), true);
    return true;
}
