<?php
/**
 * Settings Registry - Field Registration
 *
 * Handles registration of settings sections and fields.
 * Follows Single Responsibility Principle - only concerned with registration logic.
 *
 * @package CoreBoost
 * @since 2.7.0
 */

namespace CoreBoost\Admin;

use CoreBoost\Core\Config;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Settings_Registry
 *
 * Responsible for:
 * - Registering settings with WordPress
 * - Adding settings sections
 * - Adding settings fields
 * - Managing field configurations
 */
class Settings_Registry {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Settings renderer instance
     *
     * @var Settings_Renderer
     */
    private $renderer;
    
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
     * @param Settings_Renderer $renderer Renderer instance
     */
    public function __construct($options, $renderer) {
        $this->options = $options;
        $this->renderer = $renderer;
        $this->tag_settings = new Tag_Settings($options);
        $this->script_settings = new Script_Settings($options);
        $this->advanced_settings = new Advanced_Optimization_Settings($options);
    }
    
    /**
     * Register all settings
     */
    public function register_all_settings($sanitizer_callback) {
        register_setting('coreboost_options_group', 'coreboost_options', $sanitizer_callback);
        
        // Register delegated settings
        $this->tag_settings->register_settings();
        $this->script_settings->register_settings();
        $this->advanced_settings->register_settings();
        
        // Register core settings sections
        $this->register_sections();
        
        // Register core settings fields
        $this->register_fields();
    }
    
    /**
     * Register settings sections
     */
    private function register_sections() {
        add_settings_section(
            'coreboost_hero_section',
            __('Hero Image & LCP Optimization', 'coreboost'),
            array($this->renderer, 'hero_section_callback'),
            'coreboost-hero'
        );
        
        add_settings_section(
            'coreboost_script_section',
            __('Script Optimization', 'coreboost'),
            array($this->renderer, 'script_section_callback'),
            'coreboost-scripts'
        );
        
        add_settings_section(
            'coreboost_css_section',
            __('CSS Optimization & Critical CSS', 'coreboost'),
            array($this->renderer, 'css_section_callback'),
            'coreboost-css'
        );
        
        add_settings_section(
            'coreboost_image_section',
            __('Image Optimization', 'coreboost'),
            array($this->renderer, 'image_section_callback'),
            'coreboost-images'
        );
        
        add_settings_section(
            'coreboost_advanced_section',
            __('Advanced Settings', 'coreboost'),
            array($this->renderer, 'advanced_section_callback'),
            'coreboost-advanced'
        );
    }
    
    /**
     * Register all settings fields
     */
    private function register_fields() {
        $this->register_hero_fields();
        $this->register_script_fields();
        $this->register_css_fields();
        $this->register_image_fields();
        $this->register_advanced_fields();
    }
    
    /**
     * Register Hero Image optimization fields
     */
    private function register_hero_fields() {
        add_settings_field('preload_method', __('Preload Method', 'coreboost'), array($this->renderer, 'preload_method_callback'), 'coreboost-hero', 'coreboost_hero_section');
        $this->register_dynamic_field('enable_responsive_preload', __('Responsive Preloading', 'coreboost'), 'coreboost-hero', 'coreboost_hero_section');
        $this->register_dynamic_field('enable_foreground_conversion', __('Enable Foreground CSS', 'coreboost'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('enable_hero_preload_extraction', __('Enable Hero Preload', 'coreboost'), array($this->renderer, 'enable_hero_preload_extraction_callback'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('hero_preload_cache_ttl', __('Hero Preload Cache TTL', 'coreboost'), array($this->renderer, 'hero_preload_cache_ttl_callback'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('specific_pages', __('Page-Specific Images', 'coreboost'), array($this->renderer, 'specific_pages_callback'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('lazy_load_exclude_count', __('Exclude First X Images from Lazy Load', 'coreboost'), array($this->renderer, 'lazy_load_exclude_count_callback'), 'coreboost-hero', 'coreboost_hero_section');
    }
    
    /**
     * Register Script optimization fields
     */
    private function register_script_fields() {
        $this->register_dynamic_field('enable_script_defer', __('Enable Script Deferring', 'coreboost'), 'coreboost-scripts', 'coreboost_script_section');
        $this->register_dynamic_field('scripts_to_defer', __('Scripts to Defer', 'coreboost'), 'coreboost-scripts', 'coreboost_script_section');
        $this->register_dynamic_field('scripts_to_async', __('Scripts to Load Async', 'coreboost'), 'coreboost-scripts', 'coreboost_script_section');
        $this->register_dynamic_field('exclude_scripts', __('Exclude Scripts', 'coreboost'), 'coreboost-scripts', 'coreboost_script_section');
    }
    
    /**
     * Register CSS optimization fields
     */
    private function register_css_fields() {
        $this->register_dynamic_field('enable_css_defer', __('Enable CSS Deferring', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('css_defer_method', __('CSS Defer Method', 'coreboost'), array($this->renderer, 'css_defer_method_callback'), 'coreboost-css', 'coreboost_css_section');
        $this->register_dynamic_field('styles_to_defer', __('Styles to Defer', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('enable_font_optimization', __('Enable Font Optimization', 'coreboost'), array($this->renderer, 'enable_font_optimization_callback'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('defer_google_fonts', __('Defer Google Fonts', 'coreboost'), array($this->renderer, 'defer_google_fonts_callback'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('defer_adobe_fonts', __('Defer Adobe Fonts', 'coreboost'), array($this->renderer, 'defer_adobe_fonts_callback'), 'coreboost-css', 'coreboost_css_section');
        $this->register_dynamic_field('preconnect_google_fonts', __('Preconnect to Google Fonts', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        $this->register_dynamic_field('preconnect_adobe_fonts', __('Preconnect to Adobe Fonts', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        $this->register_dynamic_field('font_display_swap', __('Font Display: Swap', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        $this->register_dynamic_field('critical_css_global', __('Global Critical CSS', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        $this->register_dynamic_field('critical_css_home', __('Homepage Critical CSS', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        $this->register_dynamic_field('critical_css_pages', __('Pages Critical CSS', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
        $this->register_dynamic_field('critical_css_posts', __('Posts Critical CSS', 'coreboost'), 'coreboost-css', 'coreboost_css_section');
    }
    
    /**
     * Register Image optimization fields
     */
    private function register_image_fields() {
        $this->register_dynamic_field('enable_image_optimization', __('Enable Image Optimization', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->register_dynamic_field('enable_lazy_loading', __('Enable Lazy Loading', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->register_dynamic_field('add_width_height_attributes', __('Add Width/Height Attributes', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->register_dynamic_field('generate_aspect_ratio_css', __('Generate Aspect Ratio CSS', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->register_dynamic_field('add_decoding_async', __('Add Decoding="async"', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->register_dynamic_field('enable_responsive_image_resizing', __('Generate Responsive Sizes (PSI Compliance)', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->register_dynamic_field('enable_image_format_conversion', __('Generate AVIF/WebP Variants', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->register_dynamic_field('avif_quality', __('AVIF Quality', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->register_dynamic_field('webp_quality', __('WebP Quality', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
        $this->register_dynamic_field('cleanup_orphans_weekly', __('Weekly Orphan Cleanup', 'coreboost'), 'coreboost-images', 'coreboost_image_section');
    }
    
    /**
     * Register Advanced settings fields
     */
    private function register_advanced_fields() {
        $this->register_dynamic_field('enable_caching', __('Enable Caching', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->register_dynamic_field('enable_unused_css_removal', __('Remove Unused CSS', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->register_dynamic_field('unused_css_list', __('Unused CSS Handles', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->register_dynamic_field('enable_unused_js_removal', __('Remove Unused JavaScript', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->register_dynamic_field('unused_js_list', __('Unused JS Handles', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->register_dynamic_field('enable_inline_script_removal', __('Remove Inline Scripts by ID', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->register_dynamic_field('inline_script_ids', __('Inline Script IDs', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->register_dynamic_field('enable_inline_style_removal', __('Remove Inline Styles by ID', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->register_dynamic_field('inline_style_ids', __('Inline Style IDs', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->register_dynamic_field('smart_youtube_blocking', __('Smart YouTube Blocking', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->register_dynamic_field('block_youtube_player_css', __('Block YouTube Player CSS', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->register_dynamic_field('block_youtube_embed_ui', __('Block YouTube Embed UI', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
    }
    
    /**
     * Register a dynamic field with configuration from Config
     *
     * @param string $field_name Field name
     * @param string $label Field label
     * @param string $page Settings page
     * @param string $section Settings section
     */
    private function register_dynamic_field($field_name, $label, $page, $section) {
        $config = Config::get_field_config();
        if (isset($config[$field_name])) {
            add_settings_field(
                $field_name,
                $label,
                array($this->renderer, 'render_field_callback'),
                $page,
                $section,
                array('field_name' => $field_name, 'config' => $config[$field_name])
            );
        }
    }
    
    /**
     * Get Tag Settings instance
     *
     * @return Tag_Settings
     */
    public function get_tag_settings() {
        return $this->tag_settings;
    }
    
    /**
     * Get Script Settings instance
     *
     * @return Script_Settings
     */
    public function get_script_settings() {
        return $this->script_settings;
    }
    
    /**
     * Get Advanced Settings instance
     *
     * @return Advanced_Optimization_Settings
     */
    public function get_advanced_settings() {
        return $this->advanced_settings;
    }
}
