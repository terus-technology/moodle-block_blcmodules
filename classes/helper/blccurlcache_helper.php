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
 * Class blccurl_cache
 *
 * @package    block_blc_modules
 * @copyright  2026 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blccurlcache_helper {
    /** @var string */
    public $dir = '';
    /** @var int */
    public $ttl = 1200;

    /**
     * Constructor
     */
    public function __construct() {
        $this->dir = '/tmp/';
        if (!file_exists($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
        $this->ttl = 1200;
    }

    /**
     * Get cached value
     *
     * @param mixed $param
     * @return bool|string
     */
    public function get($param) {
        $this->cleanup($this->ttl);
        $filename = 'u_' . md5(serialize($param));
        if (file_exists($this->dir . $filename)) {
            $lasttime = filemtime($this->dir . $filename);
            if (time() - $lasttime > $this->ttl) {
                return false;
            } else {
                $fp = fopen($this->dir . $filename, 'r');
                $size = filesize($this->dir . $filename);
                $content = fread($fp, $size);
                return unserialize($content);
            }
        }

        return false;
    }

    /**
     * Set cache value
     *
     * @param mixed $param
     * @param mixed $val
     */
    public function set($param, $val) {
        $filename = 'u_' . md5(serialize($param));
        $fp = fopen($this->dir . $filename, 'w');
        fwrite($fp, serialize($val));
        fclose($fp);
    }

    /**
     * Remove cache files
     *
     * @param int $expire The number os seconds before expiry
     */
    public function cleanup($expire) {
        if ($dir = opendir($this->dir)) {
            while (false !== ($file = readdir($dir))) {
                if (!is_dir($file) && $file != '.' && $file != '..') {
                    $lasttime = @filemtime($this->dir . $file);
                    if (time() - $lasttime > $expire) {
                        @unlink($this->dir . $file);
                    }
                }
            }
        }
    }

    /**
     * delete current user's cache file
     *
     */
    public function refresh() {
        if ($dir = opendir($this->dir)) {
            while (false !== ($file = readdir($dir))) {
                if (!is_dir($file) && $file != '.' && $file != '..') {
                    if (strpos($file, 'u_') !== false) {
                        @unlink($this->dir . $file);
                    }
                }
            }
        }
    }
}
