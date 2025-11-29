<?php
/**
 * Fired during plugin activation
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
 * Class Activator
 */
class Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Add default options if they don't exist
        if (!get_option('coreboost_options')) {
            add_option('coreboost_options', self::get_default_options());
        }
        
        // Flush caches
        self::flush_caches();
    }
    
    /**
     * Get default plugin options
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
            'inline_style_ids' => ''
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
}
