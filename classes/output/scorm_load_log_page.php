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
 * Class scorm_load_log_page
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules\output;

defined('MOODLE_INTERNAL') || die();

use block_blc_modules\logger;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use stdClass;
use moodle_url;

require_once($CFG->dirroot.'/blocks/blc_modules/locallib.php');

/**
 * Class scorm_load_log_page
 */
class scorm_load_log_page implements renderable, templatable {
    /** @var moodle_url Base URL */
    public $baseurl;

    /** @var moodle_url Home URL */
    public $homeurl;

    /** @var array Filters */
    public $filters;

    /**
     * Constructor.
     *
     * @param moodle_url $baseurl Base URL for the page
     * @param moodle_url $homeurl Home URL
     * @param array $filters Filter parameters
     */
    public function __construct($baseurl, $homeurl, $filters = []) {
        $this->baseurl = $baseurl;
        $this->homeurl = $homeurl;
        $this->filters = $filters;
    }

    /**
     * Export data for template
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->baseurl = $this->baseurl->out(false);
        $data->homeurl = $this->homeurl->out(false);

        // Prepare filters for database query.
        $dbfilters = [];
        if (!empty($this->filters['courseid'])) {
            $dbfilters['courseid'] = $this->filters['courseid'];
        }
        if (!empty($this->filters['userid'])) {
            $dbfilters['userid'] = $this->filters['userid'];
        }
        if (!empty($this->filters['loglevel'])) {
            $dbfilters['log_level'] = $this->filters['loglevel'];
        }
        if (!empty($this->filters['sessionid'])) {
            $dbfilters['session_id'] = $this->filters['sessionid'];
        }
        if (!empty($this->filters['fromdate'])) {
            $dbfilters['from_date'] = $this->filters['fromdate'];
        }
        if (!empty($this->filters['todate'])) {
            $dbfilters['to_date'] = $this->filters['todate'];
        }

        // Set reasonable limit.
        $dbfilters['limit'] = 500;

        // Get logs grouped by session.
        $logger = new logger();
        $sessions = $logger::get_logs_by_session($dbfilters);

        // Initialize statistics counters.
        $statscounts = [
            'total' => 0,
            'info' => 0,
            'success' => 0,
            'error' => 0,
            'warning' => 0,
        ];

        // Transform sessions for template.
        $data->sessions = [];
        foreach ($sessions as $session) {
            $sessiondata = new stdClass();
            $sessiondata->session_id = $session['session_id'];
            $sessiondata->session_id_short = substr($session['session_id'], 0, 8);
            $sessiondata->start_time = userdate($session['start_time'], '%d %B %Y, %H:%M:%S');
            $sessiondata->end_time = userdate($session['end_time'], '%H:%M:%S');
            $sessiondata->duration = $this->format_duration($session['end_time'] - $session['start_time']);
            $sessiondata->username = $this->get_username($session['userid']);
            $sessiondata->coursename = $this->get_coursename($session['courseid']);

            // Process logs.
            $sessiondata->logs = [];
            $hasparameters = false;
            foreach ($session['logs'] as $log) {
                // Skip info logs without parameters (hide logs tanpa parameter).
                if ($log->log_level === 'info' && empty($log->parameters)) {
                    continue;
                }

                // Count for statistics (only counted logs that are displayed).
                $statscounts['total']++;
                $statscounts[$log->log_level]++;

                $logentry = new stdClass();
                $logentry->id = $log->id;
                $logentry->message = format_text($log->message, FORMAT_PLAIN);
                $logentry->log_level = $log->log_level;
                $logentry->log_level_class = $this->get_log_level_class($log->log_level);
                $logentry->log_level_icon = $this->get_log_level_icon($log->log_level);
                $logentry->timeformatted = userdate($log->timecreated, '%H:%M:%S');
                $logentry->scormname = $log->scormname ?? '-';
                $logentry->has_error = !empty($log->error_details);
                $logentry->error_details = $log->error_details ?? '';

                // Check for parameters.
                if (!empty($log->parameters)) {
                    $hasparameters = true;
                    $params = json_decode($log->parameters, true);
                    if ($params) {
                        $logentry->has_parameters = true;
                        $logentry->parameters = $this->format_parameters($params);
                    }
                }

                $sessiondata->logs[] = $logentry;
            }

            $sessiondata->has_parameters = $hasparameters;
            $data->sessions[] = $sessiondata;
        }

        $data->has_sessions = count($data->sessions) > 0;

        // Get statistics from manual counts (accurate with displayed logs).
        $data->total_logs = $statscounts['total'];
        $data->success_count = $statscounts['success'] ?? 0;
        $data->error_count = $statscounts['error'] ?? 0;
        $data->info_count = $statscounts['info'] ?? 0;
        $data->warning_count = $statscounts['warning'] ?? 0;
        $data->total_sessions = count($sessions);

        // Filter options.
        $data->courses = $this->get_courses_list();
        $data->users = $this->get_users_list();
        $data->selected_courseid = $this->filters['courseid'] ?? 0;
        $data->selected_userid = $this->filters['userid'] ?? 0;
        $data->selected_loglevel = $this->filters['loglevel'] ?? '';

        return $data;
    }

    /**
     * Get username
     *
     * @param int $userid User ID
     * @return string Full name
     */
    private function get_username($userid) {
        global $DB;
        // Fetch all fields required by fullname() to avoid warnings.
        $user = $DB->get_record(
            'user',
            ['id' => $userid],
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename'
        );
        return $user ? fullname($user) : get_string('unknown', 'block_blc_modules');
    }

    /**
     * Get course name
     *
     * @param int $courseid Course ID
     * @return string Course name
     */
    private function get_coursename($courseid) {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'fullname');
        return $course ? $course->fullname : get_string('unknown', 'block_blc_modules');
    }

    /**
     * Get log level CSS class
     *
     * @param string $level Log level
     * @return string CSS class
     */
    private function get_log_level_class($level) {
        $classes = [
            'info' => 'badge-info',
            'success' => 'badge-success',
            'error' => 'badge-danger',
            'warning' => 'badge-warning',
        ];
        return $classes[$level] ?? 'badge-secondary';
    }

    /**
     * Get log level icon
     *
     * @param string $level Log level
     * @return string Icon class
     */
    private function get_log_level_icon($level) {
        $icons = [
            'info' => 'fa-info-circle',
            'success' => 'fa-check-circle',
            'error' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
        ];
        return $icons[$level] ?? 'fa-circle';
    }

    /**
     * Format parameters for display
     *
     * @param array $params Parameters array
     * @return array Formatted parameters
     */
    private function format_parameters($params) {
        $formatted = [];
        foreach ($params as $key => $value) {
            $item = new stdClass();
            $item->key = $key;
            if (is_array($value)) {
                $item->value = json_encode($value, JSON_PRETTY_PRINT);
            } else {
                $item->value = $value;
            }
            $formatted[] = $item;
        }
        return $formatted;
    }

    /**
     * Format duration in human readable format
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . 's';
        } else if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return $minutes . 'm ' . $secs . 's';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        }
    }

    /**
     * Get courses list for filter
     *
     * @return array Courses
     */
    private function get_courses_list() {
        $logger = new logger();
        return array_values($logger::get_courses_with_logs());
    }

    /**
     * Get users list for filter
     *
     * @return array Users
     */
    private function get_users_list() {
        $logger = new logger();
        return array_values($logger::get_users_with_logs());
    }
}
