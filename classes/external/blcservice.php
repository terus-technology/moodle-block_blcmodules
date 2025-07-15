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
        . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi. '&version=5';
        $curl = new blccurl;
        $curl->setHeader('Content-Type: application/json; charset=utf-8');

        $responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));
        //print_r($responses);
        $scorms = array();
        $xml = (array)simplexml_load_string($responses);
        $multiplearray = $xml['MULTIPLE'];
        $multiple = (array)$multiplearray;
        if(!isset($multiple[0])){
            $singlearray = $multiple['SINGLE'];
            foreach($singlearray as $single){
                $single = (array)$single;		
                $keyarray = $single['KEY'];
                $scormobject = new \stdclass();
                foreach($keyarray as $key){
                    $key = (array)$key;
                    $field = $key['@attributes']['name'];
                    $fielddata = $key['VALUE'];	
                    $scormobject->$field = $fielddata ;			
                    }		
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
    public static function get_blc_modules_scormurl(string $apikey, string $requesturi, int $version): array {
        global $DB;
        $selectsubject = optional_param('subject', '', PARAM_TEXT);
        // Parameter validation.
        $params = self::validate_parameters(self::get_blc_modules_scormurl_parameters(), [
            'apikey' => $apikey,
            'requesturi' => $requesturi,
            'version' => $version,
        ]);

        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');

        $function_name = 'local_scormurl_get_scormurls';
        $serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
            . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi. '&version=5';
        $curl = new blccurl;
        $curl->setHeader('Content-Type: application/json; charset=utf-8');
        
        $responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));
        //print_r($responses);
        $scorm = array();
        $xml = (array)simplexml_load_string($responses);
        if (!isset($xml['MULTIPLE'])) {
            return [];
        }
        $multiplearray = $xml['MULTIPLE'];
        $multiple = (array) $multiplearray;
        if (!isset($multiple['SINGLE'])) {
            return [];
        }
        $singlearray = $multiple['SINGLE'];
        foreach($singlearray as $single){
            $single = (array) $single;		
            $keyarray = $single['KEY'];
            $scormobject = new \stdClass();
            foreach($keyarray as $key){
                $key = (array) $key;
                $field = $key['@attributes']['name'];
                $fielddata = $key['VALUE'];
                $scormobject->$field = $fielddata;
            }
            
            // Filter by subject if specified
            if (empty($selectsubject) || $scormobject->subject == $selectsubject) {
                $scorm[] = $scormobject;
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
                'id' => new external_value(PARAM_TEXT, 'SCORM package ID', VALUE_OPTIONAL),
                'scormname' => new external_value(PARAM_TEXT, 'SCORM package name', VALUE_OPTIONAL),
                'scormurl' => new external_value(PARAM_URL, 'SCORM package URL', VALUE_OPTIONAL),
                'scormid' => new external_value(PARAM_TEXT, 'SCORM identifier', VALUE_OPTIONAL),
                'year' => new external_value(PARAM_TEXT, 'Year', VALUE_OPTIONAL),
                'subject' => new external_value(PARAM_TEXT, 'Subject', VALUE_OPTIONAL),
                'version' => new external_value(PARAM_TEXT, 'Version', VALUE_OPTIONAL),
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
        . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi. '&version=5';
        $curl = new blccurl;
        $curl->setHeader('Content-Type: application/json; charset=utf-8');

        $responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
        
        $subjects = array();
        $xml = (array)simplexml_load_string($responses);
        if (!isset($xml['MULTIPLE'])) {
            return [];
        }
        $multiplearray = $xml['MULTIPLE'];
        $multiple = (array) $multiplearray;
        if(!isset($multiple[0])){
            // Add default "Select Subject" option
            $defaultsubject = new \stdClass();
            $defaultsubject->id = '0';
            $defaultsubject->subject = 'Select Subject';
            $subjects[] = $defaultsubject;
            
            $singlearray = $multiple['SINGLE'];
            $uniquesubjects = array();
            
            foreach($singlearray as $single){
                $single = (array) $single;
                $keyarray = $single['KEY'];
                $scormid = '';
                $subject = '';
                
                foreach($keyarray as $key){
                    $key = (array) $key;
                    if($key['@attributes']['name'] == 'id')	
                        $scormid = $key['VALUE'];
                    if($key['@attributes']['name'] == 'subject')	
                        $subject = $key['VALUE'];			
                }
                
                // Only add unique subjects
                if (!empty($subject) && !in_array($subject, $uniquesubjects)) {
                    $subjectobj = new \stdClass();
                    $subjectobj->id = $scormid;
                    $subjectobj->subject = $subject;
                    $subjects[] = $subjectobj;
                    $uniquesubjects[] = $subject;
                }
            }
        }
        return $subjects;
    }


    /**
     * Returns description of method result value for get_blc_modules_scormsubject.
     *
     * @return external_multiple_structure
     */
    public static function get_blc_modules_scormsubject_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Subject ID'),
                'subject' => new external_value(PARAM_TEXT, 'Subject name'),
            ])
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

}
