<?php
/**
 * Context Helper Utility
 *
 * Centralized context detection for optimization decisions.
 * Eliminates duplicate Elementor preview detection across 50+ locations.
 *
 * @package CoreBoost
 * @since 2.8.1
 */

namespace CoreBoost\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Context_Helper
 *
 * Provides static utility methods for context detection
 */
class Context_Helper {
    
    /**
     * Cached Elementor preview detection result
     *
     * @var bool|null
     */
    private static $is_elementor_preview = null;
    
    /**
     * Cached admin/ajax detection result
     *
     * @var bool|null
     */
    private static $should_skip = null;
    
    /**
     * Check if current request is Elementor preview
     *
     * Caches result for performance (checked many times per request).
     *
     * @return bool True if Elementor preview/editor is active
     */
    public static function is_elementor_preview() {
        if (self::$is_elementor_preview !== null) {
            return self::$is_elementor_preview;
        }
        
        // Check constant set by main plugin file
        if (defined('COREBOOST_ELEMENTOR_PREVIEW') && COREBOOST_ELEMENTOR_PREVIEW) {
            self::$is_elementor_preview = true;
            return true;
        }
        
        // Check GET parameter (fallback)
        if (isset($_GET['elementor-preview']) && !empty($_GET['elementor-preview'])) {
            self::$is_elementor_preview = true;
            return true;
        }
        
        // Check Elementor's own constants/methods
        if (defined('ELEMENTOR_VERSION')) {
            // Check if Elementor plugin defines its own preview detection
            if (class_exists('\Elementor\Plugin')) {
                $elementor = \Elementor\Plugin::instance();
                if (isset($elementor->preview) && $elementor->preview->is_preview_mode()) {
                    self::$is_elementor_preview = true;
                    return true;
                }
            }
        }
        
        self::$is_elementor_preview = false;
        return false;
    }
    
    /**
     * Check if optimizations should be skipped
     *
     * Returns true for admin, AJAX, Elementor preview, or REST API requests.
     * Cached for performance.
     *
     * @return bool True if optimizations should be skipped
     */
    public static function should_skip_optimization() {
        if (self::$should_skip !== null) {
            return self::$should_skip;
        }
        
        // Skip in admin area
        if (is_admin()) {
            self::$should_skip = true;
            return true;
        }
        
        // Skip during AJAX requests
        if (wp_doing_ajax()) {
            self::$should_skip = true;
            return true;
        }
        
        // Skip during Elementor preview
        if (self::is_elementor_preview()) {
            self::$should_skip = true;
            return true;
        }
        
        // Skip during REST API requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            self::$should_skip = true;
            return true;
        }
        
        self::$should_skip = false;
        return false;
    }
    
    /**
     * Check if current page is front page
     *
     * @return bool True if front page
     */
    public static function is_front_page() {
        return is_front_page();
    }
    
    /**
     * Check if current page is a page (not post)
     *
     * @return bool True if page
     */
    public static function is_page() {
        return is_page();
    }
    
    /**
     * Get current post object safely
     *
     * @return \WP_Post|null Post object or null
     */
    public static function get_current_post() {
        global $post;
        return $post;
    }
    
    /**
     * Reset cached values (useful for testing)
     *
     * @return void
     */
    public static function reset_cache() {
        self::$is_elementor_preview = null;
        self::$should_skip = null;
    }
}
