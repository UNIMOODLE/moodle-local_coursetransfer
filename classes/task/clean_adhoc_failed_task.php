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
 * clean_adhoc_failed_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;


use coding_exception;
use dml_exception;
use local_coursetransfer\coursetransfer_request;
use moodle_exception;
use stdClass;

/**
 * clean_adhoc_failed_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clean_adhoc_failed_task extends \core\task\scheduled_task {

    /** @var int Max FAIL Delay time in seconds */
    const MAX_FAILDELAY = 60;

    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     * @throws coding_exception
     */
    public function get_name(): string {
        return get_string('clean_adhoc_failed_task', 'local_coursetransfer');
    }

    /**
     * Execute.
     *
     * @throws dml_exception
     */
    public function execute(): void {
        global $DB;
        $this->log_start("Clean Adhoc Failed Task - Starting...");

        // Configurable threshold in seconds; 0 (or negative) disables the cleanup.
        $faildelay = get_config('local_coursetransfer', 'clean_adhoc_faildelay');
        $faildelay = ($faildelay === false || $faildelay === '') ? self::MAX_FAILDELAY : (int)$faildelay;
        if ($faildelay <= 0) {
            $this->log("Cleanup disabled (clean_adhoc_faildelay <= 0)");
            $this->log_finish("Clean Adhoc Failed Task - Finishing...");
            return;
        }

        $tasksdb = $DB->get_records_select('task_adhoc',
                'component = ? AND faildelay > ?',
                ['local_coursetransfer', $faildelay]);
        if (count($tasksdb) > 0) {
            foreach ($tasksdb as $taskdb) {
                try {
                    // Mark the linked request as errored so it is not left orphaned (LCT-015).
                    $this->fail_request($taskdb);
                    $DB->delete_records('task_adhoc', ['id' => $taskdb->id]);
                    $this->log("Adhoc tasks remove" . json_encode($taskdb, JSON_PRETTY_PRINT));
                } catch (moodle_exception $e) {
                    $this->log("Adhoc tasks remove - ERROR" . json_encode($taskdb, JSON_PRETTY_PRINT) .
                            ' - Msg: ' . $e->getMessage());
                }
            }
        } else {
            $this->log("Adhoc tasks with faildelay not found");
        }
        $this->log_finish("Clean Adhoc Failed Task - Finishing...");
    }

    /**
     * Mark the request linked to a failed adhoc task as errored, so it is not left
     * stuck in an intermediate state ("caducada") when the task is removed.
     *
     * @param stdClass $taskdb task_adhoc record.
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function fail_request(stdClass $taskdb): void {
        global $DB;
        if (empty($taskdb->customdata)) {
            return;
        }
        $data = json_decode($taskdb->customdata);
        $requestid = isset($data->requestid) ? (int)$data->requestid : 0;
        if ($requestid <= 0) {
            return;
        }
        $request = $DB->get_record('local_coursetransfer_request', ['id' => $requestid]);
        if (!$request || (int)$request->status === coursetransfer_request::STATUS_COMPLETED) {
            return;
        }
        $request->status = coursetransfer_request::STATUS_ERROR;
        $request->error_message = 'Adhoc task removed after repeated failures (clean_adhoc_failed_task); '
                . 'check cron execution and CLI memory/time limits.';
        coursetransfer_request::insert_or_update($request, $request->id);
        $this->log("Request {$requestid} marked as ERROR (orphaned adhoc task removed)");
    }
}
