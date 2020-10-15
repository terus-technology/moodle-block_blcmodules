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
require_login(null, false);

$courseid = optional_param('id', '', PARAM_INT);
$section = optional_param('sectionNumber', '', PARAM_INT);
$apikey = optional_param('apikey', '', PARAM_TEXT);
$scormurls = optional_param_array('scormurls', '', PARAM_TEXT);
$visibility = optional_param('visibility', '', PARAM_INT);
$hidebrowse = optional_param('hidebrowse', '', PARAM_INT);
$completion = optional_param('completion', '', PARAM_INT);
$completion = intval($completion);

global $DB, $USER, $CFG;

if(is_array($scormurls))
	$scormurls = implode(",", $scormurls);
$scormurls = explode(",", $scormurls);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$scormmodule = $DB->get_record('modules', array('name' => 'scorm'));
$moduleid = $scormmodule->id;
$scormmodule = $DB->get_record('modules', array('name' => 'resource'));
$resourceid = $scormmodule->id;
$token = get_config('block_blc_modules', 'token');
$domainname = get_config('block_blc_modules', 'domainname');
$requesturi = $CFG->wwwroot;

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
