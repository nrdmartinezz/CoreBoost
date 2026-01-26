<?php
/**
 * Mock Context_Helper class for testing
 * 
 * This provides test control methods for the Context_Helper class.
 * Must be loaded BEFORE the autoloader to prevent real class loading.
 * 
 * @package CoreBoost
 * @subpackage Tests
 */

namespace CoreBoost\Core;

/**
 * Mock Context_Helper class for testing purposes.
 */
class Context_Helper {
    
    /** @var bool Elementor preview state */
    private static $is_elementor_preview = false;
    
    /** @var bool|null Should skip optimization state */
    private static $should_skip = null;
    
    /** @var bool|null Debug mode state */
    private static $debug_mode = false;
    
    /** @var array Debug log calls storage */
    private static $debug_log_calls = [];
    
    /**
     * Check if in Elementor preview mode.
     *
     * @return bool
     */
    public static function is_elementor_preview() {
        return self::$is_elementor_preview;
    }
    
    /**
     * Set Elementor preview state for testing.
     *
     * @param bool $value The value to set.
     */
    public static function set_elementor_preview($value) {
        self::$is_elementor_preview = (bool) $value;
        // Note: Don't reset should_skip here to allow independent state control in tests
    }
    
    /**
     * Check if optimization should be skipped.
     *
     * @return bool
     */
    public static function should_skip_optimization() {
        if (self::$should_skip !== null) {
            return self::$should_skip;
        }
        
        // Check Elementor preview
        if (self::is_elementor_preview()) {
            self::$should_skip = true;
            return true;
        }
        
        self::$should_skip = false;
        return false;
    }
    
    /**
     * Set should skip state for testing.
     *
     * @param bool $value The value to set.
     */
    public static function set_should_skip($value) {
        self::$should_skip = $value;
    }
    
    /**
     * Check if debug mode is enabled.
     *
     * @return bool
     */
    public static function is_debug_mode() {
        return self::$debug_mode;
    }
    
    /**
     * Set debug mode state for testing.
     *
     * @param bool $value The value to set.
     */
    public static function set_debug_mode($value) {
        self::$debug_mode = $value;
    }
    
    /**
     * Log debug messages (only when debug mode is enabled).
     *
     * @param mixed  $message The message to log.
     * @param string $prefix  Optional prefix.
     */
    public static function debug_log($message, $prefix = 'CoreBoost') {
        // Only store calls if debug mode is on
        if (self::is_debug_mode()) {
            self::$debug_log_calls[] = $message;
        }
    }
    
    /**
     * Get all recorded debug log calls.
     *
     * @return array
     */
    public static function get_debug_log_calls() {
        return self::$debug_log_calls;
    }
    
    /**
     * Clear all recorded debug log calls.
     */
    public static function clear_debug_log_calls() {
        self::$debug_log_calls = [];
    }
    
    /**
     * Reset all cached states for testing.
     */
    public static function reset_cache() {
        self::$is_elementor_preview = false;
        self::$should_skip = null;
        self::$debug_mode = false;
        self::$debug_log_calls = [];
    }
}
