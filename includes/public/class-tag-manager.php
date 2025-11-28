<?php
/**
 * Custom Tag Manager
 *
 * Handles frontend output of custom tags with delay strategies
 *
 * @package    CoreBoost
 * @subpackage CoreBoost/PublicCore
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Debug_Helper;

class Tag_Manager {

    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug_mode;

    /**
     * Load strategy
     *
     * @var string
     */
    private $load_strategy;

    /**
     * Custom delay in milliseconds
     *
     * @var int
     */
    private $custom_delay;

    /**
     * Initialize the class
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
        $this->debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;
        $this->load_strategy = isset($options['tag_load_strategy']) ? $options['tag_load_strategy'] : 'balanced';
        $this->custom_delay = isset($options['tag_custom_delay']) ? intval($options['tag_custom_delay']) : 3000;
    }

    /**
     * Register hooks
     *
     * @param \CoreBoost\Loader $loader Plugin loader
     */
    public function register_hooks($loader) {
        // Only register on frontend
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        // Only register hooks if at least one tag position has content
        if ($this->has_tags()) {
            $loader->add_action('wp_head', $this, 'output_head_tags', 1);
            $loader->add_action('wp_body_open', $this, 'output_body_tags', 1);
            $loader->add_action('wp_footer', $this, 'output_footer_tags', 1);
            
            // Only add delay script if not using immediate strategy
            if ($this->load_strategy !== 'immediate') {
                $loader->add_action('wp_footer', $this, 'output_delay_script', 999);
            }
        }
    }

    /**
     * Check if any tags are configured
     *
     * @return bool
     */
    private function has_tags() {
        return !empty($this->options['tag_head_scripts']) || 
               !empty($this->options['tag_body_scripts']) || 
               !empty($this->options['tag_footer_scripts']);
    }

    /**
     * Output head tags
     */
    public function output_head_tags() {
        // Safety check: don't output on admin or preview contexts
        if (is_admin() || wp_doing_ajax() || isset($_GET['elementor-preview'])) {
            return;
        }

        if (empty($this->options['tag_head_scripts'])) {
            return;
        }

        if ($this->debug_mode) {
            Debug_Helper::comment('CoreBoost: Head tags output start', $this->debug_mode);
        }

        // For immediate strategy, output directly
        if ($this->load_strategy === 'immediate') {
            echo "\n" . $this->options['tag_head_scripts'] . "\n";
        } else {
            // For delayed strategies, wrap in a container that will be processed later
            echo "\n<!-- CoreBoost Head Tags (Delayed) -->\n";
            echo "<script id='coreboost-head-tags' type='text/template'>\n";
            echo $this->options['tag_head_scripts'] . "\n";
            echo "</script>\n";
        }

        if ($this->debug_mode) {
            Debug_Helper::comment('CoreBoost: Head tags output end (Strategy: ' . $this->load_strategy . ')', $this->debug_mode);
        }
    }

    /**
     * Output body tags (at top of body)
     */
    public function output_body_tags() {
        // Safety check: don't output on admin or preview contexts
        if (is_admin() || wp_doing_ajax() || isset($_GET['elementor-preview'])) {
            return;
        }

        if (empty($this->options['tag_body_scripts'])) {
            return;
        }

        if ($this->debug_mode) {
            Debug_Helper::comment('CoreBoost: Body tags output start', $this->debug_mode);
        }

        // For immediate strategy, output directly
        if ($this->load_strategy === 'immediate') {
            echo "\n" . $this->options['tag_body_scripts'] . "\n";
        } else {
            // For delayed strategies, wrap in a container
            echo "\n<!-- CoreBoost Body Tags (Delayed) -->\n";
            echo "<div id='coreboost-body-tags' style='display:none;'>\n";
            echo $this->options['tag_body_scripts'] . "\n";
            echo "</div>\n";
        }

        if ($this->debug_mode) {
            Debug_Helper::comment('CoreBoost: Body tags output end', $this->debug_mode);
        }
    }

    /**
     * Output footer tags
     */
    public function output_footer_tags() {
        // Safety check: don't output on admin or preview contexts
        if (is_admin() || wp_doing_ajax() || isset($_GET['elementor-preview'])) {
            return;
        }

        if (empty($this->options['tag_footer_scripts'])) {
            return;
        }

        if ($this->debug_mode) {
            Debug_Helper::comment('CoreBoost: Footer tags output start', $this->debug_mode);
        }

        // For immediate strategy, output directly
        if ($this->load_strategy === 'immediate') {
            echo "\n" . $this->options['tag_footer_scripts'] . "\n";
        } else {
            // For delayed strategies, wrap in a container
            echo "\n<!-- CoreBoost Footer Tags (Delayed) -->\n";
            echo "<script id='coreboost-footer-tags' type='text/template'>\n";
            echo $this->options['tag_footer_scripts'] . "\n";
            echo "</script>\n";
        }

        if ($this->debug_mode) {
            Debug_Helper::comment('CoreBoost: Footer tags output end', $this->debug_mode);
        }
    }

    /**
     * Output delay loading script
     */
    public function output_delay_script() {
        // Safety check: don't output on admin or preview contexts
        if (is_admin() || wp_doing_ajax() || isset($_GET['elementor-preview'])) {
            return;
        }

        if ($this->load_strategy === 'immediate') {
            return;
        }

        if ($this->debug_mode) {
            Debug_Helper::comment('CoreBoost: Delay script output start (Strategy: ' . $this->load_strategy . ')', $this->debug_mode);
        }

        ?>
        <script>
        (function() {
            'use strict';
            
            var coreBoostTagsLoaded = false;
            
            function loadCoreBoostTags() {
                if (coreBoostTagsLoaded) return;
                coreBoostTagsLoaded = true;
                
                <?php if ($this->debug_mode): ?>
                console.log('CoreBoost: Loading custom tags');
                <?php endif; ?>
                
                // Load head tags
                var headTags = document.getElementById('coreboost-head-tags');
                if (headTags) {
                    var headContent = headTags.textContent || headTags.innerText;
                    var headTemp = document.createElement('div');
                    headTemp.innerHTML = headContent;
                    var headScripts = headTemp.getElementsByTagName('script');
                    for (var i = 0; i < headScripts.length; i++) {
                        var script = document.createElement('script');
                        if (headScripts[i].src) {
                            script.src = headScripts[i].src;
                        }
                        if (headScripts[i].innerHTML) {
                            script.innerHTML = headScripts[i].innerHTML;
                        }
                        // Copy attributes
                        for (var j = 0; j < headScripts[i].attributes.length; j++) {
                            var attr = headScripts[i].attributes[j];
                            if (attr.name !== 'src' && attr.name !== 'type') {
                                script.setAttribute(attr.name, attr.value);
                            }
                        }
                        document.head.appendChild(script);
                    }
                    headTags.parentNode.removeChild(headTags);
                }
                
                // Load body tags
                var bodyTags = document.getElementById('coreboost-body-tags');
                if (bodyTags) {
                    var bodyContent = bodyTags.textContent || bodyTags.innerText;
                    var bodyTemp = document.createElement('div');
                    bodyTemp.innerHTML = bodyContent;
                    
                    // Move all child nodes from the temporary container to the body
                    // This handles scripts, noscript, and any other elements
                    while (bodyTemp.firstChild) {
                        document.body.appendChild(bodyTemp.firstChild);
                    }
                    
                    // Remove the template container
                    bodyTags.parentNode.removeChild(bodyTags);
                }
                
                // Load footer tags
                var footerTags = document.getElementById('coreboost-footer-tags');
                if (footerTags) {
                    var footerContent = footerTags.textContent || footerTags.innerText;
                    var footerTemp = document.createElement('div');
                    footerTemp.innerHTML = footerContent;
                    
                    // Move all child nodes from the temporary container to the end of body
                    // This handles scripts, noscript, and any other elements
                    while (footerTemp.firstChild) {
                        document.body.appendChild(footerTemp.firstChild);
                    }
                    
                    // Remove the template container
                    footerTags.parentNode.removeChild(footerTags);
                }
                
                <?php if ($this->debug_mode): ?>
                console.log('CoreBoost: Custom tags loaded successfully');
                <?php endif; ?>
            }
            
            <?php
            switch ($this->load_strategy) {
                case 'balanced':
                    echo "setTimeout(loadCoreBoostTags, 3000);";
                    break;
                    
                case 'aggressive':
                    echo "setTimeout(loadCoreBoostTags, 5000);";
                    break;
                    
                case 'user_interaction':
                    ?>
                    var events = ['mousedown', 'mousemove', 'touchstart', 'scroll', 'keydown'];
                    var triggerLoad = function() {
                        loadCoreBoostTags();
                        events.forEach(function(event) {
                            window.removeEventListener(event, triggerLoad);
                        });
                    };
                    events.forEach(function(event) {
                        window.addEventListener(event, triggerLoad, { passive: true, once: true });
                    });
                    // Fallback after 10 seconds
                    setTimeout(loadCoreBoostTags, 10000);
                    <?php
                    break;
                    
                case 'browser_idle':
                    ?>
                    if ('requestIdleCallback' in window) {
                        requestIdleCallback(function() {
                            setTimeout(loadCoreBoostTags, 1000);
                        });
                    } else {
                        setTimeout(loadCoreBoostTags, 3000);
                    }
                    <?php
                    break;
                    
                case 'custom':
                    echo "setTimeout(loadCoreBoostTags, " . intval($this->custom_delay) . ");";
                    break;
            }
            ?>
        })();
        </script>
        <?php

        if ($this->debug_mode) {
            Debug_Helper::comment('CoreBoost: Delay script output end', $this->debug_mode);
        }
    }
}
