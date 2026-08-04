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
 * Class blcservice
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules\external;

defined('MOODLE_INTERNAL') || die();

use block_blc_modules\helper\debug_helper;
use block_blc_modules\helper\file_helper;
use block_blc_modules\middleware\services;
use context_course;
use context_module;
use core\exception\moodle_exception;
use core_external\external_api;
use core_external\external_value;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_php_time_limit;
use Exception;
use moodle_url;
use stdClass;
use Throwable;

require_once($CFG->dirroot.'/mod/scorm/locallib.php');
require_once($CFG->dirroot.'/mod/scorm/lib.php');
require_once($CFG->dirroot.'/course/modlib.php');
require_once($CFG->libdir.'/resourcelib.php');
require_once($CFG->dirroot.'/mod/resource/locallib.php');
require_once($CFG->dirroot.'/mod/resource/lib.php');

/**
 * Class blcservice
 */
class blcservice extends external_api {
    /**
     * Returns description of method parameters for check blc modules URLs.
     *
     * @return external_function_parameters
     */
    public static function get_blc_modules_version_parameters(): external_function_parameters {
        return new external_function_parameters([
            'apikey' => new external_value(PARAM_ALPHANUMEXT, 'API key for authentication'),
            'requesturi' => new external_value(PARAM_URL, 'Request URI for validation'),
            'version' => new external_value(PARAM_INT, 'Version number'),
        ]);
    }

    /**
     * Check if API key and request URI are valid.
     *
     * @param string $apikey API key for authentication
     * @param string $requesturi Request URI for validation
     * @param int $version Version number
     * @return array Validation result
     * @throws moodle_exception
     */
    public static function get_blc_modules_version(string $apikey, string $requesturi, int $version): array {
        global $DB;

        self::validate_parameters(self::get_blc_modules_version_parameters(), [
            'apikey' => $apikey,
            'requesturi' => $requesturi,
            'version' => $version,
        ]);

        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');
        $functionname = 'local_scormurl_get_scormurls';
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
        $responses = $curl->post($serverurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);

        // Improved error handling and validation.
        $scorms = [];
        $jsondata = json_decode($responses, true);

        if (!empty($jsondata) && is_array($jsondata)) {
            foreach ($jsondata as $scormdata) {
                if (is_array($scormdata) && isset($scormdata['id'])) {
                    $scormobject = (object) $scormdata;
                    $scorms[$scormobject->id] = $scormobject;
                }
            }
        }

        $courseid = optional_param('id', '', PARAM_INT);

        $updatescorm = [];
        $coursescorms = $DB->get_records('block_blc_modules', ['courseid' => $courseid]);
        foreach ($coursescorms as $coursescorm) {
            foreach ($scorms as $scorm) {
                if ($coursescorm->scormid == $scorm->id && $coursescorm->version < $scorm->version) {
                    $updatescorm[$coursescorm->cmid] = $scorm->version;
                }
            }
        }
        return $updatescorm;
    }

    /**
     * Returns description of method result value for get_blc_modules.
     *
     * @return external_multiple_structure
     */
    public static function get_blc_modules_version_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'BLC Modules ID'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'sectionid' => new external_value(PARAM_INT, 'Section ID'),
                'cmid' => new external_value(PARAM_INT, 'Course Module ID'),
                'scormid' => new external_value(PARAM_INT, 'SCORM identifier'),
                'scormurl' => new external_value(PARAM_TEXT, 'SCORM package URL'),
                'version' => new external_value(PARAM_INT, 'Version'),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
            ])
        );
    }

    /**
     * Returns description of method parameters for check blc modules scorm URLs.
     *
     * @return external_function_parameters
     */
    public static function get_blc_modules_scormurl_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'apikey' => new external_value(PARAM_ALPHANUMEXT, 'API key for authentication'),
                'requesturi' => new external_value(PARAM_URL, 'Request URI for validation'),
                'version' => new external_value(PARAM_INT, 'Version number'),
                'scormsubject' => new external_value(PARAM_TEXT, 'SCORM subject name'),
            ]
        );
    }

    /**
     * Get SCORM URLs based on API key and request URI.
     *
     * @param string $apikey API key for authentication
     * @param string $requesturi Request URI for validation
     * @param int $version Version number
     * @return array List of SCORM data
     * @throws moodle_exception
     */
    public static function get_blc_modules_scormurl(string $apikey, string $requesturi, int $version, string $scormsubject): array {
        self::validate_parameters(self::get_blc_modules_scormurl_parameters(), [
            'apikey' => $apikey,
            'requesturi' => $requesturi,
            'version' => $version,
            'scormsubject' => $scormsubject,
        ]);

        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');

        $functionname = 'local_scormurl_get_scormurls';
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
        $responses = $curl->post($serverurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);

        // FIX: Decode as array and add error checking.
        $responses = json_decode($responses, true); // Force array instead of stdClass.

        // Add error checking for JSON decode.
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new moodle_exception('jsondecodeerror', 'block_blc_modules', '', json_last_error_msg());
        }

        // Ensure $responses is an array.
        if (!is_array($responses)) {
            $responses = [];
        }

        $scorm = [];
        if (count($responses) > 0) {
            foreach ($responses as $scormdata) {
                // FIX: Ensure $scormdata is an array before accessing its elements.
                if (!is_array($scormdata)) {
                    continue; // Skip non-array items.
                }

                // Check if required keys exist.
                if (!isset($scormdata['subject']) || !isset($scormdata['scormname']) || !isset($scormdata['scormurl'])) {
                    continue; // Skip items without required fields.
                }

                if ($scormdata['subject'] == $scormsubject) {
                    array_push(
                        $scorm,
                        [
                            'scormname' => $scormdata['scormname'],
                            'scormurl' => $scormdata['scormurl'],
                        ]
                    );
                }
            }
        }

        // Sort modules alphabetically by scormname (case-insensitive).
        if (count($scorm) > 0) {
            usort($scorm, function ($a, $b) {
                return strcasecmp($a['scormname'], $b['scormname']);
            });
        }

        return $scorm;
    }

    /**
     * Returns description of method result value for get_scormurls.
     *
     * @return external_multiple_structure
     */
    public static function get_blc_modules_scormurl_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'scormname' => new external_value(PARAM_TEXT, 'SCORM package name', VALUE_OPTIONAL),
                'scormurl' => new external_value(PARAM_TEXT, 'SCORM package URL', VALUE_OPTIONAL),
                'subject' => new external_value(PARAM_TEXT, 'Subject', VALUE_OPTIONAL),
            ])
        );
    }

    /**
     * Returns description of method parameters for check blc modules scorm URLs.
     *
     * @return external_function_parameters
     */
    public static function get_blc_modules_scormsubject_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'apikey' => new external_value(PARAM_ALPHANUMEXT, 'API key for authentication'),
                'requesturi' => new external_value(PARAM_URL, 'Request URI for validation'),
                'version' => new external_value(PARAM_INT, 'Version number'),
            ]
        );
    }

    /**
     * Get SCORM URLs based on API key and request URI.
     *
     * @param string $apikey API key for authentication
     * @param string $requesturi Request URI for validation
     * @param int $version Version number
     * @return array List of SCORM data
     * @throws moodle_exception
     */
    public static function get_blc_modules_scormsubject(string $apikey, string $requesturi, int $version): array {
        self::validate_parameters(self::get_blc_modules_scormsubject_parameters(), [
            'apikey' => $apikey,
            'requesturi' => $requesturi,
            'version' => $version,
        ]);

        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');

        $functionname = 'local_scormurl_get_scormurls';
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
        $responses = $curl->post($serverurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);

        // FIX: Decode as array and add error checking.
        $responses = json_decode($responses, true); // Force array instead of stdClass.

        // Add error checking for JSON decode.
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new moodle_exception('jsondecodeerror', 'block_blc_modules', '', json_last_error_msg());
        }

        // Ensure $responses is an array.
        if (!is_array($responses)) {
            $responses = [];
        }

        $scorms = [];
        if (count($responses) > 0) {
            $counter = 1;
            foreach ($responses as $scorm) {
                // Fix: Use counter instead of $index + 1 to avoid string + int error in PHP 8.1.
                if (is_array($scorm) && isset($scorm['subject'])) {
                    $scorms[$counter] = $scorm['subject'];
                    $counter++;
                }
            }
        }
        $scorms = array_unique($scorms);
        sort($scorms);

        return $scorms;
    }

    /**
     * Returns description of method result value for get_blc_modules_scormsubject.
     *
     * @return external_multiple_structure
     */
    public static function get_blc_modules_scormsubject_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_value(PARAM_RAW_TRIMMED, 'Subject name')
        );
    }

    /**
     * Returns description of method parameters for delete scorm Module.
     *
     * @deprecated This function is no longer used.
     *
     * @return external_function_parameters
     */
    public static function get_blc_modules_scormdelete_parameters(): external_function_parameters {
        return new external_function_parameters([
            'apikey' => new external_value(PARAM_ALPHANUMEXT, 'API key for authentication'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'scormurls' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'SCORM URL')
            ),
        ]);
    }

    /**
     * Delete temporary SCORM URLs and update download tracking.
     *
     * @deprecated This function is no longer used.
     *
     * @param string $apikey API key for authentication
     * @param int $courseid Course ID
     * @param array $scormurls Array of SCORM URLs to delete
     * @return array Response with deletion results
     * @throws moodle_exception
     */
    public static function get_blc_modules_scormdelete(string $apikey, int $courseid, array $scormurls): array {
        $params = self::validate_parameters(self::get_blc_modules_scormdelete_parameters(), [
            'apikey' => $apikey,
            'courseid' => $courseid,
            'scormurls' => $scormurls,
        ]);

        // Use validated params.
        $courseid = $params['courseid'];
        $scormurls = $params['scormurls'];

        // Capability check.
        $context = context_course::instance($courseid);
        require_capability('moodle/course:manageactivities', $context);

        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');

        foreach ($scormurls as $url) {
            $url = str_replace("’", "'", $url);

            sleep(20);

            $functionname = 'local_scormurl_get_deletetempscormurls';
            $serverurl = new moodle_url($domainname . '/webservice/rest/server.php', [
                'wstoken' => $token,
                'wsfunction' => $functionname,
                'apikey' => $apikey,
                'scormurl' => $url,
            ]);

            $curl = new \curl(['ignoresecurity' => true]);
            $curl->setHeader('Content-Type: application/json; charset=utf-8');
            $curl->post($serverurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);
        }

        return ['status' => 'completed', 'message' => 'SCORM deletion process completed'];
    }

    /**
     * Returns description of method result value for get_blc_modules_scormdelete.
     *
     * @deprecated This function is no longer used.
     *
     * @return external_single_structure
     */
    public static function get_blc_modules_scormdelete_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Overall success status'),
            'total' => new external_value(PARAM_INT, 'Total number of URLs processed'),
            'successful' => new external_value(PARAM_INT, 'Number of successful deletions'),
            'failed' => new external_value(PARAM_INT, 'Number of failed deletions'),
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'url' => new external_value(PARAM_TEXT, 'SCORM URL'),
                    'status' => new external_value(PARAM_TEXT, 'Deletion status'),
                    'message' => new external_value(PARAM_TEXT, 'Status message'),
                ])
            ),
        ]);
    }

    /**
     * Returns description of method parameters for loading SCORM modules.
     *
     * @return external_function_parameters
     */
    public static function load_scorm_modules_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnumber' => new external_value(PARAM_INT, 'Section number'),
            'apikey' => new external_value(PARAM_ALPHANUMEXT, 'API key for authentication'),
            'scormurls' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'SCORM URL'),
                'Array of SCORM URLs to load',
                VALUE_DEFAULT,
                []
            ),
            'visibility' => new external_value(PARAM_INT, 'Module visibility'),
            'hidebrowse' => new external_value(PARAM_INT, 'Hide browse button'),
            'completion' => new external_value(PARAM_INT, 'Completion setting'),
        ]);
    }

    /**
     * Load SCORM modules from external URLs into a course.
     *
     * @param int $courseid Course ID
     * @param int $sectionnumber Section number
     * @param string $apikey API key for authentication
     * @param array $scormurls Array of SCORM URLs
     * @param int $visibility Module visibility
     * @param int $hidebrowse Hide browse button
     * @param int $completion Completion setting
     * @return array Results of the loading process
     * @throws moodle_exception
     */
    public static function load_scorm_modules(
        int $courseid,
        int $sectionnumber,
        string $apikey,
        array $scormurls,
        int $visibility = 1,
        int $hidebrowse = 0,
        int $completion = 0
    ): array {
        global $DB;

        core_php_time_limit::raise(1800); // Raise time limit to 30 minutes for large operations.

        $debug = new debug_helper();

        try {
            $params = self::validate_parameters(self::load_scorm_modules_parameters(), [
                'courseid' => $courseid,
                'sectionnumber' => $sectionnumber,
                'apikey' => $apikey,
                'scormurls' => $scormurls,
                'visibility' => $visibility,
                'hidebrowse' => $hidebrowse,
                'completion' => $completion,
            ]);

            // Handle empty scormurls array.
            if (empty($scormurls)) {
                return [
                    'success' => true,
                    'total' => 0,
                    'successful' => 0,
                    'failed' => 0,
                    'messages' => ['No SCORM URLs provided'],
                    'created_modules' => [],
                ];
            }

            // Capability checks.
            try {
                $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
                $coursecontext = context_course::instance($courseid);

                require_capability('moodle/course:manageactivities', $coursecontext);
                require_capability('mod/scorm:addinstance', $coursecontext);
            } catch (Exception $e) {
                $debug->error(
                    'Capability check failed: ' . $e->getMessage(),
                    [
                        'Insufficient permissions or invalid course context',
                    ],
                    [
                        'Ensure the user has moodle/course:manageactivities and mod/scorm:addinstance capabilities',
                    ],
                    $e->getTraceAsString()
                );
                throw $e;
            }

            // Initialize result array.
            $results = [
                'success' => true,
                'total' => count($scormurls),
                'successful' => 0,
                'failed' => 0,
                'messages' => [],
                'created_modules' => [],
            ];

            $token = get_config('block_blc_modules', 'token');
            $domainname = get_config('block_blc_modules', 'domainname');

            $scormmodule = $DB->get_record('modules', ['name' => 'scorm']);
            $resourcemodule = $DB->get_record('modules', ['name' => 'resource']);

            if (!$scormmodule || !$resourcemodule) {
                throw new moodle_exception('invalidmodule', 'block_blc_modules', '', 'SCORM or Resource module not found');
            }

            $count = 0;
            foreach ($scormurls as $url) {
                try {
                    // Call the helper functions (extracted from original load_scorm.php logic).
                    $scormdata = self::fetch_scorm_data($apikey, $url, $token, $domainname);

                    // Validate scormdata is array with required fields.
                    if (!$scormdata || !is_array($scormdata)) {
                        $results['failed']++;
                        $results['messages'][] = "Failed to fetch SCORM data for URL: " . $url;
                        continue;
                    }

                    // Ensure required keys exist.
                    if (!isset($scormdata['scormurl']) || !isset($scormdata['scormname'])) {
                        $results['failed']++;
                        $results['messages'][] = "Invalid SCORM data structure for URL: " . $url;
                        continue;
                    }

                    // Check file availability.
                    // Pass Drive ID if available for proper validation.
                    $driveid = !empty($scormdata['driveid']) ? $scormdata['driveid'] : null;
                    try {
                        if (!self::validate_scorm_url($scormdata['scormurl'])) {
                            $results['failed']++;
                            $results['messages'][] = "SCORM file not accessible: " . $scormdata['scormname'];
                            continue;
                        }
                    } catch (Exception $e) {
                        $debug->error(
                            'BLC Modules: File validation failed for ' . $scormdata['scormname'] . ': ' . $e->getMessage(),
                            ['The SCORM file may be inaccessible, corrupted, or the URL is invalid'],
                            ['Check that the SCORM URL is accessible from the server', 'Verify the file exists on Google Drive'],
                            $e->getTraceAsString()
                        );
                        $results['failed']++;
                        $results['messages'][] = "SCORM file validation failed: " . $scormdata['scormname'] .
                        ' - ' . $e->getMessage();
                        continue;
                    }

                    // Create SCORM module.
                    $scormcm = self::create_scorm_module(
                        $course,
                        $sectionnumber,
                        $scormdata,
                        $scormmodule->id,
                        $visibility,
                        $hidebrowse,
                        $completion
                    );

                    // Create accessibility document if available.
                    $resourcecm = self::create_accessibility_document(
                        $course,
                        $sectionnumber,
                        $scormdata,
                        $resourcemodule->id,
                        $visibility,
                        $apikey,
                        $token,
                        $domainname,
                        $url  // Pass the original URL for lookup.
                    );

                    // Record the creation in block_blc_modules table.
                    self::record_blc_module($courseid, $sectionnumber, $scormcm, $scormdata, $url);

                    // Ensure this SCORM ID is mapped to the API key for future access.
                    if (!empty($scormdata['scormid'])) {
                        self::ensure_api_key_mapping($params['apikey'], (int) $scormdata['scormid'], $token, $domainname);
                    }

                    // Clean up temporary files.
                    self::cleanup_temp_files($apikey, $url, $token, $domainname);

                    $results['successful']++;
                    $results['created_modules'][] = [
                        'cmid' => $scormcm,
                        'name' => $scormdata['scormname'],
                        'type' => 'scorm',
                    ];

                    if ($resourcecm) {
                        $results['created_modules'][] = [
                            'cmid' => $resourcecm,
                            'name' => $scormdata['scormname'] . ' (Accessibility)',
                            'type' => 'resource',
                        ];
                    }
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['messages'][] = "Error processing URL $url: " . $e->getMessage();
                }

                $count++;
            }

            // Update overall success status - allow partial success.
            $results['success'] = ($results['successful'] > 0);

            return $results;
        } catch (Exception $e) {
            $debug = new debug_helper();
            $debug->critical(
                'BLC Modules: CRITICAL ERROR in load_scorm_modules: ' . $e->getMessage(),
                [
                    'An unexpected exception occurred during SCORM module loading process',
                    'This could be due to invalid data, API failure, or database error',
                ],
                [
                    'Check the exception details in the technical details below',
                    'Verify the SCORM URLs and course data are valid',
                    'Check Moodle error logs for more details',
                ],
                'File: ' . $e->getFile() . ' (Line: ' . $e->getLine() . ')' . "\nStack trace:\n" . $e->getTraceAsString()
            );

            // Return error response that JavaScript can handle.
            return [
                'success' => false,
                'total' => count($scormurls),
                'successful' => 0,
                'failed' => count($scormurls),
                'messages' => ['Critical error occurred: ' . $e->getMessage()],
                'created_modules' => [],
            ];
        }
    }

    /**
     * Helper method to fetch SCORM data from external service
     */
    public static function fetch_scorm_data(string $apikey, string $url, string $token, string $domainname): ?array {
        $debug = new debug_helper();

        $functionname = 'local_scormurl_get_tempscormurls';
        $serverurl = new moodle_url($domainname . '/webservice/rest/server.php', [
            'wstoken' => $token,
            'wsfunction' => $functionname,
            'apikey' => $apikey,
            'scormurl' => $url,
            'moodlewsrestformat' => 'json',
        ]);

        // Debug: Log the API request.
        $debug->info('BLC Modules: Calling API: ' . $functionname);
        $debug->info('BLC Modules: Original URL: ' . $url);

        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader('Content-Type: application/json; charset=utf-8');

        // Increase timeout for large file operations.
        $responses = $curl->post($serverurl->out(false), '', [
            'CURLOPT_FAILONERROR' => true,
            'CURLOPT_TIMEOUT' => 300, // 5 minutes timeout
            'CURLOPT_CONNECTTIMEOUT' => 30,
        ]);

        if ($responses === false) {
            $debug->error(
                'BLC Modules: ERROR - cURL request failed completely for URL: ' . $url,
                [
                    'The local_scormurl API server may be down',
                    'Network connectivity issue',
                    'Invalid API endpoint URL',
                ],
                [
                    'Verify the domainname configuration in plugin settings',
                    'Check network connectivity to the API server',
                    'Ensure the local_scormurl web service is enabled',
                ]
            );
            return null;
        }

        // Debug: Log raw response.
        $debug->info('BLC Modules: Raw API Response: ' . substr($responses, 0, 500));

        $jsondata = json_decode($responses, true);

        if (empty($jsondata)) {
            $debug->error(
                'BLC Modules: ERROR - Empty or invalid JSON response from API',
                [
                    'The API returned an empty response or invalid JSON',
                    'The local_scormurl service may have encountered an error',
                ],
                [
                    'Check the API server error logs',
                    'Verify the token and apikey parameters',
                    'Ensure the service function local_scormurl_get_tempscormurls exists',
                ],
                'Response was: ' . $responses
            );
            return null;
        }

        if (!isset($jsondata['scormname'])) {
            $debug->error(
                'BLC Modules: ERROR - Response missing scormname field',
                ['The API response format may have changed', 'The SCORM data may be incomplete'],
                ['Verify the local_scormurl plugin version matches', 'Check the API response structure'],
                'Available fields: ' . implode(', ', array_keys($jsondata)) . "\nFull response: " . json_encode($jsondata)
            );
            return null;
        }

        $scormobject = (object) $jsondata;

        // Debug: Log what we got.
        $debug->info('BLC Modules: SCORM Name: ' . ($scormobject->scormname ?? 'N/A'));
        $debug->info('BLC Modules: Temp SCORM URL (raw): ' . ($scormobject->tempscormurl ?? 'N/A'));

        // Process and validate tempscormurl.
        $tempscormurl = str_replace("ppp", ",", $scormobject->tempscormurl ?? '');

        $debug->info('BLC Modules: Temp SCORM URL (processed): ' . $tempscormurl);

        // CRITICAL: Validate URL is not empty.
        if (empty($tempscormurl)) {
            $debug->error(
                'BLC Modules: ERROR - tempscormurl is empty for SCORM: ' . ($scormobject->scormname ?? 'unknown'),
                [
                    'The local_scormurl plugin failed to create a temporary URL',
                    'The file may not exist or is inaccessible on Google Drive',
                ],
                [
                    'Check if local_scormurl plugin is working correctly',
                    'Verify the original URL: ' . $url,
                    'Check the API server error logs',
                ],
                'Original URL requested: ' . $url
            );
            return null;
        }

        // CRITICAL: Validate URL has a valid filename.
        $urlparts = explode('/', trim($tempscormurl, '/'));
        $urlfilename = end($urlparts);

        $debug->info('BLC Modules: Extracted filename: ' . $urlfilename);

        if (empty($urlfilename)) {
            $debug->error(
                'BLC Modules: ERROR - No filename in URL: ' . $tempscormurl,
                [
                    'The URL returned by local_scormurl has an invalid format with no filename',
                ],
                [
                    'Check the local_scormurl plugin file handling logic',
                    'Verify the source file exists with a proper name on Google Drive',
                ]
            );
            return null;
        }

        // Check if filename has extension (basic validation).
        if (strpos($urlfilename, '.') === false) {
            $debug->warning('BLC Modules: WARNING - Filename has no extension: ' . $urlfilename . ' (URL: ' . $tempscormurl . ')');
            // Continue anyway as some valid files might not have extensions in URL.
        }

        // Prepare SCORM data from API response.
        $result = [
            'scormname' => str_replace("'", "'", $scormobject->scormname ?? ''),
            'scormversion' => $scormobject->version ?? '1',
            'scormid' => $scormobject->id ?? '', // Package ID from API.
            'subject' => $scormobject->subject ?? '',
            'scormurl' => $tempscormurl,
        ];

        $debug->info('BLC Modules: Successfully prepared SCORM data for: ' . $result['scormname']);
        $debug->info('BLC Modules: SCORM package ID: ' . $result['scormid']);
        $debug->info('BLC Modules: Temp SCORM URL: ' . $tempscormurl);

        // Note: URL validation is handled server-side by local_scormurl service.
        // Temp URLs are only created for valid, accessible files.

        return $result;
    }

    /**
     * Helper method to validate SCORM URL availability.
     *
     * Note: With server-side Google Drive integration via local_scormurl,
     * validation is handled server-side. Temp URLs are only created for
     * valid, accessible files. This method now primarily validates format.
     *
     * @param string $scormurl The SCORM URL to validate
     * @return bool True if URL format is valid, false otherwise
     */
    public static function validate_scorm_url(string $scormurl): bool {
        $debug = new debug_helper();

        // Basic URL format validation.
        if (empty($scormurl)) {
            $debug->error(
                'Empty SCORM URL provided',
                [
                    'The SCORM package URL from the API response is empty',
                ],
                [
                    'Check the local_scormurl API response for scormurl field',
                    'Verify the source SCORM package exists',
                ]
            );
            return false;
        }

        $debug->info('Validating SCORM URL format: ' . substr($scormurl, 0, 100));

        // For pluginfile.php URLs from temp_scorm - these are validated server-side.
        if (strpos($scormurl, 'pluginfile.php') !== false && strpos($scormurl, 'temp_scorm') !== false) {
            $debug->info('Temporary pluginfile URL (validated server-side)');
            return true; // Server already validated when creating temp URL.
        }

        // For any other URL, do basic format validation.
        return self::basic_url_validation($scormurl);
    }

    /**
     * Helper method to create SCORM module
     */
    public static function create_scorm_module(
        stdClass $course,
        int $sectionnumber,
        array $scormdata,
        int $moduleid,
        int $visibility,
        int $hidebrowse,
        int $completion
    ): int {
        global $DB, $CFG;

        $debug = new debug_helper();

        $scormsection = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnumber]);

        // Create course module.
        $newcm = new stdClass();
        $newcm->course = $course->id;
        $newcm->module = $moduleid;
        $newcm->section = $scormsection->id;
        $newcm->instance = 0;
        $newcm->visible = $visibility;
        $newcm->visibleold = 1;
        $newcm->visibleoncoursepage = $visibility > 0 ? 1 : 0;
        $newcm->groupmode = 0;
        $newcm->groupingid = 0;
        $newcm->completion = $completion;
        $newcm->availability = null;
        $newcm->showdescription = 0;

        if (!$coursemodule = add_course_module($newcm)) {
            throw new moodle_exception('cannotaddcoursemodule', 'block_blc_modules');
        }

        // Create SCORM instance.
        $scorminstance = new stdClass();
        $scorminstance->course = $course->id;
        $scorminstance->coursemodule = $coursemodule;
        $scorminstance->name = rtrim($scormdata['scormname'], '.zip');
        $scorminstance->section = $sectionnumber;
        $scorminstance->module = $moduleid;
        $scorminstance->modulename = 'scorm';
        $scorminstance->intro = '';
        $scorminstance->introformat = 1;
        $scorminstance->version = 'SCORM_1.2';
        $scorminstance->maxgrade = 100;
        $scorminstance->grademethod = 1;
        $scorminstance->maxattempt = 0;
        $scorminstance->width = 100;
        $scorminstance->height = 500;
        $scorminstance->hidetoc = 3;
        $scorminstance->hidebrowse = $hidebrowse;
        $scorminstance->displaycoursestructure = 0;
        $scorminstance->skipview = 2;
        $scorminstance->packageurl = $scormdata['scormurl'];
        $scorminstance->scormtype = 'localsync';
        $scorminstance->cmidnumber = '';

        // Store the package ID for database reference.
        $scorminstance->blc_package_id = (int)$scormdata['scormid'];

        // Reference field stores the temporary URL from local_scormurl service.
        // The service handles Google Drive downloads server-side.
        $scorminstance->reference = $scormdata['scormurl'];

        // Validate packageurl before proceeding.
        if (empty($scorminstance->packageurl)) {
            throw new moodle_exception(
                'invalidpackageurl',
                'block_blc_modules',
                '',
                'Package URL is empty for SCORM: ' . $scormdata['scormname']
            );
        }

        // Validate URL has a valid filename.
        $urlparts = parse_url($scorminstance->packageurl);
        if (!isset($urlparts['path']) || empty(basename($urlparts['path']))) {
            throw new moodle_exception(
                'invalidpackageurl',
                'block_blc_modules',
                '',
                'Package URL has no filename: ' . $scorminstance->packageurl . ' for SCORM: ' . $scormdata['scormname']
            );
        }

        // Validate filename has extension (basic sanity check).
        $filename = basename($urlparts['path']);
        if (strpos($filename, '.') === false) {
            $debug->warning('Package URL filename has no extension: ' . $filename . ' - this may cause issues', true);
        }

        if ($completion == 2) {
            $scorminstance->completionstatusrequired = 6;
        }

        if ($CFG->branch >= 36) {
            $scorminstance->forcenewattempt = 2;
        }

        $id = services::blcscorm_add_instance($scorminstance);

        // Update course sections.
        $record = new stdClass();
        $record->id = $scormsection->id;
        $record->sequence = !empty($scormsection->sequence)
            ? $scormsection->sequence . "," . $coursemodule
            : $coursemodule;

        $DB->update_record('course_sections', $record);

        // Update SCORM to local type.
        $DB->execute("UPDATE {scorm} SET scormtype = 'local' WHERE id = :id", ['id' => $id]);

        return $coursemodule;
    }

    /**
     * Helper method to record BLC module data
     */
    public static function record_blc_module(
        int $courseid,
        int $sectionnumber,
        int $cmid,
        array $scormdata,
        string $originalurl
    ): void {
        global $DB, $USER;

        $record = new stdClass();
        $record->userid = $USER->id;
        $record->courseid = $courseid;
        $record->sectionid = $sectionnumber;
        $record->cmid = $cmid;
        $record->scormid = $scormdata['scormid'];
        $record->scormurl = $originalurl;
        // Set subject from API.
        $record->subject = $scormdata['subject'] ?? '';
        $record->version = $scormdata['scormversion'];
        $record->timecreated = time();
        $record->timemodified = time();

        $DB->insert_record('block_blc_modules', $record);
    }

    /**
     * Ensure SCORM ID is mapped to API key via local_scormurl API.
     * This allows the SCORM package to be accessible via the API key.
     *
     * @param string $apikey API key
     * @param int $scormid SCORM package ID
     * @param string $token Web service token
     * @param string $domainname BLC domain name
     */
    public static function ensure_api_key_mapping(string $apikey, int $scormid, string $token, string $domainname): void {
        if (empty($scormid) || empty($apikey)) {
            return;
        }

        try {
            $functionname = 'local_scormurl_update_scorm_mapping';
            $serverurl = new moodle_url($domainname . '/webservice/rest/server.php', [
                'wstoken' => $token,
                'wsfunction' => $functionname,
                'apikey' => $apikey,
                'scormid' => $scormid,
                'moodlewsrestformat' => 'json',
            ]);

            $curl = new \curl(['ignoresecurity' => true]);
            $curl->setHeader('Content-Type: application/json; charset=utf-8');
            $curl->post($serverurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);

            $debug = new debug_helper();
            $debug->info('BLC Modules: Updated SCORM mapping via API for SCORM ID ' . $scormid);
        } catch (Throwable $e) {
            $debug = new debug_helper();
            $debug->warning('BLC Modules: Failed to update API key mapping via API: ' . $e->getMessage());
            // Don't throw exception - this is not critical for module creation.
        }
    }

    /**
     * Helper method to create accessibility document
     */
    public static function create_accessibility_document(
        stdClass $course,
        int $sectionnumber,
        array $scormdata,
        int $resourceid,
        int $visibility,
        string $apikey,
        string $token,
        string $domainname,
        string $originalurl  // Original URL from user input.
    ): ?int {
        global $DB, $USER;

        $debug = new debug_helper();
        $debug->info('BLC Modules: Attempting to create accessibility document');
        $debug->info('BLC Modules: Original URL for doc lookup: ' . $originalurl);

        // Try to fetch accessibility document data - use original URL.
        $docdata = self::fetch_accessibility_document($apikey, $originalurl, $token, $domainname);

        if (!$docdata) {
            $debug->warning('BLC Modules: No accessibility document data returned from API');
            return null; // No accessibility document available.
        }

        $debug->info('BLC Modules: Accessibility document data received: ' . json_encode($docdata));

        $scormsection = $DB->get_record('course_sections', [
            'course' => $course->id,
            'section' => $sectionnumber,
        ]);

        // Create course module for resource.
        $newcm = new stdClass();
        $newcm->course = $course->id;
        $newcm->module = $resourceid;
        $newcm->section = $scormsection->id;
        $newcm->instance = 0;
        $newcm->visible = $visibility;
        $newcm->visibleold = 1;
        $newcm->visibleoncoursepage = $visibility > 0 ? 1 : 0;
        $newcm->groupmode = 0;
        $newcm->groupingid = 0;
        $newcm->completion = 0; // No completion for accessibility docs.
        $newcm->availability = null;
        $newcm->showdescription = 0;

        if (!$resourcecoursemodule = add_course_module($newcm)) {
            return null;
        }

        // Create resource instance.
        $resourceinstance = new stdClass();
        $resourceinstance->course = $course->id;
        $resourceinstance->coursemodule = $resourcecoursemodule;
        $resourceinstance->name = rtrim($docdata['docname'], '.docx');
        $resourceinstance->intro = '';
        $resourceinstance->introformat = 1;
        $resourceinstance->completionexpected = 0;

        // Set the display options to the site defaults.
        $config = get_config('resource');
        $resourceinstance->display = $config->display ?? 0;
        $resourceinstance->popupheight = $config->popupheight ?? 620;
        $resourceinstance->popupwidth = $config->popupwidth ?? 685;
        $resourceinstance->printintro = $config->printintro ?? 1;
        $resourceinstance->showsize = $config->showsize ?? 0;
        $resourceinstance->showtype = $config->showtype ?? 0;
        $resourceinstance->showdate = $config->showdate ?? 0;
        $resourceinstance->filterfiles = $config->filterfiles ?? 0;
        $resourceinstance->timemodified = time();

        resource_set_display_options($resourceinstance);
        $id = $DB->insert_record('resource', $resourceinstance);

        // Update course module with instance ID.
        $DB->set_field('course_modules', 'instance', $id, ['id' => $resourcecoursemodule]);

        // Update course section sequence.
        $record = new stdClass();
        $record->id = $scormsection->id;
        $record->sequence = !empty($scormsection->sequence)
            ? $scormsection->sequence . "," . $resourcecoursemodule
            : $resourcecoursemodule;
        $DB->update_record('course_sections', $record);

        // Try to create the file from the document URL.
        try {
            self::create_resource_file($resourcecoursemodule, $docdata);
            $debug->info('BLC Modules: Accessibility document file created successfully');
        } catch (Exception $e) {
            // Continue even if file creation fails.
            $debug->warning('BLC Modules: Failed to create accessibility document file: ' . $e->getMessage());
            $debug->warning("Failed to create accessibility document file: " . $e->getMessage());
        }

        // Record the resource in block_blc_modules_doc table
        // We need to get the blcmoduleid from the SCORM module that was just created
        // Get the most recent blc_modules record for this course.
        $blcmodule = $DB->get_record_sql(
            "SELECT * FROM {block_blc_modules} WHERE courseid = :courseid ORDER BY id DESC",
            ['courseid' => $course->id],
            IGNORE_MULTIPLE
        );

        if ($blcmodule) {
            $resourcerecord = new stdClass();
            $resourcerecord->userid = $USER->id;
            $resourcerecord->courseid = $course->id;
            $resourcerecord->blcmoduleid = $blcmodule->id;
            $resourcerecord->sectionid = $sectionnumber;
            $resourcerecord->cmid = $resourcecoursemodule;
            $resourcerecord->scormid = $docdata['docid'];
            $resourcerecord->scormurl = $docdata['docurl'];
            $resourcerecord->version = $docdata['docversion'];
            $resourcerecord->timecreated = time();
            $resourcerecord->timemodified = time();

            $DB->insert_record('block_blc_modules_doc', $resourcerecord);
            $debug->info('BLC Modules: Resource record saved to block_blc_modules_doc');
        }

        // Cleanup temporary document files on BLC server - use original URL.
        self::cleanup_temp_doc_files($apikey, $originalurl, $token, $domainname);

        return $resourcecoursemodule;
    }

    /**
     * Helper method to fetch accessibility document data
     * Uses API local_scormurl_get_tempdocurls for consistency with SCORM module creation
     */
    private static function fetch_accessibility_document(
        string $apikey,
        string $scormurl,
        string $token,
        string $domainname
    ): ?array {
        $debug = new debug_helper();
        $debug->info('BLC Modules: fetch_accessibility_document called (Using API - Consistent with SCORM)');
        $debug->info('BLC Modules: Original SCORM URL: ' . $scormurl);

        $functionname = 'local_scormurl_get_tempdocurls';
        $serverurl = new moodle_url($domainname . '/webservice/rest/server.php', [
            'wstoken' => $token,
            'wsfunction' => $functionname,
            'apikey' => $apikey,
            'scormurl' => $scormurl,
            'moodlewsrestformat' => 'json',
        ]);

        // Debug: Log the API request.
        $debug->info('BLC Modules: Calling API: ' . $functionname);
        $debug->info('BLC Modules: Original URL: ' . $scormurl);

        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader('Content-Type: application/json; charset=utf-8');
        $responses = $curl->post($serverurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);

        // Debug: Log raw response.
        $debug->info('BLC Modules: Raw API Response: ' . substr($responses, 0, 500));

        $jsondata = json_decode($responses, true);

        if (empty($jsondata)) {
            $debug->error(
                'BLC Modules: ERROR - Empty or invalid JSON response from API',
                [
                    'The API returned an empty response or invalid JSON for document',
                    'The local_scormurl service may have encountered an error',
                ],
                [
                    'Check the API server error logs',
                    'Verify the token and apikey parameters',
                    'Ensure the service function local_scormurl_get_tempdocurls exists',
                ],
                'Response was: ' . $responses
            );
            return null;
        }

        if (!isset($jsondata['docname'])) {
            $debug->error(
                'BLC Modules: ERROR - Response missing docname field',
                [
                    'The API response format may have changed', 'The accessibility document data may be incomplete',
                ],
                [
                    'Verify the local_scormurl plugin version matches', 'Check the API response structure',
                ],
                'Available fields: ' . implode(', ', array_keys($jsondata)) . "\nFull response: " . json_encode($jsondata)
            );
            return null;
        }

        $docobject = (object) $jsondata;

        // Debug: Log what we got.
        $debug->info('BLC Modules: Document Name: ' . ($docobject->docname ?? 'N/A'));
        $debug->info('BLC Modules: Temp Doc URL (raw): ' . ($docobject->tempdocurl ?? 'N/A'));
        $debug->info('BLC Modules: Doc URL Plus (raw): ' . ($docobject->docurlplus ?? 'N/A'));

        // Process and validate tempdocurl.
        $tempdocurl = str_replace("ppp", ",", $docobject->tempdocurl ?? '');

        // Prefer docurlplus (permanent Google Drive URL) for downloading
        // tempdocurl is from temp_doc which may be cross-site and not accessible.
        $downloadurl = !empty($docobject->docurlplus) ? $docobject->docurlplus : $tempdocurl;

        $debug->info('BLC Modules: Temp Doc URL (processed): ' . $tempdocurl);
        $debug->info('BLC Modules: Download URL (selected): ' . $downloadurl);

        // CRITICAL: Validate URL is not empty.
        if (empty($downloadurl)) {
            $debug->error(
                'BLC Modules: ERROR - download URL is empty for Document: ' . ($docobject->docname ?? 'unknown'),
                [
                    'The local_scormurl plugin failed to create a temporary document URL',
                    'The document file may not exist or is inaccessible on Google Drive',
                ],
                [
                    'Check if local_scormurl plugin is working correctly',
                    'Verify the original URL requested: ' . $scormurl,
                    'Check the API server error logs',
                ],
                'Original URL requested: ' . $scormurl
            );
            return null;
        }

        // CRITICAL: Validate URL has a valid filename.
        $urlparts = explode('/', trim($downloadurl, '/'));
        $urlfilename = end($urlparts);

        $debug->info('BLC Modules: Extracted filename: ' . $urlfilename);

        if (empty($urlfilename)) {
            $debug->error(
                'BLC Modules: ERROR - No filename in URL: ' . $downloadurl,
                [
                    'The URL returned by local_scormurl for the document has an invalid format with no filename',
                ],
                [
                    'Check the local_scormurl plugin file handling logic for documents',
                    'Verify the source document file exists with a proper name on Google Drive',
                ]
            );
            return null;
        }

        // Check if filename has extension (basic validation).
        if (strpos($urlfilename, '.') === false) {
            $debug->warning('BLC Modules: WARNING - Filename has no extension: ' . $urlfilename . ' (URL: ' . $downloadurl . ')');
            // Continue anyway as some valid files might not have extensions in URL.
        }

        $result = [
            'docname' => rtrim($docobject->docname ?? '', '.docx'),
            'docversion' => $docobject->version ?? '5',
            'docid' => $docobject->id ?? '',
            'docurl' => $downloadurl, // Use docurlplus if available, fallback to tempdocurl.
        ];

        $debug->info('BLC Modules: Successfully prepared accessibility document data for: ' . $result['docname']);
        $debug->info('BLC Modules: Document ID: ' . $result['docid']);
        $debug->info('BLC Modules: Download URL: ' . $downloadurl);

        return $result;
    }

    /**
     * Helper method to create resource file
     */
    private static function create_resource_file(int $resourcecoursemodule, array $docdata): void {
        global $USER;

        $context = context_module::instance($resourcecoursemodule);
        $fs = get_file_storage();
        $filename = $docdata['docname'] . '.docx';

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_resource',
            'filearea' => 'content',
            'userid' => $USER->id,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ];

        $filepath = $docdata['docurl'];

        $debug = new debug_helper();
        $debug->info('BLC Modules: Creating accessibility document file: ' . $filename);
        $debug->info('BLC Modules: Document URL: ' . $filepath);

        // Check if this is a pluginfile URL and handle it differently.
        if (strpos($filepath, '/pluginfile.php/') !== false) {
            // Use the file helper to create from pluginfile URL.
            $debug->info('BLC Modules: Using pluginfile URL method');
            $file = file_helper::create_file_from_pluginfile_url($fs, $filerecord, $filepath);
            if (!$file) {
                $debug->error(
                    'BLC Modules: ERROR - Failed to create file from pluginfile URL: ' . $filename,
                    [
                        'The pluginfile URL may be invalid or the file is no longer accessible',
                        'The temporary file may have expired',
                    ],
                    [
                        'Check the document URL accessibility',
                        'Verify the local_scormurl temp document is still valid',
                    ]
                );
                throw new moodle_exception('failedtocreatefile', 'block_blc_modules', '', $filename);
            }
        } else {
            // For external URLs, use the enhanced download method.
            $debug->info('BLC Modules: Using external URL method');
            $file = file_helper::create_file_from_external_url($fs, $filerecord, $filepath);
            if (!$file) {
                $debug->error(
                    'BLC Modules: ERROR - Failed to create file from external URL: ' . $filename,
                    [
                        'The external URL may be inaccessible or the download failed',
                        'Network connectivity issue to the file server',
                    ],
                    [
                        'Check the download URL accessibility',
                        'Verify the server can reach the external URL',
                        'Check firewall or proxy settings',
                    ]
                );
                throw new moodle_exception('failedtocreatefile', 'block_blc_modules', '', $filename);
            }
        }

        $debug->info('BLC Modules: Created accessibility document: ' . $filename);
    }

    /**
     * Helper method to clean up temporary SCORM files
     */
    public static function cleanup_temp_files(
        string $apikey,
        string $url,
        string $token,
        string $domainname
    ): void {
        $functionname = 'local_scormurl_get_deletetempscormurls';
        $serverurl = new moodle_url($domainname . '/webservice/rest/server.php', [
            'wstoken' => $token,
            'wsfunction' => $functionname,
            'apikey' => $apikey,
            'scormurl' => $url,
            'moodlewsrestformat' => 'json',
        ]);

        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader('Content-Type: application/json; charset=utf-8');
        $curl->post($serverurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);
    }

    /**
     * Helper method to clean up temporary document files
     */
    private static function cleanup_temp_doc_files(
        string $apikey,
        string $url,
        string $token,
        string $domainname
    ): void {
        $functionname = 'local_scormurl_get_deletetempdocurls';
        $serverurl = new moodle_url($domainname . '/webservice/rest/server.php', [
            'wstoken' => $token,
            'wsfunction' => $functionname,
            'apikey' => $apikey,
            'scormurl' => $url,
            'moodlewsrestformat' => 'json',
        ]);

        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader('Content-Type: application/json; charset=utf-8');
        $curl->post($serverurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);

        $debug = new debug_helper();
        $debug->info('BLC Modules: Temporary document files cleaned up on BLC server');
    }

    /**
     * Basic URL format validation.
     *
     * @param string $url URL to validate
     * @return bool True if URL format is valid
     */
    private static function basic_url_validation(string $url): bool {
        $debug = new debug_helper();

        // Basic validation - check if URL is not empty and has proper format.
        if (empty($url)) {
            $debug->warning('BLC Modules: URL validation failed - empty URL');
            return false;
        }

        // Check if it's a valid URL format.
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $debug->warning('BLC Modules: URL validation failed - invalid URL format: ' . $url);
            return false;
        }

        // For pluginfile URLs, we assume they're valid since they come from our API.
        if (strpos($url, 'pluginfile.php') !== false) {
            return true;
        }

        // For other URLs, do basic checks.
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            $debug->warning('BLC Modules: URL validation failed - missing scheme or host: ' . $url);
            return false;
        }

        // Allow http and https.
        if (!in_array($parsed['scheme'], ['http', 'https'])) {
            $debug->warning('BLC Modules: URL validation failed - unsupported scheme: ' . $parsed['scheme']);
            return false;
        }

        return true;
    }

    /**
     * Returns description of method result value for load_scorm_modules.
     *
     * @return external_single_structure
     */
    public static function load_scorm_modules_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Overall operation success'),
            'total' => new external_value(PARAM_INT, 'Total number of URLs processed'),
            'successful' => new external_value(PARAM_INT, 'Number of successful creations'),
            'failed' => new external_value(PARAM_INT, 'Number of failed creations'),
            'messages' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Status message')
            ),
            'created_modules' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                    'name' => new external_value(PARAM_TEXT, 'Module name'),
                    'type' => new external_value(PARAM_TEXT, 'Module type (scorm/resource)'),
                ])
            ),
        ]);
    }
}
