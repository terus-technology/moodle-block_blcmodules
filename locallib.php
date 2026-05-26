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

/**
 * Get unique subjects from BLC modules.
 * 
 * This function retrieves a list of unique subjects from the block_blc_modules table.
 * In Moodle 4.5+, it uses the dedicated 'subject' field instead of parsing URLs,
 * which provides better performance and reliability.
 *
 * @param int|null $courseid Optional course ID to filter by specific course
 * @return array Array of unique subjects in format [subject => subject] for backward compatibility
 * @throws dml_exception If database error occurs
 */
function get_subjects(?int $courseid = null): array {
    global $DB;
    
    // Build query to get distinct subjects
    $params = [];
    $sql = "SELECT DISTINCT subject 
            FROM {block_blc_modules}
            WHERE subject IS NOT NULL 
              AND " . $DB->sql_compare_text('subject') . " != ''";
    
    // Add course filter if specified
    if ($courseid !== null) {
        $sql .= " AND courseid = :courseid";
        $params['courseid'] = $courseid;
    }
    
    $sql .= " ORDER BY subject ASC";
    
    // Get distinct subjects from database
    $records = $DB->get_records_sql($sql, $params);
    
    // Format as [subject => subject] for backward compatibility with existing code
    $subjects = [];
    foreach ($records as $record) {
        $subject = trim($record->subject);
        if (!empty($subject)) {
            $subjects[$subject] = $subject;
        }
    }
    
    return $subjects;
}

/**
 * Sort a multi-dimensional array by a specific key.
 * 
 * @deprecated since Moodle 4.5. Use core_collator::asort() or usort() with custom comparator instead.
 * @param array $array The array to sort
 * @param string $on The key to sort by
 * @param string $order Sort order: 'ASC' or 'DESC'
 * @return array The sorted array
 */
function array_sort(array $array, string $on, string $order): array {

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
