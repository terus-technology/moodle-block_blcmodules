<?php

require_once(dirname(__FILE__).'/../../config.php');
require_once('curl.php');

$selectsubject = optional_param('subject', '', PARAM_TEXT);
$apikey = optional_param('apikey', '', PARAM_RAW);
global $DB,$USER,$CFG;

			
		$token = get_config('block_blc_modules', 'token');
		$domainname = get_config('block_blc_modules', 'domainname');
		$requesturi = $CFG->wwwroot;
		$function_name = 'local_scormurl_get_scormurls';
		$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
		 . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi. '&version=5';
		$curl = new curl;
        $curl->setHeader('Content-Type: application/json; charset=utf-8');

		
		$responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
		//print_r($responses);
		$scorm =array();
		$xml=(array)simplexml_load_string($responses);
		$multiplearray = $xml['MULTIPLE'];
		$multiple =  (array) $multiplearray;
		$singlearray = $multiple['SINGLE'];
		foreach($singlearray as $single){
			$single =  (array) $single;		
			$keyarray = $single['KEY'];
			$subject = '';
			foreach($keyarray as $key){
				$key =  (array) $key;
				if($key['@attributes']['name']=='scormname')	
					$scormvalue=$key['VALUE'];
				if($key['@attributes']['name']=='scormurl')	
					$scormkey=$key['VALUE'];
				
				if($key['@attributes']['name']=='subject')	
					 $subject=$key['VALUE'];
				}
			if($subject == $selectsubject )
				$scorm[$scormkey] =$scormvalue;
		}
			echo json_encode($scorm);
