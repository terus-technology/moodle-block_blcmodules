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
 * Bulk Update Processor - AJAX endpoint for real-time progress updates
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__).'/../../config.php');

global $DB, $CFG, $SESSION;

require_once($CFG->dirroot.'/mod/scorm/locallib.php');
require_once($CFG->dirroot.'/mod/scorm/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

use block_blc_modules\helper\debug_helper;
use block_blc_modules\helper\file_helper;
use block_blc_modules\event\bulk_update_started;
use block_blc_modules\event\bulk_update_completed;
use block_blc_modules\event\scorm_module_updated;

require_login(null, false);
require_capability('moodle/site:config', context_system::instance());

$action = required_param('action', PARAM_ALPHA);
$sesskey = required_param('sesskey', PARAM_RAW);

if (!confirm_sesskey($sesskey)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session key',
    ]);
    exit;
}

// Progress data stored in session.
if (!isset($SESSION->bulk_update_progress)) {
    $SESSION->bulk_update_progress = [
        'status' => 'idle',
        'total' => 0,
        'processed' => 0,
        'success' => 0,
        'failed' => 0,
        'errors' => [],
        'log' => [],
        'complete' => false,
        'current_module' => null,
    ];
}

/**
 * Add log entry to progress
 *
 * @param string $message Log message
 * @param string $type Log type (info, success, warning, error)
 */
function add_progress_log($message, $type = 'info') {
    global $SESSION;

    if (!isset($SESSION->bulk_update_progress['log'])) {
        $SESSION->bulk_update_progress['log'] = [];
    }

    $SESSION->bulk_update_progress['log'][] = [
        'message' => $message,
        'type' => $type,
        'time' => date('H:i:s'),
    ];

    // Keep only last 50 log entries.
    if (count($SESSION->bulk_update_progress['log']) > 50) {
        $SESSION->bulk_update_progress['log'] = array_slice($SESSION->bulk_update_progress['log'], -50);
    }
}

/**
 * Update progress data
 *
 * @param array $data Key-value pairs to update in progress
 */
function update_progress($data) {
    global $SESSION;

    foreach ($data as $key => $value) {
        $SESSION->bulk_update_progress[$key] = $value;
    }
}

/**
 * Get current progress
 *
 * @return array Current progress data
 */
function get_progress() {
    global $SESSION;

    $progress = $SESSION->bulk_update_progress;
    // Return only new log entries (implement read marker if needed).
    return $progress;
}

$logger = new debug_helper();

// Handle different actions.
switch ($action) {
    case 'start':
        // Start the bulk update process.
        try {
            // Reset session for fresh start.
            $SESSION->bulk_update_progress = [
                'status' => 'idle',
                'total' => 0,
                'processed' => 0,
                'success' => 0,
                'failed' => 0,
                'errors' => [],
                'log' => [],
                'complete' => false,
                'started' => false,
            ];

            update_progress([
                'status' => 'Starting bulk update...',
                'complete' => false,
                'started' => true,
            ]);
            add_progress_log('Bulk update process initiated', 'success');

            // Return immediately - process will run in background via subsequent calls.
            echo json_encode([
                'success' => true,
                'message' => 'Bulk update started',
            ]);

            // Flush output to browser.
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                ob_end_flush();
                flush();
            }

            // Now run the actual bulk update in background.
            ignore_user_abort(true);
            set_time_limit(1800); // 30 minutes

            // Trigger actual bulk update.
            $result = perform_bulk_update();
        } catch (Exception $e) {
            add_progress_log('Fatal error: ' . $e->getMessage(), 'error');
            update_progress([
                'complete' => true,
                'status' => 'Failed: ' . $e->getMessage(),
            ]);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
            $logger->error(
                'The bulk update process terminated unexpectedly.',
                [
                    'A system error occurred.',
                    'The BLC service could not be reached.',
                    'A database or file operation failed.',
                ],
                [
                    'Review the technical details below.',
                    'Correct any configuration issues.',
                    'Retry the bulk update.',
                    'Contact support if the issue persists.',
                ],
                $e->getMessage()
            );
        }
        break;

    case 'get_progress':
    case 'getprogress':  // Handle minified version (underscore removed by minifier)
        // Return current progress.
        $progress = get_progress();
        echo json_encode([
            'success' => true,
            'data' => $progress,
        ]);
        break;

    default:
        // Log invalid action for debugging.
        $logger->error(
            'BLC bulk_update_processor: An unsupported action was requested.',
            [
                'The request contains an invalid action parameter.',
                'The request URL is incorrect.',
                'A client-side script sent an unexpected value.',
            ],
            [
                'Verify the request URL is correct.',
                'Check that the action parameter matches a supported action.',
                'Refresh the page and try again.',
            ],
            "action={$action}",
            false
        );
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action: ' . $action,
        ]);
}

/**
 * Perform the actual bulk update process
 */
function perform_bulk_update() {
    global $DB, $CFG;

    $logger = new debug_helper();

    try {
        $starttime = microtime(true);

        update_progress(['status' => 'Connecting to BLC server...']);
        add_progress_log('Connecting to BLC server...', 'info');

        $requesturi = $CFG->wwwroot;
        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');
        $apikey = get_config('block_blc_modules', 'api_key');

        // Validate configuration.
        if (empty($token) || empty($domainname) || empty($apikey)) {
            $logger->critical(
                'BLC configuration is incomplete.',
                [
                    'The API token is missing.',
                    'The API key is missing.',
                    'The BLC server domain name is missing.',
                ],
                [
                    'Open the BLC Modules plugin settings.',
                    'Verify the API Token, API Key, and Domain Name values.',
                    'Save the settings and try again.',
                ],
                sprintf(
                    'token=%s, domainname=%s, apikey=%s',
                    empty($token) ? 'missing' : 'set',
                    empty($domainname) ? 'missing' : 'set',
                    empty($apikey) ? 'missing' : 'set'
                )
            );
            throw new Exception('BLC configuration is incomplete. Please check plugin settings.');
        }

        // Get modules from BLC server.
        $functionname = 'local_scormurl_get_bulkupscormurls';
        $serverurl = new moodle_url($domainname . '/webservice/rest/server.php', [
            'wstoken' => $token,
            'wsfunction' => $functionname,
            'apikey' => $apikey,
            'requesturi' => $requesturi,
            'version' => 5,
            'moodlewsrestformat' => 'json',
        ]);

        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader('Content-Type: application/json; charset=utf-8');

        try {
            $requeststart = microtime(true);
            $responses = $curl->post($serverurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);
            $requesttime = round(microtime(true) - $requeststart, 2);
            add_progress_log("BLC server responded in {$requesttime}s", 'success');
        } catch (Exception $e) {
            $logger->error(
                'BLC bulk_update_processor: Unable to connect to the BLC server.',
                [
                    'The BLC server is unavailable.',
                    'Network connectivity has been interrupted.',
                    'The configured domain name is incorrect.',
                    'The API service is temporarily unavailable.',
                ],
                [
                    'Verify the BLC server is online.',
                    'Check the configured domain name.',
                    'Confirm network connectivity.',
                    'Retry the bulk update later.',
                ],
                $e->getMessage()
            );
            add_progress_log('Error connecting to BLC server: ' . $e->getMessage(), 'error');
            update_progress(['complete' => true, 'status' => 'Failed to connect']);
            throw new Exception('Failed to connect to BLC server: ' . $e->getMessage());
        }

        if (empty($responses)) {
            $logger->error(
                'BLC bulk_update_processor: The BLC server returned no data.',
                [
                    'The server encountered an internal error.',
                    'The API request was not processed correctly.',
                    'No module data is available.',
                ],
                [
                    'Verify the BLC service is functioning correctly.',
                    'Check the API configuration.',
                    'Retry the request later.',
                ],
                'Empty HTTP response body received.'
            );
            add_progress_log('Empty response from BLC server', 'error');
            update_progress(['complete' => true, 'status' => 'No data received']);
            throw new Exception('Empty response from BLC server');
        }

        // Parse response - extract only essential fields for memory efficiency
        // Process incrementally to reduce memory footprint.
        $scorms = [];
        $jsondata = json_decode($responses, true);

        // Debug: show raw response.
        add_progress_log("Raw response size: " . strlen($responses) . " bytes", 'info');
        add_progress_log("Raw response (first 500 chars): " . substr($responses, 0, 500), 'info');

        if (json_last_error() === JSON_ERROR_NONE && is_array($jsondata)) {
            $totalfromserver = count($jsondata);
            add_progress_log("Received $totalfromserver modules from BLC server, parsing...", 'info');

            foreach ($jsondata as $scormdata) {
                if (is_array($scormdata) && isset($scormdata['id'])) {
                    // Store only essential fields to minimize memory.
                    $scorms[$scormdata['id']] = [
                        'id' => (int)$scormdata['id'],
                        'version' => (int)($scormdata['version'] ?? 0),
                        'scormname' => trim($scormdata['scormname'] ?? 'SCORM Module'),
                    ];
                }
                // Free memory after processing each item.
                unset($scormdata);
            }

            // Free original response data.
            unset($jsondata);

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $totalblcmodules = count($scorms);
        add_progress_log("Parsed $totalblcmodules modules successfully", 'info');

        if (empty($scorms)) {
            add_progress_log('No modules available from BLC server', 'warning');
            update_progress(['complete' => true, 'status' => 'No modules to process']);
            return true;
        }

        // OPTIMIZED: Use single SQL query instead of nested loops (N+1 problem fix)
        // Build WHERE IN clause for scorm IDs.
        $dbstart = microtime(true);
        [$insql, $params] = $DB->get_in_or_equal(array_keys($scorms), SQL_PARAMS_NAMED);

        $sql = "
            SELECT id, cmid, scormid, version, subject, courseid, scormurl
            FROM {block_blc_modules}
            WHERE scormid $insql
        ";

        $localmodules = $DB->get_records_sql($sql, $params);
        $dbtime = round(microtime(true) - $dbstart, 3);

        add_progress_log("Database query completed in {$dbtime}s (" . count($localmodules) . " local modules)", 'info');

        $updatescorm = [];

        // Single pass comparison - O(n) instead of O(n²).
        $comparestart = microtime(true);
        foreach ($localmodules as $coursescorm) {
            if (isset($scorms[$coursescorm->scormid])) {
                $blcversion = $scorms[$coursescorm->scormid]['version'];

                add_progress_log("comparing local version {$coursescorm->version} with BLC version {$blcversion} for SCORM ID {$coursescorm->scormid}", 'info');
                if ($coursescorm->version < $blcversion) {
                    // Get scormname from BLC server data (not from local database).
                    $scormname = $scorms[$coursescorm->scormid]['scormname'];
                    add_progress_log("Module {$scormname} (CMID: {$coursescorm->cmid}) requires update: local version {$coursescorm->version}, BLC version {$blcversion}", 'info');
                    // Store both version AND record ID for update.
                    $updatescorm[$coursescorm->cmid] = [
                        'version' => $blcversion,
                        'oldversion' => $coursescorm->version,
                        'record_id' => $coursescorm->id,
                        'scormname' => $scormname,
                        'scormid' => $coursescorm->scormid,
                        'courseid' => $coursescorm->courseid,
                        'scormurl' => $coursescorm->scormurl,
                    ];
                }
            }
        }

        add_progress_log("Version comparison completed: " . count($updatescorm) . " modules require updates", 'info');  
        $comparetime = round(microtime(true) - $comparestart, 3);
        add_progress_log("Version comparison completed in {$comparetime}s", 'info');

        // Free memory.
        unset($scorms);
        unset($localmodules);

        $totalavailable = count($updatescorm);

        // BATCH LIMITING: Use optimal batch size based on server capabilities.
        $batchsize = file_helper::get_optimal_batch_size();
        $modulestoprocess = $updatescorm;
        $remainingcount = 0;

        if ($totalavailable > $batchsize) {
            // Limit to first 10 modules.
            $modulestoprocess = array_slice($updatescorm, 0, $batchsize, true);
            $remainingcount = $totalavailable - $batchsize;

            add_progress_log("Processing batch of $batchsize modules (out of $totalavailable total)", 'info');
        }

        $total = count($modulestoprocess);

        update_progress([
            'total' => $total,
            'total_available' => $totalavailable,
            'remaining' => $remainingcount,
            'status' => "Processing $total modules" . ($remainingcount > 0 ? " ($remainingcount more available)" : ""),
        ]);
        add_progress_log("Found $totalavailable modules requiring updates", 'info');

        // Trigger event: Bulk update started.
        $startevent = bulk_update_started::create([
            'context' => \context_system::instance(),
            'objectid' => 0,
            'courseid' => SITEID,
            'other' => [
                'totalavailable' => $totalavailable,
                'batchsize' => $batchsize,
            ],
        ]);
        $startevent->trigger();

        if ($total == 0) {
            update_progress(['complete' => true, 'status' => 'All modules up to date']);
            add_progress_log('No updates needed - all modules are current', 'success');
            return true;
        }

        // Check system resources before starting large file processing.
        $resourcewarnings = file_helper::check_system_resources();
        if (!empty($resourcewarnings)) {
            foreach ($resourcewarnings as $warning) {
                add_progress_log("Warning: {$warning}", 'warning');
            }
            // Continue but log warnings.
        }

        // Process each module in this batch.
        $successcount = 0;
        $failedcount = 0;
        $errors = [];

        // OPTIMIZATION: Reuse curl instance instead of creating new one each iteration.
        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader('Content-Type: application/json; charset=utf-8');

        foreach ($modulestoprocess as $coursemodule => $updateinfo) {
            try {
                // Extract update info.
                $version = $updateinfo['version'];
                $recordid = $updateinfo['record_id'];
                $scormname = $updateinfo['scormname'];
                $courseid = $updateinfo['courseid'];
                $scormurl = $updateinfo['scormurl'];

                // Update current module info with file size estimate.
                $estimatedsize = 'Unknown';
                if (isset($tempscormurl)) {
                    // Try to get file size from URL if possible (requires HEAD request).
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
                        $estimatedsize = round($size / 1024 / 1024, 1) . 'MB';
                    }
                }

                update_progress([
                    'current_module' => [
                        'name' => $scormname,
                        'cmid' => $coursemodule,
                        'estimated_size' => $estimatedsize,
                    ],
                    'status' => "Updating: {$scormname} ({$estimatedsize})",
                ]);
                add_progress_log("Processing: {$scormname} (CM: {$coursemodule}, Size: {$estimatedsize})", 'info');

                // Get temporary URL.
                $url = $scormurl;
                $functionname = 'local_scormurl_get_bulkuptempscormurls';
                // NOTE: Do NOT urlencode() — moodle_url encodes parameters automatically.
                $serverurl = new moodle_url($domainname . '/webservice/rest/server.php', [
                    'wstoken' => $token,
                    'wsfunction' => $functionname,
                    'apikey' => $apikey,
                    'scormurl' => $url,
                ]);

                add_progress_log("Requesting temp URL for: {$scormname}", 'info');

                try {
                    $responses = $curl->post($serverurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);
                } catch (Exception $e) {
                    $logger->error(
                        'BLC bulk_update_processor: Unable to retrieve a temporary download URL for the SCORM package.',
                        [
                            'The BLC service could not generate a temporary URL.',
                            'The SCORM package no longer exists.',
                            'The API request failed.',
                        ],
                        [
                            'Verify the SCORM package exists on the BLC server.',
                            'Check the BLC API configuration.',
                            'Retry the update process.',
                        ],
                        $e->getMessage(),
                        false
                    );
                    throw new Exception('Failed to get temp URL: ' . $e->getMessage());
                }

                if (empty($responses)) {
                    $logger->error(
                        'BLC bulk_update_processor: The BLC server returned an empty temporary URL response.',
                        [
                            'The requested SCORM package could not be located.',
                            'The API service returned incomplete data.',
                            'A server-side error occurred.',
                        ],
                        [
                            'Verify the SCORM package exists.',
                            'Check the BLC server logs.',
                            'Retry the update process.',
                        ],
                        'No response content received.'
                    );
                    throw new Exception('Empty response getting temp URL');
                }

                // OPTIMIZATION: Extract parsing to separate function for reusability.
                $tempscormurl = parse_temp_url_response($responses);

                if (empty($tempscormurl)) {
                    $logger->error(
                        'BLC bulk_update_processor: The temporary download URL could not be extracted from the API response.',
                        [
                            'The API response format has changed.',
                            'The response contains invalid JSON or XML.',
                            'The BLC service returned unexpected data.',
                        ],
                        [
                            'Verify the API response format.',
                            'Check the BLC server logs.',
                            'Review the web service configuration.',
                        ],
                        substr($responses, 0, 500)
                    );
                    throw new Exception('Failed to parse temp URL from response');
                }

                // Get course module.
                $scormcm = $DB->get_record('course_modules', ['id' => $coursemodule]);
                if (!$scormcm) {
                    $logger->error(
                        'BLC bulk_update_processor: The SCORM activity could not be found.',
                        [
                            'The activity was deleted.',
                            'The course module ID is invalid.',
                            'The course has been modified during processing.',
                        ],
                        [
                            'Verify the SCORM activity still exists.',
                            'Confirm the course module ID is valid.',
                            'Retry the bulk update.',
                        ],
                        "coursemodule={$coursemodule}"
                    );
                    throw new Exception('Course module not found');
                }

                // Perform actual update with proper resource cleanup.
                $result = update_scorm_module($scormcm, $recordid, $courseid, $scormname, $tempscormurl, $version);

                if ($result) {
                    $successcount++;
                    add_progress_log("✓ Successfully updated: {$scormname}", 'success');

                    // Trigger event: SCORM module updated.
                    $scormevent = scorm_module_updated::create([
                        'context' => \context_course::instance($courseid),
                        'objectid' => $recordid,
                        'courseid' => $courseid,
                        'other' => [
                            'scormid' => (int) ($updateinfo['scormid'] ?? 0),
                            'scormname' => $scormname,
                            'scormurl' => $scormurl,
                            'oldversion' => (int) ($updateinfo['oldversion'] ?? 0),
                            'newversion' => $version,
                        ],
                    ]);
                    $scormevent->trigger();
                } else {
                    $logger->error(
                        'BLC bulk_update_processor: The SCORM package update could not be completed.',
                        [
                            'The package contents are invalid.',
                            'The update process encountered an internal error.',
                            'Required files could not be stored.',
                        ],
                        [
                            'Verify the SCORM package is valid.',
                            'Review the technical details below.',
                            'Retry the update process.',
                        ],
                        "coursemodule={$coursemodule}"
                    );
                    throw new Exception('Update function returned false');
                }
            } catch (Exception $e) {
                $failedcount++;
                $errormsg = "Module {$coursemodule}: " . $e->getMessage();
                $errors[] = $errormsg;
                add_progress_log("✗ Failed: " . $errormsg, 'error');
                $logger->error(
                    "BLC bulk_update_processor: Failed to update SCORM activity {$coursemodule}.",
                    [
                        'The SCORM package could not be downloaded.',
                        'The activity no longer exists.',
                        'A file storage or database error occurred.',
                    ],
                    [
                        'Review the technical details below.',
                        'Verify the SCORM activity still exists.',
                        'Retry the bulk update process.',
                    ],
                    $e->getMessage()
                );
            }

            // Update progress.
            update_progress([
                'success' => $successcount,
                'failed' => $failedcount,
                'processed' => $successcount + $failedcount,
                'errors' => $errors,
            ]);

            // OPTIMIZATION: Aggressive memory cleanup after each iteration.
            if (isset($updateinfo)) {
                unset($updateinfo);
            }
            if (isset($version)) {
                unset($version);
            }
            if (isset($recordid)) {
                unset($recordid);
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

            // Small delay to allow UI to update and prevent overwhelming the server.
            usleep(200000); // 0.2 second
        }

        // Cleanup curl instance.
        unset($curl);

        // Calculate total execution time.
        $totaltime = round(microtime(true) - $starttime, 2);

        // Mark as complete.
        $completionmessage = "Batch complete: $successcount succeeded";
        if ($failedcount > 0) {
            $completionmessage .= ", $failedcount failed";
        }
        if ($remainingcount > 0) {
            $completionmessage .= " ($remainingcount modules remaining)";
        }
        $completionmessage .= " - Total time: {$totaltime}s";

        update_progress([
            'complete' => true,
            'status' => $completionmessage,
            'current_module' => null,
            'remaining' => $remainingcount,
            'batch_complete' => true,
            'totaltime' => $totaltime,
        ]);

        if ($remainingcount > 0) {
            add_progress_log(
                "Batch completed in {$totaltime}s: $successcount successful, $failedcount failed.
                $remainingcount modules still need updating.",
                'success'
            );
        } else {
            add_progress_log("All updates completed in {$totaltime}s: $successcount successful, $failedcount failed", 'success');
        }

        // Trigger event: Bulk update completed.
        $completeevent = bulk_update_completed::create([
            'context' => \context_system::instance(),
            'objectid' => 0,
            'courseid' => SITEID,
            'other' => [
                'totalmodules' => $successcount + $failedcount,
                'successful' => $successcount,
                'failed' => $failedcount,
                'duration' => $totaltime,
            ],
        ]);
        $completeevent->trigger();

        return true;
    } catch (Exception $e) {
        add_progress_log('Critical error: ' . $e->getMessage(), 'error');
        update_progress([
            'complete' => true,
            'status' => 'Failed: ' . $e->getMessage(),
        ]);
        $logger->critical(
            'The bulk update process terminated unexpectedly.',
            [
                'A system error occurred.',
                'The BLC service could not be reached.',
                'A database or file operation failed.',
            ],
            [
                'Review the technical details below.',
                'Correct any configuration issues.',
                'Retry the bulk update.',
                'Contact support if the issue persists.',
            ],
            $e->getMessage()
        );
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
    // Try JSON first (modern API).
    $jsondata = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($jsondata['tempscormurl'])) {
        return $jsondata['tempscormurl'];
    }

    // Fallback to XML parsing (legacy API).
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
 *
 * @param stdClass $scormcm Course module record
 * @param int $recordid Block BLC modules record ID
 * @param int $courseid Course ID
 * @param string $scormname SCORM name
 * @param string $tempscormurl Temporary URL to download SCORM package
 * @param int $version New version number
 * @return bool Success status
 */
function update_scorm_module($scormcm, $recordid, $courseid, $scormname, $tempscormurl, $version) {
    global $DB, $CFG;

    require_once($CFG->libdir . '/filelib.php');

    $logger = new debug_helper();

    $zipfilepath = null;
    $extractdir = null;

    $transaction = $DB->start_delegated_transaction();

    try {
        // OPTIMIZATION: Download with streaming to reduce memory usage.
        $tempdir = make_temp_directory('scormpackage');
        $zipfilepath = $tempdir . '/' . time() . '_' . $scormcm->id . '.zip';

        // Download with streaming to handle large files (102MB+).
        $fp = fopen($zipfilepath, 'w+');
        if (!$fp) {
            throw new Exception('Failed to create temporary file for download');
        }

        $ch = curl_init($tempscormurl);
        curl_setopt($ch, CURLOPT_FILE, $fp);

        // Apply optimized curl options for large files.
        $curloptions = file_helper::get_download_curl_options();
        foreach ($curloptions as $option => $value) {
            curl_setopt($ch, $option, $value);
        }

        $success = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpcode !== 200) {
            @unlink($zipfilepath); // Clean up failed download.
            throw new Exception("Failed to download SCORM package (HTTP {$httpcode}): {$error}");
        }

        // Verify file was downloaded completely.
        if (!file_exists($zipfilepath) || filesize($zipfilepath) === 0) {
            throw new Exception('Downloaded file is empty or missing');
        }

        // Extract and update.
        $packer = get_file_packer('application/zip');
        $fs = get_file_storage();

        $context = context_course::instance($courseid);

        // Delete old files.
        $fs->delete_area_files($context->id, 'mod_scorm', 'package', $scormcm->instance);

        // Extract new package with progress indication.
        $extractdir = $tempdir . '/extract_' . time() . '_' . $scormcm->id;

        // Check available disk space before extraction.
        $zipsize = filesize($zipfilepath);
        $availablespace = disk_free_space($tempdir);
        if ($availablespace < $zipsize * 3) { // Assume 3x expansion for safety.
            throw new Exception('Insufficient disk space for SCORM extraction. Required: ' .
            ($zipsize * 3) . ' bytes, Available: ' . $availablespace . ' bytes');
        }

        $extractresult = $packer->extract_to_pathname($zipfilepath, $extractdir);

        if (!$extractresult) {
            throw new Exception('Failed to extract SCORM package');
        }

        // Verify extraction was successful.
        if (!is_dir($extractdir) || count(scandir($extractdir)) <= 2) {
            throw new Exception('SCORM package extraction failed - no files extracted');
        }

        // Create file record.
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_scorm',
            'filearea' => 'package',
            'itemid' => $scormcm->instance,
            'filepath' => '/',
            'filename' => basename($zipfilepath),
        ];

        $storedfile = $fs->create_file_from_pathname($filerecord, $zipfilepath);

        if (!$storedfile) {
            throw new Exception('Failed to store SCORM package in file system');
        }

        // Validate scormcm has required fields.
        if (empty($scormcm->instance)) {
            throw new Exception('Course module has no instance ID');
        }

        // Update SCORM instance.
        $scorm = new stdClass();
        $scorm->id = $scormcm->instance;
        $scorm->instance = $scormcm->instance;
        $scorm->course = $courseid;
        $scorm->coursemodule = $scormcm->id;
        $scorm->scormtype = 'local';
        $scorm->timemodified = time();

        // Set default values to prevent undefined property errors.
        $scorm->timeopen = 0;
        $scorm->timeclose = 0;
        $scorm->completionstatusallscos = 0;

        if (!scorm_update_instance($scorm)) {
            throw new Exception('scorm_update_instance failed');
        }

        // Update version in block_blc_modules.
        $scormrecord = new stdClass();
        $scormrecord->id = $recordid;
        $scormrecord->version = $version;
        $scormrecord->timemodified = time();

        $DB->update_record('block_blc_modules', $scormrecord);

        // Commit transaction.
        $transaction->allow_commit();

        return true;
    } catch (Exception $e) {
        // Rollback transaction on error.
        if (isset($transaction) && !$transaction->is_disposed()) {
            $transaction->rollback($e);
        }

        throw $e;
    } finally {
        // CRITICAL: Always cleanup temporary files with error handling.
        if (isset($zipfilepath) && file_exists($zipfilepath)) {
            if (!@unlink($zipfilepath)) {
                $logger->error(
                    'BLC bulk_update_processor: A temporary download file could not be removed.',
                    [
                        'The file is locked by another process.',
                        'The web server does not have delete permissions.',
                    ],
                    [
                        'Verify file permissions on the Moodle temp directory.',
                        'Remove the file manually if necessary.',
                    ],
                    $zipfilepath,
                    false
                );
            }
        }
        if (isset($extractdir) && is_dir($extractdir)) {
            if (!remove_dir($extractdir)) {
                $logger->error(
                    'BLC bulk_update_processor: A temporary extraction directory could not be removed.',
                    [
                        'Files are still in use.',
                        'The web server lacks delete permissions.',
                    ],
                    [
                        'Verify directory permissions.',
                        'Remove the temporary directory manually if necessary.',
                    ],
                    $extractdir,
                    false
                );
            }
        }

        // Force garbage collection after large file operations.
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
}
