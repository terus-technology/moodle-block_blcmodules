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
 * SCORM report page for BLC Modules block.
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_blc_modules\output\scorm_report_page;

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/formslib.php');
require_once('locallib.php');

require_login(null, false);

global $DB, $USER, $CFG, $PAGE, $OUTPUT;

$context = context_system::instance();
$home = get_string('pluginname', 'block_blc_modules');
$basetext = get_string('scormreports', 'block_blc_modules');
$baseurl = new moodle_url('/blocks/blc_modules/scorm_report.php');
$homeurl = new moodle_url('/admin/settings.php', [
    'section' => 'blocksettingblc_modules',
]);

$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('course');
$PAGE->set_heading("$home - $basetext");
$PAGE->set_title($basetext);
$PAGE->navbar->add($home, $homeurl);
$PAGE->navbar->add($basetext, $baseurl);

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('block_blc_modules');

// Create the scorm report page object.
$scormreportpage = new scorm_report_page($baseurl, $homeurl);

echo $renderer->render($scormreportpage);
echo $OUTPUT->footer();
