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
 * Privacy Subsystem implementation for block_blc_modules.
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for block_blc_modules.
 *
 * Implements the full privacy API to support GDPR data export and deletion
 * for user data stored in block_blc_modules, block_blc_modules_doc,
 * and block_blc_modules_log tables.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_blc_modules',
            [
                'userid' => 'privacy:metadata:block_blc_modules:userid',
                'courseid' => 'privacy:metadata:block_blc_modules:courseid',
                'sectionid' => 'privacy:metadata:block_blc_modules:sectionid',
                'cmid' => 'privacy:metadata:block_blc_modules:cmid',
                'scormid' => 'privacy:metadata:block_blc_modules:scormid',
                'scormurl' => 'privacy:metadata:block_blc_modules:scormurl',
                'subject' => 'privacy:metadata:block_blc_modules:subject',
                'version' => 'privacy:metadata:block_blc_modules:version',
                'timecreated' => 'privacy:metadata:block_blc_modules:timecreated',
                'timemodified' => 'privacy:metadata:block_blc_modules:timemodified',
            ],
            'privacy:metadata:block_blc_modules'
        );

        $collection->add_database_table(
            'block_blc_modules_doc',
            [
                'userid' => 'privacy:metadata:block_blc_modules_doc:userid',
                'courseid' => 'privacy:metadata:block_blc_modules_doc:courseid',
                'blcmoduleid' => 'privacy:metadata:block_blc_modules_doc:blcmoduleid',
                'sectionid' => 'privacy:metadata:block_blc_modules_doc:sectionid',
                'cmid' => 'privacy:metadata:block_blc_modules_doc:cmid',
                'scormid' => 'privacy:metadata:block_blc_modules_doc:scormid',
                'scormurl' => 'privacy:metadata:block_blc_modules_doc:scormurl',
                'version' => 'privacy:metadata:block_blc_modules_doc:version',
                'timecreated' => 'privacy:metadata:block_blc_modules_doc:timecreated',
                'timemodified' => 'privacy:metadata:block_blc_modules_doc:timemodified',
            ],
            'privacy:metadata:block_blc_modules_doc'
        );

        $collection->add_database_table(
            'block_blc_modules_log',
            [
                'userid' => 'privacy:metadata:block_blc_modules_log:userid',
                'courseid' => 'privacy:metadata:block_blc_modules_log:courseid',
                'sectionnumber' => 'privacy:metadata:block_blc_modules_log:sectionnumber',
                'process_type' => 'privacy:metadata:block_blc_modules_log:process_type',
                'process_status' => 'privacy:metadata:block_blc_modules_log:process_status',
                'session_id' => 'privacy:metadata:block_blc_modules_log:session_id',
                'parameters' => 'privacy:metadata:block_blc_modules_log:parameters',
                'scormurl' => 'privacy:metadata:block_blc_modules_log:scormurl',
                'scormname' => 'privacy:metadata:block_blc_modules_log:scormname',
                'scormid' => 'privacy:metadata:block_blc_modules_log:scormid',
                'cmid' => 'privacy:metadata:block_blc_modules_log:cmid',
                'log_level' => 'privacy:metadata:block_blc_modules_log:log_level',
                'message' => 'privacy:metadata:block_blc_modules_log:message',
                'error_details' => 'privacy:metadata:block_blc_modules_log:error_details',
                'ip_address' => 'privacy:metadata:block_blc_modules_log:ip_address',
                'user_agent' => 'privacy:metadata:block_blc_modules_log:user_agent',
                'timecreated' => 'privacy:metadata:block_blc_modules_log:timecreated',
            ],
            'privacy:metadata:block_blc_modules_log'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :contextlevel
                   AND ctx.instanceid IN (
                       SELECT DISTINCT courseid FROM {block_blc_modules} WHERE userid = :userid1
                        UNION
                       SELECT DISTINCT courseid FROM {block_blc_modules_doc} WHERE userid = :userid2
                        UNION
                       SELECT DISTINCT courseid FROM {block_blc_modules_log} WHERE userid = :userid3
                   )";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;

            // Export block_blc_modules data.
            $modules = $DB->get_records('block_blc_modules', ['userid' => $userid, 'courseid' => $courseid]);
            if (!empty($modules)) {
                $moduledata = [];
                foreach ($modules as $module) {
                    $moduledata[] = [
                        'subject' => $module->subject ?? '',
                        'scormurl' => $module->scormurl,
                        'version' => $module->version,
                        'sectionid' => $module->sectionid,
                        'cmid' => $module->cmid,
                        'scormid' => $module->scormid,
                        'timecreated' => transform::datetime($module->timecreated),
                        'timemodified' => transform::datetime($module->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:metadata:block_blc_modules', 'block_blc_modules')],
                    (object) ['modules' => $moduledata]
                );
            }

            // Export block_blc_modules_doc data.
            $docs = $DB->get_records('block_blc_modules_doc', ['userid' => $userid, 'courseid' => $courseid]);
            if (!empty($docs)) {
                $docdata = [];
                foreach ($docs as $doc) {
                    $docdata[] = [
                        'blcmoduleid' => $doc->blcmoduleid,
                        'scormurl' => $doc->scormurl,
                        'version' => $doc->version,
                        'sectionid' => $doc->sectionid,
                        'cmid' => $doc->cmid,
                        'scormid' => $doc->scormid,
                        'timecreated' => transform::datetime($doc->timecreated),
                        'timemodified' => transform::datetime($doc->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:metadata:block_blc_modules_doc', 'block_blc_modules')],
                    (object) ['documents' => $docdata]
                );
            }

            // Export block_blc_modules_log data.
            $logs = $DB->get_records('block_blc_modules_log', ['userid' => $userid, 'courseid' => $courseid]);
            if (!empty($logs)) {
                $logdata = [];
                foreach ($logs as $log) {
                    $logdata[] = [
                        'sectionnumber' => $log->sectionnumber,
                        'process_type' => $log->process_type,
                        'process_status' => $log->process_status,
                        'session_id' => $log->session_id ?? '',
                        'parameters' => $log->parameters ?? '',
                        'scormurl' => $log->scormurl ?? '',
                        'scormname' => $log->scormname ?? '',
                        'scormid' => $log->scormid ?? '',
                        'cmid' => $log->cmid ?? '',
                        'log_level' => $log->log_level,
                        'message' => $log->message,
                        'error_details' => $log->error_details ?? '',
                        'ip_address' => $log->ip_address ?? '',
                        'user_agent' => $log->user_agent ?? '',
                        'timecreated' => transform::datetime($log->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:metadata:block_blc_modules_log', 'block_blc_modules')],
                    (object) ['logs' => $logdata]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;

        $DB->delete_records('block_blc_modules', ['courseid' => $courseid]);
        $DB->delete_records('block_blc_modules_doc', ['courseid' => $courseid]);
        $DB->delete_records('block_blc_modules_log', ['courseid' => $courseid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;

            $DB->delete_records('block_blc_modules', ['userid' => $userid, 'courseid' => $courseid]);
            $DB->delete_records('block_blc_modules_doc', ['userid' => $userid, 'courseid' => $courseid]);
            $DB->delete_records('block_blc_modules_log', ['userid' => $userid, 'courseid' => $courseid]);
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;

        $sql = "SELECT DISTINCT userid FROM {block_blc_modules} WHERE courseid = :courseid1
                 UNION
                SELECT DISTINCT userid FROM {block_blc_modules_doc} WHERE courseid = :courseid2
                 UNION
                SELECT DISTINCT userid FROM {block_blc_modules_log} WHERE courseid = :courseid3";

        $userlist->add_from_sql('userid', $sql, [
            'courseid1' => $courseid,
            'courseid2' => $courseid,
            'courseid3' => $courseid,
        ]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;
        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['courseid' => $courseid], $inparams);

        $DB->delete_records_select('block_blc_modules', "courseid = :courseid AND userid $insql", $params);
        $DB->delete_records_select('block_blc_modules_doc', "courseid = :courseid AND userid $insql", $params);
        $DB->delete_records_select('block_blc_modules_log', "courseid = :courseid AND userid $insql", $params);
    }
}
