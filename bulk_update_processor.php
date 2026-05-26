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
use block_blc_modules\helper\file_helper;

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
        $start_time = microtime(true);
        
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
            $request_start = microtime(true);
            $responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
            $request_time = round(microtime(true) - $request_start, 2);
            add_progress_log("BLC server responded in {$request_time}s", 'success');
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
    
    // Parse response - extract only essential fields for memory efficiency
    // Process incrementally to reduce memory footprint
    $scorms = array();
    $jsondata = json_decode($responses, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($jsondata)) {
        $total_from_server = count($jsondata);
        add_progress_log("Received $total_from_server modules from BLC server, parsing...", 'info');
        
        foreach ($jsondata as $scormdata) {
            if (is_array($scormdata) && isset($scormdata['id'])) {
                // Store only essential fields to minimize memory
                $scorms[$scormdata['id']] = [
                    'id' => (int)$scormdata['id'],
                    'version' => (int)($scormdata['version'] ?? 0),
                    'scormname' => trim($scormdata['scormname'] ?? 'SCORM Module')
                ];
            }
            // Free memory after processing each item
            unset($scormdata);
        }
        // Free original response data
        unset($jsondata);
        
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    $total_blc_modules = count($scorms);
    add_progress_log("Parsed $total_blc_modules modules successfully", 'info');
    
    if (empty($scorms)) {
        add_progress_log('No modules available from BLC server', 'warning');
        update_progress(['complete' => true, 'status' => 'No modules to process']);
        return true;
    }
    
    // OPTIMIZED: Use single SQL query instead of nested loops (N+1 problem fix)
    // Build WHERE IN clause for scorm IDs
    $db_start = microtime(true);
    list($insql, $params) = $DB->get_in_or_equal(array_keys($scorms), SQL_PARAMS_NAMED);
    
    $sql = "SELECT id, cmid, scormid, version, subject, courseid, scormurl
            FROM {block_blc_modules}
            WHERE scormid $insql";
    
    $localmodules = $DB->get_records_sql($sql, $params);
    $db_time = round(microtime(true) - $db_start, 3);
    
    add_progress_log("Database query completed in {$db_time}s (" . count($localmodules) . " local modules)", 'info');
    
    $updatescorm = array();
    
    // Single pass comparison - O(n) instead of O(n²)
    $compare_start = microtime(true);
    foreach ($localmodules as $coursescorm) {
        if (isset($scorms[$coursescorm->scormid])) {
            $blcversion = $scorms[$coursescorm->scormid]['version'];
            
            if ($coursescorm->version < $blcversion) {
                // Get scormname from BLC server data (not from local database)
                $scormname = $scorms[$coursescorm->scormid]['scormname'];
                
                // Store both version AND record ID for update
                $updatescorm[$coursescorm->cmid] = [
                    'version' => $blcversion,
                    'record_id' => $coursescorm->id,
                    'scormname' => $scormname,
                    'courseid' => $coursescorm->courseid,
                    'scormurl' => $coursescorm->scormurl
                ];
            }
        }
    }
    $compare_time = round(microtime(true) - $compare_start, 3);
    add_progress_log("Version comparison completed in {$compare_time}s", 'info');
    
    // Free memory
    unset($scorms);
    unset($localmodules);
    
    $total_available = count($updatescorm);
    
    // BATCH LIMITING: Use optimal batch size based on server capabilities
    $batch_size = file_helper::get_optimal_batch_size();
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

    // Check system resources before starting large file processing
    $resource_warnings = file_helper::check_system_resources();
    if (!empty($resource_warnings)) {
        foreach ($resource_warnings as $warning) {
            add_progress_log("Warning: {$warning}", 'warning');
        }
        // Continue but log warnings
    }
    
    // Process each module in this batch
    $success_count = 0;
    $failed_count = 0;
    $errors = [];
    
    // OPTIMIZATION: Reuse curl instance instead of creating new one each iteration
    $curl = new blccurl();
    $curl->setHeader('Content-Type: application/json; charset=utf-8');
    
    foreach($modules_to_process as $coursemodule => $updateinfo){
        try {
            // Extract update info
            $version = $updateinfo['version'];
            $record_id = $updateinfo['record_id'];
            $scormname = $updateinfo['scormname'];
            $courseid = $updateinfo['courseid'];
            $scormurl = $updateinfo['scormurl'];
            
            // Update current module info with file size estimate
            $fileSizeMB = 'Unknown';
            if (isset($tempscormurl)) {
                // Try to get file size from URL if possible (requires HEAD request)
                $ch = curl_init($tempscormurl);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_exec($ch);
                $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                curl_close($ch);
                if ($size > 0) {
                    $fileSizeMB = round($size / 1024 / 1024, 1) . 'MB';
                }
            }

            update_progress([
                'current_module' => [
                    'name' => $scormname,
                    'cmid' => $coursemodule,
                    'estimated_size' => $fileSizeMB
                ],
                'status' => "Updating: {$scormname} ({$fileSizeMB})"
            ]);
            add_progress_log("Processing: {$scormname} (CM: {$coursemodule}, Size: {$fileSizeMB})", 'info');
            
            // Get temporary URL
            $url = $scormurl;
            $tempurl = urlencode($url);
            $function_name = 'local_scormurl_get_bulkuptempscormurls';
            $serverurl = $domainname.'/webservice/rest/server.php'.'?wstoken='.$token
                .'&wsfunction='.$function_name.'&apikey='.$apikey.'&scormurl='.$tempurl;
            
            add_progress_log("Requesting temp URL for: {$scormname}", 'info');
            
            try {
                $responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
            } catch (Exception $e) {
                throw new Exception('Failed to get temp URL: ' . $e->getMessage());
            }
            
            if (empty($responses)) {
                throw new Exception('Empty response getting temp URL');
            }
            
            // OPTIMIZATION: Extract parsing to separate function for reusability
            $tempscormurl = parse_temp_url_response($responses);
            
            if (empty($tempscormurl)) {
                debugging('Temp URL response: ' . substr($responses, 0, 500), DEBUG_DEVELOPER);
                throw new Exception('Failed to parse temp URL from response');
            }
            
            // Get course module
            $scormcm = $DB->get_record('course_modules', array('id' => $coursemodule));
            if (!$scormcm) {
                throw new Exception('Course module not found');
            }
            
            // Perform actual update with proper resource cleanup
            $result = update_scorm_module($scormcm, $record_id, $courseid, $scormname, $tempscormurl, $version);
            
            if ($result) {
                $success_count++;
                add_progress_log("✓ Successfully updated: {$scormname}", 'success');
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
        
        // OPTIMIZATION: Aggressive memory cleanup after each iteration
        if (isset($updateinfo)) {
            unset($updateinfo);
        }
        if (isset($version)) {
            unset($version);
        }
        if (isset($record_id)) {
            unset($record_id);
        }
        if (isset($scormname)) {
            unset($scormname);
        }
        if (isset($courseid)) {
            unset($courseid);
        }
        if (isset($scormurl)) {
            unset($scormurl);
        }

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // Small delay to allow UI to update and prevent overwhelming the server
        usleep(200000); // 0.2 second
    }
    
    // Cleanup curl instance
    unset($curl);
    
    // Calculate total execution time
    $total_time = round(microtime(true) - $start_time, 2);
    
    // Mark as complete
    $completion_message = "Batch complete: $success_count succeeded";
    if ($failed_count > 0) {
        $completion_message .= ", $failed_count failed";
    }
    if ($remaining_count > 0) {
        $completion_message .= " ($remaining_count modules remaining)";
    }
    $completion_message .= " - Total time: {$total_time}s";
    
    update_progress([
        'complete' => true,
        'status' => $completion_message,
        'current_module' => null,
        'remaining' => $remaining_count,
        'batch_complete' => true,
        'total_time' => $total_time
    ]);
    
    if ($remaining_count > 0) {
        add_progress_log("Batch completed in {$total_time}s: $success_count successful, $failed_count failed. $remaining_count modules still need updating.", 'success');
    } else {
        add_progress_log("All updates completed in {$total_time}s: $success_count successful, $failed_count failed", 'success');
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
 * Parse temporary URL from API response (JSON or XML)
 * 
 * @param string $response API response
 * @return string|null Temporary SCORM URL or null if not found
 */
function parse_temp_url_response($response) {
    // Try JSON first (modern API)
    $jsondata = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($jsondata['tempscormurl'])) {
        return $jsondata['tempscormurl'];
    }
    
    // Fallback to XML parsing (legacy API)
    $xml = simplexml_load_string($response);
    if ($xml !== false) {
        $xml = (array)$xml;
        if (isset($xml['SINGLE'])) {
            $single = (array)$xml['SINGLE'];
            if (isset($single['KEY'])) {
                $keyarray = $single['KEY'];
                foreach ($keyarray as $key) {
                    $key = (array)$key;
                    if (isset($key['@attributes']['name']) && $key['@attributes']['name'] == 'tempscormurl') {
                        return $key['VALUE'];
                    }
                }
            }
        }
    }
    
    return null;
}

/**
 * Update individual SCORM module
 */
/**
 * Update individual SCORM module
 * 
 * @param stdClass $scormcm Course module record
 * @param int $record_id Block BLC modules record ID
 * @param int $courseid Course ID
 * @param string $scormname SCORM name
 * @param string $tempscormurl Temporary URL to download SCORM package
 * @param int $version New version number
 * @return bool Success status
 */
function update_scorm_module($scormcm, $record_id, $courseid, $scormname, $tempscormurl, $version) {
    global $DB, $CFG;
    
    require_once($CFG->libdir . '/filelib.php');
    
    $zipfilepath = null;
    $extractdir = null;
    
    try {
        // Start database transaction for atomicity
        $transaction = $DB->start_delegated_transaction();
        
        // OPTIMIZATION: Download with streaming to reduce memory usage
        $tempdir = make_temp_directory('scormpackage');
        $zipfilepath = $tempdir . '/' . time() . '_' . $scormcm->id . '.zip';

        // Download with streaming to handle large files (102MB+)
        $fp = fopen($zipfilepath, 'w+');
        if (!$fp) {
            throw new \Exception('Failed to create temporary file for download');
        }

        $ch = curl_init($tempscormurl);
        curl_setopt($ch, CURLOPT_FILE, $fp);

        // Apply optimized curl options for large files
        $curl_options = file_helper::get_download_curl_options();
        foreach ($curl_options as $option => $value) {
            curl_setopt($ch, $option, $value);
        }

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            @unlink($zipfilepath); // Clean up failed download
            throw new \Exception("Failed to download SCORM package (HTTP {$httpCode}): {$error}");
        }

        // Verify file was downloaded completely
        if (!file_exists($zipfilepath) || filesize($zipfilepath) === 0) {
            throw new \Exception('Downloaded file is empty or missing');
        }
        
        // Extract and update
        $packer = get_file_packer('application/zip');
        $fs = get_file_storage();
        
        $context = context_course::instance($courseid);
        
        // Delete old files
        $fs->delete_area_files($context->id, 'mod_scorm', 'package', $scormcm->instance);
        
        // Extract new package with progress indication
        $extractdir = $tempdir . '/extract_' . time() . '_' . $scormcm->id;

        // Check available disk space before extraction
        $zipSize = filesize($zipfilepath);
        $availableSpace = disk_free_space($tempdir);
        if ($availableSpace < $zipSize * 3) { // Assume 3x expansion for safety
            throw new \Exception('Insufficient disk space for SCORM extraction. Required: ' . ($zipSize * 3) . ' bytes, Available: ' . $availableSpace . ' bytes');
        }

        $extractResult = $packer->extract_to_pathname($zipfilepath, $extractdir);

        if (!$extractResult) {
            throw new \Exception('Failed to extract SCORM package');
        }

        // Verify extraction was successful
        if (!is_dir($extractdir) || count(scandir($extractdir)) <= 2) {
            throw new \Exception('SCORM package extraction failed - no files extracted');
        }
        
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
        
        if (!$storedfile) {
            throw new \Exception('Failed to store SCORM package in file system');
        }
        
        // Validate scormcm has required fields
        if (empty($scormcm->instance)) {
            throw new \Exception('Course module has no instance ID');
        }
        
        // Update SCORM instance
        $scorm = new \stdClass();
        $scorm->id = $scormcm->instance;
        $scorm->instance = $scormcm->instance;
        $scorm->course = $courseid;
        $scorm->coursemodule = $scormcm->id;
        $scorm->scormtype = 'local';
        $scorm->timemodified = time();
        
        // Set default values to prevent undefined property errors
        $scorm->timeopen = 0;
        $scorm->timeclose = 0;
        $scorm->completionstatusallscos = 0;
        
        if (!scorm_update_instance($scorm)) {
            throw new \Exception('scorm_update_instance failed');
        }
        
        // Update version in block_blc_modules
        $scormrecord = new \stdClass();
        $scormrecord->id = $record_id;
        $scormrecord->version = $version;
        $scormrecord->timemodified = time();
        
        $DB->update_record('block_blc_modules', $scormrecord);
        
        // Commit transaction
        $transaction->allow_commit();
        
        return true;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($transaction) && !$transaction->is_disposed()) {
            $transaction->rollback($e);
        }
        
        debugging('SCORM update error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        throw $e;
        
    } finally {
        // CRITICAL: Always cleanup temporary files with error handling
        if (isset($zipfilepath) && file_exists($zipfilepath)) {
            if (!@unlink($zipfilepath)) {
                debugging('Failed to cleanup temporary zip file: ' . $zipfilepath, DEBUG_DEVELOPER);
            }
        }
        if (isset($extractdir) && is_dir($extractdir)) {
            if (!remove_dir($extractdir)) {
                debugging('Failed to cleanup temporary extract directory: ' . $extractdir, DEBUG_DEVELOPER);
            }
        }

        // Force garbage collection after large file operations
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
}
