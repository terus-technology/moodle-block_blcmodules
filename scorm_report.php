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

	$block_blc_modules = $DB->count_records('block_blc_modules');
	$block_blc_modules_docs = $DB->count_records('block_blc_modules_doc');
	
	if(!empty($block_blc_modules)){
		$table = new html_table();
		$table->tablealign="left";
		$labels = [];
		$data = [];
		$table->head  = array(get_string('resource', 'block_blc_modules'),get_string('usage', 'block_blc_modules'));
															
		$table->align = array('centre');
		$table->width = '50%';
		$table->attributes['class'] = 'generaltable';
		$table->attributes['style'] = 'margin-top: 30px;';
		$table->data = array();
		$labels[] = get_string('blcmodules', 'block_blc_modules');
		$data[] = $block_blc_modules;
		$labels[] = get_string('accessdoc', 'block_blc_modules');
		$data[] = $block_blc_modules_docs;
		$table->data[] = array(get_string('blcmodules', 'block_blc_modules'),$block_blc_modules);
		$table->data[] = array(get_string('accessdoc', 'block_blc_modules'),$block_blc_modules_docs);

		$scorms = new \core\chart_series('BLC', $data);
		$chart = new \core\chart_pie();
		$chart->add_series($scorms);
		$chart->set_labels($labels);
		
		echo html_writer::start_tag('div', array('class'=>'no-overflow','style'=>''));
		echo '<h3>'.get_string('blcresource', 'block_blc_modules').'</h3>';
		echo '<h5>'.get_string('blcresourceinfo', 'block_blc_modules').'</h5>';
		echo $OUTPUT->render_chart($chart, false);
		echo html_writer::table($table);
		echo html_writer::end_tag('div');
		
		$sql = "SELECT courseid,c.fullname, COUNT(*) AS count FROM {block_blc_modules} as blc 
						JOIN {course} as c on c.id=blc.courseid 
						GROUP BY courseid ORDER BY count DESC";
		$blc_modules_bycourses = $DB->get_records_sql($sql);
		
		if(!empty($blc_modules_bycourses)){
			$usage = 10;//round(($totaldocs / $totalscorms) * 100,2);
			$table = new html_table();
			$table->tablealign="left";
			$table->head  = array(get_string('course'),get_string('usage', 'block_blc_modules'));
																
			$table->align = array('centre');
			$table->width = '50%';
			$table->attributes['class'] = 'generaltable';
			$table->attributes['style'] = 'margin-top: 30px;';
			$table->data = array();
			foreach($blc_modules_bycourses as $blc_modules_bycourse)
				$table->data[] = array($blc_modules_bycourse->fullname,$blc_modules_bycourse->count);

			echo html_writer::start_tag('div', array('class'=>'no-overflow','style'=>'margin-top: 10%;'));
			echo '<h3>'.get_string('countbycourse', 'block_blc_modules').'</h3>';
			echo '<h5>'.get_string('topcourses', 'block_blc_modules').'</h5>';
			echo html_writer::table($table);
			echo html_writer::end_tag('div');
		}
		
		$subjects = get_subjects();
		if($subjects){
			$table = new html_table();
			$table->tablealign="left";
			$table->head  = array(get_string('subject', 'block_blc_modules'),get_string('usage', 'block_blc_modules'));
																
			$table->align = array('centre');
			$table->width = '50%';
			$table->attributes['class'] = 'generaltable';
			$table->attributes['style'] = 'margin-top: 30px;';
			$table->data = array();
			foreach($subjects as $id => $subject){
				$sql = "SELECT *  FROM {block_blc_modules} WHERE ";
				$sql .= $DB->sql_like('scormurl', ':subject');
				$sqlparam = array('subject' => '%/'.$DB->sql_like_escape($subject).'/%');

				$block_blc_modules = $DB->get_records_sql($sql,$sqlparam);
				$totalscorms = count($block_blc_modules);
				$table->data[] = array($subject,$totalscorms);
				
			}
			$table->data = array_sort($table->data, '1', 'DESC');
			$table->data = array_slice($table->data, 0,5, true);
			echo html_writer::start_tag('div', array('class'=>'no-overflow','style'=>'margin-top: 10%;'));
			echo '<h3>'.get_string('countbysubject', 'block_blc_modules').'</h3>';
			echo '<h5>'.get_string('topsubjects', 'block_blc_modules').'</h5>';
			echo html_writer::table($table);
			echo html_writer::end_tag('div');
			
		}

	}

echo $OUTPUT->footer();

