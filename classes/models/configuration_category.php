<?php
// This file is part of the local_amnh plugin for Moodle - http://moodle.org/
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
 * configuration
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\models;

class configuration_category extends configuration {

    /** @var bool Origin Remove Category */
    public $originremovecategory;

    /**
     * constructor.
     *
     * @param int $destinytarget
     * @param bool $destinyremoveenrols
     * @param bool $destinyremovegroups
     * @param bool $originenrolusers
     * @param bool $originremovecategory
     */
    public function __construct(
            int $destinytarget, bool $destinyremoveenrols, bool $destinyremovegroups,
            bool $originenrolusers = false, bool $originremovecategory = false) {
        parent::__construct($destinytarget, $destinyremoveenrols, $destinyremovegroups, $originenrolusers);
        $this->set_origin_remove_category($originremovecategory);
    }

    /**
     * Set Origin Remove Category.
     *
     * @param bool $config
     */
    protected function set_origin_remove_category(bool $config) {
        $this->originremovecategory = $config;
    }

}
