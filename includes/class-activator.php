<?php
/**
 * Fired during plugin activation
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost;

use CoreBoost\Core\Context_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Activator
 */
class Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Check if this is an upgrade from a previous version
        $installed_version = get_option('coreboost_version', '0.0.0');
        
        if ($installed_version && version_compare($installed_version, COREBOOST_VERSION, '<')) {
            // This is an upgrade - run migrations
            Context_Helper::debug_log('Upgrading from version ' . $installed_version . ' to ' . COREBOOST_VERSION);
            
            // Backup current options before migration
            self::backup_options($installed_version);
            
            // Run version-specific migrations
            require_once COREBOOST_PLUGIN_DIR . 'includes/core/class-migration.php';
            Core\Migration::run($installed_version);
        }
        
        // Update version in database
        update_option('coreboost_version', COREBOOST_VERSION);
        
        // Add installed timestamp if first install
        if (!get_option('coreboost_installed_at')) {
            update_option('coreboost_installed_at', current_time('timestamp'));
        }
        
        // Add default options if they don't exist
        if (!get_option('coreboost_options')) {
            add_option('coreboost_options', self::get_default_options());
        }
        
        // Schedule WP-Cron events for Phase 2 (if needed)
        self::schedule_cron_events();
        
        // Flush caches
        self::flush_caches();
        
        Context_Helper::debug_log('Activation completed successfully for version ' . COREBOOST_VERSION);
    }
    
    /**
     * Schedule WP-Cron events
     */
    private static function schedule_cron_events() {
        // Schedule weekly orphan cleanup for image variants
        if (!wp_next_scheduled('coreboost_cleanup_orphaned_variants')) {
            wp_schedule_event(
                time(),
                'weekly',
                'coreboost_cleanup_orphaned_variants'
            );
        }
    }
    
    /**
     * Get default plugin options
     *
     * @return array Default options
     */
    private static function get_default_options() {
        return array(
            'preload_method' => 'automatic',
            'enable_script_defer' => false,
            'enable_css_defer' => false,
            'enable_foreground_conversion' => false,
            'enable_responsive_preload' => false,
            'enable_caching' => false,
            'lazy_load_exclude_count' => 2,
            'scripts_to_defer' => "contact-form-7\nwc-cart-fragments\nelementor-frontend",
            'scripts_to_async' => "youtube-iframe-api\niframe-api",
            'styles_to_defer' => "contact-form-7\nwoocommerce-layout\nelementor-frontend\ncustom-frontend\nswiper\nwidget-\nelementor-post-\ncustom-\nfadeIn\ne-swiper",
            'exclude_scripts' => "jquery-core\njquery-migrate\njquery\njquery-ui-core",
            'specific_pages' => '',
            'css_defer_method' => 'preload_with_critical',
            'critical_css_global' => '',
            'critical_css_home' => '',
            'critical_css_pages' => '',
            'critical_css_posts' => '',
            'enable_font_optimization' => false,
            'font_display_swap' => true,
            'defer_google_fonts' => true,
            'defer_adobe_fonts' => true,
            'preconnect_google_fonts' => true,
            'preconnect_adobe_fonts' => true,
            'enable_unused_css_removal' => false,
            'enable_unused_js_removal' => false,
            'block_youtube_player_css' => false,
            'block_youtube_embed_ui' => false,
            'unused_css_list' => '',
            'unused_js_list' => '',
            'enable_inline_script_removal' => false,
            'inline_script_ids' => '',
            'enable_inline_style_removal' => false,
            'inline_style_ids' => '',
            // Image Optimization Settings
            'enable_image_optimization' => false,
            'enable_lazy_loading' => false,
            'add_width_height_attributes' => false,
            'generate_aspect_ratio_css' => false,
            'add_decoding_async' => false,
            // YouTube blocking
            'smart_youtube_blocking' => false,
        );
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
    
    /**
     * Backup current options before migration
     * 
     * @param string $version Version being upgraded from
     */
    private static function backup_options($version) {
        $options = get_option('coreboost_options');
        if ($options) {
            $backup_key = 'coreboost_options_backup_' . str_replace('.', '_', $version);
            update_option($backup_key, $options, false); // autoload = false
            Context_Helper::debug_log('Options backed up to ' . $backup_key);
        }
    }
}
