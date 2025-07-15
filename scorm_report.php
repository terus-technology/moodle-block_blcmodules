<?php

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/formslib.php');
require_once('locallib.php');

require_login(null, false);

global $DB,$USER,$CFG;

$context = context_system::instance();
$home = get_string('pluginname', 'block_blc_modules');
$basetext = get_string('scormreports', 'block_blc_modules');
$baseurl = new moodle_url('/blocks/blc_modules/scorm_report.php');
$homeurl = new moodle_url('/admin/settings.php', array(
    'section' => 'blocksettingblc_modules'
));


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
$scormreportpage = new \block_blc_modules\output\scorm_report_page($baseurl, $homeurl);

echo $renderer->render($scormreportpage);
echo $OUTPUT->footer();

