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
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $active_tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS);
        $active_tab = $active_tab ? sanitize_key($active_tab) : 'hero';
        
        // Handle cache clearing
        $this->handle_cache_clearing();
        
        // Show settings updated message
        $this->show_settings_updated_message($active_tab);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('CoreBoost', 'coreboost'); ?></strong> - <?php _e('Comprehensive performance optimization for WordPress sites with advanced CSS deferring and LCP optimization.', 'coreboost'); ?></p>
            </div>
            
            <?php $this->render_tabs($active_tab); ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('coreboost_options_group'); ?>
                
                <!-- Hidden fields to preserve all settings -->
                <?php $this->output_hidden_fields($active_tab); ?>
                
                <?php if ($active_tab == 'hero'): ?>
                    <?php do_settings_sections('coreboost-hero'); ?>
                <?php elseif ($active_tab == 'scripts'): ?>
                    <?php do_settings_sections('coreboost-scripts'); ?>
                <?php elseif ($active_tab == 'css'): ?>
                    <?php do_settings_sections('coreboost-css'); ?>
                <?php elseif ($active_tab == 'advanced'): ?>
                    <?php do_settings_sections('coreboost-advanced'); ?>
                <?php endif; ?>
                
                <?php submit_button(); ?>
            </form>
            
            <?php $this->render_info_box($active_tab); ?>
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
        <h2 class="nav-tab-wrapper">
            <a href="?page=coreboost&tab=hero" class="nav-tab <?php echo $active_tab == 'hero' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Hero Images & LCP', 'coreboost'); ?>
            </a>
            <a href="?page=coreboost&tab=scripts" class="nav-tab <?php echo $active_tab == 'scripts' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Script Optimization', 'coreboost'); ?>
            </a>
            <a href="?page=coreboost&tab=css" class="nav-tab <?php echo $active_tab == 'css' ? 'nav-tab-active' : ''; ?>">
                <?php _e('CSS & Critical CSS', 'coreboost'); ?>
            </a>
            <a href="?page=coreboost&tab=advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Advanced Settings', 'coreboost'); ?>
            </a>
        </h2>
        <?php
    }
    
    /**
     * Render info box for current tab
     *
     * @param string $active_tab Current active tab
     */
    private function render_info_box($active_tab) {
        ?>
        <div class="coreboost-info-box" style="background: #f1f1f1; padding: 20px; margin-top: 20px; border-radius: 5px;">
            <?php if ($active_tab == 'hero'): ?>
                <h3><?php _e('Hero Image Optimization Guide', 'coreboost'); ?></h3>
                <ul>
                    <li><strong><?php _e('Auto-detect from Elementor Data:', 'coreboost'); ?></strong> <?php _e('Best for most Elementor sites', 'coreboost'); ?></li>
                    <li><strong><?php _e('Featured Image with Fallback:', 'coreboost'); ?></strong> <?php _e('Good for mixed content types', 'coreboost'); ?></li>
                    <li><strong><?php _e('Smart Detection:', 'coreboost'); ?></strong> <?php _e('Manual override + auto-detection', 'coreboost'); ?></li>
                    <li><strong><?php _e('Advanced with Caching:', 'coreboost'); ?></strong> <?php _e('Best for high-traffic sites', 'coreboost'); ?></li>
                </ul>
            <?php elseif ($active_tab == 'scripts'): ?>
                <h3><?php _e('Script Optimization Tips', 'coreboost'); ?></h3>
                <p><strong><?php _e('Common scripts to defer:', 'coreboost'); ?></strong></p>
                <code>contact-form-7<br>wc-cart-fragments<br>elementor-frontend</code>
                <p><strong><?php _e('Never defer these scripts:', 'coreboost'); ?></strong></p>
                <code>jquery-core<br>jquery-migrate<br>jquery</code>
            <?php elseif ($active_tab == 'css'): ?>
                <h3><?php _e('Critical CSS Guide', 'coreboost'); ?></h3>
                <ul>
                    <li><?php _e('Use online tools like Critical CSS Generator to extract critical styles', 'coreboost'); ?></li>
                    <li><?php _e('Include only styles needed for above-the-fold content', 'coreboost'); ?></li>
                    <li><?php _e('Global critical CSS is applied to all pages', 'coreboost'); ?></li>
                    <li><?php _e('Page-specific CSS is additional to global CSS', 'coreboost'); ?></li>
                    <li><?php _e('Test thoroughly after enabling CSS deferring', 'coreboost'); ?></li>
                </ul>
            <?php elseif ($active_tab == 'advanced'): ?>
                <h3><?php _e('Cache Management', 'coreboost'); ?></h3>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=coreboost&tab=advanced&action=clear_cache'), 'coreboost_clear_cache'); ?>" class="button">
                        <?php _e('Clear All Caches', 'coreboost'); ?>
                    </a>
                </p>
                <h4><?php _e('Debug Mode', 'coreboost'); ?></h4>
                <p><?php _e('Enable debug mode to see HTML comments showing which optimizations are applied. Disable on production sites.', 'coreboost'); ?></p>
            <?php endif; ?>
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
            'hero' => array('preload_method', 'enable_responsive_preload', 'enable_foreground_conversion', 'specific_pages', 'lazy_load_exclude_count'),
            'scripts' => array('enable_script_defer', 'scripts_to_defer', 'scripts_to_async', 'exclude_scripts'),
            'css' => array('enable_css_defer', 'css_defer_method', 'styles_to_defer', 'enable_font_optimization', 
                          'defer_google_fonts', 'defer_adobe_fonts', 'preconnect_google_fonts', 'preconnect_adobe_fonts',
                          'font_display_swap', 'critical_css_global', 'critical_css_home', 'critical_css_pages', 'critical_css_posts'),
            'advanced' => array('enable_caching', 'enable_unused_css_removal', 'unused_css_list', 'enable_unused_js_removal', 
                               'unused_js_list', 'enable_inline_script_removal', 'inline_script_ids', 
                               'enable_inline_style_removal', 'inline_style_ids', 'block_youtube_player_css', 
                               'block_youtube_embed_ui', 'debug_mode')
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
     */
    private function handle_cache_clearing() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
        $nonce_get = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        
        if ($action === 'clear_cache' && $nonce_get && wp_verify_nonce($nonce_get, 'coreboost_clear_cache')) {
            Cache_Manager::flush_all_caches();
            
            // Redirect to show success message
            $redirect_url = remove_query_arg(array('action', '_wpnonce'));
            wp_redirect(add_query_arg('coreboost_cache_cleared', '1', $redirect_url));
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
    }
}
