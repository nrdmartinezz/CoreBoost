<?php
/**
 * Configuration and default options for CoreBoost
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
 * Class Config
 */
class Config {
    
    /**
     * Get field configuration for dynamic callback handling
     *
     * @return array Field configurations
     */
    public static function get_field_config() {
        return array(
            'enable_responsive_preload' => array('type' => 'checkbox', 'default' => true, 'description' => 'Preload different image sizes for mobile and tablet devices.'),
            'enable_foreground_conversion' => array('type' => 'checkbox', 'default' => false, 'description' => 'Add CSS to convert background images to foreground images for better performance.'),
            'enable_hero_preload_extraction' => array('type' => 'checkbox', 'default' => true, 'description' => 'Extract and preload hero images marked with data-coreboost-hero-image="true" attribute for improved LCP performance.'),
            'hero_preload_cache_ttl' => array('type' => 'select', 'default' => 2592000, 'description' => 'How long to cache preload detection results. Longer cache = better performance, but requires manual cache clear if images change.', 'options' => array(86400 => '1 Day', 604800 => '7 Days', 2592000 => '30 Days')),
            'enable_script_defer' => array('type' => 'checkbox', 'default' => true, 'description' => 'Enable automatic script deferring for better performance.'),
            'scripts_to_defer' => array('type' => 'textarea', 'rows' => 5, 'description' => 'Script handles to defer (one per line). Use defer for jQuery-dependent scripts. Leave empty to defer all non-excluded scripts.'),
            'scripts_to_async' => array('type' => 'textarea', 'rows' => 3, 'description' => 'Independent scripts to load with async (one per line). These scripts have no dependencies and can execute immediately. Examples: youtube-iframe-api, google-analytics, facebook-pixel.'),
            'exclude_scripts' => array('type' => 'textarea', 'rows' => 3, 'description' => 'Script handles to never defer or async (one per line). Keep jQuery here as it\'s required by most WordPress scripts.'),
            'enable_css_defer' => array('type' => 'checkbox', 'default' => false, 'description' => 'Enable CSS deferring with critical CSS inlining.'),
            'styles_to_defer' => array('type' => 'textarea', 'rows' => 3, 'description' => 'CSS handles to defer (one per line).'),
            'critical_css_global' => array('type' => 'textarea', 'rows' => 8, 'class' => 'large-text code', 'description' => 'Global critical CSS applied to all pages. Include only above-the-fold styles.'),
            'critical_css_home' => array('type' => 'textarea', 'rows' => 6, 'class' => 'large-text code', 'description' => 'Critical CSS specific to the homepage. This will be combined with global critical CSS.'),
            'critical_css_pages' => array('type' => 'textarea', 'rows' => 6, 'class' => 'large-text code', 'description' => 'Critical CSS for all pages (not posts). Combined with global critical CSS.'),
            'critical_css_posts' => array('type' => 'textarea', 'rows' => 6, 'class' => 'large-text code', 'description' => 'Critical CSS for all posts/blog pages. Combined with global critical CSS.'),
            'enable_caching' => array('type' => 'checkbox', 'default' => true, 'description' => 'Cache hero image detection results for better performance.'),
            'enable_unused_css_removal' => array('type' => 'checkbox', 'default' => false, 'description' => 'Dequeue and remove specified CSS files from your site.'),
            'unused_css_list' => array('type' => 'textarea', 'rows' => 3, 'description' => 'Enter CSS handles to remove (one per line). Find handles in page source or browser developer tools.'),
            'enable_unused_js_removal' => array('type' => 'checkbox', 'default' => false, 'description' => 'Dequeue and remove specified JavaScript files from your site.'),
            'unused_js_list' => array('type' => 'textarea', 'rows' => 3, 'description' => 'Enter JavaScript handles to remove (one per line). Find handles in page source or browser developer tools.'),
            'enable_inline_script_removal' => array('type' => 'checkbox', 'default' => false, 'description' => 'Remove inline scripts by ID attribute (for scripts added via wp_head/wp_footer, not wp_enqueue_script).'),
            'inline_script_ids' => array('type' => 'textarea', 'rows' => 3, 'description' => 'Enter script ID attributes to remove (one per line). Example: ga-client-property, fb-pixel-client'),
            'enable_inline_style_removal' => array('type' => 'checkbox', 'default' => false, 'description' => 'Remove inline style tags by ID attribute.'),
            'inline_style_ids' => array('type' => 'textarea', 'rows' => 3, 'description' => 'Enter style ID attributes to remove (one per line).'),
            'smart_youtube_blocking' => array('type' => 'checkbox', 'default' => false, 'description' => 'Defer Elementor YouTube background video loading to prevent render blocking. Videos load after page interactive, eliminating CSP violations and script blocking while keeping video backgrounds.'),
            'smart_video_facades' => array('type' => 'checkbox', 'default' => false, 'description' => 'Replace above-the-fold video widgets with click-to-play facades. Defers YouTube/Vimeo loading, reducing initial resource load by ~1MB per video. Videos play when clicked.'),
            'block_youtube_player_css' => array('type' => 'checkbox', 'default' => false, 'description' => 'Block YouTube player CSS files (useful for background videos that don\'t need player UI).'),
            'block_youtube_embed_ui' => array('type' => 'checkbox', 'default' => false, 'description' => 'Block YouTube embed UI scripts (useful for autoplay background videos).')
        );
    }
    
    /**
     * Get auto-detected CSS defer patterns
     *
     * @return array CSS defer patterns
     */
    public static function get_auto_defer_patterns() {
        return array(
            'widget-',                  // Widget CSS
            'elementor-post-',          // Elementor post CSS
            'custom-',                  // Custom CSS files
            'swiper',                   // Swiper CSS
            'e-swiper',                 // Elementor Swiper
            'fadeIn',                   // Animation CSS
            'elementor-frontend',       // Elementor frontend
            'woocommerce-layout',       // WooCommerce layout
            'woocommerce-smallscreen',  // WooCommerce responsive
            'elementor-global',         // Elementor global styles
            'elementor-kit-',           // Elementor kit styles
            'hello-elementor',          // Hello Elementor theme
            'elementor-icons',          // Elementor icons
            'wp-block-library-theme',   // WordPress block theme
            'parent-style',             // Parent theme main stylesheet
            'child-style',              // Child theme main stylesheet
        );
    }
    
    /**
     * Get hook priorities
     *
     * @return array Hook priorities
     */
    public static function get_hook_priorities() {
        return array(
            'preconnects' => 0,
            'resource_hints' => 0,
            'hero_images' => 1,
            'critical_css' => 2,
            'footer_scripts' => 10,
            'script_tag_filter' => 20,
            'style_tag_filter' => 20,
            'font_optimization' => 10,
            'admin_bar' => 100,
            'remove_unused' => PHP_INT_MAX,
            'output_buffer' => 1,
        );
    }
}
