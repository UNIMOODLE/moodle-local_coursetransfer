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
 * Tests for the privacy provider.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\privacy;

use context_system;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\metadata\types\external_location;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_coursetransfer\coursetransfer_request;

/**
 * Tests for the privacy provider.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass User with a request and a paired site. */
    private \stdClass $user1;

    /** @var \stdClass User with a request only. */
    private \stdClass $user2;

    /** @var \stdClass User without data. */
    private \stdClass $user3;

    /**
     * Two users with data and one without.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer');

        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();

        $this->setUser($this->user1);
        $generator->create_site('origin', ['host' => 'https://origin.example.com']);
        $generator->create_site('target', ['host' => 'https://target.example.com']);
        $generator->create_request([
            'origin_course_id' => 7,
            'origin_course_fullname' => 'Origin course',
            'target_course_id' => 9,
            'status' => coursetransfer_request::STATUS_COMPLETED,
        ]);

        $this->setUser($this->user2);
        $generator->create_request(['type' => coursetransfer_request::TYPE_REMOVE_COURSE, 'origin_course_id' => 8]);
        $this->setUser(null);
    }

    /**
     * Every table with a userid, and the remote platform, are declared.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('local_coursetransfer'))->get_collection();

        $tables = [];
        $external = [];
        foreach ($items as $item) {
            if ($item instanceof database_table) {
                $tables[$item->get_name()] = $item->get_privacy_fields();
            } else if ($item instanceof external_location) {
                $external[$item->get_name()] = $item->get_privacy_fields();
            }
        }
        $this->assertEqualsCanonicalizing(provider::TABLES, array_keys($tables));
        foreach ($tables as $fields) {
            $this->assertArrayHasKey('userid', $fields);
        }
        $this->assertArrayNotHasKey('token', $tables['local_coursetransfer_origin']);
        $this->assertSame(['remotesite'], array_keys($external));
        $this->assertEqualsCanonicalizing(['field', 'value', 'userdata'], array_keys($external['remotesite']));

        // Every declared string exists in the plugin.
        $sm = get_string_manager();
        foreach ($items as $item) {
            $this->assertTrue($sm->string_exists($item->get_summary(), 'local_coursetransfer'), $item->get_summary());
            foreach ($item->get_privacy_fields() as $identifier) {
                $this->assertTrue($sm->string_exists($identifier, 'local_coursetransfer'), $identifier);
            }
        }
    }

    /**
     * Users with data have their user context; users without data have none.
     */
    public function test_get_contexts_for_userid(): void {
        $contexts = provider::get_contexts_for_userid((int) $this->user1->id)->get_contextids();
        $this->assertEquals([context_user::instance($this->user1->id)->id], array_values($contexts));

        $contexts = provider::get_contexts_for_userid((int) $this->user2->id)->get_contextids();
        $this->assertEquals([context_user::instance($this->user2->id)->id], array_values($contexts));

        $this->assertCount(0, provider::get_contexts_for_userid((int) $this->user3->id));
    }

    /**
     * Only the owner of a user context is listed, and nothing in other contexts.
     */
    public function test_get_users_in_context(): void {
        $userlist = new userlist(context_user::instance($this->user1->id), 'local_coursetransfer');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$this->user1->id], $userlist->get_userids());

        $userlist = new userlist(context_user::instance($this->user3->id), 'local_coursetransfer');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);

        $userlist = new userlist(context_system::instance(), 'local_coursetransfer');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);
    }

    /**
     * The export holds the user's requests and paired sites, without the site token.
     */
    public function test_export_user_data(): void {
        $context = context_user::instance($this->user1->id);
        $this->export_context_data_for_user((int) $this->user1->id, $context, 'local_coursetransfer');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', 'local_coursetransfer')]);
        $this->assertCount(1, $data->requests);
        $this->assertSame('Origin course', $data->requests[0]['origin_course_fullname']);
        $this->assertEquals(9, $data->requests[0]['target_course_id']);
        $this->assertEqualsCanonicalizing(['origin', 'target'], array_column($data->sites, 'type'));
        $this->assertArrayNotHasKey('token', $data->sites[0]);

        // Nothing is exported for a user without data.
        writer::reset();
        $context = context_user::instance($this->user3->id);
        $this->export_context_data_for_user((int) $this->user3->id, $context, 'local_coursetransfer');
        $this->assertFalse(writer::with_context($context)->has_any_data());
    }

    /**
     * Deleting a user anonymises their rows and keeps the rows of the others.
     */
    public function test_delete_data_for_user(): void {
        $context = context_user::instance($this->user1->id);
        // A context of another user in the list is ignored.
        provider::delete_data_for_user(new approved_contextlist(
            $this->user1,
            'local_coursetransfer',
            [$context->id, context_user::instance($this->user2->id)->id]
        ));

        $this->assert_user_rows($this->user1, 0, 0, 0);
        $this->assert_user_rows($this->user2, 1, 0, 0);
        $this->assert_user_rows((object) ['id' => 0], 1, 1, 1);
        $this->assertCount(0, provider::get_contexts_for_userid((int) $this->user1->id));
    }

    /**
     * Deleting a list of users in a user context only touches the owner of that context.
     */
    public function test_delete_data_for_users(): void {
        // System context: nothing to do.
        provider::delete_data_for_users(new approved_userlist(
            context_system::instance(),
            'local_coursetransfer',
            [(int) $this->user1->id]
        ));
        $this->assert_user_rows($this->user1, 1, 1, 1);

        // The context of user2 does not hold the data of user1.
        provider::delete_data_for_users(new approved_userlist(
            context_user::instance($this->user2->id),
            'local_coursetransfer',
            [(int) $this->user1->id]
        ));
        $this->assert_user_rows($this->user1, 1, 1, 1);
        $this->assert_user_rows($this->user2, 1, 0, 0);

        provider::delete_data_for_users(new approved_userlist(
            context_user::instance($this->user1->id),
            'local_coursetransfer',
            [(int) $this->user1->id]
        ));
        $this->assert_user_rows($this->user1, 0, 0, 0);
        $this->assert_user_rows($this->user2, 1, 0, 0);
    }

    /**
     * Deleting a whole user context anonymises its owner; the system context is left alone.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context(context_system::instance());
        $this->assert_user_rows($this->user1, 1, 1, 1);
        $this->assert_user_rows($this->user2, 1, 0, 0);

        provider::delete_data_for_all_users_in_context(context_user::instance($this->user2->id));
        $this->assert_user_rows($this->user1, 1, 1, 1);
        $this->assert_user_rows($this->user2, 0, 0, 0);
        $this->assertSame(2, $this->count_rows('local_coursetransfer_request'));
    }

    /**
     * Check how many rows of each table point to a user.
     *
     * @param \stdClass $user
     * @param int $requests
     * @param int $origins
     * @param int $targets
     */
    private function assert_user_rows(\stdClass $user, int $requests, int $origins, int $targets): void {
        global $DB;
        $this->assertSame($requests, $DB->count_records('local_coursetransfer_request', ['userid' => $user->id]));
        $this->assertSame($origins, $DB->count_records('local_coursetransfer_origin', ['userid' => $user->id]));
        $this->assertSame($targets, $DB->count_records('local_coursetransfer_target', ['userid' => $user->id]));
    }

    /**
     * Number of rows in a table: deleting anonymises, it never removes a row.
     *
     * @param string $table
     * @return int
     */
    private function count_rows(string $table): int {
        global $DB;
        return $DB->count_records($table);
    }
}
