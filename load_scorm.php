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

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/scorm/locallib.php');
require_once($CFG->dirroot . '/mod/scorm/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once("$CFG->libdir/resourcelib.php");
require_once($CFG->dirroot . '/mod/resource/locallib.php');
require_once($CFG->dirroot . '/mod/resource/lib.php');
require_once('curl.php');
require_login(null, false);

use block_blc_modules\middleware\services as BLCService;

$courseid = optional_param('id', '', PARAM_INT);
$section = optional_param('sectionNumber', '', PARAM_INT);
$apikey = optional_param('apikey', '', PARAM_TEXT);
$scormurls = optional_param_array('scormurls', '', PARAM_TEXT);
$visibility = optional_param('visibility', '', PARAM_INT);
$hidebrowse = optional_param('hidebrowse', '', PARAM_INT);
$completion = optional_param('completion', '', PARAM_INT);
$completion = intval($completion);

global $DB, $USER, $CFG;

function UR_exists($url)
{
	$headers = get_headers($url);
	return stripos($headers[0], "200 OK") ? true : false;
}

if (is_array($scormurls))
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
$count = 0;
$notdownloaded = 0;

foreach ($scormurls as $url) {

	$function_name = 'local_scormurl_get_tempscormurls';
	$tempurl = urlencode($url);

	$serverurl = $domainname . '/webservice/rest/server.php' . '?wstoken=' . $token
		. '&wsfunction=' . $function_name . '&apikey=' . $apikey . '&scormurl=' . $tempurl;
	$curl = new blccurl;
	$curl->setHeader('Content-Type: application/json; charset=utf-8');

	$responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));

	$scorms = array();
	$xml = (array)simplexml_load_string($responses);

	if (empty($xml['SINGLE'])) {
		$url = str_replace("’", "'", $url);
		$tempurl = urlencode($url);

		$serverurl = $domainname . '/webservice/rest/server.php' . '?wstoken=' . $token
			. '&wsfunction=' . $function_name . '&apikey=' . $apikey . '&scormurl=' . $tempurl;
		$curl = new blccurl;
		$curl->setHeader('Content-Type: application/json; charset=utf-8');

		$responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));

		$scorms = array();
		$xml = (array)simplexml_load_string($responses);
	}

	$single = $xml['SINGLE'];
	$singlearray = (array) $single;
	$keyarray = $singlearray['KEY'];
	$scormobject = new stdclass();

	foreach ($keyarray as $key) {
		$key = (array)$key;
		$field = $key['@attributes']['name'];
		$fielddata = $key['VALUE'];
		$scormobject->$field = $fielddata;
	}
	if ($count > 0) {
		$scormmessage = " Scorm: " . $scormname . "<br/>";
		echo json_encode($scormmessage);
	}
	$scorms[$scormobject->id] = $scormobject;

	foreach ($scorms as $key => $scorm) {
		if ($scorm->scormurl) {
			$scorm->scormname = str_replace("'", "’", $scorm->scormname);
			$scormname = chop($scorm->scormname, ".zip");
			$scormversion = $scorm->version;
			$scormid = $scorm->id;
			$scormurl = $scorm->tempscormurl;
			$scormurl = str_replace("ppp", ",", $scormurl);
			break;
		}
	}

	sleep(5);

	if (BLCService::blcscormurl_filesize($scormurl)) {
		$scormsection = $DB->get_record('course_sections', array('course' => $courseid, 'section' => $section));
		$sectionid = $scormsection->id;

		// First add course_module record because we need the context.
		$newcm = new stdClass();
		$newcm->course           = $course->id;
		$newcm->module           = $moduleid;
		$newcm->section           = $sectionid;
		$newcm->instance         = 0; // Not known yet, will be updated later (this is similar to restore code).
		$newcm->visible          = $visibility;
		$newcm->visibleold       = 1;
		if ($visibility > 0) {
			$newcm->visibleoncoursepage = 1;
		} else {
			$newcm->visibleoncoursepage = 0;
		}

		$newcm->groupmode        = 0;
		$newcm->groupingid       = 0;
		if ($completion == 0) {
			$newcm->completion   = 0;
			$completionon = 0;
		} else if ($completion == 2) {
			$newcm->completion   = 2;
			$newcm->completiongradeitemnumber = null;
			$newcm->completionview            = 0;
			$newcm->completionexpected        = 0;
			$completionon = 2;
		} else {
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
		if ($CFG->branch >= 36)
			$scorminstance->forcenewattempt = 2;
		$scorminstance->width = 100;
		$scorminstance->height = 500;
		$scorminstance->hidetoc = 3;
		$scorminstance->hidebrowse = $hidebrowse;

		if ($completion == 2) {
			$scorminstance->completionstatusrequired = 6;
		}

		$scorminstance->displaycoursestructure = 0;
		$scorminstance->skipview = 2;
		$scorminstance->packageurl = $scormurl;
		$scorminstance->scormtype = 'localsync';
		$scorminstance->cmidnumber = '';

		$id = BLCService::blcscorm_add_instance($scorminstance);

		$record = new stdClass();
		$record->id = $sectionid;
		if (!empty($scormsection->sequence))
			$record->sequence = $scormsection->sequence . "," . $coursemodule;
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

		$blcmoduleid = $DB->insert_record('block_blc_modules', $scormrecord);

		$scormcm = $DB->get_record('course_modules', array('id' => $coursemodule));
		$scormtoupdate = new stdClass();
		$scormtoupdate->course = $courseid;
		$scormtoupdate->coursemodule = $coursemodule;
		$scormtoupdate->cmidnumber = null;
		$scormtoupdate->instance = $scormcm->instance;
		$scormtoupdate->width = 100;
		$scormtoupdate->height = 500;
		$scormtoupdate->scormtype = 'localsync';
		$scormtoupdate->packageurl = $scormurl;
		$scormtoupdate->id = $id;
		BLCService::blc_scorm_update_instance($scormtoupdate);

		$sql = "UPDATE " . $CFG->prefix . "scorm SET scormtype = 'local' WHERE id = " . $id;
		$DB->execute($sql, array($params = null));
		$scormtoview = $DB->get_record('scorm', array('id' => $id));

		$function_name = 'local_scormurl_get_deletetempscormurls';
		$serverurl = $domainname . '/webservice/rest/server.php' . '?wstoken=' . $token
			. '&wsfunction=' . $function_name . '&apikey=' . $apikey . '&scormurl=' . $tempurl;
		$curl = new blccurl;
		$curl->setHeader('Content-Type: application/json; charset=utf-8');

		$responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));

		//Add accessibility document
		$function_name = 'local_scormurl_get_tempdocurls';

		$serverurl = $domainname . '/webservice/rest/server.php' . '?wstoken=' . $token
			. '&wsfunction=' . $function_name . '&apikey=' . $apikey . '&scormurl=' . $tempurl;

		$curl = new blccurl;
		$curl->setHeader('Content-Type: application/json; charset=utf-8');

		$responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));

		$docs = array();
		$xml = (array)simplexml_load_string($responses);

		if (isset($xml['SINGLE'])) {
			$single = $xml['SINGLE'];
			$singlearray = (array) $single;
			$keyarray = $singlearray['KEY'];
			$docobject = new stdclass();
			foreach ($keyarray as $key) {
				$key = (array) $key;
				$field = $key['@attributes']['name'];
				$fielddata = $key['VALUE'];
				$docobject->$field = $fielddata;
			}

			$docs[$docobject->id] = $docobject;

			foreach ($docs as $key => $doc) {
				if ($scorm->scormurl) {
					$docname = chop($doc->docname, ".docx");
					$docversion = $doc->version;
					$docid = $doc->id;
					$docurl = $doc->tempdocurl;
					$docurl = str_replace("ppp", ",", $docurl);
					break;
				}
			}

			// First add course_module record because we need the context.
			$scormsection = $DB->get_record('course_sections', array('course' => $courseid, 'section' => $section));
			$sectionid = $scormsection->id;

			$newcm = new stdClass();
			$newcm->course           = $course->id;
			$newcm->module           = $resourceid;
			$newcm->section          = $sectionid;
			$newcm->instance         = 0; // Not known yet, will be updated later (this is similar to restore code).
			$newcm->visible          = $visibility;
			$newcm->visibleold       = 1;
			if ($visibility > 0) {
				$newcm->visibleoncoursepage = 1;
			} else {
				$newcm->visibleoncoursepage = 0;
			}

			$newcm->groupmode        = 0;
			$newcm->groupingid       = 0;
			$newcm->completion   = 0; //Remove completion tracking for accessibility documents 
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
			// Set the display options to the site defaults.
			$config = get_config('resource');
			$resourceinstance->display = $config->display;
			$resourceinstance->popupheight = $config->popupheight;
			$resourceinstance->popupwidth = $config->popupwidth;
			$resourceinstance->printintro = $config->printintro;
			$resourceinstance->showsize = (isset($config->showsize)) ? $config->showsize : 0;
			$resourceinstance->showtype = (isset($config->showtype)) ? $config->showtype : 0;
			$resourceinstance->showdate = (isset($config->showdate)) ? $config->showdate : 0;
			$resourceinstance->filterfiles = $config->filterfiles;

			//$id = resource_add_instance($resourceinstance, null);
			$resourceinstance->timemodified = time();

			resource_set_display_options($resourceinstance);

			$id = $DB->insert_record('resource', $resourceinstance);

			// we need to use context now, so we need to make sure all needed info is already in db
			$DB->set_field('course_modules', 'instance', $id, array('id' => $resourcecoursemodule));

			if ($CFG->version > 2017000000) {
				$completiontimeexpected = !empty($resourceinstance->completionexpected) ? $resourceinstance->completionexpected : null;
				\core_completion\api::update_completion_date_event($resourcecoursemodule, 'resource', $id, $completiontimeexpected);
			}

			$filepath = $docurl;
			$syscontext = context_system::instance();
			$file_name = $docname . '.docx';
			$fs = get_file_storage();
			$context = context_module::instance($resourcecoursemodule);
			$filerecord = array(
				'contextid' => $context->id, // ID of context
				'component' => 'mod_resource',
				'filearea' => 'content',
				'userid' => $USER->id,
				'itemid' => 0,
				'filepath' => '/',
				'filename' => $file_name
			);

			$file = $fs->create_file_from_url($filerecord, $filepath, array('calctimeout' => true, 'skipcertverify' => true));

			$record = new stdClass();
			$record->id = $sectionid;
			if (!empty($scormsection->sequence))
				$record->sequence = $scormsection->sequence . "," . $resourcecoursemodule;
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
			$serverurl = $domainname . '/webservice/rest/server.php' . '?wstoken=' . $token
				. '&wsfunction=' . $function_name . '&apikey=' . $apikey . '&scormurl=' . $tempurl;
			$curl = new blccurl;
			$curl->setHeader('Content-Type: application/json; charset=utf-8');

			$responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));
		}
		//end Add accessibility document
		$scormmessage = 'completed';
		echo json_encode($scormmessage);
	} else {
		$scormmessage = 'Not completed';
		$notdownloaded = 1;
		//echo json_encode($scormmessage);

	}
	$count++;
}

if ($notdownloaded == 1) {
	$scormmessage = "Something went wrong. Please re-select the modules below and try again.";
	echo json_encode($scormmessage);
}