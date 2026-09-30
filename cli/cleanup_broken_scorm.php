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
 * CLI script that deletes SCORM activities that a failed BLC create left behind.
 *
 * @package    block_blc_modules
 * @copyright  2026 Terus Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use block_blc_modules\middleware\services;

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'courseid' => 0,
        'execute' => false,
    ],
    [
        'h' => 'help',
        'c' => 'courseid',
        'e' => 'execute',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "
Delete SCORM activities that a failed BLC create left behind.

A SCORM course module is broken when one of these is true:
  - it has no scorm record (instance 0 or a missing scorm row), or
  - it is 'localsync', its reference is a local_scormurl or Google Drive URL,
    and it has no package file.

Core restore downloads the package again from reference for these activities and fails.
Activities added in the last hour are skipped, because a SCORM load can still be running.
Without --execute, the script only lists the broken activities.

Options:
-h, --help                Print out this help
-c, --courseid=ID         Check one course only
-e, --execute             Delete the broken activities

Examples:
\$ sudo -u www-data /usr/bin/php blocks/blc_modules/cli/cleanup_broken_scorm.php
\$ sudo -u www-data /usr/bin/php blocks/blc_modules/cli/cleanup_broken_scorm.php --courseid=12 --execute
";

    echo $help;
    exit(0);
}

\core\session\manager::set_user(get_admin());

// Skip activities added in the last hour. A running load has a localsync activity without a package file.
$params = ['modname' => 'scorm', 'localsync' => 'localsync', 'cutoff' => time() - HOURSECS];
$coursewhere = '';
if (!empty($options['courseid'])) {
    $coursewhere = 'AND cm.course = :courseid';
    $params['courseid'] = (int) $options['courseid'];
}

$likes = [];
foreach (['temp_scorm', 'drive.google.com', 'docs.google.com'] as $i => $needle) {
    $likes[] = $DB->sql_like('s.reference', ':ref' . $i, false);
    $params['ref' . $i] = '%' . $DB->sql_like_escape($needle) . '%';
}

$sql = "SELECT cm.id AS cmid, cm.course, s.id AS scormid, s.name
          FROM {course_modules} cm
          JOIN {modules} m ON m.id = cm.module AND m.name = :modname
     LEFT JOIN {scorm} s ON s.id = cm.instance
         WHERE cm.deletioninprogress = 0
           AND cm.added < :cutoff
               $coursewhere
           AND (s.id IS NULL
                OR (s.scormtype = :localsync AND (" . implode(' OR ', $likes) . ")))
      ORDER BY cm.course, cm.id";

$fs = get_file_storage();
$broken = [];
$rs = $DB->get_recordset_sql($sql, $params);
foreach ($rs as $record) {
    if ($record->scormid) {
        // A localsync activity with a package file still works. Keep it.
        $context = context_module::instance($record->cmid, IGNORE_MISSING);
        if ($context && !$fs->is_area_empty($context->id, 'mod_scorm', 'package', 0)) {
            continue;
        }
        $record->reason = 'localsync without a package file';
    } else {
        $record->reason = 'no scorm record';
    }
    $broken[] = $record;
}
$rs->close();

foreach ($broken as $record) {
    cli_writeln("course {$record->course}, cmid {$record->cmid}, '" . ($record->name ?? '') . "': {$record->reason}");
}
cli_writeln(count($broken) . ' broken SCORM activities found.');

if (!$options['execute']) {
    if ($broken) {
        cli_writeln('Dry run. Run again with --execute to delete them.');
    }
    exit(0);
}

$deleted = 0;
foreach ($broken as $record) {
    try {
        services::delete_blc_module($record->cmid);
        $deleted++;
    } catch (Throwable $e) {
        cli_problem("Could not delete cmid {$record->cmid}: " . $e->getMessage());
    }
}
cli_writeln("$deleted of " . count($broken) . ' broken SCORM activities deleted.');
