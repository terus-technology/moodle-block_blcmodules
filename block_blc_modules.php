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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/filelib.php');

class block_blc_modules extends block_list {
    function init() {
        $this->title = get_string('pluginname', 'block_blc_modules');
    }

    function get_content() {
        global $CFG, $DB, $OUTPUT,$USER, $COURSE;

        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        $course = $this->page->course;

        if(file_exists($CFG->dirroot. '/lib/jquery/jquery-3.2.1.min.js')){
            $this->page->requires->js(new moodle_url($CFG->wwwroot . '/lib/jquery/jquery-3.2.1.min.js'));
        }else if(file_exists($CFG->wwwroot . '/lib/jquery/jquery-3.2.1.js')){
            $this->page->requires->js(new moodle_url($CFG->wwwroot . '/lib/jquery/jquery-3.2.1.js'));
        }else if(file_exists($CFG->dirroot . '/lib/jquery/jquery-3.1.0.min.js')){
            $this->page->requires->js(new moodle_url($CFG->wwwroot . '/lib/jquery/jquery-3.1.0.min.js'));
        }else if(file_exists($CFG->wwwroot . '/lib/jquery/jquery-3.1.0.min.js')){
            $this->page->requires->js(new moodle_url($CFG->wwwroot . '/lib/jquery/jquery-3.1.0.min.js'));
        }else{
            $this->page->requires->js(new moodle_url($CFG->wwwroot . '/lib/jquery/jquery-1.12.1.min.js'));
        }
        $this->page->requires->js(new moodle_url($CFG->wwwroot . '/blocks/blc_modules/js/custom.js'));
        $this->page->requires->js(new moodle_url($CFG->wwwroot . '/blocks/blc_modules/js/tippyinitiator.js'));
        $this->page->requires->css(new moodle_url($CFG->wwwroot . '/blocks/blc_modules/js/tippytheme.css'));        



        require_once($CFG->dirroot.'/course/lib.php');

        $modinfo = get_fast_modinfo($course);
        $modfullnames = array();

        $archetypes = array();
        
        if (has_capability('block/blc_modules:viewblock', $this->context)) {
            $apikey = get_config('block_blc_modules', 'api_key');
            $this->content->items[] ='<p>Blended Learning Consortium modules are available on this course.<br/> This block is not visible to students.</p>';

            if(isset($CFG->allowstealth)){
                $allowstealthval = $CFG->allowstealth;
            }else{
                $allowstealthval = 0;
            }

            $completion=new completion_info($COURSE);
            if($completion->is_enabled()) {        
                $completionon = 1;
            }else{
                $completionon = 0;
            }

            if(!isset($completionon)){
                $completionon = 0;
            }

            $this->content->items[] =' <div id="addscorm" class="block_blc_modules"></div><input style="display:none;" type="hidden" id="userediting" value="'.$USER->editing.'"><input style="display:none;" type="hidden" id="apikey" value="'.$apikey.'"><input style="display:none;" type="hidden" id="allowstealthvalue" value="'.$allowstealthval.'"><input style="display:none;" type="hidden" id="completionon" value="'.$completionon.'">';
            


            return $this->content;

        }
        //$modfullnames = core_collator::asort($modfullnames);

      

        return $this->content;
    }

    /**
     * Returns the role that best describes this blocks contents.
     *
     * This returns 'navigation' as the blocks contents is a list of links to activities and resources.
     *
     * @return string 'navigation'
     */
    public function get_aria_role() {
        return 'navigation';
    }

    function applicable_formats() {
        return array('all' => true, 'mod' => false, 'my' => false, 'admin' => false,
                     'tag' => false);
    }
     /**
     * Allow the block to have a configuration page
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }
   
}

if (strpos($_SERVER['REQUEST_URI'], "course/view") !== false){
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<script src="https://unpkg.com/tippy.js@3/dist/tippy.all.min.js"></script>';
}

?>