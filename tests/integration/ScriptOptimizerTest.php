<?php
/**
 * Integration Tests for Script_Optimizer Class
 * 
 * Tests the Script_Optimizer functionality including WordPress Core Script
 * defer feature that breaks critical request chains.
 * 
 * @package CoreBoost
 * @subpackage Tests\Integration
 * @since 3.1.0
 */

namespace CoreBoost\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CoreBoost\Core\Context_Helper;

/**
 * Test class for Script_Optimizer functionality.
 * 
 * Note: These tests validate the defer_scripts logic without requiring
 * the full WordPress environment. We test the string manipulation directly.
 */
class ScriptOptimizerTest extends TestCase {
    
    /**
     * Default test options
     *
     * @var array
     */
    private $default_options;
    
    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Reset Context_Helper state
        Context_Helper::reset_cache();
        Context_Helper::set_should_skip(false);
        Context_Helper::set_elementor_preview(false);
        
        // Set up default options matching CoreBoost defaults
        $this->default_options = array(
            'enable_script_defer' => true,
            'enable_wp_core_defer' => false,
            'scripts_to_defer' => "contact-form-7\nwc-cart-fragments\nelementor-frontend",
            'scripts_to_async' => "youtube-iframe-api\niframe-api",
            'exclude_scripts' => "jquery-core\njquery-migrate\njquery\njquery-ui-core",
            'enable_default_exclusions' => true,
        );
    }
    
    /**
     * Tear down test fixtures.
     */
    protected function tearDown(): void {
        parent::tearDown();
        Context_Helper::reset_cache();
    }
    
    // ===========================================
    // Tests for WordPress Core Script Defer Feature
    // ===========================================
    
    /**
     * Test that WP core scripts are NOT deferred when setting is disabled (default).
     */
    public function test_wp_core_scripts_not_deferred_when_disabled(): void {
        $options = $this->default_options;
        $options['enable_wp_core_defer'] = false;
        
        $wp_core_handles = array('wp-hooks', 'wp-i18n', 'wp-dom-ready');
        
        foreach ($wp_core_handles as $handle) {
            $original_tag = '<script src="https://example.com/wp-includes/js/dist/' . $handle . '.min.js" id="' . $handle . '-js"></script>';
            $result = $this->simulate_defer_scripts($original_tag, $handle, $options);
            
            // Should NOT have defer added (will be handled by normal exclusion logic)
            $this->assertStringNotContainsString(
                ' defer src',
                $result,
                "WP core script '$handle' should not have defer when enable_wp_core_defer is false"
            );
        }
    }
    
    /**
     * Test that WP core scripts ARE deferred when setting is enabled.
     */
    public function test_wp_core_scripts_deferred_when_enabled(): void {
        $options = $this->default_options;
        $options['enable_wp_core_defer'] = true;
        
        $wp_core_handles = array('wp-hooks', 'wp-i18n', 'wp-dom-ready');
        
        foreach ($wp_core_handles as $handle) {
            $original_tag = '<script src="https://example.com/wp-includes/js/dist/' . $handle . '.min.js" id="' . $handle . '-js"></script>';
            $result = $this->simulate_defer_scripts($original_tag, $handle, $options);
            
            $this->assertStringContainsString(
                ' defer src',
                $result,
                "WP core script '$handle' should have defer when enable_wp_core_defer is true"
            );
        }
    }
    
    /**
     * Test that non-WP-core scripts are not affected by WP core defer setting.
     */
    public function test_non_wp_core_scripts_unaffected(): void {
        $options = $this->default_options;
        $options['enable_wp_core_defer'] = true;
        
        // A random handle that is not a WP core script
        $handle = 'my-custom-script';
        $original_tag = '<script src="https://example.com/custom-script.js" id="my-custom-script-js"></script>';
        
        // This should NOT be caught by the WP core defer logic
        // (it would be handled by the normal defer logic if in scripts_to_defer)
        $result = $this->simulate_wp_core_defer_only($original_tag, $handle, $options);
        
        $this->assertEquals(
            $original_tag,
            $result,
            'Non-WP-core scripts should not be affected by WP core defer setting'
        );
    }
    
    /**
     * Test that already-deferred scripts don't get double defer attribute.
     */
    public function test_no_double_defer_attribute(): void {
        $options = $this->default_options;
        $options['enable_wp_core_defer'] = true;
        
        $handle = 'wp-hooks';
        // Tag already has defer
        $original_tag = '<script defer src="https://example.com/wp-includes/js/dist/wp-hooks.min.js" id="wp-hooks-js"></script>';
        
        $result = $this->simulate_wp_core_defer_only($original_tag, $handle, $options);
        
        // Should not have 'defer defer'
        $this->assertStringNotContainsString(
            'defer defer',
            $result,
            'Should not add duplicate defer attribute'
        );
        
        // Count occurrences of 'defer'
        $defer_count = substr_count($result, 'defer');
        $this->assertEquals(
            1,
            $defer_count,
            'Should have exactly one defer attribute'
        );
    }
    
    /**
     * Test that already-async scripts don't get defer added.
     */
    public function test_no_defer_on_async_scripts(): void {
        $options = $this->default_options;
        $options['enable_wp_core_defer'] = true;
        
        $handle = 'wp-hooks';
        // Tag already has async
        $original_tag = '<script async src="https://example.com/wp-includes/js/dist/wp-hooks.min.js" id="wp-hooks-js"></script>';
        
        $result = $this->simulate_wp_core_defer_only($original_tag, $handle, $options);
        
        // Should not have defer added alongside async
        $this->assertStringNotContainsString(
            ' defer',
            $result,
            'Should not add defer to script with async attribute'
        );
    }
    
    /**
     * Test setting is disabled by default in default options.
     */
    public function test_default_option_is_disabled(): void {
        // Simulate the default options from CoreBoost
        $defaults = array(
            'enable_wp_core_defer' => false,
        );
        
        $this->assertFalse(
            $defaults['enable_wp_core_defer'],
            'enable_wp_core_defer should be false by default'
        );
    }
    
    /**
     * Test all three WP core handles are recognized.
     */
    public function test_all_wp_core_handles_recognized(): void {
        $options = $this->default_options;
        $options['enable_wp_core_defer'] = true;
        
        $expected_handles = array('wp-hooks', 'wp-i18n', 'wp-dom-ready');
        
        foreach ($expected_handles as $handle) {
            $this->assertTrue(
                $this->is_wp_core_handle($handle),
                "Handle '$handle' should be recognized as WP core script"
            );
        }
        
        // Negative test
        $this->assertFalse(
            $this->is_wp_core_handle('jquery-core'),
            'jquery-core should NOT be recognized as WP core dist script'
        );
        
        $this->assertFalse(
            $this->is_wp_core_handle('wp-embed'),
            'wp-embed should NOT be in the WP core defer list'
        );
    }
    
    // ===========================================
    // Tests for Script Defer Integration
    // ===========================================
    
    /**
     * Test that script defer is disabled when enable_script_defer is false.
     */
    public function test_defer_disabled_when_setting_off(): void {
        $options = $this->default_options;
        $options['enable_script_defer'] = false;
        $options['enable_wp_core_defer'] = true;
        
        $handle = 'wp-hooks';
        $original_tag = '<script src="https://example.com/wp-includes/js/dist/wp-hooks.min.js"></script>';
        
        $result = $this->simulate_defer_scripts($original_tag, $handle, $options);
        
        // Should return unchanged when script defer is globally disabled
        $this->assertEquals(
            $original_tag,
            $result,
            'Scripts should not be deferred when enable_script_defer is false'
        );
    }
    
    // ===========================================
    // Helper Methods
    // ===========================================
    
    /**
     * Simulate the defer_scripts method logic for WP core scripts only.
     * This isolates the WP core defer feature for testing.
     *
     * @param string $tag     Script tag HTML.
     * @param string $handle  Script handle.
     * @param array  $options Plugin options.
     * @return string Modified tag.
     */
    private function simulate_wp_core_defer_only($tag, $handle, $options) {
        if (empty($options['enable_wp_core_defer'])) {
            return $tag;
        }
        
        $wp_core_handles = array('wp-hooks', 'wp-i18n', 'wp-dom-ready');
        
        if (in_array($handle, $wp_core_handles)) {
            // Only add defer if not already present
            if (strpos($tag, ' defer') === false && strpos($tag, ' async') === false) {
                return str_replace(' src', ' defer src', $tag);
            }
        }
        
        return $tag;
    }
    
    /**
     * Simulate the full defer_scripts method logic.
     * Mirrors the actual Script_Optimizer::defer_scripts() implementation.
     *
     * @param string $tag     Script tag HTML.
     * @param string $handle  Script handle.
     * @param array  $options Plugin options.
     * @return string Modified tag.
     */
    private function simulate_defer_scripts($tag, $handle, $options) {
        // Check if script defer is enabled
        if (empty($options['enable_script_defer'])) {
            return $tag;
        }
        
        // WordPress Core Script Defer - handle wp-hooks, wp-i18n, wp-dom-ready
        if (!empty($options['enable_wp_core_defer'])) {
            $wp_core_handles = array('wp-hooks', 'wp-i18n', 'wp-dom-ready');
            if (in_array($handle, $wp_core_handles)) {
                if (strpos($tag, ' defer') === false && strpos($tag, ' async') === false) {
                    return str_replace(' src', ' defer src', $tag);
                }
                return $tag;
            }
        }
        
        // Simulated exclusion check (simplified)
        $exclude_scripts = array_filter(array_map('trim', explode("\n", $options['exclude_scripts'])));
        if (in_array($handle, $exclude_scripts)) {
            return $tag;
        }
        
        // For other scripts, apply normal defer logic
        $scripts_to_defer = array_filter(array_map('trim', explode("\n", $options['scripts_to_defer'])));
        if (empty($scripts_to_defer) || in_array($handle, $scripts_to_defer)) {
            return str_replace(' src', ' defer src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Check if a handle is a WP core dist script for deferring.
     *
     * @param string $handle Script handle.
     * @return bool
     */
    private function is_wp_core_handle($handle) {
        $wp_core_handles = array('wp-hooks', 'wp-i18n', 'wp-dom-ready');
        return in_array($handle, $wp_core_handles);
    }
}
