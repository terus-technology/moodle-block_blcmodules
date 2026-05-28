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
 * Upgrade the block_blc_module database.
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for block_blc_modules.
 *
 * @param int $oldversion The version number of the plugin that was installed.
 * @return boolean
 */
function xmldb_block_blc_modules_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2020062704) {
        $table = new xmldb_table('block_blc_modules_doc');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('blcmoduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sectionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('scormid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('scormurl', XMLDB_TYPE_CHAR, '256', null, null, null, null);
        $table->add_field('version', XMLDB_TYPE_INTEGER, '15', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for fees.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Tuition savepoint reached.
        upgrade_block_savepoint(true, 2020062704, 'blc_modules');
    }

    // Upgrade for Moodle 5.0 compatibility - increase URL field length for MSSQL compatibility.
    if ($oldversion < 2025102801) {
        // Increase scormurl field length in block_blc_modules table.
        $table = new xmldb_table('block_blc_modules');
        $field = new xmldb_field('scormurl', XMLDB_TYPE_CHAR, '500', null, XMLDB_NOTNULL, null, null);

        // Launch change of precision for field scormurl.
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        // Increase scormurl field length in block_blc_modules_doc table.
        $table = new xmldb_table('block_blc_modules_doc');
        $field = new xmldb_field('scormurl', XMLDB_TYPE_CHAR, '500', null, null, null, null);

        // Launch change of precision for field scormurl.
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        // Block savepoint reached.
        upgrade_block_savepoint(true, 2025102801, 'blc_modules');
    }

    // Add subject field to block_blc_modules table.
    if ($oldversion < 2025102900) {
        $table = new xmldb_table('block_blc_modules');
        $field = new xmldb_field('subject', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'scormurl');

        // Add field subject.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Block savepoint reached.
        upgrade_block_savepoint(true, 2025102900, 'blc_modules');
    }

    if ($oldversion < 2025011600) {
        $table = new xmldb_table('block_blc_modules');
        $field = new xmldb_field('subject', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Populate existing records.
            $records = $DB->get_records('block_blc_modules');
            foreach ($records as $record) {
                $scormurls = explode('/', trim($record->scormurl, '/'));
                $count = count($scormurls);
                if ($count > 2) {
                    $subject = $scormurls[$count - 3];
                    $DB->set_field('block_blc_modules', 'subject', $subject, ['id' => $record->id]);
                }
            }
        }

        upgrade_block_savepoint(true, 2025011600, 'blc_modules');
    }

    // Performance optimization: Add indexes for bulk update queries.
    if ($oldversion < 2025110600) {
        $table = new xmldb_table('block_blc_modules');

        // Add index on scormid for faster lookups.
        $index = new xmldb_index('scormid_idx', XMLDB_INDEX_NOTUNIQUE, ['scormid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Add index on cmid for faster lookups.
        $index = new xmldb_index('cmid_idx', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Add composite index on scormid+version for bulk update comparison.
        $index = new xmldb_index('scormid_version_idx', XMLDB_INDEX_NOTUNIQUE, ['scormid', 'version']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_block_savepoint(true, 2025110600, 'blc_modules');
    }

    // Add SCORM load logging table.
    if ($oldversion < 2025120100) {
        $table = new xmldb_table('block_blc_modules_log');

        // Add fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sectionnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('process_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('process_status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('session_id', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('parameters', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('scormurl', XMLDB_TYPE_CHAR, '500', null, null, null, null);
        $table->add_field('scormname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('scormid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('log_level', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('error_details', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('ip_address', XMLDB_TYPE_CHAR, '45', null, null, null, null);
        $table->add_field('user_agent', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Add keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Add indexes for performance.
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('session_idx', XMLDB_INDEX_NOTUNIQUE, ['session_id']);
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('process_status_idx', XMLDB_INDEX_NOTUNIQUE, ['process_status']);
        $table->add_index('log_level_idx', XMLDB_INDEX_NOTUNIQUE, ['log_level']);

        // Create table if it doesn't exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2025120100, 'blc_modules');
    }

    return true;
}
