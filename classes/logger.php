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
 * Logger class for SCORM load processes
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules;

use block_blc_modules\helper\debug_helper;
use Exception;
use stdClass;

/**
 * Logger class for persistent SCORM load logging
 */
class logger {
    /** @var int Auto-cleanup retention period in seconds (30 days = 2592000 seconds) */
    const RETENTION_PERIOD = 2592000; // 30 days

    /**
     * Log a SCORM load event to database
     *
     * @param array $data Log data containing:
     *   - courseid (int) Required
     *   - sectionnumber (int) Required
     *   - status (string) Required: started, processing, completed, failed
     *   - log_level (string) Required: info, success, error, warning
     *   - message (string) Required
     *   - process_type (string) Optional: scorm_load, bulk_update (default: scorm_load)
     *   - scormurl (string) Optional
     *   - scormname (string) Optional
     *   - scormid (int) Optional
     *   - cmid (int) Optional
     *   - error_details (string) Optional
     *   - parameters (array) Optional: will be JSON encoded
     * @return int|false Log record ID or false on failure
     */
    public static function log_scorm_load($data) {
        global $DB, $USER;

        try {
            $record = new stdClass();
            $record->userid = $USER->id;
            $record->courseid = $data['courseid'];
            $record->sectionnumber = $data['sectionnumber'];
            $record->process_type = $data['process_type'] ?? 'scorm_load';
            $record->process_status = $data['status'];
            $record->session_id = session_id();

            // Store parameters as JSON if provided.
            if (isset($data['parameters']) && is_array($data['parameters'])) {
                // Mask API key for security.
                if (isset($data['parameters']['apikey'])) {
                    $data['parameters']['apikey'] = self::mask_apikey($data['parameters']['apikey']);
                }
                $record->parameters = json_encode($data['parameters']);
            } else {
                $record->parameters = null;
            }

            $record->scormurl = $data['scormurl'] ?? null;
            $record->scormname = $data['scormname'] ?? null;
            $record->scormid = $data['scormid'] ?? null;
            $record->cmid = $data['cmid'] ?? null;
            $record->log_level = $data['log_level'];
            $record->message = $data['message'];
            $record->error_details = $data['error_details'] ?? null;
            $record->ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $record->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
            $record->timecreated = time();

            return $DB->insert_record('block_blc_modules_log', $record);
        } catch (Exception $e) {
            // Log to Moodle error log instead of failing.
            $logger = new debug_helper();
            $logger->error(
                'BLC logger: A SCORM activity event could not be recorded in the BLC log table.',
                [
                    'The BLC log table is missing or unavailable.',
                    'A database write operation failed.',
                    'The log record contains invalid data.',
                ],
                [
                    'Verify that the BLC plugin database tables exist.',
                    'Check Moodle database connectivity.',
                    'Review the technical details below.',
                    'Run the plugin upgrade if database changes are pending.',
                ],
                $e->getMessage(),
                false
            );
            return false;
        }
    }

    /**
     * Mask API key for security (show only last 4 characters)
     *
     * @param string $apikey API key to mask
     * @return string Masked API key
     */
    public static function mask_apikey($apikey) {
        if (empty($apikey) || strlen($apikey) < 4) {
            return '****';
        }
        return '****' . substr($apikey, -4);
    }

    /**
     * Get logs with optional filters
     *
     * @param array $filters Optional filters:
     *   - userid (int)
     *   - courseid (int)
     *   - session_id (string)
     *   - process_type (string)
     *   - process_status (string)
     *   - log_level (string)
     *   - from_date (int) Unix timestamp
     *   - to_date (int) Unix timestamp
     *   - limit (int) Default: 100
     *   - offset (int) Default: 0
     * @return array Array of log records
     */
    public static function get_logs($filters = []) {
        global $DB;

        $params = [];
        $where = [];

        if (!empty($filters['userid'])) {
            $where[] = 'userid = :userid';
            $params['userid'] = $filters['userid'];
        }

        if (!empty($filters['courseid'])) {
            $where[] = 'courseid = :courseid';
            $params['courseid'] = $filters['courseid'];
        }

        if (!empty($filters['session_id'])) {
            $where[] = 'session_id = :session_id';
            $params['session_id'] = $filters['session_id'];
        }

        if (!empty($filters['process_type'])) {
            $where[] = 'process_type = :process_type';
            $params['process_type'] = $filters['process_type'];
        }

        if (!empty($filters['process_status'])) {
            $where[] = 'process_status = :process_status';
            $params['process_status'] = $filters['process_status'];
        }

        if (!empty($filters['log_level'])) {
            $where[] = 'log_level = :log_level';
            $params['log_level'] = $filters['log_level'];
        }

        if (!empty($filters['from_date'])) {
            $where[] = 'timecreated >= :from_date';
            $params['from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where[] = 'timecreated <= :to_date';
            $params['to_date'] = $filters['to_date'];
        }

        $sql = "SELECT * FROM {block_blc_modules_log}";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY timecreated DESC';

        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;

        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Get log statistics
     *
     * @param array $filters Same filters as get_logs()
     * @return object Statistics object with counts
     */
    public static function get_statistics($filters = []) {
        global $DB;

        $params = [];
        $where = [];

        // Build WHERE clause (same as get_logs).
        if (!empty($filters['userid'])) {
            $where[] = 'userid = :userid';
            $params['userid'] = $filters['userid'];
        }

        if (!empty($filters['courseid'])) {
            $where[] = 'courseid = :courseid';
            $params['courseid'] = $filters['courseid'];
        }

        if (!empty($filters['from_date'])) {
            $where[] = 'timecreated >= :from_date';
            $params['from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where[] = 'timecreated <= :to_date';
            $params['to_date'] = $filters['to_date'];
        }

        $whereclause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

        // Get counts by log level.
        $sql = "
            SELECT
                COUNT(*) as total_logs,
                SUM(CASE WHEN log_level = 'info' THEN 1 ELSE 0 END) as info_count,
                SUM(CASE WHEN log_level = 'success' THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN log_level = 'error' THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN log_level = 'warning' THEN 1 ELSE 0 END) as warning_count,
                COUNT(DISTINCT session_id) as total_sessions
            FROM {block_blc_modules_log}
        " . $whereclause;

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Get logs grouped by session
     *
     * @param array $filters Same filters as get_logs()
     * @return array Array of sessions with their logs
     */
    public static function get_logs_by_session($filters = []) {
        $logs = self::get_logs($filters);
        $sessions = [];

        foreach ($logs as $log) {
            $sessionid = $log->session_id ?? 'unknown';
            if (!isset($sessions[$sessionid])) {
                $sessions[$sessionid] = [
                    'session_id' => $sessionid,
                    'logs' => [],
                    'start_time' => $log->timecreated,
                    'end_time' => $log->timecreated,
                    'userid' => $log->userid,
                    'courseid' => $log->courseid,
                ];
            }

            $sessions[$sessionid]['logs'][] = $log;

            // Update time range.
            if ($log->timecreated < $sessions[$sessionid]['start_time']) {
                $sessions[$sessionid]['start_time'] = $log->timecreated;
            }
            if ($log->timecreated > $sessions[$sessionid]['end_time']) {
                $sessions[$sessionid]['end_time'] = $log->timecreated;
            }
        }

        return array_values($sessions);
    }

    /**
     * Clean up old logs based on retention period
     *
     * @return int Number of deleted records
     */
    public static function cleanup_old_logs() {
        global $DB;

        $cutoff = time() - self::RETENTION_PERIOD;

        try {
            return $DB->delete_records_select('block_blc_modules_log', 'timecreated < ?', [$cutoff]);
        } catch (Exception $e) {
            $logger = new debug_helper();
            $logger->error(
                'BLC logger: Old BLC log records could not be removed.',
                [
                    'The database is unavailable.',
                    'The log table does not exist.',
                    'The database user does not have permission to delete records.',
                ],
                [
                    'Verify Moodle database connectivity.',
                    'Check that the BLC log table exists.',
                    'Review database permissions for the Moodle user.',
                    'Run the cleanup task again after correcting the issue.',
                ],
                sprintf(
                    'cutoff=%s, error=%s',
                    userdate($cutoff),
                    $e->getMessage()
                ),
                false
            );
            return 0;
        }
    }

    /**
     * Get unique courses that have logs
     *
     * @return array Array of course records
     */
    public static function get_courses_with_logs() {
        global $DB;

        $sql = "SELECT DISTINCT c.id, c.fullname
                FROM {block_blc_modules_log} log
                JOIN {course} c ON c.id = log.courseid
                ORDER BY c.fullname";

        return $DB->get_records_sql($sql);
    }

    /**
     * Get unique users that have logs
     *
     * @return array Array of user records
     */
    public static function get_users_with_logs() {
        global $DB;

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname
                FROM {block_blc_modules_log} log
                JOIN {user} u ON u.id = log.userid
                ORDER BY u.lastname, u.firstname";

        return $DB->get_records_sql($sql);
    }
}
