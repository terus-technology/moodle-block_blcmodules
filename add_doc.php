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
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_blc_modules\helper\blccurl_helper;
use core\output\notification;
use core_completion\api;

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');
require_once($CFG->dirroot.'/mod/scorm/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once("$CFG->libdir/resourcelib.php");
require_once($CFG->dirroot.'/mod/resource/locallib.php');
require_once($CFG->dirroot.'/mod/resource/lib.php');

global $DB, $USER, $CFG, $OUTPUT, $PAGE;

// Add security checks.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Add confirmation step.
$confirm = optional_param('confirm', 0, PARAM_INT);
$sesskey = optional_param('sesskey', '', PARAM_RAW);

if (!$confirm || !confirm_sesskey($sesskey)) {
    // Show confirmation page.
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url('/blocks/blc_modules/add_doc.php');
    $PAGE->set_title(get_string('adddocs', 'block_blc_modules'));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('adddocs', 'block_blc_modules'));

    // Count modules without docs.
    $sql = "
        SELECT COUNT(*) as count
        FROM {block_blc_modules} bm
        WHERE NOT EXISTS (
            SELECT 1 FROM {block_blc_modules_doc} bd
            WHERE bd.blcmoduleid = bm.id
        )
    ";
    $result = $DB->get_record_sql($sql);
    $modulescount = $result->count;

    if ($modulescount == 0) {
        echo $OUTPUT->notification(
            get_string('nomoduleswithourdocs', 'block_blc_modules'),
            notification::NOTIFY_INFO
        );
        echo $OUTPUT->continue_button(
            new moodle_url('/admin/settings.php', ['section' => 'blocksettingblc_modules'])
        );
    } else {
        $confirmurl = new moodle_url('/blocks/blc_modules/add_doc.php', [
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        $cancelurl = new moodle_url('/admin/settings.php', ['section' => 'blocksettingblc_modules']);

        $message = get_string('confirmbatchadddocs', 'block_blc_modules', $modulescount);
        $message .= '<br><br>' . get_string('batchprocesswarning', 'block_blc_modules');

        echo $OUTPUT->confirm($message, $confirmurl, $cancelurl);
    }

    echo $OUTPUT->footer();
    exit;
}

// Raise time limit for batch processing.
core_php_time_limit::raise(3600); // 1 hour.

// Validate configuration.
$token = get_config('block_blc_modules', 'token');
$domainname = get_config('block_blc_modules', 'domainname');
$apikey = get_config('block_blc_modules', 'api_key');

// Validate configuration exists.
if (empty($token) || empty($domainname) || empty($apikey)) {
    throw new moodle_exception('missingconfig', 'block_blc_modules');
}

$resourcemodule = $DB->get_record('modules', ['name' => 'resource']);
if (!$resourcemodule) {
    throw new moodle_exception('resourcemodulenotfound', 'block_blc_modules');
}
$resourceid = $resourcemodule->id;

// IMPROVED QUERY: Check for modules without docs AND ensure SCORM module still exists.
$sql = "
    SELECT bm.* FROM {block_blc_modules} bm
    WHERE NOT EXISTS (
        SELECT 1 FROM {block_blc_modules_doc} bd
        WHERE bd.blcmoduleid = bm.id
    )
    AND EXISTS (
        SELECT 1 FROM {course_modules} cm
        WHERE cm.id = bm.cmid AND cm.deletioninprogress = 0
    )
    ORDER BY bm.id ASC
";
$blcmodules = $DB->get_records_sql($sql);

// Initialize counters for error handling.
$successcount = 0;
$failcount = 0;
$errormessages = [];
$totalcount = count($blcmodules);

if ($blcmodules) {
    // Loop through each SCORM module.
    foreach ($blcmodules as $blcmodule) {
        // Wrap entire processing in try-catch.
        try {
            // BUGFIX 2025-11-04: Reset all variables to prevent stale data reuse across iterations
            // This prevents the last successfully downloaded document from being reused for modules
            // that fail to fetch their own document data from the API.
            $docdata = null;
            $docname = null;
            $docversion = null;
            $docid = null;
            $docurl = null;
            $filepath = null;
            $filename = null;
            $filerecord = null;
            $file = null;

            $cmid = $blcmodule->cmid;
            $coursemodules = $DB->get_record('course_modules', ['id' => $cmid, 'deletioninprogress' => 0]);

            if (!$coursemodules) {
                debugging("BLC add_doc: Course module $cmid not found or being deleted. Skipping.", DEBUG_DEVELOPER);
                continue;
            }

            $visibility = $coursemodules->visible;
            $completion = $coursemodules->completion;
            $blcmoduleid = $blcmodule->id;
            $section = $blcmodule->sectionid;
            $courseid = $blcmodule->courseid;

            // Find current section (handle if module was moved).
            $scormsection = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $section]);

            if ($scormsection) {
                $sequence = $scormsection->sequence;
                $modules = explode(',', $sequence);

                if (!in_array($cmid, $modules)) {
                    // Module has been moved - find current position.
                    $sections = $DB->get_records('course_sections', ['course' => $courseid]);
                    foreach ($sections as $sec) {
                        $modules = explode(',', $sec->sequence);
                        if (in_array($cmid, $modules)) {
                            $section = $sec->section;
                            break;
                        }
                    }
                }
            }

            // Use API to fetch accessibility document data (consistent with blcservice.php)
            // This calls local_scormurl_get_tempdocurls on the API server.

            debugging("BLC add_doc: Calling API to fetch document for module $blcmoduleid", DEBUG_DEVELOPER);

            $docdata = null;

            try {
                $functionname = 'local_scormurl_get_tempdocurls';

                $serverurl = new moodle_url($domainname . '/webservice/rest/server.php', [
                    'wstoken' => $token,
                    'wsfunction' => $functionname,
                    'apikey' => $apikey,
                    'scormurl' => $blcmodule->scormurl,
                    'moodlewsrestformat' => 'json',
                ]);

                debugging("BLC add_doc: API URL: $serverurl", DEBUG_DEVELOPER);

                $curl = new blccurl_helper();
                $curl->set_header('Content-Type: application/json; charset=utf-8');

                $responses = $curl->post($serverurl, '', ['CURLOPT_FAILONERROR' => true]);

                debugging("BLC add_doc: API Response: " . substr($responses, 0, 200), DEBUG_DEVELOPER);

                $jsondata = json_decode($responses, true);

                if (empty($jsondata)) {
                    debugging("BLC add_doc: Empty or invalid JSON response from API for module $blcmoduleid", DEBUG_DEVELOPER);
                } else if (isset($jsondata['exception'])) {
                    debugging(
                        "BLC add_doc: API returned exception for module $blcmoduleid: " . $jsondata['message'],
                        DEBUG_DEVELOPER
                    );
                } else if (!isset($jsondata['docname']) || empty($jsondata['docname'])) {
                    debugging(
                        "BLC add_doc: API response missing docname or docname is empty for module $blcmoduleid",
                        DEBUG_DEVELOPER
                    );
                } else {
                    // Success - we have valid document data.
                    $docobject = (object) $jsondata;

                    // Prefer docurlplus (permanent Google Drive URL) over tempdocurl.
                    $downloadurl = !empty($docobject->docurlplus) ? $docobject->docurlplus :
                                (!empty($docobject->tempdocurl) ? $docobject->tempdocurl :
                                (!empty($docobject->docurl) ? $docobject->docurl : ''));

                    if (!empty($downloadurl)) {
                        $docdata = [
                            'docname' => rtrim($docobject->docname ?? '', '.docx'),
                            'docversion' => $docobject->version ?? '1',
                            'docid' => $docobject->id ?? 0,
                            'docurl' => $downloadurl,
                        ];

                        debugging(
                            "BLC add_doc: Successfully fetched document data from API for module $blcmoduleid: " .
                            $docdata['docname'],
                            DEBUG_DEVELOPER
                        );
                    } else {
                        debugging("BLC add_doc: No valid URL in API response for module $blcmoduleid", DEBUG_DEVELOPER);
                    }
                }
            } catch (Exception $e) {
                debugging("BLC add_doc: API call failed for module $blcmoduleid: " . $e->getMessage(), DEBUG_DEVELOPER);
            }

            if (!$docdata) {
                debugging(
                    "BLC add_doc: No accessibility document data available for module $blcmoduleid. Skipping.",
                    DEBUG_DEVELOPER
                );
                continue;
            }

            // Extract document information from API response.
            $docname = $docdata['docname'];
            $docversion = $docdata['docversion'];
            $docid = $docdata['docid'];
            $docurl = $docdata['docurl'];

            debugging("BLC add_doc: Using document - Name: $docname, Version: $docversion, ID: $docid", DEBUG_DEVELOPER);

            // Get section info.
            $scormsection = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $section]);
            if (!$scormsection) {
                debugging("BLC add_doc: Section not found for module $blcmoduleid. Skipping.", DEBUG_DEVELOPER);
                $failcount++;
                $errormessages[] = "Module $blcmoduleid: Section not found";
                continue;
            }
            $sectionid = $scormsection->id;

            // ENHANCED DUPLICATE CHECK - Check BEFORE creating new module.

            // Check 1: Does tracking record already exist?
            $existingtracking = $DB->get_records('block_blc_modules_doc', ['blcmoduleid' => $blcmoduleid]);

            if ($existingtracking) {
                debugging(
                    "BLC add_doc: Module $blcmoduleid already has tracking record. Skipping duplicate creation.",
                    DEBUG_DEVELOPER
                );
                $successcount++;
                continue;
            }

            // Check 2: Does a resource module with matching name already exist in this section?
            if (!empty($scormsection->sequence)) {
                $sequencecmids = explode(',', $scormsection->sequence);

                if (!empty($sequencecmids)) {
                    // Get all resource modules in this section.
                    [$insql, $params] = $DB->get_in_or_equal($sequencecmids, SQL_PARAMS_NAMED);
                    $params['modulename'] = 'resource';
                    $params['courseid'] = $courseid;

                    $sql = "SELECT cm.id as cmid, cm.instance, r.name
                            FROM {course_modules} cm
                            JOIN {modules} m ON m.id = cm.module
                            JOIN {resource} r ON r.id = cm.instance
                            WHERE cm.id $insql
                              AND m.name = :modulename
                              AND cm.course = :courseid
                              AND cm.deletioninprogress = 0";

                    $existingresources = $DB->get_records_sql($sql, $params);

                    // Check if document with matching name already exists.
                    foreach ($existingresources as $resource) {
                        $resourcenamelower = strtolower(trim($resource->name));
                        $docnamelower = strtolower(trim($docname));

                        // Match if:
                        // 1. Exact match, OR
                        // 2. Resource contains docname, OR
                        // 3. Both contain "accessibility" keyword.
                        $ismatch = false;

                        if ($resourcenamelower === $docnamelower) {
                            $ismatch = true; // Exact match.
                        } else if (strpos($resourcenamelower, $docnamelower) !== false) {
                            $ismatch = true; // Resource name contains docname.
                        } else if (strpos($docnamelower, $resourcenamelower) !== false) {
                            $ismatch = true; // Docname contains resource name.
                        } else if (
                            strpos($resourcenamelower, 'accessibility') !== false &&
                            strpos($docnamelower, 'accessibility') !== false
                        ) {
                            // Both contain "accessibility" - check if same subject.
                            // Remove common generic words before comparing.
                            $genericwords = ['accessibility', 'version', 'document', 'file', 'resource', 'the', 'and', 'for'];

                            $resourcewords = array_filter(explode(' ', $resourcenamelower), function ($w) use ($genericwords) {
                                $w = trim($w, '()');
                                return strlen($w) > 3 && !in_array($w, $genericwords);
                            });
                            $docwords = array_filter(explode(' ', $docnamelower), function ($w) use ($genericwords) {
                                $w = trim($w, '()');
                                return strlen($w) > 3 && !in_array($w, $genericwords);
                            });

                            $commonwords = array_intersect($resourcewords, $docwords);
                            // Need at least 3 significant subject words to match (e.g., "academic", "reading", "skills").
                            if (count($commonwords) >= 3) {
                                $ismatch = true;
                            }
                        }

                        if ($ismatch) {
                            debugging(
                                "BLC add_doc: Found existing resource module '{$resource->name}' (cmid: {$resource->cmid}) " .
                                "for blcmodule $blcmoduleid. Creating tracking record instead of duplicate.",
                                DEBUG_DEVELOPER
                            );

                            // Create tracking record for existing module.
                            $tracking = new stdClass();
                            $tracking->userid = $USER->id;
                            $tracking->courseid = $courseid;
                            $tracking->blcmoduleid = $blcmoduleid;
                            $tracking->sectionid = $section;
                            $tracking->cmid = $resource->cmid;
                            $tracking->scormid = $docid;
                            $tracking->scormurl = $docurl;
                            $tracking->version = $docversion;
                            $tracking->timecreated = time();
                            $tracking->timemodified = time();

                            $DB->insert_record('block_blc_modules_doc', $tracking);
                            debugging(
                                "BLC add_doc: Successfully created tracking record for existing module
                                (blcmoduleid: $blcmoduleid, cmid: {$resource->cmid})",
                                DEBUG_DEVELOPER
                            );

                            $successcount++;
                            continue 2; // Skip to next blcmodule in foreach loop.
                        }
                    }
                }
            }

            // If we reach here, no duplicate found - proceed with module creation.
            debugging(
                "BLC add_doc: No existing document found for module $blcmoduleid. Proceeding with creation.",
                DEBUG_DEVELOPER
            );

            // END ENHANCED DUPLICATE CHECK.

            // Create course module.
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
                debugging("BLC add_doc: Failed to create course module for blcmodule $blcmoduleid", DEBUG_DEVELOPER);
                $failcount++;
                $errormessages[] = "Module $blcmoduleid: Could not create course module";
                continue;
            }

            // Create resource instance.
            $resourceinstance = new stdClass();
            $resourceinstance->course = $courseid;
            $resourceinstance->coursemodule = $resourcecoursemodule;

            // Ensure docname is not empty.
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

            // Update course module with instance ID.
            $DB->set_field('course_modules', 'instance', $id, ['id' => $resourcecoursemodule]);

            $completiontimeexpected = !empty($resourceinstance->completionexpected) ? $resourceinstance->completionexpected : null;
            api::update_completion_date_event($resourcecoursemodule, 'resource', $id, $completiontimeexpected);

            $filepath = $docurl;
            $filename = $docname.'.docx';
            $fs = get_file_storage();
            $context = context_module::instance($resourcecoursemodule);

            $filerecord = [
                'contextid' => $context->id,
                'component' => 'mod_resource',
                'filearea' => 'content',
                'userid' => $USER->id,
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename,
            ];

            // Use file_helper for proper Google Drive URL handling.
            try {
                if (strpos($filepath, '/pluginfile.php/') !== false) {
                    // Use the file helper to create from pluginfile URL.
                    debugging('BLC add_doc: Using pluginfile URL method for module ' . $blcmoduleid, DEBUG_DEVELOPER);
                    $file = \block_blc_modules\helper\file_helper::create_file_from_pluginfile_url($fs, $filerecord, $filepath);
                } else {
                    // For external URLs (including Google Drive), use the enhanced download method.
                    debugging('BLC add_doc: Using external URL method for module ' . $blcmoduleid, DEBUG_DEVELOPER);
                    $file = \block_blc_modules\helper\file_helper::create_file_from_external_url($fs, $filerecord, $filepath);
                }

                if (!$file) {
                    throw new Exception('File download failed - file_helper returned false');
                }
                debugging("BLC add_doc: Successfully downloaded file for module $blcmoduleid using file_helper", DEBUG_DEVELOPER);
            } catch (Exception $e) {
                debugging("BLC add_doc: Failed to download file for module $blcmoduleid: " . $e->getMessage(), DEBUG_DEVELOPER);
                // Continue - module created but file missing
                // Admin can manually upload file later.
            }

            // Update section sequence.
            $record = new stdClass();
            $record->id = $sectionid;

            if (!empty($scormsection->sequence)) {
                $modules = explode(',', $scormsection->sequence);
                $newmodules = [];

                // Check if a document resource already exists for this SCORM in the sequence.
                $existingdoccmids = [];
                $existingdocs = $DB->get_records(
                    'block_blc_modules_doc',
                    ['blcmoduleid' => $blcmoduleid],
                    '',
                    'cmid'
                );

                foreach ($existingdocs as $doc) {
                    if (in_array($doc->cmid, $modules)) {
                        $existingdoccmids[] = $doc->cmid;
                        debugging(
                            "BLC add_doc: Found existing document module
                            (cmid: {$doc->cmid}) in section for blcmodule $blcmoduleid",
                            DEBUG_DEVELOPER
                        );
                    }
                }

                // If document already exists in sequence, skip adding new one.
                if (!empty($existingdoccmids)) {
                    debugging(
                        "BLC add_doc: Document already exists in section for blcmodule $blcmoduleid.
                        Skipping sequence update to prevent duplication.",
                        DEBUG_DEVELOPER
                    );

                    // Clean up the newly created module since we don't need it
                    // Delete the course module.
                    $DB->delete_records('course_modules', ['id' => $resourcecoursemodule]);
                    // Delete the resource instance.
                    $DB->delete_records('resource', ['id' => $id]);
                    // Delete the file we just created.
                    if (isset($file) && $file) {
                        $file->delete();
                    }

                    debugging("BLC add_doc: Cleaned up duplicate module for blcmoduleid $blcmoduleid", DEBUG_DEVELOPER);

                    // Update the tracking record to use existing cmid.
                    $firstexistingcmid = reset($existingdoccmids);
                    $resourcerecord = new stdClass();
                    $resourcerecord->userid = $USER->id;
                    $resourcerecord->courseid = $courseid;
                    $resourcerecord->blcmoduleid = $blcmoduleid;
                    $resourcerecord->sectionid = $section;
                    $resourcerecord->cmid = $firstexistingcmid;
                    $resourcerecord->scormid = $docid;
                    $resourcerecord->scormurl = $docurl;
                    $resourcerecord->version = $docversion;
                    $resourcerecord->timecreated = time();
                    $resourcerecord->timemodified = time();

                    // Check if record already exists.
                    $existingrecord = $DB->get_record(
                        'block_blc_modules_doc',
                        ['blcmoduleid' => $blcmoduleid, 'cmid' => $firstexistingcmid]
                    );

                    if (!$existingrecord) {
                        $DB->insert_record('block_blc_modules_doc', $resourcerecord);
                        debugging(
                            "BLC add_doc: Created tracking record for existing module
                            (blcmoduleid: $blcmoduleid, cmid: $firstexistingcmid)",
                            DEBUG_DEVELOPER
                        );
                    } else {
                        debugging("BLC add_doc: Tracking record already exists for blcmoduleid $blcmoduleid", DEBUG_DEVELOPER);
                    }

                    $successcount++;
                    continue; // Skip to next module.
                }

                // No existing document found, proceed with adding new one to sequence.
                foreach ($modules as $key => $value) {
                    if ($value == $cmid) {
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

            // Save tracking record.
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

            // Cleanup temporary document files on API server.
            try {
                $cleanupfunction = 'local_scormurl_get_deletetempdocurls';
                $cleanupurl = new moodle_url($domainname . '/webservice/rest/server.php', [
                    'wstoken' => $token,
                    'wsfunction' => $cleanupfunction,
                    'apikey' => $apikey,
                    'scormurl' => $blcmodule->scormurl,
                    'moodlewsrestformat' => 'json',
                ]);

                $cleanupcurl = new blccurl_helper();
                $cleanupcurl->set_header('Content-Type: application/json; charset=utf-8');
                $cleanupcurl->post($cleanupurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);

                debugging("BLC add_doc: Cleaned up temporary files for module $blcmoduleid", DEBUG_DEVELOPER);
            } catch (Exception $e) {
                debugging(
                    "BLC add_doc: Failed to cleanup temporary files for module $blcmoduleid: " . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
                // Continue - cleanup failure is not critical.
            }

            $successcount++;
            debugging("BLC add_doc: Successfully processed module $blcmoduleid", DEBUG_DEVELOPER);
        } catch (Exception $e) {
            $failcount++;
            $errormessages[] = "Module " . $blcmodule->id . ": " . $e->getMessage();
            debugging(
                "BLC add_doc: Unexpected error processing module " . $blcmodule->id . ": " . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            continue;
        }
    } // End foreach loop
} // End if $blcmodules

// Count how many were skipped because no doc available.
$skippednodoc = $totalcount - $successcount - $failcount;

$redirect = new moodle_url('/admin/settings.php', ['section' => 'blocksettingblc_modules']);

if ($totalcount == 0) {
    $message = get_string('nomoduleswithourdocs', 'block_blc_modules');
    $notifytype = notification::NOTIFY_INFO;
} else if ($successcount > 0 && $failcount == 0) {
    $message = get_string('batchaddsuccess', 'block_blc_modules', $successcount);
    if ($skippednodoc > 0) {
        $message .= ' ' . get_string('skippednodoc', 'block_blc_modules', $skippednodoc);
    }
    $notifytype = notification::NOTIFY_SUCCESS;
} else if ($successcount > 0 && $failcount > 0) {
    $message = get_string(
        'batchaddpartial',
        'block_blc_modules',
        ['success' => $successcount, 'failed' => $failcount]
    );
    if ($skippednodoc > 0) {
        $message .= ' ' . get_string('skippednodoc', 'block_blc_modules', $skippednodoc);
    }
    $notifytype = notification::NOTIFY_WARNING;
} else if ($successcount == 0 && $failcount > 0) {
    $message = get_string('batchaddfailed', 'block_blc_modules', $failcount);
    $notifytype = notification::NOTIFY_ERROR;
} else {
    // All skipped because no docs available.
    $message = get_string('nodocsfound', 'block_blc_modules', $skippednodoc);
    $notifytype = notification::NOTIFY_INFO;
}

// Log summary.
debugging(
    "BLC add_doc: Batch processing completed.
    Total: $totalcount, Success: $successcount, Failed: $failcount, Skipped (no doc): $skippednodoc",
    DEBUG_DEVELOPER
);
if (!empty($errormessages)) {
    debugging("BLC add_doc: Errors: " . implode('; ', array_slice($errormessages, 0, 10)), DEBUG_DEVELOPER);
}

redirect($redirect, $message, null, $notifytype);
