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
 * @copyright  2022 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules\middleware;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../../../config.php');
require_login();
require_once("$CFG->libdir/accesslib.php");

use block_blc_modules\helper\debug_helper;
use block_blc_modules\helper\file_helper;
use context;
use context_module;
use core_completion\api;
use core_php_time_limit;
use Exception;
use file_storage;
use stdClass;
use stored_file;

/**
 * Class services
 */
class services {
    /**
     * Constructor for services class.
     */
    public function __construct() {
        $debug = new debug_helper();
        $debug->info('Initialized instance of ' . __CLASS__);
    }

    /**
     * Add a new SCORM instance to the database.
     *
     * @param object $scorm The SCORM instance data
     * @param object|null $mform The form object (optional)
     * @return int The ID of the newly created SCORM instance
     */
    public static function blcscorm_add_instance($scorm, $mform = null) {
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
        $DB->set_field('course_modules', 'instance', $id, ['id' => $cmid]);

        // Reload scorm instance.
        $record = $DB->get_record('scorm', ['id' => $id]);

        $record->reference = $scorm->packageurl;

        // Debug: Check if packageurl is being set correctly.
        $debug = new debug_helper();
        $debug->info('Setting SCORM reference to: ' . $scorm->packageurl);

        // Save reference.
        $DB->update_record('scorm', $record);

        // Extra fields required in grade related functions.
        $record->course     = $courseid;
        $record->cmidnumber = $cmidnumber;
        $record->cmid       = $cmid;

        self::blcscorm_parse($record);

        scorm_grade_item_update($record);
        scorm_update_calendar($record, $cmid);
        if (!empty($scorm->completionexpected)) {
            api::update_completion_date_event($cmid, 'scorm', $record, $scorm->completionexpected);
        }

        return $record->id;
    }

    /**
     * Parse and store the SCORM package for a module instance.
     *
     * @param stdClass $scorm SCORM activity record.
     * @return void
     */
    public static function blcscorm_parse($scorm) {
        global $DB;

        $debug = new debug_helper();

        $cfgscorm = get_config('scorm');

        if (!isset($scorm->cmid)) {
            $cm = get_coursemodule_from_instance('scorm', $scorm->id);
            $scorm->cmid = $cm->id;
        }

        $context = context_module::instance($scorm->cmid);
        $newhash = $scorm->sha1hash;

        $fs = get_file_storage();
        $packagefile = false;

        if (!$cfgscorm->allowtypelocalsync) {
            // Sorry - localsync disabled.
            return;
        }

        // Clear existing files in the package area.
        $fs->delete_area_files($context->id, 'mod_scorm', 'package');

        // Check if reference URL is set.
        if (empty($scorm->reference)) {
            $debug->error('SCORM reference URL is empty in blcscorm_parse. Cannot proceed.');
            return;
        }

        $debug->info('Attempting to download SCORM package from: ' . $scorm->reference);

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
            // Trim trailing slashes and extract filename.
            $parts = explode('/', trim($scorm->reference, '/'));
            $filename = array_pop($parts);

            // Clean the filename.
            $cleanfilename = clean_param($filename, PARAM_FILE);

            // CRITICAL: Validate filename is not empty after cleaning.
            if (empty($cleanfilename)) {
                $debug->error('Extracted filename is empty after cleaning. URL: ' . $scorm->reference);
                $debug->error('This usually means the URL has no filename or ends with a slash.');
                return;
            }

            $filerecord['filename'] = $cleanfilename;
            $debug->info('Extracted filename: ' . $cleanfilename);
        }

        // Additional safety check: Ensure filename is set and not empty.
        if (empty($filerecord['filename'])) {
            $debug->error('File record filename is empty. Cannot create file.');
            return;
        }

        // Set source URL.
        $filerecord['source'] = clean_param($scorm->reference, PARAM_URL);

        // NEW: Check if we should use Google Drive streaming.
        // The scorm->blc_package_id contains the ID from block_scorm_package table.
        // Download file from reference URL.
        // Note: URL may be from local_scormurl service (temporary URL) or direct URL.
        // Google Drive downloads are handled server-side by local_scormurl service.
        if (!empty($scorm->reference)) {
            $debug->info('Downloading SCORM package from: ' . $scorm->reference);

            try {
                // Set longer timeout for large files.
                core_php_time_limit::raise(1800); // 30 minutes.

                $packagefile = file_helper::create_file_from_external_url($fs, $filerecord, $scorm->reference);

                if ($packagefile) {
                    $newhash = $packagefile->get_contenthash();
                    $debug->info('Successfully downloaded SCORM package: ' . $packagefile->get_filesize() . ' bytes');
                } else {
                    $newhash = null;
                    $debug->error('Failed to download or create SCORM package file from: ' . $scorm->reference);
                    exit();
                }
            } catch (Exception $e) {
                $newhash = null;
                $debug->error('Exception downloading SCORM package: ' . $e->getMessage());
                exit();
            }
        } else {
            $newhash = null;
            $debug->warning('No reference URL provided for SCORM package');
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
    private static function process_scorm_package($scorm, stored_file $packagefile, context $context, file_storage $fs) {
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

    /**
     * Check whether a SCORM URL points to a file with a detectable size.
     *
     * @param string $scormurl The SCORM package URL.
     * @return bool True if the file size can be determined and is greater than zero.
     */
    public static function blcscormurl_filesize($scormurl) {
        // For external URLs, use HEAD request to get Content-Length.
        $headers = @get_headers($scormurl, 1);
        if ($headers && isset($headers['Content-Length'])) {
            // Content-Length can be an array if there are redirects.
            $length = is_array($headers['Content-Length']) ? end($headers['Content-Length']) : $headers['Content-Length'];
            $filesize = (int)$length;
            if ($filesize > 0) {
                return true;
            } else {
                return false;
            }
        }

        // If Content-Length is not available, fallback to false.
        return false;
    }

    /**
     * Update a SCORM instance.
     *
     * @param object $scorm The SCORM data.
     * @param object|null $mform Optional form data.
     * @return bool True on success.
     */
    public static function blc_scorm_update_instance($scorm, $mform = null) {
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

        $scorm->reference = $scorm->packageurl;

        $scorm = scorm_option2text($scorm);
        $scorm->width        = (int) str_replace('%', '', $scorm->width);
        $scorm->height       = (int) str_replace('%', '', $scorm->height);
        $scorm->timemodified = time();

        if (!isset($scorm->whatgrade)) {
            $scorm->whatgrade = 0;
        }

        $DB->update_record('scorm', $scorm);
        // We need to find this out before we blow away the form data.
        $completionexpected = (!empty($scorm->completionexpected)) ? $scorm->completionexpected : null;

        $scorm = $DB->get_record('scorm', ['id' => $scorm->id]);

        // Extra fields required in grade related functions.
        $scorm->course   = $courseid;
        $scorm->idnumber = $cmidnumber;
        $scorm->cmid     = $cmid;

        self::scorm_parse($scorm, (bool) $scorm->updatefreq);

        scorm_grade_item_update($scorm);
        scorm_update_grades($scorm);
        scorm_update_calendar($scorm, $cmid);
        api::update_completion_date_event($cmid, 'scorm', $scorm, $completionexpected);

        return true;
    }

    /**
     * Parse and import a SCORM package.
     *
     * @param stdClass $scorm SCORM activity record.
     * @param bool $full Whether to perform a full parse.
     * @return void
     */
    private static function scorm_parse($scorm, $full) {
        global $CFG, $DB;
        $cfgscorm = get_config('scorm');

        $debug = new debug_helper();

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
            $debug->info('SCORM reference URL found in scorm_parse: ' . $scorm->reference);

            $fs->delete_area_files($context->id, 'mod_scorm', 'package');

            $filerecord = [
                'contextid' => $context->id,
                'component' => 'mod_scorm',
                'filearea' => 'package',
                'itemid' => 0,
                'filepath' => '/',
            ];

            // Extract filename from URL for file record.
            $filerecord['filename'] = 'package.zip'; // Default filename.

            // Try to extract a better filename from URL if possible.
            if (!empty($scorm->reference)) {
                $parts = explode('/', trim($scorm->reference, '/'));
                $urlfilename = array_pop($parts);
                $cleanfilename = clean_param($urlfilename, PARAM_FILE);

                if (!empty($cleanfilename) && preg_match('/\.zip$/i', $cleanfilename)) {
                    $filerecord['filename'] = $cleanfilename;
                    $debug->info('Using filename from URL: ' . $cleanfilename);
                }
            }

            $filerecord['source'] = clean_param($scorm->reference, PARAM_URL);

            // Download file from reference URL.
            // URL from local_scormurl service already handles Google Drive downloads server-side.
            $debug->info('Downloading SCORM package from URL: ' . $scorm->reference);

            try {
                core_php_time_limit::raise(1800); // 30 minutes for large files.

                // Download the file content using standard Moodle function.
                $content = download_file_content($scorm->reference, null, null, false, 300, 20, true);

                if ($content !== false && strlen($content) > 0) {
                    // Create file from the downloaded content.
                    $packagefile = $fs->create_file_from_string($filerecord, $content);

                    if ($packagefile) {
                        $newhash = $packagefile->get_contenthash();
                        $debug->info(
                            'Successfully downloaded SCORM package: ' . $packagefile->get_filesize() . ' bytes'
                        );
                    } else {
                        $newhash = null;
                        $debug->error('Failed to create SCORM package file from content');
                    }
                } else {
                    $newhash = null;
                    $debug->error('Failed to download SCORM package content from: ' . $scorm->reference);
                }
            } catch (Exception $e) {
                $newhash = null;
                $debug->error('Exception downloading SCORM package: ' . $e->getMessage());
            }
        } else {
            $debug->error('SCORM reference URL is empty in scorm_parse. Scorm object: ' . json_encode($scorm));
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
                    // TO DO: add more sanity checks - something really exists in scorm_content area.
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
