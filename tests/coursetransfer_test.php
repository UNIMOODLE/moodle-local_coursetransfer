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
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use coding_exception;
use core_user;
use dml_exception;
use invalid_parameter_exception;
use local_coursetransfer\external\frontend\sites_external;
use local_coursetransfer\factory\user;
use local_coursetransfer\models\configuration_course;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * coursetransfer_test
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer_test extends advanced_testcase {

    const URL = 'http://cncs.test';

    /** @var stdClass Origin Course */
    protected $origincourse;

    /** @var stdClass Destiny New Course */
    protected $destinynewcourse;

    /** @var stdClass User */
    protected $user;

    /**
     * Tests Set UP.
     *
     */
    public function setUp():void {

        // Config.
        $this->setup_config();

        // Create Origin Course.
        $oc = [
                'fullname' => 'Origen Course',
                'shortname' => 'phpunit_origincourse'
        ];
        $this->origincourse = $this->getDataGenerator()->create_course($oc);

        // Create Destiny New Course.
        $dnc = [
                'fullname' => 'Remote Restoring in process...',
                'shortname' => 'IN-PROGRESS-' . time()
        ];
        $this->destinynewcourse = $this->getDataGenerator()->create_course($dnc);

        $this->resetAfterTest(true);
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
        $token = coursetransfer::postinstall();
        $this->user = core_user::get_user_by_username(user::USERNAME_WS);
        sites_external::site_add('origin', self::URL, $token);
    }

    /**
     * Tests restore_course.
     *
     * @covers coursetransfer::restore_course
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_restore_course() {
        $destinytarget = 2;
        $destinyremoveenrols = false;
        $destinyremovegroups = false;
        $originenrolusers = false;
        $originremovecourse = false;
        $destinynotremoveactivities = '';

        $siteurl = self::URL;
        $site = coursetransfer::get_site_by_url($siteurl);
        $destinycourseid = $this->destinynewcourse->id;
        $origincourseid = $this->origincourse->id;
        $configuration = new configuration_course(
                $destinytarget, $destinyremoveenrols, $destinyremovegroups, $originenrolusers,
                $originremovecourse, $destinynotremoveactivities);
        $res = coursetransfer::restore_course($this->user, $site, $destinycourseid, $origincourseid, $configuration);

        var_dump($res);

        $this->assertEquals('hola', 'adios');
        $this->assertEquals('hola', 'hola');
    }

}
