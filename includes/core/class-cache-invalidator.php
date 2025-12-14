<?php
/**
 * Cache Invalidation Manager
 *
 * Automatically invalidates variant cache when:
 * - Image optimization settings change
 * - Images are edited/deleted
 * - Bulk operations are performed
 * - Theme/plugin updates occur
 *
 * @package CoreBoost
 * @since 3.1.0
 */

namespace CoreBoost\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Cache_Invalidator
 *
 * Handles intelligent cache invalidation to maintain consistency
 */
class Cache_Invalidator {
    
    /**
     * Settings that trigger cache invalidation when changed
     *
     * @var array
     */
    private static $watched_settings = array(
        'avif_quality',
        'webp_quality',
        'enable_image_format_conversion',
        'enable_responsive_image_resizing',
        'image_generation_mode',
    );
    
    /**
     * Initialize invalidation hooks
     */
    public static function init() {
        // Only register hooks if WordPress functions are available
        if (!function_exists('add_action')) {
            return;
        }
        
        // Watch for settings changes
        \add_action('update_option_coreboost_options', array(__CLASS__, 'handle_settings_change'), 10, 2);
        
        // Watch for image edits
        \add_action('wp_generate_attachment_metadata', array(__CLASS__, 'handle_image_edit'), 10, 2);
        
        // Watch for image deletions
        \add_action('delete_attachment', array(__CLASS__, 'handle_image_delete'), 10, 1);
        
        // Watch for theme/plugin changes
        \add_action('switch_theme', array(__CLASS__, 'handle_theme_switch'));
        \add_action('activated_plugin', array(__CLASS__, 'handle_plugin_change'));
        \add_action('deactivated_plugin', array(__CLASS__, 'handle_plugin_change'));
    }
    
    /**
     * Handle settings changes
     *
     * Invalidates cache only if image-related settings changed.
     *
     * @param array $old_value Old settings
     * @param array $new_value New settings
     */
    public static function handle_settings_change($old_value, $new_value) {
        // Check if any watched settings changed
        $cache_invalidation_needed = false;
        
        foreach (self::$watched_settings as $setting) {
            $old = isset($old_value[$setting]) ? $old_value[$setting] : null;
            $new = isset($new_value[$setting]) ? $new_value[$setting] : null;
            
            if ($old !== $new) {
                $cache_invalidation_needed = true;
                error_log("CoreBoost: Cache invalidation triggered by setting change: {$setting}");
                break;
            }
        }
        
        if ($cache_invalidation_needed) {
            self::invalidate_all();
        }
    }
    
    /**
     * Handle image edit
     *
     * Invalidates cache for specific image when it's edited.
     *
     * @param array $metadata Attachment metadata
     * @param int $attachment_id Attachment ID
     */
    public static function handle_image_edit($metadata, $attachment_id) {
        $image_url = \wp_get_attachment_url($attachment_id);
        
        if ($image_url) {
            Variant_Cache::delete_variants($image_url);
            error_log("CoreBoost: Cache invalidated for edited image: {$image_url}");
        }
    }
    
    /**
     * Handle image deletion
     *
     * Removes variants and cache entries for deleted image.
     *
     * @param int $attachment_id Attachment ID
     */
    public static function handle_image_delete($attachment_id) {
        $image_url = \wp_get_attachment_url($attachment_id);
        
        if ($image_url) {
            Variant_Cache::delete_variants($image_url);
            error_log("CoreBoost: Cache invalidated for deleted image: {$image_url}");
        }
    }
    
    /**
     * Handle theme switch
     *
     * Invalidates entire cache as theme changes may affect image rendering.
     */
    public static function handle_theme_switch() {
        self::invalidate_all();
        error_log("CoreBoost: Cache invalidated due to theme switch");
    }
    
    /**
     * Handle plugin activation/deactivation
     *
     * Invalidates cache as plugins may affect image processing.
     */
    public static function handle_plugin_change() {
        self::invalidate_all();
        error_log("CoreBoost: Cache invalidated due to plugin change");
    }
    
    /**
     * Invalidate entire cache
     *
     * Clears all cache layers for all images.
     */
    public static function invalidate_all() {
        $deleted = Variant_Cache::clear_all();
        error_log("CoreBoost: Full cache invalidation completed ({$deleted} chunks deleted)");
        
        // Set flag to indicate cache was cleared
        \set_transient('coreboost_cache_cleared', time(), \HOUR_IN_SECONDS);
    }
    
    /**
     * Invalidate cache for specific image
     *
     * @param string $image_url Image URL
     */
    public static function invalidate_image($image_url) {
        Variant_Cache::delete_variants($image_url);
        error_log("CoreBoost: Cache invalidated for image: {$image_url}");
    }
    
    /**
     * Get last invalidation time
     *
     * @return int|null Unix timestamp or null if never invalidated
     */
    public static function get_last_invalidation_time() {
        return \get_transient('coreboost_cache_cleared');
    }
}
