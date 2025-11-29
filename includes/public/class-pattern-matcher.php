<?php
/**
 * Advanced Pattern Matching System (Phase 3)
 *
 * Provides sophisticated pattern matching capabilities:
 * - Regex pattern support with caching
 * - Wildcard pattern matching
 * - Plugin-specific pattern profiles
 * - Smart pattern compilation and optimization
 * - Pattern analytics and debugging
 *
 * @package CoreBoost
 * @since 2.3.0
 */

namespace CoreBoost\PublicCore;



// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Pattern_Matcher
 *
 * Handles pattern matching using multiple strategies:
 * - Exact match (fastest)
 * - Wildcard matching (flexible)
 * - Regex matching (most powerful)
 */
class Pattern_Matcher {
    
    /**
     * Compiled regex patterns cache
     *
     * @var array
     */
    private $regex_cache = [];
    
    /**
     * Plugin-specific patterns
     *
     * @var array
     */
    private $plugin_profiles = [];
    
    /**
     * Wildcard patterns (e.g., "elementor-*")
     *
     * @var array
     */
    private $wildcard_patterns = [];
    
    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug_mode;
    
    /**
     * Pattern performance stats
     *
     * @var array
     */
    private $stats = [
        'exact_matches' => 0,
        'wildcard_matches' => 0,
        'regex_matches' => 0,
        'total_checks' => 0,
    ];
    
    /**
     * Constructor
     *
     * @param bool $debug_mode Enable debug logging
     */
    public function __construct($debug_mode = false) {
        $this->debug_mode = $debug_mode;
        $this->initialize_plugin_profiles();
    }
    
    /**
     * Initialize plugin-specific pattern profiles
     *
     * These profiles group related scripts from popular plugins
     * making it easier to manage complex ecosystems.
     *
     * @return void
     */
    private function initialize_plugin_profiles() {
        // Page Builders
        $this->plugin_profiles['elementor'] = [
            'exact' => ['elementor', 'elementor-frontend', 'elementor-pro-frontend', 'elementor-pro-widgets'],
            'wildcard' => ['elementor-*'],
            'regex' => ['/^elementor[-_]/i'],
        ];
        
        $this->plugin_profiles['builder-divi'] = [
            'exact' => ['divi-core', 'divi-theme', 'divi-builder'],
            'wildcard' => ['divi-*', 'et-builder-*'],
            'regex' => ['/^et_builder|^divi_/i'],
        ];
        
        // E-Commerce
        $this->plugin_profiles['woocommerce'] = [
            'exact' => ['woocommerce', 'wc-cart-fragments', 'wc-add-to-cart', 'wc-checkout', 'wc-single-product'],
            'wildcard' => ['wc-*', 'woocommerce-*'],
            'regex' => ['/^woocommerce[-_]|^wc[-_]/i'],
        ];
        
        $this->plugin_profiles['easy-digital-downloads'] = [
            'exact' => ['edd', 'edd-checkout', 'edd-cart'],
            'wildcard' => ['edd-*'],
            'regex' => ['/^edd[-_]/i'],
        ];
        
        // Contact Forms
        $this->plugin_profiles['contact-form-7'] = [
            'exact' => ['contact-form-7', 'cf7-js', 'wpcf7-js'],
            'wildcard' => ['cf7-*', 'wpcf7-*'],
            'regex' => ['/^cf7[-_]|^wpcf7[-_]|^contact[-_]form[-_]7/i'],
        ];
        
        $this->plugin_profiles['gravity-forms'] = [
            'exact' => ['gform_jquery_json', 'gform_json', 'gform_gravityforms', 'gform_forms'],
            'wildcard' => ['gform-*', 'gf-*'],
            'regex' => ['/^gform[-_]|^gravity[-_]forms|^gf[-_]/i'],
        ];
        
        $this->plugin_profiles['wpforms'] = [
            'exact' => ['wpforms', 'wpforms-full', 'wpforms-jquery-validation', 'wpforms-utils'],
            'wildcard' => ['wpforms-*'],
            'regex' => ['/^wpforms[-_]/i'],
        ];
        
        // Analytics & Tracking
        $this->plugin_profiles['analytics' ] = [
            'exact' => ['google-analytics', 'ga', 'gtag', 'gtm', 'analytics'],
            'wildcard' => ['ga-*', 'analytics-*', 'gtm-*'],
            'regex' => ['/^ga[-_]|^google[-_]analytics|^gtag[-_]|^gtm[-_]|^analytics[-_]/i'],
        ];
        
        $this->plugin_profiles['heatmap-tools'] = [
            'exact' => ['hotjar', 'clarity', 'microsoft-clarity', 'session-cam'],
            'wildcard' => ['heatmap-*', 'tracking-*'],
            'regex' => ['/^hotjar|^clarity|^microsoft[-_]clarity|^session[-_]cam/i'],
        ];
        
        // jQuery & Dependencies
        $this->plugin_profiles['jquery-ecosystem'] = [
            'exact' => ['jquery', 'jquery-core', 'jquery-migrate', 'jquery-ui-core'],
            'wildcard' => ['jquery-ui-*', 'jquery-*'],
            'regex' => ['/^jquery[-_]ui[-_]|^jquery[-_]/i'],
        ];
        
        // Theme & Core
        $this->plugin_profiles['wordpress-core'] = [
            'exact' => ['wp-embed', 'wp-api', 'wp-block-library', 'wp-editor'],
            'wildcard' => ['wp-*'],
            'regex' => ['/^wp[-_]/i'],
        ];
        
        // Allow custom profiles via filter
        $this->plugin_profiles = apply_filters('coreboost_pattern_profiles', $this->plugin_profiles);
    }
    
    /**
     * Check if a handle matches any pattern
     *
     * Uses optimized pattern matching strategy:
     * 1. Fast: Exact match lookup
     * 2. Faster: Wildcard pattern matching
     * 3. Flexible: Regex pattern matching
     *
     * @param string $handle Script handle to check
     * @param array $patterns Array of patterns (exact, wildcard, regex)
     * @return bool True if handle matches any pattern
     */
    public function matches($handle, $patterns = []) {
        $this->stats['total_checks']++;
        
        if (empty($patterns)) {
            return false;
        }
        
        // Strategy 1: Exact match (fastest)
        if ($this->exact_match($handle, $patterns)) {
            $this->stats['exact_matches']++;
            return true;
        }
        
        // Strategy 2: Wildcard matching
        if ($this->wildcard_match($handle, $patterns)) {
            $this->stats['wildcard_matches']++;
            return true;
        }
        
        // Strategy 3: Regex matching (most flexible)
        if ($this->regex_match($handle, $patterns)) {
            $this->stats['regex_matches']++;
            return true;
        }
        
        return false;
    }
    
    /**
     * Exact string matching
     *
     * @param string $handle Script handle
     * @param array $patterns Exact patterns to match against
     * @return bool True if match found
     */
    private function exact_match($handle, $patterns) {
        return isset($patterns['exact']) && in_array($handle, $patterns['exact'], true);
    }
    
    /**
     * Wildcard pattern matching
     *
     * Supports patterns like:
     * - "jquery-ui-*" matches "jquery-ui-dialog", "jquery-ui-button", etc.
     * - "custom-*" matches "custom-script-1", "custom-handler-2", etc.
     * - "*-plugin" matches "my-plugin", "your-plugin", etc.
     *
     * @param string $handle Script handle
     * @param array $patterns Wildcard patterns
     * @return bool True if match found
     */
    private function wildcard_match($handle, $patterns) {
        if (empty($patterns['wildcard'])) {
            return false;
        }
        
        foreach ($patterns['wildcard'] as $pattern) {
            // Convert wildcard to regex
            // Escape special regex chars except *
            $regex = preg_quote($pattern, '/');
            // Replace escaped * with regex match-all
            $regex = str_replace('\*', '.*', $regex);
            $regex = "/^{$regex}$/i";
            
            if (@preg_match($regex, $handle)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Regex pattern matching with caching
     *
     * Patterns should be valid PHP regex (e.g., "/^pattern$/i")
     * Caches compiled patterns for performance.
     *
     * @param string $handle Script handle
     * @param array $patterns Regex patterns
     * @return bool True if match found
     */
    private function regex_match($handle, $patterns) {
        if (empty($patterns['regex'])) {
            return false;
        }
        
        foreach ($patterns['regex'] as $pattern) {
            // Check cache first
            $cache_key = md5($pattern);
            if (!isset($this->regex_cache[$cache_key])) {
                // Validate and cache pattern
                if (@preg_match($pattern, '') !== false) {
                    $this->regex_cache[$cache_key] = $pattern;
                } else {
                    // Invalid regex, skip
                    continue;
                }
            }
            
            // Test pattern
            if (@preg_match($this->regex_cache[$cache_key], $handle)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get plugin profile patterns
     *
     * Returns pattern set for a specific plugin profile
     *
     * @param string $profile_key Plugin profile key
     * @return array|false Pattern set or false if not found
     */
    public function get_plugin_profile($profile_key) {
        return isset($this->plugin_profiles[$profile_key]) ? $this->plugin_profiles[$profile_key] : false;
    }
    
    /**
     * Get all available plugin profiles
     *
     * @return array Available plugin profiles
     */
    public function get_available_profiles() {
        return array_keys($this->plugin_profiles);
    }
    
    /**
     * Get pattern statistics
     *
     * Useful for performance monitoring and optimization
     *
     * @return array Statistics including match types and counts
     */
    public function get_stats() {
        return [
            'total_checks' => $this->stats['total_checks'],
            'exact_matches' => $this->stats['exact_matches'],
            'wildcard_matches' => $this->stats['wildcard_matches'],
            'regex_matches' => $this->stats['regex_matches'],
            'cache_size' => count($this->regex_cache),
            'exact_match_rate' => $this->stats['total_checks'] > 0 ? round(($this->stats['exact_matches'] / $this->stats['total_checks']) * 100, 2) : 0,
            'wildcard_match_rate' => $this->stats['total_checks'] > 0 ? round(($this->stats['wildcard_matches'] / $this->stats['total_checks']) * 100, 2) : 0,
            'regex_match_rate' => $this->stats['total_checks'] > 0 ? round(($this->stats['regex_matches'] / $this->stats['total_checks']) * 100, 2) : 0,
        ];
    }
    
    /**
     * Reset statistics
     *
     * @return void
     */
    public function reset_stats() {
        $this->stats = [
            'exact_matches' => 0,
            'wildcard_matches' => 0,
            'regex_matches' => 0,
            'total_checks' => 0,
        ];
    }
}
