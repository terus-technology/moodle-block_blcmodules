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
 * Debug-aware logging utility for BLC Modules block.
 *
 * @package    block_blc_modules
 * @copyright  2026 Terus Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Logger class that controls output debug based on Moodle debugging mode.
 *
 * Debug levels:
 * - MINIMAL: Only fatal errors
 * - NORMAL: Errors and warnings
 * - DEVELOPER: High debug (all information)
 */
class debug_helper {
    /** @var string Minimal debug level. */
    const LEVEL_MINIMAL = 'minimal';
    /** @var string Normal debug level. */
    const LEVEL_NORMAL = 'normal';
    /** @var string Developer debug level. */
    const LEVEL_DEVELOPER = 'developer';

    /** @var string Info severity level. */
    const SEVERITY_INFO = 'info';
    /** @var string Warning severity level. */
    const SEVERITY_WARNING = 'warning';
    /** @var string Error severity level. */
    const SEVERITY_ERROR = 'error';
    /** @var string Critical severity level. */
    const SEVERITY_CRITICAL = 'critical';

    /** @var string Current debug level. */
    private $currentlevel;

    /**
     * Constructor - determines debug level based on Moodle's debugging configuration.
     */
    public function __construct() {
        $this->currentlevel = $this->get_debug_level();
    }

    /**
     * Determine current debug level based on Moodle debug settings.
     *
     * @return string Debug level: 'minimal', 'normal', or 'developer'
     */
    private function get_debug_level() {
        global $CFG;

        if (!isset($CFG->debug)) {
            return self::LEVEL_NORMAL;
        }

        // Moodle DEBUG constants are defined in lib/setuplib.php.
        if ($CFG->debug == DEBUG_NONE || $CFG->debug == 0) { // DEBUG_NONE.
            return self::LEVEL_MINIMAL;
        } else if ($CFG->debug == DEBUG_MINIMAL) { // DEBUG_MINIMAL.
            return self::LEVEL_MINIMAL;
        } else if ($CFG->debug == DEBUG_NORMAL) { // DEBUG_NORMAL.
            return self::LEVEL_NORMAL;
        } else if ($CFG->debug == DEBUG_DEVELOPER || $CFG->debug > DEBUG_NORMAL) {
            // DEBUG_DEVELOPER or custom levels above normal debug.
            return self::LEVEL_DEVELOPER;
        }

        return self::LEVEL_NORMAL;
    }

    /**
     * Output message at minimal level (fatal errors only).
     *
     * @param string $message The message to output
     * @param bool $affectscore Whether the error affects the score
     */
    public function error($message, bool $affectscore = false) {
        $this->log(
            $message,
            self::SEVERITY_ERROR,
            $affectscore
        );
    }

    /**
     * Output message at normal level (errors and warnings).
     *
     * @param string $message The message to output
     * @param bool $affectscore Whether the warning affects the score
     */
    public function warning($message, bool $affectscore = false) {
        $this->log(
            $message,
            self::SEVERITY_WARNING,
            $affectscore
        );
    }

    /**
     * Output message at normal level (general information).
     *
     * @param string $message The message to output
     */
    public function info($message) {
        $this->log(
            $message,
            self::SEVERITY_INFO,
            false
        );
    }

    /**
     * Output message at developer level (critical errors only).
     *
     * @param string $message The message to output
     */
    public function critical(string $message) {
        $this->log(
            $message,
            self::SEVERITY_CRITICAL,
            true
        );
    }

    /**
     * Get the current debug level.
     *
     * @return string Current debug level
     */
    public function get_level() {
        return $this->currentlevel;
    }

    /**
     * Check if we're in developer mode.
     *
     * @return bool True if in developer mode
     */
    public function is_developer_mode() {
        return $this->currentlevel === self::LEVEL_DEVELOPER;
    }

    /**
     * Check if we're in minimal mode.
     *
     * @return bool True if in minimal mode
     */
    public function is_minimal_mode() {
        return $this->currentlevel === self::LEVEL_MINIMAL;
    }

    /**
     * Log a message with severity level and optional impact indication.
     *
     * @param string $message The message to output
     * @param string $severity The severity level (SEVERITY_CRITICAL, SEVERITY_ERROR, SEVERITY_WARNING, SEVERITY_INFO)
     * @param bool $affectscore Whether this affects core functionality
     */
    public function log(
        string $message,
        string $severity = self::SEVERITY_INFO,
        bool $affectscore = false
    ): void {
        $prefix = strtoupper($severity);

        $impact = $affectscore
            ? '. Core functionality affected.'
            : '. Core functionality remains available.';

        $formatted = sprintf(
            '[%s] %s %s',
            $prefix,
            $message,
            $impact
        );

        switch ($severity) {
            case self::SEVERITY_CRITICAL:
            case self::SEVERITY_ERROR:
                debugging($formatted);
                break;

            case self::SEVERITY_WARNING:
                if (!$this->is_minimal_mode()) {
                    debugging($formatted);
                }
                break;

            case self::SEVERITY_INFO:
                if ($this->is_developer_mode()) {
                    debugging($formatted);
                }
                break;
        }
    }
}
