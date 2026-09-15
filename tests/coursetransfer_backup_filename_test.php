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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * coursetransfer_backup_filename_test
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use backup;
use backup_controller;
use coding_exception;
use core\task\manager;
use core_user;
use dml_exception;
use invalid_parameter_exception;
use local_coursetransfer\external\frontend\sites_external;
use local_coursetransfer\factory\user;
use local_coursetransfer\models\configuration_course;
use local_coursetransfer\task\create_backup_course_task;
use moodle_exception;
use phpunit_util;
use stdClass;
use testing_data_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * coursetransfer_backup_filename_test
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @group      local_coursetransfer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coursetransfer_backup_filename_test extends advanced_testcase {
    /** @var stdClass Origin Course */
    protected $origincourse;

    /** @var stdClass Target Course */
    protected $targetcourse;

    /** @var stdClass User */
    protected $user;

    /** @var stdClass Site Origin */
    protected $siteorigin;

    /** @var stdClass Site Target */
    protected $sitetarget;

    /** @var testing_data_generator Generator */
    protected $generator;

    /**
     * Tests Set UP.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->generator = phpunit_util::get_data_generator();

        $this->setup_config();

        $this->setAdminUser();

        $this->origincourse = $this->getDataGenerator()->create_course([
                'fullname' => 'Origin Course',
                'shortname' => 'phpunit-backup-filename-origin',
                'summary' => 'This a Summary',
                'numsections' => 0,
        ]);

        $this->targetcourse = $this->getDataGenerator()->create_course([
                'fullname' => 'Target Course',
                'shortname' => 'phpunit-backup-filename-target',
                'summary' => 'This a other Summary',
                'numsections' => 0,
        ]);
    }

    /**
     * Setup Config.
     *
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function setup_config() {
        global $CFG;
        $token = coursetransfer::postinstall();
        $this->user = core_user::get_user_by_username(user::USERNAME_WS);
        $ressiteorigin = sites_external::site_add('origin', $CFG->wwwroot, $token);
        $ressitetarget = sites_external::site_add('target', $CFG->wwwroot, $token);
        if ($ressiteorigin['success']) {
            $this->siteorigin = coursetransfer_sites::get('origin', $ressiteorigin['data']->id);
        }
        if ($ressitetarget['success']) {
            $this->sitetarget = coursetransfer_sites::get('target', $ressitetarget['data']->id);
        }
    }

    /**
     * Creates a restore course request pair (target + origin) and queues its backup task.
     *
     * @return stdClass The queued adhoc task for this request.
     * @throws moodle_exception
     */
    protected function queue_backup_request(): stdClass {
        $configuration = new configuration_course(
            backup::TARGET_NEW_COURSE,
            false,
            false,
            false,
            false,
            0
        );

        $requesttarget = coursetransfer_request::set_request_restore_course(
            $this->user,
            $this->siteorigin,
            $this->targetcourse->id,
            $this->origincourse->id,
            $configuration,
            [],
            null
        );

        $requestorigin = coursetransfer_request::set_request_restore_course_response(
            $this->user,
            $requesttarget->id,
            $this->sitetarget,
            $this->targetcourse->id,
            $this->origincourse,
            $configuration,
            []
        );

        $result = coursetransfer_backup::create_task_backup_course(
            $this->origincourse->id,
            $this->user->id,
            $this->sitetarget,
            $requesttarget->id,
            $requestorigin->id,
            [],
            $configuration->originenrolusers,
            null,
            true
        );

        $this->assertTrue($result);

        $tasks = manager::get_adhoc_tasks(create_backup_course_task::class);
        $task = end($tasks);
        $this->assertNotFalse($task);

        return (object)[
                'requestoriginid' => $requestorigin->id,
                'backupid' => $task->get_custom_data()->backupid,
        ];
    }

    /**
     * Tests the backup controller's filename setting is unique per request.
     *
     * A shared filename (core's 'backup.mbz' default) would make concurrent backups by the
     * same user overwrite each other in the user's private backup area, see
     * coursetransfer_backup::create_task_backup_course().
     *
     * @covers \local_coursetransfer\coursetransfer_backup::create_task_backup_course
     * @throws moodle_exception
     */
    public function test_backup_filename_is_unique_per_request(): void {
        $request1 = $this->queue_backup_request();
        $request2 = $this->queue_backup_request();

        $bc1 = backup_controller::load_controller($request1->backupid);
        $filename1 = $bc1->get_plan()->get_setting('filename')->get_value();
        $bc1->destroy();

        $bc2 = backup_controller::load_controller($request2->backupid);
        $filename2 = $bc2->get_plan()->get_setting('filename')->get_value();
        $bc2->destroy();

        $this->assertEquals(
            'local_coursetransfer_' . $request1->requestoriginid . '_' . $this->origincourse->id . '.mbz',
            $filename1
        );
        $this->assertEquals(
            'local_coursetransfer_' . $request2->requestoriginid . '_' . $this->origincourse->id . '.mbz',
            $filename2
        );
        $this->assertNotEquals($filename1, $filename2);
    }
}
