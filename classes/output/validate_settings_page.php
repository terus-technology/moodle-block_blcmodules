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
use block_blc_modules\helper\blccurl;
use moodle_url;

/**
 * Class validate_settings_page
 *
 * @package    block_blc_modules
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class validate_settings_page implements renderable, templatable {

    public $url;
    public $baseurl;

     /**
     * Constructor.
     *
     * @param array $url URL for the page
     * @param moodle_url $baseurl Base URL for the page
     * 
     */
    public function __construct($url, $baseurl) {
        $this->url = $url;
        $this->baseurl = $baseurl;
    }

    /**
     * Render the Validate Settings page.
     *
     * @param renderer_base $output
     * @return string
     */

    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->url = $this->url;
        $data->baseurl = $this->baseurl;

        // Get validation data
        $validationdata = $this->get_data();
        
        // Merge validation data into the main data object
        foreach ($validationdata as $key => $value) {
            $data->$key = $value;
        }

        return $data;
    }

    private function get_data() {
        global $DB, $CFG;

        $apikey = get_config('block_blc_modules', 'api_key');
        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');
        $requesturi = $CFG->wwwroot; // Define the missing variable
        
        $function_name = 'validate_blc_settings';
        $serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token
            . '&wsfunction='.$function_name . '&apikey='.$apikey. '&requesturi='.$requesturi;
        
        // Initialize validation results
        $validationresults = [];
        $responses = '';
        $urlokay = 'false';
        $apiokay = 'false';
        
        try {
            $curl = new blccurl;
            $curl->setHeader('Content-Type: application/json; charset=utf-8');
            $responses = $curl->post($serverurl, '', array('CURLOPT_FAILONERROR' => true));
            
            $bothresponses = explode(",", $responses);
            $urlokay = isset($bothresponses[0]) ? $bothresponses[0] : 'false';
            $apiokay = isset($bothresponses[1]) ? $bothresponses[1] : 'false';
        } catch (\Exception $e) {
            // Handle connection errors
            $validationresults['connection_error'] = true;
            $validationresults['error_message'] = $e->getMessage();
        }

        // Prepare alert messages
        $validationresults['apisuccess'] = get_string('apisuccess', 'block_blc_modules');
        $validationresults['urlsuccess'] = get_string('urlsuccess', 'block_blc_modules');
        $validationresults['apifail'] = get_string('apifail', 'block_blc_modules');
        $validationresults['urlfail'] = get_string('urlfail', 'block_blc_modules');
        $validationresults['successboth'] = get_string('successboth', 'block_blc_modules');
        $validationresults['failboth'] = get_string('failboth', 'block_blc_modules');
        $validationresults['failone'] = get_string('failone', 'block_blc_modules');
        $validationresults['refresh_text'] = get_string('refresh', 'block_blc_modules');
        $validationresults['return_text'] = get_string('return', 'block_blc_modules');
        
        // URLs for buttons
        $validationresults['refresh_url'] = $CFG->wwwroot . '/blocks/blc_modules/validate_settings.php';
        $validationresults['settings_url'] = $CFG->wwwroot . '/admin/settings.php?section=blocksettingblc_modules';
        
        // Determine validation status
        $url_valid = (strpos($urlokay, 'true') !== false);
        $api_valid = (strpos($apiokay, 'true') !== false);
        
        $validationresults['url_valid'] = $url_valid;
        $validationresults['api_valid'] = $api_valid;
        $validationresults['both_valid'] = $url_valid && $api_valid;
        $validationresults['both_invalid'] = !$url_valid && !$api_valid;
        $validationresults['partial_valid'] = ($url_valid && !$api_valid) || (!$url_valid && $api_valid);
        
        // Debug information (optional)
        $validationresults['debug_info'] = [
            'requesturi' => $requesturi,
            'apikey' => substr($apikey, 0, 8) . '...', // Show only first 8 chars for security
            'token' => substr($token, 0, 8) . '...', // Show only first 8 chars for security
            'domainname' => $domainname,
            'serverurl' => $serverurl,
            'responses' => $responses,
            'urlokay' => $urlokay,
            'apiokay' => $apiokay
        ];
        
        return $validationresults;
    }
}
