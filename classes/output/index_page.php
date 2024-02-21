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

namespace local_coursetransfer\output;

use dml_exception;
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
        global $DB;

        $error = false;
        $message = '';

        try {
            $user = \core_user::get_user_by_username(user::USERNAME_WS);

            $tokensql =
                    "SELECT token
                     FROM {external_tokens} et
                     LEFT JOIN {external_services} es ON et.externalserviceid = es.id
                     WHERE es.name = 'local_coursetransfer' AND et.userid = " . $user->id;

            $token = $DB->get_record_sql($tokensql);

            if ($token) {
                $token = $token->token;
            } else {
                $message = get_string('token_not_found', 'local_coursetransfer');
                $token = '';
                $error = true;
            }

        } catch (moodle_exception $e) {
            $token = '';
            $message = $e->getMessage();
            $error = true;
        }

        $urlconfig = new moodle_url('/admin/settings.php', ['section' => 'local_coursetransfer']);
        $urlpostinstall = new moodle_url('/local/coursetransfer/postinstall.php');

        $data = new stdClass();
        $data->token = $token;
        $data->url_config = $urlconfig->out(false);
        $data->url_postinstall = $urlpostinstall->out(false);
        $data->error = $error;
        $data->message = $message;
        return $data;
    }

}

