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

namespace block_blc_modules\helper;

/**
 * File helper class for BLC modules plugin.
 *
 * Provides utility methods for creating files from various sources including
 * pluginfile URLs and external URLs (including Google Drive).
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_helper {

    /**
     * Create a file by copying from a pluginfile URL source.
     * 
     * @param \file_storage $fs File storage instance
     * @param array $filerecord File record for the new file
     * @param string $pluginfile_url The pluginfile URL to copy from
     * @return \stored_file|false The created file or false on failure
     */
    public static function create_file_from_pluginfile_url($fs, $filerecord, $pluginfile_url) {
        // Parse the pluginfile URL to extract file information
        // URL format: /pluginfile.php/{contextid}/{component}/{filearea}/{itemid}/{filepath}/{filename}
        $url_parts = parse_url($pluginfile_url);
        $path = $url_parts['path'];
        
        // Remove /pluginfile.php/ from the beginning
        $path = str_replace('/pluginfile.php/', '', $path);
        $parts = explode('/', $path);
        
        if (count($parts) < 5) {
            error_log('BLC file_helper: Invalid pluginfile URL format: ' . $pluginfile_url);
            return false;
        }

        $source_contextid = (int)$parts[0];
        $source_component = $parts[1];
        $source_filearea = $parts[2];
        $source_itemid = (int)$parts[3];
        
        // The filename is the last part, filepath is everything in between
        $source_filename = array_pop($parts);
        $source_filepath = '/' . implode('/', array_slice($parts, 4)) . '/';
        
        // If there are no parts after itemid, filepath should be just '/'
        if (empty(array_slice($parts, 4))) {
            $source_filepath = '/';
        }

        // Get the source file from storage
        $source_file = $fs->get_file($source_contextid, $source_component, $source_filearea, 
                                      $source_itemid, $source_filepath, $source_filename);

        if (!$source_file || $source_file->is_directory()) {
            error_log('BLC file_helper: Source file not found or is directory: ' . $pluginfile_url);
            return false;
        }

        // Create the new file by copying content from the source file
        try {
            $new_file = $fs->create_file_from_storedfile($filerecord, $source_file);
            error_log('BLC file_helper: Successfully created file from pluginfile: ' . $filerecord['filename']);
            return $new_file;
        } catch (\Exception $e) {
            error_log('BLC file_helper: Exception creating file from pluginfile: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a file from an external URL with enhanced handling for Google Drive and other cloud services.
     * 
     * @param \file_storage $fs File storage instance
     * @param array $filerecord File record for the new file
     * @param string $url The external URL to download from
     * @return \stored_file|false The created file or false on failure
     */
    public static function create_file_from_external_url($fs, $filerecord, $url) {
        error_log("BLC file_helper: create_file_from_external_url called with URL: " . $url);
        
        // Convert Google Drive sharing URLs to direct download URLs
        $download_url = self::convert_google_drive_url($url);
        error_log("BLC file_helper: Converted URL: " . $download_url);
        
        // Use Moodle's robust download_file_content function
        $content = download_file_content($download_url, null, null, false, 300, 20, true);
        
        if ($content === false || empty($content)) {
            error_log("BLC file_helper: Failed to download content from URL: " . $download_url);
            error_log("BLC file_helper: Content is " . ($content === false ? "FALSE" : "EMPTY"));
            return false;
        }
        
        error_log("BLC file_helper: Downloaded content size: " . strlen($content) . " bytes");
        
        // Create file from the downloaded content
        try {
            error_log("BLC file_helper: Creating file: " . $filerecord['filename']);
            $file = $fs->create_file_from_string($filerecord, $content);
            error_log("BLC file_helper: File created successfully: " . $file->get_filename());
            return $file;
        } catch (\Exception $e) {
            error_log("BLC file_helper: Exception creating file: " . $e->getMessage());
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
        // Check if this is a Google Drive sharing URL
        if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $fileid = $matches[1];
            // Convert to direct download URL
            error_log("BLC file_helper: Converting Google Drive URL, file ID: " . $fileid);
            return "https://drive.google.com/uc?export=download&id=" . $fileid;
        }

        // For other cloud storage services, add similar conversions here if needed
        // For example, Dropbox, OneDrive, etc.

        // Return original URL if no conversion is needed
        return $url;
    }

    /**
     * Get optimized curl options for large file downloads
     *
     * @return array Curl options array
     */
    public static function get_download_curl_options() {
        return [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 1800, // 30 minutes for large files
            CURLOPT_CONNECTTIMEOUT => 60, // 1 minute connection timeout
            CURLOPT_LOW_SPEED_LIMIT => 1024, // 1KB/s minimum speed
            CURLOPT_LOW_SPEED_TIME => 300, // 5 minutes low speed timeout
            CURLOPT_BUFFERSIZE => 128 * 1024, // 128KB buffer for better performance
            CURLOPT_MAX_RECV_SPEED_LARGE => 0, // No speed limit
        ];
    }

    /**
     * Get optimal batch size based on server capabilities
     *
     * @return int Batch size
     */
    public static function get_optimal_batch_size() {
        // Auto-detect based on memory and PHP limits
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = self::parse_memory_limit($memory_limit);

        // Conservative approach: smaller batches for limited memory
        if ($memory_bytes < 256 * 1024 * 1024) { // Less than 256MB
            return 3;
        } elseif ($memory_bytes < 512 * 1024 * 1024) { // Less than 512MB
            return 5;
        } else {
            return 8; // Default for higher memory
        }
    }

    /**
     * Parse PHP memory limit string to bytes
     *
     * @param string $memory_limit PHP memory limit
     * @return int Memory limit in bytes
     */
    private static function parse_memory_limit($memory_limit) {
        if (is_numeric($memory_limit)) {
            return (int)$memory_limit;
        }

        $unit = strtolower(substr($memory_limit, -1));
        $value = (int)substr($memory_limit, 0, -1);

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value; // Assume bytes
        }
    }

    /**
     * Check if server has sufficient resources for large file processing
     *
     * @param int $estimated_file_size Estimated file size in bytes
     * @return array Check results with warnings
     */
    public static function check_system_resources($estimated_file_size = 0) {
        $warnings = [];
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = self::parse_memory_limit($memory_limit);

        // Check memory (need at least 3x file size for processing)
        $required_memory = $estimated_file_size * 3;
        if ($memory_bytes > 0 && $memory_bytes < $required_memory) {
            $warnings[] = "Memory limit ({$memory_limit}) may be insufficient for file size " .
                         round($estimated_file_size / 1024 / 1024, 1) . "MB. Consider increasing memory_limit.";
        }

        // Check execution time
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time > 0 && $max_execution_time < 1800) { // Less than 30 minutes
            $warnings[] = "max_execution_time ({$max_execution_time}s) may be too low for large files. Consider increasing to 1800+.";
        }

        // Check disk space
        $temp_dir = make_temp_directory('scormpackage');
        $available_space = disk_free_space($temp_dir);
        $required_space = $estimated_file_size * 4; // 4x for safety (zip + extract + temp)
        if ($available_space < $required_space) {
            $warnings[] = "Insufficient disk space. Available: " . round($available_space / 1024 / 1024, 1) .
                         "MB, Required: " . round($required_space / 1024 / 1024, 1) . "MB";
        }

        return $warnings;
    }

    /**
     * Get recommended PHP settings for large file processing
     *
     * @return array Recommended settings
     */
    public static function get_recommended_settings() {
        return [
            'memory_limit' => '1024M', // 1GB minimum
            'max_execution_time' => '1800', // 30 minutes
            'max_input_time' => '1800', // 30 minutes
            'upload_max_filesize' => '512M', // Allow large uploads if needed
            'post_max_size' => '512M', // Allow large POST data
        ];
    }
}
