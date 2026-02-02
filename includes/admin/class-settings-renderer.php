<?php
/**
 * Settings Renderer - Field Rendering Callbacks
 *
 * Handles rendering of settings fields and sections.
 * Follows Single Responsibility Principle - only concerned with display logic.
 *
 * @package CoreBoost
 * @since 2.7.0
 */

namespace CoreBoost\Admin;

use CoreBoost\Core\Field_Renderer;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Settings_Renderer
 *
 * Responsible for:
 * - Rendering section descriptions
 * - Rendering individual field callbacks
 * - Generating field HTML output
 */
class Settings_Renderer {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
    }
    
    /**
     * Dynamic field callback handler
     * Renders fields based on configuration from Config class
     *
     * @param array $args Field arguments
     */
    public function render_field_callback($args) {
        $field_name = $args['field_name'];
        $config = $args['config'];
        $value = isset($this->options[$field_name]) ? $this->options[$field_name] : (isset($config['default']) ? $config['default'] : null);
        
        switch($config['type']) {
            case 'checkbox':
                Field_Renderer::render_checkbox($field_name, $value, $config['default'], $config['description']);
                break;
            case 'textarea':
                $class = isset($config['class']) ? $config['class'] : 'large-text';
                Field_Renderer::render_textarea($field_name, $value, $config['rows'], $config['description'], $class);
                break;
            case 'slider':
                $min = isset($config['min']) ? (int)$config['min'] : 0;
                $max = isset($config['max']) ? (int)$config['max'] : 100;
                $step = isset($config['step']) ? (int)$config['step'] : 1;
                Field_Renderer::render_slider($field_name, $value, $min, $max, $step, $config['description']);
                break;
            case 'select':
                $options = isset($config['options']) ? $config['options'] : array();
                Field_Renderer::render_select($field_name, $value, $options, $config['description']);
                break;
        }
    }
    
    /**
     * Hero section callback
     */
    public function hero_section_callback() {
        echo '<p>' . esc_html__('Configure how hero images are detected and preloaded for optimal LCP performance.', 'coreboost') . '</p>';
        
        echo '<div style="background-color: #f0f7ff; border-left: 4px solid #2196F3; padding: 12px; margin: 15px 0; border-radius: 3px;">';
        echo '<h4 style="margin-top: 0; color: #1976D2;">' . esc_html__('Page-Specific Hero Images', 'coreboost') . '</h4>';
        echo '<p style="margin: 8px 0;">' . esc_html__('Enter your hero image URLs for specific pages in the "Page-Specific Images" field below. Format: one entry per line as page-slug|image-url', 'coreboost') . '</p>';
        echo '<code style="background: #fff; padding: 8px 12px; border-radius: 3px; display: block; margin: 10px 0; font-family: monospace; border: 1px solid #e0e0e0; overflow-x: auto;">home|/wp-content/uploads/2025/10/example.jpg</code>';
        echo '</div>';
    }
    
    /**
     * Script section callback
     */
    public function script_section_callback() {
        echo '<p>' . esc_html__('Optimize JavaScript loading to reduce critical request chain. Use defer for jQuery-dependent scripts (downloads in parallel, executes in order). Use async for independent scripts like YouTube, analytics (downloads and executes immediately). This eliminates network waterfall congestion.', 'coreboost') . '</p>';
    }
    
    /**
     * CSS section callback
     */
    public function css_section_callback() {
        echo '<p>' . esc_html__('Advanced CSS optimization with critical CSS inlining and non-critical CSS deferring.', 'coreboost') . '</p>';
    }
    
    /**
     * Image section callback
     */
    public function image_section_callback() {
        echo '<p>' . esc_html__('Comprehensive image optimization to improve performance and prevent layout shifts. Lazy loading reduces initial page load by deferring off-screen images. Width/height attributes and aspect ratio CSS prevent Cumulative Layout Shift (CLS). Async decoding prevents render-blocking image decode operations.', 'coreboost') . '</p>';
        
        // Recommendation for Converter for Media
        echo '<div style="background-color: #e3f2fd; border-left: 4px solid #2196F3; padding: 16px; margin: 20px 0; border-radius: 3px;">';
        echo '<h4 style="margin-top: 0; color: #1976D2;">' . esc_html__('Need AVIF/WebP Image Conversion?', 'coreboost') . '</h4>';
        echo '<p style="margin: 8px 0;">' . esc_html__('For converting your images to modern formats like AVIF and WebP, we recommend using a dedicated image conversion plugin:', 'coreboost') . '</p>';
        echo '<p style="margin: 8px 0;"><strong><a href="https://wordpress.org/plugins/webp-converter-for-media/" target="_blank" rel="noopener">Converter for Media</a></strong> - ' . esc_html__('Free, fast, and reliable image format conversion with cloud processing options.', 'coreboost') . '</p>';
        echo '</div>';
    }

    /**
     * Advanced section callback
     */
    public function advanced_section_callback() {
        echo '<p>' . esc_html__('Advanced optimization settings including unused resource removal and debugging options.', 'coreboost') . '</p>';
    }
    
    /**
     * Preload method field callback
     */
    public function preload_method_callback() {
        $value = isset($this->options['preload_method']) ? $this->options['preload_method'] : 'auto_elementor';
        $methods = array(
            'auto_elementor' => __('Auto-detect from Elementor Data', 'coreboost'),
            'featured_fallback' => __('Featured Image with Fallback', 'coreboost'),
            'smart_detection' => __('Smart Detection with Manual Override', 'coreboost'),
            'advanced_cached' => __('Advanced with Caching', 'coreboost'),
            'css_class_based' => __('CSS Class-Based Detection', 'coreboost'),
            'disabled' => __('Disabled', 'coreboost')
        );
        
        Field_Renderer::render_select('preload_method', $value, $methods, 'auto_elementor', __('Choose how hero images should be detected and preloaded.', 'coreboost'));
    }
    
    /**
     * Specific pages field callback
     */
    public function specific_pages_callback() {
        $value = isset($this->options['specific_pages']) ? $this->options['specific_pages'] : '';
        echo '<textarea name="coreboost_options[specific_pages]" rows="5" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Enter one URL per line. For each URL, provide the image URL to preload. Format: `https://example.com/page/ https://example.com/image.jpg`', 'coreboost') . '</p>';
        echo '<p class="description"><strong>' . __('Example:', 'coreboost') . '</strong> home|/wp-content/uploads/hero-home.jpg</p>';
    }

    /**
     * Lazy load exclude count field callback
     */
    public function lazy_load_exclude_count_callback() {
        $value = isset($this->options['lazy_load_exclude_count']) ? (int)$this->options['lazy_load_exclude_count'] : 2;
        echo '<input type="number" name="coreboost_options[lazy_load_exclude_count]" value="' . esc_attr($value) . '" min="0" max="10" /> ';
        echo '<p class="description">' . __('Automatically disable lazy loading and apply `fetchpriority="high"` to the first X images on the page. Recommended: 2-3.', 'coreboost') . '</p>';
    }

    /**
     * Hero preload cache TTL field callback
     */
    public function hero_preload_cache_ttl_callback() {
        $value = isset($this->options['hero_preload_cache_ttl']) ? (int)$this->options['hero_preload_cache_ttl'] : 2592000;
        $options = array(
            86400 => __('1 Day', 'coreboost'),
            604800 => __('7 Days', 'coreboost'),
            2592000 => __('30 Days (Recommended)', 'coreboost')
        );
        echo '<select name="coreboost_options[hero_preload_cache_ttl]">';
        foreach ($options as $ttl => $label) {
            echo '<option value="' . esc_attr($ttl) . '"' . selected($value, $ttl, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Cache duration for preload detection results. Longer cache = better performance. Manual cache clear available in settings.', 'coreboost') . '</p>';
    }

    /**
     * Enable hero preload extraction field callback
     */
    public function enable_hero_preload_extraction_callback() {
        $value = isset($this->options['enable_hero_preload_extraction']) ? $this->options['enable_hero_preload_extraction'] : true;
        echo '<label>';
        echo '<input type="checkbox" name="coreboost_options[enable_hero_preload_extraction]" value="1" ' . checked($value, true, false) . ' />';
        echo ' ' . esc_html__('Enable hero image preload extraction', 'coreboost');
        echo '</label>';
        echo '<p class="description">';
        echo esc_html__('When enabled, CoreBoost will generate preload links for hero images configured in the "Page-Specific Images" field, improving LCP performance.', 'coreboost');
        echo '</p>';
        echo '<p class="description" style="background-color: #fff8e1; padding: 10px; border-radius: 3px; border-left: 3px solid #FFC107;">';
        echo '<strong>' . esc_html__('How to use:', 'coreboost') . '</strong><br>';
        echo esc_html__('1. Enter your page slug and hero image URL above (format: page-slug|image-url)', 'coreboost') . '<br>';
        echo esc_html__('2. CoreBoost will automatically preload the image when that page loads', 'coreboost') . '<br>';
        echo esc_html__('3. Results are cached for optimal performance', 'coreboost');
        echo '</p>';
    }
    
    /**
     * CSS defer method field callback
     */
    public function css_defer_method_callback() {
        $methods = array(
            'preload_with_critical' => __('Preload with Critical CSS (Recommended)', 'coreboost'),
            'simple_defer' => __('Simple Defer (Basic)', 'coreboost')
        );
        $value = isset($this->options['css_defer_method']) ? $this->options['css_defer_method'] : 'preload_with_critical';
        Field_Renderer::render_select('css_defer_method', $value, $methods, 'preload_with_critical', 'Choose CSS deferring method. Preload with Critical CSS provides better performance.');
    }
    
    /**
     * Enable font optimization field callback
     */
    public function enable_font_optimization_callback() {
        $value = isset($this->options['enable_font_optimization']) ? $this->options['enable_font_optimization'] : false;
        Field_Renderer::render_checkbox('enable_font_optimization', $value, false, 'Optimize external font loading to eliminate render-blocking and improve page speed.');
        echo '<p class="description"><strong>' . __('Note:', 'coreboost') . '</strong> ' . __('This will automatically add preconnect links and defer font stylesheets from Google Fonts and Adobe Fonts.', 'coreboost') . '</p>';
    }
    
    /**
     * Defer Google Fonts field callback
     */
    public function defer_google_fonts_callback() {
        $value = isset($this->options['defer_google_fonts']) ? $this->options['defer_google_fonts'] : false;
        $disabled = !$this->options['enable_font_optimization'];
        Field_Renderer::render_checkbox('defer_google_fonts', $value, $disabled, 'Defer Google Fonts (fonts.googleapis.com) loading using preload with onload handler.');
    }
    
    /**
     * Defer Adobe Fonts field callback
     */
    public function defer_adobe_fonts_callback() {
        $value = isset($this->options['defer_adobe_fonts']) ? $this->options['defer_adobe_fonts'] : false;
        $disabled = !$this->options['enable_font_optimization'];
        Field_Renderer::render_checkbox('defer_adobe_fonts', $value, $disabled, 'Defer Adobe Fonts (use.typekit.net, fonts.adobe.com) loading using preload with onload handler.');
    }
}
