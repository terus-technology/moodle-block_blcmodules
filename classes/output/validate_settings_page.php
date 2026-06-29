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

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use stdClass;
use block_blc_modules\helper\blccurl_helper;
use block_blc_modules\helper\debug_helper;
use Exception;
use moodle_url;

/**
 * Class validate_settings_page
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validate_settings_page implements renderable, templatable {
    /**
     * @var string URL for the page
     */
    public $url;

    /**
     * @var moodle_url Base URL for the page
     */
    public $baseurl;

    /**
     * Constructor.
     *
     * @param array $url URL for the page
     * @param moodle_url $baseurl Base URL for the page
     * @return void
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

        // Get validation data.
        $validationdata = $this->get_data();

        // Merge validation data into the main data object.
        foreach ($validationdata as $key => $value) {
            $data->$key = $value;
        }

        return $data;
    }

    /**
     * Get validation data for the settings.
     *
     * @return array Validation results including status and configuration details
     */
    private function get_data() {
        global $CFG;

        $logger = new debug_helper();

        $apikey = get_config('block_blc_modules', 'api_key');
        $token = get_config('block_blc_modules', 'token');
        $domainname = get_config('block_blc_modules', 'domainname');
        $requesturi = $CFG->wwwroot;
        $functionname = 'local_scormurl_check_scormurls';

        $serverurl = new moodle_url($domainname . '/webservice/rest/server.php', [
            'wstoken' => $token,
            'wsfunction' => $functionname,
            'apikey' => $apikey,
            'requesturi' => $requesturi,
            'moodlewsrestformat' => 'json',
        ]);

        // Initialize validation results.
        $validationresults = [];
        $responses = '';
        $urlokay = 'false';
        $apiokay = 'false';

        try {
            $curl = new blccurl_helper();
            $curl->set_header('Content-Type: application/json; charset=utf-8');
            $responses = $curl->post($serverurl->out(false), '', ['CURLOPT_FAILONERROR' => true]);

            // FIXED: Parse JSON response correctly instead of CSV
            // Expected response: {"Status":"OK","ModuleCount":123} or {"Status":"Authentication failed"}
            // Note: Response might be double-encoded, so we need to handle that.
            $jsonresponse = json_decode($responses, true);

            // Check if response is a string (double-encoded JSON).
            if (is_string($jsonresponse)) {
                $logger->info('BLC validate_settings: Response is double-encoded, decoding again');
                $jsonresponse = json_decode($jsonresponse, true);
            }

            if ($jsonresponse && isset($jsonresponse['Status'])) {
                if ($jsonresponse['Status'] === 'OK') {
                    // Both API key and URL are valid.
                    $urlokay = 'true';
                    $apiokay = 'true';

                    // Store module count if available.
                    if (isset($jsonresponse['ModuleCount'])) {
                        $validationresults['module_count'] = $jsonresponse['ModuleCount'];
                    }

                    $logger->info('BLC validate_settings: Validation SUCCESS - ModuleCount: ' .
                    ($jsonresponse['ModuleCount'] ?? 'N/A'));
                } else {
                    // Authentication failed - both invalid
                    // Note: Current check_scormurls function validates both together,
                    // so we can't determine which specific part failed.
                    $urlokay = 'false';
                    $apiokay = 'false';

                    // Store error message if available.
                    if (isset($jsonresponse['error'])) {
                        $validationresults['api_error'] = $jsonresponse['error'];
                    }

                    $logger->error(
                        'BLC validate_settings_page: The BLC configuration could not be validated.',
                        [
                            'The API credentials are invalid.',
                            'The configured BLC server URL is incorrect.',
                            'The BLC service rejected the validation request.',
                        ],
                        [
                            'Verify the API Token and API Key settings.',
                            'Confirm the configured BLC server URL is correct.',
                            'Retry the validation after updating the settings.',
                        ],
                        sprintf(
                            'Status=%s',
                            $jsonresponse['Status'] ?? 'Unknown'
                        ),
                        false
                    );
                }
            } else {
                // Invalid response format.
                $urlokay = 'false';
                $apiokay = 'false';
                $logger->error(
                    ' BLC validate_settings_page: The BLC server returned an invalid response.',
                    [
                        'The BLC service returned malformed data.',
                        'The server response format has changed.',
                        'A proxy or network device modified the response.',
                    ],
                    [
                        'Verify that the BLC service is operating correctly.',
                        'Check for recent API changes.',
                        'Review the technical details below.',
                    ],
                    substr($responses, 0, 500),
                    false
                );
            }
        } catch (Exception $e) {
            // Handle connection errors.
            $validationresults['connection_error'] = true;
            $validationresults['error_message'] = $e->getMessage();
            $logger->error(
                'BLC validate_settings_page: Unable to connect to the BLC service.',
                [
                    'The BLC server is unavailable.',
                    'The configured server URL is incorrect.',
                    'A network connectivity issue occurred.',
                    'The remote service is temporarily unavailable.',
                ],
                [
                    'Verify the BLC server is online.',
                    'Check the configured server URL.',
                    'Confirm network connectivity from the Moodle server.',
                    'Retry the validation later.',
                ],
                $e->getMessage(),
                false
            );
        }

        // Prepare alert messages.
        $validationresults['apisuccess'] = get_string('apisuccess', 'block_blc_modules');
        $validationresults['urlsuccess'] = get_string('urlsuccess', 'block_blc_modules');
        $validationresults['apifail'] = get_string('apifail', 'block_blc_modules');
        $validationresults['urlfail'] = get_string('urlfail', 'block_blc_modules');
        $validationresults['successboth'] = get_string('successboth', 'block_blc_modules');
        $validationresults['failboth'] = get_string('failboth', 'block_blc_modules');
        $validationresults['failone'] = get_string('failone', 'block_blc_modules');
        $validationresults['refresh_text'] = get_string('refresh', 'block_blc_modules');
        $validationresults['return_text'] = get_string('return', 'block_blc_modules');

        // URLs for buttons.
        $validationresults['refresh_url'] = $CFG->wwwroot . '/blocks/blc_modules/validate_settings.php';
        $validationresults['settings_url'] = $CFG->wwwroot . '/admin/settings.php?section=blocksettingblc_modules';

        // Determine validation status.
        $urlvalid = (strpos($urlokay, 'true') !== false);
        $apivalid = (strpos($apiokay, 'true') !== false);

        $validationresults['url_valid'] = $urlvalid;
        $validationresults['api_valid'] = $apivalid;
        $validationresults['both_valid'] = $urlvalid && $apivalid;
        $validationresults['both_invalid'] = !$urlvalid && !$apivalid;
        $validationresults['partial_valid'] = ($urlvalid && !$apivalid) || (!$urlvalid && $apivalid);

        // Debug information (optional).
        $validationresults['debug_info'] = [
            'requesturi' => $requesturi,
            'apikey' => substr($apikey, 0, 8) . '...', // Show only first 8 chars for security.
            'token' => substr($token, 0, 8) . '...', // Show only first 8 chars for security.
            'domainname' => $domainname,
            'serverurl' => $serverurl,
            'responses' => $responses,
            'urlokay' => $urlokay,
            'apiokay' => $apiokay,
        ];

        return $validationresults;
    }
}
