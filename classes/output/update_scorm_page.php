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

namespace block_blc_modules\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use Exception;
use stdClass;
use moodle_exception;

/**
 * Class update_scorm_page
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_scorm_page implements renderable, templatable {
    /** @var int Course module ID */
    public $coursemodule;

    /** @var int Version number */
    public $version;

    /**
     * Constructor.
     *
     * @param int $coursemodule Course module ID
     * @param int $version Version number
     */
    public function __construct($coursemodule, $version) {
        $this->coursemodule = $coursemodule;
        $this->version = $version;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->coursemodule = $this->coursemodule;
        $data->version = $this->version;

        // Get update process data.
        $updateresult = $this->process_scorm_update();

        // Merge update result into template data.
        foreach ($updateresult as $key => $value) {
            $data->$key = $value;
        }

        return $data;
    }

    /**
     * Process SCORM update and return result data for template.
     *
     * @return array
     */
    private function process_scorm_update() {
        global $DB, $CFG;

        $result = [
            'success' => false,
            'message' => '',
            'error' => false,
            'error_message' => '',
            'scorm_info' => [],
            'debug_info' => [],
        ];

        try {
            // Validate parameters.
            if (empty($this->coursemodule) || empty($this->version)) {
                throw new moodle_exception('error');
            }

            // Get course module record.
            $coursescorm = $DB->get_record('block_blc_modules', ['cmid' => $this->coursemodule]);
            if (!$coursescorm) {
                throw new moodle_exception('error');
            }

            // Get API key from settings.
            $apikey = get_config('block_blc_modules', 'api_key');
            if (empty($apikey)) {
                throw new moodle_exception('error');
            }

            // Get course module details.
            $scormcm = $DB->get_record('course_modules', ['id' => $this->coursemodule]);
            if (!$scormcm) {
                throw new moodle_exception('error');
            }

            // Prepare directory paths.
            $root = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
            $root = rtrim($root, '/') . '/';
            $docroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/';
            $tempdir = $docroot . $apikey;

            // Create temporary directory.
            if (!file_exists($tempdir)) {
                if (!mkdir($tempdir, 0777, true)) {
                    throw new moodle_exception('error');
                }
            }

            // Download SCORM package.
            $url = $coursescorm->scormurl;
            $filecontents = file_get_contents($url);
            if ($filecontents === false) {
                throw new moodle_exception('error');
            }

            $filename = $coursescorm->scormname . '.zip';
            $filepath = $tempdir . '/' . $filename;

            if (file_put_contents($filepath, $filecontents) === false) {
                throw new moodle_exception('error');
            }

            // Prepare SCORM update data.
            $tempurl = $root . $apikey . '/' . $filename;
            $scormurl = str_replace(' ', '%20', $tempurl);

            $scorm = new stdClass();
            $scorm->course = $coursescorm->courseid;
            $scorm->coursemodule = $this->coursemodule;
            $scorm->instance = $scormcm->instance;
            $scorm->section = $scormcm->section;
            $scorm->module = $scormcm->module;
            $scorm->modulename = 'scorm';
            $scorm->intro = '';
            $scorm->introformat = 1;
            $scorm->version = 'SCORM_1.2';
            $scorm->maxgrade = 100;
            $scorm->grademethod = 1;
            $scorm->maxattempt = 0;
            $scorm->width = 100;
            $scorm->height = 500;
            $scorm->scormtype = 'localsync';
            $scorm->packageurl = $scormurl;

            // Update SCORM instance.
            require_once($CFG->dirroot . '/mod/scorm/lib.php');
            if (scorm_update_instance($scorm)) {
                // Update version in block_blc_modules table.
                $scormrecord = new stdClass();
                $scormrecord->id = $coursescorm->id;
                $scormrecord->version = $this->version;
                $scormrecord->timemodified = time();
                $DB->update_record('block_blc_modules', $scormrecord);

                $result['success'] = true;
                $result['message'] = get_string('updatescormmesage', 'block_blc_modules');

                // Add SCORM info for template.
                $result['scorm_info'] = [
                    'name' => $coursescorm->scormname,
                    'old_version' => $coursescorm->version,
                    'new_version' => $this->version,
                    'course_id' => $coursescorm->courseid,
                    'cm_id' => $this->coursemodule,
                    'updated_time' => userdate(time()),
                ];
            } else {
                throw new moodle_exception('error');
            }

            // Clean up temporary file.
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            // Remove temporary directory if empty.
            if (is_dir($tempdir) && count(scandir($tempdir)) == 2) {
                rmdir($tempdir);
            }
        } catch (Exception $e) {
            $result['error'] = true;
            $result['error_message'] = $e->getMessage();
            $result['message'] = get_string('versioncheckerror', 'block_blc_modules');

            // Clean up on error.
            if (isset($filepath) && file_exists($filepath)) {
                unlink($filepath);
            }
            if (isset($tempdir) && is_dir($tempdir) && count(scandir($tempdir)) == 2) {
                rmdir($tempdir);
            }
        }

        // Add debug information.
        $result['debug_info'] = [
            'coursemodule' => $this->coursemodule,
            'version' => $this->version,
            'timestamp' => time(),
            'user_id' => $GLOBALS['USER']->id ?? 0,
        ];

        return $result;
    }
}
