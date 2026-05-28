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
 * SCORM Report Page class.
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules\output;

defined('MOODLE_INTERNAL') || die();

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use stdClass;
use core\chart_pie;
use core\chart_series;
use moodle_url;

require_once($CFG->dirroot.'/blocks/blc_modules/locallib.php');

/**
 * SCORM Report Page class.
 */
class scorm_report_page implements renderable, templatable {
    /** @var moodle_url Base URL for the page. */
    public $baseurl;

    /** @var moodle_url Home URL for the page. */
    public $homeurl;

    /** @var array SCORM data array. */
    public $scorms;

    /** @var chart_pie Chart object for displaying data. */
    public $chart;

    /**
     * Constructor.
     *
     * @param moodle_url $baseurl Base URL for the page
     * @param moodle_url $homeurl Home URL for the page
     */
    public function __construct(moodle_url $baseurl, moodle_url $homeurl) {
        $this->baseurl = $baseurl;
        $this->homeurl = $homeurl;
    }

    /**
     * Render the SCORM Report page.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();

        $data->baseurl = $this->baseurl;
        $data->homeurl = $this->homeurl;

        [$blockblcmodules, $blockblcmodulesdocs] = $this->get_count_of_modules();

        $data->blcmodulescount = $blockblcmodules;
        $data->blcmodulesdocscount = $blockblcmodulesdocs;

        $courses = $this->get_data();
        $data->exist = count($courses) > 0 ? true : false;
        $data->courses = $courses;

        $subjects = $this->get_data_by_subject();
        $data->subjects = $subjects;

        $chartdata = [$blockblcmodules, $blockblcmodulesdocs];
        $labels = [get_string('blcmodules', 'block_blc_modules'), get_string('accessdoc', 'block_blc_modules')];

        $scorms = new chart_series('BLC', $chartdata);
        $chart = new chart_pie();
        $chart->add_series($scorms);
        $chart->set_labels($labels);

        $data->chart = $output->render_chart($chart, false);

        return $data;
    }

    /**
     * Get count of BLC modules and documents.
     *
     * @return array Array containing count of modules and documents
     */
    private function get_count_of_modules() {
        global $DB;

        // Get the count of BLC modules.
        $blockblcmodules = $DB->count_records('block_blc_modules');

        // Get the count of BLC module documents.
        $blockblcmodulesdocs = $DB->count_records('block_blc_modules_doc');

        return [$blockblcmodules, $blockblcmodulesdocs];
    }

    /**
     * Get BLC modules data grouped by course.
     *
     * @return array Array of modules grouped by course
     */
    private function get_data() {
        global $DB;

        $sql = "
            SELECT courseid, c.fullname, COUNT(*) AS count
            FROM {block_blc_modules} blc
            JOIN {course} c on c.id=blc.courseid
            GROUP BY courseid, c.fullname ORDER BY count DESC
        ";
        $blcmodulesbycourses = $DB->get_records_sql($sql);
        return array_values($blcmodulesbycourses);
    }

    /**
     * Get BLC modules data grouped by subject.
     *
     * @return array Array of top 5 subjects with module counts
     */
    private function get_data_by_subject() {
        global $DB;
        $sql = "
            SELECT subject, COUNT(*) AS count
            FROM {block_blc_modules}
            WHERE subject IS NOT NULL AND subject != ''
            GROUP BY subject
            ORDER BY count DESC
        ";
        $subjects = $DB->get_records_sql($sql);
        $data = [];
        foreach ($subjects as $record) {
            $data[] = [$record->subject, $record->count];
        }
        $data = array_slice($data, 0, 5, true); // Top 5 subjects.
        return array_values($data);
    }
}
