<?php
/**
 * Plugin Name: CoreBoost
 * Plugin URI: https://github.com/your-username/coreboost
 * Description: Comprehensive site optimization plugin with LCP optimization for Elementor hero sections, advanced CSS deferring with critical CSS, script optimization, and performance enhancements.
 * Version: 1.0.1
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
define('COREBOOST_VERSION', '1.0.1');
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
        add_action('wp_head', array($this, 'preload_hero_images'), 1);
        add_action('wp_head', array($this, 'output_critical_css'), 2);
        add_filter('script_loader_tag', array($this, 'defer_scripts'), 20, 2);
        add_filter('style_loader_tag', array($this, 'defer_styles'), 20, 4);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_optimization_styles'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
        add_action('wp_ajax_coreboost_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // LCP Image Optimization Hooks
        add_filter('wp_lazy_loading_enabled', array($this, 'maybe_disable_lazy_loading'), 10, 2);
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lcp_attributes'), 10, 3);
        
        // Plugin activation/deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('coreboost', false, dirname(COREBOOST_PLUGIN_BASENAME) . '/languages');
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
            'styles_to_defer' => "contact-form-7\nwoocommerce-layout\nelementor-frontend\ncustom-frontend\nswiper\nwidget-\nelementor-post-\ncustom-\nfadeIn\ne-swiper",
            'exclude_scripts' => "jquery-core\njquery-migrate\njquery",
            'specific_pages' => '',
            'css_defer_method' => 'preload_with_critical',
            'critical_css_global' => '',
            'critical_css_home' => '',
            'critical_css_pages' => '',
            'critical_css_posts' => ''
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        add_option('coreboost_options', $this->get_default_options());
        
        // Clear any existing caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear caches
        $this->clear_all_hero_cache();
        
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * LCP Optimization Methods
     */
    public function maybe_disable_lazy_loading($default, $tag_name) {
        if ('img' === $tag_name && $this->is_lcp_candidate()) {
            if ($this->options['debug_mode']) {
                echo '<!-- CoreBoost: Lazy loading disabled for LCP candidate -->';
            }
            return false; // Don't lazy-load
        }
        return $default;
    }

    public function add_lcp_attributes($attr, $attachment, $size) {
        if ($this->is_lcp_candidate()) {
            $attr['fetchpriority'] = 'high';
            if ($this->options['debug_mode']) {
                echo '<!-- CoreBoost: fetchpriority="high" added to LCP candidate -->';
            }
        }
        return $attr;
    }

    private function is_lcp_candidate() {
        // Simple heuristic: if it's the first image on the page, it's a candidate.
        // This can be improved with more advanced detection.
        static $image_count = 0;
        $image_count++;

        $exclude_count = isset($this->options['lazy_load_exclude_count']) ? (int)$this->options['lazy_load_exclude_count'] : 2;

        if ($image_count <= $exclude_count) {
            return true;
        }

        return false;
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
     * Add settings fields
     */
    private function add_settings_fields() {
        // Hero Image Fields
        add_settings_field('preload_method', __('Preload Method', 'coreboost'), array($this, 'preload_method_callback'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('enable_responsive_preload', __('Responsive Preloading', 'coreboost'), array($this, 'enable_responsive_preload_callback'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('enable_foreground_conversion', __('Enable Foreground CSS', 'coreboost'), array($this, 'enable_foreground_conversion_callback'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('specific_pages', __('Page-Specific Images', 'coreboost'), array($this, 'specific_pages_callback'), 'coreboost-hero', 'coreboost_hero_section');
        add_settings_field('lazy_load_exclude_count', __('Exclude First X Images from Lazy Load', 'coreboost'), array($this, 'lazy_load_exclude_count_callback'), 'coreboost-hero', 'coreboost_hero_section');
        
        // Script Fields
        add_settings_field('enable_script_defer', __('Enable Script Deferring', 'coreboost'), array($this, 'enable_script_defer_callback'), 'coreboost-scripts', 'coreboost_script_section');
        add_settings_field('scripts_to_defer', __('Scripts to Defer', 'coreboost'), array($this, 'scripts_to_defer_callback'), 'coreboost-scripts', 'coreboost_script_section');
        add_settings_field('exclude_scripts', __('Exclude Scripts', 'coreboost'), array($this, 'exclude_scripts_callback'), 'coreboost-scripts', 'coreboost_script_section');
        
        // CSS Fields
        add_settings_field('enable_css_defer', __('Enable CSS Deferring', 'coreboost'), array($this, 'enable_css_defer_callback'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('css_defer_method', __('CSS Defer Method', 'coreboost'), array($this, 'css_defer_method_callback'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('styles_to_defer', __('Styles to Defer', 'coreboost'), array($this, 'styles_to_defer_callback'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('critical_css_global', __('Global Critical CSS', 'coreboost'), array($this, 'critical_css_global_callback'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('critical_css_home', __('Homepage Critical CSS', 'coreboost'), array($this, 'critical_css_home_callback'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('critical_css_pages', __('Pages Critical CSS', 'coreboost'), array($this, 'critical_css_pages_callback'), 'coreboost-css', 'coreboost_css_section');
        add_settings_field('critical_css_posts', __('Posts Critical CSS', 'coreboost'), array($this, 'critical_css_posts_callback'), 'coreboost-css', 'coreboost_css_section');
        
        // Advanced Fields
        add_settings_field('enable_caching', __('Enable Caching', 'coreboost'), array($this, 'enable_caching_callback'), 'coreboost-advanced', 'coreboost_advanced_section');
        add_settings_field('debug_mode', __('Debug Mode', 'coreboost'), array($this, 'debug_mode_callback'), 'coreboost-advanced', 'coreboost_advanced_section');
    }
    
    /**
     * Section callbacks
     */
    public function hero_section_callback() {
        echo '<p>' . __('Configure how hero images are detected and preloaded for optimal LCP performance.', 'coreboost') . '</p>';
    }
    
    public function script_section_callback() {
        echo '<p>' . __('Optimize JavaScript loading by deferring non-critical resources.', 'coreboost') . '</p>';
    }
    
    public function css_section_callback() {
        echo '<p>' . __('Advanced CSS optimization with critical CSS inlining and non-critical CSS deferring.', 'coreboost') . '</p>';
    }
    
    public function advanced_section_callback() {
        echo '<p>' . __('Advanced optimization settings and debugging options.', 'coreboost') . '</p>';
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
    
    public function enable_responsive_preload_callback() {
        $value = isset($this->options['enable_responsive_preload']) ? $this->options['enable_responsive_preload'] : true;
        echo '<input type="checkbox" name="coreboost_options[enable_responsive_preload]" value="1"' . checked($value, true, false) . '>';
        echo '<p class="description">' . __('Preload different image sizes for mobile and tablet devices.', 'coreboost') . '</p>';
    }
    
    public function enable_foreground_conversion_callback() {
        $value = isset($this->options['enable_foreground_conversion']) ? $this->options['enable_foreground_conversion'] : false;
        echo '<input type="checkbox" name="coreboost_options[enable_foreground_conversion]" value="1"' . checked($value, true, false) . '>';
        echo '<p class="description">' . __('Add CSS to convert background images to foreground images for better performance.', 'coreboost') . '</p>';
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
    
    public function enable_script_defer_callback() {
        $value = isset($this->options['enable_script_defer']) ? $this->options['enable_script_defer'] : true;
        echo '<input type="checkbox" name="coreboost_options[enable_script_defer]" value="1"' . checked($value, true, false) . '>';
        echo '<p class="description">' . __('Enable automatic script deferring for better performance.', 'coreboost') . '</p>';
    }
    
    public function scripts_to_defer_callback() {
        $value = isset($this->options['scripts_to_defer']) ? $this->options['scripts_to_defer'] : '';
        echo '<textarea name="coreboost_options[scripts_to_defer]" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Script handles to defer (one per line). Leave empty to defer all non-excluded scripts.', 'coreboost') . '</p>';
    }
    
    public function exclude_scripts_callback() {
        $value = isset($this->options['exclude_scripts']) ? $this->options['exclude_scripts'] : '';
        echo '<textarea name="coreboost_options[exclude_scripts]" rows="3" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Script handles to never defer (one per line).', 'coreboost') . '</p>';
    }
    
    public function enable_css_defer_callback() {
        $value = isset($this->options['enable_css_defer']) ? $this->options['enable_css_defer'] : false;
        echo '<input type="checkbox" name="coreboost_options[enable_css_defer]" value="1"' . checked($value, true, false) . '>';
        echo '<p class="description">' . __('Enable CSS deferring with critical CSS inlining.', 'coreboost') . '</p>';
    }
    
    public function css_defer_method_callback() {
        $value = isset($this->options['css_defer_method']) ? $this->options['css_defer_method'] : 'preload_with_critical';
        $methods = array(
            'preload_with_critical' => __('Preload with Critical CSS (Recommended)', 'coreboost'),
            'simple_defer' => __('Simple Defer (Basic)', 'coreboost')
        );
        
        echo '<select name="coreboost_options[css_defer_method]">';
        foreach ($methods as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Choose CSS deferring method. Preload with Critical CSS provides better performance.', 'coreboost') . '</p>';
    }
    
    public function styles_to_defer_callback() {
        $value = isset($this->options['styles_to_defer']) ? $this->options['styles_to_defer'] : '';
        echo '<textarea name="coreboost_options[styles_to_defer]" rows="3" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('CSS handles to defer (one per line).', 'coreboost') . '</p>';
    }
    
    public function critical_css_global_callback() {
        $value = isset($this->options['critical_css_global']) ? $this->options['critical_css_global'] : '';
        echo '<textarea name="coreboost_options[critical_css_global]" rows="8" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Global critical CSS applied to all pages. Include only above-the-fold styles.', 'coreboost') . '</p>';
        echo '<p class="description"><strong>' . __('Tip:', 'coreboost') . '</strong> ' . __('Use tools like Critical CSS Generator or manually extract essential styles.', 'coreboost') . '</p>';
    }
    
    public function critical_css_home_callback() {
        $value = isset($this->options['critical_css_home']) ? $this->options['critical_css_home'] : '';
        echo '<textarea name="coreboost_options[critical_css_home]" rows="6" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Critical CSS specific to the homepage. This will be combined with global critical CSS.', 'coreboost') . '</p>';
    }
    
    public function critical_css_pages_callback() {
        $value = isset($this->options['critical_css_pages']) ? $this->options['critical_css_pages'] : '';
        echo '<textarea name="coreboost_options[critical_css_pages]" rows="6" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Critical CSS for all pages (not posts). Combined with global critical CSS.', 'coreboost') . '</p>';
    }
    
    public function critical_css_posts_callback() {
        $value = isset($this->options['critical_css_posts']) ? $this->options['critical_css_posts'] : '';
        echo '<textarea name="coreboost_options[critical_css_posts]" rows="6" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Critical CSS for all posts/blog pages. Combined with global critical CSS.', 'coreboost') . '</p>';
    }
    
    public function enable_caching_callback() {
        $value = isset($this->options['enable_caching']) ? $this->options['enable_caching'] : true;
        echo '<input type="checkbox" name="coreboost_options[enable_caching]" value="1"' . checked($value, true, false) . '>';
        echo '<p class="description">' . __('Cache hero image detection results for better performance.', 'coreboost') . '</p>';
    }
    
    public function debug_mode_callback() {
        $value = isset($this->options['debug_mode']) ? $this->options['debug_mode'] : false;
        echo '<input type="checkbox" name="coreboost_options[debug_mode]" value="1"' . checked($value, true, false) . '>';
        echo '<p class="description">' . __('Add HTML comments showing which optimizations are applied.', 'coreboost') . '</p>';
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
        
        // Boolean fields - handle checkboxes properly
        $boolean_fields = array(
            'enable_script_defer',
            'enable_css_defer', 
            'enable_foreground_conversion',
            'enable_responsive_preload',
            'enable_caching',
            'debug_mode'
        );
        
        foreach ($boolean_fields as $field) {
            if (array_key_exists($field, $input)) {
                $sanitized[$field] = !empty($input[$field]);
            }
        }
        
        // Text area fields
        $textarea_fields = array(
            'scripts_to_defer',
            'styles_to_defer', 
            'exclude_scripts',
            'specific_pages'
        );
        
        foreach ($textarea_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_textarea_field($input[$field]);
            }
        }
        
        // Select fields
        if (isset($input['css_defer_method'])) {
            $sanitized['css_defer_method'] = sanitize_text_field($input['css_defer_method']);
        }
        
        // Critical CSS fields (allow CSS but strip dangerous content)
        $css_fields = array(
            'critical_css_global',
            'critical_css_home', 
            'critical_css_pages',
            'critical_css_posts'
        );
        
        foreach ($css_fields as $field) {
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
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'hero';
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
                <div class="npg-so-info-box" style="background: #f1f1f1; padding: 20px; margin-top: 20px; border-radius: 5px;">
                    <h3><?php _e('Hero Image Optimization Guide', 'coreboost'); ?></h3>
                    <ul>
                        <li><strong><?php _e('Auto-detect from Elementor Data:', 'coreboost'); ?></strong> <?php _e('Best for most Elementor sites', 'coreboost'); ?></li>
                        <li><strong><?php _e('Featured Image with Fallback:', 'coreboost'); ?></strong> <?php _e('Good for mixed content types', 'coreboost'); ?></li>
                        <li><strong><?php _e('Smart Detection:', 'coreboost'); ?></strong> <?php _e('Manual override + auto-detection', 'coreboost'); ?></li>
                        <li><strong><?php _e('Advanced with Caching:', 'coreboost'); ?></strong> <?php _e('Best for high-traffic sites', 'coreboost'); ?></li>
                    </ul>
                </div>
            <?php elseif ($active_tab == 'scripts'): ?>
                <div class="npg-so-info-box" style="background: #f1f1f1; padding: 20px; margin-top: 20px; border-radius: 5px;">
                    <h3><?php _e('Script Optimization Tips', 'coreboost'); ?></h3>
                    <p><strong><?php _e('Common scripts to defer:', 'coreboost'); ?></strong></p>
                    <code>contact-form-7<br>wc-cart-fragments<br>elementor-frontend</code>
                    <p><strong><?php _e('Never defer these scripts:', 'coreboost'); ?></strong></p>
                    <code>jquery-core<br>jquery-migrate<br>jquery</code>
                </div>
            <?php elseif ($active_tab == 'css'): ?>
                <div class="npg-so-info-box" style="background: #f1f1f1; padding: 20px; margin-top: 20px; border-radius: 5px;">
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
                <div class="npg-so-info-box" style="background: #f1f1f1; padding: 20px; margin-top: 20px; border-radius: 5px;">
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
        
        // Handle cache clearing
        if (isset($_GET['action']) && sanitize_key($_GET['action']) === 'clear_cache' && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'coreboost_clear_cache')) {
            $this->clear_all_hero_cache();
            echo '<div class="notice notice-success"><p>' . __('All caches cleared successfully!', 'coreboost') . '</p></div>';
        }
        
        // Handle settings update and redirect to preserve tab
        if (isset($_GET['settings-updated']) && sanitize_text_field($_GET['settings-updated']) === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'coreboost') . '</p></div>';
            
            // JavaScript to redirect to correct tab if needed
            if (isset($_POST['current_tab'])) {
                $current_tab = sanitize_key($_POST['current_tab']);
                if ($current_tab !== $active_tab) {
                    echo '<script>
                        if (window.history.replaceState) {
                            var newUrl = window.location.href.replace(/&tab=[^&]*/, "") + "&tab=' . esc_js($current_tab) . '";
                            window.history.replaceState(null, null, newUrl);
                            window.location.reload();
                        }
                    </script>';
                }
            }
        }
    }
    
    /**
     * Output hidden fields to preserve settings from other tabs
     */
    private function output_hidden_fields($active_tab) {
        $all_fields = array(
            'hero' => array('preload_method', 'enable_responsive_preload', 'enable_foreground_conversion', 'specific_pages'),
            'scripts' => array('enable_script_defer', 'scripts_to_defer', 'exclude_scripts'),
            'css' => array('enable_css_defer', 'css_defer_method', 'styles_to_defer', 'critical_css_global', 'critical_css_home', 'critical_css_pages', 'critical_css_posts'),
            'advanced' => array('enable_caching', 'debug_mode')
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
            if ($this->options['debug_mode']) {
                echo "<!-- CoreBoost: Outputting Critical CSS -->\n";
            }
            
            echo "<style id='npg-so-critical-css'>\n";
            echo $critical_css;
            echo "\n</style>\n";
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
            'title' => __('NPG Optimizer', 'coreboost'),
            'href'  => admin_url('options-general.php?page=coreboost'),
            'meta'  => array(
                'title' => __('CoreBoost Settings', 'coreboost'),
            ),
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'coreboost',
            'id'     => 'npg-so-clear-cache',
            'title'  => __('Clear Cache', 'coreboost'),
            'href'   => '#',
            'meta'   => array(
                'title' => __('Clear CoreBoost Cache', 'coreboost'),
                'class' => 'npg-so-clear-cache-link',
            ),
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'coreboost',
            'id'     => 'npg-so-settings',
            'title'  => __('Settings', 'coreboost'),
            'href'   => admin_url('options-general.php?page=coreboost'),
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'coreboost',
            'id'     => 'npg-so-test-pagespeed',
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
            wp_enqueue_script('npg-so-admin', COREBOOST_PLUGIN_URL . 'assets/admin.js', array('jquery'), COREBOOST_VERSION, true);
            wp_localize_script('npg-so-admin', 'coreboost_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('coreboost_clear_cache_nonce'),
                'clearing_text' => __('Clearing...', 'coreboost'),
                'cleared_text'  => __('Cache Cleared!', 'coreboost'),
                'error_text'    => __('Error clearing cache', 'coreboost'),
            ));
        }
        
        // Enqueue on settings page for enhanced functionality
        if ($hook === 'settings_page_coreboost') {
            wp_enqueue_script('npg-so-settings', COREBOOST_PLUGIN_URL . 'assets/settings.js', array('jquery'), COREBOOST_VERSION, true);
            wp_enqueue_style('npg-so-admin-style', COREBOOST_PLUGIN_URL . 'assets/admin.css', array(), COREBOOST_VERSION);
        }
    }
    
    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_cache() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'coreboost_clear_cache_nonce')) {
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
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        
        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        // LiteSpeed Cache
        if (class_exists('LiteSpeed_Cache_API')) {
            LiteSpeed_Cache_API::purge_all();
        }
        
        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
        }
    }
    
    /**
     * Main hero image preloading function
     */
    public function preload_hero_images() {
        if (is_admin() || $this->options['preload_method'] === 'disabled') {
            return;
        }
        
        $method = $this->options['preload_method'];
        
        switch ($method) {
            case 'auto_elementor':
                $this->preload_auto_elementor();
                break;
            case 'featured_fallback':
                $this->preload_featured_fallback();
                break;
            case 'smart_detection':
                $this->preload_smart_detection();
                break;
            case 'advanced_cached':
                $this->preload_advanced_cached();
                break;
            case 'css_class_based':
                $this->preload_css_class_based();
                break;
        }
    }
    
    /**
     * Auto Elementor detection method
     */
    private function preload_auto_elementor() {
        if (!defined('ELEMENTOR_VERSION')) {
            return;
        }
        
        global $post;
        if (!$post) {
            return;
        }
        
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if (empty($elementor_data)) {
            return;
        }
        
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) {
            return;
        }
        
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
            if ($custom_hero) {
                $hero_image_url = $custom_hero;
            }
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
        if (!defined('ELEMENTOR_VERSION')) {
            return;
        }
        
        global $post;
        if (!$post) {
            return;
        }
        
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
        if (!defined('ELEMENTOR_VERSION')) {
            return;
        }
        
        global $post;
        if (!$post) {
            return;
        }
        
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
    private function find_hero_background_image($elements, $depth = 0) {
        if ($depth > 2) {
            return null;
        }
        
        foreach ($elements as $element) {
            if (isset($element['settings']['background_image']['url'])) {
                return $element['settings']['background_image']['url'];
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found_image = $this->find_hero_background_image($element['elements'], $depth + 1);
                if ($found_image) {
                    return $found_image;
                }
            }
        }
        
        return null;
    }
    
    private function search_elementor_hero_advanced($elements, $max_depth = 3, $current_depth = 0) {
        if ($current_depth >= $max_depth) {
            return null;
        }
        
        foreach ($elements as $index => $element) {
            if ($index > 2 && $current_depth === 0) {
                break;
            }
            
            if (isset($element['settings']['background_image']['url'])) {
                return $element['settings']['background_image']['url'];
            }
            
            if ($current_depth === 0 && isset($element['elements'])) {
                foreach ($element['elements'] as $column) {
                    if (isset($column['elements'])) {
                        foreach ($column['elements'] as $widget) {
                            if ($widget['widgetType'] === 'image' && 
                                isset($widget['settings']['image']['url'])) {
                                return $widget['settings']['image']['url'];
                            }
                        }
                    }
                }
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->search_elementor_hero_advanced($element['elements'], $max_depth, $current_depth + 1);
                if ($found) {
                    return $found;
                }
            }
        }
        
        return null;
    }
    
    private function find_hero_foreground_image($elements) {
        foreach ($elements as $element) {
            if ($element['widgetType'] === 'image') {
                $css_classes = isset($element['settings']['_css_classes']) ? $element['settings']['_css_classes'] : '';
                
                if (strpos($css_classes, 'hero-foreground-image') !== false || 
                    strpos($css_classes, 'heroimg') !== false) {
                    
                    if (isset($element['settings']['image']['url'])) {
                        return array(
                            'url' => $element['settings']['image']['url'],
                            'id' => isset($element['settings']['image']['id']) ? $element['settings']['image']['id'] : null
                        );
                    }
                }
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->find_hero_foreground_image($element['elements']);
                if ($found) {
                    return $found;
                }
            }
        }
        return null;
    }
    
    /**
     * Parse specific pages configuration
     */
    private function parse_specific_pages() {
        $specific_pages = array();
        $lines = explode("\n", $this->options['specific_pages']);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $parts = explode('|', $line, 2);
            if (count($parts) === 2) {
                $specific_pages[trim($parts[0])] = trim($parts[1]);
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
    
    /**
     * Output responsive preload tags
     */
    private function output_responsive_preload($image_url) {
        $image_id = attachment_url_to_postid($image_url);
        if ($image_id) {
            $this->output_responsive_preload_by_id($image_id);
        }
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
     * Script deferring
     */
    public function defer_scripts($tag, $handle) {
        if (!$this->options['enable_script_defer'] || is_admin()) {
            return $tag;
        }
        
        // Get excluded scripts
        $excluded_scripts = array_filter(array_map('trim', explode("\n", $this->options['exclude_scripts'])));
        
        if (in_array($handle, $excluded_scripts)) {
            return $tag;
        }
        
        // Get specific scripts to defer
        $scripts_to_defer = array_filter(array_map('trim', explode("\n", $this->options['scripts_to_defer'])));
        
        // If specific scripts are defined, only defer those
        if (!empty($scripts_to_defer)) {
            if (!in_array($handle, $scripts_to_defer)) {
                return $tag;
            }
        }
        
        if ($this->options['debug_mode']) {
            $tag = str_replace('<script', '<!-- CoreBoost: Deferring script: ' . $handle . ' --><script', $tag);
        }
        
        return str_replace(' src', ' defer src', $tag);
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
        if (!$this->should_defer_style($handle, $styles_to_defer)) {
            return $html;
        }
        
        if ($this->options['debug_mode']) {
            $html = '<!-- CoreBoost: Deferring CSS: ' . $handle . ' -->' . "\n" . $html;
        }
        
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
     * Check if a CSS handle should be deferred using pattern matching
     */
    private function should_defer_style($handle, $styles_to_defer) {
        foreach ($styles_to_defer as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) continue;
            
            // Exact match
            if ($handle === $pattern) {
                return true;
            }
            
            // Pattern matching (ends with -)
            if (substr($pattern, -1) === '-' && strpos($handle, rtrim($pattern, '-')) === 0) {
                return true;
            }
            
            // Wildcard matching (contains *)
            if (strpos($pattern, '*') !== false) {
                $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
                if (preg_match($regex, $handle)) {
                    return true;
                }
            }
            
            // Partial matching (contains pattern)
            if (strpos($handle, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Debug CSS detection and output information
     */
    public function debug_css_detection() {
        if (!$this->options['debug_mode'] || is_admin()) {
            return;
        }
        
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
            if (!isset($wp_styles->registered[$handle])) {
                continue;
            }
            
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
        if (!$this->options['debug_mode'] || is_admin()) {
            return;
        }
        
        global $wp_styles;
        
        if (isset($wp_styles->queue) && !empty($wp_styles->queue)) {
            $styles_to_defer = $this->get_styles_to_defer();
            
            foreach ($wp_styles->queue as $handle) {
                if (isset($wp_styles->registered[$handle])) {
                    $should_defer = $this->should_defer_style($handle, $styles_to_defer);
                    if ($should_defer) {
                        error_log("CoreBoost: Will defer CSS - Handle: {$handle}");
                    }
                }
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

// Initialize the plugin
function coreboost_init() {
    return CoreBoost::get_instance();
}

// Hook into plugins_loaded to ensure all plugins are loaded
add_action('plugins_loaded', 'coreboost_init');

// Clear cache when Elementor data is updated
add_action('elementor/editor/after_save', function($post_id) {
    delete_transient('coreboost_hero_' . $post_id);
});
