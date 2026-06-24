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
     * Output message at minimal level (fatal errors only) with probable causes and resolution steps.
     *
     * @param string $message User-friendly error message
     * @param array $causes Possible causes
     * @param array $actions Resolution steps
     * @param string $technicaldetails Technical details
     * @param bool $affectscore Whether core functionality is affected
     */
    public function error(
        string $message,
        array $causes,
        array $actions,
        string $technicaldetails = '',
        bool $affectscore = true
    ): void {
        $this->log(
            $message,
            self::SEVERITY_ERROR,
            $affectscore,
            $causes,
            $actions,
            $technicaldetails
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
     * Output message at developer level (critical errors only) with probable causes and resolution steps.
     *
     * @param string $message User-friendly error message
     * @param array $causes Possible causes
     * @param array $actions Resolution steps
     * @param string $technicaldetails Technical details
     */
    public function critical(
        string $message,
        array $causes,
        array $actions,
        string $technicaldetails = ''
    ): void {
        $this->log(
            $message,
            self::SEVERITY_ERROR,
            true,
            $causes,
            $actions,
            $technicaldetails
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
     * Log a message with severity level and optional troubleshooting guidance.
     *
     * @param string $message User-friendly error message
     * @param string $severity Severity level
     * @param bool $affectscore Whether core functionality is affected
     * @param array $probablecauses Possible causes of the issue
     * @param array $actions Recommended actions to resolve the issue
     * @param string $technicaldetails Technical details for developers
     */
    protected function log(
        string $message,
        string $severity = self::SEVERITY_INFO,
        bool $affectscore = false,
        array $probablecauses = [],
        array $actions = [],
        string $technicaldetails = ''
    ): void {
        $output = '[' . strtoupper($severity) . '] ' . $message . PHP_EOL . PHP_EOL;

        if (!empty($probablecauses)) {
            $output .= 'PROBABLE CAUSE:' . PHP_EOL;

            foreach ($probablecauses as $cause) {
                $output .= '- ' . $cause . PHP_EOL;
            }

            $output .= PHP_EOL;
        }

        if (!empty($actions)) {
            $output .= 'ACTION:' . PHP_EOL;

            foreach ($actions as $index => $action) {
                $output .= ($index + 1) . '. ' . $action . PHP_EOL;
            }

            $output .= PHP_EOL;
        }

        $impact = $affectscore
            ? 'Core functionality is affected.'
            : 'Core functionality remains available.';

        $output .= 'IMPACT: ' . $impact . PHP_EOL;

        if ($technicaldetails && $this->is_developer_mode()) {
            $output .= PHP_EOL;
            $output .= 'TECHNICAL DETAILS:' . PHP_EOL;
            $output .= $technicaldetails . PHP_EOL;
        }

        switch ($severity) {
            case self::SEVERITY_CRITICAL:
            case self::SEVERITY_ERROR:
                debugging($output);
                break;

            case self::SEVERITY_WARNING:
                if (!$this->is_minimal_mode()) {
                    debugging($output);
                }
                break;

            case self::SEVERITY_INFO:
                if ($this->is_developer_mode()) {
                    debugging($output);
                }
                break;
        }
    }
}
