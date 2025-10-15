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

/**
 * Class bulk_update_progress_page
 *
 * Renderable class for bulk update progress page using Mustache template
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology <ali@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_update_progress_page implements renderable, templatable {

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;
        
        $data = new stdClass();
        $data->sesskey = sesskey();
        $data->wwwroot = $CFG->wwwroot;
        $data->current_time = date('H:i:s');
        
        // Enable JavaScript section in template
        $data->js = true;
        
        return $data;
    }
}
