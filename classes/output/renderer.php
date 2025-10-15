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
 * Class render
 *
 * @package    block_blc_modules
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace block_blc_modules\output;

use plugin_renderer_base;



class renderer extends plugin_renderer_base {

    /**
     * Render the SCORM Report page.
     *
     * @param scorm_report_page $page
     * @return string
     */
    public function render_scorm_report_page(scorm_report_page $page){
        $data = $page->export_for_template($this);
        return $this->render_from_template('block_blc_modules/scorm_report_page', $data);
    }
    /**
     * Render the Validate Settings page.
     *
     * @param validate_settings_page $page
     * @return string
     */
    public function render_validate_settings_page(validate_settings_page $page){
        $data = $page->export_for_template($this);
        return $this->render_from_template('block_blc_modules/validate_settings_page', $data);
    }

    /**
     * Render the update scorm page.
     *
     * @param update_scorm_page $page
     * @return string
     */
    public function render_update_scorm_page(update_scorm_page $page){
        $data = $page->export_for_template($this);
        return $this->render_from_template('block_blc_modules/update_scorm_page', $data);
    }

    /**
     * Render the bulk update progress page.
     *
     * @param bulk_update_progress_page $page
     * @return string
     */
    public function render_bulk_update_progress_page(bulk_update_progress_page $page){
        $data = $page->export_for_template($this);
        return $this->render_from_template('block_blc_modules/bulk_update_progress', $data);
    }

}
