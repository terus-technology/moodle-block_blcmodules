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
 * Strings for component 'block_blc_modules', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   block_blc_modules
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['blc_modules:addinstance'] = 'Add a new BLC Moodle block';
$string['pluginname'] = 'BLC Modules';
$string['privacy:metadata'] = 'The scorm block only shows data stored in other locations.';
$string['api_key'] = 'API Key';
$string['api_key_desc'] = 'If you are unsure where to get this, please refer to the <a href="https://blc.howcollege.ac.uk/blocks/scorm_package/guide.php">BLC Plugin Guide</a>.<br/> You can also test that your block is properly configured using <a href="'.$CFG->wwwroot.'/blocks/blc_modules/validate_settings.php">this tool</a>.';
$string['token'] = 'Token';
$string['token_desc'] = 'Web Service Token.';
$string['domainname'] = 'Domain Name';
$string['domainname_desc'] = 'The BLC Moodle URL. We recommend you do not change this.';
$string['blc_modules:viewblock'] = 'BLC Module Picker viewblock';
$string['token_value'] = 'd623555b36cb7e3db03cd06178ccb284';
$string['webserviceaddress'] = 'https://blc.howcollege.ac.uk';
$string['updatescorm'] = 'To update BLC Modules in bulk, please click <a href="'.$CFG->wwwroot.'/blocks/blc_modules/bulk_update.php">Here</a>.';
$string['updatescormmesage'] =  'Successfully upated';
$string['updatedoc'] = 'To automatically add an accessibility document under each BLC Module, please click <a href="'.$CFG->wwwroot.'/blocks/blc_modules/add_doc.php">Here</a>.';
$string['updatedocmesage'] =  'Successfully added accessibility documents';
$string['apisuccess'] =  'Success: Your <b>API Key</b> is configured correctly.';
$string['apifail'] =  'Failure: Your <b>API Key</b> is not configured correctly.';
$string['urlsuccess'] =  'Success: Your <b>Moodle URL</b> is configured correctly.';
$string['urlfail'] =  'Failure: Your <b>Moodle URL</b> is not configured correctly.';
$string['refresh'] =  'Refresh';
$string['return'] =  'Return to Settings';
$string['failone'] =  '

<h2>Configuration problem</h2>
<p>There is a problem with some of your settings. We suggest revisiting <a href="blc.howcollege.ac.uk">blc.howcollege.ac.uk</a> and checking the details. If you continue to experience issues, please contact us at <a href="mailto:blc@howcollege.ac.uk">blc@howcollege.ac.uk</a></p>

';
$string['failboth'] =  '

<h2>Configuration problem</h2>
<p>There is a problem with both your API key and your URL. We suggest revisiting <a href="blc.howcollege.ac.uk">blc.howcollege.ac.uk</a> and rechecking the details. If you continue to experience issues, please contact us at <a href="mailto:blc@howcollege.ac.uk">blc@howcollege.ac.uk</a></p>

';
$string['successboth'] =  '

<h2>Fully Working</h2>
<p>We can\'t see anything wrong with your configuration. If you experience any issues, please contact us at <a href="mailto:blc@howcollege.ac.uk">blc@howcollege.ac.uk</a></p>

';
$string['updatescormconfirm'] =  'Are you sure?';
$string['updateconfirmmessage'] =  'Please be aware that updating SCORM packages can have implications for in-progress attempts.<br/> Students may loose their progress, and need to restart the module. They may also continue to be served the old version.<br/>It may be a good idea to do this during a strategic time in the academic year.<br/>We will try our best to track down and update all modules, however this may not always be possible, especially if a module has moved, been duplicated or renamed. ';
$string['failupdatescormmesage'] =  'The server is currently busy updating another BLC college. Please try again later.';


