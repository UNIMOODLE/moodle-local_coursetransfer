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
 * Get Category by idnumber regex pattern.
 *
 * Resolves the unique course category whose idnumber matches a provided
 * regular expression. Used by remote consumers (e.g. local_coursetransfermanager)
 * to translate a configurable pattern into a concrete category ID before
 * triggering a category restore.
 *
 * @package    local_coursetransfer
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\external\backend;

use coding_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use dml_exception;
use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Class get_category_idnumber_external.
 *
 * @package local_coursetransfer\external\backend
 */
class get_category_idnumber_external extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function get_category_idnumber_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pattern' => new external_value(PARAM_RAW, 'Category idnumber regex pattern'),
        ]);
    }

    /**
     * Look up a category by an idnumber regex pattern.
     *
     * Returns success only when exactly one category matches — ambiguous or
     * empty results are reported via the success flag so callers can react
     * without raising exceptions.
     *
     * @param string $pattern Regular expression to apply against category idnumbers.
     * @return array {success, category{id,name,idnumber}, matchcount, error}
     * @throws invalid_parameter_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_category_idnumber(string $pattern): array {
        global $DB;

        $params = self::validate_parameters(self::get_category_idnumber_parameters(), [
            'pattern' => $pattern,
        ]);

        $categories = $DB->get_records_select(
            'course_categories',
            'idnumber IS NOT NULL AND idnumber <> :empty',
            ['empty' => ''],
            '',
            'id, name, idnumber'
        );

        $matches = [];
        $regex = '/' . str_replace('/', '\/', $params['pattern']) . '/';

        foreach ($categories as $category) {
            if (@preg_match($regex, $category->idnumber)) {
                $matches[] = [
                    'id' => (int)$category->id,
                    'name' => $category->name,
                    'idnumber' => $category->idnumber,
                ];
            }
        }

        if (count($matches) !== 1) {
            return [
                'success' => false,
                'category' => [
                    'id' => 0,
                    'name' => '',
                    'idnumber' => '',
                ],
                'matchcount' => count($matches),
                'error' => count($matches) === 0
                    ? get_string('categorynotfound', 'local_coursetransfer')
                    : get_string('categoryambiguous', 'local_coursetransfer'),
            ];
        }

        return [
            'success' => true,
            'category' => $matches[0],
            'matchcount' => 1,
            'error' => '',
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function get_category_idnumber_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether exactly one match was found'),
            'category' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Category ID'),
                'name' => new external_value(PARAM_TEXT, 'Category name'),
                'idnumber' => new external_value(PARAM_TEXT, 'Category idnumber'),
            ]),
            'matchcount' => new external_value(PARAM_INT, 'Number of matched categories'),
            'error' => new external_value(PARAM_TEXT, 'Error message'),
        ]);
    }
}
