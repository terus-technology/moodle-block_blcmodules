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
 * This file  will validate the settings.
 *
 * @package    block_blc_modules
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function get_subjects(){
	global $DB;
    $subjects = [];
    // Get all scormurls and extract subjects using PHP for cross-database compatibility
    $sql = "SELECT DISTINCT scormurl FROM {block_blc_modules} WHERE scormurl LIKE '%/%/%'";
    $results = $DB->get_fieldset_sql($sql);
    foreach ($results as $scormurl) {
        if (!empty($scormurl)) {
            // Extract subject from URL path (second-to-last segment)
            $parts = explode('/', trim($scormurl, '/'));
            if (count($parts) >= 3) {
                $subject = $parts[count($parts) - 2]; // Get second-to-last part
                if (!empty($subject)) {
                    $subjects[$subject] = $subject;
                }
            }
        }
    }
    return $subjects;
}

function array_sort($array, $on, $order){

    $new_array = array();
    $sortable_array = array();

    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }

        switch ($order) {
            case 'ASC':
                asort($sortable_array);
                break;
            case 'DESC':
                arsort($sortable_array);
                break;
        }

        foreach ($sortable_array as $k => $v) {
            $new_array[$k] = $array[$k];
        }
    }

    return $new_array;
}
