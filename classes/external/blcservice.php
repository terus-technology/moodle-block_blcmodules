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

namespace block_blc_modules\external;

use core_external\external_api;
use core\exception\moodle_exception;
use core_external\external_value;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use block_blc_modules\helper\blccurl;

/**
 * Class blcservice
**/
defined('VALUE_OPTIONAL') || define('VALUE_OPTIONAL', 2);

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');
require_once($CFG->dirroot.'/mod/scorm/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once("$CFG->libdir/resourcelib.php");
require_once($CFG->dirroot.'/mod/resource/locallib.php');
require_once($CFG->dirroot.'/mod/resource/lib.php');
// Removed unnecessary require_once for exceptionlib.php

/**
 * Class blcservice
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology <ali@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blcservice extends external_api{

 /**
     * Returns description of method parameters for check blc modules URLs.
     *
     * @return external_function_parameters
     */
    
    public static function get_blc_modules_version_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'apikey' => new external_value(PARAM_ALPHANUMEXT, 'API key for authentication'),
                'requesturi' => new external_value(PARAM_URL, 'Request URI for validation' ),
                'version' => new external_value(PARAM_INT, 'Version number' ),
            ]
        );
    }

        /**
     * Check if API key and request URI are valid.
     *
     * @param string $apikey API key for authentication
     * @param string $requesturi Request URI for validation
     * @return string Validation result
     * @throws moodle_exception
     */
    public static function get_blc_modules_version (string $apikey, string $requesturi, int $version): array {
        global $CFG, $DB, $USERS;
        
        $courseid = optional_param('id', '', PARAM_INT);
        $params = self::validate_parameters(self::get_blc_modules_version_parameters(), [
            'apikey' => $apikey,
            'requesturi' => $requesturi,
            'version' => $version,
        ]);

        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');
        $function_name = 'local_scormurl_get_scormurls';
        $serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
        . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi. '&version=5&moodlewsrestformat=json';
        $curl = new blccurl;
        $curl->setHeader('Content-Type: application/json; charset=utf-8');

        $responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));
        //print_r($responses);
        $scorms = array();
        $jsondata = json_decode($responses, true);
        
        if(!empty($jsondata) && is_array($jsondata)){
            foreach($jsondata as $scormdata){
                $scormobject = (object) $scormdata;
                $scorms[$scormobject->id] = $scormobject;
            }
        }
        $updatescorm = array();
        $coursescorms = $DB->get_records('block_blc_modules',array('courseid'=>$courseid));
        foreach($coursescorms as $coursescorm){
            foreach($scorms as $scorm){
                if($coursescorm->scormid == $scorm->id && $coursescorm->version < $scorm->version){
                    $updatescorm[$coursescorm->cmid]=$scorm->version;
                    
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
                'courseid' => new external_value(PARAM_INT,'Course ID'),
                'sectionid' => new external_value(PARAM_INT,'Section ID'),
                'cmid' => new external_value(PARAM_INT,'Course Module ID'),
                'scormid' => new external_value(PARAM_INT,'SCORM identifier'),
                'scormurl' => new external_value(PARAM_URL, 'SCORM package URL'),
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
                'requesturi' => new external_value(PARAM_URL, 'Request URI for validation' ),
                'version' => new external_value(PARAM_INT, 'Version number' ),
                'scormsubject' => new external_value(PARAM_TEXT, 'SCORM subject name')
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
        global $DB;
        // $selectsubject = optional_param('subject', '', PARAM_TEXT);
        // Parameter validation.
        $params = self::validate_parameters(self::get_blc_modules_scormurl_parameters(), [
            'apikey' => $apikey,
            'requesturi' => $requesturi,
            'version' => $version,
            'scormsubject' => $scormsubject,
        ]);

        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');

        $function_name = 'local_scormurl_get_scormurls';
        $serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
            . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi. '&version=5&moodlewsrestformat=json';
        $curl = new blccurl;
        $curl->setHeader('Content-Type: application/json; charset=utf-8');
        
        $responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));

        $responses = json_decode($responses);
        $responses = array_map(fn($item) => (array)$item, $responses);

        $scorm = array();
        if(count($responses) > 0){
            foreach($responses as $item => $scormdata) {
                
                if($scormdata['subject'] == $scormsubject) {

                    array_push($scorm,[
                        'scormname' => $scormdata['scormname'],
                        'scormurl' => $scormdata['scormurl']
                    ]);
                }
            }
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
                'scormurl' => new external_value(PARAM_URL, 'SCORM package URL', VALUE_OPTIONAL),
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
                'requesturi' => new external_value(PARAM_URL, 'Request URI for validation' ),
                'version' => new external_value(PARAM_INT, 'Version number' ),
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
        global $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::get_blc_modules_scormsubject_parameters(), [
            'apikey' => $apikey,
            'requesturi' => $requesturi,
            'version' => $version,
        ]);

        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');

        $function_name = 'local_scormurl_get_scormurls';
        $serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
        . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi. '&version=5&moodlewsrestformat=json';
        $curl = new blccurl;
        $curl->setHeader('Content-Type: application/json; charset=utf-8');

        $responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
        $responses = json_decode($responses);
        $responses = array_map(fn($item) => (array)$item, $responses);

        if(count($responses) > 0){
           foreach($responses as $index => $scorm) {
                $scorms[$index + 1] = $scorm['subject'];
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
     * @param string $apikey API key for authentication
     * @param int $courseid Course ID
     * @param array $scormurls Array of SCORM URLs to delete
     * @return array Response with deletion results
     * @throws moodle_exception
     */
    public static function get_blc_modules_scormdelete(string $apikey, int $courseid, array $scormurls): array {

    $courseid = optional_param('id', '', PARAM_INT);
    $section = optional_param('sectionNumber', '', PARAM_INT);
    $apikey = optional_param('apikey', '', PARAM_TEXT);
    $scormurls = optional_param_array('scormurls', '', PARAM_TEXT);
    $visibility = optional_param('visibility', '', PARAM_INT);
    $hidebrowse = optional_param('hidebrowse', '', PARAM_INT);
    $completion = optional_param('completion', '', PARAM_INT);
    $completion = intval($completion);

    global $DB, $USER, $CFG;

    if (!is_array($scormurls)) {
        $scormurls = explode(",", $scormurls);
    }

    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

    $scormmodule = $DB->get_record('modules', array('name' => 'scorm'));
    $moduleid = $scormmodule->id;
    $resourcemodule = $DB->get_record('modules', array('name' => 'resource'));
    $resourceid = $resourcemodule->id;
    $token = get_config('block_blc_modules', 'token');
    $domainname = get_config('block_blc_modules', 'domainname');

    foreach($scormurls as $url){

        //$url = str_replace("qqq",",",$url);
        $url = str_replace("’","'", $url);
        $tempurl = urlencode($url);

                sleep(20);

            
            $function_name = 'local_scormurl_get_deletetempscormurls';
            $serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
                . '&wsfunction='.$function_name . '&apikey='.$apikey. '&scormurl='.$tempurl;
            $curl = new blccurl;
            $curl->setHeader('Content-Type: application/json; charset=utf-8');


            $responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));
        }	

        return ['status' => 'completed', 'message' => 'SCORM deletion process completed'];
    }

    /**
     * Returns description of method result value for get_blc_modules_scormdelete.
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
        global $DB, $USER, $CFG;

        // Parameter validation
        $params = self::validate_parameters(self::load_scorm_modules_parameters(), [
            'courseid' => $courseid,
            'sectionnumber' => $sectionnumber,
            'apikey' => $apikey,
            'scormurls' => $scormurls,
            'visibility' => $visibility,
            'hidebrowse' => $hidebrowse,
            'completion' => $completion,
        ]);

        // Handle empty scormurls array
        if (empty($scormurls)) {
            return [
                'success' => true,
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'messages' => ['No SCORM URLs provided'],
                'created_modules' => []
            ];
        }

        // Capability checks
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($courseid);
        
        require_capability('moodle/course:manageactivities', $coursecontext);
        require_capability('mod/scorm:addinstance', $coursecontext);

        // Initialize result array
        $results = [
            'success' => true,
            'total' => count($scormurls),
            'successful' => 0,
            'failed' => 0,
            'messages' => [],
            'created_modules' => []
        ];

        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');
        
        $scormmodule = $DB->get_record('modules', ['name' => 'scorm']);
        $resourcemodule = $DB->get_record('modules', ['name' => 'resource']);
        
        if (!$scormmodule || !$resourcemodule) {
            throw new \moodle_exception('invalidmodule', 'error', '', 'SCORM or Resource module not found');
        }

        $count = 0;
        foreach ($scormurls as $url) {
            try {
                // Call the helper functions (extracted from original load_scorm.php logic)
                $scormdata = self::fetch_scorm_data($apikey, $url, $token, $domainname);
                
                if (!$scormdata) {
                    $results['failed']++;
                    $results['messages'][] = "Failed to fetch SCORM data for URL: " . $url;
                    continue;
                }

                // Check file availability
                if (!self::validate_scorm_url($scormdata['scormurl'])) {
                    $results['failed']++;
                    $results['messages'][] = "SCORM file not accessible: " . $scormdata['scormname'];
                    continue;
                }

                // Create SCORM module
                $scormcm = self::create_scorm_module(
                    $course,
                    $sectionnumber,
                    $scormdata,
                    $scormmodule->id,
                    $visibility,
                    $hidebrowse,
                    $completion
                );

                // Create accessibility document if available
                $resourcecm = self::create_accessibility_document(
                    $course,
                    $sectionnumber,
                    $scormdata,
                    $resourcemodule->id,
                    $visibility,
                    $apikey,
                    $token,
                    $domainname
                );

                // Record the creation in block_blc_modules table
                self::record_blc_module($courseid, $sectionnumber, $scormcm, $scormdata, $url);

                // Clean up temporary files
                self::cleanup_temp_files($apikey, $url, $token, $domainname);

                $results['successful']++;
                $results['created_modules'][] = [
                    'cmid' => $scormcm,
                    'name' => $scormdata['scormname'],
                    'type' => 'scorm'
                ];

                if ($resourcecm) {
                    $results['created_modules'][] = [
                        'cmid' => $resourcecm,
                        'name' => $scormdata['scormname'] . ' (Accessibility)',
                        'type' => 'resource'
                    ];
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['messages'][] = "Error processing URL $url: " . $e->getMessage();
            }

            $count++;
        }

        // Update overall success status
        $results['success'] = ($results['failed'] == 0);

        return $results;
    }

    /**
     * Helper method to fetch SCORM data from external service
     */
    private static function fetch_scorm_data(string $apikey, string $url, string $token, string $domainname): ?array {
        $function_name = 'local_scormurl_get_tempscormurls';
        $tempurl = urlencode($url);

        $serverurl = $domainname . '/webservice/rest/server.php' . '?wstoken=' . $token
            . '&wsfunction=' . $function_name . '&apikey=' . $apikey . '&scormurl=' . $tempurl 
            . '&moodlewsrestformat=json';

        $curl = new \block_blc_modules\helper\blccurl();
        $curl->setHeader('Content-Type: application/json; charset=utf-8');

        $responses = $curl->post($serverurl, '', ['CURLOPT_FAILONERROR' => true]);
        $jsondata = json_decode($responses, true);

        if (empty($jsondata) || !isset($jsondata['scormname'])) {
            return null;
        }

        $scormobject = (object) $jsondata;

        return [
            'scormname' => str_replace("'", "'", $scormobject->scormname ?? ''),
            'scormversion' => $scormobject->version ?? '1',
            'scormid' => $scormobject->id ?? '',
            'scormurl' => str_replace("ppp", ",", $scormobject->tempscormurl ?? ''),
        ];
    }

    /**
     * Helper method to validate SCORM URL availability
     */
    private static function validate_scorm_url(string $scormurl): bool {
        return \block_blc_modules\middleware\services::blcscormurl_filesize($scormurl);
    }

    /**
     * Helper method to create SCORM module
     */
    private static function create_scorm_module(
        \stdClass $course,
        int $sectionnumber,
        array $scormdata,
        int $moduleid,
        int $visibility,
        int $hidebrowse,
        int $completion
    ): int {
        global $DB, $CFG;

        $scormsection = $DB->get_record('course_sections', [
            'course' => $course->id,
            'section' => $sectionnumber
        ]);

        // Create course module
        $newcm = new \stdClass();
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
            throw new \moodle_exception('cannotaddcoursemodule');
        }

        // Create SCORM instance
        $scorminstance = new \stdClass();
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

        if ($completion == 2) {
            $scorminstance->completionstatusrequired = 6;
        }

        if ($CFG->branch >= 36) {
            $scorminstance->forcenewattempt = 2;
        }

        $id = \block_blc_modules\middleware\services::blcscorm_add_instance($scorminstance);

        // Update course sections
        $record = new \stdClass();
        $record->id = $scormsection->id;
        $record->sequence = !empty($scormsection->sequence) 
            ? $scormsection->sequence . "," . $coursemodule 
            : $coursemodule;

        $DB->update_record('course_sections', $record);

        // Update SCORM to local type
        $DB->execute("UPDATE {scorm} SET scormtype = 'local' WHERE id = ?", [$id]);

        return $coursemodule;
    }

    /**
     * Helper method to record BLC module data
     */
    private static function record_blc_module(
        int $courseid,
        int $sectionnumber,
        int $cmid,
        array $scormdata,
        string $originalurl
    ): void {
        global $DB, $USER;

        $record = new \stdClass();
        $record->userid = $USER->id;
        $record->courseid = $courseid;
        $record->sectionid = $sectionnumber;
        $record->cmid = $cmid;
        $record->scormid = $scormdata['scormid'];
        $record->scormurl = $originalurl;
        $record->version = $scormdata['scormversion'];
        $record->timecreated = time();
        $record->timemodified = time();

        $DB->insert_record('block_blc_modules', $record);
    }

    /**
     * Helper method to create accessibility document
     */
    private static function create_accessibility_document(
        \stdClass $course,
        int $sectionnumber,
        array $scormdata,
        int $resourceid,
        int $visibility,
        string $apikey,
        string $token,
        string $domainname
    ): ?int {
        global $DB, $USER;

        // Try to fetch accessibility document data
        $docdata = self::fetch_accessibility_document($apikey, $scormdata['scormurl'], $token, $domainname);
        
        if (!$docdata) {
            return null; // No accessibility document available
        }

        $scormsection = $DB->get_record('course_sections', [
            'course' => $course->id,
            'section' => $sectionnumber
        ]);

        // Create course module for resource
        $newcm = new \stdClass();
        $newcm->course = $course->id;
        $newcm->module = $resourceid;
        $newcm->section = $scormsection->id;
        $newcm->instance = 0;
        $newcm->visible = $visibility;
        $newcm->visibleold = 1;
        $newcm->visibleoncoursepage = $visibility > 0 ? 1 : 0;
        $newcm->groupmode = 0;
        $newcm->groupingid = 0;
        $newcm->completion = 0; // No completion for accessibility docs
        $newcm->availability = null;
        $newcm->showdescription = 0;

        if (!$resourcecoursemodule = add_course_module($newcm)) {
            return null;
        }

        // Create resource instance
        $resourceinstance = new \stdClass();
        $resourceinstance->course = $course->id;
        $resourceinstance->coursemodule = $resourcecoursemodule;
        $resourceinstance->name = rtrim($docdata['docname'], '.docx');
        $resourceinstance->intro = '';
        $resourceinstance->introformat = 1;
        $resourceinstance->completionexpected = 0;
        
        // Set the display options to the site defaults
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

        // Update course module with instance ID
        $DB->set_field('course_modules', 'instance', $id, ['id' => $resourcecoursemodule]);

        // Update course section sequence
        $record = new \stdClass();
        $record->id = $scormsection->id;
        $record->sequence = !empty($scormsection->sequence) 
            ? $scormsection->sequence . "," . $resourcecoursemodule 
            : $resourcecoursemodule;
        $DB->update_record('course_sections', $record);

        // Try to create the file from the document URL
        try {
            self::create_resource_file($resourcecoursemodule, $docdata);
        } catch (\Exception $e) {
            // Continue even if file creation fails
            debugging("Failed to create accessibility document file: " . $e->getMessage());
        }

        return $resourcecoursemodule;
    }

    /**
     * Helper method to fetch accessibility document data
     */
    private static function fetch_accessibility_document(string $apikey, string $scormurl, string $token, string $domainname): ?array {
        $tempurl = urlencode($scormurl);
        $function_name = 'local_scormurl_get_tempdocurls';

        $serverurl = $domainname . '/webservice/rest/server.php' . '?wstoken=' . $token
            . '&wsfunction=' . $function_name . '&apikey=' . $apikey . '&scormurl=' . $tempurl 
            . '&moodlewsrestformat=json';

        $curl = new \block_blc_modules\helper\blccurl();
        $curl->setHeader('Content-Type: application/json; charset=utf-8');

        $responses = $curl->post($serverurl, '', ['CURLOPT_FAILONERROR' => true]);
        $jsondata = json_decode($responses, true);

        if (empty($jsondata) || !isset($jsondata['docname'])) {
            return null;
        }

        $docobject = (object) $jsondata;

        return [
            'docname' => rtrim($docobject->docname ?? '', '.docx'),
            'docversion' => $docobject->version ?? '5',
            'docid' => $docobject->id ?? '',
            'docurl' => str_replace("ppp", ",", $docobject->tempdocurl ?? ''),
        ];
    }

    /**
     * Helper method to create resource file
     */
    private static function create_resource_file(int $resourcecoursemodule, array $docdata): void {
        global $USER;

        $context = \context_module::instance($resourcecoursemodule);
        $fs = get_file_storage();
        $filename = $docdata['docname'] . '.docx';
        
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_resource',
            'filearea' => 'content',
            'userid' => $USER->id,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename
        ];

        $filepath = $docdata['docurl'];

        // Check if this is a pluginfile URL and handle it differently
        if (strpos($filepath, '/pluginfile.php/') !== false) {
            // Use the function from load_scorm.php if available
            if (function_exists('create_file_from_pluginfile_url')) {
                create_file_from_pluginfile_url($fs, $filerecord, $filepath);
            }
        } else {
            // For external URLs, use the enhanced download method
            if (function_exists('create_file_from_external_url')) {
                create_file_from_external_url($fs, $filerecord, $filepath);
            }
        }
    }

    /**
     * Helper method to clean up temporary files
     */
    private static function cleanup_temp_files(
        string $apikey,
        string $url,
        string $token,
        string $domainname
    ): void {
        $tempurl = urlencode($url);
        $function_name = 'local_scormurl_get_deletetempscormurls';
        $serverurl = $domainname . '/webservice/rest/server.php' . '?wstoken=' . $token
            . '&wsfunction=' . $function_name . '&apikey=' . $apikey . '&scormurl=' . $tempurl;

        $curl = new \block_blc_modules\helper\blccurl();
        $curl->setHeader('Content-Type: application/json; charset=utf-8');
        $curl->post($serverurl, '', ['CURLOPT_FAILONERROR' => true]);
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
