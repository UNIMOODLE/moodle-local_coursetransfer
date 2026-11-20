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
 * Contract tests for the site (platform) web services.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external;

use advanced_testcase;
use core_external\external_api;
use core_external\external_description;
use local_coursetransfer\coursetransfer_sites;
use local_coursetransfer\external\frontend\sites_external;

/**
 * Contract tests for site_edit, site_remove, site_check, site_test, site_origin_test and
 * site_target_test.
 *
 * Remote calls go through Moodle's curl class with a mocked response: no request leaves the
 * test, and every test checks that the mocked responses were consumed.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\external\frontend\sites_external
 */
final class sites_external_test extends advanced_testcase {
    /** @var \local_coursetransfer_generator */
    private $generator;

    /**
     * Act as admin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');
    }

    /**
     * No mocked response may be left behind (it would leak into another test).
     */
    protected function tearDown(): void {
        $property = new \ReflectionProperty(\curl::class, 'mockresponses');
        $this->assertSame([], $property->getValue(), 'Every mocked remote response must be consumed');
        parent::tearDown();
    }

    /**
     * Return description of a method.
     *
     * Some errors structures pass PARAM_TEXT and a label as description and $required, which
     * raises a developer debugging() message when the description is built (plugin bug, reported
     * in TIPCOUTRAN-10). It is discarded here so the contract itself can be checked.
     *
     * @param string $method
     * @return external_description
     */
    private function returns(string $method): external_description {
        $description = call_user_func([sites_external::class, $method . '_returns']);
        $this->resetDebugging();
        return $description;
    }

    /**
     * Queue the JSON envelope a remote local_coursetransfer service answers with.
     *
     * @param bool $success
     * @param array $errors
     */
    private function mock_remote(bool $success, array $errors = []): void {
        \curl::mock_response(json_encode(['success' => $success, 'errors' => $errors, 'data' => []]));
    }

    /**
     * Editing a site stores the cleaned host, the new token and name.
     */
    public function test_site_edit(): void {
        $site = $this->generator->create_site('origin');

        $res = sites_external::site_edit('origin', (int) $site->id, ' https://new.example.com/ ', 'newtoken', 'New name');
        $res = external_api::clean_returnvalue($this->returns('site_edit'), $res);

        $this->assertTrue($res['success']);
        $this->assertSame((int) $site->id, $res['data']['id']);
        $record = coursetransfer_sites::get('origin', $site->id);
        $this->assertSame('https://new.example.com', $record->host);
        $this->assertSame('newtoken', $record->token);
        $this->assertSame('New name', $record->name);
    }

    /**
     * Editing with an empty token keeps the stored one, unless the host changes.
     */
    public function test_site_edit_keeps_token(): void {
        $site = $this->generator->create_site('origin', ['token' => 'storedtoken']);

        $res = sites_external::site_edit('origin', (int) $site->id, $site->host, '', 'Renamed');
        $this->assertTrue($res['success']);
        $record = coursetransfer_sites::get('origin', $site->id);
        $this->assertSame('storedtoken', $record->token);
        $this->assertSame('Renamed', $record->name);

        // A new host never inherits the token: it would send it somewhere else.
        $res = sites_external::site_edit('origin', (int) $site->id, 'https://other.example.com', '');
        $this->assertSame('18043', $res['errors'][0]['code']);
        $this->assertSame($site->host, coursetransfer_sites::get('origin', $site->id)->host);
    }

    /**
     * The platforms page never sends the stored tokens to the browser.
     */
    public function test_platforms_page_without_token(): void {
        global $PAGE;
        $this->generator->create_site('origin', ['token' => 'secret-origin-token', 'host' => 'https://p.example.com']);
        $this->generator->create_site('target', ['token' => 'secret-target-token', 'host' => 'https://q.example.com']);

        $page = new \local_coursetransfer\output\platforms_page(coursetransfer_sites::get_platforms());
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $json = json_encode($data);
        $this->assertStringNotContainsString('secret-origin-token', $json);
        $this->assertStringNotContainsString('secret-target-token', $json);
        $this->assertTrue($data->platforms[0]->hastoken);
    }

    /**
     * Invalid type, empty values, invalid URL, unknown site and duplicated host are rejected.
     */
    public function test_site_edit_rejected(): void {
        $site = $this->generator->create_site('target');
        $other = $this->generator->create_site('target');
        $cases = [
            '18032' => ['destiny', $site->id, 'https://a.example.com', 't'],
            '18043' => ['target', $site->id, '', 't'],
            '18045' => ['target', $site->id, 'ftp://a.example.com', 't'],
            '18033' => ['target', 999999, 'https://a.example.com', 't'],
        ];
        foreach ($cases as $code => [$type, $id, $host, $token]) {
            $res = sites_external::site_edit($type, (int) $id, $host, $token);
            $res = external_api::clean_returnvalue($this->returns('site_edit'), $res);
            $this->assertFalse($res['success'], "Case $code");
            $this->assertSame((string) $code, $res['errors'][0]['code'], "Case $code");
        }

        $res = sites_external::site_edit('target', (int) $site->id, $other->host, 't');
        $this->assertSame('18032', $res['errors'][0]['code']);
        $this->assertSame($site->host, coursetransfer_sites::get('target', $site->id)->host);
    }

    /**
     * Removing an unused site deletes it; a site with requests or an unknown one is kept.
     */
    public function test_site_remove(): void {
        global $DB;
        $unused = $this->generator->create_site('origin');
        $inuse = $this->generator->create_site('origin', ['host' => 'https://busy.example.com']);
        $this->generator->create_request(['siteurl' => 'https://busy.example.com']);

        $res = sites_external::site_remove('origin', (int) $unused->id);
        $res = external_api::clean_returnvalue($this->returns('site_remove'), $res);
        $this->assertTrue($res['success']);
        $this->assertFalse($DB->record_exists('local_coursetransfer_origin', ['id' => $unused->id]));

        $res = sites_external::site_remove('origin', (int) $inuse->id);
        $res = external_api::clean_returnvalue($this->returns('site_remove'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('18024', $res['errors'][0]['code']);
        $this->assertTrue($DB->record_exists('local_coursetransfer_origin', ['id' => $inuse->id]));

        $this->assertSame('18023', sites_external::site_remove('origin', 999999)['errors'][0]['code']);
        $this->assertSame('18022', sites_external::site_remove('destiny', (int) $inuse->id)['errors'][0]['code']);
    }

    /**
     * site_check tests unsaved credentials against the remote and relays its errors.
     */
    public function test_site_check(): void {
        $this->mock_remote(true);
        $res = sites_external::site_check('origin', 'https://check.example.com/', 'token');
        $res = external_api::clean_returnvalue($this->returns('site_check'), $res);
        $this->assertTrue($res['success']);
        $this->assertSame([], $res['errors']);

        $this->mock_remote(false, [['code' => '17002', 'msg' => 'User not found']]);
        $res = sites_external::site_check('target', 'https://check.example.com', 'token');
        $res = external_api::clean_returnvalue($this->returns('site_check'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('17002', $res['errors'][0]['code']);

        $this->assertSame('18011', sites_external::site_check('destiny', 'https://check.example.com', 't')['errors'][0]['code']);
        $this->assertSame('18045', sites_external::site_check('origin', 'not a url', 't')['errors'][0]['code']);
    }

    /**
     * A transport failure of the remote is reported, not thrown.
     */
    public function test_site_check_unreachable(): void {
        \curl::mock_response('<html>Bad gateway</html>');
        $res = sites_external::site_check('origin', 'https://check.example.com', 'token');
        $res = external_api::clean_returnvalue($this->returns('site_check'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('12003', $res['errors'][0]['code']);
    }

    /**
     * site_test tests a stored site and persists the outcome on its row.
     */
    public function test_site_test(): void {
        $site = $this->generator->create_site('origin');

        $this->mock_remote(true);
        $res = external_api::clean_returnvalue($this->returns('site_test'), sites_external::site_test('origin', (int) $site->id));
        $this->assertTrue($res['success']);
        $this->assertSame('', $res['error']['code']);
        $record = coursetransfer_sites::get('origin', $site->id);
        $this->assertEquals(1, $record->lastteststatus);
        $this->assertNull($record->lasttesterror);

        $this->mock_remote(false, [['code' => '17001', 'msg' => 'No courses']]);
        $res = external_api::clean_returnvalue($this->returns('site_test'), sites_external::site_test('origin', (int) $site->id));
        $this->assertFalse($res['success']);
        $this->assertSame('17001', $res['error']['code']);
        $record = coursetransfer_sites::get('origin', $site->id);
        $this->assertEquals(0, $record->lastteststatus);
        $this->assertSame('17001: No courses', $record->lasttesterror);
    }

    /**
     * A peer that still accepts a user without the webservice capability: the test passes with a warning (2.2).
     */
    public function test_site_test_deprecated_user(): void {
        $site = $this->generator->create_site('origin');
        \curl::mock_response(json_encode(['success' => true, 'errors' => [], 'data' => [],
            'deprecations' => [['code' => sites_external::DEPRECATED_WSUSER, 'msg' => 'x']]]));

        $res = sites_external::site_test('origin', (int) $site->id);

        $this->assertTrue($res['success']);
        $record = coursetransfer_sites::get('origin', $site->id);
        $this->assertEquals(1, $record->lastteststatus);
        $this->assertSame(get_string('platform_warn_legacyws', 'local_coursetransfer'), $record->lasttesterror);
        $platform = coursetransfer_sites::get_platforms()[0];
        $this->assertEquals(1, $platform->lastteststatus);
        $this->assertStringContainsString('2.3', $platform->lasttesterror);
    }

    /**
     * site_test on an unknown site or type returns an error and does not call the remote.
     */
    public function test_site_test_rejected(): void {
        $res = external_api::clean_returnvalue($this->returns('site_test'), sites_external::site_test('origin', 999999));
        $this->assertFalse($res['success']);
        $this->assertSame('18010', $res['error']['code']);

        $res = external_api::clean_returnvalue($this->returns('site_test'), sites_external::site_test('destiny', 1));
        $this->assertSame('18011', $res['error']['code']);
    }

    /**
     * site_origin_test (run on the origin) authenticates the user and tests the way back to the target.
     */
    public function test_site_origin_test(): void {
        $this->getDataGenerator()->create_course();
        $this->generator->create_site('target', ['host' => 'https://target.example.com']);

        $this->mock_remote(true);
        $res = sites_external::origin_test('username', 'admin', 'https://target.example.com/');
        $res = external_api::clean_returnvalue($this->returns('origin_test'), $res);
        $this->assertTrue($res['success']);

        $this->mock_remote(false, [['code' => '17002', 'msg' => 'User not found']]);
        $res = sites_external::origin_test('username', 'admin', 'https://target.example.com');
        $res = external_api::clean_returnvalue($this->returns('origin_test'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('17002', $res['errors'][0]['code']);
    }

    /**
     * site_origin_test rejects unknown users and target sites that are not registered, without remote calls.
     */
    public function test_site_origin_test_rejected(): void {
        $this->getDataGenerator()->create_course();

        $res = sites_external::origin_test('username', 'nobody', 'https://target.example.com');
        $res = external_api::clean_returnvalue($this->returns('origin_test'), $res);
        $this->assertSame('17002', $res['errors'][0]['code']);

        $res = sites_external::origin_test('username', 'admin', 'https://target.example.com');
        $res = external_api::clean_returnvalue($this->returns('origin_test'), $res);
        $this->assertSame('18001', $res['errors'][0]['code']);
    }

    /**
     * site_target_test (run on the target) only authenticates the user.
     */
    public function test_site_target_test(): void {
        $this->getDataGenerator()->create_course();
        $nocourses = $this->getDataGenerator()->create_user();

        $res = external_api::clean_returnvalue($this->returns('target_test'), sites_external::target_test('username', 'admin'));
        $this->assertTrue($res['success']);

        $res = sites_external::target_test('username', $nocourses->username);
        $res = external_api::clean_returnvalue($this->returns('target_test'), $res);
        $this->assertFalse($res['success']);
        $this->assertSame('17001', $res['errors'][0]['code']);

        $res = external_api::clean_returnvalue($this->returns('target_test'), sites_external::target_test('email', ''));
        $this->assertSame('17003', $res['errors'][0]['code']);
    }

    /**
     * Only users with moodle/site:config manage, check or test sites (LCT-009).
     */
    public function test_sites_require_capability(): void {
        $site = $this->generator->create_site('origin');
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id);
        $student = $this->getDataGenerator()->create_user();
        $calls = [
            'site_add' => fn() => sites_external::site_add('origin', 'https://evil.example.com', 't'),
            'site_edit' => fn() => sites_external::site_edit('origin', (int) $site->id, 'https://evil.example.com', 't'),
            'site_remove' => fn() => sites_external::site_remove('origin', (int) $site->id),
            'site_check' => fn() => sites_external::site_check('origin', 'https://evil.example.com', 't'),
            'site_test' => fn() => sites_external::site_test('origin', (int) $site->id),
        ];
        foreach ([$manager, $student] as $user) {
            $this->setUser($user);
            foreach ($calls as $method => $call) {
                try {
                    $call();
                    $this->fail("$method must require moodle/site:config");
                } catch (\required_capability_exception $e) {
                    $this->assertStringContainsString(get_capability_string('moodle/site:config'), $e->getMessage(), $method);
                }
            }
        }
        $this->setAdminUser();
        $this->assertSame($site->host, coursetransfer_sites::get('origin', $site->id)->host);
    }

    /**
     * Hosts are a plain http(s) base URL: no query, fragment, credentials or spaces (LCT-009).
     */
    public function test_site_host_validation(): void {
        $rejected = [
            'https://a.example.com/?x=1',
            'https://a.example.com/#x',
            'https://user:pass@a.example.com',
            'https://user@a.example.com',
            'https://a.example.com/moodle path',
            "https://a.example.com/\nmoodle",
            'https://a.example.com/moodle\\x',
            'https://',
            'javascript://a.example.com',
        ];
        foreach ($rejected as $host) {
            $res = sites_external::site_add('origin', $host, 't');
            $this->assertSame('18045', $res['errors'][0]['code'] ?? '', $host);
            $this->assertSame('18045', sites_external::site_check('origin', $host, 't')['errors'][0]['code'], $host);
        }

        $accepted = ['https://a.example.com', 'http://moodle45.tip.internal:8080/moodle/', 'https://10.0.0.1/m-1_x~y'];
        foreach ($accepted as $host) {
            $res = sites_external::site_add('origin', $host, 't');
            $this->assertTrue($res['success'], $host);
        }
    }

    /**
     * site_check and site_test do not relay what an arbitrary host answers, only a diagnosis.
     */
    public function test_site_check_does_not_relay_remote_body(): void {
        $site = $this->generator->create_site('target');
        $bodies = [
            '<html>internal-secret-body</html>',
            json_encode(['exception' => 'x', 'message' => 'internal-secret-body', 'debuginfo' => 'internal-secret-body']),
        ];
        foreach ($bodies as $body) {
            \curl::mock_response($body);
            $res = sites_external::site_check('origin', 'https://check.example.com', 'token');
            $this->resetDebugging(); // The request logs the raw answer for developers.
            $this->assertFalse($res['success']);
            $this->assertStringNotContainsString('internal-secret-body', json_encode($res));

            \curl::mock_response($body);
            $res = sites_external::site_test('target', (int) $site->id);
            $this->resetDebugging();
            $this->assertFalse($res['success']);
            $this->assertStringNotContainsString('internal-secret-body', json_encode($res));
            $lasttesterror = (string) coursetransfer_sites::get('target', $site->id)->lasttesterror;
            $this->assertStringNotContainsString('internal-secret-body', $lasttesterror);
        }
    }
}
