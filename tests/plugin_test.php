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
 * BLC Modules block PHPUnit tests
 *
 * @package    block_blc_modules
 * @copyright  2024 BLC Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_blc_modules;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for BLC Modules block
 *
 * @package    block_blc_modules
 * @copyright  2024 BLC Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_test extends \advanced_testcase {

    /**
     * Test plugin installation and basic functionality
     */
    public function test_plugin_installation(): void {
        $this->resetAfterTest();
        
        // Check if plugin is properly installed
        $plugin = \core_plugin_manager::instance()->get_plugin_info('block_blc_modules');
        $this->assertNotNull($plugin);
        $this->assertEquals('block_blc_modules', $plugin->component);
    }

    /**
     * Test block instance creation
     */
    public function test_block_creation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course
        $course = $this->getDataGenerator()->create_course();
        
        // Create block instance
        $block = $this->getDataGenerator()->create_block('blc_modules', ['courseid' => $course->id]);
        $this->assertNotNull($block);
    }

    /**
     * Test database tables exist
     */
    public function test_database_tables(): void {
        global $DB;
        
        $this->resetAfterTest();
        
        // Check main table exists
        $this->assertTrue($DB->get_manager()->table_exists('block_blc_modules'));
        
        // Check doc table exists  
        $this->assertTrue($DB->get_manager()->table_exists('block_blc_modules_doc'));
    }

    /**
     * Test capabilities
     */
    public function test_capabilities(): void {
        $this->resetAfterTest();
        
        // Check capabilities are defined
        $capabilities = get_all_capabilities();
        $this->assertArrayHasKey('block/blc_modules:addinstance', $capabilities);
        $this->assertArrayHasKey('block/blc_modules:viewblock', $capabilities);
    }

    /**
     * Test external services are registered
     */
    public function test_external_services(): void {
        global $DB;
        
        $this->resetAfterTest();
        
        // Check if external functions are registered
        $functions = $DB->get_records('external_functions', ['component' => 'block_blc_modules']);
        $this->assertNotEmpty($functions);
    }
}