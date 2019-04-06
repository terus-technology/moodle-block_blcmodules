<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Form for editing activity results block instances.
 *
 * @copyright 2009 Tim Hunt
 * @copyright 2015 Stephen Bourget
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_blc_modules_edit_form extends block_edit_form {
    /**
     * The definition of the fields to use.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $DB;

        // Load defaults.
        $blockconfig = get_config('block_blc_modules');

        // Fields for editing blc_modules block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

 

        $mform->addElement('text', 'api_key',
                get_string('api_key', 'block_blc_modules'));
        $mform->setDefault('api_key', $blockconfig->api_key);
        $mform->setType('api_key', PARAM_TEXT);
   

    }
}
