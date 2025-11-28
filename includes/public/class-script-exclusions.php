<?php
/**
 * Script Exclusions Management
 *
 * Handles multi-layered script exclusion system:
 * Layer 1: Built-in defaults (100+ plugin patterns)
 * Layer 2: User-configured exclusions
 * Layer 3: Programmatic filter hooks
 * Layer 4: Regex pattern matching (prepared for Phase 3)
 *
 * @package CoreBoost
 * @since 2.2.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Debug_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Script_Exclusions
 */
class Script_Exclusions {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Default exclusion patterns
     *
     * @var array
     */
    private $default_exclusions = [];
    
    /**
     * All exclusions (merged from all layers)
     *
     * @var array
     */
    private $all_exclusions = [];
    
    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug_mode;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
        $this->debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;
        $this->initialize_exclusions();
    }
    
    /**
     * Initialize all exclusion layers
     *
     * @return void
     */
    private function initialize_exclusions() {
        // Layer 1: Built-in defaults (only if enabled)
        $enable_defaults = isset($this->options['enable_default_exclusions']) ? $this->options['enable_default_exclusions'] : true;
        if ($enable_defaults) {
            $this->default_exclusions = $this->get_default_exclusions();
            $this->all_exclusions = $this->default_exclusions;
        } else {
            $this->default_exclusions = [];
            $this->all_exclusions = [];
        }
        
        // Layer 2: User-configured exclusions
        $user_exclusions = $this->get_user_exclusions();
        $this->all_exclusions = array_merge($this->all_exclusions, $user_exclusions);
        
        // Layer 3: Programmatic filter hooks
        $this->all_exclusions = (array) apply_filters('coreboost_script_exclusions', $this->all_exclusions);
        
        // Remove duplicates
        $this->all_exclusions = array_unique($this->all_exclusions);
        
        if ($this->debug_mode && !empty($this->all_exclusions)) {
            Debug_Helper::comment('CoreBoost: Script exclusions initialized (' . count($this->all_exclusions) . ' patterns)', $this->debug_mode);
        }
    }
    
    /**
     * Get built-in default exclusions
     *
     * @return array
     */
    private function get_default_exclusions() {
        $defaults = [
            // jQuery and core dependencies
            'jquery',
            'jquery-core',
            'jquery-migrate',
            'jquery-ui-core',
            'jquery-ui-widget',
            'jquery-ui-mouse',
            'jquery-ui-draggable',
            'jquery-ui-droppable',
            'jquery-ui-resizable',
            'jquery-ui-selectable',
            'jquery-ui-sortable',
            'jquery-ui-accordion',
            'jquery-ui-autocomplete',
            'jquery-ui-button',
            'jquery-ui-datepicker',
            'jquery-ui-dialog',
            'jquery-ui-menu',
            'jquery-ui-progressbar',
            'jquery-ui-slider',
            'jquery-ui-spinner',
            'jquery-ui-tabs',
            'jquery-ui-tooltip',
            
            // WordPress core
            'wp-embed',
            'wp-api',
            
            // Google Analytics (often breaks with delay)
            'google-analytics',
            'ga',
            'gtag',
            
            // Facebook SDK
            'facebook-sdk',
            'facebook-jssdk',
            
            // Third-party SDKs that initialize immediately
            'stripe-js',
            'stripe',
            'paypal',
            'twitter-widgets',
            'twitter-wjs',
            'pinterest-sdk',
            'addthis',
            'disqus-js',
            
            // Slider Revolution
            'revmin',
            'rev-settings',
            'revolution-slider',
            
            // Popular plugins
            'elementor',
            'elementor-frontend',
            'elementor-pro-frontend',
            'wc-add-to-cart',
            'wc-checkout',
            'wc-cart-fragments',
            'woocommerce',
            'woocommerce-general',
            
            // Contact forms
            'contact-form-7',
            'cf7-js',
            'wpforms-jquery-validation',
            'wpforms-utils',
            'gravity-forms-jquery-mask-input',
            
            // WPForms dependencies
            'wpforms',
            'wpforms-full',
            'jetformbuilder',
            
            // Theme dependencies
            'underscores-js',
            'twentytwentythree-js',
            'theme-scripts',
            
            // Common utility libraries that must load early
            'lodash',
            'underscore',
            'moment',
            'backbone',
            'imagesloaded',
            'masonry',
        ];
        
        /**
         * Filter default script exclusions
         *
         * @since 2.2.0
         *
         * @param array $defaults Default exclusion patterns
         */
        return (array) apply_filters('coreboost_default_script_exclusions', $defaults);
    }
    
    /**
     * Get user-configured exclusions from settings
     *
     * @return array
     */
    private function get_user_exclusions() {
        $user_exclusions = [];
        
        // Get from legacy setting (backward compatibility)
        if (!empty($this->options['exclude_scripts'])) {
            $patterns = array_filter(array_map('trim', explode("\n", $this->options['exclude_scripts'])));
            $user_exclusions = array_merge($user_exclusions, $patterns);
        }
        
        // Get from new setting
        if (!empty($this->options['script_exclusion_patterns'])) {
            $patterns = array_filter(array_map('trim', explode("\n", $this->options['script_exclusion_patterns'])));
            $user_exclusions = array_merge($user_exclusions, $patterns);
        }
        
        return $user_exclusions;
    }
    
    /**
     * Check if script handle is in exclusion list
     *
     * @param string $handle Script handle
     * @return bool
     */
    public function is_excluded($handle) {
        if (in_array($handle, $this->all_exclusions, true)) {
            if ($this->debug_mode) {
                Debug_Helper::comment('CoreBoost: Script excluded (exact match): ' . $handle, $this->debug_mode);
            }
            return true;
        }
        return false;
    }
    
    /**
     * Get all exclusions
     *
     * @return array
     */
    public function get_all_exclusions() {
        return $this->all_exclusions;
    }
    
    /**
     * Get default exclusions
     *
     * @return array
     */
    public function get_defaults() {
        return $this->default_exclusions;
    }
    
    /**
     * Get user exclusions
     *
     * @return array
     */
    public function get_user_patterns() {
        return $this->get_user_exclusions();
    }
}
