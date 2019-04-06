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
 * Defines the form for editing add scorm block instances.
 *
 * @package    block_blc_modules
 * @copyright  2016 Stephen Bourget
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    // Default Api key.
    $setting = new admin_setting_configtext('block_blc_modules/api_key',
        new lang_string('api_key', 'block_blc_modules'),
        new lang_string('api_key_desc', 'block_blc_modules'), '', PARAM_TEXT);
    $settings->add($setting);
    
    // Default Token.
    $setting = new admin_setting_configtext('block_blc_modules/token',
        new lang_string('token', 'block_blc_modules'),
        new lang_string('token_desc', 'block_blc_modules'), 'd623555b36cb7e3db03cd06178ccb284', PARAM_TEXT);
    $settings->add($setting);
    
    // Default domain name.
    $setting = new admin_setting_configtext('block_blc_modules/domainname',
        new lang_string('domainname', 'block_blc_modules'),
        new lang_string('domainname_desc', 'block_blc_modules'), 'https://blc.howcollege.ac.uk/', PARAM_TEXT);
    $settings->add($setting);



}
