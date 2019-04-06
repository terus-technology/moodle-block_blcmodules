<?php

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');
require_once($CFG->dirroot.'/mod/scorm/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

$courseid = optional_param('id', '', PARAM_RAW);
$section = optional_param('sectionNumber', '', PARAM_RAW);
$apikey = optional_param('apikey', '', PARAM_RAW);
$scormurls = optional_param_array('scormurls', '', PARAM_TEXT);
$visibility = optional_param('visibility', '', PARAM_RAW);
$hidebrowse = optional_param('hidebrowse', '', PARAM_RAW);
$completion = optional_param('completion', '', PARAM_RAW);
$completion = intval($completion);
global $DB,$USER,$CFG;

if(isset($_POST['scormurls']) && $_POST['scormurls'])
	$scormurls= $_POST['scormurls'];
if(is_array($scormurls))
	$scormurls= implode(",",$scormurls);
$scormurls=explode(",",$scormurls);


$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);

$scormmodule = $DB->get_record('modules',array('name'=>'scorm'));
$moduleid=$scormmodule->id;
$token = get_config('block_blc_modules', 'token');
$domainname = get_config('block_blc_modules', 'domainname');
$requesturi = $CFG->wwwroot;

foreach($scormurls as $url){

	$function_name = 'local_scormurl_get_tempscormurls';
	//$url = str_replace("qqq",",",$url);
	$tempurl = urlencode($url);

	$serverurl = $domainname.'/webservice/rest/server.php'.'?wstoken='.$token
		 .'&wsfunction='.$function_name.'&apikey='.$apikey.'&scormurl='.$tempurl;
	$curl = new curl;
	$curl->setHeader('Content-Type: application/json; charset=utf-8');

	$responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
	
	$scorms =array();
	$xml=(array)simplexml_load_string($responses);
	$single = $xml['SINGLE'];
	$singlearray =  (array) $single;
	$keyarray = $singlearray['KEY'];
	$scormobject = new stdclass();
	foreach($keyarray as $key){
		$key =  (array) $key;
		$field = $key['@attributes']['name'];
		$fielddata = $key['VALUE'];	
			$scormobject->$field =$fielddata ;
		
	}
	
	$scorms[$scormobject->id] =$scormobject;

	foreach($scorms as $key=>$scorm){
			if($scorm->scormurl){
				$scormname = chop($scorm->scormname,".zip");
				$scormversion = $scorm->version;
				$scormid = $scorm->id;
				$scormurl = $scorm->tempscormurl;
				$scormurl = str_replace("ppp",",",$scormurl); 
				break;
			}
		}
			
		$scormsection = $DB->get_record('course_sections',array('course'=>$courseid,'section'=>$section));
		$sectionid=$scormsection->id;

		// First add course_module record because we need the context.

		$newcm = new stdClass();
		$newcm->course           = $course->id;
		$newcm->module           = $moduleid;
		$newcm->section           = $sectionid;
		$newcm->instance         = 0; // Not known yet, will be updated later (this is similar to restore code).
		$newcm->visible          = $visibility;
		$newcm->visibleold       = 1;
		if($visibility > 0){
			$newcm->visibleoncoursepage = 1;
		}else{
			$newcm->visibleoncoursepage = 0;
		}
		
		$newcm->groupmode        = 0;
		$newcm->groupingid       = 0;
		if ($completion == 0){
			$newcm->completion   = 0;
			$completionon = 0;
		}else if ($completion == 2){
			$newcm->completion   = 2;
			$newcm->completiongradeitemnumber = null;
			$newcm->completionview            = 0;
			$newcm->completionexpected        = 0;		
			$completionon = 2;	
		}else{
			$newcm->completion   = 1;
			$newcm->completiongradeitemnumber = null;
			$newcm->completionview            = 0;
			$newcm->completionexpected        = 0;		
			$completionon = 1;	
		}
		

		$newcm->availability = null;
		$newcm->showdescription = 0;

		if (!$coursemodule = add_course_module($newcm)) {
			print_error('cannotaddcoursemodule');
		}

		$scorminstance = new stdClass();
		$scorminstance->course = $courseid;
		$scorminstance->coursemodule = $coursemodule;
		$scorminstance->name = $scormname;
		$scorminstance->section = $section;
		$scorminstance->module = $moduleid;
		$scorminstance->modulename = 'scorm';
		$scorminstance->intro       = '';
		$scorminstance->introformat = 1;
		$scorminstance->version = 'SCORM_1.2';
		$scorminstance->maxgrade = 100;
		$scorminstance->grademethod = 1;
		$scorminstance->maxattempt = 0;
		$scorminstance->width = 100;
		$scorminstance->height = 500;
		$scorminstance->hidetoc = 3;
		$scorminstance->hidebrowse = $hidebrowse;

		if($completion == 2){
			$scorminstance->completionstatusrequired = 6;
		}	

		$scorminstance->displaycoursestructure = 0;
		$scorminstance->skipview = 2;
		$scorminstance->packageurl = $scormurl;
		$scorminstance->scormtype = 'localsync';
		$id = scorm_add_instance($scorminstance);


		$record = new stdClass();
		$record->id = $sectionid;
		//error_log($id);
		if(!empty($scormsection->sequence))
			$record->sequence = $scormsection->sequence.",".$coursemodule;
		else
			$record->sequence = $coursemodule;

		$DB->update_record('course_sections', $record);

		$scormrecord = new stdClass();
		$scormrecord->userid = $USER->id;
		$scormrecord->courseid = $courseid;
		$scormrecord->sectionid = $section;
		$scormrecord->cmid = $coursemodule;
		$scormrecord->scormid = $scormid;
		$scormrecord->scormurl = $url;
		$scormrecord->version = $scormversion;
		$scormrecord->timecreated = time();
		$scormrecord->timemodified = time();
	
		$DB->insert_record('block_blc_modules', $scormrecord);

		$function_name = 'local_scormurl_get_deletetempscormurls';
		$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
			 . '&wsfunction='.$function_name . '&apikey='.$apikey. '&scormurl='.$tempurl;
		$curl = new curl;
		$curl->setHeader('Content-Type: application/json; charset=utf-8');


		$responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
		$sql = "UPDATE ".$CFG->prefix."scorm SET scormtype = 'local' WHERE id = ".$id;
		$DB->execute($sql, array($params=null));
	}	


?>

