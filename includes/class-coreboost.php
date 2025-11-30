<?php
/**
 * Main CoreBoost plugin class
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost;

use CoreBoost\Admin\Admin;
use CoreBoost\PublicCore\Hero_Optimizer;
use CoreBoost\PublicCore\Script_Optimizer;
use CoreBoost\PublicCore\CSS_Optimizer;
use CoreBoost\PublicCore\Font_Optimizer;
use CoreBoost\PublicCore\Resource_Remover;
use CoreBoost\PublicCore\Tag_Manager;
use CoreBoost\PublicCore\Image_Optimizer;
use CoreBoost\PublicCore\Image_Format_Optimizer;
use CoreBoost\PublicCore\Image_Variant_Lifecycle_Manager;
use CoreBoost\Admin\Image_Variant_Admin_Tools;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CoreBoost
 */
class CoreBoost {
    
    /**
     * Single instance of the class
     *
     * @var CoreBoost
     */
    private static $instance = null;
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Loader instance
     *
     * @var Loader
     */
    private $loader;
    
    /**
     * Admin instance
     *
     * @var Admin
     */
    private $admin;
    
    /**
     * Hero optimizer instance
     *
     * @var Hero_Optimizer
     */
    private $hero_optimizer;
    
    /**
     * Script optimizer instance
     *
     * @var Script_Optimizer
     */
    private $script_optimizer;
    
    /**
     * CSS optimizer instance
     *
     * @var CSS_Optimizer
     */
    private $css_optimizer;
    
    /**
     * Font optimizer instance
     *
     * @var Font_Optimizer
     */
    private $font_optimizer;
    
    /**
     * Resource remover instance
     *
     * @var Resource_Remover
     */
    private $resource_remover;
    
    /**
     * Tag manager instance
     *
     * @var Tag_Manager
     */
    private $tag_manager;
    
    /**
     * Image optimizer instance (Phase 1 & 2)
     *
     * @var Image_Optimizer
     */
    private $image_optimizer;
    
    /**
     * Image variant admin tools instance (Phase 2.5)
     *
     * @var Image_Variant_Admin_Tools
     */
    private $image_variant_admin_tools;
    
    /**
     * Analytics engine instance
     *
     * @var \CoreBoost_Analytics_Engine
     */
    private $analytics_engine;
    
    /**
     * Dashboard UI instance
     *
     * @var \CoreBoost_Dashboard_UI
     */
    private $dashboard_ui;
    
    /**
     * Get single instance
     *
     * @return CoreBoost
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
        $this->load_dependencies();
        $this->set_locale();
        $this->options = get_option('coreboost_options', $this->get_default_options());
        $this->define_hooks();
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        $this->loader = new Loader();
    }
    
    /**
     * Set the plugin locale
     */
    private function set_locale() {
        $this->loader->add_action('plugins_loaded', $this, 'load_plugin_textdomain');
    }
    
    /**
     * Load plugin text domain
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('coreboost', false, dirname(COREBOOST_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Define all hooks
     */
    private function define_hooks() {
        // CRITICAL: Skip ALL optimization in Elementor preview/AJAX contexts
        if (defined('COREBOOST_ELEMENTOR_PREVIEW') && COREBOOST_ELEMENTOR_PREVIEW) {
            return;
        }
        
        // Initialize admin area
        if (is_admin()) {
            $this->admin = new Admin($this->options, $this->loader);
            // Initialize dashboard UI (Phase 5) - will lazy-load analytics engine when needed
            $this->dashboard_ui = new \CoreBoost_Dashboard_UI(null, $this->options);
        }
        
        // Initialize frontend optimizers
        // Skip on admin pages and preview contexts (Elementor, etc)
        $elementor_preview = isset($_GET['elementor-preview']) ? sanitize_text_field( wp_unslash( $_GET['elementor-preview'] ) ) : '';
        if (!is_admin() && !wp_doing_ajax() && empty($elementor_preview)) {
            // Initialize analytics engine (Phase 5) - frontend only
            $this->analytics_engine = new \CoreBoost_Analytics_Engine($this->options, defined('WP_DEBUG') && WP_DEBUG);
            
            $this->hero_optimizer = new Hero_Optimizer($this->options, $this->loader);
            $this->script_optimizer = new Script_Optimizer($this->options, $this->loader);
            $this->css_optimizer = new CSS_Optimizer($this->options, $this->loader);
            $this->font_optimizer = new Font_Optimizer($this->options, $this->loader);
            $this->resource_remover = new Resource_Remover($this->options, $this->loader);
            $this->tag_manager = new Tag_Manager($this->options);
            $this->tag_manager->register_hooks($this->loader);
            
            // Initialize image optimizer (Phase 1 & 2)
            $this->image_optimizer = new Image_Optimizer($this->options, $this->loader);
            
            // Initialize video facade for click-to-play videos
            new \CoreBoost\PublicCore\Video_Facade($this->options, $this->loader);
        }
        
        // Initialize admin tools (Phase 2.5)
        if (is_admin() && !wp_doing_ajax()) {
            $this->image_variant_admin_tools = new Image_Variant_Admin_Tools(
                $this->options,
                null  // Will be instantiated when needed
            );
            $this->image_variant_admin_tools->register_hooks($this->loader);
        }
    }    
    /**
     * Run the loader to execute all hooks
     */
    public function run() {
        $this->loader->run();
    }
    
    /**
     * Get plugin options
     *
     * @return array
     */
    public function get_options() {
        return $this->options;
    }
    
    /**
     * Get Hero_Optimizer instance
     * Allows other classes to reuse singleton instead of creating new instances
     *
     * @return Hero_Optimizer|null
     */
    public function get_hero_optimizer() {
        return $this->hero_optimizer;
    }
    
    /**
     * Get Analytics_Engine instance
     * Allows other classes to access the analytics engine for metrics recording
     *
     * @return \CoreBoost_Analytics_Engine|null
     */
    public function get_analytics_engine() {
        return $this->analytics_engine;
    }
    
    /**
     * Get default options
     *
     * @return array
     */
    private function get_default_options() {
        return array(
            'preload_method' => 'elementor_data',
            'enable_script_defer' => false,
            'enable_css_defer' => false,
            'enable_foreground_conversion' => false,
            'enable_responsive_preload' => false,
            'enable_caching' => false,
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
            'unused_js_list' => '',
            'enable_inline_script_removal' => false,
            'inline_script_ids' => '',
            'enable_inline_style_removal' => false,
            'inline_style_ids' => '',
            // Custom Tag Manager settings (v2.0.2)
            'tag_head_scripts' => '',
            'tag_body_scripts' => '',
            'tag_footer_scripts' => '',
            'tag_load_strategy' => 'balanced',
            'tag_custom_delay' => 3000,
            // Script Exclusions (v2.2.0 Phase 1)
            'script_exclusion_patterns' => '',
            'enable_default_exclusions' => true,
            // Advanced Pattern Matching (v2.3.0 Phase 3)
            'script_wildcard_patterns' => '',
            'script_regex_patterns' => '',
            'script_plugin_profiles' => '',
            // Event Hijacking (v2.4.0 Phase 4)
            'enable_event_hijacking' => false,
            'event_hijack_triggers' => 'user_interaction,browser_idle',
            'script_load_priority' => 'standard',
            // Dashboard & Analytics (v2.5.0 Phase 5)
            'enable_analytics' => true,
            'analytics_retention_days' => 30,
            'enable_ab_testing' => false,
            'enable_recommendations' => true,
            // Smart YouTube blocking (v2.0.3)
            'smart_youtube_blocking' => false
        );
    }
}
