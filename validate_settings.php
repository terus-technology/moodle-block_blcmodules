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
 * This file  will validate the settings.
 *
 * @package    block_blc_modules
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 

require_once(dirname(__FILE__).'/../../config.php');
require_once('curl.php');
global $DB, $USER, $CFG;
require_login(null, false);

// PERMISSION.
require_capability('moodle/user:viewdetails', context_system::instance(), $USER->id);

$title = get_string('pluginname', 'block_blc_modules');
$heading = $SITE->fullname;
$url = '/blocks/blc_modules/validate_settings.php';

$baseurl = new moodle_url($url);

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_context(context_system::instance());
$PAGE->set_title("Validate BLC Settings");
$PAGE->set_heading($heading);
$PAGE->set_cacheable(false);
// $PAGE->requires->jquery();

echo $OUTPUT->header();

$requesturi = $CFG->wwwroot;	
$apikey = get_config('block_blc_modules', 'api_key');

$token = get_config('block_blc_modules', 'token');

$domainname = get_config('block_blc_modules', 'domainname');
$function_name = 'validate_blc_settings';
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
 . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi;
$curl = new blccurl;
$curl->setHeader('Content-Type: application/json; charset=utf-8');

$responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));

$apisuccess = '<div class="alert alert-success alert-block fade in " role="alert">
				'.get_string('apisuccess', 'block_blc_modules').'</div>';

$urlsuccess = '<div class="alert alert-success alert-block fade in " role="alert">
    '.get_string('urlsuccess', 'block_blc_modules').' </div>';

$apifail = '<div class="alert alert-warning alert-block fade in " role="alert">
     '.get_string('apifail', 'block_blc_modules').' </div>';

$urlfail = '<div class="alert alert-warning alert-block fade in " role="alert">
         '.get_string('urlfail', 'block_blc_modules').' </div>';

$successboth =get_string('successboth', 'block_blc_modules');

$failboth = get_string('failboth', 'block_blc_modules');

$failone = get_string('failone', 'block_blc_modules');

$returntosettings = '<div class="row">
                <div class="col-sm-3" >
				<button onclick="window.location.href = \''.$CFG->wwwroot.'/blocks/blc_modules/validate_settings.php\';" class="btn btn-primary" >'.get_string('refresh', 'block_blc_modules').'</button>
				</div>
                <div class="col-sm-3" >    
                    <button onclick="window.location.href = \''.$CFG->wwwroot.'/admin/settings.php?section=blocksettingblc_modules\';" class="btn btn-secondary" >'.get_string('return', 'block_blc_modules').'</button>
                </div>
            </div>';

$bothresponses = explode(",",$responses);
$urlokay = $bothresponses[0];
$apiokay = $bothresponses[1];

if(strpos($urlokay, 'true') !== false && strpos($apiokay, 'true') !== false) {
    echo $apisuccess . "<br />" . $urlsuccess . "<br />";
    echo $successboth;
    echo $returntosettings;

}else if(strpos($urlokay, 'true') !== false){
    echo $urlsuccess . "<br />" . $apifail . "<br />";
    echo $failone;	
    echo $returntosettings;

}else if(strpos($apiokay, 'true') !== false){
    echo $apisuccess . "<br />" . $urlfail . "<br />";
    echo $failone;	
    echo $returntosettings;

}else if(strpos($urlokay, 'true') == false && strpos($apiokay, 'true') == false){
	echo $apifail . "<br />" . $urlfail . "<br />";
	echo $failboth;	
	echo $returntosettings;
}

echo $OUTPUT->footer();
