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
 * Batch add accessibility documents to existing SCORM modules.
 * 
 * This script processes all SCORM modules that don't have accessibility
 * documents and creates them automatically by fetching from BLC server.
 *
 * @package    block_blc_modules
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// FIXED: Removed incorrect namespace declaration for standalone script
require_once(dirname(__FILE__).'/../../config.php');
// FIXED: Removed incorrect namespace declaration for standalone script
require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');
require_once($CFG->dirroot.'/mod/scorm/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once("$CFG->libdir/resourcelib.php");
require_once($CFG->dirroot.'/mod/resource/locallib.php');
require_once($CFG->dirroot.'/mod/resource/lib.php');

global $DB, $USER, $CFG, $OUTPUT, $PAGE;

// PRIORITY 1 FIX: Add security checks
require_login();
require_capability('moodle/site:config', context_system::instance());

// PRIORITY 1 FIX: Add confirmation step
$confirm = optional_param('confirm', 0, PARAM_INT);
$sesskey = optional_param('sesskey', '', PARAM_RAW);

if (!$confirm || !confirm_sesskey($sesskey)) {
    // Show confirmation page
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url('/blocks/blc_modules/add_doc.php');
    $PAGE->set_title(get_string('adddocs', 'block_blc_modules'));
    // $PAGE->set_heading(get_string('adddocs', 'block_blc_modules'));
    
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('adddocs', 'block_blc_modules'));
    
    // Count modules without docs
    $sql = "SELECT COUNT(*) as count FROM {block_blc_modules} bm 
            WHERE NOT EXISTS (
                SELECT 1 FROM {block_blc_modules_doc} bd 
                WHERE bd.blcmoduleid = bm.id
            )";
    $result = $DB->get_record_sql($sql);
    $modules_count = $result->count;
    
    if ($modules_count == 0) {
        echo $OUTPUT->notification(
            get_string('nomoduleswithourdocs', 'block_blc_modules'), 
            \core\output\notification::NOTIFY_INFO
        );
        echo $OUTPUT->continue_button(new moodle_url('/admin/settings.php', 
            array('section' => 'blocksettingblc_modules')));
    } else {
        $confirmurl = new moodle_url('/blocks/blc_modules/add_doc.php', 
            array('confirm' => 1, 'sesskey' => sesskey()));
        $cancelurl = new moodle_url('/admin/settings.php', 
            array('section' => 'blocksettingblc_modules'));
        
        $message = get_string('confirmbatchadddocs', 'block_blc_modules', $modules_count);
        $message .= '<br><br>' . get_string('batchprocesswarning', 'block_blc_modules');
        
        echo $OUTPUT->confirm($message, $confirmurl, $cancelurl);
    }
    
    echo $OUTPUT->footer();
    exit;
}

// PRIORITY 1 FIX: Raise time limit for batch processing
\core_php_time_limit::raise(3600); // 1 hour

// Validate configuration
$token = get_config('block_blc_modules', 'token');
$domainname = get_config('block_blc_modules', 'domainname');
$apikey = get_config('block_blc_modules', 'api_key');

// Validate configuration
$token = get_config('block_blc_modules', 'token');
$domainname = get_config('block_blc_modules', 'domainname');
$apikey = get_config('block_blc_modules', 'api_key');

// PRIORITY 1 FIX: Validate configuration exists
if (empty($token) || empty($domainname) || empty($apikey)) {
    print_error('missingconfig', 'block_blc_modules', 
        new moodle_url('/admin/settings.php', array('section' => 'blocksettingblc_modules')));
}

$resourcemodule = $DB->get_record('modules',array('name'=>'resource'));
if (!$resourcemodule) {
    print_error('resourcemodulenotfound', 'block_blc_modules');
}
$resourceid = $resourcemodule->id;

// PRIORITY 2 FIX: Use correct query logic with NOT EXISTS
$sql = "SELECT bm.* FROM {block_blc_modules} bm 
        WHERE NOT EXISTS (
            SELECT 1 FROM {block_blc_modules_doc} bd 
            WHERE bd.blcmoduleid = bm.id
        )
        ORDER BY bm.id ASC";
$blcmodules = $DB->get_records_sql($sql);

// PRIORITY 3 FIX: Initialize counters for error handling
$success_count = 0;
$fail_count = 0;
$error_messages = array();
$total_count = count($blcmodules);

if ($blcmodules) {
    // Loop through each SCORM module
    foreach($blcmodules as $blcmodule){
        // PRIORITY 3 FIX: Wrap entire processing in try-catch
        try {
            $cmid=$blcmodule->cmid;
            $course_modules = $DB->get_record('course_modules',array('id'=>$cmid,'deletioninprogress'=>0));
            
            if(!$course_modules){
                error_log("BLC add_doc: Course module $cmid not found or being deleted. Skipping.");
                continue;
            }
            if(!$course_modules){
                error_log("BLC add_doc: Course module $cmid not found or being deleted. Skipping.");
                continue;
            }
            
            $visibility = $course_modules->visible;
            $completion = $course_modules->completion;
            $blcmoduleid = $blcmodule->id;
            $section = $blcmodule->sectionid;
            $courseid = $blcmodule->courseid;
            
            // Find current section (handle if module was moved)
            $scormsection = $DB->get_record('course_sections',array('course'=>$courseid,'section'=>$section));
            
            if ($scormsection) {
                $sequence=$scormsection->sequence;
                $modules = explode(',', $sequence);
                
                if(!in_array($cmid,$modules)){
                    // Module has been moved - find current position
                    $sections = $DB->get_records('course_sections',array('course'=>$courseid));
                    foreach($sections as $sec){
                         $modules = explode(',', $sec->sequence);
                         if(in_array($cmid,$modules)){
                             $section = $sec->section;
                             break;
                         }
                    }
                }
            }
            
            // FIXED: Query local database instead of calling external web service
            // The accessibility documents are stored locally in block_scorm_access_doc table
            // Using same strategy as fetch_accessibility_document method
            
            $doc = null;
            $scorm_package = null;
            
            if (preg_match('/\/d\/([a-zA-Z0-9_-]+)\//', $blcmodule->scormurl, $matches)) {
                $driveid = $matches[1];
                error_log("BLC add_doc: Extracted Drive ID: $driveid for module $blcmoduleid");
                
                try {
                    // Find SCORM package with matching scormid
                    $scorm_package = $DB->get_record('block_scorm_package', ['scormid' => $driveid]);
                    
                    if ($scorm_package) {
                        $scormname = rtrim($scorm_package->scormname, '.zip');
                        $expected_docname = $scormname . ' (Accessibility Version).docx';
                        error_log("BLC add_doc: Looking for doc: $expected_docname");
                        
                        $doc = $DB->get_record('block_scorm_access_doc', ['docname' => $expected_docname]);
                        if ($doc) {
                            error_log("BLC add_doc: Found document by Drive ID strategy for module $blcmoduleid");
                        }
                    }
                } catch (\Exception $e) {
                    error_log("BLC add_doc: Strategy 1 failed for module $blcmoduleid: " . $e->getMessage());
                }
            }
            
            if (!$doc) {
                try {
                    // Use get_records_sql with parameter binding (Moodle DML handles TEXT columns)
                    $packages = $DB->get_records_sql(
                        "SELECT id, scormname, scormurl FROM {block_scorm_package} 
                         WHERE scormurl = :scormurl 
                         ORDER BY id DESC",
                        ['scormurl' => $blcmodule->scormurl],
                        0,
                        1
                    );
                    
                    if (!empty($packages)) {
                        $scorm_package = reset($packages);
                        $scormname = rtrim($scorm_package->scormname, '.zip');
                        $expected_docname = $scormname . ' (Accessibility Version).docx';
                        error_log("BLC add_doc: Looking for doc: $expected_docname");
                        
                        $doc = $DB->get_record('block_scorm_access_doc', ['docname' => $expected_docname]);
                        if ($doc) {
                            error_log("BLC add_doc: Found document by URL strategy for module $blcmoduleid");
                        }
                    }
                } catch (\Exception $e) {
                    error_log("BLC add_doc: Strategy 2 failed for module $blcmoduleid: " . $e->getMessage());
                }
            }
            
            if (!$scorm_package) {
                error_log("BLC add_doc: SCORM package not found in database for module $blcmoduleid. Skipping.");
                continue;
            }
            
            if (!$doc) {
                error_log("BLC add_doc: No accessibility document found in local database for module $blcmoduleid. Skipping.");
                continue;
            }
            
            error_log("BLC add_doc: Found accessibility document in local database for module $blcmoduleid");
            
            // Extract document information from database
            $docname = rtrim($doc->docname, ".docx");
            $docversion = isset($doc->version) ? $doc->version : '1';
            $docid = $doc->id;
            
            // Use docurlplus if available (Google Drive URL), otherwise use docurl
            if (!empty($doc->docurlplus)) {
                $docurl = $doc->docurlplus;
                error_log("BLC add_doc: Using docurlplus for module $blcmoduleid");
            } else if (!empty($doc->docurl)) {
                $docurl = $doc->docurl;
                error_log("BLC add_doc: Using docurl for module $blcmoduleid");
            } else {
                error_log("BLC add_doc: No valid URL found for document in module $blcmoduleid. Skipping.");
                continue;
            }
            
            // Validate we have all required data
            if (empty($docname) || empty($docurl) || $docid == 0) {
                error_log("BLC add_doc: Invalid document data for module $blcmoduleid. Skipping.");
                continue;
            }
            
            // Get section info
            $scormsection = $DB->get_record('course_sections',array('course'=>$courseid,'section'=>$section));
            if (!$scormsection) {
                error_log("BLC add_doc: Section not found for module $blcmoduleid. Skipping.");
                $fail_count++;
                $error_messages[] = "Module $blcmoduleid: Section not found";
                continue;
            }
            $sectionid=$scormsection->id;

            // Create course module
            $newcm = new stdClass();
            $newcm->course = $courseid;
            $newcm->module = $resourceid;
            $newcm->section = $sectionid;
            $newcm->instance = 0;
            $newcm->visible = $visibility;
            $newcm->visibleold = 1;
            $newcm->visibleoncoursepage = ($visibility > 0) ? 1 : 0;
            $newcm->groupmode = 0;
            $newcm->groupingid = 0;
            $newcm->completion = 0;
            $newcm->availability = null;
            $newcm->showdescription = 0;

            $resourcecoursemodule = add_course_module($newcm);
            if (!$resourcecoursemodule) {
                error_log("BLC add_doc: Failed to create course module for blcmodule $blcmoduleid");
                $fail_count++;
                $error_messages[] = "Module $blcmoduleid: Could not create course module";
                continue;
            }

            // Create resource instance
            $resourceinstance = new stdClass();
            $resourceinstance->course = $courseid;
            $resourceinstance->coursemodule = $resourcecoursemodule;
            
            // Ensure docname is not empty
            if (empty($docname)) {
                $docname = 'Accessibility Document';
            }
            $resourceinstance->name = $docname;
            $resourceinstance->intro = '';
            $resourceinstance->introformat = 1;
            $resourceinstance->completionexpected = 0;	
            
            $config = get_config('resource');
            $resourceinstance->display = isset($config->display) ? $config->display : 0;
            $resourceinstance->popupheight = isset($config->popupheight) ? $config->popupheight : 620;
            $resourceinstance->popupwidth = isset($config->popupwidth) ? $config->popupwidth : 620;
            $resourceinstance->printintro = isset($config->printintro) ? $config->printintro : 1;
            $resourceinstance->showsize = isset($config->showsize) ? $config->showsize : 0;
            $resourceinstance->showtype = isset($config->showtype) ? $config->showtype : 0;
            $resourceinstance->showdate = isset($config->showdate) ? $config->showdate : 0;
            $resourceinstance->filterfiles = isset($config->filterfiles) ? $config->filterfiles : 0;
            $resourceinstance->timemodified = time();

            resource_set_display_options($resourceinstance);
            $id = $DB->insert_record('resource', $resourceinstance);

            // Update course module with instance ID
            $DB->set_field('course_modules', 'instance', $id, array('id'=>$resourcecoursemodule));

            $completiontimeexpected = !empty($resourceinstance->completionexpected) ? $resourceinstance->completionexpected : null;
            \core_completion\api::update_completion_date_event($resourcecoursemodule, 'resource', $id, $completiontimeexpected);

            $filepath = $docurl;
            $file_name = $docname.'.docx';
            $fs = get_file_storage(); 
            $context = context_module::instance($resourcecoursemodule);
            
            $filerecord = array(
                'contextid' => $context->id,
                'component' => 'mod_resource',
                'filearea' => 'content',
                'userid' => $USER->id,
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $file_name
            );

            try {
                $file = $fs->create_file_from_url($filerecord, $filepath);
                if (!$file) {
                    throw new \Exception('File download failed - create_file_from_url returned false');
                }
                error_log("BLC add_doc: Successfully downloaded file for module $blcmoduleid");
            } catch (\Exception $e) {
                error_log("BLC add_doc: Failed to download file for module $blcmoduleid: " . $e->getMessage());
                // Continue - module created but file missing
                // Admin can manually upload file later
            }

            // Update section sequence
            $record = new stdClass();
            $record->id = $sectionid;
            
            if(!empty($scormsection->sequence)){
                $modules = explode(',', $scormsection->sequence);
                $newmodules = array();
                
                foreach($modules as $key=>$value){
                    if($value == $cmid){
                        $newmodules[] = $value;
                        $newmodules[] = $resourcecoursemodule;
                    } else {
                        $newmodules[] = $value;
                    }
                }
                
                $record->sequence = implode(',', $newmodules);
            } else {
                $record->sequence = $resourcecoursemodule;
            }

            $DB->update_record('course_sections', $record);

            // Save tracking record
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

            // Note: No cleanup needed as we're querying local database, not using web service temporary files
            $success_count++;
            error_log("BLC add_doc: Successfully processed module $blcmoduleid");
            
        } catch (\Exception $e) {

            $fail_count++;
            $error_messages[] = "Module " . $blcmodule->id . ": " . $e->getMessage();
            error_log("BLC add_doc: Unexpected error processing module " . $blcmodule->id . ": " . $e->getMessage());
            continue;
        }
    } // End foreach loop
} // End if $blcmodules} // End if $blcmodules

// Count how many were skipped because no doc available
$skipped_nodoc = $total_count - $success_count - $fail_count;

$redirect = new moodle_url('/admin/settings.php', array('section' => 'blocksettingblc_modules'));

if ($total_count == 0) {
    $message = get_string('nomoduleswithourdocs', 'block_blc_modules');
    $notifytype = \core\output\notification::NOTIFY_INFO;
} else if ($success_count > 0 && $fail_count == 0) {
    $message = get_string('batchaddsuccess', 'block_blc_modules', $success_count);
    if ($skipped_nodoc > 0) {
        $message .= ' ' . get_string('skippednodoc', 'block_blc_modules', $skipped_nodoc);
    }
    $notifytype = \core\output\notification::NOTIFY_SUCCESS;
} else if ($success_count > 0 && $fail_count > 0) {
    $message = get_string('batchaddpartial', 'block_blc_modules', 
        array('success' => $success_count, 'failed' => $fail_count));
    if ($skipped_nodoc > 0) {
        $message .= ' ' . get_string('skippednodoc', 'block_blc_modules', $skipped_nodoc);
    }
    $notifytype = \core\output\notification::NOTIFY_WARNING;
} else if ($success_count == 0 && $fail_count > 0) {
    $message = get_string('batchaddfailed', 'block_blc_modules', $fail_count);
    $notifytype = \core\output\notification::NOTIFY_ERROR;
} else {
    // All skipped because no docs available
    $message = get_string('nodocsfound', 'block_blc_modules', $skipped_nodoc);
    $notifytype = \core\output\notification::NOTIFY_INFO;
}

// Log summary
error_log("BLC add_doc: Batch processing completed. Total: $total_count, Success: $success_count, Failed: $fail_count, Skipped (no doc): $skipped_nodoc");
if (!empty($error_messages)) {
    error_log("BLC add_doc: Errors: " . implode('; ', array_slice($error_messages, 0, 10)));
}

redirect($redirect, $message, null, $notifytype);
