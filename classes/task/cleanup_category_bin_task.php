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
 * cleanup category bin task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use core\task\manager;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_remove;
use Throwable;


/**
 * cleanup category bin task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_category_bin_task extends \core\task\adhoc_task {
    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Execute.
     *
     */
    public function execute(): void {
        $this->log_start("Cleanup category bin - Starting...");
        try {
            $data = $this->get_custom_data();
            $retention = coursetransfer_remove::get_recyclebin_retention();
            if (empty($data->timeremoved) || time() < (int)$data->timeremoved + $retention) {
                // Tasks queued by 2.1 carry no removal time: they start the retention now.
                $data->timeremoved = (int)($data->timeremoved ?? time());
                $task = new self();
                $task->set_custom_data($data);
                $task->set_next_run_time($data->timeremoved + $retention);
                manager::queue_adhoc_task($task);
                $this->log('Recycle bin retention not reached: purge queued again.');
                return;
            }
            coursetransfer::cleanup_category_bin((int)$data->categoryid);
        } catch (Throwable $e) {
            mtrace('Cleanup category bin - ERROR: ' . $e->getMessage());
            $this->log($e->getMessage());
        } finally {
            $this->log_finish("Cleanup category bin - Finishing...");
        }
    }
}
