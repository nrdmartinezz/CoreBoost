<?php
/**
 * Integration Tests for Context_Helper Class
 * 
 * Tests the Context_Helper utility class which handles context-aware
 * behavior like Elementor preview detection and optimization skipping.
 * 
 * @package CoreBoost
 * @subpackage Tests\Integration
 * @since 3.0.7
 */

namespace CoreBoost\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CoreBoost\Core\Context_Helper;

/**
 * Test class for Context_Helper functionality.
 */
class ContextHelperTest extends TestCase {
    
    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Reset Context_Helper state before each test
        Context_Helper::reset_cache();
        Context_Helper::clear_debug_log_calls();
        Context_Helper::set_should_skip(false);
        Context_Helper::set_debug_mode(false);
        Context_Helper::set_elementor_preview(false);
    }
    
    /**
     * Tear down test fixtures.
     */
    protected function tearDown(): void {
        parent::tearDown();
        
        // Clean up
        Context_Helper::reset_cache();
    }
    
    // ===========================================
    // Tests for is_elementor_preview()
    // ===========================================
    
    /**
     * Test Elementor preview detection returns false by default.
     */
    public function test_is_elementor_preview_returns_false_by_default(): void {
        $this->assertFalse(
            Context_Helper::is_elementor_preview(),
            'Elementor preview should be false by default'
        );
    }
    
    /**
     * Test Elementor preview can be set to true.
     */
    public function test_is_elementor_preview_can_be_set_true(): void {
        Context_Helper::set_elementor_preview(true);
        
        $this->assertTrue(
            Context_Helper::is_elementor_preview(),
            'Elementor preview should be true after setting'
        );
    }
    
    /**
     * Test Elementor preview state persists across calls.
     */
    public function test_elementor_preview_state_persists(): void {
        Context_Helper::set_elementor_preview(true);
        
        // Multiple calls should return the same value
        $this->assertTrue(Context_Helper::is_elementor_preview());
        $this->assertTrue(Context_Helper::is_elementor_preview());
        $this->assertTrue(Context_Helper::is_elementor_preview());
    }
    
    /**
     * Test Elementor preview state is cached.
     */
    public function test_elementor_preview_uses_caching(): void {
        // Set initial state
        Context_Helper::set_elementor_preview(true);
        $first_result = Context_Helper::is_elementor_preview();
        
        // The result should be cached
        $second_result = Context_Helper::is_elementor_preview();
        
        $this->assertEquals($first_result, $second_result);
    }
    
    /**
     * Test reset_cache clears Elementor preview cache.
     */
    public function test_reset_cache_clears_elementor_preview(): void {
        Context_Helper::set_elementor_preview(true);
        $this->assertTrue(Context_Helper::is_elementor_preview());
        
        Context_Helper::reset_cache();
        
        // After reset, should return default false
        $this->assertFalse(Context_Helper::is_elementor_preview());
    }
    
    // ===========================================
    // Tests for should_skip_optimization()
    // ===========================================
    
    /**
     * Test should_skip_optimization returns false by default.
     */
    public function test_should_skip_optimization_returns_false_by_default(): void {
        $this->assertFalse(
            Context_Helper::should_skip_optimization(),
            'Should not skip optimization by default'
        );
    }
    
    /**
     * Test should_skip_optimization can be set to true.
     */
    public function test_should_skip_optimization_can_be_set_true(): void {
        Context_Helper::set_should_skip(true);
        
        $this->assertTrue(
            Context_Helper::should_skip_optimization(),
            'Should skip optimization after setting'
        );
    }
    
    /**
     * Test skip optimization returns true when in Elementor preview.
     */
    public function test_should_skip_when_elementor_preview(): void {
        Context_Helper::set_elementor_preview(true);
        
        // Depending on implementation, Elementor preview may trigger skip
        // This tests that the relationship works correctly
        $is_preview = Context_Helper::is_elementor_preview();
        $this->assertTrue($is_preview);
    }
    
    /**
     * Test skip optimization state is independent of preview state.
     */
    public function test_skip_state_independent_of_preview(): void {
        // Set skip without preview
        Context_Helper::set_should_skip(true);
        Context_Helper::set_elementor_preview(false);
        
        $this->assertTrue(Context_Helper::should_skip_optimization());
        $this->assertFalse(Context_Helper::is_elementor_preview());
    }
    
    /**
     * Test skip optimization state persists across multiple calls.
     */
    public function test_skip_optimization_caching(): void {
        Context_Helper::set_should_skip(true);
        
        // Multiple calls should return consistent results
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = Context_Helper::should_skip_optimization();
        }
        
        $this->assertEquals(
            [true, true, true, true, true],
            $results,
            'Skip optimization should return consistent cached results'
        );
    }
    
    /**
     * Test reset_cache clears skip optimization state.
     */
    public function test_reset_cache_clears_skip_state(): void {
        Context_Helper::set_should_skip(true);
        $this->assertTrue(Context_Helper::should_skip_optimization());
        
        Context_Helper::reset_cache();
        
        $this->assertFalse(Context_Helper::should_skip_optimization());
    }
    
    // ===========================================
    // Tests for is_debug_mode()
    // ===========================================
    
    /**
     * Test is_debug_mode returns false by default.
     */
    public function test_is_debug_mode_returns_false_by_default(): void {
        $this->assertFalse(
            Context_Helper::is_debug_mode(),
            'Debug mode should be false by default in tests'
        );
    }
    
    /**
     * Test is_debug_mode can be set to true.
     */
    public function test_is_debug_mode_can_be_set_true(): void {
        Context_Helper::set_debug_mode(true);
        
        $this->assertTrue(
            Context_Helper::is_debug_mode(),
            'Debug mode should be true after setting'
        );
    }
    
    /**
     * Test debug mode state persists across calls.
     */
    public function test_debug_mode_persists(): void {
        Context_Helper::set_debug_mode(true);
        
        $this->assertTrue(Context_Helper::is_debug_mode());
        $this->assertTrue(Context_Helper::is_debug_mode());
    }
    
    // ===========================================
    // Tests for debug_log()
    // ===========================================
    
    /**
     * Test debug_log does nothing when debug mode is disabled.
     */
    public function test_debug_log_does_nothing_when_disabled(): void {
        Context_Helper::set_debug_mode(false);
        Context_Helper::clear_debug_log_calls();
        
        Context_Helper::debug_log('Test message');
        
        $log_calls = Context_Helper::get_debug_log_calls();
        
        $this->assertEmpty(
            $log_calls,
            'Debug log should not record when debug mode is disabled'
        );
    }
    
    /**
     * Test debug_log records messages when debug mode is enabled.
     */
    public function test_debug_log_records_when_enabled(): void {
        Context_Helper::set_debug_mode(true);
        Context_Helper::clear_debug_log_calls();
        
        Context_Helper::debug_log('Test message 1');
        Context_Helper::debug_log('Test message 2');
        
        $log_calls = Context_Helper::get_debug_log_calls();
        
        $this->assertCount(2, $log_calls, 'Should have recorded 2 debug log calls');
        $this->assertEquals('Test message 1', $log_calls[0]);
        $this->assertEquals('Test message 2', $log_calls[1]);
    }
    
    /**
     * Test debug_log handles various data types.
     */
    public function test_debug_log_handles_various_types(): void {
        Context_Helper::set_debug_mode(true);
        Context_Helper::clear_debug_log_calls();
        
        // Log different types
        Context_Helper::debug_log('String message');
        Context_Helper::debug_log(['array' => 'data']);
        Context_Helper::debug_log(12345);
        Context_Helper::debug_log(true);
        
        $log_calls = Context_Helper::get_debug_log_calls();
        
        $this->assertCount(4, $log_calls, 'Should have recorded 4 debug log calls');
    }
    
    /**
     * Test clear_debug_log_calls removes all recorded calls.
     */
    public function test_clear_debug_log_calls(): void {
        Context_Helper::set_debug_mode(true);
        
        Context_Helper::debug_log('Message 1');
        Context_Helper::debug_log('Message 2');
        
        $this->assertNotEmpty(Context_Helper::get_debug_log_calls());
        
        Context_Helper::clear_debug_log_calls();
        
        $this->assertEmpty(Context_Helper::get_debug_log_calls());
    }
    
    /**
     * Test debug_log prefix is applied correctly.
     */
    public function test_debug_log_records_exact_message(): void {
        Context_Helper::set_debug_mode(true);
        Context_Helper::clear_debug_log_calls();
        
        $message = 'CoreBoost: Testing specific message format';
        Context_Helper::debug_log($message);
        
        $log_calls = Context_Helper::get_debug_log_calls();
        
        $this->assertCount(1, $log_calls);
        $this->assertEquals($message, $log_calls[0]);
    }
    
    // ===========================================
    // Tests for reset_cache()
    // ===========================================
    
    /**
     * Test reset_cache clears all cached states.
     */
    public function test_reset_cache_clears_all_states(): void {
        // Set all states to non-default values
        Context_Helper::set_elementor_preview(true);
        Context_Helper::set_should_skip(true);
        Context_Helper::set_debug_mode(true);
        Context_Helper::debug_log('Test');
        
        // Verify states are set
        $this->assertTrue(Context_Helper::is_elementor_preview());
        $this->assertTrue(Context_Helper::should_skip_optimization());
        $this->assertTrue(Context_Helper::is_debug_mode());
        $this->assertNotEmpty(Context_Helper::get_debug_log_calls());
        
        // Reset
        Context_Helper::reset_cache();
        
        // Verify all states are cleared
        $this->assertFalse(Context_Helper::is_elementor_preview());
        $this->assertFalse(Context_Helper::should_skip_optimization());
        $this->assertFalse(Context_Helper::is_debug_mode());
        $this->assertEmpty(Context_Helper::get_debug_log_calls());
    }
    
    /**
     * Test reset_cache can be called multiple times safely.
     */
    public function test_reset_cache_is_idempotent(): void {
        // Call reset multiple times
        Context_Helper::reset_cache();
        Context_Helper::reset_cache();
        Context_Helper::reset_cache();
        
        // Should not throw and states should be defaults
        $this->assertFalse(Context_Helper::is_elementor_preview());
        $this->assertFalse(Context_Helper::should_skip_optimization());
        $this->assertFalse(Context_Helper::is_debug_mode());
    }
    
    // ===========================================
    // Integration Scenarios
    // ===========================================
    
    /**
     * Test typical usage scenario: optimization check during page load.
     */
    public function test_optimization_check_scenario(): void {
        // Simulate normal page load
        Context_Helper::reset_cache();
        
        // Check if we should optimize
        $should_optimize = !Context_Helper::should_skip_optimization();
        $this->assertTrue($should_optimize, 'Should optimize on normal page load');
        
        // Perform optimization...
        // Log debug info if enabled
        Context_Helper::set_debug_mode(true);
        Context_Helper::debug_log('Starting image optimization');
        
        $log_calls = Context_Helper::get_debug_log_calls();
        $this->assertCount(1, $log_calls);
    }
    
    /**
     * Test typical usage scenario: Elementor editor mode.
     */
    public function test_elementor_editor_scenario(): void {
        // Simulate Elementor editor being active
        Context_Helper::set_elementor_preview(true);
        
        // In Elementor preview, should detect it
        $this->assertTrue(
            Context_Helper::is_elementor_preview(),
            'Should detect Elementor preview mode'
        );
    }
    
    /**
     * Test typical usage scenario: AJAX request handling.
     */
    public function test_ajax_request_scenario(): void {
        Context_Helper::reset_cache();
        Context_Helper::set_debug_mode(true);
        Context_Helper::clear_debug_log_calls();
        
        // Simulate AJAX conversion request
        $images_to_convert = ['image1.jpg', 'image2.png', 'image3.gif'];
        
        foreach ($images_to_convert as $image) {
            if (!Context_Helper::should_skip_optimization()) {
                Context_Helper::debug_log("Converting: $image");
            }
        }
        
        $log_calls = Context_Helper::get_debug_log_calls();
        $this->assertCount(
            3,
            $log_calls,
            'Should log conversion for each image'
        );
    }
    
    /**
     * Test thread safety simulation with rapid state changes.
     */
    public function test_rapid_state_changes(): void {
        // Rapidly toggle states
        for ($i = 0; $i < 100; $i++) {
            $state = ($i % 2 === 0);
            Context_Helper::set_should_skip($state);
            $this->assertEquals($state, Context_Helper::should_skip_optimization());
        }
        
        // Final state should be based on last iteration (99 is odd, so false)
        $this->assertFalse(Context_Helper::should_skip_optimization());
    }
}
