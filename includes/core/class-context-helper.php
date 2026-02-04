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

// Skip if class already defined (e.g., mock loaded during testing)
if (class_exists('CoreBoost\Core\Context_Helper', false)) {
    return;
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
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (filter_input(INPUT_GET, 'elementor-preview', FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
            self::$is_elementor_preview = true;
            return true;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        
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
        
        // Skip on WordPress core functionality pages (admin, login, cron, etc.)
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (preg_match('/wp-(admin|login|cron|signup|activate|trackback|comments-post)|xmlrpc\.php/', $request_uri)) {
            self::$should_skip = true;
            return true;
        }
        
        // Skip during Elementor editor mode (catches iframe context)
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) === 'elementor') {
            self::$should_skip = true;
            return true;
        }
        if (filter_input(INPUT_GET, 'elementor-preview', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null) {
            self::$should_skip = true;
            return true;
        }
        // Skip Elementor editor iframe
        if (filter_input(INPUT_GET, 'elementor_library', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null) {
            self::$should_skip = true;
            return true;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        
        // Skip if Elementor is in edit mode (via JavaScript globals set before PHP)
        if (defined('ELEMENTOR_VERSION') && class_exists('\Elementor\Plugin')) {
            $elementor = \Elementor\Plugin::instance();
            // Check editor mode
            if (isset($elementor->editor) && method_exists($elementor->editor, 'is_edit_mode') && $elementor->editor->is_edit_mode()) {
                self::$should_skip = true;
                return true;
            }
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
    
    /**
     * Check if debug mode is enabled
     *
     * Returns true if WP_DEBUG is enabled. Use this instead of
     * checking WP_DEBUG directly to allow for future customization.
     *
     * @return bool True if debug mode is enabled
     */
    public static function is_debug_mode() {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * Log a debug message if debug mode is enabled
     *
     * @param string $message The message to log
     * @param string $prefix Optional prefix (defaults to 'CoreBoost')
     * @return void
     */
    public static function debug_log($message, $prefix = 'CoreBoost') {
        if (self::is_debug_mode()) {
            error_log("{$prefix}: {$message}");
        }
    }
}
