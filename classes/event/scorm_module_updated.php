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
 * Event triggered when a SCORM module is updated via BLC Modules plugin.
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules\event;

/**
 * SCORM module updated event.
 *
 * @property-read array $other {
 *     Extra information about the event.
 *
 *     - int scormid: The SCORM instance ID.
 *     - string scormname: The name of the SCORM module.
 *     - string scormurl: The SCORM package URL.
 *     - int oldversion: The previous version number.
 *     - int newversion: The new version number.
 * }
 */
class scorm_module_updated extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'block_blc_modules';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventscormmoduleupdated', 'block_blc_modules');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' updated SCORM module '{$this->other['scormname']}' " .
            "(scormid: {$this->other['scormid']}) from version {$this->other['oldversion']} " .
            "to version {$this->other['newversion']} in course with id '$this->courseid'.";
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['scormid'])) {
            throw new \coding_exception('The \'scormid\' value must be set in other.');
        }
        if (!isset($this->other['scormname'])) {
            throw new \coding_exception('The \'scormname\' value must be set in other.');
        }
        if (!isset($this->other['oldversion'])) {
            throw new \coding_exception('The \'oldversion\' value must be set in other.');
        }
        if (!isset($this->other['newversion'])) {
            throw new \coding_exception('The \'newversion\' value must be set in other.');
        }
    }
}
