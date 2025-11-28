<?php
/**
 * Cache management functionality
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
 * Class Cache_Manager
 */
class Cache_Manager {
    
    /**
     * Clear all hero image cache
     */
    public static function clear_hero_cache() {
        global $wpdb;
        
        // Clear object cache
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('coreboost_hero_cache');
        }
        
        // Clear transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_coreboost_hero_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_coreboost_hero_%'");
    }
    
    /**
     * Clear all background video detection cache
     */
    public static function clear_video_cache() {
        global $wpdb;
        
        // Clear object cache
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('coreboost_video_cache');
        }
        
        // Clear transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_coreboost_bg_videos_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_coreboost_bg_videos_%'");
    }
    
    /**
     * Clear third-party caching plugin caches
     */
    public static function clear_third_party_caches() {
        $cache_functions = array(
            'rocket_clean_domain',
            'w3tc_flush_all',
            'wp_cache_clear_cache'
        );
        
        foreach ($cache_functions as $func) {
            if (function_exists($func)) {
                $func();
            }
        }
        
        if (class_exists('LiteSpeed_Cache_API')) {
            \LiteSpeed_Cache_API::purge_all();
        }
        
        if (class_exists('autoptimizeCache')) {
            \autoptimizeCache::clearall();
        }
    }
    
    /**
     * Flush all caches
     */
    public static function flush_all_caches() {
        self::clear_hero_cache();
        self::clear_video_cache();
        
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        self::clear_third_party_caches();
    }
}
