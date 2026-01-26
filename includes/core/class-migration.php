<?php
/**
 * Database Migration Handler
 * 
 * Handles version-specific migrations when upgrading between versions
 *
 * @package CoreBoost
 * @since 3.0.0
 */

namespace CoreBoost\Core;

use CoreBoost\Core\Context_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Migration
 */
class Migration {
    
    /**
     * Run all necessary migrations from the installed version
     * 
     * @param string $from_version The version being upgraded from
     */
    public static function run($from_version) {
        Context_Helper::debug_log('Starting migration from version ' . $from_version, 'Migration');
        
        // Run version-specific migrations in order
        if (version_compare($from_version, '2.0.2', '<')) {
            self::migrate_to_2_0_2();
        }
        
        if (version_compare($from_version, '2.5.0', '<')) {
            self::migrate_to_2_5_0();
        }
        
        if (version_compare($from_version, '3.0.0', '<')) {
            self::migrate_to_3_0_0();
        }
        
        // Always merge defaults with existing options to add new settings
        self::merge_option_defaults();
        
        // Clear all caches after migration
        self::clear_all_caches();
        
        Context_Helper::debug_log('Completed successfully', 'Migration');
    }
    
    /**
     * Migrate to version 2.0.2
     * - Transition from GTM-specific settings to generic tag settings
     */
    private static function migrate_to_2_0_2() {
        Context_Helper::debug_log('Migrating to 2.0.2', 'Migration');
        
        $options = get_option('coreboost_options', array());
        
        // Check if old GTM settings exist
        if (isset($options['gtm_container_id']) && !empty($options['gtm_container_id'])) {
            $gtm_id = $options['gtm_container_id'];
            
            // Convert to new tag format if not already migrated
            if (!isset($options['tag_head_scripts']) || empty($options['tag_head_scripts'])) {
                $options['tag_head_scripts'] = sprintf(
                    "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','%s');</script>\n<!-- End Google Tag Manager -->",
                    esc_js($gtm_id)
                );
            }
            
            if (!isset($options['tag_body_scripts']) || empty($options['tag_body_scripts'])) {
                $options['tag_body_scripts'] = sprintf(
                    "<!-- Google Tag Manager (noscript) -->\n<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=%s\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n<!-- End Google Tag Manager (noscript) -->",
                    esc_attr($gtm_id)
                );
            }
            
            // Migrate load strategy
            if (isset($options['gtm_load_strategy'])) {
                $options['tag_load_strategy'] = $options['gtm_load_strategy'];
            }
            
            if (isset($options['gtm_custom_delay'])) {
                $options['tag_custom_delay'] = $options['gtm_custom_delay'];
            }
        }
        
        // Remove obsolete GTM-specific keys
        unset($options['gtm_enabled'], $options['gtm_container_id'], 
              $options['gtm_load_strategy'], $options['gtm_custom_delay']);
        
        update_option('coreboost_options', $options);
    }
    
    /**
     * Migrate to version 2.5.0
     * - Add analytics and A/B testing features
     */
    private static function migrate_to_2_5_0() {
        Context_Helper::debug_log('Migrating to 2.5.0', 'Migration');
        
        $options = get_option('coreboost_options', array());
        
        // Add analytics settings if they don't exist
        if (!isset($options['enable_analytics'])) {
            $options['enable_analytics'] = true;
            $options['analytics_retention_days'] = 30;
            $options['enable_ab_testing'] = false;
            $options['enable_recommendations'] = true;
        }
        
        update_option('coreboost_options', $options);
        
        // Initialize analytics storage
        if (!get_option('coreboost_script_metrics')) {
            add_option('coreboost_script_metrics', array());
        }
        
        if (!get_option('coreboost_pattern_effectiveness')) {
            add_option('coreboost_pattern_effectiveness', array());
        }
        
        if (!get_option('coreboost_ab_tests')) {
            add_option('coreboost_ab_tests', array());
        }
    }
    
    /**
     * Migrate to version 3.0.0
     * - Add responsive image resizing
     * - Add smart video facades
     * - Update hero detection cache format
     */
    private static function migrate_to_3_0_0() {
        Context_Helper::debug_log('Migrating to 3.0.0', 'Migration');
        
        $options = get_option('coreboost_options', array());
        
        // Add new Phase 2 image optimization settings
        if (!isset($options['enable_responsive_image_resizing'])) {
            $options['enable_responsive_image_resizing'] = false;
        }
        
        if (!isset($options['smart_video_facades'])) {
            $options['smart_video_facades'] = false;
        }
        
        // Ensure GTM custom delay exists (may have been lost in previous migrations)
        if (!isset($options['gtm_custom_delay'])) {
            $options['gtm_custom_delay'] = 3000;
        }
        
        if (!isset($options['tag_custom_delay'])) {
            $options['tag_custom_delay'] = 3000;
        }
        
        update_option('coreboost_options', $options);
        
        // Clear hero cache since detection logic changed in v3.0.0
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_coreboost_hero_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_coreboost_hero_%'");
    }
    
    /**
     * Merge default options with existing options
     * Adds any new settings while preserving existing values
     */
    private static function merge_option_defaults() {
        Context_Helper::debug_log('Merging default options', 'Migration');
        
        $options = get_option('coreboost_options', array());
        $defaults = self::get_default_options();
        
        // Add missing keys from defaults, preserve existing values
        $merged = array_replace($defaults, $options);
        
        // Remove obsolete keys that are no longer in defaults
        $merged = array_intersect_key($merged, $defaults);
        
        update_option('coreboost_options', $merged);
    }
    
    /**
     * Clear all plugin caches after migration
     */
    private static function clear_all_caches() {
        Context_Helper::debug_log('Clearing all caches', 'Migration');
        
        global $wpdb;
        
        // Clear all CoreBoost transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '%_transient_coreboost_%' 
            OR option_name LIKE '%_transient_timeout_coreboost_%'"
        );
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear opcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
    
    /**
     * Get default plugin options
     * This should match the defaults in Activator class
     * 
     * @return array Default options
     */
    private static function get_default_options() {
        return array(
            'preload_method' => 'elementor_data',
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
            'enable_image_optimization' => false,
            'enable_lazy_loading' => false,
            'add_width_height_attributes' => false,
            'generate_aspect_ratio_css' => false,
            'add_decoding_async' => false,
            'smart_youtube_blocking' => false,
            'enable_image_format_conversion' => false,
            'avif_quality' => 85,
            'webp_quality' => 85,
            'cleanup_orphans_weekly' => true,
            'enable_responsive_image_resizing' => false,
            'smart_video_facades' => false,
            'enable_analytics' => true,
            'analytics_retention_days' => 30,
            'enable_ab_testing' => false,
            'enable_recommendations' => true,
            'tag_head_scripts' => '',
            'tag_body_scripts' => '',
            'tag_footer_scripts' => '',
            'tag_load_strategy' => 'balanced',
            'tag_custom_delay' => 3000,
            'gtm_custom_delay' => 3000,
        );
    }
}
