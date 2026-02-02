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
use CoreBoost\Core\Context_Helper;

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
        
        // AJAX error logging
        $this->loader->add_action('wp_ajax_coreboost_log_error', $this, 'ajax_log_error');
        
        // Frontend cache clearing handler
        $this->loader->add_action('init', $this, 'handle_frontend_cache_clear');
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Add top-level menu page
        add_menu_page(
            __('CoreBoost', 'coreboost'),
            __('CoreBoost', 'coreboost'),
            'manage_options',
            'coreboost',
            array($this, 'render_dashboard_page'),
            'dashicons-performance',
            80
        );
        
        // Rename the auto-generated submenu from "CoreBoost" to "Dashboard"
        global $submenu;
        if (isset($submenu['coreboost'])) {
            $submenu['coreboost'][0][0] = __('Dashboard', 'coreboost');
        }
        
        // Add submenu pages
        add_submenu_page(
            'coreboost',
            __('Optimizations', 'coreboost'),
            __('Optimizations', 'coreboost'),
            'manage_options',
            'coreboost-optimizations',
            array($this->settings_page, 'render_admin_page')
        );
        
        add_submenu_page(
            'coreboost',
            __('Cache', 'coreboost'),
            __('Cache', 'coreboost'),
            'manage_options',
            'coreboost-cache',
            array($this, 'render_cache_page')
        );
        
        add_submenu_page(
            'coreboost',
            __('Database', 'coreboost'),
            __('Database', 'coreboost'),
            'manage_options',
            'coreboost-database',
            array($this, 'render_database_page')
        );
        
        add_submenu_page(
            'coreboost',
            __('Account', 'coreboost'),
            __('Account', 'coreboost'),
            'manage_options',
            'coreboost-account',
            array($this, 'render_account_page')
        );
        
        add_submenu_page(
            'coreboost',
            __('Report Issue', 'coreboost'),
            __('Report Issue', 'coreboost'),
            'manage_options',
            'coreboost-report',
            array($this, 'render_report_page')
        );
    }
    
    /**
     * Render Dashboard page
     */
    public function render_dashboard_page() {
        $dashboard_page = new Overview_Page($this->options);
        $dashboard_page->render();
    }
    
    /**
     * Render Cache page
     */
    public function render_cache_page() {
        $cache_page = new Cache_Page($this->options);
        $cache_page->render();
    }
    
    /**
     * Render Database page
     */
    public function render_database_page() {
        $database_page = new Database_Page($this->options);
        $database_page->render();
    }
    
    /**
     * Render Account page
     */
    public function render_account_page() {
        $account_page = new Account_Page($this->options);
        $account_page->render();
    }
    
    /**
     * Render Report Issue page
     */
    public function render_report_page() {
        $report_page = new Report_Page($this->options);
        $report_page->render();
    }
    
    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        // Check if we're on a CoreBoost admin page
        $coreboost_pages = array(
            'toplevel_page_coreboost',
            'coreboost_page_coreboost-optimizations',
            'coreboost_page_coreboost-cache',
            'coreboost_page_coreboost-database',
            'coreboost_page_coreboost-account',
            'coreboost_page_coreboost-report',
            'settings_page_coreboost' // Legacy support
        );
        $is_coreboost_page = in_array($hook, $coreboost_pages);
        
        // Enqueue on all pages for admin bar functionality
        if (is_admin_bar_showing()) {
            wp_enqueue_script('coreboost-admin', COREBOOST_PLUGIN_URL . 'assets/admin.js', array('jquery'), COREBOOST_VERSION, true);
            wp_localize_script('coreboost-admin', 'coreboost_ajax', array(
                'ajax_url'      => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('coreboost_clear_cache_nonce'),
                'clearing_text' => __('Clearing cache...', 'coreboost'),
                'cleared_text'  => __('Cache cleared!', 'coreboost'),
                'error_text'    => __('Error clearing cache', 'coreboost'),
            ));
        }
        
        // Enqueue on CoreBoost pages for enhanced functionality
        if ($is_coreboost_page) {
            // Enqueue error logger first (other scripts may depend on it)
            wp_enqueue_script('coreboost-error-logger', COREBOOST_PLUGIN_URL . 'assets/error-logger.js', array(), COREBOOST_VERSION, true);
            
            // Enable debug mode if WP_DEBUG is on or plugin debug mode is enabled
            $debug_mode = (defined('WP_DEBUG') && WP_DEBUG) || !empty($this->options['debug_mode']);
            wp_add_inline_script('coreboost-error-logger', 'window.coreBoostDebug = ' . ($debug_mode ? 'true' : 'false') . ';', 'before');
            
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
        if (!current_user_can('manage_options') || Context_Helper::should_skip_optimization()) {
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
            ✓ CoreBoost cache cleared successfully!
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
            wp_send_json_error(__('Security check failed', 'coreboost'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'coreboost'));
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
            wp_send_json_error(__('Security check failed', 'coreboost'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'coreboost'));
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

    /**
     * AJAX handler for logging client-side errors
     */
    public function ajax_log_error() {
        // Verify nonce
        $nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!$nonce || !wp_verify_nonce($nonce, 'coreboost_bulk_converter') && !wp_verify_nonce($nonce, 'coreboost_clear_cache_nonce')) {
            wp_send_json_error(__('Security check failed', 'coreboost'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'coreboost'));
            return;
        }
        
        // Get error data
        $error_data = filter_input(INPUT_POST, 'error_data', FILTER_UNSAFE_RAW);
        if (!$error_data) {
            wp_send_json_error(__('No error data provided', 'coreboost'));
            return;
        }
        
        // Decode JSON
        $error = json_decode($error_data, true);
        if (!$error) {
            wp_send_json_error(__('Invalid error data', 'coreboost'));
            return;
        }
        
        // Log to PHP error log if debug mode is enabled
        Context_Helper::debug_log(sprintf(
            'Client Error [%s]: %s - %s | Context: %s | URL: %s',
            $error['category'] ?? 'unknown',
            $error['operation'] ?? 'unknown',
            $error['message'] ?? 'no message',
            json_encode($error['context'] ?? []),
            $error['url'] ?? 'unknown'
        ));
        
        // Store critical errors in database for later review
        if (!empty($error['errorName']) && in_array($error['errorName'], ['AbortError', 'NetworkError', 'TimeoutError'])) {
            $option_name = 'coreboost_critical_errors';
            $errors = get_option($option_name, []);
            
            // Keep only last 50 errors
            if (count($errors) >= 50) {
                array_shift($errors);
            }
            
            $errors[] = [
                'timestamp' => current_time('mysql'),
                'category' => $error['category'] ?? 'unknown',
                'operation' => $error['operation'] ?? 'unknown',
                'message' => $error['message'] ?? 'no message',
                'error_name' => $error['errorName'] ?? 'Error',
                'url' => $error['url'] ?? 'unknown',
                'context' => $error['context'] ?? []
            ];
            
            update_option($option_name, $errors, false);
        }
        
        wp_send_json_success(['logged' => true]);
    }
}
