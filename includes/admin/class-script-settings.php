<?php
/**
 * Script Optimization Settings (Phase 1 & 2)
 *
 * Handles admin interface for:
 * - Phase 1: Multi-layer script exclusion system
 * - Phase 2: Smart load strategies for scripts
 *
 * @package    CoreBoost
 * @subpackage CoreBoost/Admin
 */

namespace CoreBoost\Admin;

class Script_Settings {

    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * Initialize the class
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
    }

    /**
     * Register settings for script optimization
     */
    public function register_settings() {
        // Script Exclusions Section (Phase 1)
        add_settings_section(
            'coreboost_script_exclusions_section',
            'Script Exclusion Patterns (Phase 1)',
            array($this, 'exclusions_section_callback'),
            'coreboost-scripts'
        );

        // Built-in exclusions toggle
        add_settings_field(
            'enable_default_exclusions',
            'Use Built-in Exclusion Patterns',
            array($this, 'enable_default_exclusions_callback'),
            'coreboost-scripts',
            'coreboost_script_exclusions_section'
        );

        // Custom exclusion patterns field
        add_settings_field(
            'script_exclusion_patterns',
            'Custom Exclusion Patterns',
            array($this, 'script_exclusion_patterns_callback'),
            'coreboost-scripts',
            'coreboost_script_exclusions_section'
        );

        // Script Load Strategies Section (Phase 2)
        add_settings_section(
            'coreboost_script_strategies_section',
            'Script Load Strategies (Phase 2)',
            array($this, 'strategies_section_callback'),
            'coreboost-scripts'
        );

        // Script load strategy field
        add_settings_field(
            'script_load_strategy',
            'Script Load Strategy',
            array($this, 'script_load_strategy_callback'),
            'coreboost-scripts',
            'coreboost_script_strategies_section'
        );

        // Custom script delay field
        add_settings_field(
            'script_custom_delay',
            'Custom Script Delay (ms)',
            array($this, 'script_custom_delay_callback'),
            'coreboost-scripts',
            'coreboost_script_strategies_section'
        );
    }

    /**
     * Script Exclusions section callback
     */
    public function exclusions_section_callback() {
        ?>
        <p><?php esc_html_e('Control which scripts are excluded from deferring. This multi-layer system includes built-in patterns for common libraries and allows custom patterns.', 'coreboost'); ?></p>
        <div class="notice notice-info inline">
            <p><strong>Ã°Å¸â€œâ€¹ Built-in Exclusion Patterns:</strong></p>
            <p style="margin: 5px 0; font-size: 12px;">
                jQuery (jquery, jquery-core, jquery-migrate, jquery-ui-*), WordPress core scripts, Google Analytics, Facebook SDK, Stripe/PayPal, Elementor, WooCommerce, Contact Form 7, Gravity Forms, WPForms, and many more.
            </p>
        </div>
        <?php
    }

    /**
     * Enable default exclusions callback
     */
    public function enable_default_exclusions_callback() {
        $value = isset($this->options['enable_default_exclusions']) ? $this->options['enable_default_exclusions'] : true;
        $checked = $value ? 'checked' : '';
        ?>
        <input 
            type="checkbox" 
            name="coreboost_options[enable_default_exclusions]" 
            value="1"
            <?php echo esc_attr($checked); ?>
        >
        <label><?php esc_html_e('Enable 50+ built-in script exclusion patterns optimized for popular WordPress libraries and plugins', 'coreboost'); ?></label>
        <p class="description">
            <?php esc_html_e('When enabled, common scripts like jQuery, WordPress utilities, and popular plugin scripts are automatically excluded from deferring to prevent compatibility issues.', 'coreboost'); ?>
        </p>
        <?php
    }

    /**
     * Custom exclusion patterns callback
     */
    public function script_exclusion_patterns_callback() {
        $value = isset($this->options['script_exclusion_patterns']) ? $this->options['script_exclusion_patterns'] : '';
        ?>
        <textarea 
            name="coreboost_options[script_exclusion_patterns]" 
            id="script_exclusion_patterns" 
            rows="8" 
            class="large-text code"
            placeholder="jquery&#10;jquery-ui&#10;my-plugin-script&#10;custom-handler"
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Add custom script handles to exclude from deferring. One handle per line. These are added to the built-in patterns above.', 'coreboost'); ?>
        </p>
        <p class="description">
            <strong><?php esc_html_e('Example:', 'coreboost'); ?></strong> 
            <?php esc_html_e('If a plugin script is breaking your site when deferred, add its handle here.', 'coreboost'); ?>
        </p>
        <?php
    }

    /**
     * Script Load Strategies section callback
     */
    public function strategies_section_callback() {
        ?>
        <p><?php esc_html_e('Choose how enqueued scripts should load. Different strategies balance performance gains with functionality needs.', 'coreboost'); ?></p>
        <div class="notice notice-warning inline">
            <p><strong>Ã¢Å¡Â Ã¯Â¸Â Note:</strong> <?php esc_html_e('These settings apply to scripts enqueued with wp_enqueue_script() that are not excluded. Use with testing to ensure all functionality works correctly.', 'coreboost'); ?></p>
        </div>
        <?php
    }

    /**
     * Script load strategy callback
     */
    public function script_load_strategy_callback() {
        $value = isset($this->options['script_load_strategy']) ? $this->options['script_load_strategy'] : 'immediate';
        $strategies = array(
            'immediate' => array(
                'label' => 'Immediate',
                'description' => 'Load scripts normally (no deferring). For scripts that must run before page interaction.'
            ),
            'defer' => array(
                'label' => 'Defer',
                'description' => 'Add defer attribute. Scripts download in parallel but execute in order after HTML parsing.'
            ),
            'async' => array(
                'label' => 'Async',
                'description' => 'Add async attribute. Scripts download and execute immediately. Best for independent scripts.'
            ),
            'user_interaction' => array(
                'label' => 'User Interaction',
                'description' => 'Load on user interaction (click, scroll, touch, keypress). Falls back to 10 seconds.'
            ),
            'browser_idle' => array(
                'label' => 'Browser Idle',
                'description' => 'Load when browser is idle via requestIdleCallback. Best for non-critical scripts.'
            ),
            'custom' => array(
                'label' => 'Custom Delay',
                'description' => 'Delay loading by custom milliseconds (see below).'
            )
        );
        ?>
        <fieldset>
            <?php foreach ($strategies as $strategy_key => $strategy): ?>
                <label style="display: block; margin-bottom: 10px;">
                    <input 
                        type="radio" 
                        name="coreboost_options[script_load_strategy]" 
                        value="<?php echo esc_attr($strategy_key); ?>"
                        <?php checked($value, $strategy_key); ?>
                    >
                    <strong><?php echo esc_html($strategy['label']); ?></strong>
                    <br>
                    <span style="margin-left: 25px; color: #666;">
                        <?php echo esc_html($strategy['description']); ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">
            <?php esc_html_e('Recommended: Start with "Defer" for best compatibility, or "Browser Idle" for maximum performance.', 'coreboost'); ?>
        </p>
        <?php
    }

    /**
     * Custom script delay callback
     */
    public function script_custom_delay_callback() {
        $value = isset($this->options['script_custom_delay']) ? intval($this->options['script_custom_delay']) : 3000;
        $strategy = isset($this->options['script_load_strategy']) ? $this->options['script_load_strategy'] : 'immediate';
        ?>
        <input 
            type="number" 
            name="coreboost_options[script_custom_delay]" 
            id="script_custom_delay"
            value="<?php echo esc_attr($value); ?>"
            min="0"
            max="10000"
            step="100"
            class="small-text"
            <?php echo $strategy !== 'custom' ? 'disabled' : ''; ?>
        > ms
        <p class="description">
            <?php esc_html_e('Only used when "Custom Delay" strategy is selected. Enter delay in milliseconds (1000ms = 1 second).', 'coreboost'); ?>
        </p>
        <script>
        jQuery(document).ready(function($) {
            $('input[name="coreboost_options[script_load_strategy]"]').on('change', function() {
                var isCustom = $(this).val() === 'custom';
                $('#script_custom_delay').prop('disabled', !isCustom);
            });
        });
        </script>
        <?php
    }

    /**
     * Sanitize and validate script settings
     *
     * @param array $input Raw input values
     * @param array $sanitized Current sanitized values
     * @return array Updated sanitized values
     */
    public function sanitize_settings($input, $sanitized) {
        // Sanitize enable_default_exclusions
        if (isset($input['enable_default_exclusions'])) {
            $sanitized['enable_default_exclusions'] = !empty($input['enable_default_exclusions']);
        }

        // Sanitize script_exclusion_patterns
        if (isset($input['script_exclusion_patterns'])) {
            $sanitized['script_exclusion_patterns'] = sanitize_textarea_field($input['script_exclusion_patterns']);
        }

        // Sanitize script_load_strategy
        if (isset($input['script_load_strategy'])) {
            $valid_strategies = array('immediate', 'defer', 'async', 'user_interaction', 'browser_idle', 'custom');
            if (in_array($input['script_load_strategy'], $valid_strategies)) {
                $sanitized['script_load_strategy'] = $input['script_load_strategy'];
            }
        }

        // Sanitize script_custom_delay
        if (isset($input['script_custom_delay'])) {
            $delay = intval($input['script_custom_delay']);
            $sanitized['script_custom_delay'] = max(0, min(10000, $delay));
        }

        return $sanitized;
    }
}
