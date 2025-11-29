<?php
/**
 * Debug helper functionality (DEPRECATED - Removed in CoreBoost 2.5.1)
 *
 * This file is kept for backward compatibility but is no longer used.
 * All debug comments have been removed from the plugin.
 *
 * @package CoreBoost
 * @since 1.2.0
 * @deprecated 2.5.1
 */

namespace CoreBoost\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Debug_Helper (DEPRECATED)
 * 
 * @deprecated 2.5.1 Debug output has been removed. Use error_log() or WordPress debugging instead.
 */
class Debug_Helper {
    
    /**
     * Output debug comment (DEPRECATED)
     *
     * @param string $message The debug message
     * @param bool $enabled Whether debug mode is enabled
     * @deprecated 2.5.1
     */
    public static function comment($message, $enabled = true) {
        // Deprecated - does nothing
    }
}
