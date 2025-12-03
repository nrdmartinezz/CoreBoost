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
        echo '<code style="background: #fff; padding: 8px 12px; border-radius: 3px; display: block; margin: 10px 0; font-family: monospace; border: 1px solid #e0e0e0; overflow-x: auto;">meet-dr-shafer|/wp-content/uploads/2025/10/image.jpg</code>';
        echo '<p style="margin: 8px 0 0 0;"><strong>' . esc_html__('Examples:', 'coreboost') . '</strong></p>';
        echo '<small style="color: #666;">' . esc_html__('home|/wp-content/uploads/hero-home.jpg', 'coreboost') . '<br>';
        echo esc_html__('meet-dr-shafer|/wp-content/uploads/2025/10/dr-shafer.jpg', 'coreboost') . '<br>';
        echo esc_html__('services|/wp-content/uploads/services-hero.jpg', 'coreboost') . '</small>';
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
     * Image section callback (includes Bulk Converter UI)
     */
    public function image_section_callback() {
        echo '<p>' . esc_html__('Comprehensive image optimization to improve performance and prevent layout shifts. Lazy loading reduces initial page load by deferring off-screen images. Width/height attributes and aspect ratio CSS prevent Cumulative Layout Shift (CLS). Async decoding prevents render-blocking image decode operations.', 'coreboost') . '</p>';
        
        // Check if image format conversion is enabled
        $format_conversion_enabled = !empty($this->options['enable_image_format_conversion']);
        
        // Bulk Image Converter UI
        echo '<div style="background-color: #f0f7ff; border-left: 4px solid #2196F3; padding: 16px; margin: 20px 0; border-radius: 3px;">';
        echo '<h4 style="margin-top: 0; color: #1976D2;">' . esc_html__('Bulk Image Converter', 'coreboost') . '</h4>';
        
        // Warning if format conversion is disabled
        if (!$format_conversion_enabled) {
            echo '<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 15px; color: #856404;">';
            echo '<p style="margin: 0;"><strong>⚠️ ' . esc_html__('Image Format Conversion Disabled', 'coreboost') . '</strong></p>';
            echo '<p style="margin: 8px 0 0 0; font-size: 13px;">' . esc_html__('Enable "Generate AVIF/WebP Variants" below to use the bulk converter.', 'coreboost') . '</p>';
            echo '</div>';
        }
        
        // Status and info
        echo '<div style="margin-bottom: 15px;">';
        echo '<p style="margin: 8px 0;"><strong>' . esc_html__('Status:', 'coreboost') . '</strong> <span id="coreboost-bulk-status" style="font-weight: bold; color: #666;">Not started</span></p>';
        echo '<p style="margin: 8px 0;"><strong>' . esc_html__('Images converted:', 'coreboost') . '</strong> <span id="coreboost-images-converted" style="font-weight: bold; color: #2196F3;">0</span> / <span id="coreboost-image-count">-</span></p>';
        echo '<p style="margin: 8px 0;"><strong>' . esc_html__('Batch size:', 'coreboost') . '</strong> <span id="coreboost-batch-size">-</span></p>';
        echo '<p style="margin: 8px 0;"><strong>' . esc_html__('Estimated time:', 'coreboost') . '</strong> <span id="coreboost-est-time">-</span></p>';
        echo '</div>';
        
        $this->render_bulk_converter_stats();
        $this->render_bulk_converter_controls($format_conversion_enabled);
        
        echo '</div>';
    }
    
    /**
     * Render bulk converter statistics dashboard
     */
    private function render_bulk_converter_stats() {
        // Image Conversion Statistics Dashboard
        echo '<div id="coreboost-stats-dashboard" style="margin: 20px 0;">';
        echo '<h5 style="margin: 0 0 15px 0; color: #1976D2; font-size: 14px; font-weight: 600;">' . esc_html__('Image Conversion Statistics', 'coreboost') . '</h5>';
        
        // Storage folder path
        $upload_dir = wp_upload_dir();
        $variants_path = $upload_dir['basedir'] . '/coreboost-variants/';
        echo '<p style="margin: 0 0 15px 0; font-size: 13px; color: #666;">';
        echo '<strong>' . esc_html__('Storage Location:', 'coreboost') . '</strong> ';
        echo '<code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 12px;">' . esc_html($variants_path) . '</code>';
        echo '</p>';
        
        echo '<div class="coreboost-stats-grid">';
        
        // Converted stat card
        echo '<div class="coreboost-stat-card stat-converted">';
        echo '<div class="coreboost-circle-progress">';
        echo '<svg width="80" height="80">';
        echo '<circle class="circle-bg" cx="40" cy="40" r="36"></circle>';
        echo '<circle class="circle-progress" id="circle-converted" cx="40" cy="40" r="36" stroke-dasharray="226.19" stroke-dashoffset="226.19"></circle>';
        echo '</svg>';
        echo '<div class="circle-text" id="percent-converted">0%</div>';
        echo '</div>';
        echo '<div class="coreboost-stat-label">' . esc_html__('Converted', 'coreboost') . '</div>';
        echo '<div class="coreboost-stat-number"><span id="count-converted">0</span> ' . esc_html__('images', 'coreboost') . '</div>';
        echo '</div>';
        
        // Orphaned stat card
        echo '<div class="coreboost-stat-card stat-orphaned">';
        echo '<div class="coreboost-circle-progress">';
        echo '<svg width="80" height="80">';
        echo '<circle class="circle-bg" cx="40" cy="40" r="36"></circle>';
        echo '<circle class="circle-progress" id="circle-orphaned" cx="40" cy="40" r="36" stroke-dasharray="226.19" stroke-dashoffset="226.19"></circle>';
        echo '</svg>';
        echo '<div class="circle-text" id="percent-orphaned">0%</div>';
        echo '</div>';
        echo '<div class="coreboost-stat-label">' . esc_html__('Orphaned', 'coreboost') . '</div>';
        echo '<div class="coreboost-stat-number"><span id="count-orphaned">0</span> ' . esc_html__('variants', 'coreboost') . '</div>';
        echo '</div>';
        
        // Unconverted stat card
        echo '<div class="coreboost-stat-card stat-unconverted">';
        echo '<div class="coreboost-circle-progress">';
        echo '<svg width="80" height="80">';
        echo '<circle class="circle-bg" cx="40" cy="40" r="36"></circle>';
        echo '<circle class="circle-progress" id="circle-unconverted" cx="40" cy="40" r="36" stroke-dasharray="226.19" stroke-dashoffset="226.19"></circle>';
        echo '</svg>';
        echo '<div class="circle-text" id="percent-unconverted">100%</div>';
        echo '</div>';
        echo '<div class="coreboost-stat-label">' . esc_html__('Not Converted', 'coreboost') . '</div>';
        echo '<div class="coreboost-stat-number"><span id="count-unconverted">0</span> ' . esc_html__('images', 'coreboost') . '</div>';
        echo '</div>';
        
        // Total stat card
        echo '<div class="coreboost-stat-card stat-total">';
        echo '<div class="coreboost-circle-progress">';
        echo '<svg width="80" height="80">';
        echo '<circle class="circle-bg" cx="40" cy="40" r="36"></circle>';
        echo '<circle class="circle-progress" id="circle-total" cx="40" cy="40" r="36" stroke-dasharray="226.19" stroke-dashoffset="0"></circle>';
        echo '</svg>';
        echo '<div class="circle-text" id="percent-total">100%</div>';
        echo '</div>';
        echo '<div class="coreboost-stat-label">' . esc_html__('Total Images', 'coreboost') . '</div>';
        echo '<div class="coreboost-stat-number"><span id="count-total">0</span> ' . esc_html__('images', 'coreboost') . '</div>';
        echo '</div>';
        
        echo '</div>'; // End stats-grid
        echo '</div>'; // End stats-dashboard
    }
    
    /**
     * Render bulk converter control elements
     *
     * @param bool $format_conversion_enabled Whether format conversion is enabled
     */
    private function render_bulk_converter_controls($format_conversion_enabled) {
        // Progress bar
        echo '<div id="coreboost-progress-container" style="display: none; margin-bottom: 15px;">';
        echo '<div style="background-color: #e0e0e0; border-radius: 4px; height: 24px; overflow: hidden; margin-bottom: 8px;">';
        echo '<div id="coreboost-progress-bar" style="background: linear-gradient(90deg, #4CAF50 0%, #45a049 100%); height: 100%; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: bold;"></div>';
        echo '</div>';
        echo '<p style="margin: 8px 0; font-size: 13px;"><span id="coreboost-progress-text">Processing...</span></p>';
        echo '<p style="margin: 4px 0; font-size: 12px; color: #666;"><span id="coreboost-time-elapsed">Elapsed: 0s</span> | <span id="coreboost-time-remaining">Remaining: calculating...</span></p>';
        echo '</div>';
        
        // Error message
        echo '<div id="coreboost-error-message" style="display: none; background-color: #ffebee; border-left: 4px solid #f44336; padding: 12px; margin-bottom: 15px; color: #c62828;">';
        echo '<p style="margin: 0;" id="coreboost-error-text"></p>';
        echo '</div>';
        
        // Success message
        echo '<div id="coreboost-success-message" style="display: none; background-color: #e8f5e9; border-left: 4px solid #4CAF50; padding: 12px; margin-bottom: 15px; color: #2e7d32;">';
        echo '<p style="margin: 0;" id="coreboost-success-text"></p>';
        echo '</div>';
        
        // Buttons
        echo '<div style="margin-top: 15px;">';
        $button_disabled = !$format_conversion_enabled ? ' disabled' : '';
        $button_title = !$format_conversion_enabled ? ' title="' . esc_attr__('Enable Image Format Conversion first', 'coreboost') . '"' : '';
        echo '<button type="button" id="coreboost-start-bulk" class="button button-primary" style="background-color: #4CAF50; border-color: #4CAF50; margin-right: 10px;" data-format-enabled="' . ($format_conversion_enabled ? '1' : '0') . '"' . $button_disabled . $button_title . '>' . esc_html__('Start Conversion', 'coreboost') . '</button>';
        echo '<button type="button" id="coreboost-stop-bulk" class="button" style="display: none; background-color: #f44336; border-color: #f44336; color: white;" disabled>' . esc_html__('Stop', 'coreboost') . '</button>';
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
