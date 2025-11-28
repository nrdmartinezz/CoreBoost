<?php
/**
 * GTM settings management
 *
 * @package CoreBoost
 * @since 2.0.0
 */

namespace CoreBoost\Admin;

use CoreBoost\Core\Config;
use CoreBoost\Core\Field_Renderer;
use CoreBoost\Core\GTM_Detector;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GTM_Settings
 */
class GTM_Settings {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
    }
    
    /**
     * Register GTM settings
     */
    public function register_settings() {
        // Add GTM settings section
        add_settings_section(
            'coreboost-gtm',
            __('Google Tag Manager', 'coreboost'),
            array($this, 'gtm_section_callback'),
            'coreboost-gtm'
        );
        
        // GTM Enable toggle
        add_settings_field(
            'gtm_enabled',
            __('Enable GTM Management', 'coreboost'),
            array($this, 'gtm_enabled_callback'),
            'coreboost-gtm',
            'coreboost-gtm'
        );
        
        // GTM Container ID
        add_settings_field(
            'gtm_container_id',
            __('GTM Container ID', 'coreboost'),
            array($this, 'gtm_container_id_callback'),
            'coreboost-gtm',
            'coreboost-gtm'
        );
        
        // Load Strategy
        add_settings_field(
            'gtm_load_strategy',
            __('Load Strategy', 'coreboost'),
            array($this, 'gtm_load_strategy_callback'),
            'coreboost-gtm',
            'coreboost-gtm'
        );
        
        // Custom Delay
        add_settings_field(
            'gtm_custom_delay',
            __('Custom Delay (ms)', 'coreboost'),
            array($this, 'gtm_custom_delay_callback'),
            'coreboost-gtm',
            'coreboost-gtm'
        );
    }
    
    /**
     * GTM section callback
     */
    public function gtm_section_callback() {
        // Check for existing GTM
        $detection = GTM_Detector::get_cached_detection();
        if ($detection === false) {
            $detection = GTM_Detector::detect_existing_gtm();
            GTM_Detector::cache_detection($detection);
        }
        
        echo '<div class="coreboost-gtm-detection">';
        
        if ($detection['found']) {
            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>' . __('⚠️ Existing GTM Implementation Detected', 'coreboost') . '</strong></p>';
            echo '<p>' . __('CoreBoost detected existing Google Tag Manager implementations on your site. To prevent conflicts, please resolve these before enabling CoreBoost GTM:', 'coreboost') . '</p>';
            echo '<ul style="list-style: disc; margin-left: 20px;">';
            
            foreach ($detection['sources'] as $source) {
                echo '<li>';
                echo '<strong>' . esc_html($source['name']) . '</strong> (' . esc_html($source['type']) . ')';
                if (!empty($source['container'])) {
                    echo ' - <code>' . esc_html($source['container']) . '</code>';
                }
                if (!empty($source['recommendation'])) {
                    echo '<br><em>' . esc_html($source['recommendation']) . '</em>';
                }
                echo '</li>';
            }
            
            echo '</ul>';
            echo '<p><button type="button" class="button" onclick="coreboostClearGTMCache()">' . __('Re-scan for GTM', 'coreboost') . '</button></p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>' . __('✓ No Conflicts Detected', 'coreboost') . '</strong></p>';
            echo '<p>' . __('No existing GTM implementations found. Safe to enable CoreBoost GTM Management.', 'coreboost') . '</p>';
            echo '<p><button type="button" class="button" onclick="coreboostClearGTMCache()">' . __('Re-scan', 'coreboost') . '</button></p>';
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<p>' . __('Manage your Google Tag Manager container with optimized loading strategies for better performance.', 'coreboost') . '</p>';
    }
    
    /**
     * GTM enabled callback
     */
    public function gtm_enabled_callback() {
        $value = isset($this->options['gtm_enabled']) ? $this->options['gtm_enabled'] : false;
        $detection = GTM_Detector::get_cached_detection();
        $disabled = ($detection && $detection['found']) ? 'disabled' : '';
        
        echo '<label>';
        echo '<input type="checkbox" name="coreboost_options[gtm_enabled]" value="1" ' . checked($value, true, false) . ' ' . $disabled . '>';
        echo ' ' . __('Enable Google Tag Manager management', 'coreboost');
        echo '</label>';
        
        if ($disabled) {
            echo '<p class="description" style="color: #d63638;">' . __('Disabled due to detected conflicts. Resolve conflicts above to enable.', 'coreboost') . '</p>';
        } else {
            echo '<p class="description">' . __('Enable this to activate CoreBoost GTM management. Ensure no other GTM plugins are active.', 'coreboost') . '</p>';
        }
    }
    
    /**
     * GTM container ID callback
     */
    public function gtm_container_id_callback() {
        $value = isset($this->options['gtm_container_id']) ? $this->options['gtm_container_id'] : '';
        
        echo '<input type="text" name="coreboost_options[gtm_container_id]" value="' . esc_attr($value) . '" class="regular-text" placeholder="GTM-XXXXXXX" pattern="GTM-[A-Z0-9]+">';
        echo '<p class="description">' . __('Enter your Google Tag Manager container ID (format: GTM-XXXXXXX).', 'coreboost') . '</p>';
        
        if (!empty($value)) {
            $valid = preg_match('/^GTM-[A-Z0-9]+$/', $value);
            if ($valid) {
                echo '<p class="description" style="color: #46b450;">✓ ' . __('Valid container ID format', 'coreboost') . '</p>';
            } else {
                echo '<p class="description" style="color: #d63638;">✗ ' . __('Invalid container ID format. Should be GTM-XXXXXXX', 'coreboost') . '</p>';
            }
        }
    }
    
    /**
     * GTM load strategy callback
     */
    public function gtm_load_strategy_callback() {
        $value = isset($this->options['gtm_load_strategy']) ? $this->options['gtm_load_strategy'] : 'balanced';
        
        $strategies = array(
            'immediate' => array(
                'label' => __('Immediate', 'coreboost'),
                'description' => __('Load GTM immediately (no delay). Standard implementation.', 'coreboost'),
                'performance' => __('Performance: Standard', 'coreboost')
            ),
            'balanced' => array(
                'label' => __('Balanced (Recommended)', 'coreboost'),
                'description' => __('Load GTM after 3 seconds. Best balance of performance and functionality.', 'coreboost'),
                'performance' => __('Performance: ⚡⚡ Good', 'coreboost')
            ),
            'aggressive' => array(
                'label' => __('Aggressive', 'coreboost'),
                'description' => __('Load GTM after 5 seconds. Maximum performance optimization.', 'coreboost'),
                'performance' => __('Performance: ⚡⚡⚡ Excellent', 'coreboost')
            ),
            'interaction' => array(
                'label' => __('User Interaction', 'coreboost'),
                'description' => __('Load GTM on first user interaction (click, scroll, touch). Maximum savings.', 'coreboost'),
                'performance' => __('Performance: ⚡⚡⚡ Excellent', 'coreboost')
            ),
            'idle' => array(
                'label' => __('Browser Idle', 'coreboost'),
                'description' => __('Load GTM when browser is idle. Uses requestIdleCallback API.', 'coreboost'),
                'performance' => __('Performance: ⚡⚡⚡ Excellent', 'coreboost')
            ),
            'custom' => array(
                'label' => __('Custom Delay', 'coreboost'),
                'description' => __('Set custom delay in milliseconds (see below).', 'coreboost'),
                'performance' => __('Performance: Depends on delay', 'coreboost')
            )
        );
        
        echo '<fieldset>';
        foreach ($strategies as $strategy_key => $strategy_info) {
            echo '<label style="display: block; margin-bottom: 15px;">';
            echo '<input type="radio" name="coreboost_options[gtm_load_strategy]" value="' . esc_attr($strategy_key) . '" ' . checked($value, $strategy_key, false) . '>';
            echo ' <strong>' . $strategy_info['label'] . '</strong>';
            echo '<br><span style="margin-left: 25px; color: #666;">' . $strategy_info['description'] . '</span>';
            echo '<br><span style="margin-left: 25px; color: #135e96; font-size: 0.9em;">' . $strategy_info['performance'] . '</span>';
            echo '</label>';
        }
        echo '</fieldset>';
        
        echo '<p class="description">' . __('Choose how and when GTM should load. "Balanced" is recommended for most sites.', 'coreboost') . '</p>';
    }
    
    /**
     * GTM custom delay callback
     */
    public function gtm_custom_delay_callback() {
        $value = isset($this->options['gtm_custom_delay']) ? $this->options['gtm_custom_delay'] : 3000;
        
        echo '<input type="number" name="coreboost_options[gtm_custom_delay]" value="' . esc_attr($value) . '" min="0" max="10000" step="100" class="small-text">';
        echo ' ' . __('milliseconds', 'coreboost');
        echo '<p class="description">' . __('Custom delay in milliseconds (1000ms = 1 second). Only used when "Custom Delay" strategy is selected.', 'coreboost') . '</p>';
    }
    
    /**
     * Sanitize GTM settings
     *
     * @param array $input Settings input
     * @return array Sanitized input
     */
    public function sanitize_gtm_settings($input) {
        $sanitized = array();
        
        // GTM enabled
        $sanitized['gtm_enabled'] = isset($input['gtm_enabled']) ? true : false;
        
        // Container ID - only validate if GTM is enabled or field has content
        if (isset($input['gtm_container_id'])) {
            $container_id = trim(sanitize_text_field($input['gtm_container_id']));
            
            if (!empty($container_id)) {
                // Field has content - validate format
                if (preg_match('/^GTM-[A-Z0-9]+$/', $container_id)) {
                    $sanitized['gtm_container_id'] = $container_id;
                } else {
                    // Invalid format - only show error if GTM is enabled
                    $sanitized['gtm_container_id'] = '';
                    if ($sanitized['gtm_enabled']) {
                        add_settings_error(
                            'coreboost_options',
                            'gtm_container_id',
                            __('Invalid GTM Container ID format. Should be GTM-XXXXXXX', 'coreboost'),
                            'error'
                        );
                    }
                }
            } else {
                // Field is empty
                $sanitized['gtm_container_id'] = '';
                
                // Only show error if trying to enable GTM without container ID
                if ($sanitized['gtm_enabled']) {
                    add_settings_error(
                        'coreboost_options',
                        'gtm_container_id_required',
                        __('GTM Container ID is required when GTM is enabled.', 'coreboost'),
                        'error'
                    );
                    // Disable GTM since no valid container ID
                    $sanitized['gtm_enabled'] = false;
                }
            }
        } else {
            $sanitized['gtm_container_id'] = '';
        }
        
        // Load strategy
        $valid_strategies = array('immediate', 'balanced', 'aggressive', 'interaction', 'idle', 'custom');
        if (isset($input['gtm_load_strategy']) && in_array($input['gtm_load_strategy'], $valid_strategies)) {
            $sanitized['gtm_load_strategy'] = $input['gtm_load_strategy'];
        } else {
            $sanitized['gtm_load_strategy'] = 'balanced';
        }
        
        // Custom delay
        if (isset($input['gtm_custom_delay'])) {
            $delay = intval($input['gtm_custom_delay']);
            $sanitized['gtm_custom_delay'] = max(0, min(10000, $delay));
        } else {
            $sanitized['gtm_custom_delay'] = 3000;
        }
        
        // Tags (placeholder for future tag management)
        $sanitized['gtm_tags'] = isset($input['gtm_tags']) ? $input['gtm_tags'] : array();
        
        // Clear detection cache when settings change
        GTM_Detector::clear_detection_cache();
        
        return $sanitized;
    }
}
