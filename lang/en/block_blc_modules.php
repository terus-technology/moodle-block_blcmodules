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
$string['api_key_desc'] = 'If you are unsure where to get this, please refer to the <a href="https://blc.howcollege.ac.uk/blocks/scorm_package/guide.php">BLC Plugin Guide</a>.<br/> You can also test that your block is properly configured using <a href="{$a->wwwroot}/blocks/blc_modules/validate_settings.php">this tool</a>.';
$string['token'] = 'Token';
$string['token_desc'] = 'Web Service Token.';
$string['domainname'] = 'Domain Name';
$string['domainname_desc'] = 'The BLC Moodle URL. We recommend you do not change this.';
$string['blc_modules:viewblock'] = 'BLC Module Picker viewblock';
$string['token_value'] = 'd623555b36cb7e3db03cd06178ccb284';
$string['webserviceaddress'] = 'https://blc.howcollege.ac.uk';
$string['updatescorm'] = '<h4>Updates</h4>To update BLC Modules in bulk, please click <a href="{$a->wwwroot}/blocks/blc_modules/bulk_update.php">Here</a>.';
$string['updatescormmesage'] =  'Successfully updated';
$string['updatedoc'] = 'To automatically add an accessibility document under each BLC Module, please click <a href="{$a->wwwroot}/blocks/blc_modules/add_doc.php">Here</a>.';
$string['updatedocmesage'] =  'Successfully added accessibility documents';
$string['adddocs'] = 'Add Accessibility Documents';
$string['confirmbatchadddocs'] = 'This will create accessibility documents for {$a} SCORM modules that do not have them yet. Do you want to continue?';
$string['batchprocesswarning'] = '<strong>Warning:</strong> This process may take several minutes depending on the number of modules. Please do not close this window until the process is complete.';
$string['nomoduleswithourdocs'] = 'All SCORM modules already have accessibility documents.';
$string['batchaddsuccess'] = 'Successfully added {$a} accessibility documents.';
$string['batchaddpartial'] = 'Added {$a->success} accessibility documents successfully. {$a->failed} modules failed.';
$string['batchaddfailed'] = 'Failed to add accessibility documents. {$a} modules had errors.';
$string['skippednodoc'] = '{$a} modules were skipped because no accessibility documents are available on the BLC server.';
$string['nodocsfound'] = 'No accessibility documents were added. {$a} modules do not have accessibility documents available on the BLC server.';
$string['missingconfig'] = 'Plugin configuration is incomplete. Please configure the API key, token, and domain name.';
$string['resourcemodulenotfound'] = 'Resource module not found. Please ensure the Resource activity module is installed and enabled.';
$string['apisuccess'] =  'Success: Your <b>API Key</b> is configured correctly.';
$string['apifail'] =  'Failure: Your <b>API Key</b> is not configured correctly.';
$string['urlsuccess'] =  'Success: Your <b>Moodle URL</b> is configured correctly.';
$string['urlfail'] =  'Failure: Your <b>Moodle URL</b> is not configured correctly.';
$string['refresh'] =  'Refresh';
$string['return'] =  'Return to Settings';
$string['validatesettings'] = 'Validate BLC Settings';
$string['failone'] =  '

<h2>Configuration problem</h2>
<p>There is a problem with some of your settings. We suggest revisiting <a href="https://blc.howcollege.ac.uk">blc.howcollege.ac.uk</a> and checking the details. If you continue to experience issues, please contact us at <a href="mailto:blc@howcollege.ac.uk">blc@howcollege.ac.uk</a></p>

';
$string['failboth'] =  '

<h2>Configuration problem</h2>
<p>There is a problem with both your API key and your URL. We suggest revisiting <a href="https://blc.howcollege.ac.uk">blc.howcollege.ac.uk</a> and rechecking the details. If you continue to experience issues, please contact us at <a href="mailto:blc@howcollege.ac.uk">blc@howcollege.ac.uk</a></p>

';
$string['successboth'] =  '

<h2>Fully Working</h2>
<p>We can\'t see anything wrong with your configuration. If you experience any issues, please contact us at <a href="mailto:blc@howcollege.ac.uk">blc@howcollege.ac.uk</a></p>

';
$string['updatescormconfirm'] =  'Are you sure?';
$string['updateconfirmmessage'] =  'Please be aware that updating SCORM packages can have implications for in-progress attempts.<br/> Students may loose their progress, and need to restart the module. They may also continue to be served the old version.<br/>It may be a good idea to do this during a strategic time in the academic year.<br/>We will try our best to track down and update all modules, however this may not always be possible, especially if a module has moved, been duplicated or renamed. ';
$string['failupdatescormmesage'] =  'The server is currently busy updating another BLC college. Please try again later.';
$string['scormreport'] = '<h4>Report</h4>Click <a href="{$a->wwwroot}/blocks/blc_modules/scorm_report.php">here</a> to access a report detailing the usage of BLC modules and accessibility documents added via this plugin.';
$string['scormreports'] = 'Report';
$string['resource'] = 'Resource';
$string['usage'] = 'Total';
$string['subject'] = 'Subject';
$string['blcmodules'] = 'BLC Modules';
$string['accessdoc'] = 'Accessibility Documents';
$string['blcresource'] = 'BLC Resources';
$string['failedtocreatefile'] = 'Failed to create accessibility document file: {$a}';
$string['scormreportchart'] = 'Scrom Report Chart';
$string['blcresourceinfo'] = 'Below you will find some basic statistics on BLC Resource used across your Moodle.<br/><br/>';
$string['countbycourse'] = 'BLC Modules by course';
$string['topcourses'] = 'Here are the top courses, making best use of BLC modules.';
$string['course'] = 'Course';
$string['countbysubject'] = 'BLC Modules by subject';
$string['topsubjects'] = 'Here are the top 5 subjects making best use of BLC resources.';
$string['versioncheckerror'] = 'Something went wrong while checking for updates. Please try again later.';
$string['versioncheckjsonerror'] = 'something wrong js while checking for update';
$string['googledriveapierror'] = 'Google Drive API error: {$a}';
$string['googledrivedownloaderror'] = 'Error downloading from Google Drive: {$a}';
$string['googledrivestreamingerror'] = 'Error streaming from Google Drive: {$a}';
$string['googleapinotinstalled'] = 'Google API library not installed. Please run composer install in blocks/scorm_package/';
$string['googlecredentialsnotfound'] = 'Google credentials file not found: {$a}';
$string['invalidpackageurl'] = 'Invalid package URL: {$a}';
$string['scormfilenotaccessible'] = 'SCORM file not accessible in Google Drive. The file may have been deleted, moved, or sharing permissions may have changed.';

// SCORM Load Log Viewer strings (NEW).
$string['scormloadlog'] = '<h4>SCORM Load Logs</h4>Click <a href="{$a->wwwroot}/blocks/blc_modules/scorm_load_log.php">here</a> to view detailed logs of SCORM loading processes.<h4>Plugin Settings</h4><br/>';
$string['scormloadlogs'] = 'SCORM Load Logs';
$string['filterlog'] = 'Filter Logs';
$string['loglevel'] = 'Log Level';
$string['clearfilter'] = 'Clear Filter';
$string['statistics'] = 'Statistics';
$string['totallogs'] = 'Total Logs';
$string['successlogs'] = 'Success';
$string['errorlogs'] = 'Errors';
$string['warninglogs'] = 'Warnings';
$string['infologs'] = 'Info';
$string['logentries'] = 'Log Entries';
$string['scormmodule'] = 'SCORM Module';
$string['level'] = 'Level';
$string['status'] = 'Status';
$string['nologsfound'] = 'No logs found';
$string['nologsfounddesc'] = 'There are no SCORM load logs matching your filter criteria. Try adjusting your filters or load some SCORM modules to generate logs.';
$string['session'] = 'Session';
$string['sessions'] = 'Sessions';
$string['viewparameters'] = 'View Parameters';
$string['parameter'] = 'Parameter';
$string['value'] = 'Value';
$string['time'] = 'Time';
$string['unknown'] = 'Unknown';
$string['info'] = 'Info';
$string['success'] = 'Success';
$string['warning'] = 'Warning';
$string['error'] = 'Error';
$string['all'] = 'All';
$string['filter'] = 'Filter';
?>