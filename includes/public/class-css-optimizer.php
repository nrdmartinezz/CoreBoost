<?php
/**
 * CSS deferring and critical CSS optimization
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Config;
use CoreBoost\Core\Context_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CSS_Optimizer
 */
class CSS_Optimizer {
    
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
        $this->loader->add_filter('style_loader_tag', $this, 'defer_styles', 10, 4);
        $this->loader->add_action('wp_head', $this, 'output_critical_css', 1);
        $this->loader->add_action('wp_footer', $this, 'debug_css_detection', 999);
        $this->loader->add_action('wp_enqueue_scripts', $this, 'log_enqueued_styles', 999);
    }
    
    /**
     * Enhanced CSS deferring with critical CSS support and pattern matching
     */
    public function defer_styles($html, $handle, $href, $media) {
        if (!$this->options['enable_css_defer'] || is_admin()) {
            return $html;
        }
        
        // Get styles to defer (including auto-detected patterns)
        $styles_to_defer = $this->get_styles_to_defer();
        
        // Check if this style should be deferred
        $should_defer = $this->should_defer_style($handle, $styles_to_defer);
        
        if (!$should_defer) {
            return $html;
        }
        
        if ($this->options['css_defer_method'] === 'preload_with_critical') {
            // Advanced method with preload and critical CSS
            $preload_html = '<link rel="preload" href="' . esc_url($href) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" id="' . esc_attr($handle) . '-preload">';
            $noscript_html = '<noscript><link rel="stylesheet" href="' . esc_url($href) . '" id="' . esc_attr($handle) . '-noscript"></noscript>';
            return $preload_html . "\n" . $noscript_html;
        } else {
            // Simple defer method - change media to print, then switch with JS
            $deferred_html = str_replace(' media=\'all\'', ' media=\'print\' onload="this.media=\'all\'"', $html);
            $deferred_html = str_replace(' media="all"', ' media="print" onload="this.media=\'all\'"', $deferred_html);
            if (strpos($deferred_html, 'media=') === false) {
                $deferred_html = str_replace('<link ', '<link media="print" onload="this.media=\'all\'" ', $deferred_html);
            }
            return $deferred_html . '<noscript>' . $html . '</noscript>';
        }
    }
    
    /**
     * Output critical CSS
     */
    public function output_critical_css() {
        // Skip in admin, AJAX, or Elementor preview contexts
        if (Context_Helper::should_skip_optimization()) {
            return;
        }
        
        if (!$this->options['enable_css_defer'] || $this->options['css_defer_method'] !== 'preload_with_critical') {
            return;
        }
        
        $critical_css = '';
        
        // Global critical CSS (all pages)
        if (!empty($this->options['critical_css_global'])) {
            $critical_css .= $this->options['critical_css_global'];
        }
        
        // Homepage-specific critical CSS
        if (is_front_page() && !empty($this->options['critical_css_home'])) {
            $critical_css .= "\n" . $this->options['critical_css_home'];
        }
        
        // Page-specific critical CSS
        if (is_page() && !empty($this->options['critical_css_pages'])) {
            $critical_css .= "\n" . $this->options['critical_css_pages'];
        }
        
        // Post-specific critical CSS
        if (is_single() && !empty($this->options['critical_css_posts'])) {
            $critical_css .= "\n" . $this->options['critical_css_posts'];
        }
        
        if (!empty($critical_css)) {
            echo '<style id="coreboost-critical-css">' . "\n" . $critical_css . "\n" . '</style>' . "\n";
        }
    }
    
    /**
     * Get styles to defer including auto-detected patterns
     */
    private function get_styles_to_defer() {
        $manual_styles = array_filter(array_map('trim', explode("\n", $this->options['styles_to_defer'])));
        $auto_patterns = Config::get_auto_defer_patterns();
        
        return array_merge($manual_styles, $auto_patterns);
    }
    
    /**
     * Check if a CSS handle should be deferred using pattern matching
     */
    private function should_defer_style($handle, $styles_to_defer) {
        foreach ($styles_to_defer as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) continue;
            
            // Exact match (including -css suffix)
            if ($handle === $pattern || $handle === $pattern . '-css') {
                return true;
            }
            
            // Pattern matching (ends with -)
            if (substr($pattern, -1) === '-' && strpos($handle, rtrim($pattern, '-')) === 0) {
                return true;
            }
            
            // Wildcard or partial matching
            if (strpos($pattern, '*') !== false) {
                $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';  
                if (preg_match($regex, $handle)) return true;
            } elseif (strpos($handle, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Debug CSS detection and output information
     */
    public function debug_css_detection() {
        // Skip if debug mode disabled or in admin/AJAX/Elementor preview contexts
        if (!$this->options['debug_mode'] || Context_Helper::should_skip_optimization()) {
            return;
        }
        
        global $wp_styles;
        if (!isset($wp_styles->queue) || empty($wp_styles->queue)) {
            echo "<!-- CoreBoost: No CSS files found in queue -->\n";
            return;
        }
        
        echo "<!-- CoreBoost: CSS Debug Information -->\n";
        echo "<!-- CoreBoost: Total CSS files enqueued: " . count($wp_styles->queue) . " -->\n";
        
        $styles_to_defer = $this->get_styles_to_defer();
        $deferred_count = 0;
        
        foreach ($wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) continue;
            
            $style_obj = $wp_styles->registered[$handle];
            $src = isset($style_obj->src) ? $style_obj->src : 'N/A';
            $should_defer = $this->should_defer_style($handle, $styles_to_defer);
            
            if ($should_defer) {
                $deferred_count++;
                echo "<!-- CoreBoost: CSS DEFERRED - Handle: {$handle} | Src: " . basename($src) . " -->\n";
            } else {
                echo "<!-- CoreBoost: CSS NORMAL - Handle: {$handle} | Src: " . basename($src) . " -->\n";
            }
        }
        
        echo "<!-- CoreBoost: Total deferred CSS files: {$deferred_count} -->\n";
        echo "<!-- CoreBoost: CSS defer patterns: " . implode(', ', array_slice($styles_to_defer, 0, 10)) . (count($styles_to_defer) > 10 ? '...' : '') . " -->\n";
    }
    
    /**
     * Log enqueued styles for debugging
     */
    public function log_enqueued_styles() {
        // Skip if debug mode disabled or in admin/AJAX/Elementor preview contexts
        if (!$this->options['debug_mode'] || Context_Helper::should_skip_optimization()) {
            return;
        }
        
        global $wp_styles;
        if (!isset($wp_styles->queue) || empty($wp_styles->queue)) return;
        
        $styles_to_defer = $this->get_styles_to_defer();
        foreach ($wp_styles->queue as $handle) {
            if (isset($wp_styles->registered[$handle]) && $this->should_defer_style($handle, $styles_to_defer)) {
                Context_Helper::debug_log("Will defer CSS - Handle: {$handle}");
            }
        }
    }
}
