<?php
// This file is part of the block_tresipuntsepe plugin for Moodle - http://moodle.org/
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
 * user
 *
 * @package     local_coursetransfer
 * @copyright   2023 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\factory;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

use coding_exception;
use context_system;
use dml_exception;
use moodle_exception;
use stdClass;

class user {

    const USERNAME_WS = 'local_coursetransfer_ws';

    /**
     * Create User.
     *
     * @param $roleid
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function create_user($roleid): int {
        global $DB;
        $username = self::USERNAME_WS;
        $email = 'coursetransfer@test.xxx';
        $name = 'Course Transfer';
        $lastname = 'WS';
        $desc = 'Local Course Transfer User for services web and cli script';
        $userrecord = $DB->get_record('user', ['username' => $username], '*');
        if (empty($userrecord)) {
            $userid = self::create($username, $email, $name, $lastname, $desc);
        } else {
            $userid = $userrecord->id;
        }
        role::add_role_to_user($roleid, $userid);
        return $userid;
    }

    /**
     * Create Token.
     *
     * @param int $userid
     * @return string|null
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function create_token(int $userid): ?string {
        global $DB;
        $token = null;
        $user = \core_user::get_user($userid);
        $externalserviceid = $DB->get_field('external_services',
                'id', array('component' => 'local_coursetransfer'));

        if ($externalserviceid) {
            $userauthorized = new stdClass();
            $userauthorized->externalserviceid = $externalserviceid;
            $userauthorized->userid = $user->id;
            $userauthorized->iprestriction = '';
            $userauthorized->validuntil = '';
            $userauthorized->timecreated = time();
            $DB->insert_record('external_services_users', $userauthorized);

            $usertokens = $DB->get_records('external_tokens', array(
                    'userid' => $user->id,
                    'tokentype' => EXTERNAL_TOKEN_PERMANENT,
                    'externalserviceid' => $externalserviceid
            ));

            if ($usertokens) {
                foreach ($usertokens as $usertoken) {
                    $token = $usertoken->token;
                }
            }
            if ($token === null) {
                try {
                    $token = external_generate_token(EXTERNAL_TOKEN_PERMANENT, $externalserviceid,
                            $user->id, context_system::instance());
                } catch (moodle_exception $e) {
                    debugging("Can't generate Token!!", serialize($e));
                }
            }
        }
        return $token;
    }

    /**
     * Create User.
     *
     * @param $username
     * @param $email
     * @param $firstname
     * @param $lastname
     * @param $desc
     * @return int
     * @throws moodle_exception
     */
    public static function create($username, $email, $firstname, $lastname, $desc): int {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $user = new stdClass();
        $user->username = $username;
        $user->password = generate_password(10);
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $email;
        $user->username = $username;
        $user->description = $desc;
        $user->confirmed = 1;
        $user->mnethostid = 1;
        return user_create_user($user);
    }

}
