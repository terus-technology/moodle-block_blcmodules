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

        $fs->delete_area_files($context->id, 'mod_scorm', 'package');
        $filerecord = array(
            'contextid' => $context->id, 'component' => 'mod_scorm', 'filearea' => 'package',
            'itemid' => 0, 'filepath' => '/'
        );
        $options = array('calctimeout' => true, 'connecttimeout' => 600, 'skipcertverify' => true);
        $filerecord = (array)$filerecord;  // Do not modify the submitted record, this cast unlinks objects.
        $filerecord = (object)$filerecord; // We support arrays too.

        $headers        = isset($options['headers'])        ? $options['headers'] : null;
        $postdata       = isset($options['postdata'])       ? $options['postdata'] : null;
        $fullresponse   = isset($options['fullresponse'])   ? $options['fullresponse'] : false;
        $timeout        = isset($options['timeout'])        ? $options['timeout'] : 300;
        $connecttimeout = isset($options['connecttimeout']) ? $options['connecttimeout'] : 20;
        $skipcertverify = isset($options['skipcertverify']) ? $options['skipcertverify'] : false;
        $calctimeout    = isset($options['calctimeout'])    ? $options['calctimeout'] : false;

        if (!isset($filerecord->filename)) {
            $parts = explode('/', $scorm->reference);
            $filename = array_pop($parts);
            $filerecord->filename = clean_param($filename, PARAM_FILE);
        }
        $source = !empty($filerecord->source) ? $filerecord->source : $scorm->reference;
        $filerecord->source = clean_param($source, PARAM_URL);

        $content = download_file_content($scorm->reference, $headers, $postdata, $fullresponse, $timeout, $connecttimeout, $skipcertverify, NULL, $calctimeout);
        $filesize = strlen($content);

        if ($packagefile = $fs->create_file_from_string($filerecord, $content)) {
            $newhash = $packagefile->get_contenthash();
        } else {
            $newhash = null;
        }

        $scorm->revision++;
        $scorm->sha1hash = $newhash;
        $DB->update_record('scorm', $scorm);
    }

    public static function blcscormurl_filesize($scormurl)
    {
        global $CFG, $DB, $COURSE;

        $fs = get_file_storage();
        $packagefile = false;
        $packagefileimsmanifest = false;
        $context = \context_course::instance($COURSE->id);

        $fs->delete_area_files($context->id, 'mod_scorm', 'package');
        $filerecord = array(
            'contextid' => $context->id, 'component' => 'mod_scorm', 'filearea' => 'package',
            'itemid' => 0, 'filepath' => '/'
        );
        $options = array('calctimeout' => true, 'connecttimeout' => 600, 'skipcertverify' => true);
        $filerecord = (array)$filerecord;  // Do not modify the submitted record, this cast unlinks objects.
        $filerecord = (object)$filerecord; // We support arrays too.

        $headers        = isset($options['headers'])        ? $options['headers'] : null;
        $postdata       = isset($options['postdata'])       ? $options['postdata'] : null;
        $fullresponse   = isset($options['fullresponse'])   ? $options['fullresponse'] : false;
        $timeout        = isset($options['timeout'])        ? $options['timeout'] : 300;
        $connecttimeout = isset($options['connecttimeout']) ? $options['connecttimeout'] : 20;
        $skipcertverify = isset($options['skipcertverify']) ? $options['skipcertverify'] : false;
        $calctimeout    = isset($options['calctimeout'])    ? $options['calctimeout'] : false;

        if (!isset($filerecord->filename)) {
            $parts = explode('/', $scormurl);
            $filename = array_pop($parts);
            $filerecord->filename = clean_param($filename, PARAM_FILE);
        }

        $source = !empty($filerecord->source) ? $filerecord->source : $scormurl;
        $filerecord->source = clean_param($source, PARAM_URL);

        $content = download_file_content($scormurl, $headers, $postdata, $fullresponse, $timeout, $connecttimeout, $skipcertverify, NULL, $calctimeout);
        $filesize = strlen($content);
        
        if ($filesize == 0)
            return false;
        else if ($filesize > 0)
            return true;
        else
            return false;
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
            $fs->delete_area_files($context->id, 'mod_scorm', 'package');
            $filerecord = array(
                'contextid' => $context->id, 'component' => 'mod_scorm', 'filearea' => 'package',
                'itemid' => 0, 'filepath' => '/'
            );
            if ($packagefile = $fs->create_file_from_url($filerecord, $scorm->reference, array('calctimeout' => true, 'skipcertverify' => true), true)) {
                $newhash = $packagefile->get_contenthash();
            } else {
                $newhash = null;
            }
        }

        if ($packagefile) {
            if (!$full and $packagefile and $scorm->sha1hash === $newhash) {
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
                $packagefile->extract_to_storage($packer, $context->id, 'mod_scorm', 'content', 0, '/');
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
