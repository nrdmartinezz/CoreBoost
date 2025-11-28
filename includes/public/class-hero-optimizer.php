<?php
/**
 * Hero image optimization and LCP improvement
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Debug_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Hero_Optimizer
 */
class Hero_Optimizer {
    
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
        $this->define_hooks();
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        $this->loader->add_action('wp_head', $this, 'preload_hero_images', 1);
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_optimization_styles');
    }
    
    /**
     * Main hero image preloading function
     */
    public function preload_hero_images() {
        if (is_admin() || $this->options['preload_method'] === 'disabled') {
            return;
        }
        
        $methods = array(
            'auto_elementor' => 'preload_auto_elementor',
            'featured_fallback' => 'preload_featured_fallback',
            'smart_detection' => 'preload_smart_detection',
            'advanced_cached' => 'preload_advanced_cached',
            'css_class_based' => 'preload_css_class_based'
        );
        
        $method = $this->options['preload_method'];
        if (isset($methods[$method])) {
            $this->{$methods[$method]}();
        }
    }
    
    /**
     * Auto Elementor detection method
     */
    private function preload_auto_elementor() {
        if (!defined('ELEMENTOR_VERSION')) return;
        
        global $post;
        if (!$post) return;
        
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if (empty($elementor_data)) return;
        
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) return;
        
        $hero_image_url = $this->find_hero_background_image($data);
        if ($hero_image_url) {
            $this->output_preload_tag($hero_image_url);
            if ($this->options['enable_responsive_preload']) {
                $this->output_responsive_preload($hero_image_url);
            }
        }
    }
    
    /**
     * Featured image fallback method
     */
    private function preload_featured_fallback() {
        global $post;
        $hero_image_url = null;
        $hero_image_id = null;
        
        // Try Elementor first
        if (defined('ELEMENTOR_VERSION') && $post) {
            $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
            if ($elementor_data) {
                $data = json_decode($elementor_data, true);
                if (isset($data[0]['settings']['background_image']['url'])) {
                    $hero_image_url = $data[0]['settings']['background_image']['url'];
                    $hero_image_id = isset($data[0]['settings']['background_image']['id']) ? $data[0]['settings']['background_image']['id'] : null;
                }
            }
        }
        
        // Fallback to featured image
        if (!$hero_image_url && has_post_thumbnail()) {
            $hero_image_id = get_post_thumbnail_id();
            $hero_image_url = get_the_post_thumbnail_url($post->ID, 'full');
        }
        
        // Fallback to custom field
        if (!$hero_image_url) {
            $custom_hero = get_post_meta($post->ID, 'hero_image', true);
            if ($custom_hero) $hero_image_url = $custom_hero;
        }
        
        if ($hero_image_url) {
            $this->output_preload_tag($hero_image_url);
            if ($this->options['enable_responsive_preload'] && $hero_image_id) {
                $this->output_responsive_preload_by_id($hero_image_id);
            }
        }
    }
    
    /**
     * Smart detection with manual override
     */
    private function preload_smart_detection() {
        global $post;
        
        // Check for page-specific images first
        $specific_images = $this->parse_specific_pages();
        
        if (is_front_page() && isset($specific_images['home'])) {
            $this->output_preload_tag($specific_images['home']);
            return;
        }
        
        if (is_page() && $post) {
            $page_slug = $post->post_name;
            if (isset($specific_images[$page_slug])) {
                $this->output_preload_tag($specific_images[$page_slug]);
                return;
            }
        }
        
        // Fall back to auto-detection
        $this->preload_auto_elementor();
    }
    
    /**
     * Advanced cached method
     */
    private function preload_advanced_cached() {
        if (!defined('ELEMENTOR_VERSION')) return;
        
        global $post;
        if (!$post) return;
        
        // Check cache first
        $cache_key = 'coreboost_hero_' . $post->ID;
        $cached_image = null;
        
        if ($this->options['enable_caching']) {
            $cached_image = get_transient($cache_key);
        }
        
        if ($cached_image !== false && $cached_image !== null) {
            if ($cached_image) {
                $this->output_preload_tag($cached_image);
                
                if ($this->options['enable_responsive_preload']) {
                    $this->output_responsive_preload($cached_image);
                }
            }
            return;
        }
        
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        $hero_image_url = null;
        
        if ($elementor_data) {
            $data = json_decode($elementor_data, true);
            if (is_array($data)) {
                $hero_image_url = $this->search_elementor_hero_advanced($data);
            }
        }
        
        // Cache the result
        if ($this->options['enable_caching']) {
            set_transient($cache_key, $hero_image_url, 3600); // Cache for 1 hour
        }
        
        if ($hero_image_url) {
            $this->output_preload_tag($hero_image_url);
            
            if ($this->options['enable_responsive_preload']) {
                $this->output_responsive_preload($hero_image_url);
            }
        }
    }
    
    /**
     * CSS class-based detection
     */
    private function preload_css_class_based() {
        if (!defined('ELEMENTOR_VERSION')) return;
        
        global $post;
        if (!$post) return;
        
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if ($elementor_data) {
            $data = json_decode($elementor_data, true);
            $hero_image = $this->find_hero_foreground_image($data);
            
            if ($hero_image) {
                $this->output_preload_tag($hero_image['url']);
                if ($this->options['enable_responsive_preload'] && $hero_image['id']) {
                    $this->output_responsive_preload_by_id($hero_image['id']);
                }
            }
        }
    }
    
    /**
     * Helper functions for image detection
     */
    private function find_hero_background_image($elements, $depth = 0, $max_depth = 2) {
        if ($depth > $max_depth) return null;
        
        foreach ($elements as $element) {
            if (isset($element['settings']['background_image']['url'])) {
                return $element['settings']['background_image']['url'];
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->find_hero_background_image($element['elements'], $depth + 1, $max_depth);
                if ($found) return $found;
            }
        }
        return null;
    }
    
    private function search_elementor_hero_advanced($elements, $max_depth = 3, $current_depth = 0) {
        if ($current_depth >= $max_depth) return null;
        
        foreach ($elements as $index => $element) {
            if ($index > 2 && $current_depth === 0) break;
            
            // Check background image
            if (isset($element['settings']['background_image']['url'])) {
                return $element['settings']['background_image']['url'];
            }
            
            // Check for foreground images in widgets (only at top level)
            if ($current_depth === 0 && isset($element['elements'])) {
                foreach ($element['elements'] as $column) {
                    if (isset($column['elements'])) {
                        foreach ($column['elements'] as $widget) {
                            if ($widget['widgetType'] === 'image' && isset($widget['settings']['image']['url'])) {
                                return $widget['settings']['image']['url'];
                            }
                        }
                    }
                }
            }
            
            // Recurse
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->search_elementor_hero_advanced($element['elements'], $max_depth, $current_depth + 1);
                if ($found) return $found;
            }
        }
        return null;
    }
    
    private function find_hero_foreground_image($elements) {
        foreach ($elements as $element) {
            if ($element['widgetType'] === 'image') {
                $css_classes = isset($element['settings']['_css_classes']) ? $element['settings']['_css_classes'] : '';
                
                if ((strpos($css_classes, 'hero-foreground-image') !== false || strpos($css_classes, 'heroimg') !== false) 
                    && isset($element['settings']['image']['url'])) {
                    return array(
                        'url' => $element['settings']['image']['url'],
                        'id' => isset($element['settings']['image']['id']) ? $element['settings']['image']['id'] : null
                    );
                }
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->find_hero_foreground_image($element['elements']);
                if ($found) return $found;
            }
        }
        return null;
    }
    
    /**
     * Parse specific pages configuration
     */
    private function parse_specific_pages() {
        $specific_pages = array();
        foreach (explode("\n", $this->options['specific_pages']) as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $parts = explode('|', $line, 2);
                if (count($parts) === 2) {
                    $specific_pages[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
        return $specific_pages;
    }
    
    /**
     * Output preload tag
     */
    private function output_preload_tag($image_url) {
        if (empty($image_url)) {
            return;
        }
        
        Debug_Helper::comment('Preloading hero image: ' . esc_url($image_url), $this->options['debug_mode']);
        
        echo '<link rel="preload" href="' . esc_url($image_url) . '" as="image" fetchpriority="high">' . "\n";
    }
    
    private function output_responsive_preload($image_url) {
        $image_id = attachment_url_to_postid($image_url);
        if ($image_id) $this->output_responsive_preload_by_id($image_id);
    }
    
    private function output_responsive_preload_by_id($image_id) {
        $sizes = array(
            'large' => '(max-width: 1024px)',
            'medium_large' => '(max-width: 768px)',
            'medium' => '(max-width: 480px)'
        );
        
        $original_url = wp_get_attachment_image_url($image_id, 'full');
        
        foreach ($sizes as $size => $media_query) {
            $responsive_url = wp_get_attachment_image_url($image_id, $size);
            if ($responsive_url && $responsive_url !== $original_url) {
                echo '<link rel="preload" href="' . esc_url($responsive_url) . '" as="image" media="' . $media_query . '">' . "\n";
            }
        }
    }
    
    /**
     * Enqueue optimization styles
     */
    public function enqueue_optimization_styles() {
        if ($this->options['enable_foreground_conversion']) {
            wp_add_inline_style('wp-block-library', $this->get_foreground_conversion_css());
        }
    }
    
    /**
     * Get foreground conversion CSS
     */
    private function get_foreground_conversion_css() {
        return '
        /* CoreBoost - Foreground Image Conversion */
        .hero-section-container,
        .relative {
            position: relative;
        }
        
        .hero-section-container .elementor-container,
        .hero-section-container .elementor-widget-wrap,
        .relative .elementor-container,
        .relative .elementor-widget-wrap {
            position: initial;
        }
        
        .hero-foreground-image,
        .hero-foreground-image img,
        .heroimg,
        .heroimg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            z-index: -1;
        }
        ';
    }
}
