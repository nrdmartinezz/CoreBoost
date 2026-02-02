<?php
/**
 * Settings page HTML rendering
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\Admin;

use CoreBoost\Core\Cache_Manager;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Settings_Page
 */
class Settings_Page {
    
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
        
        // Handle cache clearing early to prevent headers already sent
        add_action('admin_init', array($this, 'handle_cache_clearing'), 5);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $active_tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS);
        $active_tab = $active_tab ? sanitize_key($active_tab) : 'media';
        
        // Show settings updated message
        $this->show_settings_updated_message($active_tab);
        
        ?>
        <div class="wrap coreboost-optimizations">
            <div class="coreboost-page-header">
                <div class="coreboost-logo">
                    <span class="dashicons dashicons-admin-settings"></span>
                </div>
                <h1><?php _e('CoreBoost', 'coreboost'); ?> <span><?php _e('Optimizations', 'coreboost'); ?></span></h1>
            </div>
            
            <?php $this->render_tabs($active_tab); ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('coreboost_options_group'); ?>
                <?php wp_nonce_field('coreboost_bulk_converter', 'coreboost_nonce'); ?>
                
                <!-- Hidden fields to preserve all settings -->
                <?php $this->output_hidden_fields($active_tab); ?>
                
                <input type="hidden" name="current_tab" value="<?php echo esc_attr($active_tab); ?>">
                
                <?php if ($active_tab == 'media'): ?>
                    <?php $this->render_media_tab(); ?>
                <?php elseif ($active_tab == 'performance'): ?>
                    <?php $this->render_performance_tab(); ?>
                <?php elseif ($active_tab == 'advanced'): ?>
                    <?php $this->render_advanced_tab(); ?>
                <?php endif; ?>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render navigation tabs
     *
     * @param string $active_tab Current active tab
     */
    private function render_tabs($active_tab) {
        ?>
        <nav class="nav-tab-wrapper">
            <a href="?page=coreboost-optimizations&tab=media" class="nav-tab <?php echo $active_tab == 'media' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-format-image"></span>
                <?php _e('Media', 'coreboost'); ?>
            </a>
            <a href="?page=coreboost-optimizations&tab=performance" class="nav-tab <?php echo $active_tab == 'performance' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-performance"></span>
                <?php _e('Performance', 'coreboost'); ?>
            </a>
            <a href="?page=coreboost-optimizations&tab=advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('Advanced', 'coreboost'); ?>
            </a>
        </nav>
        <?php
    }
    
    /**
     * Render Media tab content
     */
    private function render_media_tab() {
        ?>
        <!-- Hero/LCP Section -->
        <div class="coreboost-collapsible-section" data-section="hero">
            <h3 class="coreboost-section-header">
                <span class="dashicons dashicons-images-alt2"></span>
                <?php _e('Hero Images & LCP', 'coreboost'); ?>
                <span class="coreboost-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
            </h3>
            <div class="coreboost-section-content">
                <?php do_settings_sections('coreboost-hero'); ?>
            </div>
        </div>
        
        <!-- Image Optimization Section -->
        <div class="coreboost-collapsible-section" data-section="images">
            <h3 class="coreboost-section-header">
                <span class="dashicons dashicons-format-gallery"></span>
                <?php _e('Image Processing', 'coreboost'); ?>
                <span class="coreboost-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
            </h3>
            <div class="coreboost-section-content">
                <?php do_settings_sections('coreboost-images'); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Performance tab content
     */
    private function render_performance_tab() {
        ?>
        <!-- Scripts Section -->
        <div class="coreboost-collapsible-section" data-section="scripts">
            <h3 class="coreboost-section-header">
                <span class="dashicons dashicons-editor-code"></span>
                <?php _e('Script Optimization', 'coreboost'); ?>
                <span class="coreboost-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
            </h3>
            <div class="coreboost-section-content">
                <?php do_settings_sections('coreboost-scripts'); ?>
            </div>
        </div>
        
        <!-- CSS Section -->
        <div class="coreboost-collapsible-section" data-section="css">
            <h3 class="coreboost-section-header">
                <span class="dashicons dashicons-admin-appearance"></span>
                <?php _e('CSS & Fonts', 'coreboost'); ?>
                <span class="coreboost-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
            </h3>
            <div class="coreboost-section-content">
                <?php do_settings_sections('coreboost-css'); ?>
            </div>
        </div>
        
        <!-- Custom Tags Section -->
        <div class="coreboost-collapsible-section" data-section="tags">
            <h3 class="coreboost-section-header">
                <span class="dashicons dashicons-tag"></span>
                <?php _e('Custom Tags', 'coreboost'); ?>
                <span class="coreboost-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
            </h3>
            <div class="coreboost-section-content">
                <?php do_settings_sections('coreboost-tags'); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Advanced tab content
     */
    private function render_advanced_tab() {
        ?>
        <!-- Resource Removal Section -->
        <div class="coreboost-collapsible-section" data-section="removal">
            <h3 class="coreboost-section-header">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Resource Removal', 'coreboost'); ?>
                <span class="coreboost-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
            </h3>
            <div class="coreboost-section-content">
                <?php do_settings_sections('coreboost-advanced'); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Output hidden fields to preserve settings from other tabs
     *
     * @param string $active_tab Current active tab
     */
    private function output_hidden_fields($active_tab) {
        $all_fields = array(
            'media' => array(
                'preload_method', 'enable_responsive_preload', 'enable_foreground_conversion', 
                'enable_hero_preload_extraction', 'hero_preload_cache_ttl', 'specific_pages', 'lazy_load_exclude_count',
                'enable_image_optimization', 'enable_lazy_loading', 'add_width_height_attributes', 
                'generate_aspect_ratio_css', 'add_decoding_async', 'enable_responsive_image_resizing', 
                'enable_image_format_conversion', 'avif_quality', 'webp_quality', 'cleanup_orphans_weekly'
            ),
            'performance' => array(
                'enable_script_defer', 'scripts_to_defer', 'scripts_to_async', 'exclude_scripts',
                'enable_css_defer', 'css_defer_method', 'styles_to_defer', 'enable_font_optimization', 
                'defer_google_fonts', 'defer_adobe_fonts', 'preconnect_google_fonts', 'preconnect_adobe_fonts', 'font_display_swap',
                'critical_css_global', 'critical_css_home', 'critical_css_pages', 'critical_css_posts',
                'tag_head_scripts', 'tag_body_scripts', 'tag_footer_scripts', 'tag_load_strategy', 'tag_custom_delay'
            ),
            'advanced' => array(
                'enable_caching', 'enable_unused_css_removal', 'unused_css_list', 'enable_unused_js_removal', 
                'unused_js_list', 'enable_inline_script_removal', 'inline_script_ids', 
                'enable_inline_style_removal', 'inline_style_ids', 'smart_youtube_blocking',
                'block_youtube_player_css', 'block_youtube_embed_ui'
            )
        );
        
        // Output hidden fields for all tabs except the active one
        foreach ($all_fields as $tab => $fields) {
            if ($tab !== $active_tab) {
                foreach ($fields as $field) {
                    $value = isset($this->options[$field]) ? $this->options[$field] : '';
                    
                    if (is_bool($value)) {
                        $value = $value ? '1' : '';
                    }
                    
                    echo '<input type="hidden" name="coreboost_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '">' . "\n";
                }
            }
        }
    }
    
    /**
     * Handle cache clearing
     * Called early via admin_init to prevent headers already sent error
     */
    public function handle_cache_clearing() {
        // Only check on our settings pages
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!in_array($page, array('coreboost', 'coreboost-optimizations'))) {
            return;
        }
        
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
        $nonce_get = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        
        if ($action === 'clear_cache' && $nonce_get && wp_verify_nonce($nonce_get, 'coreboost_clear_cache')) {
            Cache_Manager::flush_all_caches();
            
            // Redirect to show success message
            $redirect_url = remove_query_arg(array('action', '_wpnonce'));
            wp_redirect(add_query_arg('coreboost_cache_cleared', '1', $redirect_url));
            exit;
        }
        
        if ($action === 'clear_hero_preload_cache' && $nonce_get && wp_verify_nonce($nonce_get, 'coreboost_clear_hero_preload_cache')) {
            // Clear all hero preload cache entries
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE 'coreboost_hero_preload_%' 
                OR option_name LIKE '_transient_coreboost_hero_preload_%'"
            );
            
            // Redirect to show success message
            $redirect_url = remove_query_arg(array('action', '_wpnonce'));
            wp_redirect(add_query_arg('coreboost_hero_cache_cleared', '1', $redirect_url));
            exit;
        }
    }
    
    /**
     * Show settings updated message
     *
     * @param string $active_tab Current active tab
     */
    private function show_settings_updated_message($active_tab) {
        $settings_updated = filter_input(INPUT_GET, 'settings-updated', FILTER_SANITIZE_SPECIAL_CHARS);
        $cache_cleared = filter_input(INPUT_GET, 'coreboost_cache_cleared', FILTER_SANITIZE_SPECIAL_CHARS);
        $hero_cache_cleared = filter_input(INPUT_GET, 'coreboost_hero_cache_cleared', FILTER_SANITIZE_SPECIAL_CHARS);
        
        if ($settings_updated === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'coreboost') . '</p></div>';
            
            // JavaScript to redirect to correct tab if needed
            $current_tab = filter_input(INPUT_POST, 'current_tab', FILTER_SANITIZE_SPECIAL_CHARS);
            if ($current_tab && $current_tab !== $active_tab) {
                echo '<script>
                    if (window.history.replaceState) {
                        var newUrl = window.location.href.replace(/&tab=[^&]*/, "") + "&tab=' . esc_js(sanitize_key($current_tab)) . '";
                        window.history.replaceState(null, null, newUrl);
                        window.location.reload();
                    }
                </script>';
            }
        }
        
        if ($cache_cleared === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('All caches cleared successfully!', 'coreboost') . '</p></div>';
        }
        
        if ($hero_cache_cleared === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Hero preload cache cleared successfully!', 'coreboost') . '</p></div>';
        }
    }
}
