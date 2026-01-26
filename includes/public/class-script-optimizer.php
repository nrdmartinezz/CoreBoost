<?php
/**
 * Script defer and async optimization
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Context_Helper;// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Script_Optimizer
 */
class Script_Optimizer {
    
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
     * Script exclusions instance
     *
     * @var Script_Exclusions
     */
    private $exclusions;
    
    /**
     * Event hijacker instance (Phase 4)
     *
     * @var Event_Hijacker
     */
    private $event_hijacker;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        // Only register on frontend
        if (!is_admin()) {
            $this->exclusions = new Script_Exclusions($options);
            
            // Initialize event hijacker if enabled (Phase 4)
            if (!empty($options['enable_event_hijacking'])) {
                $this->event_hijacker = new Event_Hijacker($options);
            }
            
            $this->define_hooks();
        }
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        $this->loader->add_filter('script_loader_tag', $this, 'defer_scripts', 10, 2);
        $this->loader->add_action('wp_head', $this, 'add_script_resource_hints', 2);
    }
    
    /**
     * Script deferring with async support for independent scripts
     * Ensures jQuery-dependent scripts use defer (not async) to maintain execution order
     */
    public function defer_scripts($tag, $handle) {
        if (!$this->options['enable_script_defer'] || is_admin()) return $tag;
        
        // Check excluded scripts using new exclusions system
        if ($this->exclusions->is_excluded($handle)) {
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
        }
        
        if ($use_async) {
            return str_replace(' src', ' async src', $tag);
        } elseif ($use_defer) {
            return str_replace(' src', ' defer src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Add resource hints for critical scripts
     */
    public function add_script_resource_hints() {
        // Skip if disabled or in admin/AJAX/Elementor preview contexts
        if (!$this->options['enable_script_defer'] || Context_Helper::should_skip_optimization()) {
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
            }
        }
    }
}
