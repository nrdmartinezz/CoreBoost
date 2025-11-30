<?php
/**
 * Settings registration and sanitization
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\Admin;

use CoreBoost\Core\Config;
use CoreBoost\Core\Field_Renderer;
use CoreBoost\Core\Cache_Manager;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Settings
 */
class Settings {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Tag Settings instance
     *
     * @var Tag_Settings
     */
    private $tag_settings;
    
    /**
     * Script Settings instance
     *
     * @var Script_Settings
     */
    private $script_settings;
    
    /**
     * Advanced Optimization Settings instance
     *
     * @var Advanced_Optimization_Settings
     */
    private $advanced_settings;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
        $this->tag_settings = new Tag_Settings($options);
        $this->script_settings = new Script_Settings($options);
        $this->advanced_settings = new Advanced_Optimization_Settings($options);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('coreboost_options_group', 'coreboost_options', array($this, 'sanitize_options'));
        
        // Register Custom Tag settings
        $this->tag_settings->register_settings();
        
        // Register Script Optimization settings
        $this->script_settings->register_settings();
        
        // Register Advanced Optimization settings (Phase 3-4)
        $this->advanced_settings->register_settings();
        
        // Hero Image Optimization Tab
        add_settings_section(
            'coreboost_hero_section',
            __('Hero Image & LCP Optimization', 'coreboost'),
            array($this, 'hero_section_callback'),
            'coreboost-hero'
        );
        
        // Script Optimization Tab
        add_settings_section(
            'coreboost_script_section',
            __('Script Optimization', 'coreboost'),
            array($this, 'script_section_callback'),
            'coreboost-scripts'
        );
        
        // CSS Optimization Tab
        add_settings_section(
            'coreboost_css_section',
            __('CSS Optimization & Critical CSS', 'coreboost'),
            array($this, 'css_section_callback'),
            'coreboost-css'
        );
        
        // Image Optimization Tab
        add_settings_section(
            'coreboost_image_section',
            __('Image Optimization', 'coreboost'),
            array($this, 'image_section_callback'),
            'coreboost-images'
        );
        
        // Advanced Settings Tab
        add_settings_section(
            'coreboost_advanced_section',
            __('Advanced Settings', 'coreboost'),
            array($this, 'advanced_section_callback'),
            'coreboost-advanced'
        );
        
        $this->add_settings_fields();
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // Hero Image Fields
        add_settings_field('preload_method', __('Preload Method', 'coreboost'), array($this, 'preload_method_callback'), 'coreboost-hero', 'coreboost_hero_section');
        $this->add_dynamic_field('enable_responsive_preload', __('Responsive Preloading', 'coreboost'), 'coreboost-hero', 'coreboost_hero_section');
        $this->add_dynamic_field('enable_foreground_conversion', __('Enable Foreground CSS', 'coreboost'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('enable_hero_preload_extraction', __('Enable Hero Preload', 'coreboost'), array($this, 'enable_hero_preload_extraction_callback'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('hero_preload_cache_ttl', __('Hero Preload Cache TTL', 'coreboost'), array($this, 'hero_preload_cache_ttl_callback'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('specific_pages', __('Page-Specific Images', 'coreboost'), array($this, 'specific_pages_callback'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('lazy_load_exclude_count', __('Exclude First X Images from Lazy Load', 'coreboost'), array($this, 'lazy_load_exclude_count_callback'), 'coreboost-hero', 'coreboost_hero_section');
        
        // Script Fields
        $this->add_dynamic_field('enable_script_defer', __('Enable Script Deferring', 'coreboost'), 'coreboost-scripts', 'coreboost_script_section');
        $this->add_dynamic_field('scripts_to_defer', __('Scripts to Defer', 'coreboost'), 'coreboost-scripts', 'coreboost_script_section');
        $this->add_dynamic_field('scripts_to_async', __('Scripts to Load Async', 'coreboost'), 'coreboost-scripts', 'coreboost_script_section');
        $this->add_dynamic_field('exclude_scripts', __('Exclude Scripts', 'coreboost'), 'coreboost-scripts', 'coreboost_script_section');
        
        // CSS Fields
        $this->add_dynamic_field('enable_css_defer', __('Enable CSS Deferring', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('css_defer_method', __('CSS Defer Method', 'coreboost'), array($this, 'css_defer_method_callback'), 'coreboost-css', 'coreboost_css_section');
        $this->add_dynamic_field('styles_to_defer', __('Styles to Defer', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('enable_font_optimization', __('Enable Font Optimization', 'coreboost'), array($this, 'enable_font_optimization_callback'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('defer_google_fonts', __('Defer Google Fonts', 'coreboost'), array($this, 'defer_google_fonts_callback'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('defer_adobe_fonts', __('Defer Adobe Fonts', 'coreboost'), array($this, 'defer_adobe_fonts_callback'), 'coreboost-css', 'coreboost_css_section');
        $this->add_dynamic_field('critical_css_global', __('Global Critical CSS', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        $this->add_dynamic_field('critical_css_home', __('Homepage Critical CSS', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        $this->add_dynamic_field('critical_css_pages', __('Pages Critical CSS', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        $this->add_dynamic_field('critical_css_posts', __('Posts Critical CSS', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        
        // Image Optimization Fields
        $this->add_dynamic_field('enable_image_optimization', __('Enable Image Optimization', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->add_dynamic_field('enable_lazy_loading', __('Enable Lazy Loading', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->add_dynamic_field('add_width_height_attributes', __('Add Width/Height Attributes', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->add_dynamic_field('generate_aspect_ratio_css', __('Generate Aspect Ratio CSS', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->add_dynamic_field('add_decoding_async', __('Add Decoding="async"', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        
        // Image Format Optimization Fields (Phase 2)
        $this->add_dynamic_field('enable_image_format_conversion', __('Enable Image Format Conversion', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->add_dynamic_field('avif_quality', __('AVIF Quality', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->add_dynamic_field('webp_quality', __('WebP Quality', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->add_dynamic_field('image_generation_mode', __('Variant Generation Mode', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->add_dynamic_field('cleanup_orphans_weekly', __('Weekly Orphan Cleanup', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        
        // Advanced Fields
        $this->add_dynamic_field('enable_caching', __('Enable Caching', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('enable_unused_css_removal', __('Remove Unused CSS', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('unused_css_list', __('Unused CSS Handles', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('enable_unused_js_removal', __('Remove Unused JavaScript', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('unused_js_list', __('Unused JS Handles', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('enable_inline_script_removal', __('Remove Inline Scripts by ID', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('inline_script_ids', __('Inline Script IDs', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('enable_inline_style_removal', __('Remove Inline Styles by ID', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('inline_style_ids', __('Inline Style IDs', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('smart_youtube_blocking', __('Smart YouTube Blocking', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('block_youtube_player_css', __('Block YouTube Player CSS', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('block_youtube_embed_ui', __('Block YouTube Embed UI', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
    }
    
    /**
     * Helper to add dynamic field with configuration
     */
    private function add_dynamic_field($field_name, $label, $page, $section) {
        $config = Config::get_field_config();
        if (isset($config[$field_name])) {
            add_settings_field(
                $field_name,
                $label,
                array($this, 'render_field_callback'),
                $page,
                $section,
                array('field_name' => $field_name, 'config' => $config[$field_name])
            );
        }
    }
    
    /**
     * Dynamic field callback handler
     */
    public function render_field_callback($args) {
        $field_name = $args['field_name'];
        $config = $args['config'];
        $value = isset($this->options[$field_name]) ? $this->options[$field_name] : null;
        
        switch($config['type']) {
            case 'checkbox':
                Field_Renderer::render_checkbox($field_name, $value, $config['default'], $config['description']);
                break;
            case 'textarea':
                $class = isset($config['class']) ? $config['class'] : 'large-text';
                Field_Renderer::render_textarea($field_name, $value, $config['rows'], $config['description'], $class);
                break;
        }
    }
    
    /**
     * Section callbacks
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
    
    public function script_section_callback() {
        echo '<p>' . esc_html__('Optimize JavaScript loading to reduce critical request chain. Use defer for jQuery-dependent scripts (downloads in parallel, executes in order). Use async for independent scripts like YouTube, analytics (downloads and executes immediately). This eliminates network waterfall congestion.', 'coreboost') . '</p>';
    }
    
    public function css_section_callback() {
        echo '<p>' . esc_html__('Advanced CSS optimization with critical CSS inlining and non-critical CSS deferring.', 'coreboost') . '</p>';
    }
    
    public function image_section_callback() {
        echo '<p>' . esc_html__('Comprehensive image optimization to improve performance and prevent layout shifts. Lazy loading reduces initial page load by deferring off-screen images. Width/height attributes and aspect ratio CSS prevent Cumulative Layout Shift (CLS). Async decoding prevents render-blocking image decode operations.', 'coreboost') . '</p>';
    }
    
    public function advanced_section_callback() {
        echo '<p>' . esc_html__('Advanced optimization settings including unused resource removal and debugging options.', 'coreboost') . '</p>';
    }
    
    /**
     * Field callbacks
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
    
    public function specific_pages_callback() {
        $value = isset($this->options['specific_pages']) ? $this->options['specific_pages'] : '';
        echo '<textarea name="coreboost_options[specific_pages]" rows="5" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Enter one URL per line. For each URL, provide the image URL to preload. Format: `https://example.com/page/ https://example.com/image.jpg`', 'coreboost') . '</p>';
        echo '<p class="description"><strong>' . __('Example:', 'coreboost') . '</strong> home|/wp-content/uploads/hero-home.jpg</p>';
    }

    public function lazy_load_exclude_count_callback() {
        $value = isset($this->options['lazy_load_exclude_count']) ? (int)$this->options['lazy_load_exclude_count'] : 2;
        echo '<input type="number" name="coreboost_options[lazy_load_exclude_count]" value="' . esc_attr($value) . '" min="0" max="10" /> ';
        echo '<p class="description">' . __('Automatically disable lazy loading and apply `fetchpriority="high"` to the first X images on the page. Recommended: 2-3.', 'coreboost') . '</p>';
    }

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
    
    public function css_defer_method_callback() {
        $methods = array(
            'preload_with_critical' => __('Preload with Critical CSS (Recommended)', 'coreboost'),
            'simple_defer' => __('Simple Defer (Basic)', 'coreboost')
        );
        $value = isset($this->options['css_defer_method']) ? $this->options['css_defer_method'] : 'preload_with_critical';
        Field_Renderer::render_select('css_defer_method', $value, $methods, 'preload_with_critical', 'Choose CSS deferring method. Preload with Critical CSS provides better performance.');
    }
    
    public function enable_font_optimization_callback() {
        $value = isset($this->options['enable_font_optimization']) ? $this->options['enable_font_optimization'] : false;
        Field_Renderer::render_checkbox('enable_font_optimization', $value, false, 'Optimize external font loading to eliminate render-blocking and improve page speed.');
        echo '<p class="description"><strong>' . __('Note:', 'coreboost') . '</strong> ' . __('This will automatically add preconnect links and defer font stylesheets from Google Fonts and Adobe Fonts.', 'coreboost') . '</p>';
    }
    
    public function defer_google_fonts_callback() {
        $value = isset($this->options['defer_google_fonts']) ? $this->options['defer_google_fonts'] : false;
        $disabled = !$this->options['enable_font_optimization'];
        Field_Renderer::render_checkbox('defer_google_fonts', $value, $disabled, 'Defer Google Fonts (fonts.googleapis.com) loading using preload with onload handler.');
    }
    
    public function defer_adobe_fonts_callback() {
        $value = isset($this->options['defer_adobe_fonts']) ? $this->options['defer_adobe_fonts'] : false;
        $disabled = !$this->options['enable_font_optimization'];
        Field_Renderer::render_checkbox('defer_adobe_fonts', $value, $disabled, 'Defer Adobe Fonts (use.typekit.net, fonts.adobe.com) loading using preload with onload handler.');
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        // Get current options to preserve values not in current form
        $current_options = get_option('coreboost_options', array());
        
        // Start with current options
        $sanitized = $current_options;
        
        // Sanitize and update only the fields that are present in the input
        if (isset($input['preload_method'])) {
            $sanitized['preload_method'] = sanitize_text_field($input['preload_method']);
        }
        
        if (isset($input['lazy_load_exclude_count'])) {
            $sanitized['lazy_load_exclude_count'] = absint($input['lazy_load_exclude_count']);
        }

        if (isset($input['hero_preload_cache_ttl'])) {
            $sanitized['hero_preload_cache_ttl'] = absint($input['hero_preload_cache_ttl']);
        }
        
        // Sanitize fields by type
        $field_types = array(
            'boolean' => array('enable_script_defer', 'enable_css_defer', 'enable_foreground_conversion', 
                              'enable_responsive_preload', 'enable_hero_preload_extraction', 'enable_caching', 'enable_font_optimization',
                              'font_display_swap', 'defer_google_fonts', 'defer_adobe_fonts', 
                              'preconnect_google_fonts', 'preconnect_adobe_fonts', 'enable_unused_css_removal',
                              'enable_unused_js_removal', 'enable_inline_script_removal', 'enable_inline_style_removal',
                              'smart_youtube_blocking', 'block_youtube_player_css', 'block_youtube_embed_ui',
                              'enable_image_optimization', 'enable_lazy_loading', 'add_width_height_attributes',
                              'generate_aspect_ratio_css', 'add_decoding_async', 'enable_image_format_conversion',
                              'cleanup_orphans_weekly'),
            'textarea' => array('scripts_to_defer', 'scripts_to_async', 'styles_to_defer', 'exclude_scripts', 'specific_pages',
                               'unused_css_list', 'unused_js_list', 'inline_script_ids', 'inline_style_ids'),
            'text' => array('css_defer_method'),
            'css' => array('critical_css_global', 'critical_css_home', 'critical_css_pages', 'critical_css_posts'),
            'integer' => array('avif_quality', 'webp_quality'),
            'select' => array('image_generation_mode')
        );
        
        // Detect which tab submitted the form
        $has_script_fields = isset($input['scripts_to_defer']) || isset($input['scripts_to_async']) || isset($input['exclude_scripts']);
        $has_css_fields = isset($input['styles_to_defer']) || isset($input['critical_css_global']) || isset($input['css_defer_method']);
        $has_hero_fields = isset($input['preload_method']) || isset($input['specific_pages']) || isset($input['enable_hero_preload_extraction']) || isset($input['hero_preload_cache_ttl']);
        $has_advanced_fields = isset($input['unused_css_list']) || isset($input['unused_js_list']) || 
                                isset($input['inline_script_ids']) || isset($input['inline_style_ids']);
        $has_image_fields = isset($input['enable_image_optimization']) || isset($input['enable_lazy_loading']) || 
                           isset($input['add_width_height_attributes']) || isset($input['generate_aspect_ratio_css']) || 
                           isset($input['add_decoding_async']) || isset($input['enable_image_format_conversion']) ||
                           isset($input['avif_quality']) || isset($input['webp_quality']) || 
                           isset($input['image_generation_mode']) || isset($input['cleanup_orphans_weekly']);
        
        foreach ($field_types['boolean'] as $field) {
            if (array_key_exists($field, $input)) {
                $sanitized[$field] = !empty($input[$field]);
            } else {
                // Determine if this field is from the current form tab
                $is_current_form = false;
                
                if ($has_script_fields && $field === 'enable_script_defer') {
                    $is_current_form = true;
                }
                if ($has_css_fields && in_array($field, array('enable_css_defer', 'enable_font_optimization', 'font_display_swap', 
                    'defer_google_fonts', 'defer_adobe_fonts', 'preconnect_google_fonts', 'preconnect_adobe_fonts'))) {
                    $is_current_form = true;
                }
                if ($has_hero_fields && in_array($field, array('enable_responsive_preload', 'enable_foreground_conversion', 'enable_hero_preload_extraction'))) {
                    $is_current_form = true;
                }
                if ($has_advanced_fields && in_array($field, array('enable_caching', 'enable_unused_css_removal',
                    'enable_unused_js_removal', 'enable_inline_script_removal', 'enable_inline_style_removal',
                    'smart_youtube_blocking', 'block_youtube_player_css', 'block_youtube_embed_ui'))) {
                    $is_current_form = true;
                }
                if ($has_image_fields && in_array($field, array('enable_image_optimization', 'enable_lazy_loading', 
                    'add_width_height_attributes', 'generate_aspect_ratio_css', 'add_decoding_async',
                    'enable_image_format_conversion', 'cleanup_orphans_weekly'))) {
                    $is_current_form = true;
                }
                
                if ($is_current_form) {
                    $sanitized[$field] = false;
                }
            }
        }
        
        foreach ($field_types['textarea'] as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_textarea_field($input[$field]);
            }
        }
        
        foreach ($field_types['text'] as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_text_field($input[$field]);
            }
        }
        
        foreach ($field_types['css'] as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = wp_strip_all_tags($input[$field]);
            }
        }
        
        // Sanitize integer fields with range validation
        foreach ($field_types['integer'] as $field) {
            if (isset($input[$field])) {
                $value = absint($input[$field]);
                // Clamp quality values between 75-95
                if (in_array($field, array('avif_quality', 'webp_quality'))) {
                    $value = max(75, min(95, $value));
                }
                $sanitized[$field] = $value;
            }
        }
        
        // Sanitize select fields
        foreach ($field_types['select'] as $field) {
            if (isset($input[$field])) {
                $value = sanitize_text_field($input[$field]);
                // Validate select values
                if ($field === 'image_generation_mode' && in_array($value, array('on-demand', 'eager'))) {
                    $sanitized[$field] = $value;
                }
            }
        }

        $has_tag_fields = isset($input['tag_head_scripts']) || isset($input['tag_body_scripts']) || 
                         isset($input['tag_footer_scripts']) || isset($input['tag_load_strategy']);
        
        if ($has_tag_fields) {
            // Sanitize tag settings
            $sanitized = $this->tag_settings->sanitize_settings($input, $sanitized);
        }
        
        // Check if Script Optimization tab was submitted
        $has_script_fields = isset($input['enable_default_exclusions']) || isset($input['script_exclusion_patterns']) || 
                            isset($input['script_load_strategy']) || isset($input['script_custom_delay']);
        
        if ($has_script_fields) {
            // Sanitize script settings
            $sanitized = $this->script_settings->sanitize_settings($input, $sanitized);
        }
        
        // Check if Advanced Optimization tab was submitted
        $has_advanced_fields = isset($input['script_wildcard_patterns']) || isset($input['script_regex_patterns']) || 
                              isset($input['script_plugin_profiles']) || isset($input['enable_event_hijacking']) ||
                              isset($input['event_hijack_triggers']) || isset($input['script_load_priority']);
        
        if ($has_advanced_fields) {
            // Sanitize advanced settings
            $sanitized = $this->advanced_settings->sanitize_settings($input, $sanitized);
        }
        
        // Clear cache when settings change
        Cache_Manager::clear_hero_cache();
        
        return $sanitized;
    }
}
