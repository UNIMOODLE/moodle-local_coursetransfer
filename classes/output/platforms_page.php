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
 * Unified paired-platforms page (Tresipunt redesign).
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\output;

use coding_exception;
use core\exception\moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * platforms_page
 *
 * Renders the unified list of paired platforms. Receives the already
 * resolved platform list by constructor: no database access here.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class platforms_page implements renderable, templatable {
    /** @var stdClass[] Platforms (merged origin/target rows by host) */
    protected array $platforms;

    /** @var bool[] Hosts with registered requests, keyed by host */
    protected array $inuse;

    /**
     * Constructor.
     *
     * @param stdClass[] $platforms as returned by coursetransfer_sites::get_platforms()
     * @param bool[] $inuse map host => has requests registered
     */
    public function __construct(array $platforms, array $inuse = []) {
        $this->platforms = $platforms;
        $this->inuse = $inuse;
    }

    /**
     * Export for Template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->headertitle = get_string('platforms_title', 'local_coursetransfer');
        $data->headerdesc = get_string('platforms_lead', 'local_coursetransfer');
        $data->back = (new moodle_url('/admin/settings.php', ['section' => 'local_coursetransfer']))->out(false);
        $data->summary = (new moodle_url('/local/coursetransfer/index.php'))->out(false);
        $data->platforms = [];
        $data->hasplatforms = !empty($this->platforms);
        foreach ($this->platforms as $platform) {
            $data->platforms[] = $this->export_platform($platform);
        }
        return $data;
    }

    /**
     * Export one platform row.
     *
     * @param stdClass $platform
     * @return stdClass
     * @throws coding_exception
     */
    protected function export_platform(stdClass $platform): stdClass {
        $item = new stdClass();
        $item->name = $platform->name;
        $item->host = $platform->host;
        $item->originid = $platform->origin->id ?? 0;
        $item->targetid = $platform->target->id ?? 0;
        $item->roleorigin = !empty($platform->origin);
        $item->roletarget = !empty($platform->target);
        $item->inuse = !empty($this->inuse[$platform->host]);
        $item->isself = $this->is_self_host($platform->host);

        // Current token, so editing (e.g. renaming) never forces re-entering
        // it. Admin-only page (moodle/site:config). When both role rows hold
        // different tokens the field is left empty and must be re-entered.
        $origintoken = $platform->origin->token ?? null;
        $targettoken = $platform->target->token ?? null;
        if ($origintoken !== null && $targettoken !== null) {
            $item->token = ($origintoken === $targettoken) ? $origintoken : '';
        } else {
            $item->token = $origintoken ?? $targettoken ?? '';
        }

        // Connection badge from the persisted last test.
        $item->connok = false;
        $item->connerror = false;
        $item->connuntested = false;
        if ($platform->lastteststatus === null) {
            $item->connuntested = true;
            $item->connlabel = get_string('platform_conn_untested', 'local_coursetransfer');
            $item->lasttestlabel = get_string('platform_untested', 'local_coursetransfer');
        } else if ((int)$platform->lastteststatus === 1) {
            $item->connok = true;
            $item->connlabel = get_string('platform_conn_ok', 'local_coursetransfer');
            $item->lasttestlabel = get_string(
                'platform_lasttest',
                'local_coursetransfer',
                userdate($platform->lasttest, get_string('strftimedatetimeshort', 'langconfig'))
            );
        } else {
            $item->connerror = true;
            $item->connlabel = get_string('platform_conn_error', 'local_coursetransfer');
            $item->lasttestlabel = get_string(
                'platform_lasttest',
                'local_coursetransfer',
                userdate($platform->lasttest, get_string('strftimedatetimeshort', 'langconfig'))
            );
            $item->errcause = (string)$platform->lasttesterror;
            $item->erraction = get_string('platform_error_action', 'local_coursetransfer');
        }

        // Relationship summary line.
        if ($item->roleorigin && $item->roletarget) {
            $item->pairlabel = get_string('platform_pair_both', 'local_coursetransfer');
        } else if ($item->roleorigin) {
            $item->pairlabel = get_string('platform_pair_origin', 'local_coursetransfer');
        } else {
            $item->pairlabel = get_string('platform_pair_target', 'local_coursetransfer');
        }
        return $item;
    }

    /**
     * Whether the host is this very platform (self-pairing).
     *
     * @param string $host
     * @return bool
     */
    protected function is_self_host(string $host): bool {
        global $CFG;
        $normalize = function (string $url): string {
            return strtolower(rtrim(trim($url), '/'));
        };
        return $normalize($host) === $normalize($CFG->wwwroot);
    }
}
