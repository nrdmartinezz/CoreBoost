<?php
/**
 * Settings Sanitizer - Input Validation and Sanitization
 *
 * Handles sanitization and validation of settings input.
 * Follows Single Responsibility Principle - only concerned with data validation.
 *
 * @package CoreBoost
 * @since 2.7.0
 */

namespace CoreBoost\Admin;

use CoreBoost\Core\Cache_Manager;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Settings_Sanitizer
 *
 * Responsible for:
 * - Input sanitization
 * - Data validation
 * - Type enforcement
 * - Business rule validation
 */
class Settings_Sanitizer {
    
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
     * @param Tag_Settings $tag_settings
     * @param Script_Settings $script_settings
     * @param Advanced_Optimization_Settings $advanced_settings
     */
    public function __construct($tag_settings, $script_settings, $advanced_settings) {
        $this->tag_settings = $tag_settings;
        $this->script_settings = $script_settings;
        $this->advanced_settings = $advanced_settings;
    }
    
    /**
     * Sanitize all options
     *
     * @param array $input Raw input from form
     * @return array Sanitized options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        // Get current options to preserve values not in current form
        $current_options = get_option('coreboost_options', array());
        
        // Start with current options
        $sanitized = $current_options;
        
        // Detect which tab submitted the form
        $form_context = $this->detect_form_context($input);
        
        // Sanitize basic fields
        $this->sanitize_basic_fields($input, $sanitized);
        
        // Sanitize typed fields
        $this->sanitize_typed_fields($input, $sanitized, $form_context);
        
        // Sanitize delegated settings
        $this->sanitize_delegated_settings($input, $sanitized, $form_context);
        
        // Clear cache when settings change
        Cache_Manager::clear_hero_cache();
        
        return $sanitized;
    }
    
    /**
     * Detect which form/tab submitted the data
     *
     * @param array $input Form input
     * @return array Form context flags
     */
    private function detect_form_context($input) {
        return array(
            'hero' => isset($input['preload_method']) || isset($input['specific_pages']) || isset($input['enable_hero_preload_extraction']) || isset($input['hero_preload_cache_ttl']),
            'script' => isset($input['scripts_to_defer']) || isset($input['scripts_to_async']) || isset($input['exclude_scripts']),
            'css' => isset($input['styles_to_defer']) || isset($input['critical_css_global']) || isset($input['css_defer_method']),
            'image' => isset($input['enable_image_optimization']) || isset($input['enable_lazy_loading']) || isset($input['add_width_height_attributes']) || isset($input['generate_aspect_ratio_css']) || isset($input['add_decoding_async']),
            'advanced' => isset($input['unused_css_list']) || isset($input['unused_js_list']) || isset($input['inline_script_ids']) || isset($input['inline_style_ids']),
            'tag' => isset($input['tag_head_scripts']) || isset($input['tag_body_scripts']) || isset($input['tag_footer_scripts']) || isset($input['tag_load_strategy']),
            'script_advanced' => isset($input['enable_default_exclusions']) || isset($input['script_exclusion_patterns']) || isset($input['script_load_strategy']) || isset($input['script_custom_delay']),
            'optimization_advanced' => isset($input['script_wildcard_patterns']) || isset($input['script_regex_patterns']) || isset($input['script_plugin_profiles']) || isset($input['enable_event_hijacking']) || isset($input['event_hijack_triggers']) || isset($input['script_load_priority'])
        );
    }
    
    /**
     * Sanitize basic single-value fields
     *
     * @param array $input Form input
     * @param array &$sanitized Sanitized output (modified by reference)
     */
    private function sanitize_basic_fields($input, &$sanitized) {
        if (isset($input['preload_method'])) {
            $sanitized['preload_method'] = sanitize_text_field($input['preload_method']);
        }
        
        if (isset($input['lazy_load_exclude_count'])) {
            $sanitized['lazy_load_exclude_count'] = absint($input['lazy_load_exclude_count']);
        }

        if (isset($input['hero_preload_cache_ttl'])) {
            $sanitized['hero_preload_cache_ttl'] = absint($input['hero_preload_cache_ttl']);
        }
        
        if (isset($input['css_defer_method'])) {
            $sanitized['css_defer_method'] = sanitize_text_field($input['css_defer_method']);
        }
    }
    
    /**
     * Sanitize typed fields (boolean, textarea, CSS, integer)
     *
     * @param array $input Form input
     * @param array &$sanitized Sanitized output (modified by reference)
     * @param array $form_context Form context flags
     */
    private function sanitize_typed_fields($input, &$sanitized, $form_context) {
        // Define field types
        $field_types = $this->get_field_type_mapping();
        
        // Sanitize boolean fields
        foreach ($field_types['boolean'] as $field) {
            if (array_key_exists($field, $input)) {
                $sanitized[$field] = !empty($input[$field]);
            } else {
                // Only set to false if this field is from the current form tab
                if ($this->is_field_in_current_form($field, $form_context)) {
                    $sanitized[$field] = false;
                }
            }
        }
        
        // Sanitize textarea fields
        foreach ($field_types['textarea'] as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_textarea_field($input[$field]);
            } else {
                // Only clear if this field is from the current form tab
                if ($this->is_field_in_current_form($field, $form_context)) {
                    $sanitized[$field] = '';
                }
            }
        }
        
        // Sanitize CSS fields (allow CSS syntax)
        foreach ($field_types['css'] as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = wp_strip_all_tags($input[$field]);
            } else {
                // Only clear if this field is from the current form tab
                if ($this->is_field_in_current_form($field, $form_context)) {
                    $sanitized[$field] = '';
                }
            }
        }
    }
    
    /**
     * Get field type mapping
     *
     * @return array Field type mapping
     */
    private function get_field_type_mapping() {
        return array(
            'boolean' => array(
                'enable_script_defer', 'enable_css_defer', 'enable_foreground_conversion', 
                'enable_responsive_preload', 'enable_hero_preload_extraction', 'enable_caching', 
                'enable_font_optimization', 'font_display_swap', 'defer_google_fonts', 'defer_adobe_fonts', 
                'preconnect_google_fonts', 'preconnect_adobe_fonts', 'enable_unused_css_removal',
                'enable_unused_js_removal', 'enable_inline_script_removal', 'enable_inline_style_removal',
                'smart_youtube_blocking', 'smart_video_facades', 'block_youtube_player_css', 'block_youtube_embed_ui',
                'enable_image_optimization', 'enable_lazy_loading', 'add_width_height_attributes',
                'generate_aspect_ratio_css', 'add_decoding_async'
            ),
            'textarea' => array(
                'scripts_to_defer', 'scripts_to_async', 'styles_to_defer', 'exclude_scripts', 'specific_pages',
                'unused_css_list', 'unused_js_list', 'inline_script_ids', 'inline_style_ids'
            ),
            'css' => array(
                'critical_css_global', 'critical_css_home', 'critical_css_pages', 'critical_css_posts'
            )
        );
    }
    
    /**
     * Check if field belongs to current form context
     *
     * @param string $field Field name
     * @param array $form_context Form context flags
     * @return bool
     */
    private function is_field_in_current_form($field, $form_context) {
        $field_map = array(
            'script' => array('enable_script_defer'),
            'css' => array('enable_css_defer', 'enable_font_optimization', 'font_display_swap', 'defer_google_fonts', 'defer_adobe_fonts', 'preconnect_google_fonts', 'preconnect_adobe_fonts'),
            'hero' => array('enable_responsive_preload', 'enable_foreground_conversion', 'enable_hero_preload_extraction'),
            'advanced' => array('enable_caching', 'enable_unused_css_removal', 'enable_unused_js_removal', 'enable_inline_script_removal', 'enable_inline_style_removal', 'smart_youtube_blocking', 'block_youtube_player_css', 'block_youtube_embed_ui'),
            'image' => array('enable_image_optimization', 'enable_lazy_loading', 'add_width_height_attributes', 'generate_aspect_ratio_css', 'add_decoding_async')
        );
        
        foreach ($field_map as $context => $fields) {
            if ($form_context[$context] && in_array($field, $fields)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sanitize delegated settings (Tag, Script, Advanced)
     *
     * @param array $input Form input
     * @param array &$sanitized Sanitized output (modified by reference)
     * @param array $form_context Form context flags
     */
    private function sanitize_delegated_settings($input, &$sanitized, $form_context) {
        // Sanitize tag settings if present
        if ($form_context['tag']) {
            $sanitized = $this->tag_settings->sanitize_settings($input, $sanitized);
        }
        
        // Sanitize script settings if present
        if ($form_context['script_advanced']) {
            $sanitized = $this->script_settings->sanitize_settings($input, $sanitized);
        }
        
        // Sanitize advanced optimization settings if present
        if ($form_context['optimization_advanced']) {
            $sanitized = $this->advanced_settings->sanitize_settings($input, $sanitized);
        }
    }
}
