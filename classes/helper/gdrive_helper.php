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
 * Google Drive helper class for BLC modules.
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology <ali@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for Google Drive operations.
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gdrive_helper {

    /** @var \Google_Service_Drive Google Drive service instance */
    private static $service = null;

    /**
     * Initialize Google Drive client and return service instance.
     * Automatically uses credentials from blocks/scorm_package.
     *
     * @return \Google_Service_Drive
     * @throws \moodle_exception
     */
    public static function get_drive_service(): \Google_Service_Drive {
        global $CFG;

        if (self::$service !== null) {
            return self::$service;
        }

        // Check if vendor autoload exists (composer dependencies).
        $vendorpath = $CFG->dirroot . '/blocks/scorm_package/vendor/autoload.php';
        if (!file_exists($vendorpath)) {
            throw new \moodle_exception('googleapinotinstalled', 'block_blc_modules', '',
                'Google API library not found. Please run composer install in blocks/scorm_package/');
        }

        require_once($vendorpath);

        // Automatically use credentials from scorm_package plugin.
        $credentialspath = get_config('block_scorm_package', 'google_credentials_path');
        if (empty($credentialspath)) {
            // Use default path.
            $credentialspath = '/blocks/scorm_package/classes/task/blc-plugin-268510-444894a2203c.json';
        }

        $fullcredentialspath = $CFG->dirroot . $credentialspath;

        if (!file_exists($fullcredentialspath)) {
            throw new \moodle_exception('googlecredentialsnotfound', 'block_blc_modules', '',
                'Google credentials file not found: ' . $fullcredentialspath);
        }

        // Setup Google Client.
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $fullcredentialspath);
        $client = new \Google_Client();
        $client->setApplicationName('Moodle BLC Modules');
        $client->addScope(\Google_Service_Drive::DRIVE);
        $client->useApplicationDefaultCredentials();

        self::$service = new \Google_Service_Drive($client);

        return self::$service;
    }

    /**
     * Download file from Google Drive and return content.
     *
     * @param string $driveid Google Drive File ID
     * @return string|false File content or false on failure
     * @throws \moodle_exception
     */
    public static function download_file(string $driveid) {
        global $CFG;

        if (empty($driveid)) {
            debugging('Google Drive ID is empty', DEBUG_DEVELOPER);
            return false;
        }

        try {
            $service = self::get_drive_service();

            // Set longer timeout for large files.
            \core_php_time_limit::raise(1800); // 30 minutes.

            // Verify file exists first.
            try {
                $fileMetadata = $service->files->get($driveid);
                if (empty($fileMetadata->getId())) {
                    debugging('File not found in Google Drive: ' . $driveid, DEBUG_DEVELOPER);
                    return false;
                }
                debugging('Downloading from Google Drive: ' . $fileMetadata->getName() . 
                         ' (Size: ' . $fileMetadata->getSize() . ' bytes)', DEBUG_DEVELOPER);
            } catch (\Google_Service_Exception $e) {
                debugging('Google Drive file not found: ' . $driveid . ' - ' . $e->getMessage(), DEBUG_DEVELOPER);
                return false;
            }

            // Download file content with streaming.
            $response = $service->files->get($driveid, ['alt' => 'media']);
            $content = $response->getBody()->getContents();

            if (empty($content)) {
                debugging('Empty content returned from Google Drive for ID: ' . $driveid, DEBUG_DEVELOPER);
                return false;
            }

            debugging('Successfully downloaded ' . strlen($content) . ' bytes from Google Drive', DEBUG_DEVELOPER);
            return $content;

        } catch (\Google_Service_Exception $e) {
            debugging('Google Drive API error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('googledriveapierror', 'block_blc_modules', '', $e->getMessage());
        } catch (\Exception $e) {
            debugging('Unexpected error downloading from Google Drive: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('googledrivedownloaderror', 'block_blc_modules', '', $e->getMessage());
        }
    }

    /**
     * Stream file from Google Drive directly to Moodle file storage.
     * This is more memory-efficient for large files.
     *
     * @param string $driveid Google Drive File ID
     * @param array $filerecord File record for Moodle file storage
     * @param \context $context Context for file storage
     * @return \stored_file|false Stored file object or false on failure
     * @throws \moodle_exception
     */
    public static function stream_to_storage(string $driveid, array $filerecord, \context $context) {
        global $CFG;

        if (empty($driveid)) {
            debugging('Google Drive ID is empty', DEBUG_DEVELOPER);
            return false;
        }

        try {
            $service = self::get_drive_service();

            // Set longer timeout for large files.
            \core_php_time_limit::raise(1800); // 30 minutes.

            // Verify file exists and get metadata.
            $fileMetadata = $service->files->get($driveid);
            if (empty($fileMetadata->getId())) {
                debugging('File not found in Google Drive: ' . $driveid, DEBUG_DEVELOPER);
                return false;
            }

            $filename = $fileMetadata->getName();
            $filesize = $fileMetadata->getSize();

            debugging('Streaming from Google Drive: ' . $filename . ' (Size: ' . $filesize . ' bytes)', DEBUG_DEVELOPER);

            // Download file content.
            $response = $service->files->get($driveid, ['alt' => 'media']);
            $content = $response->getBody()->getContents();

            if (empty($content)) {
                debugging('Empty content returned from Google Drive', DEBUG_DEVELOPER);
                return false;
            }

            // Create file in Moodle storage.
            $fs = get_file_storage();
            
            // Ensure filename is set in filerecord.
            if (empty($filerecord['filename'])) {
                $filerecord['filename'] = clean_param($filename, PARAM_FILE);
            }

            // Create file from content.
            $storedfile = $fs->create_file_from_string($filerecord, $content);

            if (!$storedfile) {
                debugging('Failed to create stored file from Google Drive content', DEBUG_DEVELOPER);
                return false;
            }

            debugging('Successfully stored file from Google Drive: ' . $storedfile->get_filename() . 
                     ' (' . $storedfile->get_filesize() . ' bytes)', DEBUG_DEVELOPER);

            return $storedfile;

        } catch (\Google_Service_Exception $e) {
            debugging('Google Drive API error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('googledriveapierror', 'block_blc_modules', '', $e->getMessage());
        } catch (\Exception $e) {
            debugging('Unexpected error streaming from Google Drive: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('googledrivestreamingerror', 'block_blc_modules', '', $e->getMessage());
        }
    }

    /**
     * Check if Google Drive streaming is enabled.
     * Always returns true - streaming is always enabled when Google Drive ID is available.
     *
     * @return bool
     */
    public static function is_streaming_enabled(): bool {
        // Always enabled - no user configuration needed.
        return true;
    }

    /**
     * Extract Google Drive ID from various URL formats.
     *
     * @param string $url URL that might contain a Drive ID
     * @return string|false Drive ID or false if not found
     */
    public static function extract_drive_id(string $url) {
        // If the input is already a valid Drive ID (25-33 alphanumeric chars with dashes/underscores).
        if (preg_match('/^[a-zA-Z0-9_-]{25,35}$/', trim($url))) {
            return trim($url);
        }

        // Try different Google Drive URL patterns.
        $patterns = [
            '/\/file\/d\/([a-zA-Z0-9_-]+)/',           // /file/d/{id}
            '/id=([a-zA-Z0-9_-]+)/',                    // id={id}
            '/folders\/([a-zA-Z0-9_-]+)/',              // /folders/{id}
            '/\/d\/([a-zA-Z0-9_-]+)/',                  // /d/{id}
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return false;
    }

    /**
     * Check if a URL is a Google Drive URL.
     *
     * @param string $url URL to check
     * @return bool
     */
    public static function is_gdrive_url(string $url): bool {
        return (stripos($url, 'drive.google.com') !== false || 
                stripos($url, 'docs.google.com') !== false);
    }

    /**
     * Validate if a Google Drive file is accessible.
     * This method checks if a file exists and is accessible before attempting download.
     *
     * @param string $driveid Google Drive File ID
     * @return bool True if file is accessible, false otherwise
     */
    public static function validate_drive_file(string $driveid): bool {
        if (empty($driveid)) {
            debugging('Google Drive ID is empty for validation', DEBUG_DEVELOPER);
            return false;
        }

        try {
            $service = self::get_drive_service();

            // Try to get file metadata - this will fail if file doesn't exist or is not accessible
            $fileMetadata = $service->files->get($driveid, ['fields' => 'id,name,size,mimeType,trashed']);
            
            // Check if file exists
            if (empty($fileMetadata->getId())) {
                debugging('Google Drive file not found: ' . $driveid, DEBUG_DEVELOPER);
                error_log('BLC Modules: Google Drive validation failed - file not found: ' . $driveid);
                return false;
            }

            // Check if file is trashed
            if ($fileMetadata->getTrashed()) {
                debugging('Google Drive file is in trash: ' . $driveid, DEBUG_DEVELOPER);
                error_log('BLC Modules: Google Drive validation failed - file is trashed: ' . $driveid);
                return false;
            }

            // Check if file has content (size > 0)
            $filesize = $fileMetadata->getSize();
            if ($filesize === null || $filesize <= 0) {
                debugging('Google Drive file has no content: ' . $driveid, DEBUG_DEVELOPER);
                error_log('BLC Modules: Google Drive validation failed - file has no content: ' . $driveid);
                return false;
            }

            debugging('Google Drive file validation passed: ' . $fileMetadata->getName() . 
                     ' (Size: ' . $filesize . ' bytes)', DEBUG_DEVELOPER);
            error_log('BLC Modules: Google Drive file validated successfully: ' . $fileMetadata->getName() . 
                     ' (' . $filesize . ' bytes)');
            
            return true;

        } catch (\Google_Service_Exception $e) {
            // This exception occurs when file doesn't exist, is not shared, or user doesn't have access
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            debugging('Google Drive validation failed - API error (Code: ' . $errorCode . '): ' . $errorMessage, DEBUG_DEVELOPER);
            error_log('BLC Modules: Google Drive validation failed for ID: ' . $driveid . 
                     ' - Error ' . $errorCode . ': ' . $errorMessage);
            
            // Log specific error types
            if ($errorCode == 404) {
                error_log('BLC Modules: File not found or not accessible (404)');
            } else if ($errorCode == 403) {
                error_log('BLC Modules: Access denied - file may not be shared properly (403)');
            }
            
            return false;
            
        } catch (\Exception $e) {
            debugging('Unexpected error validating Google Drive file: ' . $e->getMessage(), DEBUG_DEVELOPER);
            error_log('BLC Modules: Unexpected validation error: ' . $e->getMessage());
            return false;
        }
    }
}
