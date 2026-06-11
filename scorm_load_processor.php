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
 * SCORM Load Processor - AJAX endpoint for real-time progress updates
 *
 * Uses Moodle's $SESSION global for session data persistence instead of
 * PHP's native $_SESSION, ensuring compatibility with Moodle's custom
 * session handlers.
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_blc_modules\external\blcservice;
use block_blc_modules\logger;

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');
require_once($CFG->dirroot.'/mod/scorm/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once("$CFG->libdir/resourcelib.php");
require_once($CFG->dirroot.'/mod/resource/locallib.php');
require_once($CFG->dirroot.'/mod/resource/lib.php');

require_login(null, false);

global $DB, $USER, $SESSION;

$action = required_param('action', PARAM_ALPHA);
$sesskey = required_param('sesskey', PARAM_RAW);

if (!confirm_sesskey($sesskey)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session key',
    ]);
    exit;
}

// Use Moodle's $SESSION object instead of $_SESSION for reliable persistence.
if (!isset($SESSION->blc_scorm_load_progress)) {
    $SESSION->blc_scorm_load_progress = new stdClass();
    $SESSION->blc_scorm_load_progress->status = 'idle';
    $SESSION->blc_scorm_load_progress->total = 0;
    $SESSION->blc_scorm_load_progress->processed = 0;
    $SESSION->blc_scorm_load_progress->success = 0;
    $SESSION->blc_scorm_load_progress->failed = 0;
    $SESSION->blc_scorm_load_progress->errors = [];
    $SESSION->blc_scorm_load_progress->complete = false;
    $SESSION->blc_scorm_load_progress->current_module = null;
    $SESSION->blc_scorm_load_progress->task_data = null;
    $SESSION->blc_scorm_load_progress->log = [];
}

/**
 * Log a message to both session and database.
 *
 * @param string $message The log message
 * @param string $type The log type/level (info, error, warning, etc.)
 * @param array $extradata Additional data to log (scormname, scormid, cmid, scormurl)
 * @return void
 */
function add_load_log($message, $type = 'info', $extradata = []) {
    global $SESSION;

    // Session-based logging.
    if (!isset($SESSION->blc_scorm_load_progress->log)) {
        $SESSION->blc_scorm_load_progress->log = [];
    }

    $SESSION->blc_scorm_load_progress->log[] = [
        'message' => $message,
        'type' => $type,
        'time' => date('H:i:s'),
    ];

    if (count($SESSION->blc_scorm_load_progress->log) > 50) {
        $SESSION->blc_scorm_load_progress->log = array_slice($SESSION->blc_scorm_load_progress->log, -50);
    }

    // Database logging (additive).
    if (isset($SESSION->blc_scorm_load_progress->task_data)) {
        $task = $SESSION->blc_scorm_load_progress->task_data;

        // Prepare log data.
        $logdata = [
            'courseid' => $task['courseid'],
            'sectionnumber' => $task['sectionnumber'],
            'status' => $SESSION->blc_scorm_load_progress->status ?? 'unknown',
            'log_level' => $type,
            'message' => $message,
        ];

        // Add extra data if provided (scormname, scormid, cmid, scormurl).
        if (!empty($extradata)) {
            foreach (['scormname', 'scormid', 'cmid', 'scormurl'] as $field) {
                if (isset($extradata[$field])) {
                    $logdata[$field] = $extradata[$field];
                }
            }
        }

        // Fallback: Add current module info if available AND not already set.
        if (empty($logdata['scormurl']) && isset($task['current_index']) && isset($task['scormurls'][$task['current_index']])) {
            // Only add if status is processing to avoid adding it during start/complete.
            if (($logdata['status'] ?? '') === 'processing') {
                $logdata['scormurl'] = $task['scormurls'][$task['current_index']];
            }
        }

        // Log to database.
        require_once(__DIR__ . '/classes/logger.php');
        logger::log_scorm_load($logdata);
    }
}

/**
 * Updates the SCORM load progress session data.
 *
 * @param array $data Associative array of progress data to update.
 * @return void
 */
function update_load_progress($data) {
    global $SESSION;

    foreach ($data as $key => $value) {
        $SESSION->blc_scorm_load_progress->$key = $value;
    }
}

/**
 * Retrieves the current SCORM load progress session data as an array.
 *
 * @return array The SCORM load progress data.
 */
function get_load_progress() {
    global $SESSION;

    $progress = $SESSION->blc_scorm_load_progress;

    // Convert stdClass to array for JSON encoding, handling nested objects.
    $result = [
        'status' => $progress->status,
        'total' => $progress->total,
        'processed' => $progress->processed,
        'success' => $progress->success,
        'failed' => $progress->failed,
        'errors' => $progress->errors,
        'complete' => $progress->complete,
        'current_module' => $progress->current_module,
        'task_data' => $progress->task_data,
    ];

    return $result;
}

switch ($action) {
    case 'start':
        try {
            $courseid = required_param('courseid', PARAM_INT);
            $sectionnumber = required_param('sectionnumber', PARAM_INT);
            $apikey = required_param('apikey', PARAM_ALPHANUMEXT);
            $scormurls = required_param('scormurls', PARAM_RAW);
            $visibility = required_param('visibility', PARAM_INT);
            $hidebrowse = required_param('hidebrowse', PARAM_INT);
            $completion = required_param('completion', PARAM_INT);

            $scormurlsarray = json_decode($scormurls, true);
            if (!is_array($scormurlsarray)) {
                throw new moodle_exception('Invalid scormurls format');
            }

            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $coursecontext = context_course::instance($courseid);
            require_capability('moodle/course:manageactivities', $coursecontext);
            require_capability('mod/scorm:addinstance', $coursecontext);

            $SESSION->blc_scorm_load_progress = new stdClass();
            $SESSION->blc_scorm_load_progress->status = 'starting';
            $SESSION->blc_scorm_load_progress->total = count($scormurlsarray);
            $SESSION->blc_scorm_load_progress->processed = 0;
            $SESSION->blc_scorm_load_progress->success = 0;
            $SESSION->blc_scorm_load_progress->failed = 0;
            $SESSION->blc_scorm_load_progress->errors = [];
            $SESSION->blc_scorm_load_progress->log = [];
            $SESSION->blc_scorm_load_progress->complete = false;
            $SESSION->blc_scorm_load_progress->current_module = null;
            $SESSION->blc_scorm_load_progress->task_data = [
                'courseid' => $courseid,
                'sectionnumber' => $sectionnumber,
                'apikey' => $apikey,
                'scormurls' => $scormurlsarray,
                'visibility' => $visibility,
                'hidebrowse' => $hidebrowse,
                'completion' => $completion,
                'current_index' => 0,
            ];

            // Log process start (will be logged to both session and database via add_load_log).
            add_load_log('SCORM load process initialized with ' . count($scormurlsarray) . ' modules', 'info');

            // Store parameters separately in database (one-time, won't duplicate).
            require_once(__DIR__ . '/classes/logger.php');
            logger::log_scorm_load([
                'courseid' => $courseid,
                'sectionnumber' => $sectionnumber,
                'status' => 'started',
                'log_level' => 'info',
                'message' => 'Process parameters',
                'parameters' => [
                    'courseid' => $courseid,
                    'sectionnumber' => $sectionnumber,
                    'apikey' => $apikey, // Will be masked by logger.
                    'total_modules' => count($scormurlsarray),
                    'visibility' => $visibility,
                    'hidebrowse' => $hidebrowse,
                    'completion' => $completion,
                ],
            ]);

            // CRITICAL: Flush session data to storage before the next AJAX call.
            // This ensures the 'process' action can read the task_data immediately.
            session_write_close();

            echo json_encode([
                'success' => true,
                'message' => 'Process started successfully',
                'data' => get_load_progress(),
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
        break;

    case 'process':
        try {
            $progress = get_load_progress();

            if (!isset($progress['task_data']) || $progress['complete']) {
                echo json_encode([
                    'success' => true,
                    'data' => $progress,
                ]);
                exit;
            }

            $task = $progress['task_data'];
            $index = $task['current_index'];

            if ($index >= count($task['scormurls'])) {
                update_load_progress([
                    'complete' => true,
                    'status' => 'completed',
                ]);
                add_load_log('All modules processed successfully', 'success');
                echo json_encode([
                    'success' => true,
                    'data' => get_load_progress(),
                ]);
                exit;
            }

            $url = $task['scormurls'][$index];
            $courseid = $task['courseid'];
            $sectionnumber = $task['sectionnumber'];
            $apikey = $task['apikey'];

            update_load_progress([
                'status' => 'processing',
                'current_module' => ['name' => 'Loading module ' . ($index + 1) . '...'],
            ]);

            $token = get_config('block_blc_modules', 'token');
            $domainname = get_config('block_blc_modules', 'domainname');

            $scormmodule = $DB->get_record('modules', ['name' => 'scorm']);
            $resourcemodule = $DB->get_record('modules', ['name' => 'resource']);

            if (!$scormmodule || !$resourcemodule) {
                throw new moodle_exception('Required modules not found');
            }

            require_once($CFG->dirroot.'/blocks/blc_modules/classes/external/blcservice.php');

            $scormdata = blcservice::fetch_scorm_data($apikey, $url, $token, $domainname);

            if (!$scormdata || !is_array($scormdata) || !isset($scormdata['scormurl']) || !isset($scormdata['scormname'])) {
                throw new moodle_exception('Invalid SCORM data');
            }

            update_load_progress([
                'current_module' => ['name' => $scormdata['scormname']],
            ]);

            $driveid = !empty($scormdata['driveid']) ? $scormdata['driveid'] : null;
            if (!blcservice::validate_scorm_url($scormdata['scormurl'])) {
                throw new moodle_exception('SCORM file not accessible');
            }

            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

            $scormcm = blcservice::create_scorm_module(
                $course,
                $sectionnumber,
                $scormdata,
                $scormmodule->id,
                $task['visibility'],
                $task['hidebrowse'],
                $task['completion']
            );

            $resourcecm = blcservice::create_accessibility_document(
                $course,
                $sectionnumber,
                $scormdata,
                $resourcemodule->id,
                $task['visibility'],
                $apikey,
                $token,
                $domainname,
                $url
            );

            blcservice::record_blc_module($courseid, $sectionnumber, $scormcm, $scormdata, $url);

            if (!empty($scormdata['scormid'])) {
                blcservice::ensure_api_key_mapping($apikey, (int)$scormdata['scormid'], $token, $domainname);
            }

            blcservice::cleanup_temp_files($apikey, $url, $token, $domainname);

            // Re-read progress after all the processing above.
            $progress = get_load_progress();

            update_load_progress([
                'success' => $progress['success'] + 1,
                'processed' => $progress['processed'] + 1,
            ]);

            $task['current_index'] = $index + 1;
            update_load_progress(['task_data' => $task]);

            add_load_log('Successfully loaded: ' . $scormdata['scormname'], 'success', [
                'scormname' => $scormdata['scormname'],
                'scormid' => $scormdata['scormid'] ?? null,
                'cmid' => $scormcm->id ?? null,
                'scormurl' => $url, // Explicitly pass the URL we just processed.
            ]);

            echo json_encode([
                'success' => true,
                'data' => get_load_progress(),
            ]);
        } catch (Exception $e) {
            $progress = get_load_progress();
            $errors = $progress['errors'];
            $errors[] = $e->getMessage();

            $task = $progress['task_data'];
            $task['current_index'] = $task['current_index'] + 1;

            update_load_progress([
                'failed' => $progress['failed'] + 1,
                'processed' => $progress['processed'] + 1,
                'errors' => $errors,
                'task_data' => $task,
            ]);

            add_load_log('Error: ' . $e->getMessage(), 'error');

            echo json_encode([
                'success' => true,
                'data' => get_load_progress(),
            ]);
        }
        break;

    case 'get_progress':
        echo json_encode([
            'success' => true,
            'data' => get_load_progress(),
        ]);
        break;

    case 'reset':
        $SESSION->blc_scorm_load_progress = new stdClass();
        $SESSION->blc_scorm_load_progress->status = 'idle';
        $SESSION->blc_scorm_load_progress->total = 0;
        $SESSION->blc_scorm_load_progress->processed = 0;
        $SESSION->blc_scorm_load_progress->success = 0;
        $SESSION->blc_scorm_load_progress->failed = 0;
        $SESSION->blc_scorm_load_progress->errors = [];
        $SESSION->blc_scorm_load_progress->complete = false;
        $SESSION->blc_scorm_load_progress->current_module = null;
        $SESSION->blc_scorm_load_progress->task_data = null;
        $SESSION->blc_scorm_load_progress->log = [];

        echo json_encode([
            'success' => true,
            'message' => 'Progress reset',
        ]);
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action',
        ]);
        break;
}
