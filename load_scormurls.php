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
 * This file contains the Activity modules block.
 *
 * @package    block_blc_modules
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
require_once(dirname(__FILE__).'/../../config.php');
require_once('curl.php');
require_login(null, false);

$selectsubject = optional_param('subject', '', PARAM_TEXT);
$apikey = optional_param('apikey', '', PARAM_TEXT);
global $DB, $USER, $CFG;
			
		$token = get_config('block_blc_modules', 'token');
		$domainname = get_config('block_blc_modules', 'domainname');
		$requesturi = $CFG->wwwroot;
		$function_name = 'local_scormurl_get_scormurls';
		$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
		 . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi. '&version=5';
		$curl = new blccurl;
        $curl->setHeader('Content-Type: application/json; charset=utf-8');
		
		$responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));
		//print_r($responses);
		$scorm = array();
		$xml = (array)simplexml_load_string($responses);
		$multiplearray = $xml['MULTIPLE'];
		$multiple = (array) $multiplearray;
		$singlearray = $multiple['SINGLE'];
		foreach($singlearray as $single){
			$single = (array) $single;		
			$keyarray = $single['KEY'];
			$subject = '';
			foreach($keyarray as $key){
				$key = (array) $key;
				if($key['@attributes']['name'] == 'scormname')	
					$scormvalue = $key['VALUE'];
				if($key['@attributes']['name'] == 'scormurl')	
					$scormkey = $key['VALUE'];
				
				if($key['@attributes']['name'] == 'subject')	
					 $subject=$key['VALUE'];
				}
			if($subject == $selectsubject )
				$scorm[$scormkey] = $scormvalue;
		}
			echo json_encode($scorm);
