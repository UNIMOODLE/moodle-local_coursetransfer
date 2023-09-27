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
 * Class destiny_sites_table
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\tables;

use coding_exception;
use moodle_exception;
use moodle_url;
use stdClass;
use table_sql;

defined('MOODLE_INTERNAL') || die;

require_once('../../lib/tablelib.php');

/**
 * Class destiny_sites_table
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class destiny_sites_table extends table_sql {

    /** @var int PAGE SIZE */
    const PAGE_SIZE = 100;

    /** @var stdClass Course */
    protected $course;

    /** @var string Site */
    protected $site;

    /**
     * constructor.
     *
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function __construct() {

        $uniqueid = time();
        parent::__construct($uniqueid);

        $this->pageable(true);
        $this->collapsible(true);
        $this->sortable(true);
        $url = '/local/coursetransfer/destinysites.php';
        $moodleurl = new moodle_url($url);
        $this->define_baseurl($moodleurl);

        $this->define_columns([
            'id', 'url', 'token', 'actions'
        ]);

        $this->define_headers([
                get_string('id', 'local_coursetransfer'),
                get_string('host_url', 'local_coursetransfer'),
                get_string('host_token', 'local_coursetransfer'),
                get_string('actions', 'local_coursetransfer'),
        ]);

        $this->is_collapsible = false;
        $this->sortable(false);

        $this->column_style('id', 'text-align', 'center');
        $this->column_style('url', 'text-align', 'left');
        $this->column_style('token', 'text-align', 'center');
        $this->column_style('actions', 'text-align', 'center');
    }

    /**
     * Col request id
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_id(stdClass $row): string {
        return $row->id;
    }

    /**
     * Col URL
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_url(stdClass $row): string {
        return $row->host;
    }

    /**
     * Col Token
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_token(stdClass $row): string {
        return $row->token;
    }

    /**
     * Col Actions
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_actions(stdClass $row): string {
        $html = '<!-- Button trigger modal -->
<button type="button" class="btn btn-danger" data-toggle="modal" data-target="#destinyDelete' . $row->id . '">
  Borrar
</button>

<!-- Modal -->
<div class="modal fade" id="destinyDelete' . $row->id . '" tabindex="-1" role="dialog" aria-labelledby="destinyDelete' . $row->id . '" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Borrar sitio de destino ' . $row->id . '</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><b>¿Estas seguro de borrar este sitio de destino?</b></p>
        <p>' . $row->host . '</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" data-action="remove" data-id="' . $row->id . '" class="btn btn-danger">Borrar</button>
      </div>
    </div>
  </div>
</div>';

        $html .= '<!-- Button trigger modal -->
<button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#destinyEdit' . $row->id . '">
  Editar
</button>

<!-- Modal -->
<div class="modal fade" id="destinyEdit' . $row->id . '" tabindex="-1" role="dialog" aria-labelledby="destinyEdit' . $row->id . '" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Editar sitio de destino ' . $row->id . '</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form data-region="edit-form" data-id="' . $row->id . '" style="text-align: left">
            <div class="form-group">
                <label for="host">Host</label>
                <input type="text" value="' . $row->host . '" class="form-control" id="host" aria-describedby="emailHelp" placeholder="URL de destino">
                <small id="hostHelp" class="form-text text-muted">Añada la URL del host de destino</small>
            </div>
            <div class="form-group">
                <label for="token">Token</label>
                <input type="text" value="' . $row->token . '" class="form-control" id="token" placeholder="Token de destino">
                <small id="tokenHelp" class="form-text text-muted">Añada el Token de destino</small>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" data-action="edit" data-id="' . $row->id . '" class="btn btn-primary">Guardar</button>
      </div>
    </div>
  </div>
</div>';

        $html .= '<button disabled type="button" class="btn btn-light" data-toggle="modal" data-target="#destinyEdit' . $row->id . '">
  Test
</button>';
        return $html;
    }

}
