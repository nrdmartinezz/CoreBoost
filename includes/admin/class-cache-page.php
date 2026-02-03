<?php
/**
 * Cache Page for CoreBoost Admin
 *
 * Displays cache controls, settings, and third-party integrations.
 *
 * @package CoreBoost
 * @since 3.1.0
 */

namespace CoreBoost\Admin;

use CoreBoost\Core\Cache_Manager;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Cache_Page
 */
class Cache_Page {
    
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
        
        // Handle cache actions early to avoid headers already sent
        add_action('admin_init', array($this, 'early_handle_cache_actions'), 5);
    }
    
    /**
     * Handle cache actions early (before headers sent)
     */
    public function early_handle_cache_actions() {
        // Only run on our cache page
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($page !== 'coreboost-cache') {
            return;
        }
        
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
        $nonce = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        
        if (!$action || !$nonce) {
            return;
        }
        
        $redirect_args = array();
        
        switch ($action) {
            case 'clear_all':
                if (wp_verify_nonce($nonce, 'coreboost_clear_all')) {
                    Cache_Manager::flush_all_caches();
                    $redirect_args['all_cleared'] = '1';
                }
                break;
                
            case 'clear_hero':
                if (wp_verify_nonce($nonce, 'coreboost_clear_hero')) {
                    Cache_Manager::clear_hero_cache();
                    $redirect_args['hero_cleared'] = '1';
                }
                break;
                
            case 'clear_video':
                if (wp_verify_nonce($nonce, 'coreboost_clear_video')) {
                    Cache_Manager::clear_video_cache();
                    $redirect_args['video_cleared'] = '1';
                }
                break;
                
            case 'clear_third_party':
                if (wp_verify_nonce($nonce, 'coreboost_clear_third_party')) {
                    Cache_Manager::clear_third_party_caches();
                    $redirect_args['third_party_cleared'] = '1';
                }
                break;
        }
        
        if (!empty($redirect_args)) {
            wp_safe_redirect(add_query_arg($redirect_args, remove_query_arg(array('action', '_wpnonce'))));
            exit;
        }
    }
    
    /**
     * Render the cache page
     */
    public function render() {
        $this->handle_cache_actions();
        
        ?>
        <div class="wrap coreboost-cache">
            <div class="coreboost-page-header">
                <div class="coreboost-logo">
                    <span class="dashicons dashicons-performance"></span>
                </div>
                <h1><?php _e('CoreBoost', 'coreboost'); ?> <span><?php _e('Cache', 'coreboost'); ?></span></h1>
            </div>
            
            <?php $this->render_notices(); ?>
            
            <!-- Cache Status -->
            <div class="coreboost-cache-section">
                <h2><span class="dashicons dashicons-dashboard"></span> <?php _e('Cache Status', 'coreboost'); ?></h2>
                <div class="coreboost-cache-status">
                    <?php $this->render_cache_status(); ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="coreboost-cache-section">
                <h2><span class="dashicons dashicons-update"></span> <?php _e('Cache Actions', 'coreboost'); ?></h2>
                <div class="coreboost-cache-actions">
                    <?php $this->render_cache_actions(); ?>
                </div>
            </div>
            
            <!-- Cache Settings -->
            <div class="coreboost-cache-section">
                <h2><span class="dashicons dashicons-admin-generic"></span> <?php _e('Cache Settings', 'coreboost'); ?></h2>
                <form method="post" action="options.php">
                    <?php 
                    settings_fields('coreboost_options_group');
                    $this->render_cache_settings();
                    submit_button(__('Save Settings', 'coreboost'));
                    ?>
                </form>
            </div>
            
            <!-- Third-Party Integrations -->
            <div class="coreboost-cache-section">
                <h2><span class="dashicons dashicons-plugins-checked"></span> <?php _e('Third-Party Cache Integrations', 'coreboost'); ?></h2>
                <p class="description"><?php _e('CoreBoost automatically clears these caches when you clear CoreBoost cache.', 'coreboost'); ?></p>
                <div class="coreboost-integrations">
                    <?php $this->render_integrations(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render cache status
     */
    private function render_cache_status() {
        $caching_enabled = !empty($this->options['enable_caching']);
        $indicator_class = $caching_enabled ? 'enabled' : 'disabled';
        $icon = $caching_enabled ? 'dashicons-yes' : 'dashicons-no';
        $status_text = $caching_enabled ? __('Caching Enabled', 'coreboost') : __('Caching Disabled', 'coreboost');
        $description = $caching_enabled 
            ? __('CoreBoost caching is active and optimizing your site.', 'coreboost')
            : __('Enable caching to improve performance.', 'coreboost');
        ?>
        <div class="coreboost-cache-indicator <?php echo esc_attr($indicator_class); ?>">
            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
        </div>
        <div class="coreboost-cache-info">
            <h3><?php echo esc_html($status_text); ?></h3>
            <p><?php echo esc_html($description); ?></p>
        </div>
        <?php
    }
    
    /**
     * Render cache actions
     */
    private function render_cache_actions() {
        $clear_all_url = wp_nonce_url(
            admin_url('admin.php?page=coreboost-cache&action=clear_all'),
            'coreboost_clear_all'
        );
        
        $clear_hero_url = wp_nonce_url(
            admin_url('admin.php?page=coreboost-cache&action=clear_hero'),
            'coreboost_clear_hero'
        );
        
        $clear_video_url = wp_nonce_url(
            admin_url('admin.php?page=coreboost-cache&action=clear_video'),
            'coreboost_clear_video'
        );
        
        $clear_third_party_url = wp_nonce_url(
            admin_url('admin.php?page=coreboost-cache&action=clear_third_party'),
            'coreboost_clear_third_party'
        );
        ?>
        <a href="<?php echo esc_url($clear_all_url); ?>" class="button button-primary">
            <span class="dashicons dashicons-trash"></span>
            <?php _e('Clear All CoreBoost Caches', 'coreboost'); ?>
        </a>
        
        <a href="<?php echo esc_url($clear_hero_url); ?>" class="button">
            <span class="dashicons dashicons-images-alt2"></span>
            <?php _e('Clear Hero Image Cache', 'coreboost'); ?>
        </a>
        
        <a href="<?php echo esc_url($clear_video_url); ?>" class="button">
            <span class="dashicons dashicons-video-alt3"></span>
            <?php _e('Clear Video Cache', 'coreboost'); ?>
        </a>
        
        <a href="<?php echo esc_url($clear_third_party_url); ?>" class="button">
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php _e('Clear Third-Party Caches', 'coreboost'); ?>
        </a>
        <?php
    }
    
    /**
     * Render cache settings
     */
    private function render_cache_settings() {
        $caching_enabled = !empty($this->options['enable_caching']);
        $hero_ttl = isset($this->options['hero_preload_cache_ttl']) ? $this->options['hero_preload_cache_ttl'] : '2592000';
        ?>
        <table class="coreboost-cache-settings-table">
            <tr>
                <th><label for="enable_caching"><?php _e('Enable Caching', 'coreboost'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="enable_caching" 
                               name="coreboost_options[enable_caching]" 
                               value="1" 
                               <?php checked($caching_enabled); ?>>
                        <?php _e('Enable CoreBoost internal caching', 'coreboost'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Caches hero image detection, variant mappings, and optimization results for faster page loads.', 'coreboost'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="hero_preload_cache_ttl"><?php _e('Hero Cache TTL', 'coreboost'); ?></label></th>
                <td>
                    <select id="hero_preload_cache_ttl" name="coreboost_options[hero_preload_cache_ttl]">
                        <option value="86400" <?php selected($hero_ttl, '86400'); ?>><?php _e('1 Day', 'coreboost'); ?></option>
                        <option value="604800" <?php selected($hero_ttl, '604800'); ?>><?php _e('7 Days', 'coreboost'); ?></option>
                        <option value="2592000" <?php selected($hero_ttl, '2592000'); ?>><?php _e('30 Days', 'coreboost'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('How long to cache hero image detection results.', 'coreboost'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <!-- Hidden fields to preserve other options -->
        <?php
        $preserve_fields = array(
            'preload_method', 'enable_responsive_preload', 'enable_foreground_conversion',
            'enable_hero_preload_extraction', 'specific_pages', 'lazy_load_exclude_count',
            'enable_script_defer', 'scripts_to_defer', 'scripts_to_async', 'exclude_scripts',
            'enable_css_defer', 'css_defer_method', 'styles_to_defer', 'enable_font_optimization',
            'defer_google_fonts', 'defer_adobe_fonts', 'preconnect_google_fonts', 'preconnect_adobe_fonts',
            'font_display_swap', 'critical_css_global', 'critical_css_home', 'critical_css_pages', 'critical_css_posts',
            'enable_image_optimization', 'enable_lazy_loading', 'add_width_height_attributes',
            'generate_aspect_ratio_css', 'add_decoding_async',
            'tag_head_scripts', 'tag_body_scripts', 'tag_footer_scripts', 'tag_load_strategy', 'tag_custom_delay',
            'enable_unused_css_removal', 'unused_css_list', 'enable_unused_js_removal', 'unused_js_list',
            'enable_inline_script_removal', 'inline_script_ids', 'enable_inline_style_removal', 'inline_style_ids',
            'smart_youtube_blocking', 'block_youtube_player_css', 'block_youtube_embed_ui'
        );
        
        foreach ($preserve_fields as $field) {
            $value = isset($this->options[$field]) ? $this->options[$field] : '';
            if (is_bool($value)) {
                $value = $value ? '1' : '';
            }
            echo '<input type="hidden" name="coreboost_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '">' . "\n";
        }
    }
    
    /**
     * Render third-party integrations
     */
    private function render_integrations() {
        $integrations = array(
            array(
                'name' => 'WP Rocket',
                'slug' => 'wp-rocket',
                'active' => function_exists('rocket_clean_domain'),
            ),
            array(
                'name' => 'W3 Total Cache',
                'slug' => 'w3tc',
                'active' => function_exists('w3tc_flush_all'),
            ),
            array(
                'name' => 'LiteSpeed Cache',
                'slug' => 'litespeed',
                'active' => class_exists('LiteSpeed_Cache_API'),
            ),
            array(
                'name' => 'Autoptimize',
                'slug' => 'autoptimize',
                'active' => class_exists('autoptimizeCache'),
            ),
            array(
                'name' => 'WP Super Cache',
                'slug' => 'wpsc',
                'active' => function_exists('wp_cache_clear_cache'),
            ),
            array(
                'name' => 'SG Optimizer',
                'slug' => 'sgo',
                'active' => function_exists('sg_cachepress_purge_cache'),
            ),
        );
        
        foreach ($integrations as $integration) {
            $status_class = $integration['active'] ? 'active' : 'inactive';
            $status_text = $integration['active'] ? __('Detected', 'coreboost') : __('Not Detected', 'coreboost');
            ?>
            <div class="coreboost-integration-item <?php echo esc_attr($status_class); ?>">
                <div class="coreboost-integration-icon">
                    <?php echo esc_html(strtoupper(substr($integration['slug'], 0, 2))); ?>
                </div>
                <div class="coreboost-integration-info">
                    <h4><?php echo esc_html($integration['name']); ?></h4>
                    <span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Handle cache actions (legacy - now handled in early_handle_cache_actions)
     */
    private function handle_cache_actions() {
        // Actions are now handled early via admin_init hook
        // This method is kept for backwards compatibility
    }
    
    /**
     * Render notices
     */
    private function render_notices() {
        $notices = array(
            'all_cleared' => __('All CoreBoost caches cleared successfully!', 'coreboost'),
            'hero_cleared' => __('Hero image cache cleared successfully!', 'coreboost'),
            'video_cleared' => __('Video cache cleared successfully!', 'coreboost'),
            'third_party_cleared' => __('Third-party caches cleared successfully!', 'coreboost'),
        );
        
        foreach ($notices as $key => $message) {
            if (filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS) === '1') {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }
}
