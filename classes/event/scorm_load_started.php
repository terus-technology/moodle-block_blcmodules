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
 * Event triggered when a SCORM load process starts.
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules\event;

/**
 * SCORM load started event.
 *
 * @property-read array $other {
 *     Extra information about the event.
 *
 *     - int totalmodules: Total number of modules to load.
 *     - int sectionnumber: The course section number.
 * }
 */
class scorm_load_started extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'block_blc_modules_log';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventscormloadstarted', 'block_blc_modules');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' started a SCORM load process " .
            "for {$this->other['totalmodules']} modules in section {$this->other['sectionnumber']} " .
            "of course with id '$this->courseid'.";
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['totalmodules'])) {
            throw new \coding_exception('The \'totalmodules\' value must be set in other.');
        }
        if (!isset($this->other['sectionnumber'])) {
            throw new \coding_exception('The \'sectionnumber\' value must be set in other.');
        }
    }
}
