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
 * This file will validate the settings.
 *
 * @package    block_blc_modules
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');

global $DB, $USER, $CFG;
require_login(null, false);

// PERMISSION - Require site configuration capability for better security.
require_capability('moodle/site:config', context_system::instance());

$title = get_string('pluginname', 'block_blc_modules');
$heading = $SITE->fullname;
$url = '/blocks/blc_modules/validate_settings.php';

$baseurl = new moodle_url($url);

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('validatesettings', 'block_blc_modules'));
$PAGE->set_heading($heading);
$PAGE->set_cacheable(false);

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('block_blc_modules');

// Create the validate settings page object.
$validatesettings = new \block_blc_modules\output\validate_settings_page($baseurl, $url);

echo $renderer->render($validatesettings);

echo $OUTPUT->footer();
