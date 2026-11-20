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
 * Access tests for the web services that only peer platforms call.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external;

use advanced_testcase;
use core_external\external_api;
use core_external\external_description;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\external\backend\backend_api;
use local_coursetransfer\external\frontend\origin_remove_external;
use local_coursetransfer\factory\user;
use required_capability_exception;

/**
 * Peer-only services: not callable from AJAX and only by the plugin's service user.
 *
 * Before TIPCOUTRAN-10 any logged-in user could call them through /lib/ajax/service.php and pick
 * the user whose permissions were checked (H8).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\backend\backend_api
 * @covers     \local_coursetransfer\external\backend\origin_user_external
 * @covers     \local_coursetransfer\external\backend\origin_course_external
 * @covers     \local_coursetransfer\external\backend\origin_category_external
 * @covers     \local_coursetransfer\external\backend\origin_course_backup_external
 * @covers     \local_coursetransfer\external\backend\remove_external
 * @covers     \local_coursetransfer\external\backend\target_course_callback_external
 * @covers     \local_coursetransfer\external\backend\get_category_idnumber_external
 * @covers     \local_coursetransfer\external\frontend\sites_external
 * @covers     \local_coursetransfer\external\frontend\origin_remove_external
 */
final class backend_access_test extends advanced_testcase {
    /** @var string[] Services called only by a peer platform with the service user token. */
    private const PEER_FUNCTIONS = [
        'local_coursetransfer_origin_has_user',
        'local_coursetransfer_origin_get_courses',
        'local_coursetransfer_origin_get_categories',
        'local_coursetransfer_origin_get_course_detail',
        'local_coursetransfer_origin_get_category_detail',
        'local_coursetransfer_origin_get_category_detail_tree',
        'local_coursetransfer_origin_backup_course',
        'local_coursetransfer_origin_backup_category_course',
        'local_coursetransfer_target_backup_course_completed',
        'local_coursetransfer_target_backup_course_error',
        'local_coursetransfer_target_remove_course_completed',
        'local_coursetransfer_target_remove_course_error',
        'local_coursetransfer_origin_remove_course',
        'local_coursetransfer_origin_remove_category',
        'local_coursetransfer_origin_get_courses_by_ids',
        'local_coursetransfer_get_category_idnumber',
        'local_coursetransfer_site_origin_test',
        'local_coursetransfer_site_target_test',
    ];

    /** @var string[] Deprecated services no screen uses: kept for peers, but not from AJAX. */
    private const DEPRECATED_FUNCTIONS = [
        'local_coursetransfer_origin_remove_step1',
        'local_coursetransfer_origin_remove_step3',
        'local_coursetransfer_origin_remove_cat_step3',
    ];

    /**
     * Run as admin while the fixtures are created.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Build valid arguments for a function: ids that do not exist, so nothing is changed.
     *
     * @param external_description $desc
     * @param string $name
     * @return mixed
     */
    private function fake_value(external_description $desc, string $name) {
        if ($desc instanceof external_single_structure) {
            $value = [];
            foreach ($desc->keys as $key => $sub) {
                $value[$key] = $this->fake_value($sub, $key);
            }
            return $value;
        }
        if ($desc instanceof external_multiple_structure) {
            return [];
        }
        $named = [
            'field' => 'username',
            'value' => 'admin',
            'pattern' => '^NOTHING$',
            'targetsite' => 'https://nowhere.example.com',
            'fileurl' => 'https://nowhere.example.com/webservice/pluginfile.php/1/backup/course/0/x.mbz',
            'courseids' => '[]',
            'type' => 'course',
        ];
        if (isset($named[$name])) {
            return $named[$name];
        }
        switch ($desc->type) {
            case PARAM_INT:
                return 999999;
            case PARAM_BOOL:
                return false;
            default:
                return 'x';
        }
    }

    /**
     * Call a service the way the web service layer does, without the AJAX/session checks.
     *
     * @param string $function
     * @return array|null
     */
    private function call(string $function) {
        $info = external_api::external_function_info($function);
        $args = $this->fake_value($info->parameters_desc, '');
        $args = array_values(external_api::validate_parameters($info->parameters_desc, $args));
        return call_user_func_array([$info->classname, $info->methodname], $args);
    }

    /**
     * Peer-only and deprecated services are not exposed to the browser.
     */
    public function test_peer_functions_not_ajax(): void {
        foreach (array_merge(self::PEER_FUNCTIONS, self::DEPRECATED_FUNCTIONS) as $function) {
            $info = external_api::external_function_info($function);
            $this->assertFalse((bool) $info->allowed_from_ajax, $function);
        }
    }

    /**
     * Students and teachers cannot call peer-only services, whatever user they name.
     */
    public function test_peer_functions_reject_other_users(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $manager = $generator->create_user();
        $generator->role_assign('manager', $manager->id);

        foreach ([$student, $teacher, $manager] as $user) {
            $this->setUser($user);
            foreach (self::PEER_FUNCTIONS as $function) {
                try {
                    $this->call($function);
                    $this->fail("$function must reject {$user->username}");
                } catch (required_capability_exception $e) {
                    $capability = get_capability_string('local/coursetransfer:webservice');
                    $this->assertStringContainsString($capability, $e->getMessage(), $function);
                }
            }
        }
    }

    /**
     * The deprecated category removal checks the category capability, not the course one (LCT-039).
     */
    public function test_origin_remove_cat_step3_capability(): void {
        $generator = $this->getDataGenerator();
        $syscontext = \context_system::instance();
        $courseremover = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('local/coursetransfer:origin_remove_course', CAP_ALLOW, $roleid, $syscontext);
        $generator->role_assign($roleid, $courseremover->id);
        $catremover = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('local/coursetransfer:origin_remove_category', CAP_ALLOW, $roleid, $syscontext);
        $generator->role_assign($roleid, $catremover->id);

        $this->setUser($courseremover);
        $res = origin_remove_external::origin_remove_cat_step3(999999, 999999, 0);
        $this->assertSame('30100', (string) $res['errors'][0]['code']);

        // Past the capability check: it fails on the unknown site instead.
        $this->setUser($catremover);
        $res = origin_remove_external::origin_remove_cat_step3(999999, 999999, 0);
        $this->assertSame('30101', (string) $res['errors'][0]['code']);
    }

    /**
     * The plugin's service user created by postinstall can call every peer-only service.
     */
    public function test_peer_functions_allow_service_user(): void {
        coursetransfer::postinstall();
        $this->setUser(\core_user::get_user_by_username(user::USERNAME_WS));

        foreach (self::PEER_FUNCTIONS as $function) {
            $res = $this->call($function);
            $this->assertIsArray($res, $function);
            $this->assertArrayHasKey('success', $res, $function);
        }
    }

    /**
     * Transition (Decision 4): a token of this plugin's service whose user lacks the capability is accepted in 2.2
     * and recorded; the same user from a browser session, another service's token or no REST permission are not.
     */
    public function test_legacy_service_user(): void {
        global $DB;
        coursetransfer::postinstall();
        $service = $DB->get_record('external_services', ['component' => 'local_coursetransfer'], '*', MUST_EXIST);
        $legacy = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('webservice/rest:use', CAP_ALLOW, $roleid, \context_system::instance());
        $this->getDataGenerator()->role_assign($roleid, $legacy->id);
        $token = \core_external\util::generate_token(
            EXTERNAL_TOKEN_PERMANENT,
            $service,
            $legacy->id,
            \context_system::instance()
        );
        $othertoken = \core_external\util::generate_token(
            EXTERNAL_TOKEN_PERMANENT,
            $DB->get_record('external_services', ['shortname' => 'moodle_mobile_app'], '*', MUST_EXIST),
            $legacy->id,
            \context_system::instance()
        );

        $this->assertTrue(backend_api::is_legacy_service_user((int) $legacy->id, $token, true));
        $this->assertFalse(backend_api::is_legacy_service_user((int) $legacy->id, $token, false), 'Not from a session');
        $this->assertFalse(backend_api::is_legacy_service_user((int) $legacy->id, $othertoken, true), 'Not another service');
        $this->assertFalse(backend_api::is_legacy_service_user((int) $legacy->id, '', true));
        $student = $this->getDataGenerator()->create_user();
        $this->assertFalse(backend_api::is_legacy_service_user((int) $student->id, $token, true), 'Not the token owner');

        // Without WS_SERVER (AJAX, PHPUnit) the capability is still required, token or not.
        $_POST['wstoken'] = $token;
        $this->setUser($legacy);
        try {
            backend_api::validate_service_user();
            $this->fail('A session call must still require the capability');
        } catch (required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string(backend_api::CAPABILITY), $e->getMessage());
        } finally {
            unset($_POST['wstoken']);
        }

        // The service user itself is not the deprecated path.
        $this->setUser(\core_user::get_user_by_username(user::USERNAME_WS));
        $this->assertFalse(backend_api::validate_service_user());

        // What the Summary screen shows: the user and when, never the token.
        $this->assertNull(backend_api::get_legacy_use());
        backend_api::record_legacy_use((int) $legacy->id);
        $recorded = backend_api::get_legacy_use();
        $this->assertSame((int) $legacy->id, (int) $recorded['userid']);
        $this->assertStringNotContainsString($token, (string) get_config('local_coursetransfer', backend_api::LEGACY_CONFIG));
    }
}
