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

}
