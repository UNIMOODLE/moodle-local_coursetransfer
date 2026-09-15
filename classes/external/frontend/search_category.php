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
 * Search Category External.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external\frontend;

use core_course_category;
use core_text;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/lib.php');

/**
 * Class search_category
 *
 * Server-side search of local destination categories for the restore wizard.
 * Backs the core/form-autocomplete widget so the picker scales to sites with
 * thousands of categories (the old <select> dumped every category into the DOM).
 *
 * @package local_coursetransfer\external\frontend
 */
class search_category extends external_api {
    /** @var int Maximum categories returned per query (keeps the payload bounded). */
    const MAX_RESULTS = 30;

    /**
     * Search by name parameters.
     *
     * @return external_function_parameters
     */
    public static function search_by_name_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'text' => new external_value(PARAM_TEXT, 'Text for searching a destination category'),
                'type' => new external_value(
                    PARAM_ALPHA,
                    'Restore type: "category" (option 0 = Top/root) or "course" (option 0 = default category)',
                    VALUE_DEFAULT,
                    ''
                ),
            ]
        );
    }

    /**
     * Search destination categories by (path) name.
     *
     * Only categories where the user can create courses are offered, mirroring
     * the capability the plain select used. Result 0 is the synthetic option
     * returned when the query is empty or matches its label, so it stays
     * reachable from the autocomplete. Its meaning (and label) depends on the
     * restore type: for a category restore it is the top level (Moodle's "Top",
     * parent 0 = a new root category); for a course restore it is the site
     * default category (a course cannot live under Top).
     *
     * @param string $text
     * @param string $type Restore type ("category" | "course" | "").
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function search_by_name(string $text, string $type = ''): array {
        $params = self::validate_parameters(
            self::search_by_name_parameters(),
            [
                'text' => $text,
                'type' => $type,
            ]
        );

        $needle = core_text::strtolower(trim($params['text']));

        $success = false;
        $errors = [];
        $data = [];

        try {
            // The id-0 pseudo option, always first when it matches. Label by type:
            // category => Moodle's core "Top"; course => plugin "default category".
            $defaultlabel = ($params['type'] === 'category')
                ? get_string('top')
                : get_string('rw_defaultcat', 'local_coursetransfer');
            if ($needle === '' || core_text::strpos(core_text::strtolower($defaultlabel), $needle) !== false) {
                $d = new stdClass();
                $d->id = 0;
                $d->name = $defaultlabel;
                $data[] = $d;
            }

            // Real categories the user may create courses in (full path names).
            $list = core_course_category::make_categories_list('moodle/course:create');
            foreach ($list as $id => $name) {
                if (count($data) >= self::MAX_RESULTS) {
                    break;
                }
                if ($needle !== '' && core_text::strpos(core_text::strtolower($name), $needle) === false) {
                    continue;
                }
                $c = new stdClass();
                $c->id = (int)$id;
                $c->name = $name;
                $data[] = $c;
            }
            $success = true;
        } catch (moodle_exception $e) {
            $errors[] = [
                'code' => '18100',
                'msg' => $e->getMessage(),
            ];
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * Search by name returns.
     *
     * @return external_single_structure
     */
    public static function search_by_name_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Was it a success?'),
                'errors' => new external_multiple_structure(new external_single_structure(
                    [
                        'code' => new external_value(PARAM_TEXT, 'Code'),
                        'msg' => new external_value(PARAM_RAW, 'Message'),
                    ]
                )),
                'data' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Category ID (0 = default category)'),
                        'name' => new external_value(PARAM_TEXT, 'Category path name'),
                    ])
                ),
            ]
        );
    }
}
