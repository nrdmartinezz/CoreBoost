<?php
/**
 * Plugin Name: CoreBoost
 * Plugin URI: https://github.com/your-username/coreboost
 * Description: Comprehensive site optimization plugin with LCP optimization for Elementor hero sections, advanced CSS deferring with critical CSS, Google Fonts & Adobe Fonts optimization, script optimization, and performance enhancements.
 * Version: 1.1.1
 * Author: nrdmartinezz
 * Author URI: https://github.com/nrdmartinezz
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: coreboost
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('COREBOOST_VERSION', '1.1.1');
define('COREBOOST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('COREBOOST_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COREBOOST_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main CoreBoost Class
 */
class CoreBoost {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Plugin options
     */
    private $options;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->options = get_option('coreboost_options', $this->get_default_options());
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_head', array($this, 'add_font_preconnects'), 0);
        add_action('wp_head', array($this, 'add_script_resource_hints'), 0);
        add_action('wp_head', array($this, 'preload_hero_images'), 1);
        add_action('wp_head', array($this, 'output_critical_css'), 2);
        add_filter('script_loader_tag', array($this, 'defer_scripts'), 20, 2);
        add_filter('style_loader_tag', array($this, 'defer_styles'), 20, 4);
        add_filter('style_loader_tag', array($this, 'optimize_font_loading'), 10, 4);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_optimization_styles'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
        add_action('wp_ajax_coreboost_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // LCP Image Optimization Hooks
        add_filter('wp_lazy_loading_enabled', array($this, 'maybe_disable_lazy_loading'), 10, 2);
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lcp_attributes'), 10, 3);
        
        // Remove unused CSS and JS
        add_action('wp_print_styles', array($this, 'remove_unused_styles'), 100);
        add_action('wp_print_scripts', array($this, 'remove_unused_scripts'), 100);
        
        // Block YouTube player resources if enabled
        add_filter('script_loader_tag', array($this, 'block_youtube_resources'), 10, 3);
        add_filter('style_loader_tag', array($this, 'block_youtube_style_resources'), 10, 4);
        
        // Output buffer filtering for inline/hardcoded CSS
        add_action('template_redirect', array($this, 'start_output_buffer'), 1);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('coreboost', false, dirname(COREBOOST_PLUGIN_BASENAME) . '/languages');
        
        // Handle frontend cache clearing
        $this->handle_frontend_cache_clear();
    }
    
    /**
     * Handle cache clearing from frontend admin bar
     */
    private function handle_frontend_cache_clear() {
        $action = filter_input(INPUT_GET, 'coreboost_action', FILTER_SANITIZE_SPECIAL_CHARS);
        $nonce = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        
        // Only handle frontend cache clearing (not admin area)
        if ($action === 'clear_cache' && $nonce && wp_verify_nonce($nonce, 'coreboost_clear_cache_frontend') && !is_admin()) {
            if (current_user_can('manage_options')) {
                $this->clear_all_hero_cache();
                
                // Redirect back to the current page without the query parameters
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
     * Show cache cleared notice
     */
    public function show_cache_cleared_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <style>
            .coreboost-notice {
                position: fixed;
                top: 50px;
                right: 20px;
                background: #00a32a;
                color: white;
                padding: 15px 20px;
                border-radius: 4px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                font-size: 14px;
                animation: coreboost-slide-in 0.3s ease-out;
            }
            @keyframes coreboost-slide-in {
                from {
                    transform: translateX(400px);
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
                    notice.style.transition = 'opacity 0.3s ease-out';
                    notice.style.opacity = '0';
                    setTimeout(function() {
                        notice.remove();
                        // Clean up URL
                        if (window.history.replaceState) {
                            var url = new URL(window.location);
                            url.searchParams.delete('coreboost_cache_cleared');
                            window.history.replaceState({}, '', url);
                        }
                    }, 300);
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
     * Get default options
     */
    private function get_default_options() {
        return array(
            'preload_method' => 'elementor_data',
            'enable_script_defer' => false,
            'enable_css_defer' => false,
            'enable_foreground_conversion' => false,
            'enable_responsive_preload' => false,
            'enable_caching' => false,
            'debug_mode' => false,
            'lazy_load_exclude_count' => 2,
            'scripts_to_defer' => "contact-form-7\nwc-cart-fragments\nelementor-frontend",
            'scripts_to_async' => "youtube-iframe-api\niframe-api",
            'styles_to_defer' => "contact-form-7\nwoocommerce-layout\nelementor-frontend\ncustom-frontend\nswiper\nwidget-\nelementor-post-\ncustom-\nfadeIn\ne-swiper",
            'exclude_scripts' => "jquery-core\njquery-migrate\njquery\njquery-ui-core",
            'specific_pages' => '',
            'css_defer_method' => 'preload_with_critical',
            'critical_css_global' => '',
            'critical_css_home' => '',
            'critical_css_pages' => '',
            'critical_css_posts' => '',
            'enable_font_optimization' => false,
            'font_display_swap' => true,
            'defer_google_fonts' => true,
            'defer_adobe_fonts' => true,
            'preconnect_google_fonts' => true,
            'preconnect_adobe_fonts' => true,
            'enable_unused_css_removal' => false,
            'enable_unused_js_removal' => false,
            'block_youtube_player_css' => false,
            'block_youtube_embed_ui' => false,
            'unused_css_list' => '',
            'unused_js_list' => ''
        );
    }
    
    /**
     * Helper: Flush all caches
     */
    private function flush_caches() {
        $this->clear_all_hero_cache();
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        add_option('coreboost_options', $this->get_default_options());
        $this->flush_caches();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        $this->flush_caches();
    }
    
    /**
     * Helper: Output debug comment
     */
    private function debug_comment($message) {
        if ($this->options['debug_mode']) {
            echo '<!-- CoreBoost: ' . esc_html($message) . ' -->' . "\n";
        }
    }

    /**
     * LCP Optimization Methods
     */
    public function maybe_disable_lazy_loading($default, $tag_name) {
        if ('img' === $tag_name && $this->is_lcp_candidate()) {
            $this->debug_comment('Lazy loading disabled for LCP candidate');
            return false;
        }
        return $default;
    }

    public function add_lcp_attributes($attr, $attachment, $size) {
        if ($this->is_lcp_candidate()) {
            $attr['fetchpriority'] = 'high';
            $this->debug_comment('fetchpriority="high" added to LCP candidate');
        }
        return $attr;
    }

    private function is_lcp_candidate() {
        static $image_count = 0;
        $image_count++;
        $exclude_count = isset($this->options['lazy_load_exclude_count']) ? (int)$this->options['lazy_load_exclude_count'] : 2;
        return $image_count <= $exclude_count;
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
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page initialization
     */
    public function admin_init() {
        register_setting('coreboost_options_group', 'coreboost_options', array($this, 'sanitize_options'));
        
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
     * Field configuration for dynamic callback handling
     */
    private function get_field_config() {
        return array(
            'enable_responsive_preload' => array('type' => 'checkbox', 'default' => true, 'description' => 'Preload different image sizes for mobile and tablet devices.'),
            'enable_foreground_conversion' => array('type' => 'checkbox', 'default' => false, 'description' => 'Add CSS to convert background images to foreground images for better performance.'),
            'enable_script_defer' => array('type' => 'checkbox', 'default' => true, 'description' => 'Enable automatic script deferring for better performance.'),
            'scripts_to_defer' => array('type' => 'textarea', 'rows' => 5, 'description' => 'Script handles to defer (one per line). Use defer for jQuery-dependent scripts. Leave empty to defer all non-excluded scripts.'),
            'scripts_to_async' => array('type' => 'textarea', 'rows' => 3, 'description' => 'Independent scripts to load with async (one per line). These scripts have no dependencies and can execute immediately. Examples: youtube-iframe-api, google-analytics, facebook-pixel.'),
            'exclude_scripts' => array('type' => 'textarea', 'rows' => 3, 'description' => 'Script handles to never defer or async (one per line). Keep jQuery here as it\'s required by most WordPress scripts.'),
            'enable_css_defer' => array('type' => 'checkbox', 'default' => false, 'description' => 'Enable CSS deferring with critical CSS inlining.'),
            'styles_to_defer' => array('type' => 'textarea', 'rows' => 3, 'description' => 'CSS handles to defer (one per line).'),
            'critical_css_global' => array('type' => 'textarea', 'rows' => 8, 'class' => 'large-text code', 'description' => 'Global critical CSS applied to all pages. Include only above-the-fold styles.'),
            'critical_css_home' => array('type' => 'textarea', 'rows' => 6, 'class' => 'large-text code', 'description' => 'Critical CSS specific to the homepage. This will be combined with global critical CSS.'),
            'critical_css_pages' => array('type' => 'textarea', 'rows' => 6, 'class' => 'large-text code', 'description' => 'Critical CSS for all pages (not posts). Combined with global critical CSS.'),
            'critical_css_posts' => array('type' => 'textarea', 'rows' => 6, 'class' => 'large-text code', 'description' => 'Critical CSS for all posts/blog pages. Combined with global critical CSS.'),
            'enable_caching' => array('type' => 'checkbox', 'default' => true, 'description' => 'Cache hero image detection results for better performance.'),
            'enable_unused_css_removal' => array('type' => 'checkbox', 'default' => false, 'description' => 'Dequeue and remove specified CSS files from your site.'),
            'unused_css_list' => array('type' => 'textarea', 'rows' => 3, 'description' => 'Enter CSS handles to remove (one per line). Find handles in page source or browser developer tools.'),
            'enable_unused_js_removal' => array('type' => 'checkbox', 'default' => false, 'description' => 'Dequeue and remove specified JavaScript files from your site.'),
            'unused_js_list' => array('type' => 'textarea', 'rows' => 3, 'description' => 'Enter JavaScript handles to remove (one per line). Find handles in page source or browser developer tools.'),
            'block_youtube_player_css' => array('type' => 'checkbox', 'default' => false, 'description' => 'Block YouTube player CSS files (useful for background videos that don\'t need player UI).'),
            'block_youtube_embed_ui' => array('type' => 'checkbox', 'default' => false, 'description' => 'Block YouTube embed UI scripts (useful for autoplay background videos).'),
            'debug_mode' => array('type' => 'checkbox', 'default' => false, 'description' => 'Add HTML comments showing which optimizations are applied.')
        );
    }
    
    /**
     * Dynamic field callback handler
     */
    public function render_field_callback($args) {
        $field_name = $args['field_name'];
        $config = $args['config'];
        
        switch($config['type']) {
            case 'checkbox':
                $this->render_checkbox($field_name, $config['default'], $config['description']);
                break;
            case 'textarea':
                $class = isset($config['class']) ? $config['class'] : 'large-text';
                $this->render_textarea($field_name, $config['rows'], $config['description'], $class);
                break;
        }
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // Hero Image Fields
        add_settings_field('preload_method', __('Preload Method', 'coreboost'), array($this, 'preload_method_callback'), 'coreboost-hero', 'coreboost_hero_section');
        $this->add_dynamic_field('enable_responsive_preload', __('Responsive Preloading', 'coreboost'), 'coreboost-hero', 'coreboost_hero_section');
        $this->add_dynamic_field('enable_foreground_conversion', __('Enable Foreground CSS', 'coreboost'), 'coreboost-hero', 'coreboost_hero_section');
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
        
        // Advanced Fields
        $this->add_dynamic_field('enable_caching', __('Enable Caching', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('enable_unused_css_removal', __('Remove Unused CSS', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('unused_css_list', __('Unused CSS Handles', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('enable_unused_js_removal', __('Remove Unused JavaScript', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('unused_js_list', __('Unused JS Handles', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('block_youtube_player_css', __('Block YouTube Player CSS', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('block_youtube_embed_ui', __('Block YouTube Embed UI', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
        $this->add_dynamic_field('debug_mode', __('Debug Mode', 'coreboost'), 'coreboost-advanced', 'coreboost_advanced_section');
    }
    
    /**
     * Helper to add dynamic field with configuration
     */
    private function add_dynamic_field($field_name, $label, $page, $section) {
        $config = $this->get_field_config();
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
     * Section callbacks
     */
    /**
     * Helper: Output section description
     */
    private function section_description($text) {
        echo '<p>' . esc_html($text) . '</p>';
    }
    
    /**
     * Helper: Render checkbox field
     */
    private function render_checkbox($name, $default, $description) {
        $value = isset($this->options[$name]) ? $this->options[$name] : $default;
        echo '<input type="checkbox" name="coreboost_options[' . esc_attr($name) . ']" value="1"' . checked($value, true, false) . '>';
        echo '<p class="description">' . esc_html($description) . '</p>';
    }
    
    /**
     * Helper: Render textarea field
     */
    private function render_textarea($name, $rows, $description, $class = 'large-text') {
        $value = isset($this->options[$name]) ? $this->options[$name] : '';
        echo '<textarea name="coreboost_options[' . esc_attr($name) . ']" rows="' . esc_attr($rows) . '" cols="50" class="' . esc_attr($class) . '">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html($description) . '</p>';
    }
    
    /**
     * Helper: Render select field
     */
    private function render_select($name, $options, $default, $description) {
        $value = isset($this->options[$name]) ? $this->options[$name] : $default;
        echo '<select name="coreboost_options[' . esc_attr($name) . ']">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html($description) . '</p>';
    }

    public function hero_section_callback() {
        echo '<p>' . esc_html('Configure how hero images are detected and preloaded for optimal LCP performance.') . '</p>';
    }
    
    public function script_section_callback() {
        echo '<p>' . esc_html('Optimize JavaScript loading to reduce critical request chain. Use defer for jQuery-dependent scripts (downloads in parallel, executes in order). Use async for independent scripts like YouTube, analytics (downloads and executes immediately). This eliminates network waterfall congestion.') . '</p>';
    }
    
    public function css_section_callback() {
        echo '<p>' . esc_html('Advanced CSS optimization with critical CSS inlining and non-critical CSS deferring.') . '</p>';
    }
    
    public function advanced_section_callback() {
        echo '<p>' . esc_html('Advanced optimization settings including unused resource removal and debugging options.') . '</p>';
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
        
        echo '<select name="coreboost_options[preload_method]">';
        foreach ($methods as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Choose how hero images should be detected and preloaded.', 'coreboost') . '</p>';
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
    
    public function css_defer_method_callback() {
        $methods = array(
            'preload_with_critical' => __('Preload with Critical CSS (Recommended)', 'coreboost'),
            'simple_defer' => __('Simple Defer (Basic)', 'coreboost')
        );
        $this->render_select('css_defer_method', $methods, 'preload_with_critical', 'Choose CSS deferring method. Preload with Critical CSS provides better performance.');
    }
    
    public function enable_font_optimization_callback() {
        $this->render_checkbox('enable_font_optimization', false, 'Optimize external font loading to eliminate render-blocking and improve page speed.');
        echo '<p class="description"><strong>' . __('Note:', 'coreboost') . '</strong> ' . __('This will automatically add preconnect links and defer font stylesheets from Google Fonts and Adobe Fonts.', 'coreboost') . '</p>';
    }
    
    public function defer_google_fonts_callback() {
        $disabled = !$this->options['enable_font_optimization'];
        $this->render_checkbox('defer_google_fonts', $disabled, 'Defer Google Fonts (fonts.googleapis.com) loading using preload with onload handler.');
    }
    
    public function defer_adobe_fonts_callback() {
        $disabled = !$this->options['enable_font_optimization'];
        $this->render_checkbox('defer_adobe_fonts', $disabled, 'Defer Adobe Fonts (use.typekit.net, fonts.adobe.com) loading using preload with onload handler.');
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        // Get current options to preserve values not in current form
        $current_options = get_option('coreboost_options', $this->get_default_options());
        
        // Start with current options
        $sanitized = $current_options;
        
        // Sanitize and update only the fields that are present in the input
        if (isset($input['preload_method'])) {
            $sanitized['preload_method'] = sanitize_text_field($input['preload_method']);
        }
        
        // Sanitize fields by type
        $field_types = array(
            'boolean' => array('enable_script_defer', 'enable_css_defer', 'enable_foreground_conversion', 
                              'enable_responsive_preload', 'enable_caching', 'debug_mode', 'enable_font_optimization',
                              'font_display_swap', 'defer_google_fonts', 'defer_adobe_fonts', 
                              'preconnect_google_fonts', 'preconnect_adobe_fonts', 'enable_unused_css_removal',
                              'enable_unused_js_removal', 'block_youtube_player_css', 'block_youtube_embed_ui'),
            'textarea' => array('scripts_to_defer', 'scripts_to_async', 'styles_to_defer', 'exclude_scripts', 'specific_pages',
                               'unused_css_list', 'unused_js_list'),
            'text' => array('css_defer_method'),
            'css' => array('critical_css_global', 'critical_css_home', 'critical_css_pages', 'critical_css_posts')
        );
        
        // For boolean fields: WordPress doesn't send unchecked checkbox values in POST
        // So we need to check if the field is from the current form submission
        // If it's in $input as '1', set to true. If it's not in $input but should be (from current form), set to false
        // We determine current form fields by checking for any non-boolean field from the same tab
        $has_script_fields = isset($input['scripts_to_defer']) || isset($input['scripts_to_async']) || isset($input['exclude_scripts']);
        $has_css_fields = isset($input['styles_to_defer']) || isset($input['critical_css_global']) || isset($input['css_defer_method']);
        $has_hero_fields = isset($input['preload_method']) || isset($input['specific_pages']);
        $has_advanced_fields = isset($input['unused_css_list']) || isset($input['unused_js_list']);
        
        foreach ($field_types['boolean'] as $field) {
            if (array_key_exists($field, $input)) {
                // Checkbox was checked
                $sanitized[$field] = !empty($input[$field]);
            } else {
                // Checkbox not in input - only set to false if it's from the current form tab
                $is_current_form = false;
                
                // Script tab booleans
                if ($has_script_fields && $field === 'enable_script_defer') {
                    $is_current_form = true;
                }
                // CSS tab booleans
                if ($has_css_fields && in_array($field, array('enable_css_defer', 'enable_font_optimization', 'font_display_swap', 
                    'defer_google_fonts', 'defer_adobe_fonts', 'preconnect_google_fonts', 'preconnect_adobe_fonts'))) {
                    $is_current_form = true;
                }
                // Hero tab booleans
                if ($has_hero_fields && in_array($field, array('enable_responsive_preload', 'enable_foreground_conversion'))) {
                    $is_current_form = true;
                }
                // Advanced tab booleans
                if ($has_advanced_fields && in_array($field, array('enable_caching', 'debug_mode', 'enable_unused_css_removal',
                    'enable_unused_js_removal', 'block_youtube_player_css', 'block_youtube_embed_ui'))) {
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
        
        // Clear cache when settings change
        $this->clear_all_hero_cache();
        
        return $sanitized;
    }
    
    /**
     * Admin page HTML
     */
    public function admin_page() {
        $active_tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS);
        $active_tab = $active_tab ? sanitize_key($active_tab) : 'hero';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('CoreBoost', 'coreboost'); ?></strong> - <?php _e('Comprehensive performance optimization for WordPress sites with advanced CSS deferring and LCP optimization.', 'coreboost'); ?></p>
            </div>
            
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
            
            <?php if ($active_tab == 'hero'): ?>
                <div class="coreboost-info-box" style="background: #f1f1f1; padding: 20px; margin-top: 20px; border-radius: 5px;">
                    <h3><?php _e('Hero Image Optimization Guide', 'coreboost'); ?></h3>
                    <ul>
                        <li><strong><?php _e('Auto-detect from Elementor Data:', 'coreboost'); ?></strong> <?php _e('Best for most Elementor sites', 'coreboost'); ?></li>
                        <li><strong><?php _e('Featured Image with Fallback:', 'coreboost'); ?></strong> <?php _e('Good for mixed content types', 'coreboost'); ?></li>
                        <li><strong><?php _e('Smart Detection:', 'coreboost'); ?></strong> <?php _e('Manual override + auto-detection', 'coreboost'); ?></li>
                        <li><strong><?php _e('Advanced with Caching:', 'coreboost'); ?></strong> <?php _e('Best for high-traffic sites', 'coreboost'); ?></li>
                    </ul>
                </div>
            <?php elseif ($active_tab == 'scripts'): ?>
                <div class="coreboost-info-box" style="background: #f1f1f1; padding: 20px; margin-top: 20px; border-radius: 5px;">
                    <h3><?php _e('Script Optimization Tips', 'coreboost'); ?></h3>
                    <p><strong><?php _e('Common scripts to defer:', 'coreboost'); ?></strong></p>
                    <code>contact-form-7<br>wc-cart-fragments<br>elementor-frontend</code>
                    <p><strong><?php _e('Never defer these scripts:', 'coreboost'); ?></strong></p>
                    <code>jquery-core<br>jquery-migrate<br>jquery</code>
                </div>
            <?php elseif ($active_tab == 'css'): ?>
                <div class="coreboost-info-box" style="background: #f1f1f1; padding: 20px; margin-top: 20px; border-radius: 5px;">
                    <h3><?php _e('Critical CSS Guide', 'coreboost'); ?></h3>
                    <ul>
                        <li><?php _e('Use online tools like Critical CSS Generator to extract critical styles', 'coreboost'); ?></li>
                        <li><?php _e('Include only styles needed for above-the-fold content', 'coreboost'); ?></li>
                        <li><?php _e('Global critical CSS is applied to all pages', 'coreboost'); ?></li>
                        <li><?php _e('Page-specific CSS is additional to global CSS', 'coreboost'); ?></li>
                        <li><?php _e('Test thoroughly after enabling CSS deferring', 'coreboost'); ?></li>
                    </ul>
                </div>
            <?php elseif ($active_tab == 'advanced'): ?>
                <div class="coreboost-info-box" style="background: #f1f1f1; padding: 20px; margin-top: 20px; border-radius: 5px;">
                    <h3><?php _e('Cache Management', 'coreboost'); ?></h3>
                    <p>
                        <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=coreboost&tab=advanced&action=clear_cache'), 'coreboost_clear_cache'); ?>" class="button">
                            <?php _e('Clear All Caches', 'coreboost'); ?>
                        </a>
                    </p>
                    <h4><?php _e('Debug Mode', 'coreboost'); ?></h4>
                    <p><?php _e('Enable debug mode to see HTML comments showing which optimizations are applied. Disable on production sites.', 'coreboost'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        // Handle cache clearing in admin
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
        $nonce_get = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($action === 'clear_cache' && $nonce_get && wp_verify_nonce($nonce_get, 'coreboost_clear_cache')) {
            $this->clear_all_hero_cache();
            
            // Redirect to show success message
            $redirect_url = remove_query_arg(array('action', '_wpnonce'));
            wp_redirect(add_query_arg('coreboost_cache_cleared', '1', $redirect_url));
            exit;
        }
        
        // Handle settings update and redirect to preserve tab
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
    
    /**
     * Output hidden fields to preserve settings from other tabs
     */
    private function output_hidden_fields($active_tab) {
        $all_fields = array(
            'hero' => array('preload_method', 'enable_responsive_preload', 'enable_foreground_conversion', 'specific_pages'),
            'scripts' => array('enable_script_defer', 'scripts_to_defer', 'scripts_to_async', 'exclude_scripts'),
            'css' => array('enable_css_defer', 'css_defer_method', 'styles_to_defer', 'enable_font_optimization', 
                          'defer_google_fonts', 'defer_adobe_fonts', 'preconnect_google_fonts', 'preconnect_adobe_fonts',
                          'font_display_swap', 'critical_css_global', 'critical_css_home', 'critical_css_pages', 'critical_css_posts'),
            'advanced' => array('enable_caching', 'enable_unused_css_removal', 'unused_css_list', 'enable_unused_js_removal', 
                               'unused_js_list', 'block_youtube_player_css', 'block_youtube_embed_ui', 'debug_mode')
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
     * Output critical CSS
     */
    public function output_critical_css() {
        if (!$this->options['enable_css_defer'] || $this->options['css_defer_method'] !== 'preload_with_critical') {
            return;
        }
        
        $critical_css = $this->get_critical_css_for_current_page();
        
        if (!empty($critical_css)) {
            $this->debug_comment('Outputting Critical CSS');
            echo "<style id='coreboost-critical-css'>\n" . $critical_css . "\n</style>\n";
        }
    }
    
    /**
     * Get critical CSS for current page
     */
    private function get_critical_css_for_current_page() {
        $critical_css = '';
        
        // Always include global critical CSS
        if (!empty($this->options['critical_css_global'])) {
            $critical_css .= $this->options['critical_css_global'] . "\n";
        }
        
        // Add page-specific critical CSS
        if (is_front_page() && !empty($this->options['critical_css_home'])) {
            $critical_css .= $this->options['critical_css_home'] . "\n";
        } elseif (is_page() && !empty($this->options['critical_css_pages'])) {
            $critical_css .= $this->options['critical_css_pages'] . "\n";
        } elseif (is_single() && !empty($this->options['critical_css_posts'])) {
            $critical_css .= $this->options['critical_css_posts'] . "\n";
        }
        
        return trim($critical_css);
    }
    
    /**
     * Clear all hero image cache
     */
    private function clear_all_hero_cache() {
        global $wpdb;
        
        // Clear object cache
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('coreboost_hero_cache');
        }
        
        // Clear transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_coreboost_hero_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_coreboost_hero_%'");
    }
    
    /**
     * Add admin bar menu
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_menu(array(
            'id'    => 'coreboost',
            'title' => __('CoreBoost', 'coreboost'),
            'href'  => admin_url('options-general.php?page=coreboost'),
            'meta'  => array(
                'title' => __('CoreBoost Settings', 'coreboost'),
            ),
        ));
        
        // Create nonce URL for cache clearing
        $clear_cache_url = add_query_arg(
            array(
                'coreboost_action' => 'clear_cache',
                '_wpnonce' => wp_create_nonce('coreboost_clear_cache_frontend')
            ),
            home_url(add_query_arg(array()))
        );
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'coreboost',
            'id'     => 'coreboost-clear-cache',
            'title'  => __('Clear Cache', 'coreboost'),
            'href'   => $clear_cache_url,
            'meta'   => array(
                'title' => __('Clear CoreBoost Cache', 'coreboost'),
            ),
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'coreboost',
            'id'     => 'coreboost-settings',
            'title'  => __('Settings', 'coreboost'),
            'href'   => admin_url('options-general.php?page=coreboost'),
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'coreboost',
            'id'     => 'coreboost-test-pagespeed',
            'title'  => __('Test PageSpeed', 'coreboost'),
            'href'   => 'https://pagespeed.web.dev/analysis?url=' . urlencode(home_url()),
            'meta'   => array(
                'target' => '_blank',
                'title'  => __('Test this page with PageSpeed Insights', 'coreboost'),
            ),
        ));
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Enqueue on all pages for admin bar functionality
        if (is_admin_bar_showing()) {
            wp_enqueue_script('coreboost-admin', COREBOOST_PLUGIN_URL . 'assets/admin.js', array('jquery'), COREBOOST_VERSION, true);
            wp_localize_script('coreboost-admin', 'coreboost_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('coreboost_clear_cache_nonce'),
                'clearing_text' => __('Clearing...', 'coreboost'),
                'cleared_text'  => __('Cache Cleared!', 'coreboost'),
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
        
        // Clear the cache
        $this->clear_all_hero_cache();
        
        // Clear other caches if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear popular caching plugin caches
        $this->clear_third_party_caches();
        
        wp_send_json_success(array(
            'message' => __('All caches cleared successfully!', 'coreboost')
        ));
    }
    
    /**
     * Clear third-party caching plugin caches
     */
    private function clear_third_party_caches() {
        $cache_functions = array(
            'rocket_clean_domain' => 'rocket_clean_domain',
            'w3tc_flush_all' => 'w3tc_flush_all',
            'wp_cache_clear_cache' => 'wp_cache_clear_cache'
        );
        
        foreach ($cache_functions as $func) {
            if (function_exists($func)) $func();
        }
        
        if (class_exists('LiteSpeed_Cache_API')) LiteSpeed_Cache_API::purge_all();
        if (class_exists('autoptimizeCache')) autoptimizeCache::clearall();
    }
    
    /**
     * Main hero image preloading function
     */
    public function preload_hero_images() {
        if (is_admin() || $this->options['preload_method'] === 'disabled') {
            return;
        }
        
        $methods = array(
            'auto_elementor' => 'preload_auto_elementor',
            'featured_fallback' => 'preload_featured_fallback',
            'smart_detection' => 'preload_smart_detection',
            'advanced_cached' => 'preload_advanced_cached',
            'css_class_based' => 'preload_css_class_based'
        );
        
        $method = $this->options['preload_method'];
        if (isset($methods[$method])) {
            $this->{$methods[$method]}();
        }
    }
    
    /**
     * Auto Elementor detection method
     */
    private function preload_auto_elementor() {
        if (!defined('ELEMENTOR_VERSION')) return;
        
        global $post;
        if (!$post) return;
        
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if (empty($elementor_data)) return;
        
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) return;
        
        $hero_image_url = $this->find_hero_background_image($data);
        if ($hero_image_url) {
            $this->output_preload_tag($hero_image_url);
            if ($this->options['enable_responsive_preload']) {
                $this->output_responsive_preload($hero_image_url);
            }
        }
    }
    
    /**
     * Featured image fallback method
     */
    private function preload_featured_fallback() {
        global $post;
        $hero_image_url = null;
        $hero_image_id = null;
        
        // Try Elementor first
        if (defined('ELEMENTOR_VERSION') && $post) {
            $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
            if ($elementor_data) {
                $data = json_decode($elementor_data, true);
                if (isset($data[0]['settings']['background_image']['url'])) {
                    $hero_image_url = $data[0]['settings']['background_image']['url'];
                    $hero_image_id = isset($data[0]['settings']['background_image']['id']) ? $data[0]['settings']['background_image']['id'] : null;
                }
            }
        }
        
        // Fallback to featured image
        if (!$hero_image_url && has_post_thumbnail()) {
            $hero_image_id = get_post_thumbnail_id();
            $hero_image_url = get_the_post_thumbnail_url($post->ID, 'full');
        }
        
        // Fallback to custom field
        if (!$hero_image_url) {
            $custom_hero = get_post_meta($post->ID, 'hero_image', true);
            if ($custom_hero) $hero_image_url = $custom_hero;
        }
        
        if ($hero_image_url) {
            $this->output_preload_tag($hero_image_url);
            if ($this->options['enable_responsive_preload'] && $hero_image_id) {
                $this->output_responsive_preload_by_id($hero_image_id);
            }
        }
    }
    
    /**
     * Smart detection with manual override
     */
    private function preload_smart_detection() {
        global $post;
        
        // Check for page-specific images first
        $specific_images = $this->parse_specific_pages();
        
        if (is_front_page() && isset($specific_images['home'])) {
            $this->output_preload_tag($specific_images['home']);
            return;
        }
        
        if (is_page() && $post) {
            $page_slug = $post->post_name;
            if (isset($specific_images[$page_slug])) {
                $this->output_preload_tag($specific_images[$page_slug]);
                return;
            }
        }
        
        // Fall back to auto-detection
        $this->preload_auto_elementor();
    }
    
    /**
     * Advanced cached method
     */
    private function preload_advanced_cached() {
        if (!defined('ELEMENTOR_VERSION')) return;
        
        global $post;
        if (!$post) return;
        
        // Check cache first
        $cache_key = 'coreboost_hero_' . $post->ID;
        $cached_image = null;
        
        if ($this->options['enable_caching']) {
            $cached_image = get_transient($cache_key);
        }
        
        if ($cached_image !== false && $cached_image !== null) {
            if ($cached_image) {
                $this->output_preload_tag($cached_image);
                
                if ($this->options['enable_responsive_preload']) {
                    $this->output_responsive_preload($cached_image);
                }
            }
            return;
        }
        
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        $hero_image_url = null;
        
        if ($elementor_data) {
            $data = json_decode($elementor_data, true);
            if (is_array($data)) {
                $hero_image_url = $this->search_elementor_hero_advanced($data);
            }
        }
        
        // Cache the result
        if ($this->options['enable_caching']) {
            set_transient($cache_key, $hero_image_url, 3600); // Cache for 1 hour
        }
        
        if ($hero_image_url) {
            $this->output_preload_tag($hero_image_url);
            
            if ($this->options['enable_responsive_preload']) {
                $this->output_responsive_preload($hero_image_url);
            }
        }
    }
    
    /**
     * CSS class-based detection
     */
    private function preload_css_class_based() {
        if (!defined('ELEMENTOR_VERSION')) return;
        
        global $post;
        if (!$post) return;
        
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if ($elementor_data) {
            $data = json_decode($elementor_data, true);
            $hero_image = $this->find_hero_foreground_image($data);
            
            if ($hero_image) {
                $this->output_preload_tag($hero_image['url']);
                if ($this->options['enable_responsive_preload'] && $hero_image['id']) {
                    $this->output_responsive_preload_by_id($hero_image['id']);
                }
            }
        }
    }
    
    /**
     * Helper functions for image detection
     */
    private function find_hero_background_image($elements, $depth = 0, $max_depth = 2) {
        if ($depth > $max_depth) return null;
        
        foreach ($elements as $element) {
            if (isset($element['settings']['background_image']['url'])) {
                return $element['settings']['background_image']['url'];
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->find_hero_background_image($element['elements'], $depth + 1, $max_depth);
                if ($found) return $found;
            }
        }
        return null;
    }
    
    private function search_elementor_hero_advanced($elements, $max_depth = 3, $current_depth = 0) {
        if ($current_depth >= $max_depth) return null;
        
        foreach ($elements as $index => $element) {
            if ($index > 2 && $current_depth === 0) break;
            
            // Check background image
            if (isset($element['settings']['background_image']['url'])) {
                return $element['settings']['background_image']['url'];
            }
            
            // Check for foreground images in widgets (only at top level)
            if ($current_depth === 0 && isset($element['elements'])) {
                foreach ($element['elements'] as $column) {
                    if (isset($column['elements'])) {
                        foreach ($column['elements'] as $widget) {
                            if ($widget['widgetType'] === 'image' && isset($widget['settings']['image']['url'])) {
                                return $widget['settings']['image']['url'];
                            }
                        }
                    }
                }
            }
            
            // Recurse
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->search_elementor_hero_advanced($element['elements'], $max_depth, $current_depth + 1);
                if ($found) return $found;
            }
        }
        return null;
    }
    
    private function find_hero_foreground_image($elements) {
        foreach ($elements as $element) {
            if ($element['widgetType'] === 'image') {
                $css_classes = isset($element['settings']['_css_classes']) ? $element['settings']['_css_classes'] : '';
                
                if ((strpos($css_classes, 'hero-foreground-image') !== false || strpos($css_classes, 'heroimg') !== false) 
                    && isset($element['settings']['image']['url'])) {
                    return array(
                        'url' => $element['settings']['image']['url'],
                        'id' => isset($element['settings']['image']['id']) ? $element['settings']['image']['id'] : null
                    );
                }
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->find_hero_foreground_image($element['elements']);
                if ($found) return $found;
            }
        }
        return null;
    }
    
    /**
     * Parse specific pages configuration
     */
    private function parse_specific_pages() {
        $specific_pages = array();
        foreach (explode("\n", $this->options['specific_pages']) as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $parts = explode('|', $line, 2);
                if (count($parts) === 2) {
                    $specific_pages[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
        return $specific_pages;
    }
    
    /**
     * Output preload tag
     */
    private function output_preload_tag($image_url) {
        if (empty($image_url)) {
            return;
        }
        
        if ($this->options['debug_mode']) {
            echo "<!-- CoreBoost: Preloading hero image: " . esc_url($image_url) . " -->\n";
        }
        
        echo '<link rel="preload" href="' . esc_url($image_url) . '" as="image" fetchpriority="high">' . "\n";
    }
    
    private function output_responsive_preload($image_url) {
        $image_id = attachment_url_to_postid($image_url);
        if ($image_id) $this->output_responsive_preload_by_id($image_id);
    }
    
    private function output_responsive_preload_by_id($image_id) {
        $sizes = array(
            'large' => '(max-width: 1024px)',
            'medium_large' => '(max-width: 768px)',
            'medium' => '(max-width: 480px)'
        );
        
        $original_url = wp_get_attachment_image_url($image_id, 'full');
        
        foreach ($sizes as $size => $media_query) {
            $responsive_url = wp_get_attachment_image_url($image_id, $size);
            if ($responsive_url && $responsive_url !== $original_url) {
                echo '<link rel="preload" href="' . esc_url($responsive_url) . '" as="image" media="' . $media_query . '">' . "\n";
            }
        }
    }
    
    /**
     * Script deferring with async support for independent scripts
     * Ensures jQuery-dependent scripts use defer (not async) to maintain execution order
     */
    public function defer_scripts($tag, $handle) {
        if (!$this->options['enable_script_defer'] || is_admin()) return $tag;
        
        // Check excluded scripts (jQuery, critical scripts)
        $excluded_scripts = array_filter(array_map('trim', explode("\n", $this->options['exclude_scripts'])));
        if (in_array($handle, $excluded_scripts)) {
            $this->debug_comment('Excluded from deferring: ' . $handle . ' (critical dependency)');
            return $tag;
        }
        
        // Check if script has jQuery as a dependency - these MUST use defer (not async)
        global $wp_scripts;
        $has_jquery_dependency = false;
        if (isset($wp_scripts->registered[$handle])) {
            $deps = $wp_scripts->registered[$handle]->deps;
            if (!empty($deps)) {
                $jquery_deps = array('jquery', 'jquery-core', 'jquery-migrate', 'jquery-ui-core');
                foreach ($jquery_deps as $jquery_dep) {
                    if (in_array($jquery_dep, $deps)) {
                        $has_jquery_dependency = true;
                        break;
                    }
                }
            }
        }
        
        // Check if this script should be processed
        $scripts_to_defer = array_filter(array_map('trim', explode("\n", $this->options['scripts_to_defer'])));
        $scripts_to_async = array_filter(array_map('trim', explode("\n", $this->options['scripts_to_async'])));
        
        // Determine if script should use async or defer
        // IMPORTANT: Scripts with jQuery dependencies MUST use defer (not async) to maintain execution order
        $use_async = in_array($handle, $scripts_to_async) && !$has_jquery_dependency;
        $use_defer = empty($scripts_to_defer) || in_array($handle, $scripts_to_defer);
        
        // Force defer for jQuery-dependent scripts (even if mistakenly in async list)
        if ($has_jquery_dependency && in_array($handle, $scripts_to_async)) {
            $use_async = false;
            $use_defer = true;
            $this->debug_comment('Forcing defer for jQuery-dependent script (was in async list): ' . $handle);
        }
        
        if ($use_async) {
            $this->debug_comment('Using async for independent script: ' . $handle);
            return str_replace(' src', ' async src', $tag);
        } elseif ($use_defer) {
            if ($has_jquery_dependency) {
                $this->debug_comment('Using defer for jQuery-dependent script: ' . $handle . ' (depends on jQuery)');
            } else {
                $this->debug_comment('Using defer for dependent script: ' . $handle);
            }
            return str_replace(' src', ' defer src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Enhanced CSS deferring with critical CSS support and pattern matching
     */
    public function defer_styles($html, $handle, $href, $media) {
        if (!$this->options['enable_css_defer'] || is_admin()) {
            return $html;
        }
        
        // Get styles to defer (including auto-detected patterns)
        $styles_to_defer = $this->get_styles_to_defer();
        
        // Check if this style should be deferred
        $should_defer = $this->should_defer_style($handle, $styles_to_defer);
        
        if (!$should_defer) {
            $this->debug_comment("NOT deferring CSS: {$handle} (no match found)");
            return $html;
        }
        
        $this->debug_comment("Deferring CSS: {$handle}");
        
        if ($this->options['css_defer_method'] === 'preload_with_critical') {
            // Advanced method with preload and critical CSS
            $preload_html = '<link rel="preload" href="' . esc_url($href) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" id="' . esc_attr($handle) . '-preload">';
            $noscript_html = '<noscript><link rel="stylesheet" href="' . esc_url($href) . '" id="' . esc_attr($handle) . '-noscript"></noscript>';
            return $preload_html . "\n" . $noscript_html;
        } else {
            // Simple defer method - change media to print, then switch with JS
            $deferred_html = str_replace(' media=\'all\'', ' media=\'print\' onload="this.media=\'all\'"', $html);
            $deferred_html = str_replace(' media="all"', ' media="print" onload="this.media=\'all\'"', $deferred_html);
            if (strpos($deferred_html, 'media=') === false) {
                $deferred_html = str_replace('<link ', '<link media="print" onload="this.media=\'all\'" ', $deferred_html);
            }
            return $deferred_html . '<noscript>' . $html . '</noscript>';
        }
    }
    
    /**
     * Add font preconnect links
     */
    public function add_font_preconnects() {
        if (!$this->options['enable_font_optimization']) {
            return;
        }
        
        $preconnects = array();
        
        if ($this->options['defer_google_fonts']) {
            $preconnects[] = '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>';
            $preconnects[] = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            $this->debug_comment('Added Google Fonts preconnect');
        }
        
        if ($this->options['defer_adobe_fonts']) {
            $preconnects[] = '<link rel="preconnect" href="https://use.typekit.net" crossorigin>';
            $this->debug_comment('Added Adobe Fonts preconnect');
        }
        
        if (!empty($preconnects)) {
            echo implode("\n", $preconnects) . "\n";
        }
    }
    
    /**
     * Add resource hints for critical scripts
     */
    public function add_script_resource_hints() {
        if (!$this->options['enable_script_defer'] || is_admin()) {
            return;
        }
        
        global $wp_scripts;
        if (!isset($wp_scripts->registered)) {
            return;
        }
        
        // Preload jQuery and jQuery UI as critical dependencies
        $critical_scripts = array(
            'jquery-core' => 'high',
            'jquery-migrate' => 'low',
            'jquery-ui-core' => 'low'
        );
        
        foreach ($critical_scripts as $handle => $priority) {
            if (isset($wp_scripts->registered[$handle])) {
                $src = $wp_scripts->registered[$handle]->src;
                if (strpos($src, '//') === 0) {
                    $src = 'https:' . $src;
                } elseif (strpos($src, '/') === 0) {
                    $src = site_url($src);
                }
                echo '<link rel="preload" href="' . esc_url($src) . '" as="script" fetchpriority="' . esc_attr($priority) . '">' . "\n";
                $this->debug_comment('Preloading critical script: ' . $handle);
            }
        }
    }
    
    /**
     * Remove unused CSS files
     */
    public function remove_unused_styles() {
        if (!$this->options['enable_unused_css_removal'] || empty($this->options['unused_css_list'])) {
            return;
        }
        
        $handles = array_filter(array_map('trim', explode("\n", $this->options['unused_css_list'])));
        
        foreach ($handles as $handle) {
            if (wp_style_is($handle, 'enqueued')) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
                
                if ($this->options['debug_mode']) {
                    $this->debug_comment("Removed unused CSS: {$handle}");
                }
            }
        }
    }
    
    /**
     * Remove unused JavaScript files
     */
    public function remove_unused_scripts() {
        if (!$this->options['enable_unused_js_removal'] || empty($this->options['unused_js_list'])) {
            return;
        }
        
        $handles = array_filter(array_map('trim', explode("\n", $this->options['unused_js_list'])));
        
        foreach ($handles as $handle) {
            if (wp_script_is($handle, 'enqueued')) {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
                
                if ($this->options['debug_mode']) {
                    $this->debug_comment("Removed unused script: {$handle}");
                }
            }
        }
    }
    
    /**
     * Block YouTube player resources from script tags
     */
    public function block_youtube_resources($tag, $handle, $src) {
        // Block YouTube embed UI scripts
        if ($this->options['block_youtube_embed_ui'] && strpos($src, 'youtube.com/yts/') !== false) {
            if ($this->options['debug_mode']) {
                $this->debug_comment("Blocked YouTube embed UI script: {$src}");
                return "<!-- CoreBoost: Blocked YouTube embed UI script -->\n";
            }
            return '';
        }
        
        return $tag;
    }
    
    /**
     * Block YouTube player resources from style tags
     */
    public function block_youtube_style_resources($html, $handle, $href, $media) {
        // Block YouTube player CSS
        if ($this->options['block_youtube_player_css'] && strpos($href, 'www.youtube.com/s/player') !== false) {
            if ($this->options['debug_mode']) {
                $this->debug_comment("Blocked YouTube player CSS: {$href}");
                return "<!-- CoreBoost: Blocked YouTube player CSS -->\n";
            }
            return '';
        }
        
        return $html;
    }
    
    /**
     * Optimize font loading
     */
    public function optimize_font_loading($html, $handle, $href, $media) {
        if (!$this->options['enable_font_optimization'] || is_admin()) {
            return $html;
        }
        
        $is_font = false;
        
        // Check if this is a Google Font
        if ($this->options['defer_google_fonts'] && 
            (strpos($href, 'fonts.googleapis.com') !== false || strpos($href, 'fonts.gstatic.com') !== false)) {
            $is_font = true;
            $this->debug_comment("Optimizing Google Font: {$handle}");
        }
        
        // Check if this is an Adobe Font
        if ($this->options['defer_adobe_fonts'] && 
            (strpos($href, 'use.typekit.net') !== false || strpos($href, 'fonts.adobe.com') !== false)) {
            $is_font = true;
            $this->debug_comment("Optimizing Adobe Font: {$handle}");
        }
        
        if (!$is_font) {
            return $html;
        }
        
        // Add display=swap to font URLs if not present
        if ($this->options['font_display_swap'] && strpos($href, 'display=') === false) {
            $href = add_query_arg('display', 'swap', $href);
        }
        
        // Convert to preload with onload handler for non-blocking load
        $preload_html = '<link rel="preload" href="' . esc_url($href) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" id="' . esc_attr($handle) . '-preload">';
        $noscript_html = '<noscript><link rel="stylesheet" href="' . esc_url($href) . '" id="' . esc_attr($handle) . '-noscript"></noscript>';
        
        return $preload_html . "\n" . $noscript_html;
    }
    
    /**
     * Get styles to defer including auto-detected patterns
     */
    private function get_styles_to_defer() {
        $manual_styles = array_filter(array_map('trim', explode("\n", $this->options['styles_to_defer'])));
        $auto_patterns = $this->get_auto_defer_patterns();
        
        return array_merge($manual_styles, $auto_patterns);
    }
    
    /**
     * Automatically detect common render-blocking CSS patterns
     */
    private function get_auto_defer_patterns() {
        return array(
            'elementor-post-',          // Post-specific Elementor CSS
            'elementor-pro-',           // Elementor Pro CSS
            'motion-fx',                // Elementor Pro motion effects
            'custom-',                  // Custom CSS files
            'widget-',                  // Widget CSS files
            'swiper',                   // Swiper CSS
            'e-swiper',                 // Elementor Swiper
            'fadeIn',                   // Animation CSS
            'elementor-frontend',       // Elementor frontend
            'woocommerce-layout',       // WooCommerce layout
            'woocommerce-smallscreen',  // WooCommerce responsive
            'elementor-global',         // Elementor global styles
            'elementor-kit-',           // Elementor kit styles
            'hello-elementor',          // Hello Elementor theme
            'elementor-icons',          // Elementor icons
            'wp-block-library-theme',   // WordPress block theme
        );
    }
    
    /**
     * Start output buffer to catch inline/hardcoded CSS and scripts
     */
    public function start_output_buffer() {
        if (is_admin()) {
            return;
        }
        if ($this->options['enable_css_defer'] || $this->options['enable_script_defer']) {
            ob_start(array($this, 'process_inline_assets'));
        }
    }
    
    /**
     * Process inline CSS and scripts in HTML output
     */
    public function process_inline_assets($html) {
        if (is_admin()) {
            return $html;
        }
        
        // Process CSS
        if ($this->options['enable_css_defer']) {
            $css_pattern = '/<link\s+([^>]*\s+)?rel=["\']stylesheet["\']([^>]*\s+)?href=["\']([^"\'\']+)["\']([^>]*)>/i';
            $html = preg_replace_callback($css_pattern, array($this, 'process_inline_css_callback'), $html);
        }
        
        // Process Scripts
        if ($this->options['enable_script_defer']) {
            $script_pattern = '/<script([^>]*)src=["\']([^"\'\']+)["\']([^>]*)><\/script>/i';
            $html = preg_replace_callback($script_pattern, array($this, 'process_inline_script_callback'), $html);
        }
        
        return $html;
    }
    
    /**
     * Callback for processing individual script tags
     */
    private function process_inline_script_callback($matches) {
        $full_tag = $matches[0];
        $before_src = $matches[1];
        $src = $matches[2];
        $after_src = $matches[3];
        
        // Skip if already has defer or async
        if (strpos($full_tag, ' defer') !== false || strpos($full_tag, ' async') !== false) {
            return $full_tag;
        }
        
        // Exclude jQuery and jQuery UI core (must load first, critical dependencies)
        if (strpos($src, '/jquery/jquery.min.js') !== false || 
            strpos($src, '/jquery-migrate') !== false ||
            strpos($src, 'jquery.min.js') !== false ||
            strpos($src, 'jquery.js') !== false ||
            strpos($src, '/jquery-ui-core') !== false ||
            strpos($src, 'jquery-ui.min.js') !== false ||
            strpos($src, '/ui/core.min.js') !== false) {
            return $full_tag;
        }
        
        $should_defer = false;
        $use_async = false;
        
        // Check for YouTube iframe API (independent - use async)
        if (strpos($src, 'youtube.com/iframe_api') !== false || strpos($src, 'www.youtube.com/') !== false) {
            $use_async = true;
            $should_defer = true;
            $this->debug_comment('Using async for inline YouTube API: ' . basename($src));
        }
        // Elementor scripts (dependent - use defer)
        elseif (strpos($src, '/elementor/') !== false || strpos($src, '/elementor-pro/') !== false) {
            $should_defer = true;
            $this->debug_comment('Deferring inline Elementor script: ' . basename($src));
        }
        // smartmenus (dependent - use defer)
        elseif (strpos($src, '/smartmenus/') !== false) {
            $should_defer = true;
            $this->debug_comment('Deferring inline smartmenus script: ' . basename($src));
        }
        // Other jQuery UI components (not core - can defer)
        elseif (strpos($src, '/jquery-ui/') !== false && strpos($src, 'core.min.js') === false) {
            $should_defer = true;
            $this->debug_comment('Deferring inline jQuery UI component: ' . basename($src));
        }
        // WordPress core dist scripts (may have dependencies - use defer)
        elseif (strpos($src, '/wp-includes/js/dist/') !== false) {
            $should_defer = true;
            $this->debug_comment('Deferring inline WordPress script: ' . basename($src));
        }
        // WooCommerce scripts
        elseif (strpos($src, '/woocommerce/') !== false) {
            $should_defer = true;
            $this->debug_comment('Deferring inline WooCommerce script: ' . basename($src));
        }
        
        if (!$should_defer) {
            return $full_tag;
        }
        
        // Add async or defer attribute
        $attribute = $use_async ? ' async' : ' defer';
        return '<script' . $before_src . $attribute . ' src="' . $src . '"' . $after_src . '></script>';
    }
    
    /**
     * Callback for processing individual CSS link tags
     */
    private function process_inline_css_callback($matches) {
        $full_tag = $matches[0];
        $href = $matches[3];
        
        // Check if this CSS should be deferred based on URL patterns
        $should_defer = false;
        
        // Elementor Pro patterns
        if (strpos($href, '/elementor-pro/assets/css/') !== false) {
            $should_defer = true;
            $this->debug_comment("Deferring inline Elementor Pro CSS: {$href}");
        }
        // Elementor patterns
        elseif (strpos($href, '/elementor/assets/css/') !== false) {
            $should_defer = true;
            $this->debug_comment("Deferring inline Elementor CSS: {$href}");
        }
        // WooCommerce patterns
        elseif (strpos($href, '/woocommerce/assets/css/') !== false) {
            $should_defer = true;
            $this->debug_comment("Deferring inline WooCommerce CSS: {$href}");
        }
        // Contact Form 7
        elseif (strpos($href, '/contact-form-7/') !== false) {
            $should_defer = true;
            $this->debug_comment("Deferring inline Contact Form 7 CSS: {$href}");
        }
        // Custom theme CSS files (custom-*.css pattern)
        elseif (preg_match('/\/custom-[a-z0-9\-]+\.min\.css$/i', $href) || 
                preg_match('/\/custom-[a-z0-9\-]+\.css$/i', $href)) {
            $should_defer = true;
            $this->debug_comment("Deferring inline Custom CSS: {$href}");
        }
        // Widget and animation CSS
        elseif (strpos($href, '/widget-') !== false || 
                strpos($href, '/fadeIn') !== false ||
                strpos($href, '/swiper') !== false) {
            $should_defer = true;
            $this->debug_comment("Deferring inline Widget/Animation CSS: {$href}");
        }
        // Plugin CSS in uploads folder (often non-critical)
        elseif (strpos($href, '/uploads/') !== false && strpos($href, '.css') !== false) {
            $should_defer = true;
            $this->debug_comment("Deferring inline Uploaded CSS: {$href}");
        }
        
        if (!$should_defer) {
            return $full_tag;
        }
        
        // Check if already has an ID to avoid duplicates
        if (strpos($full_tag, 'id=') !== false) {
            preg_match('/id=["\']([^"\'\']+)["\']/', $full_tag, $id_match);
            $id = isset($id_match[1]) ? $id_match[1] : '';
            
            // Skip if already processed (has -preload or -noscript suffix)
            if (strpos($id, '-preload') !== false || strpos($id, '-noscript') !== false) {
                return $full_tag;
            }
        }
        
        // Generate a unique ID based on the filename
        $filename = basename($href, '.css');
        $unique_id = 'coreboost-inline-' . sanitize_key($filename);
        
        // Convert to preload method
        if ($this->options['css_defer_method'] === 'preload_with_critical') {
            $preload_html = '<link rel="preload" href="' . esc_url($href) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" id="' . esc_attr($unique_id) . '-preload">';
            $noscript_html = '<noscript><link rel="stylesheet" href="' . esc_url($href) . '" id="' . esc_attr($unique_id) . '-noscript"></noscript>';
            return $preload_html . "\n" . $noscript_html;
        } else {
            // Simple defer method
            $deferred_html = str_replace('rel="stylesheet"', 'rel="stylesheet" media="print" onload="this.media=\'all\'"', $full_tag);
            $deferred_html = str_replace("rel='stylesheet'", "rel='stylesheet' media='print' onload=\"this.media='all'\"", $deferred_html);
            return $deferred_html . '<noscript>' . $full_tag . '</noscript>';
        }
    }
    
    /**
     * Check if a CSS handle should be deferred using pattern matching
     */
    private function should_defer_style($handle, $styles_to_defer) {
        foreach ($styles_to_defer as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) continue;
            
            // Exact match (including -css suffix)
            if ($handle === $pattern || $handle === $pattern . '-css') {
                return true;
            }
            
            // Pattern matching (ends with -)
            if (substr($pattern, -1) === '-' && strpos($handle, rtrim($pattern, '-')) === 0) {
                return true;
            }
            
            // Wildcard or partial matching
            if (strpos($pattern, '*') !== false) {
                $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';  
                if (preg_match($regex, $handle)) return true;
            } elseif (strpos($handle, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }    /**
     * Debug CSS detection and output information
     */
    public function debug_css_detection() {
        if (!$this->options['debug_mode'] || is_admin()) return;
        
        global $wp_styles;
        if (!isset($wp_styles->queue) || empty($wp_styles->queue)) {
            echo "<!-- CoreBoost: No CSS files found in queue -->\n";
            return;
        }
        
        echo "<!-- CoreBoost: CSS Debug Information -->\n";
        echo "<!-- CoreBoost: Total CSS files enqueued: " . count($wp_styles->queue) . " -->\n";
        
        $styles_to_defer = $this->get_styles_to_defer();
        $deferred_count = 0;
        
        foreach ($wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) continue;
            
            $style_obj = $wp_styles->registered[$handle];
            $src = isset($style_obj->src) ? $style_obj->src : 'N/A';
            $should_defer = $this->should_defer_style($handle, $styles_to_defer);
            
            if ($should_defer) {
                $deferred_count++;
                echo "<!-- CoreBoost: CSS DEFERRED - Handle: {$handle} | Src: " . basename($src) . " -->\n";
            } else {
                echo "<!-- CoreBoost: CSS NORMAL - Handle: {$handle} | Src: " . basename($src) . " -->\n";
            }
        }
        
        echo "<!-- CoreBoost: Total deferred CSS files: {$deferred_count} -->\n";
        echo "<!-- CoreBoost: CSS defer patterns: " . implode(', ', array_slice($styles_to_defer, 0, 10)) . (count($styles_to_defer) > 10 ? '...' : '') . " -->\n";
    }
    
    /**
     * Log enqueued styles for debugging
     */
    public function log_enqueued_styles() {
        if (!$this->options['debug_mode'] || is_admin()) return;
        
        global $wp_styles;
        if (!isset($wp_styles->queue) || empty($wp_styles->queue)) return;
        
        $styles_to_defer = $this->get_styles_to_defer();
        foreach ($wp_styles->queue as $handle) {
            if (isset($wp_styles->registered[$handle]) && $this->should_defer_style($handle, $styles_to_defer)) {
                error_log("CoreBoost: Will defer CSS - Handle: {$handle}");
            }
        }
    }
    
    /**
     * Enqueue optimization styles
     */
    public function enqueue_optimization_styles() {
        if ($this->options['enable_foreground_conversion']) {
            wp_add_inline_style('wp-block-library', $this->get_foreground_conversion_css());
        }
    }
    
    /**
     * Get foreground conversion CSS
     */
    private function get_foreground_conversion_css() {
        return '
        /* CoreBoost - Foreground Image Conversion */
        .hero-section-container,
        .relative {
            position: relative;
        }
        
        .hero-section-container .elementor-container,
        .hero-section-container .elementor-widget-wrap,
        .relative .elementor-container,
        .relative .elementor-widget-wrap {
            position: initial;
        }
        
        .hero-foreground-image,
        .hero-foreground-image img,
        .heroimg,
        .heroimg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            z-index: -1;
        }
        ';
    }
}

// Initialize the plugin
function coreboost_init() {
    return CoreBoost::get_instance();
}

// Hook into plugins_loaded to ensure all plugins are loaded
add_action('plugins_loaded', 'coreboost_init');

// Plugin activation
function coreboost_activate() {
    $instance = CoreBoost::get_instance();
    $instance->activate();
}
register_activation_hook(__FILE__, 'coreboost_activate');

// Plugin deactivation
function coreboost_deactivate() {
    $instance = CoreBoost::get_instance();
    $instance->deactivate();
}
register_deactivation_hook(__FILE__, 'coreboost_deactivate');

// Clear cache when Elementor data is updated
add_action('elementor/editor/after_save', function($post_id) {
    delete_transient('coreboost_hero_' . $post_id);
});
