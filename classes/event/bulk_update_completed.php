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
 * Event triggered when a bulk update process completes.
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules\event;

/**
 * Bulk update completed event.
 *
 * @property-read array $other {
 *     Extra information about the event.
 *
 *     - int totalmodules: Total number of modules processed.
 *     - int successful: Number of successfully updated modules.
 *     - int failed: Number of failed modules.
 *     - float duration: Duration of the process in seconds.
 * }
 */
class bulk_update_completed extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'block_blc_modules_log';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventbulkupdatecompleted', 'block_blc_modules');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' completed a bulk update process: " .
            "{$this->other['successful']} successful, {$this->other['failed']} failed " .
            "out of {$this->other['totalmodules']} total (duration: {$this->other['duration']}s).";
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
        if (!isset($this->other['successful'])) {
            throw new \coding_exception('The \'successful\' value must be set in other.');
        }
        if (!isset($this->other['failed'])) {
            throw new \coding_exception('The \'failed\' value must be set in other.');
        }
    }
}
