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
}
