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
require_once($CFG->dirroot.'/mod/scorm/locallib.php');
require_once($CFG->dirroot.'/mod/scorm/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once("$CFG->libdir/resourcelib.php");
require_once($CFG->dirroot.'/mod/resource/locallib.php');
require_once($CFG->dirroot.'/mod/resource/lib.php');
require_once('curl.php');

global $DB,$USER,$CFG;

$token = get_config('block_blc_modules', 'token');
$domainname = get_config('block_blc_modules', 'domainname');
$apikey = get_config('block_blc_modules', 'api_key');

$resourcemodule = $DB->get_record('modules',array('name'=>'resource'));
$resourceid=$resourcemodule->id;

$sql = "select min(blcmoduleid) as blcmoduleid from {block_blc_modules_doc} ";
$blcdoc = $DB->get_record_sql($sql);
$sql = "select * from {block_blc_modules} ";

if($blcdoc){
	$blcid = $blcdoc->blcmoduleid;
	if(!empty($blcid))
		$sql .=" where id < ".$blcid;
}

	$blcmodules = $DB->get_records_sql($sql);
	if($blcmodules){
		
		foreach($blcmodules as $blcmodule){
			
			$cmid=$blcmodule->cmid;
			$course_modules = $DB->get_record('course_modules',array('id'=>$cmid,'deletioninprogress'=>0));
			if($course_modules){
				
				$visibility = $course_modules->visible;
				$completion = $course_modules->completion;
				$tempurl = $blcmodule->scormurl;
				$tempurl = urlencode($tempurl);
				$blcmoduleid = $blcmodule->id;
				$section = $blcmodule->sectionid;
				$courseid=$blcmodule->courseid;
				
				$scormsection = $DB->get_record('course_sections',array('course'=>$courseid,'section'=>$section));
				$sequence=$scormsection->sequence;
				$modules = explode(',', $sequence);
				if(!in_array($cmid,$modules)){
					//we will have to find the current position of the modules, in case they have moved.
					$sections = $DB->get_records('course_sections',array('course'=>$courseid));
					foreach($sections as $sec){
						 $modules = explode(',', $sec->sequence);
						 if(in_array($cmid,$modules)){
							 $section = $sec->section;
						 }
					}

				}
				
				$function_name = 'local_scormurl_get_tempdocurls';

				$serverurl = $domainname.'/webservice/rest/server.php'.'?wstoken='.$token
					 .'&wsfunction='.$function_name.'&apikey='.$apikey.'&scormurl='.$tempurl;
				$curl = new blccurl;
				$curl->setHeader('Content-Type: application/json; charset=utf-8');

				$responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));

				$docs =array();
				$xml=(array)simplexml_load_string($responses);
				if(isset($xml['SINGLE'])){
					$single = $xml['SINGLE'];
					$singlearray =  (array) $single;
					$keyarray = $singlearray['KEY'];
					$docobject = new stdclass();
					foreach($keyarray as $key){
						$key =  (array) $key;
						$field = $key['@attributes']['name'];
						$fielddata = $key['VALUE'];	
							$docobject->$field =$fielddata ;
						
					}
					
					$docs[$docobject->id] =$docobject;

					foreach($docs as $key=>$doc){
							if($doc->docurl){
								$docname = chop($doc->docname,".docx");
								$docversion = $doc->version;
								$docid = $doc->id;
								$docurl = $doc->tempdocurl;
								$docurl = str_replace("ppp",",",$docurl); 
								break;
							}
						}
						
					$scormsection = $DB->get_record('course_sections',array('course'=>$courseid,'section'=>$section));
					$sectionid=$scormsection->id;

					$newcm = new stdClass();
					$newcm->course           = $courseid;
					$newcm->module           = $resourceid;
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
					$newcm->completion   = 0;//Remove completion tracking for accessibility documents
					$completionon = 0;
					$newcm->availability = null;
					$newcm->showdescription = 0;

					if (!$resourcecoursemodule = add_course_module($newcm)) {
						print_error('cannotaddcoursemodule');
					}

					$resourceinstance = new stdClass();
					$resourceinstance->course = $courseid;
					$resourceinstance->coursemodule = $resourcecoursemodule;
					$resourceinstance->name = $docname;
					$resourceinstance->intro = '';
					$resourceinstance->introformat = 1;
					$resourceinstance->completionexpected  = 0;	
					$config = get_config('resource');
					$resourceinstance->display = $config->display;
					$resourceinstance->popupheight = $config->popupheight;
					$resourceinstance->popupwidth = $config->popupwidth;
					$resourceinstance->printintro = $config->printintro;
					$resourceinstance->showsize = (isset($config->showsize)) ? $config->showsize : 0;
					$resourceinstance->showtype = (isset($config->showtype)) ? $config->showtype : 0;
					$resourceinstance->showdate = (isset($config->showdate)) ? $config->showdate : 0;
					$resourceinstance->filterfiles = $config->filterfiles;	
					$resourceinstance->timemodified = time();

					resource_set_display_options($resourceinstance);

					$id = $DB->insert_record('resource', $resourceinstance);

					// we need to use context now, so we need to make sure all needed info is already in db
					$DB->set_field('course_modules', 'instance', $id, array('id'=>$resourcecoursemodule));

					$completiontimeexpected = !empty($resourceinstance->completionexpected) ? $resourceinstance->completionexpected : null;
					\core_completion\api::update_completion_date_event($resourcecoursemodule, 'resource', $id, $completiontimeexpected);

					$filepath = $docurl;
					$file_name=$docname.'.docx';
					$fs = get_file_storage(); 
					$context = context_module::instance($resourcecoursemodule);
					$filerecord = array(
										'contextid' => $context->id, // ID of context
										'component' => 'mod_resource',
										'filearea' => 'content',
										'userid' => $USER->id,
										'itemid' => 0,
										'filepath' => '/',
									 'filename' => $file_name);

					$file = $fs->create_file_from_url($filerecord, $filepath);

					$record = new stdClass();
					$record->id = $sectionid;
					if(!empty($scormsection->sequence)){
						$modules = explode(',', $scormsection->sequence);
						$newmodules=array();
						foreach($modules as $key=>$value){
							if($value == $cmid){
								$newmodules[]=$value;
								$newmodules[]=$resourcecoursemodule;
							}
							else
							$newmodules[]=$value;
						}
						$record->sequence = implode(',', $newmodules);
						
					}
					else
						$record->sequence = $resourcecoursemodule;

					$DB->update_record('course_sections', $record);

					$resourcerecord = new stdClass();
					$resourcerecord->userid = $USER->id;
					$resourcerecord->courseid = $courseid;
					$resourcerecord->blcmoduleid = $blcmoduleid;
					$resourcerecord->sectionid = $section;
					$resourcerecord->cmid = $resourcecoursemodule;
					$resourcerecord->scormid = $docid;
					$resourcerecord->scormurl = $docurl;
					$resourcerecord->version = $docversion;
					$resourcerecord->timecreated = time();
					$resourcerecord->timemodified = time();
				
					$DB->insert_record('block_blc_modules_doc', $resourcerecord);

					$function_name = 'local_scormurl_get_deletetempdocurls';
					$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
						 . '&wsfunction='.$function_name . '&apikey='.$apikey. '&scormurl='.$tempurl;
					$curl = new blccurl;
					$curl->setHeader('Content-Type: application/json; charset=utf-8');


					$responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
				}
				
			}
		}
	}	

	$redirect = new moodle_url('/admin/settings.php', array('section' => 'blocksettingblc_modules'));

	redirect($redirect,get_string('updatedocmesage', 'block_blc_modules'),null,\core\output\notification::NOTIFY_SUCCESS);
