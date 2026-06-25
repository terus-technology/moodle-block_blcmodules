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
 * File helper class for BLC modules plugin.
 *
 * Provides utility methods for creating files from various sources including
 * pluginfile URLs and external URLs (including Google Drive).
 *
 * @package    block_blc_modules
 * @copyright  2022 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules\helper;

use Exception;
use file_storage;
use stored_file;

/**
 * File helper class for BLC modules plugin.
 *
 * Provides utility methods for creating files from various sources including
 * pluginfile URLs and external URLs (including Google Drive).
 */
class file_helper {
    /**
     * Create a file by copying from a pluginfile URL source.
     *
     * @param file_storage $fs File storage instance
     * @param array $filerecord File record for the new file
     * @param string $pluginfileurl The pluginfile URL to copy from
     * @return stored_file|false The created file or false on failure
     */
    public static function create_file_from_pluginfile_url($fs, $filerecord, $pluginfileurl) {
        $logger = new debug_helper();

        // Parse the pluginfile URL to extract file information
        // URL format: /pluginfile.php/{contextid}/{component}/{filearea}/{itemid}/{filepath}/{filename}.
        $urlparts = parse_url($pluginfileurl);
        $path = $urlparts['path'];

        // Remove /pluginfile.php/ from the beginning.
        $path = str_replace('/pluginfile.php/', '', $path);
        $parts = explode('/', $path);

        if (count($parts) < 5) {
            $logger->error(
                'BLC file_helper: The source file URL is not in a valid Moodle pluginfile format.',
                [
                    'The file URL is incomplete.',
                    'The source file URL was generated incorrectly.',
                    'The referenced file no longer exists.',
                ],
                [
                    'Verify the source file exists.',
                    'Check that the pluginfile URL is generated correctly.',
                    'Retry the operation after correcting the file reference.',
                ],
                $pluginfileurl,
                false
            );
            return false;
        }

        $sourcecontextid = (int)$parts[0];
        $sourcecomponent = $parts[1];
        $sourcefilearea = $parts[2];
        $sourceitemid = (int)$parts[3];

        // The filename is the last part, filepath is everything in between.
        $sourcefilename = array_pop($parts);
        $sourcefilepath = '/' . implode('/', array_slice($parts, 4)) . '/';

        // If there are no parts after itemid, filepath should be just '/'.
        if (empty(array_slice($parts, 4))) {
            $sourcefilepath = '/';
        }

        // Get the source file from storage.
        $sourcefile = $fs->get_file(
            $sourcecontextid,
            $sourcecomponent,
            $sourcefilearea,
            $sourceitemid,
            $sourcefilepath,
            $sourcefilename
        );

        if (!$sourcefile || $sourcefile->is_directory()) {
            $logger->error(
                'BLC file_helper: The source file could not be located.',
                [
                    'The file has been deleted.',
                    'The file reference is invalid.',
                    'The referenced item is a directory rather than a file.',
                ],
                [
                    'Verify the source file still exists.',
                    'Check that the file reference is correct.',
                    'Retry the operation after restoring the file.',
                ],
                $pluginfileurl,
                false
            );
            return false;
        }

        // Create the new file by copying content from the source file.
        try {
            $newfile = $fs->create_file_from_storedfile($filerecord, $sourcefile);
            $logger->info('BLC file_helper: Successfully created file from pluginfile: ' . $filerecord['filename']);
            return $newfile;
        } catch (Exception $e) {
            $logger->error(
                'BLC file_helper: The file could not be copied into Moodle storage.',
                [
                    'The destination file record is invalid.',
                    'The Moodle file storage system encountered an error.',
                    'A database or file permission issue occurred.',
                ],
                [
                    'Verify Moodle file storage permissions.',
                    'Check available disk space.',
                    'Review the technical details below.',
                ],
                $e->getMessage(),
                false
            );
            return false;
        }
    }

    /**
     * Create a file from an external URL with enhanced handling for Google Drive and other cloud services.
     *
     * @param file_storage $fs File storage instance
     * @param array $filerecord File record for the new file
     * @param string $url The external URL to download from
     * @return stored_file|false The created file or false on failure
     */
    public static function create_file_from_external_url($fs, $filerecord, $url) {
        $logger = new debug_helper();

        $logger->info("BLC file_helper: create_file_from_external_url called with URL: " . $url);

        // Convert Google Drive sharing URLs to direct download URLs.
        $downloadurl = self::convert_google_drive_url($url);
        $logger->info("BLC file_helper: Converted URL: " . $downloadurl);

        // Use Moodle's robust download_file_content function.
        $content = download_file_content($downloadurl, null, null, false, 300, 20, true);

        if ($content === false || empty($content)) {
            $logger->error(
                'BLC file_helper: The file could not be downloaded from the external source.',
                [
                    'The download URL is invalid.',
                    'The remote server is unavailable.',
                    'The file no longer exists.',
                    'Access to the file is restricted.',
                ],
                [
                    'Verify the file URL is accessible.',
                    'Check that the file exists on the remote server.',
                    'Confirm the file can be downloaded without authentication issues.',
                    'Retry the operation.',
                ],
                sprintf(
                    'url=%s, content=%s',
                    $downloadurl,
                    $content === false ? 'FALSE' : 'EMPTY'
                ),
                false
            );
            return false;
        }

        $logger->info("BLC file_helper: Downloaded content size: " . strlen($content) . " bytes");

        // Create file from the downloaded content.
        try {
            $logger->info("BLC file_helper: Creating file: " . $filerecord['filename']);
            $newfile = $fs->create_file_from_string($filerecord, $content);
            $logger->info("BLC file_helper: File created successfully: " . $newfile->get_filename());
            return $newfile;
        } catch (Exception $e) {
            $logger->error(
                'BLC file_helper: The downloaded file could not be saved into Moodle storage.',
                [
                    'The downloaded content is invalid.',
                    'The file storage system encountered an error.',
                    'A database or file permission issue occurred.',
                ],
                [
                    'Verify Moodle file storage permissions.',
                    'Check available disk space.',
                    'Review the technical details below.',
                    'Retry the operation.',
                ],
                $e->getMessage(),
                false
            );
            return false;
        }
    }

    /**
     * Convert Google Drive sharing URL to direct download URL.
     *
     * @param string $url Original URL
     * @return string Converted URL for direct download
     */
    public static function convert_google_drive_url($url) {
        // Check if this is a Google Drive sharing URL.
        if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $fileid = $matches[1];
            // Convert to direct download URL.
            $logger = new debug_helper();
            $logger->info("BLC file_helper: Converting Google Drive URL, file ID: " . $fileid);
            return "https://drive.google.com/uc?export=download&id=" . $fileid;
        }

        // For other cloud storage services, add similar conversions here if needed.
        // For example, Dropbox, OneDrive, etc.

        // Return original URL if no conversion is needed.
        return $url;
    }

    /**
     * Get optimized curl options for large file downloads
     *
     * @return array Curl options array
     */
    public static function getdownloadcurloptions() {
        return [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 1800, // 30 minutes for large files
            CURLOPT_CONNECTTIMEOUT => 60, // 1 minute connection timeout
            CURLOPT_LOW_SPEED_LIMIT => 1024, // 1KB/s minimum speed
            CURLOPT_LOW_SPEED_TIME => 300, // 5 minutes low speed timeout
            CURLOPT_BUFFERSIZE => 128 * 1024, // 128KB buffer for better performance
            CURLOPT_MAX_RECV_SPEED_LARGE => 0, // No speed limit.
        ];
    }

    /**
     * Get optimal batch size based on server capabilities
     *
     * @return int Batch size
     */
    public static function getoptimalbatchsize() {
        // Auto-detect based on memory and PHP limits.
        $memorylimit = ini_get('memory_limit');
        $memorybytes = self::parsememorylimit($memorylimit);

        // Conservative approach: smaller batches for limited memory.
        if ($memorybytes < 256 * 1024 * 1024) { // Less than 256MB.
            return 3;
        } else if ($memorybytes < 512 * 1024 * 1024) { // Less than 512MB.
            return 5;
        } else {
            return 8; // Default for higher memory.
        }
    }

    /**
     * Parse PHP memory limit string to bytes
     *
     * @param string $memorylimit PHP memory limit
     * @return int Memory limit in bytes
     */
    private static function parsememorylimit($memorylimit) {
        if (is_numeric($memorylimit)) {
            return (int) $memorylimit;
        }

        $unit = strtolower(substr($memorylimit, -1));
        $value = (int)substr($memorylimit, 0, -1);

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value; // Assume bytes.
        }
    }

    /**
     * Check if server has sufficient resources for large file processing
     *
     * @param ?int $estimatedfilesize Estimated file size in bytes
     * @return array Check results with warnings
     */
    public static function checksystemresources($estimatedfilesize = 0) {
        $warnings = [];
        $memorylimit = ini_get('memory_limit');
        $memorybytes = self::parsememorylimit($memorylimit);

        // Check memory (need at least 3x file size for processing).
        $requiredmemory = $estimatedfilesize * 3;
        if ($memorybytes > 0 && $memorybytes < $requiredmemory) {
            $warnings[] = "Memory limit ({$memorylimit}) may be insufficient for file size " .
            round($estimatedfilesize / 1024 / 1024, 1) . "MB. Consider increasing memory_limit.";
        }

        // Check execution time.
        $maxexecutiontime = ini_get('max_execution_time');
        if ($maxexecutiontime > 0 && $maxexecutiontime < 1800) { // Less than 30 minutes.
            $warnings[] = "max_execution_time ({$maxexecutiontime}s) may be too low for large files. Consider increasing to 1800+.";
        }

        // Check disk space.
        $tempdir = make_temp_directory('scormpackage');
        $availablespace = disk_free_space($tempdir);
        $requiredspace = $estimatedfilesize * 4; // 4x for safety (zip + extract + temp)
        if ($availablespace < $requiredspace) {
            $warnings[] = "Insufficient disk space. Available: " . round($availablespace / 1024 / 1024, 1) .
            "MB, Required: " . round($requiredspace / 1024 / 1024, 1) . "MB";
        }

        return $warnings;
    }

    /**
     * Get recommended PHP settings for large file processing
     *
     * @return array Recommended settings
     */
    public static function getrecommendedsettings() {
        return [
            'memory_limit' => '1024M', // 1GB minimum
            'max_execution_time' => '1800', // 30 minutes
            'max_input_time' => '1800', // 30 minutes
            'upload_max_filesize' => '512M', // Allow large uploads if needed.
            'post_max_size' => '512M', // Allow large POST data.
        ];
    }
}
