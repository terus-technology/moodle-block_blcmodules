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

global $DB, $USER, $CFG;
require_login(null, false);

// Check permissions
require_capability('moodle/course:manageactivities', context_system::instance());

$coursemodule = optional_param('cmid', '', PARAM_INT);
$version = optional_param('version', '', PARAM_INT);

// Validate parameters
if (empty($coursemodule) || empty($version)) {
    print_error('invalidparameters');
}

$PAGE->set_url('/blocks/blc_modules/update_scorm.php', ['cmid' => $coursemodule, 'version' => $version]);
$PAGE->set_pagelayout('admin');
$PAGE->set_context(context_system::instance());
$PAGE->set_title("Update SCORM Module");
$PAGE->set_heading("Update SCORM Module");
$PAGE->set_cacheable(false);

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('block_blc_modules');

// Create the validate settings page object.
$updatescorm = new \block_blc_modules\output\update_scorm_page($coursemodule, $version);

echo $renderer->render($updatescorm);

echo $OUTPUT->footer();
