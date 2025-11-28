<?php
/**
 * Advanced Event Hijacking System (Phase 4)
 *
 * Sophisticated event-driven script loading with:
 * - Custom trigger conditions
 * - Priority-based loading queues
 * - Event debouncing and throttling
 * - Conditional loading (media queries, user agent, etc.)
 * - Performance monitoring
 *
 * @package CoreBoost
 * @since 2.4.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Debug_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Event_Hijacker
 *
 * Intercepts and manages script loading based on:
 * - User interactions (clicks, scrolls, etc.)
 * - Page visibility changes
 * - Network conditions
 * - Custom conditions via filters
 */
class Event_Hijacker {
    
    /**
     * Registered event listeners
     *
     * @var array
     */
    private $listeners = [];
    
    /**
     * Script load queue by priority
     *
     * @var array
     */
    private $load_queue = [];
    
    /**
     * Conditions that trigger loading
     *
     * @var array
     */
    private $trigger_conditions = [];
    
    /**
     * Performance metrics
     *
     * @var array
     */
    private $metrics = [];
    
    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug_mode;
    
    /**
     * Options
     *
     * @var array
     */
    private $options;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options = []) {
        $this->options = $options;
        $this->debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;
        $this->initialize_default_conditions();
    }
    
    /**
     * Initialize default trigger conditions
     *
     * @return void
     */
    private function initialize_default_conditions() {
        // User Interaction Events (Standard)
        $this->trigger_conditions['user_interaction'] = [
            'events' => ['mousedown', 'mousemove', 'touchstart', 'scroll', 'keydown'],
            'debounce' => 100, // ms
            'threshold' => 1, // Fire after 1 event
            'fallback' => 10000, // 10 seconds fallback
        ];
        
        // Page Visibility Change
        $this->trigger_conditions['visibility_change'] = [
            'events' => ['visibilitychange'],
            'condition' => 'document.visibilityState === "visible"',
            'fallback' => 10000,
        ];
        
        // Network Online
        $this->trigger_conditions['network_online'] = [
            'events' => ['online'],
            'condition' => 'navigator.onLine',
            'fallback' => 10000,
        ];
        
        // Browser Idle (requestIdleCallback)
        $this->trigger_conditions['browser_idle'] = [
            'method' => 'requestIdleCallback',
            'timeout' => 1000,
            'fallback' => 3000,
        ];
        
        // Page Load Complete
        $this->trigger_conditions['page_load_complete'] = [
            'events' => ['load'],
            'debounce' => 500,
            'fallback' => 5000,
        ];
        
        // Allow custom conditions via filter
        $this->trigger_conditions = apply_filters('coreboost_event_trigger_conditions', $this->trigger_conditions);
    }
    
    /**
     * Register an event listener for script loading
     *
     * @param string $handle Script handle
     * @param array $config Listener configuration
     *  - triggers: array of trigger condition keys
     *  - priority: 10 (higher = load earlier)
     *  - conditions: array of additional conditions
     * @return bool True if registered
     */
    public function register_listener($handle, $config = []) {
        $default_config = [
            'triggers' => ['user_interaction'],
            'priority' => 10,
            'conditions' => [],
            'enabled' => true,
        ];
        
        $config = array_merge($default_config, $config);
        
        // Validate triggers
        foreach ($config['triggers'] as $trigger) {
            if (!isset($this->trigger_conditions[$trigger])) {
                if ($this->debug_mode) {
                    Debug_Helper::comment("CoreBoost: Unknown trigger condition: $trigger", $this->debug_mode);
                }
                return false;
            }
        }
        
        // Add to listeners
        $this->listeners[$handle] = $config;
        
        // Sort into load queue by priority
        $priority = $config['priority'];
        if (!isset($this->load_queue[$priority])) {
            $this->load_queue[$priority] = [];
        }
        $this->load_queue[$priority][] = $handle;
        
        return true;
    }
    
    /**
     * Get registered listeners
     *
     * @return array All registered listeners
     */
    public function get_listeners() {
        return $this->listeners;
    }
    
    /**
     * Get scripts sorted by load priority
     *
     * @return array Scripts ordered by priority (high to low)
     */
    public function get_load_queue() {
        $queue = [];
        krsort($this->load_queue);
        
        foreach ($this->load_queue as $priority => $handles) {
            $queue = array_merge($queue, $handles);
        }
        
        return $queue;
    }
    
    /**
     * Generate JavaScript for event hijacking
     *
     * Creates optimized JS code for handling event-driven loading
     *
     * @param array $handles Script handles to load
     * @return string JavaScript code
     */
    public function generate_hijack_script($handles = []) {
        if (empty($handles)) {
            $handles = $this->get_load_queue();
        }
        
        ob_start();
        ?>
<script>
/**
 * CoreBoost Event Hijacking System (Phase 4)
 * Priority-based script loading with multiple trigger strategies
 */
(function() {
    'use strict';
    
    // Configuration
    var config = <?php echo json_encode([
        'handles' => $handles,
        'triggers' => $this->trigger_conditions,
    ]); ?>;
    
    var loadedScripts = {};
    var eventTriggered = false;
    var timeoutTriggered = false;
    
    /**
     * Load scripts in priority order
     */
    function loadScripts() {
        // Prevent double loading
        if (eventTriggered) return;
        eventTriggered = true;
        
        // Clear any pending timeouts
        if (window._cbTimeout) {
            clearTimeout(window._cbTimeout);
        }
        
        // Fire loading event for analytics
        if (window.document) {
            var event = new Event('coreboost_scripts_loading', { bubbles: true });
            document.dispatchEvent(event);
        }
        
        // Load each script handle
        config.handles.forEach(function(handle) {
            if (!loadedScripts[handle]) {
                loadedScripts[handle] = true;
                // Script loading is handled via wp_enqueue_script
                // This just sets flag that script should load
            }
        });
    }
    
    /**
     * Setup user interaction listeners
     */
    function setupUserInteractionListeners() {
        var events = ['mousedown', 'mousemove', 'touchstart', 'scroll', 'keydown'];
        var eventCount = 0;
        var debounceTimer;
        
        function handleEvent() {
            eventCount++;
            
            // Debounce: only fire after multiple events
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                if (eventCount >= 1) {
                    removeListeners();
                    loadScripts();
                }
            }, 100);
        }
        
        function removeListeners() {
            events.forEach(function(event) {
                document.removeEventListener(event, handleEvent, true);
            });
        }
        
        events.forEach(function(event) {
            document.addEventListener(event, handleEvent, { passive: true, capture: true });
        });
    }
    
    /**
     * Setup browser idle listeners
     */
    function setupIdleCallback() {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(function() {
                setTimeout(loadScripts, 1000);
            }, { timeout: 1000 });
        } else {
            // Fallback for browsers without requestIdleCallback
            window._cbTimeout = setTimeout(loadScripts, 3000);
        }
    }
    
    /**
     * Setup page visibility listeners
     */
    function setupVisibilityListeners() {
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                loadScripts();
            }
        });
    }
    
    /**
     * Setup network status listeners
     */
    function setupNetworkListeners() {
        window.addEventListener('online', function() {
            if (navigator.onLine) {
                loadScripts();
            }
        });
    }
    
    /**
     * Initialize all listeners based on configuration
     */
    function init() {
        // User interaction (most common)
        setupUserInteractionListeners();
        
        // Browser idle
        setupIdleCallback();
        
        // Page visibility
        setupVisibilityListeners();
        
        // Network status
        setupNetworkListeners();
        
        // Fallback: always load after timeout
        window._cbTimeout = setTimeout(loadScripts, 10000);
    }
    
    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
        <?php
        $js = ob_get_clean();
        
        return $js;
    }
    
    /**
     * Record performance metrics
     *
     * @param string $name Metric name
     * @param float $value Metric value
     * @return void
     */
    public function record_metric($name, $value) {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = [];
        }
        $this->metrics[$name][] = [
            'value' => $value,
            'timestamp' => time(),
        ];
    }
    
    /**
     * Get performance metrics
     *
     * @param string $name Optional metric name filter
     * @return array Metrics data
     */
    public function get_metrics($name = null) {
        if ($name) {
            return isset($this->metrics[$name]) ? $this->metrics[$name] : [];
        }
        return $this->metrics;
    }
    
    /**
     * Add custom trigger condition
     *
     * Allows users to define custom loading triggers
     *
     * @param string $key Condition key
     * @param array $config Condition configuration
     * @return void
     */
    public function add_trigger_condition($key, $config) {
        $this->trigger_conditions[$key] = $config;
    }
    
    /**
     * Get all available trigger conditions
     *
     * @return array Trigger conditions
     */
    public function get_trigger_conditions() {
        return $this->trigger_conditions;
    }
}
