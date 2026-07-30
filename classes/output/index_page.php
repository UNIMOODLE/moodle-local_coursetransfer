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
 * index_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use dml_exception;
use local_coursetransfer\coursetransfer_sites;
use local_coursetransfer\factory\user;
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * index_page
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class index_page implements renderable, templatable {

    /**
     * constructor.
     *
     */
    public function __construct() {
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB, $CFG;

        $data = new stdClass();
        $data->logourl = $output->image_url('logo', 'local_coursetransfer')->out(false);
        $data->username_ws = user::USERNAME_WS;

        // Token of this site's service user (+ creation date).
        $token = '';
        $tokencreated = '';
        $userok = false;
        $user = null;
        try {
            $user = \core_user::get_user_by_username(user::USERNAME_WS);
            $userok = (bool)$user;
            if ($user) {
                $row = $DB->get_record_sql(
                        "SELECT et.token, et.timecreated
                           FROM {external_tokens} et
                           JOIN {external_services} es ON et.externalserviceid = es.id
                          WHERE es.component = :cmp AND et.userid = :uid",
                        ['cmp' => 'local_coursetransfer', 'uid' => $user->id]);
                if ($row) {
                    $token = $row->token;
                    $tokencreated = userdate((int)$row->timecreated,
                            get_string('strftimedate', 'langconfig'));
                }
            }
        } catch (moodle_exception $e) {
            $token = '';
        }
        $data->token = $token;
        $data->hastoken = $token !== '';
        $data->notoken = $token === '';
        $data->tokencreated = $tokencreated;

        // URLs.
        $data->url_config = (new moodle_url('/admin/settings.php',
                ['section' => 'local_coursetransfer']))->out(false);
        $data->url_postinstall = (new moodle_url('/local/coursetransfer/postinstall.php'))->out(false);
        $data->url_platforms = (new moodle_url('/local/coursetransfer/sites.php'))->out(false);
        $data->url_logs = (new moodle_url('/local/coursetransfer/logs.php'))->out(false);

        // Integration status checks.
        $wsok = !empty($CFG->enablewebservices)
                && str_contains((string)$CFG->webserviceprotocols, 'rest');
        try {
            $platforms = coursetransfer_sites::get_platforms();
        } catch (\Throwable $e) {
            $platforms = [];
        }
        $nplatforms = is_array($platforms) ? count($platforms) : 0;

        // External service enabled + service user authorized on it.
        $serviceok = false;
        try {
            $service = $DB->get_record('external_services', ['component' => 'local_coursetransfer']);
            if ($service && !empty($service->enabled)) {
                if (empty($service->restrictedusers)) {
                    $serviceok = true;
                } else if ($user) {
                    $serviceok = $DB->record_exists('external_services_users',
                            ['externalserviceid' => $service->id, 'userid' => $user->id]);
                }
            }
        } catch (\Throwable $e) {
            $serviceok = false;
        }

        // Service user is active (confirmed, not suspended, not deleted).
        $activeok = $user && empty($user->suspended) && !empty($user->confirmed) && empty($user->deleted);

        // Service user keeps the capabilities the role grants (a broken/edited
        // role is a common "connected but nothing works" cause). A representative
        // subset is enough to detect it.
        $capsok = false;
        if ($user) {
            $sysctx = \context_system::instance();
            $capsok = has_capability('webservice/rest:use', $sysctx, $user)
                    && has_capability('moodle/backup:backupcourse', $sysctx, $user)
                    && has_capability('moodle/restore:restorecourse', $sysctx, $user);
        }

        // Cron recency: deferred/async restores and deletions run through cron.
        $lastcron = 0;
        try {
            $lastcron = (int)$DB->get_field_sql(
                    'SELECT MAX(lastruntime) FROM {task_scheduled} WHERE disabled = 0');
        } catch (\Throwable $e) {
            $lastcron = 0;
        }
        $cronok = $lastcron > 0 && (time() - $lastcron) < HOURSECS;
        $cronago = $lastcron > 0 ? format_time(time() - $lastcron) : '';

        $repair = get_string('idx_fix', 'local_coursetransfer');
        $url_tasks = (new moodle_url('/admin/tool/task/scheduledtasks.php'))->out(false);

        $data->checks = [
            $this->check('fa-key', get_string('idx_check_token', 'local_coursetransfer'),
                    $data->hastoken ? 'ok' : 'error',
                    get_string($data->hastoken ? 'idx_check_token_ok' : 'idx_check_token_ko', 'local_coursetransfer'),
                    $data->hastoken ? '' : $repair,
                    $data->hastoken ? '' : $data->url_postinstall),
            $this->check('fa-plug', get_string('idx_check_ws', 'local_coursetransfer'),
                    $wsok ? 'ok' : 'warn',
                    get_string($wsok ? 'idx_check_ws_ok' : 'idx_check_ws_ko', 'local_coursetransfer'),
                    $wsok ? '' : get_string('config', 'local_coursetransfer'),
                    $wsok ? '' : $data->url_config),
            $this->check('fa-cubes', get_string('idx_check_service', 'local_coursetransfer'),
                    $serviceok ? 'ok' : 'error',
                    get_string($serviceok ? 'idx_check_service_ok' : 'idx_check_service_ko', 'local_coursetransfer'),
                    $serviceok ? '' : $repair,
                    $serviceok ? '' : $data->url_postinstall),
            $this->check('fa-user-circle-o', get_string('idx_check_user', 'local_coursetransfer'),
                    $userok ? 'ok' : 'error',
                    get_string($userok ? 'idx_check_user_ok' : 'idx_check_user_ko', 'local_coursetransfer'),
                    $userok ? '' : $repair,
                    $userok ? '' : $data->url_postinstall),
            $this->check('fa-user-o', get_string('idx_check_active', 'local_coursetransfer'),
                    $activeok ? 'ok' : 'error',
                    get_string($activeok ? 'idx_check_active_ok' : 'idx_check_active_ko', 'local_coursetransfer'),
                    $activeok ? '' : $repair,
                    $activeok ? '' : $data->url_postinstall),
            $this->check('fa-shield', get_string('idx_check_caps', 'local_coursetransfer'),
                    $capsok ? 'ok' : 'error',
                    get_string($capsok ? 'idx_check_caps_ok' : 'idx_check_caps_ko', 'local_coursetransfer'),
                    $capsok ? '' : $repair,
                    $capsok ? '' : $data->url_postinstall),
            $this->check('fa-clock-o', get_string('idx_check_cron', 'local_coursetransfer'),
                    $cronok ? 'ok' : 'warn',
                    $cronok
                            ? get_string('idx_check_cron_ok', 'local_coursetransfer', $cronago)
                            : get_string('idx_check_cron_ko', 'local_coursetransfer'),
                    get_string('idx_ml_cron', 'local_coursetransfer'), $url_tasks),
            $this->check('fa-globe', get_string('idx_check_platforms', 'local_coursetransfer'),
                    $nplatforms > 0 ? 'ok' : 'warn',
                    get_string('idx_check_platforms_n', 'local_coursetransfer', $nplatforms),
                    get_string('platforms_link', 'local_coursetransfer'), $data->url_platforms),
        ];

        // Quick links.
        $data->pluginlinks = [
            $this->link('fa-globe', get_string('platforms_title', 'local_coursetransfer'),
                    get_string('idx_l_platforms', 'local_coursetransfer'), $data->url_platforms),
            $this->link('fa-download', get_string('rw_title', 'local_coursetransfer'),
                    get_string('idx_l_restore', 'local_coursetransfer'),
                    (new moodle_url('/local/coursetransfer/origin_restore.php'))->out(false)),
            $this->link('fa-trash', get_string('rmv_title', 'local_coursetransfer'),
                    get_string('idx_l_remove', 'local_coursetransfer'),
                    (new moodle_url('/local/coursetransfer/origin_remove.php'))->out(false)),
            $this->link('fa-list-alt', get_string('logs_page', 'local_coursetransfer'),
                    get_string('idx_l_logs', 'local_coursetransfer'), $data->url_logs),
            $this->link('fa-cog', get_string('config', 'local_coursetransfer'),
                    get_string('idx_l_config', 'local_coursetransfer'), $data->url_config),
        ];
        $data->moodlelinks = [
            $this->link('fa-key', get_string('idx_ml_tokens', 'local_coursetransfer'),
                    'admin/webservice/tokens.php',
                    (new moodle_url('/admin/webservice/tokens.php'))->out(false)),
            $this->link('fa-clock-o', get_string('idx_ml_cron', 'local_coursetransfer'),
                    'admin/tool/task/scheduledtasks.php',
                    (new moodle_url('/admin/tool/task/scheduledtasks.php'))->out(false)),
        ];

        return $data;
    }

    /**
     * Build a status-check card context.
     *
     * @param string $faicon
     * @param string $label
     * @param string $state ok|warn|error
     * @param string $status
     * @param string $actionlabel
     * @param string $actionurl
     * @return stdClass
     */
    protected function check(string $faicon, string $label, string $state,
            string $status, string $actionlabel = '', string $actionurl = ''): stdClass {
        return (object)[
            'faicon' => $faicon,
            'label' => $label,
            'state' => $state,
            'status' => $status,
            'hasaction' => $actionlabel !== '' && $actionurl !== '',
            'actionlabel' => $actionlabel,
            'actionurl' => $actionurl,
        ];
    }

    /**
     * Build a quick-link card context.
     *
     * @param string $faicon
     * @param string $title
     * @param string $sub
     * @param string $href
     * @return stdClass
     */
    protected function link(string $faicon, string $title, string $sub, string $href): stdClass {
        return (object)['faicon' => $faicon, 'title' => $title, 'sub' => $sub, 'href' => $href];
    }

}

