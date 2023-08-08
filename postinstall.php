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
 * Plugin post installation configuration.
 *
 * @package     local_coursetransfer
 * @copyright   2023 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetransfer\factory\role;
use local_coursetransfer\factory\user;

require(__DIR__.'/../../config.php');
global $CFG, $DB;
require($CFG->libdir . '/externallib.php');

require_login();

if (is_siteadmin()) {

    // 1. Create Role.
    $roleid = role::create_roles();

    // 2. Add Permission.
    role::add_capability($roleid, 'moodle/category:viewcourselist');
    role::add_capability($roleid, 'moodle/course:view');
    role::add_capability($roleid, 'moodle/course:create');
    role::add_capability($roleid, 'moodle/backup:backuptargetimport');
    role::add_capability($roleid, 'moodle/backup:backupcourse');
    role::add_capability($roleid, 'moodle/backup:backupactivity');
    role::add_capability($roleid, 'moodle/backup:backupsection');
    role::add_capability($roleid, 'moodle/backup:downloadfile');
    role::add_capability($roleid, 'moodle/backup:userinfo');
    role::add_capability($roleid, 'moodle/backup:anonymise');
    role::add_capability($roleid, 'moodle/backup:configure');
    role::add_capability($roleid, 'webservice/rest:use');
    role::add_capability($roleid, 'moodle/restore:restoreactivity');
    role::add_capability($roleid, 'moodle/restore:restorecourse');
    role::add_capability($roleid, 'moodle/restore:restoresection');
    role::add_capability($roleid, 'moodle/restore:restoretargetimport');
    role::add_capability($roleid, 'moodle/restore:uploadfile');
    role::add_capability($roleid, 'moodle/restore:rolldates');
    role::add_capability($roleid, 'moodle/restore:userinfo');
    role::add_capability($roleid, 'moodle/restore:viewautomatedfilearea');
    role::add_capability($roleid, 'moodle/restore:createuser');
    role::add_capability($roleid, 'moodle/site:maintenanceaccess');

    // 3. Create User.
    $userid = user::create_user($roleid);

    // 4. Create Token.
    user::create_token($userid);


    // 7. Redirigir al INDEX
    header("Location: $CFG->wwwroot/local/coursetransfer/index.php");
}


