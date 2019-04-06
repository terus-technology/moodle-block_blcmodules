<?php

require_once(dirname(__FILE__).'/../../config.php');
require_once('curl.php');

$courseid = optional_param('id', '', PARAM_RAW);
$apikey = optional_param('apikey', '', PARAM_RAW);
global $DB,$USER,$CFG;

		$requesturi = $CFG->wwwroot;	
		$token = get_config('block_blc_modules', 'token');
		$domainname = get_config('block_blc_modules', 'domainname');
		$function_name = 'local_scormurl_get_scormurls';
		$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
		 . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi. '&version=5';
		$curl = new curl;
        $curl->setHeader('Content-Type: application/json; charset=utf-8');

		
		$responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
		//print_r($responses);
		$scorms =array();
		$xml=(array)simplexml_load_string($responses);
		$multiplearray = $xml['MULTIPLE'];
		$multiple =  (array) $multiplearray;
		if(!isset($multiple[0])){
			$singlearray = $multiple['SINGLE'];
			foreach($singlearray as $single){
				$single =  (array) $single;		
				$keyarray = $single['KEY'];
				$scormobject = new stdclass();
				foreach($keyarray as $key){
					$key =  (array) $key;
					$field = $key['@attributes']['name'];
					$fielddata = $key['VALUE'];	
						$scormobject->$field =$fielddata ;
					
					}
				
					$scorms[$scormobject->id] =$scormobject;
			}
		}
		$updatescorm=array();
		$coursescorms = $DB->get_records('block_blc_modules',array('courseid'=>$courseid));
		foreach($coursescorms as $coursescorm){
			foreach($scorms as $scorm){
				if($coursescorm->scormid == $scorm->id && $coursescorm->version != $scorm->version){
					$updatescorm[$coursescorm->cmid]=$scorm->version;
					
				}
			}
			
		}
				echo json_encode($updatescorm);
