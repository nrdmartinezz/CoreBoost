<?php
/**
 * Delay JavaScript Optimizer
 *
 * Delays JavaScript execution until user interaction or specified triggers
 * to reduce Total Blocking Time (TBT) and improve Time to Interactive (TTI).
 *
 * Features:
 * - Replaces script src with data-coreboost-src and type="coreboost/delayed"
 * - Injects loader script that restores scripts on trigger
 * - Supports multiple trigger strategies (user interaction, browser idle, custom delay)
 * - Configurable exclusions with wildcard and regex support
 * - Optional inline script delaying
 *
 * @package    CoreBoost
 * @subpackage CoreBoost/Public
 * @since      3.1.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Context_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Delay_JS_Optimizer
 */
class Delay_JS_Optimizer {

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
     * Pattern matcher instance
     *
     * @var Pattern_Matcher
     */
    private $pattern_matcher;

    /**
     * Default exclusion patterns (always excluded from delay)
     *
     * @var array
     */
    private $default_delay_exclusions = [
        // jQuery core - required for most WordPress functionality
        'jquery',
        'jquery-core',
        'jquery-migrate',
        'jquery-ui-core',
        'jquery-ui-widget',
        'jquery-ui-position',
        'jquery-ui-mouse',
        'jquery-ui-sortable',
        'jquery-ui-draggable',
        'jquery-ui-droppable',
        'jquery-ui-resizable',
        'jquery-effects-core',
        'jquery-effects-slide',
        
        // WordPress core essentials
        'wp-embed',
        'wp-polyfill',
        'wp-element',
        'wp-data',
        'wp-compose',
        'wp-date',
        'wp-hooks',
        'wp-i18n',
        'wp-api-fetch',
        'wp-components',
        'wp-rich-text',
        'wp-primitives',
        'wp-editor',
        'wp-block-editor',
        'wp-blocks',
        'wp-url',
        'wp-dom-ready',
        
        // React and dependencies
        'react',
        'react-dom',
        'react-jsx-runtime',
        'moment',
        'lodash',
        'underscore',
        'backbone',
        'marionette',
        
        // Elementor scripts that need immediate initialization
        'elementor-frontend',
        'elementor-frontend-modules',
        'elementor-pro-frontend',
        'elementor-waypoints',
        'elementor-common',
        'elementor-common-modules',
        'elementor-dialog',
        'swiper',
        'e-swiper',
        
        // CoreBoost's own scripts
        'coreboost',
        'coreboost-delay-loader',
    ];

    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        
        // Initialize pattern matcher for custom exclusions
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;
        $this->pattern_matcher = new Pattern_Matcher($debug_mode);
        
        // Only initialize if enabled and on frontend
        if (!is_admin() && $this->is_enabled()) {
            $this->define_hooks();
        }
    }

    /**
     * Check if delay JS is enabled
     *
     * @return bool
     */
    private function is_enabled() {
        return isset($this->options['enable_delay_js']) && $this->options['enable_delay_js'];
    }

    /**
     * Define hooks
     */
    private function define_hooks() {
        // Filter script tags to add delay
        $this->loader->add_filter('script_loader_tag', $this, 'delay_script_tag', 99, 3);
        
        // Add the delay loader script in footer
        $this->loader->add_action('wp_footer', $this, 'output_delay_loader', 1);
        
        // Handle inline scripts if enabled
        if ($this->should_delay_inline()) {
            $this->loader->add_action('wp_footer', $this, 'delay_inline_scripts', 0);
        }
    }

    /**
     * Check if inline script delaying is enabled
     *
     * @return bool
     */
    private function should_delay_inline() {
        return isset($this->options['delay_js_include_inline']) && $this->options['delay_js_include_inline'];
    }

    /**
     * Check if a script should be excluded from delay
     *
     * @param string $handle Script handle
     * @param string $src Script source URL
     * @return bool True if should be excluded (not delayed)
     */
    private function is_excluded($handle, $src = '') {
        // Always exclude core delay exclusions
        if (in_array($handle, $this->default_delay_exclusions, true)) {
            return true;
        }
        
        // Exclude Elementor scripts by URL path (catches all Elementor assets)
        if ($src && strpos($src, '/elementor/') !== false) {
            return true;
        }
        
        // Exclude Elementor Pro scripts by URL path
        if ($src && strpos($src, '/elementor-pro/') !== false) {
            return true;
        }

        // Check default exclusions if enabled (uses same list as script defer)
        if ($this->use_default_exclusions()) {
            $script_exclusions = new Script_Exclusions($this->options);
            if ($script_exclusions->is_excluded($handle)) {
                return true;
            }
        }

        // Check custom exclusion patterns
        $custom_exclusions = $this->get_custom_exclusions();
        foreach ($custom_exclusions as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) {
                continue;
            }

            // Check if it's a regex pattern (starts and ends with /)
            if (preg_match('/^\/.*\/[a-z]*$/i', $pattern)) {
                // Regex pattern - test against handle and src
                if (@preg_match($pattern, $handle) || ($src && @preg_match($pattern, $src))) {
                    return true;
                }
            }
            // Check for wildcard pattern
            elseif (strpos($pattern, '*') !== false) {
                $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';
                if (@preg_match($regex, $handle) || ($src && @preg_match($regex, $src))) {
                    return true;
                }
            }
            // Exact match or substring match
            else {
                if ($handle === $pattern || strpos($handle, $pattern) !== false) {
                    return true;
                }
                if ($src && (strpos($src, $pattern) !== false)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if default exclusions should be used
     *
     * @return bool
     */
    private function use_default_exclusions() {
        return isset($this->options['delay_js_use_default_exclusions']) 
            ? $this->options['delay_js_use_default_exclusions'] 
            : true;
    }

    /**
     * Get custom exclusion patterns
     *
     * @return array
     */
    private function get_custom_exclusions() {
        $exclusions = isset($this->options['delay_js_exclusions']) 
            ? $this->options['delay_js_exclusions'] 
            : '';
        
        if (empty($exclusions)) {
            return [];
        }

        return array_filter(array_map('trim', explode("\n", $exclusions)));
    }

    /**
     * Get trigger strategy
     *
     * @return string
     */
    private function get_trigger() {
        return isset($this->options['delay_js_trigger']) 
            ? $this->options['delay_js_trigger'] 
            : 'user_interaction';
    }

    /**
     * Get fallback timeout
     *
     * @return int
     */
    private function get_timeout() {
        return isset($this->options['delay_js_timeout']) 
            ? intval($this->options['delay_js_timeout']) 
            : 10000;
    }

    /**
     * Get custom delay value
     *
     * @return int
     */
    private function get_custom_delay() {
        return isset($this->options['delay_js_custom_delay']) 
            ? intval($this->options['delay_js_custom_delay']) 
            : 3000;
    }

    /**
     * Filter script tags to add delay
     *
     * @param string $tag Script tag HTML
     * @param string $handle Script handle
     * @param string $src Script source URL
     * @return string Modified script tag
     */
    public function delay_script_tag($tag, $handle, $src) {
        // Skip optimization in certain contexts
        if (Context_Helper::should_skip_optimization()) {
            return $tag;
        }

        // Only process actual script tags
        if (strpos($tag, '<script') === false) {
            return $tag;
        }

        // Check if this script should be excluded
        if ($this->is_excluded($handle, $src)) {
            return $tag;
        }

        // Don't delay scripts that are already modified by other CoreBoost features
        if (strpos($tag, 'coreboost/delayed') !== false) {
            return $tag;
        }

        // Don't delay inline scripts here (handled separately)
        if (empty($src)) {
            return $tag;
        }
        
        // Don't delay scripts with module type (ES6 modules need to load in order)
        if (preg_match('/type=["\']module["\']/i', $tag)) {
            return $tag;
        }

        // Replace script type and src to prevent execution
        // Change: <script src="..." -> <script type="coreboost/delayed" data-coreboost-src="..."
        $delayed_tag = $tag;

        // Handle script tags with type attribute
        if (preg_match('/type=["\'][^"\']*["\']/i', $delayed_tag)) {
            // Replace existing type
            $delayed_tag = preg_replace(
                '/type=["\'][^"\']*["\']/i',
                'type="coreboost/delayed"',
                $delayed_tag
            );
        } else {
            // Add type attribute
            $delayed_tag = str_replace('<script ', '<script type="coreboost/delayed" ', $delayed_tag);
        }

        // Replace src with data-coreboost-src
        $delayed_tag = preg_replace(
            '/\ssrc=["\']([^"\']*)["\']/',
            ' data-coreboost-src="$1"',
            $delayed_tag
        );

        // Add handle as data attribute for debugging
        $delayed_tag = str_replace(
            'type="coreboost/delayed"',
            'type="coreboost/delayed" data-coreboost-handle="' . esc_attr($handle) . '"',
            $delayed_tag
        );

        return $delayed_tag;
    }

    /**
     * Output the delay loader script
     *
     * This script runs on page load and sets up the trigger listeners
     * to restore delayed scripts when the trigger fires.
     */
    public function output_delay_loader() {
        // Skip optimization in certain contexts
        if (Context_Helper::should_skip_optimization()) {
            return;
        }

        $trigger = $this->get_trigger();
        $timeout = $this->get_timeout();
        $custom_delay = $this->get_custom_delay();

        ?>
<script id="coreboost-delay-loader" defer>
/**
 * CoreBoost Delay JavaScript Loader
 * Restores delayed scripts on user interaction or specified trigger
 */
(function() {
    'use strict';

    var loaded = false;
    var config = {
        trigger: <?php echo wp_json_encode($trigger); ?>,
        timeout: <?php echo intval($timeout); ?>,
        customDelay: <?php echo intval($custom_delay); ?>
    };

    /**
     * Load all delayed scripts
     */
    function loadDelayedScripts() {
        if (loaded) return;
        loaded = true;

        // Remove event listeners
        removeListeners();

        // Clear fallback timeout
        if (window._cbDelayTimeout) {
            clearTimeout(window._cbDelayTimeout);
        }

        // Fire event for analytics/debugging
        document.dispatchEvent(new CustomEvent('coreboost_delay_scripts_loading'));

        // Find all delayed scripts
        var scripts = document.querySelectorAll('script[type="coreboost/delayed"]');
        
        // Load scripts in order (preserving document order)
        var loadQueue = [];
        scripts.forEach(function(script) {
            loadQueue.push({
                src: script.getAttribute('data-coreboost-src'),
                handle: script.getAttribute('data-coreboost-handle'),
                element: script
            });
        });

        // Sequential loading to preserve dependency order
        function loadNext(index) {
            if (index >= loadQueue.length) {
                document.dispatchEvent(new CustomEvent('coreboost_delay_scripts_loaded'));
                return;
            }

            var item = loadQueue[index];
            if (!item.src) {
                loadNext(index + 1);
                return;
            }

            var newScript = document.createElement('script');
            newScript.src = item.src;
            
            // Copy other attributes (except type and data-coreboost-*)
            Array.from(item.element.attributes).forEach(function(attr) {
                if (attr.name !== 'type' && 
                    attr.name !== 'data-coreboost-src' && 
                    attr.name !== 'data-coreboost-handle') {
                    newScript.setAttribute(attr.name, attr.value);
                }
            });

            newScript.onload = newScript.onerror = function() {
                loadNext(index + 1);
            };

            // Replace the delayed script with the real one
            item.element.parentNode.replaceChild(newScript, item.element);
        }

        loadNext(0);
    }

    /**
     * Setup user interaction listeners
     */
    function setupUserInteractionListeners() {
        var events = ['mousedown', 'mousemove', 'touchstart', 'touchmove', 'scroll', 'keydown', 'wheel'];
        
        events.forEach(function(event) {
            document.addEventListener(event, loadDelayedScripts, { passive: true, capture: true, once: true });
            window.addEventListener(event, loadDelayedScripts, { passive: true, capture: true, once: true });
        });
    }

    /**
     * Remove all event listeners
     */
    function removeListeners() {
        var events = ['mousedown', 'mousemove', 'touchstart', 'touchmove', 'scroll', 'keydown', 'wheel'];
        
        events.forEach(function(event) {
            document.removeEventListener(event, loadDelayedScripts, { passive: true, capture: true });
            window.removeEventListener(event, loadDelayedScripts, { passive: true, capture: true });
        });
    }

    /**
     * Setup browser idle callback
     */
    function setupIdleCallback() {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(loadDelayedScripts, { timeout: config.timeout });
        } else {
            // Fallback for browsers without requestIdleCallback
            setTimeout(loadDelayedScripts, 200);
        }
    }

    /**
     * Setup page load complete trigger
     */
    function setupPageLoadTrigger() {
        if (document.readyState === 'complete') {
            setTimeout(loadDelayedScripts, 100);
        } else {
            window.addEventListener('load', function() {
                setTimeout(loadDelayedScripts, 100);
            });
        }
    }

    /**
     * Setup custom delay trigger
     */
    function setupCustomDelayTrigger() {
        setTimeout(loadDelayedScripts, config.customDelay);
    }

    /**
     * Initialize based on trigger strategy
     */
    function init() {
        // Check if there are any delayed scripts
        if (!document.querySelector('script[type="coreboost/delayed"]')) {
            return;
        }

        switch (config.trigger) {
            case 'user_interaction':
                setupUserInteractionListeners();
                // Fallback timeout
                window._cbDelayTimeout = setTimeout(loadDelayedScripts, config.timeout);
                break;

            case 'browser_idle':
                setupIdleCallback();
                break;

            case 'page_load_complete':
                setupPageLoadTrigger();
                break;

            case 'custom_delay':
                setupCustomDelayTrigger();
                break;

            default:
                // Default to user interaction with timeout fallback
                setupUserInteractionListeners();
                window._cbDelayTimeout = setTimeout(loadDelayedScripts, config.timeout);
        }
    }

    // Start initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
        <?php
    }

    /**
     * Handle inline script delaying (output buffer approach)
     * 
     * Note: This is a more aggressive feature that captures and delays
     * inline scripts. Use with caution.
     */
    public function delay_inline_scripts() {
        // This feature uses output buffering to capture and modify inline scripts
        // It's triggered early in wp_footer and processes the buffer later
        
        // For now, we'll implement a simpler approach using wp_print_footer_scripts
        // Full inline script delaying would require output buffering which can conflict
        // with other plugins. This is left as an extension point.
        
        // The inline script removal feature in Resource_Remover can be used
        // in conjunction with this to remove specific inline scripts by ID
    }
}
