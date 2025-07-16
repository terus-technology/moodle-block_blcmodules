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

use renderable;
use renderer_base;
use templatable;
use stdClass;
use core\chart_series;
use core\chart_pie;
use moodle_url;
use html_table;
use html_writer;
/**
 * Class scorm_report_page
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology <ali@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/blocks/blc_modules/locallib.php');
class scorm_report_page implements renderable, templatable {


    public $baseurl;
    public $homeurl;

    public $scorms;

    public $chart;


    /**
     * Constructor.
     *
     * @param array $block_blc_modules Number of BLC modules
     * @param array $block_blc_modules_docs Number of BLC module documents
     * @param array $subjects List of subjects
     * @param moodle_url $baseurl Base URL for the page
     * @param int $roleid Plugin role ID
     * 
     */
    public function __construct($baseurl, $homeurl) {
        $this->baseurl = $baseurl;
        $this->homeurl = $homeurl;


    }
    /**
     * Render the SCORM Report page.
     *
     * @param renderer_base $output
     * @param chart_pie $output
     * @return string
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();

        $data->baseurl = $this->baseurl;
        $data->homeurl = $this->homeurl;

        list ($block_blc_modules, $block_blc_modules_docs) = $this->get_count_of_modules();

        $data->blcmodulescount = $block_blc_modules;
        $data->blcmodulesdocscount = $block_blc_modules_docs;

        $courses = $this->get_data();
        $data->exist = count($courses) > 0 ? true : false;
        $data->courses = $courses;
        
        $subjects = $this->get_data_by_subject();
        $data->subjects = $subjects;
  
        $chartdata = [$block_blc_modules, $block_blc_modules_docs];
        $labels = [get_string('blcmodules', 'block_blc_modules'), get_string('accessdoc', 'block_blc_modules')];
        
		$scorms = new \core\chart_series('BLC', $chartdata);
		$chart = new \core\chart_pie();
		$chart->add_series($scorms);
		$chart->set_labels($labels);

        $data->chart = $output->render_chart($chart, false);

        return $data;
    }

    private function get_count_of_modules() {
        global $DB;

        // Get the count of BLC modules.
        $block_blc_modules = $DB->count_records('block_blc_modules');

        // Get the count of BLC module documents.
        $block_blc_modules_docs = $DB->count_records('block_blc_modules_doc');
        // var_dump($block_blc_modules); die();// Debugging line, can be removed later.
        return [$block_blc_modules,$block_blc_modules_docs];
    }

    private function get_data(){
        global $DB;

        $sql = "SELECT courseid,c.fullname, COUNT(*) AS count FROM {block_blc_modules} as blc 
                        JOIN {course} as c on c.id=blc.courseid 
                        GROUP BY courseid ORDER BY count DESC";
        $blc_modules_bycourses = $DB->get_records_sql($sql);
        return array_values($blc_modules_bycourses);
        
    }

    private function get_data_by_subject() {
        global $DB;
        $subjects = get_subjects();
        $data = [];
        if (count($subjects) > 0 ){
            foreach ($subjects as $subject) {
                $sql = "SELECT *  FROM {block_blc_modules} WHERE ";
                $sql .= $DB->sql_like('scormurl', ':subject');
                $sqlparam = array('subject' => '%/'.$DB->sql_like_escape($subject).'/%');

                $block_blc_modules = $DB->get_records_sql($sql,$sqlparam);
    			$totalscorms = count($block_blc_modules);
				$data[] = array($subject,$totalscorms);
            }
        }
        $data = array_sort($data, '1', 'DESC');
        $data = array_slice($data, 0, 5, true); // Get top 5 subjects.
        return array_values($data);
    }


}
