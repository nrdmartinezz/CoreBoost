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
            __('Advanced Pattern Matching (Phase 3)', 'coreboost'),
            array($this, 'pattern_matching_section_callback'),
            'coreboost-advanced'
        );

        // Wildcard patterns field
        add_settings_field(
            'script_wildcard_patterns',
            __('Wildcard Patterns', 'coreboost'),
            array($this, 'wildcard_patterns_callback'),
            'coreboost-advanced',
            'coreboost_pattern_matching_section'
        );

        // Regex patterns field
        add_settings_field(
            'script_regex_patterns',
            __('Regular Expression Patterns', 'coreboost'),
            array($this, 'regex_patterns_callback'),
            'coreboost-advanced',
            'coreboost_pattern_matching_section'
        );

        // Plugin profiles field
        add_settings_field(
            'script_plugin_profiles',
            __('Plugin Profile Exclusions', 'coreboost'),
            array($this, 'plugin_profiles_callback'),
            'coreboost-advanced',
            'coreboost_pattern_matching_section'
        );

        // Phase 4: Event Hijacking Section
        add_settings_section(
            'coreboost_event_hijacking_section',
            __('Event-Driven Loading with Priorities (Phase 4)', 'coreboost'),
            array($this, 'event_hijacking_section_callback'),
            'coreboost-advanced'
        );

        // Enable event hijacking
        add_settings_field(
            'enable_event_hijacking',
            __('Enable Event Hijacking', 'coreboost'),
            array($this, 'enable_event_hijacking_callback'),
            'coreboost-advanced',
            'coreboost_event_hijacking_section'
        );

        // Event trigger strategy
        add_settings_field(
            'event_hijack_triggers',
            __('Trigger Strategies', 'coreboost'),
            array($this, 'event_hijack_triggers_callback'),
            'coreboost-advanced',
            'coreboost_event_hijacking_section'
        );

        // Script load priority
        add_settings_field(
            'script_load_priority',
            __('Load Priority Strategy', 'coreboost'),
            array($this, 'script_load_priority_callback'),
            'coreboost-advanced',
            'coreboost_event_hijacking_section'
        );

        // Phase 5: Delay JavaScript Section
        add_settings_section(
            'coreboost_delay_js_section',
            __('Delay JavaScript Execution', 'coreboost'),
            array($this, 'delay_js_section_callback'),
            'coreboost-advanced'
        );

        // Enable delay JS
        add_settings_field(
            'enable_delay_js',
            __('Enable Delay JavaScript', 'coreboost'),
            array($this, 'enable_delay_js_callback'),
            'coreboost-advanced',
            'coreboost_delay_js_section'
        );

        // Delay JS trigger strategy
        add_settings_field(
            'delay_js_trigger',
            __('Trigger Strategy', 'coreboost'),
            array($this, 'delay_js_trigger_callback'),
            'coreboost-advanced',
            'coreboost_delay_js_section'
        );

        // Delay JS timeout
        add_settings_field(
            'delay_js_timeout',
            __('Fallback Timeout', 'coreboost'),
            array($this, 'delay_js_timeout_callback'),
            'coreboost-advanced',
            'coreboost_delay_js_section'
        );

        // Delay JS custom delay
        add_settings_field(
            'delay_js_custom_delay',
            __('Custom Delay', 'coreboost'),
            array($this, 'delay_js_custom_delay_callback'),
            'coreboost-advanced',
            'coreboost_delay_js_section'
        );

        // Delay JS include inline
        add_settings_field(
            'delay_js_include_inline',
            __('Include Inline Scripts', 'coreboost'),
            array($this, 'delay_js_include_inline_callback'),
            'coreboost-advanced',
            'coreboost_delay_js_section'
        );

        // Delay JS use default exclusions
        add_settings_field(
            'delay_js_use_default_exclusions',
            __('Use Default Exclusions', 'coreboost'),
            array($this, 'delay_js_use_default_exclusions_callback'),
            'coreboost-advanced',
            'coreboost_delay_js_section'
        );

        // Delay JS exclusions
        add_settings_field(
            'delay_js_exclusions',
            __('Custom Exclusions', 'coreboost'),
            array($this, 'delay_js_exclusions_callback'),
            'coreboost-advanced',
            'coreboost_delay_js_section'
        );

        // Phase 6: Custom Preconnect URLs Section
        add_settings_section(
            'coreboost_preconnect_section',
            __('Custom Preconnect URLs', 'coreboost'),
            array($this, 'preconnect_section_callback'),
            'coreboost-advanced'
        );

        // Custom preconnect URLs
        add_settings_field(
            'custom_preconnect_urls',
            __('Preconnect URLs', 'coreboost'),
            array($this, 'custom_preconnect_urls_callback'),
            'coreboost-advanced',
            'coreboost_preconnect_section'
        );

        // Phase 7: Auto CSS Defer Section
        add_settings_section(
            'coreboost_auto_css_defer_section',
            __('Auto CSS Deferring', 'coreboost'),
            array($this, 'auto_css_defer_section_callback'),
            'coreboost-advanced'
        );

        // Auto defer all CSS
        add_settings_field(
            'auto_defer_all_css',
            __('Auto-Defer All CSS', 'coreboost'),
            array($this, 'auto_defer_all_css_callback'),
            'coreboost-advanced',
            'coreboost_auto_css_defer_section'
        );
    }

    /**
     * Pattern Matching section callback
     */
    public function pattern_matching_section_callback() {
        ?>
        <p><?php esc_html_e('Advanced pattern matching for powerful script exclusion control. Combine wildcard patterns for flexibility with regex for precision matching.', 'coreboost'); ?></p>
        <div class="notice notice-info inline">
            <p><strong><?php esc_html_e('📋 Pattern Types:', 'coreboost'); ?></strong></p>
            <ul style="margin-left: 20px; list-style: disc; margin-top: 5px;">
                <li><strong><?php esc_html_e('Wildcard:', 'coreboost'); ?></strong> <?php esc_html_e('Use * for matching. Example:', 'coreboost'); ?> <code>jquery-ui-*</code> <?php esc_html_e('matches all jQuery UI widgets', 'coreboost'); ?></li>
                <li><strong><?php esc_html_e('Regex:', 'coreboost'); ?></strong> <?php esc_html_e('Full regex support. Example:', 'coreboost'); ?> <code>/^elementor[-_]/i</code> <?php esc_html_e('matches Elementor scripts', 'coreboost'); ?></li>
                <li><strong><?php esc_html_e('Profiles:', 'coreboost'); ?></strong> <?php esc_html_e('Predefined exclusion sets for popular plugins (Elementor, WooCommerce, etc.)', 'coreboost'); ?></li>
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
            <p><strong><?php esc_html_e('⚡ Advanced Feature:', 'coreboost'); ?></strong> <?php esc_html_e('Event hijacking requires careful testing. Start with default settings and adjust based on your site performance metrics.', 'coreboost'); ?></p>
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
     * Delay JavaScript section callback
     */
    public function delay_js_section_callback() {
        ?>
        <p><?php esc_html_e('Delay JavaScript execution until user interaction to reduce Total Blocking Time (TBT) and improve Time to Interactive (TTI). This is particularly effective for third-party scripts like analytics, chat widgets, and advertising.', 'coreboost'); ?></p>
        <div class="notice notice-warning inline">
            <p><strong><?php esc_html_e('⚠️ Important:', 'coreboost'); ?></strong> <?php esc_html_e('Test thoroughly after enabling. Some scripts may break if delayed. Use exclusions to keep critical scripts loading normally.', 'coreboost'); ?></p>
        </div>
        <?php
    }

    /**
     * Enable delay JS callback
     */
    public function enable_delay_js_callback() {
        $value = isset($this->options['enable_delay_js']) ? $this->options['enable_delay_js'] : false;
        $checked = $value ? 'checked' : '';
        ?>
        <input 
            type="checkbox" 
            name="coreboost_options[enable_delay_js]" 
            id="enable_delay_js"
            value="1"
            <?php echo esc_attr($checked); ?>
        >
        <label for="enable_delay_js"><?php esc_html_e('Delay non-critical JavaScript until user interaction', 'coreboost'); ?></label>
        <p class="description">
            <?php esc_html_e('Scripts are stored as text/template and only executed when triggered. This can significantly reduce TBT but requires careful exclusion management.', 'coreboost'); ?>
        </p>
        <?php
    }

    /**
     * Delay JS trigger strategy callback
     */
    public function delay_js_trigger_callback() {
        $value = isset($this->options['delay_js_trigger']) ? $this->options['delay_js_trigger'] : 'user_interaction';
        $delay_enabled = isset($this->options['enable_delay_js']) && $this->options['enable_delay_js'];
        
        $triggers = [
            'user_interaction' => [
                'label' => 'User Interaction',
                'description' => 'Load scripts on mouse, keyboard, touch, or scroll events (recommended)'
            ],
            'browser_idle' => [
                'label' => 'Browser Idle',
                'description' => 'Load scripts when browser is idle via requestIdleCallback'
            ],
            'page_load_complete' => [
                'label' => 'Page Load Complete',
                'description' => 'Load scripts after the window load event fires'
            ],
            'custom_delay' => [
                'label' => 'Custom Delay',
                'description' => 'Load scripts after a fixed delay (configured below)'
            ],
        ];
        ?>
        <fieldset <?php echo !$delay_enabled ? 'style="opacity: 0.6;"' : ''; ?>>
            <?php foreach ($triggers as $trigger_key => $trigger): ?>
                <label style="display: block; margin-bottom: 10px;">
                    <input 
                        type="radio" 
                        name="coreboost_options[delay_js_trigger]" 
                        value="<?php echo esc_attr($trigger_key); ?>"
                        <?php checked($value, $trigger_key); ?>
                        <?php echo !$delay_enabled ? 'disabled' : ''; ?>
                    >
                    <strong><?php echo esc_html($trigger['label']); ?></strong>
                    <br>
                    <span style="margin-left: 25px; color: #666; font-size: 12px;">
                        <?php echo esc_html($trigger['description']); ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php
    }

    /**
     * Delay JS timeout callback
     */
    public function delay_js_timeout_callback() {
        $value = isset($this->options['delay_js_timeout']) ? intval($this->options['delay_js_timeout']) : 10000;
        $delay_enabled = isset($this->options['enable_delay_js']) && $this->options['enable_delay_js'];
        ?>
        <input 
            type="range" 
            name="coreboost_options[delay_js_timeout]" 
            id="delay_js_timeout"
            min="1000" 
            max="20000" 
            step="500"
            value="<?php echo esc_attr($value); ?>"
            <?php echo !$delay_enabled ? 'disabled' : ''; ?>
            oninput="document.getElementById('delay_js_timeout_display').textContent = this.value + 'ms'"
        >
        <span id="delay_js_timeout_display" style="margin-left: 10px; font-weight: bold;"><?php echo esc_html($value); ?>ms</span>
        <p class="description">
            <?php esc_html_e('Maximum time to wait before loading scripts even without user interaction. Acts as a safety fallback.', 'coreboost'); ?>
        </p>
        <?php
    }

    /**
     * Delay JS custom delay callback
     */
    public function delay_js_custom_delay_callback() {
        $value = isset($this->options['delay_js_custom_delay']) ? intval($this->options['delay_js_custom_delay']) : 3000;
        $delay_enabled = isset($this->options['enable_delay_js']) && $this->options['enable_delay_js'];
        $trigger = isset($this->options['delay_js_trigger']) ? $this->options['delay_js_trigger'] : 'user_interaction';
        $is_custom = $trigger === 'custom_delay';
        ?>
        <input 
            type="range" 
            name="coreboost_options[delay_js_custom_delay]" 
            id="delay_js_custom_delay"
            min="0" 
            max="10000" 
            step="500"
            value="<?php echo esc_attr($value); ?>"
            <?php echo (!$delay_enabled || !$is_custom) ? 'disabled' : ''; ?>
            oninput="document.getElementById('delay_js_custom_delay_display').textContent = this.value + 'ms'"
        >
        <span id="delay_js_custom_delay_display" style="margin-left: 10px; font-weight: bold;"><?php echo esc_html($value); ?>ms</span>
        <p class="description">
            <?php esc_html_e('Fixed delay before loading scripts. Only applies when "Custom Delay" trigger is selected.', 'coreboost'); ?>
        </p>
        <?php
    }

    /**
     * Delay JS include inline callback
     */
    public function delay_js_include_inline_callback() {
        $value = isset($this->options['delay_js_include_inline']) ? $this->options['delay_js_include_inline'] : false;
        $delay_enabled = isset($this->options['enable_delay_js']) && $this->options['enable_delay_js'];
        $checked = $value ? 'checked' : '';
        ?>
        <input 
            type="checkbox" 
            name="coreboost_options[delay_js_include_inline]" 
            id="delay_js_include_inline"
            value="1"
            <?php echo esc_attr($checked); ?>
            <?php echo !$delay_enabled ? 'disabled' : ''; ?>
        >
        <label for="delay_js_include_inline"><?php esc_html_e('Also delay inline scripts (experimental)', 'coreboost'); ?></label>
        <p class="description">
            <?php esc_html_e('Delay inline scripts in addition to external scripts. Use with caution as this may affect critical functionality.', 'coreboost'); ?>
        </p>
        <?php
    }

    /**
     * Delay JS use default exclusions callback
     */
    public function delay_js_use_default_exclusions_callback() {
        $value = isset($this->options['delay_js_use_default_exclusions']) ? $this->options['delay_js_use_default_exclusions'] : true;
        $delay_enabled = isset($this->options['enable_delay_js']) && $this->options['enable_delay_js'];
        $checked = $value ? 'checked' : '';
        ?>
        <input 
            type="checkbox" 
            name="coreboost_options[delay_js_use_default_exclusions]" 
            id="delay_js_use_default_exclusions"
            value="1"
            <?php echo esc_attr($checked); ?>
            <?php echo !$delay_enabled ? 'disabled' : ''; ?>
        >
        <label for="delay_js_use_default_exclusions"><?php esc_html_e('Use default exclusions (jQuery, WP core, analytics, etc.)', 'coreboost'); ?></label>
        <p class="description">
            <?php esc_html_e('Recommended. Uses the same exclusion list as script deferring to prevent breaking critical scripts.', 'coreboost'); ?>
        </p>
        <?php
    }

    /**
     * Delay JS exclusions callback
     */
    public function delay_js_exclusions_callback() {
        $value = isset($this->options['delay_js_exclusions']) ? $this->options['delay_js_exclusions'] : '';
        $delay_enabled = isset($this->options['enable_delay_js']) && $this->options['enable_delay_js'];
        ?>
        <textarea 
            name="coreboost_options[delay_js_exclusions]" 
            id="delay_js_exclusions" 
            rows="6" 
            class="large-text code"
            placeholder="jquery&#10;recaptcha&#10;*.gstatic.com/*&#10;/gtag|gtm|analytics/i"
            <?php echo !$delay_enabled ? 'disabled' : ''; ?>
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Scripts to exclude from delay (one per line). These scripts will load normally without delay.', 'coreboost'); ?>
        </p>
        <p class="description">
            <strong><?php esc_html_e('Supported patterns:', 'coreboost'); ?></strong><br>
            • <?php esc_html_e('Exact match:', 'coreboost'); ?> <code>jquery-core</code><br>
            • <?php esc_html_e('Wildcard:', 'coreboost'); ?> <code>*.gstatic.com/*</code>, <code>elementor-*</code><br>
            • <?php esc_html_e('Regex:', 'coreboost'); ?> <code>/recaptcha|grecaptcha/i</code>
        </p>
        <?php
    }

    /**
     * Preconnect section callback
     */
    public function preconnect_section_callback() {
        ?>
        <p><?php esc_html_e('Add preconnect hints to reduce DNS lookup and connection time for third-party resources. Preconnects establish early connections to important domains.', 'coreboost'); ?></p>
        <div class="notice notice-info inline">
            <p><strong><?php esc_html_e('💡 Common preconnect domains:', 'coreboost'); ?></strong></p>
            <ul style="margin-left: 20px; list-style: disc; margin-top: 5px;">
                <li><code>https://www.googletagmanager.com</code> - <?php esc_html_e('Google Tag Manager', 'coreboost'); ?></li>
                <li><code>https://www.gstatic.com</code> - <?php esc_html_e('Google reCAPTCHA & other Google services', 'coreboost'); ?></li>
                <li><code>https://www.google-analytics.com</code> - <?php esc_html_e('Google Analytics', 'coreboost'); ?></li>
                <li><code>https://connect.facebook.net</code> - <?php esc_html_e('Facebook Pixel', 'coreboost'); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Custom preconnect URLs callback
     */
    public function custom_preconnect_urls_callback() {
        $value = isset($this->options['custom_preconnect_urls']) ? $this->options['custom_preconnect_urls'] : '';
        ?>
        <textarea 
            name="coreboost_options[custom_preconnect_urls]" 
            id="custom_preconnect_urls" 
            rows="5" 
            class="large-text code"
            placeholder="https://www.googletagmanager.com&#10;https://www.gstatic.com&#10;https://connect.facebook.net"
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Enter URLs to preconnect (one per line). Only include the domain with protocol (e.g., https://example.com). Invalid URLs will be silently skipped.', 'coreboost'); ?>
        </p>
        <?php
    }

    /**
     * Auto CSS Defer section callback
     */
    public function auto_css_defer_section_callback() {
        ?>
        <p><?php esc_html_e('Automatically defer all CSS stylesheets to improve render performance. This is the simplest approach to CSS optimization.', 'coreboost'); ?></p>
        <div class="notice notice-info inline">
            <p><strong><?php esc_html_e('🎨 How it works:', 'coreboost'); ?></strong></p>
            <ul style="margin-left: 20px; list-style: disc; margin-top: 5px;">
                <li><?php esc_html_e('All non-critical CSS files are deferred (loaded with media="print" then swapped to "all")', 'coreboost'); ?></li>
                <li><?php esc_html_e('Critical admin styles (admin-bar, dashicons) are automatically excluded', 'coreboost'); ?></li>
                <li><?php esc_html_e('Works with the CSS Defer settings in Performance tab - just enables auto-detection', 'coreboost'); ?></li>
                <li><?php esc_html_e('For best results, combine with Critical CSS in the Performance tab', 'coreboost'); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Auto defer all CSS callback
     */
    public function auto_defer_all_css_callback() {
        $checked = !empty($this->options['auto_defer_all_css']);
        ?>
        <label class="coreboost-toggle">
            <input type="checkbox" 
                   name="coreboost_options[auto_defer_all_css]" 
                   id="auto_defer_all_css" 
                   value="1" 
                   <?php checked($checked); ?>>
            <span class="coreboost-toggle-slider"></span>
            <?php esc_html_e('Automatically defer all CSS (except critical)', 'coreboost'); ?>
        </label>
        <p class="description" style="margin-top: 8px;">
            <?php esc_html_e('Enable to defer all CSS files automatically. This overrides the manual "Styles to Defer" list in the Performance tab. Browser will load CSS asynchronously, reducing render-blocking.', 'coreboost'); ?>
        </p>
        <div class="notice notice-warning inline" style="margin-top: 10px; padding: 8px 12px;">
            <p style="margin: 0;"><strong><?php esc_html_e('⚠️ Note:', 'coreboost'); ?></strong> <?php esc_html_e('CSS Deferring must be enabled in the Performance tab for this setting to work.', 'coreboost'); ?></p>
        </div>
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

        // Sanitize Delay JavaScript settings
        if (isset($input['enable_delay_js'])) {
            $sanitized['enable_delay_js'] = !empty($input['enable_delay_js']);
        } else {
            $sanitized['enable_delay_js'] = false;
        }

        if (isset($input['delay_js_trigger'])) {
            $valid_triggers = array('user_interaction', 'browser_idle', 'page_load_complete', 'custom_delay');
            if (in_array($input['delay_js_trigger'], $valid_triggers)) {
                $sanitized['delay_js_trigger'] = $input['delay_js_trigger'];
            }
        }

        if (isset($input['delay_js_timeout'])) {
            $sanitized['delay_js_timeout'] = max(1000, min(20000, intval($input['delay_js_timeout'])));
        }

        if (isset($input['delay_js_custom_delay'])) {
            $sanitized['delay_js_custom_delay'] = max(0, min(10000, intval($input['delay_js_custom_delay'])));
        }

        if (isset($input['delay_js_include_inline'])) {
            $sanitized['delay_js_include_inline'] = !empty($input['delay_js_include_inline']);
        } else {
            $sanitized['delay_js_include_inline'] = false;
        }

        if (isset($input['delay_js_use_default_exclusions'])) {
            $sanitized['delay_js_use_default_exclusions'] = !empty($input['delay_js_use_default_exclusions']);
        } else {
            $sanitized['delay_js_use_default_exclusions'] = false;
        }

        if (isset($input['delay_js_exclusions'])) {
            $sanitized['delay_js_exclusions'] = sanitize_textarea_field($input['delay_js_exclusions']);
        }

        // Sanitize custom preconnect URLs
        if (isset($input['custom_preconnect_urls'])) {
            $sanitized['custom_preconnect_urls'] = sanitize_textarea_field($input['custom_preconnect_urls']);
        }

        // Sanitize auto defer all CSS
        if (isset($input['auto_defer_all_css'])) {
            $sanitized['auto_defer_all_css'] = !empty($input['auto_defer_all_css']);
        } else {
            $sanitized['auto_defer_all_css'] = false;
        }

        return $sanitized;
    }
}
