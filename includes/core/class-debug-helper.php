<?php
/**
 * Debug helper functionality
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Debug_Helper
 */
class Debug_Helper {
    
    /**
     * Output debug comment
     *
     * @param string $message The debug message
     * @param bool $enabled Whether debug mode is enabled
     */
    public static function comment($message, $enabled = true) {
        // Don't output comments during AJAX requests or on admin pages to avoid corrupting responses
        if ($enabled && !wp_doing_ajax() && !is_admin()) {
            echo '<!-- CoreBoost: ' . esc_html($message) . ' -->' . "\n";
        }
    }
}
