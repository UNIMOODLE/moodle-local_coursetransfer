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
 * This file defines an adhoc task to create a backup of the curse.
 *
 * @package    mod_forum
 * @copyright  2023 3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_coursetransfer\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Create Backup Course Task
 */
class create_backup_course_task extends \core\task\adhoc_task {

    public static function instance(
        int $id,
        string $status,
    ) {
        $task = new self();
        $task->set_custom_data((object) [
            'id' => $id,
            'status' => $status,
        ]);

        return $task;
    }

    /**
     * Execute the task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        mtrace($data->id);
        mtrace($data->status);
    }
}
