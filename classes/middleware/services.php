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
 * @copyright  2022 Terus Technology Inc (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 * 
 **/

namespace block_blc_modules\middleware;

require_once(dirname(__FILE__) . '/../../../../config.php');
require_once("$CFG->libdir/accesslib.php");

use context_module;

class services
{

    function __construct()
    {
        var_dump("Instance of " . __CLASS__);
    }

    public static function blcscorm_add_instance($scorm, $mform = null)
    {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/scorm/locallib.php');

        if (empty($scorm->timeopen)) {
            $scorm->timeopen = 0;
        }
        if (empty($scorm->timeclose)) {
            $scorm->timeclose = 0;
        }
        if (empty($scorm->completionstatusallscos)) {
            $scorm->completionstatusallscos = 0;
        }
        $cmid       = $scorm->coursemodule;
        $cmidnumber = $scorm->cmidnumber;
        $courseid   = $scorm->course;

        $context = context_module::instance($cmid);

        $scorm = scorm_option2text($scorm);
        $scorm->width  = (int)str_replace('%', '', $scorm->width);
        $scorm->height = (int)str_replace('%', '', $scorm->height);

        if (!isset($scorm->whatgrade)) {
            $scorm->whatgrade = 0;
        }

        $id = $DB->insert_record('scorm', $scorm);

        // Update course module record - from now on this instance properly exists and all function may be used.
        $DB->set_field('course_modules', 'instance', $id, array('id' => $cmid));

        // Reload scorm instance.
        $record = $DB->get_record('scorm', array('id' => $id));

        $record->reference = $scorm->packageurl;
        
        // Debug: Check if packageurl is being set correctly
        debugging('Setting SCORM reference to: ' . $scorm->packageurl, DEBUG_DEVELOPER);

        // Save reference.
        $DB->update_record('scorm', $record);

        // Extra fields required in grade related functions.
        $record->course     = $courseid;
        $record->cmidnumber = $cmidnumber;
        $record->cmid       = $cmid;

        self::blcscorm_parse($record, false);

        scorm_grade_item_update($record);
        scorm_update_calendar($record, $cmid);
        if (!empty($scorm->completionexpected)) {
            \core_completion\api::update_completion_date_event($cmid, 'scorm', $record, $scorm->completionexpected);
        }

        return $record->id;
    }

    public static function blcscorm_parse($scorm, $full)
    {
        global $CFG, $DB;
        $cfgscorm = get_config('scorm');

        if (!isset($scorm->cmid)) {
            $cm = get_coursemodule_from_instance('scorm', $scorm->id);
            $scorm->cmid = $cm->id;
        }
        $context = context_module::instance($scorm->cmid);
        $newhash = $scorm->sha1hash;

        $fs = get_file_storage();
        $packagefile = false;
        $packagefileimsmanifest = false;

        if (!$cfgscorm->allowtypelocalsync) {
            // Sorry - localsync disabled.
            return;
        }

        // Clear existing files in the package area.
        $fs->delete_area_files($context->id, 'mod_scorm', 'package');
        
        // Prepare file record for the SCORM package.
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_scorm',
            'filearea' => 'package',
            'itemid' => 0,
            'filepath' => '/',
        ];

        // Extract filename from URL if not provided.
        if (!isset($filerecord['filename'])) {
            $parts = explode('/', $scorm->reference);
            $filename = array_pop($parts);
            $filerecord['filename'] = clean_param($filename, PARAM_FILE);
        }
        
        // Set source URL.
        $filerecord['source'] = clean_param($scorm->reference, PARAM_URL);

        // Download options for the SCORM package.
        $options = [
            'calctimeout' => true,
            'connecttimeout' => 600,
            'skipcertverify' => true,
            'timeout' => 300,
        ];

        // Debug: Check if reference URL is set
        if (empty($scorm->reference)) {
            debugging('SCORM reference URL is empty in blcscorm_parse. Scorm object: ' . print_r($scorm, true), DEBUG_DEVELOPER);
            return;
        }
        
        debugging('Attempting to download SCORM package from: ' . $scorm->reference, DEBUG_DEVELOPER);
        
        // Download the file content using the same method as blcscormurl_filesize (which works)
        $content = download_file_content($scorm->reference, null, null, false, 300, 20, true);
        
        if ($content !== false && strlen($content) > 0) {
            try {
                // Create file from the downloaded content
                $packagefile = $fs->create_file_from_string($filerecord, $content);
                if ($packagefile) {
                    $newhash = $packagefile->get_contenthash();
                } else {
                    $newhash = null;
                    debugging('Failed to create SCORM package file from downloaded content', DEBUG_DEVELOPER);
                }
            } catch (Exception $e) {
                $newhash = null;
                debugging('Error creating SCORM package file: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        } else {
            $newhash = null;
            debugging('Failed to download SCORM package content from: ' . $scorm->reference, DEBUG_DEVELOPER);
        }

        // Update SCORM record with new hash.
        $scorm->revision++;
        $scorm->sha1hash = $newhash;
        $DB->update_record('scorm', $scorm);

        // Process the downloaded package if successful.
        if ($packagefile) {
            self::process_scorm_package($scorm, $packagefile, $context, $fs);
        }
    }

    /**
     * Process the downloaded SCORM package and extract its contents.
     *
     * @param stdClass $scorm The SCORM instance
     * @param stored_file $packagefile The downloaded package file
     * @param context $context The module context
     * @param file_storage $fs File storage instance
     */
    private static function process_scorm_package($scorm, $packagefile, $context, $fs)
    {
        global $CFG;

        // Check if package needs processing.
        if (!$packagefile || $packagefile->is_directory()) {
            return;
        }

        // Clear existing content files.
        $fs->delete_area_files($context->id, 'mod_scorm', 'content');

        // Extract the SCORM package.
        $packer = get_file_packer('application/zip');
        if ($packer) {
            $packagefile->extract_to_storage($packer, $context->id, 'mod_scorm', 'content', 0, '/');
        }

        // Check for imsmanifest.xml and parse SCORM content.
        $manifest = $fs->get_file($context->id, 'mod_scorm', 'content', 0, '/', 'imsmanifest.xml');
        if ($manifest) {
            require_once("$CFG->dirroot/mod/scorm/datamodels/scormlib.php");
            // Parse SCORM manifest.
            if (!scorm_parse_scorm($scorm, $manifest)) {
                $scorm->version = 'ERROR';
            }
        } else {
            // Try AICC format.
            require_once("$CFG->dirroot/mod/scorm/datamodels/aicclib.php");
            $result = scorm_parse_aicc($scorm);
            if (!$result) {
                $scorm->version = 'ERROR';
            } else {
                $scorm->version = 'AICC';
            }
        }
    }

    public static function blcscormurl_filesize($scormurl)
    {
        global $CFG, $DB, $COURSE;

        // Check if this is a pluginfile URL (internal Moodle file)
        if (strpos($scormurl, '/pluginfile.php/') !== false) {
            //return self::check_pluginfile_exists($scormurl);
        }

        // For external URLs, try to download and check size
        $content = download_file_content($scormurl, null, null, false, 300, 20, true);
        $filesize = strlen($content);
        
        if ($filesize == 0)
            return false;
        else if ($filesize > 0)
            return true;
        else
            return false;
    }

    /**
     * Check if a pluginfile URL corresponds to an existing file in Moodle's file storage.
     *
     * @param string $pluginfile_url The pluginfile URL to check
     * @return bool True if file exists, false otherwise
     */
    private static function check_pluginfile_exists($pluginfile_url)
    {
        // Parse the pluginfile URL to extract file information
        // URL format: /pluginfile.php/{contextid}/{component}/{filearea}/{itemid}/{filepath}/{filename}
        $url_parts = parse_url($pluginfile_url);
        $path = $url_parts['path'];
        
        // Remove /pluginfile.php/ from the beginning
        $path = str_replace('/pluginfile.php/', '', $path);
        $parts = explode('/', $path);
        
        if (count($parts) < 5) {
            return false;
        }

        $contextid = (int)$parts[0];
        $component = $parts[1];
        $filearea = $parts[2];
        $itemid = (int)$parts[3];
        
        // The filename is the last part, filepath is everything in between
        $filename = array_pop($parts);
        $filepath = '/' . implode('/', array_slice($parts, 4)) . '/';
        
        // If there are no parts after itemid, filepath should be just '/'
        if (empty(array_slice($parts, 4))) {
            $filepath = '/';
        }

        // Get the file from storage
        $fs = get_file_storage();
        $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

        // Return true if file exists and is not a directory
        return ($file && !$file->is_directory());
    }

    public static function blc_scorm_update_instance($scorm, $mform = null)
    {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/scorm/locallib.php');

        if (empty($scorm->timeopen)) {
            $scorm->timeopen = 0;
        }
        if (empty($scorm->timeclose)) {
            $scorm->timeclose = 0;
        }
        if (empty($scorm->completionstatusallscos)) {
            $scorm->completionstatusallscos = 0;
        }

        $cmid       = $scorm->coursemodule;
        $cmidnumber = $scorm->cmidnumber;
        $courseid   = $scorm->course;

        $scorm->id = $scorm->instance;

        $context = context_module::instance($cmid);

        $scorm->reference = $scorm->packageurl;

        $scorm = scorm_option2text($scorm);
        $scorm->width        = (int)str_replace('%', '', $scorm->width);
        $scorm->height       = (int)str_replace('%', '', $scorm->height);
        $scorm->timemodified = time();

        if (!isset($scorm->whatgrade)) {
            $scorm->whatgrade = 0;
        }

        $DB->update_record('scorm', $scorm);
        // We need to find this out before we blow away the form data.
        $completionexpected = (!empty($scorm->completionexpected)) ? $scorm->completionexpected : null;

        $scorm = $DB->get_record('scorm', array('id' => $scorm->id));

        // Extra fields required in grade related functions.
        $scorm->course   = $courseid;
        $scorm->idnumber = $cmidnumber;
        $scorm->cmid     = $cmid;

        self::scorm_parse($scorm, (bool)$scorm->updatefreq);

        scorm_grade_item_update($scorm);
        scorm_update_grades($scorm);
        scorm_update_calendar($scorm, $cmid);
        \core_completion\api::update_completion_date_event($cmid, 'scorm', $scorm, $completionexpected);

        return true;
    }

    private static function scorm_parse($scorm, $full)
    {
        global $CFG, $DB;
        $cfgscorm = get_config('scorm');

        if (!isset($scorm->cmid)) {
            $cm = get_coursemodule_from_instance('scorm', $scorm->id);
            $scorm->cmid = $cm->id;
        }
        $context = context_module::instance($scorm->cmid);
        $newhash = $scorm->sha1hash;

        $fs = get_file_storage();
        $packagefile = false;
        $packagefileimsmanifest = false;

        if (!$cfgscorm->allowtypelocalsync) {
            // Sorry - localsync disabled.
            return;
        }

        if ($scorm->reference !== '') {
            debugging('SCORM reference URL found in scorm_parse: ' . $scorm->reference, DEBUG_DEVELOPER);
            
            $fs->delete_area_files($context->id, 'mod_scorm', 'package');
            
            $filerecord = [
                'contextid' => $context->id,
                'component' => 'mod_scorm',
                'filearea' => 'package',
                'itemid' => 0,
                'filepath' => '/',
            ];

            // Extract filename from URL.
            $parts = explode('/', $scorm->reference);
            $filename = array_pop($parts);
            $filerecord['filename'] = clean_param($filename, PARAM_FILE);
            $filerecord['source'] = clean_param($scorm->reference, PARAM_URL);

            $options = [
                'calctimeout' => true,
                'skipcertverify' => true,
                'connecttimeout' => 600,
                'timeout' => 300,
            ];

            // Download the file content using the same method as blcscormurl_filesize (which works)
            $content = download_file_content($scorm->reference, null, null, false, 300, 20, true);
            
            if ($content !== false && strlen($content) > 0) {
                try {
                    // Create file from the downloaded content
                    $packagefile = $fs->create_file_from_string($filerecord, $content);
                    if ($packagefile) {
                        $newhash = $packagefile->get_contenthash();
                    } else {
                        $newhash = null;
                        debugging('Failed to create SCORM package file from downloaded content', DEBUG_DEVELOPER);
                    }
                } catch (Exception $e) {
                    $newhash = null;
                    debugging('Error creating SCORM package file: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            } else {
                $newhash = null;
                debugging('Failed to download SCORM package content from: ' . $scorm->reference, DEBUG_DEVELOPER);
            }
        } else {
            debugging('SCORM reference URL is empty in scorm_parse. Scorm object: ' . print_r($scorm, true), DEBUG_DEVELOPER);
            return;
        }

        if ($packagefile) {
            if (!$full && $packagefile && $scorm->sha1hash === $newhash) {
                if (strpos($scorm->version, 'SCORM') !== false) {
                    if ($packagefileimsmanifest || $fs->get_file($context->id, 'mod_scorm', 'content', 0, '/', 'imsmanifest.xml')) {
                        // No need to update.
                        return;
                    }
                } else if (strpos($scorm->version, 'AICC') !== false) {
                    // TODO: add more sanity checks - something really exists in scorm_content area.
                    return;
                }
            }
            
            if (!$packagefileimsmanifest) {
                // Now extract files.
                $fs->delete_area_files($context->id, 'mod_scorm', 'content');

                $packer = get_file_packer('application/zip');
                if ($packer) {
                    $packagefile->extract_to_storage($packer, $context->id, 'mod_scorm', 'content', 0, '/');
                }
            }
        } else if (!$full) {
            return;
        }

        if ($packagefileimsmanifest) {
            require_once("$CFG->dirroot/mod/scorm/datamodels/scormlib.php");
            // Direct link to imsmanifest.xml file.
            if (!scorm_parse_scorm($scorm, $packagefile)) {
                $scorm->version = 'ERROR';
            }
        } else if ($manifest = $fs->get_file($context->id, 'mod_scorm', 'content', 0, '/', 'imsmanifest.xml')) {
            require_once("$CFG->dirroot/mod/scorm/datamodels/scormlib.php");
            // SCORM.
            if (!scorm_parse_scorm($scorm, $manifest)) {
                $scorm->version = 'ERROR';
            }
        } else {
            require_once("$CFG->dirroot/mod/scorm/datamodels/aicclib.php");
            // AICC.
            $result = scorm_parse_aicc($scorm);
            if (!$result) {
                $scorm->version = 'ERROR';
            } else {
                $scorm->version = 'AICC';
            }
        }

        $scorm->revision++;
        $scorm->sha1hash = $newhash;
        $DB->update_record('scorm', $scorm);
    }
}
