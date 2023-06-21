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

class configuration {

    /** @var bool Destinity Remove Activities */
    public $destinyremoveactivities;

    /** @var bool Destinity Merge Activities */
    public $destinymergeactivities;

    /** @var bool Destinity Remove Enrols */
    public $destinyremoveenrols;

    /** @var bool Destinity Remove Groups */
    public $destinyremovegroups;

    /** @var bool Origin Remove Course */
    public $originremovecourse;

    /** @var string Destinity Not Remove Activities */
    public $destinynotremoveactivities;

    /**
     * constructor.
     *
     * @param bool $destinyremoveactivities
     * @param bool $destinymergeactivities
     * @param bool $destinyremoveenrols
     * @param bool $destinyremovegroups
     * @param bool $originremovecourse
     * @param string $destinynotremoveactivities
     */
    public function __construct(
            bool $destinyremoveactivities, bool $destinymergeactivities, bool $destinyremoveenrols,
    bool $destinyremovegroups, bool $originremovecourse, string $destinynotremoveactivities = '') {
        $this->set_destiny_remove_activities($destinyremoveactivities);
        $this->set_destiny_merge_activities($destinymergeactivities);
        $this->set_destiny_remove_enrols($destinyremoveenrols);
        $this->set_destiny_remove_groups($destinyremovegroups);
        $this->set_origin_remove_course($originremovecourse);
        $this->set_destiny_notremove_activities($destinynotremoveactivities);
    }

    /**
     * Set Destiny Remove Activities.
     *
     * @param bool $config
     */
    protected function set_destiny_remove_activities(bool $config) {
        $this->destinyremoveactivities = $config;
    }

    /**
     * Set Destiny Merge Activities.
     *
     * @param bool $config
     */
    protected function set_destiny_merge_activities(bool $config) {
        $this->destinymergeactivities = $config;
    }

    /**
     * Set Destiny Remove Enrols.
     *
     * @param bool $config
     */
    protected function set_destiny_remove_enrols(bool $config) {
        $this->destinyremoveenrols = $config;
    }

    /**
     * Set Destiny Remove Groups.
     *
     * @param bool $config
     */
    protected function set_destiny_remove_groups(bool $config) {
        $this->destinyremovegroups = $config;
    }

    /**
     * Set Origin Remove Course.
     *
     * @param bool $config
     */
    protected function set_origin_remove_course(bool $config) {
        $this->originremovecourse = $config;
    }

    /**
     * Set Destiny Not Remove Activities.
     *
     * @param string $config
     */
    protected function set_destiny_notremove_activities(string $config) {
        $this->destinynotremoveactivities = $config;
    }

}
