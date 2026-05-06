<?php
/**
 * Font optimization (Google Fonts and Adobe Fonts)
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Context_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Font_Optimizer
 */
class Font_Optimizer {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Loader instance
     *
     * @var \CoreBoost\Loader
     */
    private $loader;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        // Only register on frontend
        if (!is_admin()) {
            $this->define_hooks();
        }
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        $this->loader->add_filter('style_loader_tag', $this, 'optimize_font_loading', 10, 4);
        $this->loader->add_action('wp_head', $this, 'add_font_preconnects', 1);
        $this->loader->add_action('wp_head', $this, 'add_custom_preconnects', 1);
        $this->loader->add_action('wp_head', $this, 'output_local_font_preloads', 1);
        // Output buffer wrapping wp_head to inject font-display:swap into inline @font-face blocks
        // (covers Elementor custom fonts and theme fonts that never go through style_loader_tag)
        $this->loader->add_action('wp_head', $this, 'start_font_display_buffer', 0);
        $this->loader->add_action('wp_head', $this, 'end_font_display_buffer', 999);
    }
    
    /**
     * Optimize font loading
     */
    public function optimize_font_loading($html, $handle, $href, $media) {
        if (!$this->options['enable_font_optimization'] || is_admin()) {
            return $html;
        }
        
        $is_font = false;
        
        // Check if this is a Google Font
        if ($this->options['defer_google_fonts'] && 
            (strpos($href, 'fonts.googleapis.com') !== false || strpos($href, 'fonts.gstatic.com') !== false)) {
            $is_font = true;
        }
        
        // Check if this is an Adobe Font
        if ($this->options['defer_adobe_fonts'] && 
            (strpos($href, 'use.typekit.net') !== false || strpos($href, 'fonts.adobe.com') !== false)) {
            $is_font = true;
        }
        
        if (!$is_font) {
            return $html;
        }
        
        // Add display=swap to font URLs if not present
        if ($this->options['font_display_swap'] && strpos($href, 'display=') === false) {
            $href = add_query_arg('display', 'swap', $href);
        }
        
        // Convert to preload with onload handler for non-blocking load
        $preload_html = '<link rel="preload" href="' . esc_url($href) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" id="' . esc_attr($handle) . '-preload">';
        $noscript_html = '<noscript><link rel="stylesheet" href="' . esc_url($href) . '" id="' . esc_attr($handle) . '-noscript"></noscript>';
        
        return $preload_html . "\n" . $noscript_html;
    }
    
    /**
     * Add font preconnect links
     */
    public function add_font_preconnects() {
        // Don't output on admin or preview contexts
        if (Context_Helper::should_skip_optimization()) {
            return;
        }
        
        if (!$this->options['enable_font_optimization']) {
            return;
        }
        
        $preconnects = array();
        
        if ($this->options['defer_google_fonts']) {
            $preconnects[] = '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>';
            $preconnects[] = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        }
        
        if ($this->options['defer_adobe_fonts']) {
            $preconnects[] = '<link rel="preconnect" href="https://use.typekit.net" crossorigin>';
        }
        
        if (!empty($preconnects)) {
            echo implode("\n", $preconnects) . "\n";
        }
    }

    /**
     * Output preload tags for locally-hosted font files (including Elementor custom fonts).
     * Users list their .woff2 URLs in the Local Font Preloads setting, one per line.
     */
    public function output_local_font_preloads() {
        if (Context_Helper::should_skip_optimization()) {
            return;
        }

        if (!$this->options['enable_font_optimization'] || empty($this->options['local_font_preloads'])) {
            return;
        }

        $urls = array_filter(array_map('trim', explode("\n", $this->options['local_font_preloads'])));

        foreach ($urls as $url) {
            // Allow relative paths (e.g. /wp-content/uploads/...) as well as full URLs
            if (strpos($url, '/') === 0) {
                echo '<link rel="preload" href="' . esc_attr($url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
                continue;
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $parsed = wp_parse_url($url);
            if (empty($parsed['scheme']) || !in_array($parsed['scheme'], array('http', 'https'), true)) {
                continue;
            }

            echo '<link rel="preload" href="' . esc_url($url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        }
    }

    /**
     * Start output buffer at wp_head priority 0 to capture inline @font-face blocks.
     * Only active when font_display_swap is enabled.
     */
    public function start_font_display_buffer() {
        if (!$this->options['enable_font_optimization'] || !$this->options['font_display_swap']) {
            return;
        }

        if (Context_Helper::should_skip_optimization()) {
            return;
        }

        ob_start();
    }

    /**
     * End output buffer at wp_head priority 999 and inject font-display:swap into
     * any @font-face block that doesn't already declare it.
     * Covers Elementor custom fonts, theme fonts, and any other inline @font-face.
     */
    public function end_font_display_buffer() {
        if (!$this->options['enable_font_optimization'] || !$this->options['font_display_swap']) {
            return;
        }

        if (Context_Helper::should_skip_optimization()) {
            return;
        }

        if (!ob_get_level()) {
            return;
        }

        $html = ob_get_clean();

        // Inject font-display: swap into @font-face blocks that don't already have it
        $html = preg_replace_callback(
            '/@font-face\s*\{([^}]+)\}/s',
            function ($matches) {
                $block_content = $matches[1];
                if (strpos($block_content, 'font-display') !== false) {
                    return $matches[0];
                }
                return '@font-face {' . $block_content . 'font-display: swap;}';
            },
            $html
        );

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML already rendered by WordPress core
    }

    /**
     * Add custom preconnect links from user settings
     */
    public function add_custom_preconnects() {
        // Don't output on admin or preview contexts
        if (Context_Helper::should_skip_optimization()) {
            return;
        }

        // Get custom preconnect URLs
        $custom_urls = isset($this->options['custom_preconnect_urls']) 
            ? $this->options['custom_preconnect_urls'] 
            : '';

        if (empty($custom_urls)) {
            return;
        }

        // Parse URLs (one per line)
        $urls = array_filter(array_map('trim', explode("\n", $custom_urls)));
        
        if (empty($urls)) {
            return;
        }

        $preconnects = array();

        foreach ($urls as $url) {
            // Skip empty lines
            if (empty($url)) {
                continue;
            }

            // Validate URL - silently skip invalid URLs
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            // Parse URL to get just the origin (scheme + host)
            $parsed = wp_parse_url($url);
            
            // Must have scheme and host
            if (empty($parsed['scheme']) || empty($parsed['host'])) {
                continue;
            }

            // Only allow http/https
            if (!in_array($parsed['scheme'], array('http', 'https'), true)) {
                continue;
            }

            // Build origin URL (scheme + host + optional port)
            $origin = $parsed['scheme'] . '://' . $parsed['host'];
            if (!empty($parsed['port'])) {
                $origin .= ':' . $parsed['port'];
            }

            // Avoid duplicates
            $preconnect_tag = '<link rel="preconnect" href="' . esc_url($origin) . '" crossorigin>';
            if (!in_array($preconnect_tag, $preconnects, true)) {
                $preconnects[] = $preconnect_tag;
            }
        }

        if (!empty($preconnects)) {
            echo "<!-- CoreBoost Custom Preconnects -->\n";
            echo implode("\n", $preconnects) . "\n";
        }
    }
}
