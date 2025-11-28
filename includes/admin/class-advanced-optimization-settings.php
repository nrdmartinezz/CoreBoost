<?php
/**
 * Phase 3-4 Advanced Optimization Settings
 *
 * Admin UI for:
 * - Phase 3: Pattern matching (wildcard, regex, plugin profiles)
 * - Phase 4: Event hijacking with custom triggers and priorities
 *
 * @package    CoreBoost
 * @subpackage CoreBoost/Admin
 */

namespace CoreBoost\Admin;

use CoreBoost\PublicCore\Pattern_Matcher;

class Advanced_Optimization_Settings {

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
     * Register settings for advanced optimization
     */
    public function register_settings() {
        // Phase 3: Pattern Matching Section
        add_settings_section(
            'coreboost_pattern_matching_section',
            'Advanced Pattern Matching (Phase 3)',
            array($this, 'pattern_matching_section_callback'),
            'coreboost-advanced'
        );

        // Wildcard patterns field
        add_settings_field(
            'script_wildcard_patterns',
            'Wildcard Patterns',
            array($this, 'wildcard_patterns_callback'),
            'coreboost-advanced',
            'coreboost_pattern_matching_section'
        );

        // Regex patterns field
        add_settings_field(
            'script_regex_patterns',
            'Regular Expression Patterns',
            array($this, 'regex_patterns_callback'),
            'coreboost-advanced',
            'coreboost_pattern_matching_section'
        );

        // Plugin profiles field
        add_settings_field(
            'script_plugin_profiles',
            'Plugin Profile Exclusions',
            array($this, 'plugin_profiles_callback'),
            'coreboost-advanced',
            'coreboost_pattern_matching_section'
        );

        // Phase 4: Event Hijacking Section
        add_settings_section(
            'coreboost_event_hijacking_section',
            'Event-Driven Loading with Priorities (Phase 4)',
            array($this, 'event_hijacking_section_callback'),
            'coreboost-advanced'
        );

        // Enable event hijacking
        add_settings_field(
            'enable_event_hijacking',
            'Enable Event Hijacking',
            array($this, 'enable_event_hijacking_callback'),
            'coreboost-advanced',
            'coreboost_event_hijacking_section'
        );

        // Event trigger strategy
        add_settings_field(
            'event_hijack_triggers',
            'Trigger Strategies',
            array($this, 'event_hijack_triggers_callback'),
            'coreboost-advanced',
            'coreboost_event_hijacking_section'
        );

        // Script load priority
        add_settings_field(
            'script_load_priority',
            'Load Priority Strategy',
            array($this, 'script_load_priority_callback'),
            'coreboost-advanced',
            'coreboost_event_hijacking_section'
        );
    }

    /**
     * Pattern Matching section callback
     */
    public function pattern_matching_section_callback() {
        ?>
        <p><?php esc_html_e('Advanced pattern matching for powerful script exclusion control. Combine wildcard patterns for flexibility with regex for precision matching.', 'coreboost'); ?></p>
        <div class="notice notice-info inline">
            <p><strong>📚 Pattern Types:</strong></p>
            <ul style="margin-left: 20px; list-style: disc; margin-top: 5px;">
                <li><strong>Wildcard:</strong> Use * for matching. Example: <code>jquery-ui-*</code> matches all jQuery UI widgets</li>
                <li><strong>Regex:</strong> Full regex support. Example: <code>/^elementor[-_]/i</code> matches Elementor scripts</li>
                <li><strong>Profiles:</strong> Predefined exclusion sets for popular plugins (Elementor, WooCommerce, etc.)</li>
            </ul>
        </div>
        <?php
    }

    /**
     * Wildcard patterns callback
     */
    public function wildcard_patterns_callback() {
        $value = isset($this->options['script_wildcard_patterns']) ? $this->options['script_wildcard_patterns'] : '';
        ?>
        <textarea 
            name="coreboost_options[script_wildcard_patterns]" 
            id="script_wildcard_patterns" 
            rows="6" 
            class="large-text code"
            placeholder="jquery-ui-*&#10;custom-plugin-*&#10;*-analytics&#10;theme-handler-*"
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Add wildcard patterns for flexible script matching. One pattern per line. Use * as wildcard character.', 'coreboost'); ?>
        </p>
        <p class="description">
            <strong><?php esc_html_e('Examples:', 'coreboost'); ?></strong>
            <code>elementor-*</code>, <code>wc-*</code>, <code>*-custom</code>
        </p>
        <?php
    }

    /**
     * Regex patterns callback
     */
    public function regex_patterns_callback() {
        $value = isset($this->options['script_regex_patterns']) ? $this->options['script_regex_patterns'] : '';
        ?>
        <textarea 
            name="coreboost_options[script_regex_patterns]" 
            id="script_regex_patterns" 
            rows="6" 
            class="large-text code monospace"
            placeholder="/^elementor[-_]/i&#10;/^woocommerce[-_]/i&#10;/jquery[-_]ui[-_].*$/i"
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Add regular expression patterns for advanced matching. One pattern per line. Must include delimiters and flags.', 'coreboost'); ?>
        </p>
        <p class="description">
            <strong><?php esc_html_e('Format:', 'coreboost'); ?></strong>
            <code>/pattern/flags</code> (e.g., <code>/^my[-_]script/i</code> for case-insensitive matching)
        </p>
        <?php
    }

    /**
     * Plugin profiles callback
     */
    public function plugin_profiles_callback() {
        $value = isset($this->options['script_plugin_profiles']) ? $this->options['script_plugin_profiles'] : '';
        
        // Get available profiles
        $matcher = new Pattern_Matcher();
        $available_profiles = $matcher->get_available_profiles();
        
        ?>
        <fieldset>
            <?php foreach ($available_profiles as $profile_key): ?>
                <?php 
                $selected = false;
                if (!empty($value)) {
                    $selected_profiles = array_map('trim', explode(',', $value));
                    $selected = in_array($profile_key, $selected_profiles, true);
                }
                ?>
                <label style="display: block; margin-bottom: 8px;">
                    <input 
                        type="checkbox" 
                        class="plugin-profile-checkbox"
                        value="<?php echo esc_attr($profile_key); ?>"
                        <?php checked($selected, true); ?>
                    >
                    <strong><?php echo esc_html(ucwords(str_replace('-', ' ', $profile_key))); ?></strong>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <input type="hidden" name="coreboost_options[script_plugin_profiles]" id="script_plugin_profiles" value="<?php echo esc_attr($value); ?>">
        <p class="description">
            <?php esc_html_e('Select predefined plugin profiles to automatically exclude known scripts from popular plugins. Saves time and reduces compatibility issues.', 'coreboost'); ?>
        </p>
        <script>
        jQuery(document).ready(function($) {
            // Sync checkboxes with hidden input
            function updateProfileInput() {
                var selected = [];
                $('input.plugin-profile-checkbox:checked').each(function() {
                    selected.push($(this).val());
                });
                $('#script_plugin_profiles').val(selected.join(', '));
            }
            
            $('input.plugin-profile-checkbox').on('change', updateProfileInput);
        });
        </script>
        <?php
    }

    /**
     * Event Hijacking section callback
     */
    public function event_hijacking_section_callback() {
        ?>
        <p><?php esc_html_e('Control when and how scripts load using event-driven triggers and priority-based queues. Maximizes performance while maintaining functionality.', 'coreboost'); ?></p>
        <div class="notice notice-warning inline">
            <p><strong>⚡ Advanced Feature:</strong> Event hijacking requires careful testing. Start with default settings and adjust based on your site performance metrics.</p>
        </div>
        <?php
    }

    /**
     * Enable event hijacking callback
     */
    public function enable_event_hijacking_callback() {
        $value = isset($this->options['enable_event_hijacking']) ? $this->options['enable_event_hijacking'] : false;
        $checked = $value ? 'checked' : '';
        ?>
        <input 
            type="checkbox" 
            name="coreboost_options[enable_event_hijacking]" 
            value="1"
            <?php echo esc_attr($checked); ?>
        >
        <label><?php esc_html_e('Enable priority-based script loading with custom event triggers', 'coreboost'); ?></label>
        <p class="description">
            <?php esc_html_e('When enabled, scripts load based on user interactions, page state, and custom conditions instead of fixed delays.', 'coreboost'); ?>
        </p>
        <?php
    }

    /**
     * Event hijack triggers callback
     */
    public function event_hijack_triggers_callback() {
        $value = isset($this->options['event_hijack_triggers']) ? $this->options['event_hijack_triggers'] : 'user_interaction,browser_idle';
        $hijacking_enabled = isset($this->options['enable_event_hijacking']) && $this->options['enable_event_hijacking'];
        
        $triggers = [
            'user_interaction' => [
                'label' => 'User Interaction',
                'description' => 'Triggers on mouse/keyboard/touch events (100ms debounce)'
            ],
            'visibility_change' => [
                'label' => 'Page Visibility',
                'description' => 'Triggers when user returns to the page'
            ],
            'browser_idle' => [
                'label' => 'Browser Idle',
                'description' => 'Triggers via requestIdleCallback when browser is idle'
            ],
            'page_load_complete' => [
                'label' => 'Page Load',
                'description' => 'Triggers after page load event completes'
            ],
            'network_online' => [
                'label' => 'Network Online',
                'description' => 'Triggers when connection is restored'
            ],
        ];
        ?>
        <fieldset <?php echo !$hijacking_enabled ? 'disabled' : ''; ?>>
            <?php foreach ($triggers as $trigger_key => $trigger): ?>
                <?php 
                $selected = strpos($value, $trigger_key) !== false;
                ?>
                <label style="display: block; margin-bottom: 8px;">
                    <input 
                        type="checkbox" 
                        name="coreboost_options[event_hijack_triggers_cb][]"
                        class="hijack-trigger-checkbox"
                        value="<?php echo esc_attr($trigger_key); ?>"
                        <?php checked($selected, true); ?>
                        <?php echo !$hijacking_enabled ? 'disabled' : ''; ?>
                    >
                    <strong><?php echo esc_html($trigger['label']); ?></strong>
                    <br>
                    <span style="margin-left: 25px; color: #666; font-size: 12px;">
                        <?php echo esc_html($trigger['description']); ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <input type="hidden" name="coreboost_options[event_hijack_triggers]" id="event_hijack_triggers" value="<?php echo esc_attr($value); ?>">
        <p class="description">
            <?php esc_html_e('Select which events should trigger script loading. Multiple triggers can be active simultaneously.', 'coreboost'); ?>
        </p>
        <script>
        jQuery(document).ready(function($) {
            function updateTriggersInput() {
                var selected = [];
                $('input.hijack-trigger-checkbox:checked').each(function() {
                    selected.push($(this).val());
                });
                $('#event_hijack_triggers').val(selected.join(','));
            }
            
            $('input.hijack-trigger-checkbox').on('change', updateTriggersInput);
        });
        </script>
        <?php
    }

    /**
     * Script load priority callback
     */
    public function script_load_priority_callback() {
        $value = isset($this->options['script_load_priority']) ? $this->options['script_load_priority'] : 'standard';
        $hijacking_enabled = isset($this->options['enable_event_hijacking']) && $this->options['enable_event_hijacking'];
        
        $strategies = [
            'standard' => [
                'label' => 'Standard Loading',
                'description' => 'Equal priority for all scripts'
            ],
            'critical_first' => [
                'label' => 'Critical First',
                'description' => 'Load critical scripts before non-critical ones'
            ],
            'lazy_load' => [
                'label' => 'Lazy Loading',
                'description' => 'Load non-critical scripts only on user demand'
            ],
        ];
        ?>
        <fieldset <?php echo !$hijacking_enabled ? 'disabled' : ''; ?>>
            <?php foreach ($strategies as $strategy_key => $strategy): ?>
                <label style="display: block; margin-bottom: 10px;">
                    <input 
                        type="radio" 
                        name="coreboost_options[script_load_priority]" 
                        value="<?php echo esc_attr($strategy_key); ?>"
                        <?php checked($value, $strategy_key); ?>
                        <?php echo !$hijacking_enabled ? 'disabled' : ''; ?>
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
            <?php esc_html_e('Choose how scripts should be prioritized when event hijacking is enabled.', 'coreboost'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize and validate advanced settings
     *
     * @param array $input Raw input values
     * @param array $sanitized Current sanitized values
     * @return array Updated sanitized values
     */
    public function sanitize_settings($input, $sanitized) {
        // Sanitize wildcard patterns
        if (isset($input['script_wildcard_patterns'])) {
            $sanitized['script_wildcard_patterns'] = sanitize_textarea_field($input['script_wildcard_patterns']);
        }

        // Sanitize regex patterns
        if (isset($input['script_regex_patterns'])) {
            $sanitized['script_regex_patterns'] = sanitize_textarea_field($input['script_regex_patterns']);
        }

        // Sanitize plugin profiles
        if (isset($input['script_plugin_profiles'])) {
            $sanitized['script_plugin_profiles'] = sanitize_text_field($input['script_plugin_profiles']);
        }

        // Sanitize event hijacking enable
        if (isset($input['enable_event_hijacking'])) {
            $sanitized['enable_event_hijacking'] = !empty($input['enable_event_hijacking']);
        }

        // Sanitize hijack triggers
        if (isset($input['event_hijack_triggers'])) {
            $sanitized['event_hijack_triggers'] = sanitize_text_field($input['event_hijack_triggers']);
        }

        // Sanitize load priority
        if (isset($input['script_load_priority'])) {
            $valid_priorities = array('standard', 'critical_first', 'lazy_load');
            if (in_array($input['script_load_priority'], $valid_priorities)) {
                $sanitized['script_load_priority'] = $input['script_load_priority'];
            }
        }

        return $sanitized;
    }
}
