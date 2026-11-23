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
 * Subsections in the backups the origin makes for a peer.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use advanced_testcase;
use backup_controller;
use core\task\manager;
use local_coursetransfer\task\create_backup_course_task;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

/**
 * The content of a subsection follows the subsection activity, as in the native backup (#58).
 *
 * @package    local_coursetransfer
 * @copyright  2026 3ipunt <https://www.tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_coursetransfer
 * @covers     \local_coursetransfer\coursetransfer_backup
 */
final class coursetransfer_backup_subsection_test extends advanced_testcase {
    /** @var stdClass Course with an assignment and a subsection holding a page in section 1. */
    private stdClass $course;

    /** @var int Subsection activity cmid. */
    private int $subcmid;

    /** @var int Page (inside the subsection) cmid. */
    private int $pagecmid;

    /** @var int Delegated section id. */
    private int $delegatedid;

    /**
     * Build the course.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        // As on a site that uses subsections (the module may be disabled by default).
        \core\plugininfo\mod::enable_plugin('subsection', 1);
        $this->course = $gen->create_course(['numsections' => 1]);
        $gen->create_module('assign', ['course' => $this->course->id, 'section' => 1]);
        $sub = $gen->create_module('subsection', ['course' => $this->course->id, 'section' => 1]);
        $this->subcmid = (int) $sub->cmid;
        $delegated = get_fast_modinfo($this->course)->get_section_info_by_component('mod_subsection', $sub->id);
        $this->delegatedid = (int) $delegated->id;
        $page = $gen->create_module('page', ['course' => $this->course->id, 'section' => $delegated->section]);
        $this->pagecmid = (int) $page->cmid;
    }

    /**
     * The selection the wizard sends: every section and activity, with these overrides.
     *
     * @param array $selected cmid or 's' . sectionid => 0|1; the rest are selected.
     * @param int[] $unlisted cmids left out of the selection.
     * @return array
     */
    private function selection(array $selected, array $unlisted = []): array {
        $modinfo = get_fast_modinfo($this->course);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $activities = [];
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                if (!in_array($cmid, $unlisted)) {
                    $activities[] = ['cmid' => $cmid, 'selected' => $selected[$cmid] ?? 1];
                }
            }
            $sections[] = ['sectionid' => $section->id, 'selected' => $selected['s' . $section->id] ?? 1,
                'activities' => $activities];
        }
        return $sections;
    }

    /**
     * Included and userinfo values of the queued backup for the delegated section and the page.
     *
     * @param array $sections
     * @return array
     */
    private function plan_values(array $sections): array {
        $site = $this->getDataGenerator()->get_plugin_generator('local_coursetransfer')->create_site('target');
        $this->assertTrue(coursetransfer_backup::create_task_backup_course(
            (int) $this->course->id,
            (int) get_admin()->id,
            $site,
            1,
            1,
            $sections,
            1
        ));
        $tasks = manager::get_adhoc_tasks(create_backup_course_task::class);
        $bc = backup_controller::load_controller(end($tasks)->get_custom_data()->backupid);
        $plan = $bc->get_plan();
        $values = [
            'section' => (int) $plan->get_setting('section_' . $this->delegatedid . '_included')->get_value(),
            'page' => (int) $plan->get_setting('page_' . $this->pagecmid . '_included')->get_value(),
            'pageuserinfo' => (int) $plan->get_setting('page_' . $this->pagecmid . '_userinfo')->get_value(),
        ];
        $bc->destroy();
        return $values;
    }

    /**
     * A selected subsection carries its content even if its own section is unticked or the page is not listed.
     */
    public function test_selected_subsection_carries_content(): void {
        $values = $this->plan_values($this->selection(['s' . $this->delegatedid => 0], [$this->pagecmid]));
        $this->assertSame(['section' => 1, 'page' => 1, 'pageuserinfo' => 1], $values);
    }

    /**
     * A subsection left out leaves out its content, which would otherwise be restored outside it.
     */
    public function test_unselected_subsection_leaves_content_out(): void {
        $values = $this->plan_values($this->selection([$this->subcmid => 0]));
        $this->assertSame(0, $values['section']);
        $this->assertSame(0, $values['page']);
    }

    /**
     * An activity of a selected subsection unticked on purpose stays out.
     */
    public function test_unticked_activity_inside_subsection(): void {
        $values = $this->plan_values($this->selection([$this->pagecmid => 0]));
        $this->assertSame(1, $values['section']);
        $this->assertSame(0, $values['page']);
    }
}
