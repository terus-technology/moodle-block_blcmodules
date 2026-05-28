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
 * Web service definitions for BLC modules block.
 *
 * @package    block_blc_modules
 * @copyright  2025 Terus Technology
 * @author     Ali <ali@teruselearning.co.uk>, Rama <rama@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'blocks_blc_modules_get_blc_modules_version' => [
        'classname' => 'block_blc_modules\external\blcservice',
        'methodname' => 'get_blc_modules_version',
        'returns' => 'block_blc_modules\external\blcservice::get_blc_modules_version_returns',
        'description' => 'Check plugin BLC modules version',
        'type'  => 'read',
        'ajax' => 'true',
    ],
    'blocks_blc_modules_get_blc_modules_scormurl' => [
        'classname' => 'block_blc_modules\external\blcservice',
        'methodname' => 'get_blc_modules_scormurl',
        'returns' => 'block_blc_modules\external\blcservice::get_blc_modules_scormurl_returns',
        'description' => 'Retrieve scorm urls under an apikey',
        'type'  => 'read',
        'ajax' => 'true',
    ],
    'blocks_blc_modules_get_blc_modules_scormsubject' => [
        'classname' => 'block_blc_modules\external\blcservice',
        'methodname' => 'get_blc_modules_scormsubject',
        'returns' => 'block_blc_modules\external\blcservice::get_blc_modules_scormsubject_returns',
        'description' => 'Return scorm subjects',
        'type'  => 'read',
        'ajax' => 'true',
    ],
    'blocks_blc_modules_get_blc_modules_scormdelete' => [
        'classname' => 'block_blc_modules\external\blcservice',
        'methodname' => 'get_blc_modules_scormdelete',
        'returns' => 'block_blc_modules\external\blcservice::get_blc_modules_scormdelete_returns',
        'description' => 'Delete scorm module',
        'type'  => 'write',
        'ajax' => 'true',
    ],
    'blocks_blc_modules_load_scorm_modules' => [
        'classname' => 'block_blc_modules\external\blcservice',
        'methodname' => 'load_scorm_modules',
        'returns' => 'block_blc_modules\external\blcservice::load_scorm_modules_returns',
        'description' => 'Load SCORM modules from external URLs into a course',
        'type' => 'write',
        'ajax' => 'true',
    ],
];

$services = [
    'BLC Modules Service' => [
        'functions' => [
            'blocks_blc_modules_get_blc_modules_version',
            'blocks_blc_modules_get_blc_modules_scormurl',
            'blocks_blc_modules_get_blc_modules_scormsubject',
            'blocks_blc_modules_get_blc_modules_scormdelete',
            'blocks_blc_modules_load_scorm_modules',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];
