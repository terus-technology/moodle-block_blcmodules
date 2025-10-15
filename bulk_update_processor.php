<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Bulk Update Processor - AJAX endpoint for real-time progress updates
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

use context_course;
use context_system;
use block_blc_modules\helper\blccurl;

require_login(null, false);
require_capability('moodle/site:config', context_system::instance());

$action = required_param('action', PARAM_ALPHA);
$sesskey = required_param('sesskey', PARAM_RAW);

// Validate sesskey
if (!confirm_sesskey($sesskey)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session key'
    ]);
    exit;
}

// Progress data stored in session
if (!isset($_SESSION['bulk_update_progress'])) {
    $_SESSION['bulk_update_progress'] = [
        'status' => 'idle',
        'total' => 0,
        'processed' => 0,
        'success' => 0,
        'failed' => 0,
        'errors' => [],
        'log' => [],
        'complete' => false,
        'current_module' => null
    ];
}

/**
 * Add log entry to progress
 */
function add_progress_log($message, $type = 'info') {
    if (!isset($_SESSION['bulk_update_progress']['log'])) {
        $_SESSION['bulk_update_progress']['log'] = [];
    }
    $_SESSION['bulk_update_progress']['log'][] = [
        'message' => $message,
        'type' => $type,
        'time' => date('H:i:s')
    ];
    // Keep only last 50 log entries
    if (count($_SESSION['bulk_update_progress']['log']) > 50) {
        $_SESSION['bulk_update_progress']['log'] = array_slice($_SESSION['bulk_update_progress']['log'], -50);
    }
}

/**
 * Update progress data
 */
function update_progress($data) {
    foreach ($data as $key => $value) {
        $_SESSION['bulk_update_progress'][$key] = $value;
    }
}

/**
 * Get current progress
 */
function get_progress() {
    $progress = $_SESSION['bulk_update_progress'];
    // Return only new log entries (implement read marker if needed)
    return $progress;
}

// Handle different actions
switch ($action) {
    case 'start':
        // Start the bulk update process
        try {
            // Reset session for fresh start
            $_SESSION['bulk_update_progress'] = [
                'status' => 'idle',
                'total' => 0,
                'processed' => 0,
                'success' => 0,
                'failed' => 0,
                'errors' => [],
                'log' => [],
                'complete' => false,
                'started' => false
            ];
            
            update_progress([
                'status' => 'Starting bulk update...',
                'complete' => false,
                'started' => true
            ]);
            add_progress_log('Bulk update process initiated', 'success');
            
            // Return immediately - process will run in background via subsequent calls
            echo json_encode([
                'success' => true,
                'message' => 'Bulk update started'
            ]);
            
            // Flush output to browser
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                ob_end_flush();
                flush();
            }
            
            // Now run the actual bulk update in background
            ignore_user_abort(true);
            set_time_limit(1800); // 30 minutes
            
            // Trigger actual bulk update
            $result = perform_bulk_update();
            
        } catch (Exception $e) {
            add_progress_log('Fatal error: ' . $e->getMessage(), 'error');
            update_progress([
                'complete' => true,
                'status' => 'Failed: ' . $e->getMessage()
            ]);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            debugging('Bulk update fatal error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        break;
        
    case 'get_progress':
    case 'getprogress':  // Handle minified version (underscore removed by minifier)
        // Return current progress
        $progress = get_progress();
        echo json_encode([
            'success' => true,
            'data' => $progress
        ]);
        break;
        
    default:
        // Log invalid action for debugging
        debugging('Invalid action received: ' . $action, DEBUG_DEVELOPER);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action: ' . $action
        ]);
}

/**
 * Perform the actual bulk update process
 */
function perform_bulk_update() {
    global $DB, $CFG;
    
    try {
        update_progress(['status' => 'Connecting to BLC server...']);
        add_progress_log('Connecting to BLC server...', 'info');
        
        $requesturi = $CFG->wwwroot;
        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');
        $apikey = get_config('block_blc_modules', 'api_key');
        
        // Validate configuration
        if (empty($token) || empty($domainname) || empty($apikey)) {
            throw new Exception('BLC configuration is incomplete. Please check plugin settings.');
        }
        
        // Get modules from BLC server
        $function_name = 'local_scormurl_get_bulkupscormurls';
        $serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
            . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi. '&version=5&moodlewsrestformat=json';
        
        $curl = new blccurl();
        $curl->setHeader('Content-Type: application/json; charset=utf-8');
        
        try {
            $responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
            add_progress_log('Successfully connected to BLC server', 'success');
        } catch (Exception $e) {
            add_progress_log('Error connecting to BLC server: ' . $e->getMessage(), 'error');
            update_progress(['complete' => true, 'status' => 'Failed to connect']);
            throw new Exception('Failed to connect to BLC server: ' . $e->getMessage());
        }
        
        if (empty($responses)) {
            add_progress_log('Empty response from BLC server', 'error');
            update_progress(['complete' => true, 'status' => 'No data received']);
            throw new Exception('Empty response from BLC server');
        }
    
    // Parse response
    $scorms = array();
    $jsondata = json_decode($responses, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($jsondata)) {
        foreach ($jsondata as $scormdata) {
            if (is_array($scormdata) && isset($scormdata['id'])) {
                $scormobject = new \stdClass();
                $scormobject->id = $scormdata['id'];
                $scormobject->scormname = $scormdata['scormname'] ?? '';
                $scormobject->scormurl = $scormdata['scormurl'] ?? '';
                $scormobject->scormid = $scormdata['scormid'] ?? '';
                $scormobject->version = $scormdata['version'] ?? 0;
                $scorms[$scormobject->id] = $scormobject;
            }
        }
    }
    
    add_progress_log('Retrieved ' . count($scorms) . ' modules from BLC server', 'info');
    
    // Get local modules
    $allcoursescorms = $DB->get_records('block_blc_modules');
    $updatescorm = array();
    
    // Find modules that need updating
    foreach($allcoursescorms as $coursescorm){
        foreach($scorms as $scorm){
            if($coursescorm->scormid == $scorm->id) {
                if($coursescorm->version < $scorm->version){
                    // Store both version AND record ID for update
                    $updatescorm[$coursescorm->cmid] = [
                        'version' => $scorm->version,
                        'record_id' => $coursescorm->id,  // Store the block_blc_modules.id
                        'scormname' => $coursescorm->scormname
                    ];
                }
                break;
            }
        }
    }
    
    $total_available = count($updatescorm);
    
    // BATCH LIMITING: Process maximum 10 modules per run for reliability
    $batch_size = 10;
    $modules_to_process = $updatescorm;
    $remaining_count = 0;
    
    if ($total_available > $batch_size) {
        // Limit to first 10 modules
        $modules_to_process = array_slice($updatescorm, 0, $batch_size, true);
        $remaining_count = $total_available - $batch_size;
        
        add_progress_log("Processing batch of $batch_size modules (out of $total_available total)", 'info');
    }
    
    $total = count($modules_to_process);
    
    update_progress([
        'total' => $total,
        'total_available' => $total_available,
        'remaining' => $remaining_count,
        'status' => "Processing $total modules" . ($remaining_count > 0 ? " ($remaining_count more available)" : "")
    ]);
    add_progress_log("Found $total_available modules requiring updates", 'info');
    
    if ($total == 0) {
        update_progress(['complete' => true, 'status' => 'All modules up to date']);
        add_progress_log('No updates needed - all modules are current', 'success');
        return true;
    }
    
    // Process each module in this batch
    $success_count = 0;
    $failed_count = 0;
    $errors = [];
    
    foreach($modules_to_process as $coursemodule => $updateinfo){
        try {
            // Extract update info
            $version = $updateinfo['version'];
            $record_id = $updateinfo['record_id'];
            
            $coursescorm = $DB->get_record('block_blc_modules', array('cmid' => $coursemodule));
            
            if (!$coursescorm) {
                $failed_count++;
                $errors[] = "Module CM ID {$coursemodule}: Record not found in block_blc_modules";
                update_progress([
                    'failed' => $failed_count,
                    'errors' => $errors
                ]);
                add_progress_log("CM {$coursemodule}: Record not found", 'error');
                continue;
            }
            
            // Validate record has required fields
            if (empty($coursescorm->id)) {
                $failed_count++;
                $errors[] = "Module CM ID {$coursemodule}: BLC module record has no ID (using stored ID: {$record_id})";
                // Use the stored ID as fallback
                $coursescorm->id = $record_id;
                add_progress_log("CM {$coursemodule}: Using stored record ID: {$record_id}", 'warning');
            }
            
            // Update current module info
            update_progress([
                'current_module' => [
                    'name' => $coursescorm->scormname,
                    'cmid' => $coursemodule
                ],
                'status' => "Updating: {$coursescorm->scormname}"
            ]);
            add_progress_log("Processing: {$coursescorm->scormname} (CM: {$coursemodule})", 'info');
            
            // Get temporary URL
            $url = $coursescorm->scormurl;
            $tempurl = urlencode($url);
            $function_name = 'local_scormurl_get_bulkuptempscormurls';
            $serverurl = $domainname.'/webservice/rest/server.php'.'?wstoken='.$token
                .'&wsfunction='.$function_name.'&apikey='.$apikey.'&scormurl='.$tempurl;
            
            add_progress_log("Requesting temp URL for: {$coursescorm->scormname}", 'info');
            
            $curl = new blccurl();
            $curl->setHeader('Content-Type: application/json; charset=utf-8');
            
            try {
                $responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
            } catch (Exception $e) {
                throw new Exception('Failed to get temp URL: ' . $e->getMessage());
            }
            
            if (empty($responses)) {
                throw new Exception('Empty response getting temp URL');
            }
            
            // Parse temp URL response - try JSON first, then XML
            $tempscormurl = null;
            
            // Try JSON first (modern API)
            $jsondata = json_decode($responses, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($jsondata['tempscormurl'])) {
                $tempscormurl = $jsondata['tempscormurl'];
                add_progress_log("Got temp URL from JSON response", 'info');
            } else {
                // Fallback to XML parsing (legacy API)
                $xml = simplexml_load_string($responses);
                if ($xml !== false) {
                    $xml = (array)$xml;
                    if (isset($xml['SINGLE'])) {
                        $single = (array)$xml['SINGLE'];
                        if (isset($single['KEY'])) {
                            $keyarray = $single['KEY'];
                            foreach ($keyarray as $key) {
                                $key = (array)$key;
                                if (isset($key['@attributes']['name']) && $key['@attributes']['name'] == 'tempscormurl') {
                                    $tempscormurl = $key['VALUE'];
                                    add_progress_log("Got temp URL from XML response", 'info');
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            
            if (empty($tempscormurl)) {
                // Log the actual response for debugging
                debugging('Temp URL response: ' . substr($responses, 0, 500), DEBUG_DEVELOPER);
                throw new Exception('Failed to parse temp URL from response (not JSON or XML)');
            }
            
            // Download and update
            $scormcm = $DB->get_record('course_modules', array('id' => $coursemodule));
            if (!$scormcm) {
                throw new Exception('Course module not found');
            }
            
            // Perform actual update
            $result = update_scorm_module($scormcm, $coursescorm, $tempscormurl, $version);
            
            if ($result) {
                $success_count++;
                add_progress_log("✓ Successfully updated: {$coursescorm->scormname}", 'success');
            } else {
                throw new Exception('Update function returned false');
            }
            
        } catch (Exception $e) {
            $failed_count++;
            $error_msg = "Module {$coursemodule}: " . $e->getMessage();
            $errors[] = $error_msg;
            add_progress_log("✗ Failed: " . $error_msg, 'error');
            debugging("BLC Bulk Update Error for CM {$coursemodule}: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
        
        // Update progress
        update_progress([
            'success' => $success_count,
            'failed' => $failed_count,
            'processed' => $success_count + $failed_count,
            'errors' => $errors
        ]);
        
        // Small delay to allow UI to update
        usleep(100000); // 0.1 second
    }
    
    // Mark as complete
    $completion_message = "Batch complete: $success_count succeeded";
    if ($failed_count > 0) {
        $completion_message .= ", $failed_count failed";
    }
    if ($remaining_count > 0) {
        $completion_message .= " ($remaining_count modules remaining)";
    }
    
    update_progress([
        'complete' => true,
        'status' => $completion_message,
        'current_module' => null,
        'remaining' => $remaining_count,
        'batch_complete' => true
    ]);
    
    if ($remaining_count > 0) {
        add_progress_log("Batch completed: $success_count successful, $failed_count failed. $remaining_count modules still need updating.", 'success');
    } else {
        add_progress_log("All updates completed: $success_count successful, $failed_count failed", 'success');
    }
    
    return true;
    
    } catch (Exception $e) {
        // Catch any top-level exceptions
        add_progress_log('Critical error: ' . $e->getMessage(), 'error');
        update_progress([
            'complete' => true,
            'status' => 'Failed: ' . $e->getMessage()
        ]);
        debugging('Bulk update critical error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Update individual SCORM module
 */
function update_scorm_module($scormcm, $coursescorm, $tempscormurl, $version) {
    global $DB, $CFG;
    
    require_once($CFG->libdir . '/filelib.php');
    
    // Debug: Log what we received
    debugging("update_scorm_module called with coursescorm->id: " . 
        (isset($coursescorm->id) ? $coursescorm->id : 'MISSING'), DEBUG_DEVELOPER);
    
    // Validate coursescorm has ID
    if (empty($coursescorm->id)) {
        debugging("ERROR: coursescorm->id is empty! Object: " . print_r($coursescorm, true), DEBUG_DEVELOPER);
        throw new \Exception('BLC module record missing ID field');
    }
    
    // Download package
    $tempdir = make_temp_directory('scormpackage');
    $zipfilepath = $tempdir . '/' . time() . '.zip';
    
    $ch = curl_init($tempscormurl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $fileContents = curl_exec($ch);
    curl_close($ch);
    
    if ($fileContents === false) {
        return false;
    }
    
    file_put_contents($zipfilepath, $fileContents);
    
    // Extract and update
    $packer = get_file_packer('application/zip');
    $fs = get_file_storage();
    
    $context = context_course::instance($coursescorm->courseid);
    
    // Delete old files
    $fs->delete_area_files($context->id, 'mod_scorm', 'package', $scormcm->instance);
    
    // Extract new package
    $extractdir = $tempdir . '/extract_' . time();
    $packer->extract_to_pathname($zipfilepath, $extractdir);
    
    // Create file record
    $filerecord = array(
        'contextid' => $context->id,
        'component' => 'mod_scorm',
        'filearea' => 'package',
        'itemid' => $scormcm->instance,
        'filepath' => '/',
        'filename' => basename($zipfilepath)
    );
    
    $storedfile = $fs->create_file_from_pathname($filerecord, $zipfilepath);
    
    // Validate scormcm has required fields
    if (empty($scormcm->instance)) {
        debugging('ERROR: scormcm->instance is empty! Object: ' . print_r($scormcm, true), DEBUG_DEVELOPER);
        throw new \Exception('Course module has no instance ID');
    }
    
    // Update SCORM instance
    $scorm = new \stdClass();
    $scorm->id = $scormcm->instance;
    $scorm->instance = $scormcm->instance;  // IMPORTANT: scorm_update_instance() uses this!
    $scorm->course = $coursescorm->courseid;
    $scorm->coursemodule = $scormcm->id;
    $scorm->scormtype = 'local';
    $scorm->timemodified = time();
    
    // Set default values to prevent undefined property errors
    $scorm->timeopen = 0;
    $scorm->timeclose = 0;
    $scorm->completionstatusallscos = 0;
    
    debugging("Calling scorm_update_instance with scorm->id: {$scorm->id}, instance: {$scorm->instance}", DEBUG_DEVELOPER);
    
    if (scorm_update_instance($scorm)) {
        // Update version in block_blc_modules
        if (empty($coursescorm->id)) {
            debugging('BLC Module record ID is empty for CM: ' . $scormcm->id, DEBUG_DEVELOPER);
            throw new \Exception('BLC Module record ID is missing');
        }
        
        $scormrecord = new \stdClass();
        $scormrecord->id = $coursescorm->id;
        $scormrecord->version = $version;
        $scormrecord->timemodified = time();
        
        $DB->update_record('block_blc_modules', $scormrecord);
        
        // Cleanup
        @unlink($zipfilepath);
        if (is_dir($extractdir)) {
            remove_dir($extractdir);
        }
        
        return true;
    }
    
    return false;
}
