<?php
/**
 * GTM frontend manager - handles GTM container output and tag loading
 *
 * @package CoreBoost
 * @since 2.0.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\GTM_Detector;
use CoreBoost\Core\Context_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GTM_Manager
 */
class GTM_Manager {
    
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
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        $this->define_hooks();
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        // Only add hooks if GTM is enabled
        if (!empty($this->options['gtm_enabled']) && !empty($this->options['gtm_container_id'])) {
            $this->loader->add_action('wp_head', $this, 'output_gtm_head', 1);
            $this->loader->add_action('wp_body_open', $this, 'output_gtm_body', 1);
            $this->loader->add_action('wp_footer', $this, 'output_gtm_body_fallback', 1);
            $this->loader->add_action('wp_footer', $this, 'output_delay_script', 999);
        }
    }
    
    /**
     * Output GTM head code
     */
    public function output_gtm_head() {
        // CRITICAL: Don't output in Elementor preview/AJAX
        if (defined('COREBOOST_ELEMENTOR_PREVIEW') && COREBOOST_ELEMENTOR_PREVIEW) {
            return;
        }
        
        // Safety check: don't output on admin or preview contexts
        $elementor_preview = isset($_GET['elementor-preview']) ? sanitize_text_field( wp_unslash( $_GET['elementor-preview'] ) ) : '';
        if (is_admin() || wp_doing_ajax() || !empty($elementor_preview)) {
            return;
        }
        
        $container_id = $this->options['gtm_container_id'];
        
        // Safety check - skip if existing GTM detected
        if (GTM_Detector::should_skip_gtm_output($container_id)) {
            return;
        }
        
        // Get load strategy
        $strategy = isset($this->options['gtm_load_strategy']) ? $this->options['gtm_load_strategy'] : 'balanced';
        $delay = $this->get_delay_for_strategy($strategy);
        
        if ($delay === 0 || $strategy === 'immediate') {
            // Immediate load - standard GTM implementation
            $this->output_immediate_gtm($container_id);
        } else {
            // Delayed load - output placeholder and delay script
            $this->output_delayed_gtm($container_id, $strategy, $delay);
        }
    }
    
    /**
     * Output immediate GTM (no delay)
     *
     * @param string $container_id GTM container ID
     */
    private function output_immediate_gtm($container_id) {
        ?>
<!-- Google Tag Manager (CoreBoost) -->
<script async src="https://www.googletagmanager.com/gtm.js?id=<?php echo esc_attr($container_id); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({'gtm.start': new Date().getTime(), event: 'gtm.js'});
</script>
<!-- End Google Tag Manager -->
<?php
    }
    
    /**
     * Output delayed GTM (with strategy)
     *
     * @param string $container_id GTM container ID
     * @param string $strategy Load strategy
     * @param int $delay Delay in milliseconds
     */
    private function output_delayed_gtm($container_id, $strategy, $delay) {
        ?>
<!-- Google Tag Manager (CoreBoost - Delayed) -->
<script>
window.coreboostGTM = {
    containerId: '<?php echo esc_js($container_id); ?>',
    strategy: '<?php echo esc_js($strategy); ?>',
    delay: <?php echo intval($delay); ?>,
    loaded: false,
    dataLayer: window.dataLayer || []
};
window.dataLayer = window.coreboostGTM.dataLayer;
</script>
<!-- End Google Tag Manager Placeholder -->
<?php
    }
    
    /**
     * Output GTM body fallback for themes without wp_body_open
     */
    public function output_gtm_body_fallback() {
        // Safety check: don't output on admin or preview contexts
        $elementor_preview = isset($_GET['elementor-preview']) ? sanitize_text_field( wp_unslash( $_GET['elementor-preview'] ) ) : '';
        if (is_admin() || wp_doing_ajax() || !empty($elementor_preview)) {
            return;
        }
        
        if ($this->has_output_body_tag()) {
            return; // Already output in wp_body_open
        }
        
        $container_id = $this->options['gtm_container_id'];
        
        // Safety check
        if (GTM_Detector::should_skip_gtm_output($container_id)) {
            return;
        }
        $this->output_noscript_iframe($container_id);
    }
    
    /**
     * Output noscript iframe
     *
     * @param string $container_id GTM container ID
     */
    private function output_noscript_iframe($container_id) {
        ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($container_id); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<?php
    }
    
    /**
     * Output delay script
     */
    public function output_delay_script() {
        // CRITICAL: Don't output in Elementor preview/AJAX
        if (Context_Helper::is_elementor_preview()) {
            return;
        }
        
        // Safety check: don't output on admin or preview contexts
        $elementor_preview = isset($_GET['elementor-preview']) ? sanitize_text_field( wp_unslash( $_GET['elementor-preview'] ) ) : '';
        if (is_admin() || wp_doing_ajax() || !empty($elementor_preview)) {
            return;
        }
        
        $strategy = isset($this->options['gtm_load_strategy']) ? $this->options['gtm_load_strategy'] : 'balanced';
        
        if ($strategy === 'immediate') {
            return; // No delay script needed
        }
        
        $delay = $this->get_delay_for_strategy($strategy);
        
        ?>
<script>
(function() {
    'use strict';
    
    if (!window.coreboostGTM || window.coreboostGTM.loaded) {
        return; // GTM already loaded or not configured
    }
    
    var config = window.coreboostGTM;
    var gtmLoaded = false;
    
    function loadGTM() {
        if (gtmLoaded) return;
        gtmLoaded = true;
        config.loaded = true;
        
        <?php if ($this->options['debug_mode']): ?>
        console.log('CoreBoost GTM: Loading container (strategy: ' + config.strategy + ')');
        <?php endif; ?>
        
        // Load GTM script
        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtm.js?id=' + config.containerId;
        
        var firstScript = document.getElementsByTagName('script')[0];
        firstScript.parentNode.insertBefore(script, firstScript);
        
        // Initialize dataLayer
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({'gtm.start': new Date().getTime(), event: 'gtm.js'});
    }
    
    <?php if ($strategy === 'interaction'): ?>
    // Load on first user interaction
    var events = ['mousedown', 'mousemove', 'touchstart', 'scroll', 'keydown'];
    var loaded = false;
    
    function onInteraction(e) {
        if (!loaded) {
            loaded = true;
            // Use setTimeout to ensure we don't block the event
            setTimeout(function() {
                loadGTM();
            }, 0);
            // Remove listeners
            events.forEach(function(event) {
                window.removeEventListener(event, onInteraction);
            });
        }
        // Explicitly return undefined to avoid async listener issues
        return;
    }
    
    events.forEach(function(event) {
        window.addEventListener(event, onInteraction, { passive: true, once: false });
    });
    
    // Fallback timeout
    setTimeout(function() {
        if (!loaded) {
            loaded = true;
            loadGTM();
        }
    }, 10000); // Load after 10s even without interaction
    
    <?php elseif ($strategy === 'idle'): ?>
    // Load when browser is idle
    if ('requestIdleCallback' in window) {
        requestIdleCallback(function() {
            setTimeout(loadGTM, <?php echo intval($delay); ?>);
        });
    } else {
        // Fallback for browsers without requestIdleCallback
        setTimeout(loadGTM, <?php echo intval($delay); ?>);
    }
    
    <?php else: ?>
    // Time-based delay (balanced/aggressive/custom)
    setTimeout(loadGTM, <?php echo intval($delay); ?>);
    <?php endif; ?>
    
})();
</script>
<?php
    }
    
    /**
     * Get delay time for strategy
     *
     * @param string $strategy Load strategy
     * @return int Delay in milliseconds
     */
    private function get_delay_for_strategy($strategy) {
        switch ($strategy) {
            case 'immediate':
                return 0;
            case 'balanced':
                return 3000; // 3 seconds - default
            case 'aggressive':
                return 5000; // 5 seconds
            case 'interaction':
                return 0; // Loaded on interaction, not time-based
            case 'idle':
                return 1000; // 1 second after idle detected
            case 'custom':
                return isset($this->options['gtm_custom_delay']) ? intval($this->options['gtm_custom_delay']) : 3000;
            default:
                return 3000; // Default to balanced
        }
    }
    
    /**
     * Check if body tag output has been done
     *
     * @return bool
     */
    private function has_output_body_tag() {
        return get_transient('coreboost_gtm_body_output_' . get_the_ID());
    }
    
    /**
     * Mark body tag as output
     */
    private function mark_body_tag_output() {
        set_transient('coreboost_gtm_body_output_' . get_the_ID(), true, 60);
    }
    
    /**
     * Get GTM container ID
     *
     * @return string
     */
    public function get_container_id() {
        return isset($this->options['gtm_container_id']) ? $this->options['gtm_container_id'] : '';
    }
    
    /**
     * Check if GTM is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return !empty($this->options['gtm_enabled']) && !empty($this->options['gtm_container_id']);
    }
}
