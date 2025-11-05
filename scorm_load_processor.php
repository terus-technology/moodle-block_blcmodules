<?php
// This file is part of Moodle - http://moodle.org/

/**
 * SCORM Load Processor - AJAX endpoint for real-time progress updates
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');
require_once($CFG->dirroot.'/mod/scorm/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once("$CFG->libdir/resourcelib.php");
require_once($CFG->dirroot.'/mod/resource/locallib.php');
require_once($CFG->dirroot.'/mod/resource/lib.php');

require_login(null, false);

$action = required_param('action', PARAM_ALPHA);
$sesskey = required_param('sesskey', PARAM_RAW);

if (!confirm_sesskey($sesskey)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session key'
    ]);
    exit;
}

if (!isset($_SESSION['scorm_load_progress'])) {
    $_SESSION['scorm_load_progress'] = [
        'status' => 'idle',
        'total' => 0,
        'processed' => 0,
        'success' => 0,
        'failed' => 0,
        'errors' => [],
        'complete' => false,
        'current_module' => null,
        'task_data' => null
    ];
}

function add_load_log($message, $type = 'info') {
    if (!isset($_SESSION['scorm_load_progress']['log'])) {
        $_SESSION['scorm_load_progress']['log'] = [];
    }
    $_SESSION['scorm_load_progress']['log'][] = [
        'message' => $message,
        'type' => $type,
        'time' => date('H:i:s')
    ];
    if (count($_SESSION['scorm_load_progress']['log']) > 50) {
        $_SESSION['scorm_load_progress']['log'] = array_slice($_SESSION['scorm_load_progress']['log'], -50);
    }
}

function update_load_progress($data) {
    foreach ($data as $key => $value) {
        $_SESSION['scorm_load_progress'][$key] = $value;
    }
}

function get_load_progress() {
    return $_SESSION['scorm_load_progress'];
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

            $scormurls_array = json_decode($scormurls, true);
            if (!is_array($scormurls_array)) {
                throw new moodle_exception('Invalid scormurls format');
            }

            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $coursecontext = \context_course::instance($courseid);
            require_capability('moodle/course:manageactivities', $coursecontext);
            require_capability('mod/scorm:addinstance', $coursecontext);

            $_SESSION['scorm_load_progress'] = [
                'status' => 'starting',
                'total' => count($scormurls_array),
                'processed' => 0,
                'success' => 0,
                'failed' => 0,
                'errors' => [],
                'log' => [],
                'complete' => false,
                'current_module' => null,
                'task_data' => [
                    'courseid' => $courseid,
                    'sectionnumber' => $sectionnumber,
                    'apikey' => $apikey,
                    'scormurls' => $scormurls_array,
                    'visibility' => $visibility,
                    'hidebrowse' => $hidebrowse,
                    'completion' => $completion,
                    'current_index' => 0
                ]
            ];

            add_load_log('SCORM load process initialized with ' . count($scormurls_array) . ' modules', 'info');

            echo json_encode([
                'success' => true,
                'message' => 'Process started successfully',
                'data' => get_load_progress()
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        break;

    case 'process':
        try {
            $progress = $_SESSION['scorm_load_progress'];
            
            if (!isset($progress['task_data']) || $progress['complete']) {
                echo json_encode([
                    'success' => true,
                    'data' => $progress
                ]);
                exit;
            }

            $task = $progress['task_data'];
            $index = $task['current_index'];
            
            if ($index >= count($task['scormurls'])) {
                update_load_progress([
                    'complete' => true,
                    'status' => 'completed'
                ]);
                add_load_log('All modules processed successfully', 'success');
                echo json_encode([
                    'success' => true,
                    'data' => get_load_progress()
                ]);
                exit;
            }

            $url = $task['scormurls'][$index];
            $courseid = $task['courseid'];
            $sectionnumber = $task['sectionnumber'];
            $apikey = $task['apikey'];
            
            update_load_progress([
                'status' => 'processing',
                'current_module' => ['name' => 'Loading module ' . ($index + 1) . '...']
            ]);

            $token = get_config('block_blc_modules', 'token');
            $domainname = get_config('block_blc_modules', 'domainname');
            
            $scormmodule = $DB->get_record('modules', ['name' => 'scorm']);
            $resourcemodule = $DB->get_record('modules', ['name' => 'resource']);
            
            if (!$scormmodule || !$resourcemodule) {
                throw new moodle_exception('Required modules not found');
            }

            require_once($CFG->dirroot.'/blocks/blc_modules/classes/external/blcservice.php');
            $service = new \block_blc_modules\external\blcservice();
            
            $scormdata = \block_blc_modules\external\blcservice::fetch_scorm_data($apikey, $url, $token, $domainname);
            
            if (!$scormdata || !is_array($scormdata) || !isset($scormdata['scormurl']) || !isset($scormdata['scormname'])) {
                throw new moodle_exception('Invalid SCORM data');
            }

            update_load_progress([
                'current_module' => ['name' => $scormdata['scormname']]
            ]);

            $driveid = !empty($scormdata['driveid']) ? $scormdata['driveid'] : null;
            if (!\block_blc_modules\external\blcservice::validate_scorm_url($scormdata['scormurl'], $driveid)) {
                throw new moodle_exception('SCORM file not accessible');
            }

            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            
            $scormcm = \block_blc_modules\external\blcservice::create_scorm_module(
                $course,
                $sectionnumber,
                $scormdata,
                $scormmodule->id,
                $task['visibility'],
                $task['hidebrowse'],
                $task['completion']
            );

            $resourcecm = \block_blc_modules\external\blcservice::create_accessibility_document(
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

            \block_blc_modules\external\blcservice::record_blc_module($courseid, $sectionnumber, $scormcm, $scormdata, $url);

            if (!empty($scormdata['scormid'])) {
                \block_blc_modules\external\blcservice::ensure_api_key_mapping($apikey, (int)$scormdata['scormid']);
            }

            \block_blc_modules\external\blcservice::cleanup_temp_files($apikey, $url, $token, $domainname);

            update_load_progress([
                'success' => $progress['success'] + 1,
                'processed' => $progress['processed'] + 1
            ]);

            $task['current_index'] = $index + 1;
            update_load_progress(['task_data' => $task]);

            add_load_log('Successfully loaded: ' . $scormdata['scormname'], 'success');

            echo json_encode([
                'success' => true,
                'data' => get_load_progress()
            ]);

        } catch (Exception $e) {
            $progress = $_SESSION['scorm_load_progress'];
            $errors = $progress['errors'];
            $errors[] = $e->getMessage();
            
            $task = $progress['task_data'];
            $task['current_index'] = $task['current_index'] + 1;
            
            update_load_progress([
                'failed' => $progress['failed'] + 1,
                'processed' => $progress['processed'] + 1,
                'errors' => $errors,
                'task_data' => $task
            ]);

            add_load_log('Error: ' . $e->getMessage(), 'error');

            echo json_encode([
                'success' => true,
                'data' => get_load_progress()
            ]);
        }
        break;

    case 'get_progress':
        echo json_encode([
            'success' => true,
            'data' => get_load_progress()
        ]);
        break;

    case 'reset':
        $_SESSION['scorm_load_progress'] = [
            'status' => 'idle',
            'total' => 0,
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'complete' => false,
            'current_module' => null,
            'task_data' => null
        ];
        echo json_encode([
            'success' => true,
            'message' => 'Progress reset'
        ]);
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
}
