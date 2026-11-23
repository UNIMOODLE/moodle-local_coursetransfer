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
 * remove_category_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer_remove;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_sites;
use local_coursetransfer\event\remote_removal_executed;
use local_coursetransfer\event\remote_removal_rejected;
use stdClass;
use Throwable;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/course/externallib.php');

/**
 * remove_category_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_category_task extends \core\task\adhoc_task {
    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /**
     * Execute.
     *
     */
    public function execute(): void {
        global $DB;
        $this->log_start("Remove Category Remote Starting...");

        $data = $this->get_custom_data();
        $requestoriginid = (int)($data->requestoriginid ?? 0);
        $requestdestid = (int)($data->requestdestid ?? 0);
        $catid = (int)($data->catid ?? 0);

        $requestorigin = coursetransfer_request::get($requestoriginid);
        if (!$requestorigin) {
            // Without its request nothing would record what was removed (LCT-036).
            $this->log_finish('Request ' . $requestoriginid . ' not found: nothing removed.');
            return;
        }
        if ((int)$requestorigin->status === coursetransfer_request::STATUS_COMPLETED) {
            $this->log_finish('Request ' . $requestoriginid . ' already completed.');
            return;
        }

        $user = \core_user::get_user((int)($data->userid ?? 0));
        $request = null;
        try {
            $site = coursetransfer_sites::get('target', (int)($data->targetsiteid ?? 0));
            $request = $site ? new request($site) : null;

            $category = \core_course_category::get($catid, MUST_EXIST, true);
            // The removal runs as the cron: check it on the user who asked for it.
            if (
                !$user || !has_capability('moodle/category:manage', $category->get_context(), $user)
                    || !has_capability('local/coursetransfer:origin_remove_category', \context_system::instance(), $user)
            ) {
                $this->fail($requestorigin, $request, $user, $requestdestid, '19002', 'USER HAS NOT CAPABILITY');
                return;
            }

            // The served backups of its courses would go with their contexts: they must outlive the removal.
            $courseids = $DB->get_fieldset_sql(
                'SELECT c.id FROM {course} c JOIN {course_categories} cc ON cc.id = c.category
                  WHERE cc.id = :id OR ' . $DB->sql_like('cc.path', ':path'),
                ['id' => $catid, 'path' => $category->path . '/%']
            );
            coursetransfer_remove::preserve_served_backups($courseids);
            \core_course_external::delete_categories([['id' => $catid, 'recursive' => 1]]);

            coursetransfer_request::update_fields($requestoriginid, ['status' => coursetransfer_request::STATUS_COMPLETED]);
            $this->audit(remote_removal_executed::class, $requestorigin, 'removed');

            coursetransfer_remove::create_cleanup_category_bin_task($catid);

            if ($request) {
                // The category is removed whatever the target answers: a failed notice is a note on a completed
                // request, never an error that would say the removal did not happen (C3).
                try {
                    $res = $request->target_remove_course_completed($requestdestid, $user);
                    $why = $res->success ? '' : ($res->errors[0]->code ?? '') . ' ' . ($res->errors[0]->msg ?? '');
                } catch (Throwable $e) {
                    $res = null;
                    $why = $e->getMessage();
                }
                if ($why !== '') {
                    coursetransfer_request::update_fields($requestoriginid, [
                        'error_code' => '15010',
                        'error_message' => 'Removed; the target was not told: ' . trim($why),
                    ]);
                    mtrace('Remove Category Remote: removed, but the completed callback failed: ' . trim($why));
                    $this->log(json_encode($res));
                }
            }
        } catch (Throwable $e) {
            // Leave the request in a final state and tell the target (LCT-015).
            $this->fail($requestorigin, $request, $user, $requestdestid, '19001', $e->getMessage());
        } finally {
            $this->log_finish("Remove Category Remote Finishing...");
        }
    }

    /**
     * Mark the request as failed and tell the target.
     *
     * @param stdClass $requestorigin
     * @param request|null $request
     * @param stdClass|false $user
     * @param int $requestdestid
     * @param string $code
     * @param string $message
     */
    protected function fail(
        stdClass $requestorigin,
        ?request $request,
        $user,
        int $requestdestid,
        string $code,
        string $message
    ): void {
        mtrace('Remove Category Remote ERROR: ' . $message);
        $this->audit(remote_removal_rejected::class, $requestorigin, $code . ' ' . $message);
        try {
            coursetransfer_request::update_fields((int)$requestorigin->id, [
                'status' => coursetransfer_request::STATUS_ERROR,
                'error_code' => $code,
                'error_message' => $message,
            ]);
            if ($request && $user) {
                $res = $request->target_remove_course_error($user, $requestdestid, $message, $code);
                if (!$res->success) {
                    mtrace('Remove Course Remote in Error Callback ERROR: ' . $res->errors[0]->msg);
                    $this->log(json_encode($res));
                }
            }
        } catch (Throwable $e) {
            $this->log('Remove Category Remote ERROR: ' . $e->getMessage());
        }
    }

    /**
     * Audit what was done with a removal asked by another platform.
     *
     * @param string $event Event class.
     * @param stdClass $requestorigin
     * @param string $result
     */
    protected function audit(string $event, stdClass $requestorigin, string $result): void {
        try {
            $event::fire((int)$requestorigin->id, (int)($this->get_custom_data()->userid ?? 0), [
                'kind' => 'category',
                'id' => (int)($this->get_custom_data()->catid ?? 0),
                'site' => (string)$requestorigin->siteurl,
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            $this->log('Audit event not triggered: ' . $e->getMessage());
        }
    }
}
