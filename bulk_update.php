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
require_once('curl.php');
require_login(null, false);

global $DB,$USER,$CFG;

$sesskey = optional_param('sesskey', '', PARAM_RAW);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$action = optional_param('action', '', PARAM_RAW);

$title = get_string('pluginname', 'block_blc_modules');
$heading = $SITE->fullname;
$url = '/blocks/blc_modules/bulk_update.php';

$baseurl = new moodle_url($url);

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_context(context_system::instance());
$PAGE->set_title("Bulk Update");
$PAGE->set_heading($heading);
$PAGE->set_cacheable(false);
$PAGE->requires->jquery();
$PAGE->requires->js(new moodle_url($CFG->wwwroot . '/blocks/blc_modules/js/custom-bulk.js'));

if($action == 'continue' ){
	echo $OUTPUT->header();
	$requesturi = $CFG->wwwroot;	
	$token = get_config('block_blc_modules', 'token');
	$domainname = get_config('block_blc_modules', 'domainname');
	$apikey = get_config('block_blc_modules', 'api_key');//'jvm1sad1bm88oog';

	$function_name = 'local_scormurl_get_bulkupscormurls';
	 $serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
	 . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi. '&version=5';
	$curl = new blccurl;
	$curl->setHeader('Content-Type: application/json; charset=utf-8');


	$responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
	$scorms =array();
	$xml=(array)simplexml_load_string($responses);
	if(isset($xml['MULTIPLE'])){
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
	
	$courses = $DB->get_records('course');
	$updatescorm=array();
	foreach($courses as $course){
		$courseid=$course->id;
		$coursescorms = $DB->get_records('block_blc_modules',array('courseid'=>$courseid));
		foreach($coursescorms as $coursescorm){
			foreach($scorms as $scorm){
				if($coursescorm->scormid == $scorm->id && $coursescorm->version < $scorm->version){
					$updatescorm[$coursescorm->cmid]=$scorm->version;
					
				}
			}
			
		}
	}
	
	foreach($updatescorm as $coursemodule=>$version){
			

		$coursescorm = $DB->get_record('block_blc_modules',array('cmid'=>$coursemodule));
		$url =$coursescorm->scormurl;
		
		$function_name = 'local_scormurl_get_bulkuptempscormurls';
		$tempurl = urlencode($url);

		$serverurl = $domainname.'/webservice/rest/server.php'.'?wstoken='.$token
			 .'&wsfunction='.$function_name.'&apikey='.$apikey.'&scormurl='.$tempurl;
		$curl = new blccurl;
		$curl->setHeader('Content-Type: application/json; charset=utf-8');

		$responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
		
		$scorms =array();
		$xml=(array)simplexml_load_string($responses);
		if(isset($xml['SINGLE'])){
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
				
			$scormcm = $DB->get_record('course_modules',array('id'=>$coursemodule));

			$scorm = new stdClass();
			$scorm->course = $coursescorm->courseid;
			$scorm->coursemodule = $coursemodule;
			$scorm->cmidnumber = null;
			$scorm->instance = $scormcm->instance;
			$scorm->scormtype = 'localsync';
			$scorm->packageurl = $scormurl;
			$scorm->width = 100;
			$scorm->height = 500;
		
			if(scorm_update_instance($scorm)){
				$scormrecord = new stdClass();
				$scormrecord->id = $coursescorm->id;
				$scormrecord->version = $version;
				$scormrecord->timemodified = time();
				$DB->update_record('block_blc_modules', $scormrecord);

				$function_name = 'local_scormurl_get_deletetempscormurls';
				$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
					 . '&wsfunction='.$function_name . '&apikey='.$apikey. '&scormurl='.$tempurl;
				$curl = new blccurl;
				$curl->setHeader('Content-Type: application/json; charset=utf-8');


				$responses = $curl->post($serverurl,'', array('CURLOPT_FAILONERROR' => true));
				$sql = "UPDATE ".$CFG->prefix."scorm SET scormtype = 'local' WHERE id = ".$scormcm->instance;
				$DB->execute($sql, array($params=null));
			
			}
		}
		
		
	}
	$redirect = new moodle_url('/admin/settings.php', array('section' => 'blocksettingblc_modules'));

	echo '<br /><div class="alert alert-success alert-block fade in " role="alert">
				'.get_string('updatescormmesage', 'block_blc_modules').'</div><br />';

	}
	else{
		
		echo '<br /><div class="alert alert-warning alert-block fade in " role="alert">
		 '.get_string('failupdatescormmesage', 'block_blc_modules').' </div><br />';
		
	}

	echo  '<div class="row">
                <div class="col-sm-3" >
				<button onclick="window.location.href = \''.$CFG->wwwroot.'/blocks/blc_modules/bulk_update.php?action=continue\';" class="btn btn-primary" >'.get_string('refresh', 'block_blc_modules').'</button>
				</div>
                <div class="col-sm-3" >    
                    <button onclick="window.location.href = \''.$CFG->wwwroot.'/admin/settings.php?section=blocksettingblc_modules\';" class="btn btn-secondary" >'.get_string('return', 'block_blc_modules').'</button>
                </div>
            </div>';
            		echo $OUTPUT->footer();

}
	else{	
		echo $OUTPUT->header();
		echo '<script src="https://netdna.bootstrapcdn.com/bootstrap/3.0.3/js/bootstrap.min.js"></script>

          <div class="modal fade" id="modalForm" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">      <!-- Modal Header -->
                <div class="modal-header">
                    <h4 class="modal-title" id="myModalLabel">Confirm</h4>
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                        <span class="sr-only">Close</span>
                    </button>
                </div>
                <!-- Modal Body -->
                <div class="modal-body">
                    <p class="statusMsg">'.get_string('updateconfirmmessage', 'block_blc_modules').'</p>
                    <form role="form" action="'.$CFG->wwwroot.'/blocks/blc_modules/bulk_update.php" id="bulkupdatesubmit">
                    <input type="hidden" name="action" value="continue"/>
                     </form>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-action="cancel" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-action="save" id="bulkupdatecont" >Continue</button>
                </div>
            </div>
        </div>
    </div>
    ';
		echo $OUTPUT->footer();

}
