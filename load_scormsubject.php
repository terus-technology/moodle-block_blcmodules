<?php

require_once(dirname(__FILE__).'/../../config.php');
require_once('curl.php');

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
		
		$scorm =array();
		$xml=(array)simplexml_load_string($responses);
		$multiplearray = $xml['MULTIPLE'];
		$multiple =  (array) $multiplearray;
		if(!isset($multiple[0])){
		$scorm= array('0'=>"Select Subject");
		$singlearray = $multiple['SINGLE'];
			foreach($singlearray as $single){
				$single =  (array) $single;
				
				$keyarray = $single['KEY'];
				foreach($keyarray as $key){
					$key =  (array) $key;
					if($key['@attributes']['name']=='id')	
						$scormkey=$key['VALUE'];
					if($key['@attributes']['name']=='subject')	
						$scormvalue=$key['VALUE'];
					
				}
				$scorm[$scormkey] =$scormvalue;

			}
		}
		$scorm=array_unique($scorm);
		echo json_encode($scorm);
