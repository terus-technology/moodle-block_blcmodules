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
 * Bulk Update - Main entry point for bulk SCORM module updates
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_blc_modules\helper\debug_helper;
use block_blc_modules\output\bulk_update_progress_page;
use core\output\notification;

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');
require_once($CFG->dirroot.'/mod/scorm/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

require_login(null, false);

global $DB, $USER, $CFG, $PAGE, $OUTPUT, $SITE;

// Security: Require system config capability (admin only).
require_capability('moodle/site:config', context_system::instance());

$sesskey = optional_param('sesskey', '', PARAM_RAW);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$action = optional_param('action', '', PARAM_RAW);

// Security: Validate sesskey for CSRF protection when action is continue.
if ($action == 'continue') {
    require_sesskey();
}

$title = get_string('pluginname', 'block_blc_modules');
$heading = $SITE->fullname;
$url = '/blocks/blc_modules/bulk_update.php';

$baseurl = new moodle_url($url);

$PAGE->set_url(new moodle_url($url));
$PAGE->set_pagelayout('admin');
$PAGE->set_context(context_system::instance());
$PAGE->set_title("Bulk Update");
$PAGE->set_heading($heading);
$PAGE->set_cacheable(false);
$PAGE->requires->jquery();
$PAGE->requires->js_call_amd('block_blc_modules/module', 'bulkUpdateInit');

$logger = new debug_helper();

if ($action == 'continue') {
    // Initialize session for progress tracking.
    if (!isset($_SESSION['bulk_update_progress'])) {
        $_SESSION['bulk_update_progress'] = [
            'status' => 'idle',
            'total' => 0,
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'log' => [],
            'complete' => false,
        ];
    }

    // Call AMD module to initialize progress tracking.
    $PAGE->requires->js_call_amd('block_blc_modules/module', 'bulkUpdateProgressInit', [[
        'endpoint' => $CFG->wwwroot . '/blocks/blc_modules/bulk_update_processor.php',
        'sesskey' => sesskey(),
    ]]);

    // Render progress page using Mustache template with error handling.
    $PAGE->set_pagelayout('standard');

    try {
        echo $OUTPUT->header();

        $renderer = $PAGE->get_renderer('block_blc_modules');
        $progresspage = new bulk_update_progress_page();
        echo $renderer->render($progresspage);

        echo $OUTPUT->footer();
    } catch (Exception $e) {
        // Fallback error handling if template rendering fails.
        echo $OUTPUT->header();
        echo $OUTPUT->notification(
            'Error rendering bulk update page: ' . $e->getMessage(),
            notification::NOTIFY_ERROR
        );
        echo '<div class="alert alert-danger">';
        echo '<h4>Debug Information:</h4>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
        echo '<div class="mt-3">';
        echo '<a href="' . $CFG->wwwroot . '/admin/settings.php?section=blocksettingblc_modules" class="btn btn-secondary">';
        echo get_string('return', 'block_blc_modules') . '</a>';
        echo '</div>';
        echo $OUTPUT->footer();

        // Log the error for administrators.
        $logger->error('Bulk update page rendering error: ' . $e->getMessage());
    }
    exit;
} else {
    // Show confirmation modal when first accessing the page.
    echo $OUTPUT->header();

    // Use Moodle's native Bootstrap (version depends on theme) instead of loading old Bootstrap 3.0.3 from CDN.
    // Modal structure compatible with both Bootstrap 4 and 5 used in Moodle 4.x themes.
    echo '
    <div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header">
                    <h4 class="modal-title" id="myModalLabel">' . get_string('updatescormconfirm', 'block_blc_modules') . '</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <!-- Modal Body -->
                <div class="modal-body">
                    <p class="statusMsg">' . get_string('updateconfirmmessage', 'block_blc_modules') . '</p>
                    <form role="form" action="' . $CFG->wwwroot . '/blocks/blc_modules/bulk_update.php"
                    id="bulkupdatesubmit" method="post">
                        <input type="hidden" name="action" value="continue"/>
                        <input type="hidden" name="sesskey" value="' . sesskey() . '"/>
                    </form>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="bulkupdatecont">Continue</button>
                </div>
            </div>
        </div>
    </div>';

    echo $OUTPUT->footer();
}
