<?php
/**
 * Fired during plugin deactivation
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Deactivator
 */
class Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Unschedule cron jobs
        self::unschedule_cron_events();
        
        // Flush caches
        self::flush_caches();
    }
    
    /**
     * Unschedule WP-Cron events
     */
    private static function unschedule_cron_events() {
        // Unschedule weekly orphan cleanup
        $timestamp = wp_next_scheduled('coreboost_cleanup_orphaned_variants');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'coreboost_cleanup_orphaned_variants');
        }
    }
    
    /**
     * Flush all caches
     */
    private static function flush_caches() {
        // Clear CoreBoost hero cache
        global $wpdb;
        
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('coreboost_hero_cache');
        }
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_coreboost_hero_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_coreboost_hero_%'");
        
        // Flush WordPress cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
}
