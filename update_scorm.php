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
require_login(null, false);

$coursemodule = optional_param('cmid', '', PARAM_INT);
$version = optional_param('version', '', PARAM_INT);

global $DB, $USER;

$coursescorm = $DB->get_record('block_blc_modules', array('cmid' => $coursemodule));

$root = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
$root = rtrim($root, '/') . '/';
$docroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/';
if (!file_exists($docroot.'/'.$apikey)) {
	mkdir($docroot.'/'.$apikey, 0777, true);
}
	
if (file_exists($docroot .$apikey)) {	
	$url = $coursescorm->scormurl;
	$fileContents = file_get_contents($url);
	$handle = fopen($docroot.$apikey.'/'.$coursescorm->scormname.'.zip', "w");
	fwrite($handle, $fileContents);
	fclose($handle);
	
	$tempurl = $root.$apikey.'/'.$coursescorm->scormname.'.zip';
	$scormurl = str_replace(' ', '%20', $tempurl);
			
	$scormcm = $DB->get_record('course_modules', array('id' => $coursemodule));

	$scorm = new stdClass();
	$scorm->course = $coursescorm->courseid;
	$scorm->coursemodule = $coursemodule;
	$scorm->instance = $scormcm->instance;
	$scorm->section = $scormcm->section;
	$scorm->module = $scormcm->module;
	$scorm->modulename = 'scorm';
	$scorm->intro       = '';
	$scorm->introformat = 1;
	$scorm->version = 'SCORM_1.2';
	$scorm->maxgrade = 100;
	$scorm->grademethod = 1;
	$scorm->maxattempt = 0;
	$scorm->width = 100;
	$scorm->height = 500;
	$scorm->scormtype = 'localsync';
	$scorm->packageurl = $scormurl;
	
	if(scorm_update_instance($scorm)){
		$scormrecord = new stdClass();
		$scormrecord->id = $coursescorm->id;
		$scormrecord->version = $version;
		$scormrecord->timemodified = time();
		$DB->update_record('block_blc_modules', $scormrecord);
	}
	unlink($docroot.$apikey.'/'.$coursescorm->scormname.'.zip');

}
rmdir($docroot.$apikey);
