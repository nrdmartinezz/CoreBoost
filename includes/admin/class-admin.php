<?php
/**
 * Admin area functionality
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\Admin;

use CoreBoost\Core\Config;
use CoreBoost\Core\Cache_Manager;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Admin
 */
class Admin {
    
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
     * Settings instance
     *
     * @var Settings
     */
    private $settings;
    
    /**
     * Settings page instance
     *
     * @var Settings_Page
     */
    private $settings_page;
    
    /**
     * Admin bar instance
     *
     * @var Admin_Bar
     */
    private $admin_bar;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        
        $this->load_dependencies();
        $this->define_hooks();
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        $this->settings = new Settings($this->options);
        $this->settings_page = new Settings_Page($this->options);
        $this->admin_bar = new Admin_Bar($this->options);
    }
    
    /**
     * Define admin hooks
     */
    private function define_hooks() {
        // Admin menu
        $this->loader->add_action('admin_menu', $this, 'add_admin_menu');
        
        // Admin init for settings
        $this->loader->add_action('admin_init', $this->settings, 'register_settings');
        
        // Admin scripts
        $this->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_admin_scripts');
        
        // Admin bar
        $this->loader->add_action('admin_bar_menu', $this->admin_bar, 'add_admin_bar_menu', 100);
        
        // AJAX cache clearing
        $this->loader->add_action('wp_ajax_coreboost_clear_cache', $this, 'ajax_clear_cache');
        $this->loader->add_action('wp_ajax_coreboost_clear_hero_preload_cache', $this, 'ajax_clear_hero_preload_cache');
        
        // Frontend cache clearing handler
        $this->loader->add_action('init', $this, 'handle_frontend_cache_clear');
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('CoreBoost', 'coreboost'),
            __('CoreBoost', 'coreboost'),
            'manage_options',
            'coreboost',
            array($this->settings_page, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        // Enqueue on all pages for admin bar functionality
        if (is_admin_bar_showing()) {
            wp_enqueue_script('coreboost-admin', COREBOOST_PLUGIN_URL . 'assets/admin.js', array('jquery'), COREBOOST_VERSION, true);
            wp_localize_script('coreboost-admin', 'coreboost_ajax', array(
                'ajax_url'      => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('coreboost_clear_cache_nonce'),
                'clearing_text' => __('Clearing cache...', 'coreboost'),
                'success_text'  => __('Cache cleared!', 'coreboost'),
                'error_text'    => __('Error clearing cache', 'coreboost'),
            ));
        }
        
        // Enqueue on settings page for enhanced functionality
        if ($hook === 'settings_page_coreboost') {
            wp_enqueue_script('coreboost-settings', COREBOOST_PLUGIN_URL . 'assets/settings.js', array('jquery'), COREBOOST_VERSION, true);
            wp_enqueue_style('coreboost-admin-style', COREBOOST_PLUGIN_URL . 'assets/admin.css', array(), COREBOOST_VERSION);
        }
    }
    
    /**
     * Handle frontend cache clearing
     */
    public function handle_frontend_cache_clear() {
        $action = filter_input(INPUT_GET, 'coreboost_action', FILTER_SANITIZE_SPECIAL_CHARS);
        $nonce = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        
        // Only handle frontend cache clearing (not admin area)
        if ($action === 'clear_cache' && $nonce && wp_verify_nonce($nonce, 'coreboost_clear_cache_frontend') && !is_admin()) {
            if (current_user_can('manage_options')) {
                Cache_Manager::flush_all_caches();
                
                // Redirect to show success message
                $redirect_url = remove_query_arg(array('coreboost_action', '_wpnonce'));
                wp_redirect(add_query_arg('coreboost_cache_cleared', '1', $redirect_url));
                exit;
            }
        }
        
        // Show success message if cache was just cleared (frontend only)
        if (filter_input(INPUT_GET, 'coreboost_cache_cleared', FILTER_SANITIZE_SPECIAL_CHARS) === '1' && !is_admin()) {
            add_action('wp_footer', array($this, 'show_cache_cleared_notice'));
        }
        
        // Show admin notice if cache was cleared in backend
        if (is_admin() && filter_input(INPUT_GET, 'coreboost_cache_cleared', FILTER_SANITIZE_SPECIAL_CHARS) === '1') {
            add_action('admin_notices', array($this, 'show_admin_cache_cleared_notice'));
        }
    }
    
    /**
     * Show cache cleared notice on frontend
     */
    public function show_cache_cleared_notice() {
        // Don't show on admin pages, preview contexts, or non-admin users
        if (!current_user_can('manage_options') || is_admin() || wp_doing_ajax() || isset($_GET['elementor-preview'])) {
            return;
        }
        ?>
        <style>
            .coreboost-notice {
                position: fixed;
                top: 32px;
                right: 20px;
                background: #46b450;
                color: white;
                padding: 15px 20px;
                border-radius: 4px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                z-index: 999999;
                animation: coreboost-slide-in 0.3s ease;
            }
            @keyframes coreboost-slide-in {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        </style>
        <div class="coreboost-notice" id="coreboost-notice">
            Ã¢Å“â€œ CoreBoost cache cleared successfully!
        </div>
        <script>
            setTimeout(function() {
                var notice = document.getElementById('coreboost-notice');
                if (notice) {
                    notice.style.opacity = '0';
                    setTimeout(function() { notice.remove(); }, 300);
                }
            }, 3000);
        </script>
        <?php
    }
    
    /**
     * Show admin cache cleared notice
     */
    public function show_admin_cache_cleared_notice() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('CoreBoost cache cleared successfully!', 'coreboost'); ?></p>
        </div>
        <script>
            // Clean up URL parameter
            if (window.history.replaceState) {
                var url = new URL(window.location);
                url.searchParams.delete('coreboost_cache_cleared');
                window.history.replaceState({}, '', url);
            }
        </script>
        <?php
    }
    
    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_cache() {
        // Verify nonce
        $nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!$nonce || !wp_verify_nonce($nonce, 'coreboost_clear_cache_nonce')) {
            wp_die(__('Security check failed', 'coreboost'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'coreboost'));
        }
        
        // Clear all caches
        Cache_Manager::flush_all_caches();
        
        wp_send_json_success(array(
            'message' => __('All caches cleared successfully!', 'coreboost')
        ));
    }

    /**
     * AJAX handler for clearing hero preload cache
     */
    public function ajax_clear_hero_preload_cache() {
        // Verify nonce
        $nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!$nonce || !wp_verify_nonce($nonce, 'coreboost_clear_hero_preload_cache_nonce')) {
            wp_die(__('Security check failed', 'coreboost'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'coreboost'));
        }
        
        // Clear all hero preload cache entries
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE 'coreboost_hero_preload_%' 
            OR option_name LIKE '_transient_coreboost_hero_preload_%'"
        );
        
        wp_send_json_success(array(
            'message' => __('Hero preload cache cleared successfully!', 'coreboost')
        ));
    }
}
