<?php
/**
 * Custom Tag Settings
 *
 * Handles admin interface for custom tag management
 *
 * @package    CoreBoost
 * @subpackage CoreBoost/Admin
 */

namespace CoreBoost\Admin;

class Tag_Settings {

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
     * Register settings
     */
    public function register_settings() {
        // Register settings section
        add_settings_section(
            'coreboost_tag_section',
            'Custom Tag Manager',
            array($this, 'tag_section_callback'),
            'coreboost-tags'
        );

        // Head scripts field
        add_settings_field(
            'tag_head_scripts',
            'Head Scripts',
            array($this, 'tag_head_scripts_callback'),
            'coreboost-tags',
            'coreboost_tag_section'
        );

        // Body scripts field
        add_settings_field(
            'tag_body_scripts',
            'Body Scripts (Top)',
            array($this, 'tag_body_scripts_callback'),
            'coreboost-tags',
            'coreboost_tag_section'
        );

        // Footer scripts field
        add_settings_field(
            'tag_footer_scripts',
            'Footer Scripts',
            array($this, 'tag_footer_scripts_callback'),
            'coreboost-tags',
            'coreboost_tag_section'
        );

        // Load strategy field
        add_settings_field(
            'tag_load_strategy',
            'Load Strategy',
            array($this, 'tag_load_strategy_callback'),
            'coreboost-tags',
            'coreboost_tag_section'
        );

        // Custom delay field (conditional)
        add_settings_field(
            'tag_custom_delay',
            'Custom Delay (ms)',
            array($this, 'tag_custom_delay_callback'),
            'coreboost-tags',
            'coreboost_tag_section'
        );
    }

    /**
     * Section callback
     */
    public function tag_section_callback() {
        ?>
        <p>Add custom scripts and tags to your site. Tags can be placed in the <code>&lt;head&gt;</code>, at the top of <code>&lt;body&gt;</code>, or in the footer.</p>
        <div class="notice notice-info inline">
            <p><strong>Ã°Å¸â€™Â¡ Common Use Cases:</strong></p>
            <ul style="margin-left: 20px; list-style: disc;">
                <li><strong>Google Tag Manager:</strong> Add your GTM container snippet</li>
                <li><strong>Google Analytics:</strong> Add GA4 tracking code</li>
                <li><strong>Facebook Pixel:</strong> Add Meta pixel tracking</li>
                <li><strong>Hotjar, Clarity, etc.:</strong> Add any analytics or heatmap tools</li>
                <li><strong>Custom Scripts:</strong> Add any JavaScript needed for your site</li>
            </ul>
        </div>
        <div class="notice notice-warning inline">
            <p><strong>Ã¢Å¡Â Ã¯Â¸Â Important:</strong> Only paste script code you trust. Malicious code can compromise your site security.</p>
        </div>
        <?php
    }

    /**
     * Head scripts callback
     */
    public function tag_head_scripts_callback() {
        $value = isset($this->options['tag_head_scripts']) ? $this->options['tag_head_scripts'] : '';
        ?>
        <textarea 
            name="coreboost_options[tag_head_scripts]" 
            id="tag_head_scripts" 
            rows="10" 
            class="large-text code"
            placeholder="<!-- Example: Google Tag Manager -->
<script>
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-XXXXXX');
</script>"
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            Scripts placed here will be output in the <code>&lt;head&gt;</code> section. Ideal for tracking codes that need to load early.
        </p>
        <?php
    }

    /**
     * Body scripts callback
     */
    public function tag_body_scripts_callback() {
        $value = isset($this->options['tag_body_scripts']) ? $this->options['tag_body_scripts'] : '';
        ?>
        <textarea 
            name="coreboost_options[tag_body_scripts]" 
            id="tag_body_scripts" 
            rows="10" 
            class="large-text code"
            placeholder="<!-- Example: Google Tag Manager (noscript) -->
<noscript>
<iframe src=&quot;https://www.googletagmanager.com/ns.html?id=GTM-XXXXXX&quot;
height=&quot;0&quot; width=&quot;0&quot; style=&quot;display:none;visibility:hidden&quot;></iframe>
</noscript>"
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            Scripts placed here will be output immediately after the opening <code>&lt;body&gt;</code> tag. Used for noscript tags or early body content.
        </p>
        <?php
    }

    /**
     * Footer scripts callback
     */
    public function tag_footer_scripts_callback() {
        $value = isset($this->options['tag_footer_scripts']) ? $this->options['tag_footer_scripts'] : '';
        ?>
        <textarea 
            name="coreboost_options[tag_footer_scripts]" 
            id="tag_footer_scripts" 
            rows="10" 
            class="large-text code"
            placeholder="<!-- Example: Custom tracking script -->
<script>
console.log('Footer script loaded');
</script>"
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            Scripts placed here will be output in the footer before the closing <code>&lt;/body&gt;</code> tag. Good for analytics or non-critical scripts.
        </p>
        <?php
    }

    /**
     * Load strategy callback
     */
    public function tag_load_strategy_callback() {
        $value = isset($this->options['tag_load_strategy']) ? $this->options['tag_load_strategy'] : 'balanced';
        $strategies = array(
            'immediate' => array(
                'label' => 'Immediate',
                'description' => 'Load tags immediately (no delay). Use when you need instant tracking.'
            ),
            'balanced' => array(
                'label' => 'Balanced (3 seconds)',
                'description' => 'Delay tags by 3 seconds. Best balance between performance and tracking accuracy.'
            ),
            'aggressive' => array(
                'label' => 'Aggressive (5 seconds)',
                'description' => 'Delay tags by 5 seconds. Maximum performance gains, slight delay in tracking.'
            ),
            'user_interaction' => array(
                'label' => 'User Interaction',
                'description' => 'Load tags when user interacts (mouse, scroll, touch, key). Falls back to 10 seconds.'
            ),
            'browser_idle' => array(
                'label' => 'Browser Idle',
                'description' => 'Load tags when browser is idle. Uses requestIdleCallback with 1 second delay.'
            ),
            'custom' => array(
                'label' => 'Custom Delay',
                'description' => 'Set your own delay in milliseconds (see below).'
            )
        );
        ?>
        <fieldset>
            <?php foreach ($strategies as $strategy_key => $strategy): ?>
                <label style="display: block; margin-bottom: 10px;">
                    <input 
                        type="radio" 
                        name="coreboost_options[tag_load_strategy]" 
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
            Choose when your custom tags should load. Delayed loading improves page speed scores significantly.
        </p>
        <?php
    }

    /**
     * Custom delay callback
     */
    public function tag_custom_delay_callback() {
        $value = isset($this->options['tag_custom_delay']) ? intval($this->options['tag_custom_delay']) : 3000;
        $strategy = isset($this->options['tag_load_strategy']) ? $this->options['tag_load_strategy'] : 'balanced';
        ?>
        <input 
            type="number" 
            name="coreboost_options[tag_custom_delay]" 
            id="tag_custom_delay"
            value="<?php echo esc_attr($value); ?>"
            min="0"
            max="10000"
            step="100"
            class="small-text"
            <?php echo $strategy !== 'custom' ? 'disabled' : ''; ?>
        > ms
        <p class="description">
            Only used when "Custom Delay" strategy is selected. Enter delay in milliseconds (1000ms = 1 second).
        </p>
        <script>
        jQuery(document).ready(function($) {
            $('input[name="coreboost_options[tag_load_strategy]"]').on('change', function() {
                var isCustom = $(this).val() === 'custom';
                $('#tag_custom_delay').prop('disabled', !isCustom);
            });
        });
        </script>
        <?php
    }

    /**
     * Sanitize and validate tag settings
     *
     * @param array $input Raw input values
     * @param array $sanitized Current sanitized values
     * @return array Updated sanitized values
     */
    public function sanitize_settings($input, $sanitized) {
        // Sanitize head scripts
        if (isset($input['tag_head_scripts'])) {
            $sanitized['tag_head_scripts'] = $this->sanitize_script_field($input['tag_head_scripts']);
        }

        // Sanitize body scripts
        if (isset($input['tag_body_scripts'])) {
            $sanitized['tag_body_scripts'] = $this->sanitize_script_field($input['tag_body_scripts']);
        }

        // Sanitize footer scripts
        if (isset($input['tag_footer_scripts'])) {
            $sanitized['tag_footer_scripts'] = $this->sanitize_script_field($input['tag_footer_scripts']);
        }

        // Sanitize load strategy
        if (isset($input['tag_load_strategy'])) {
            $valid_strategies = array('immediate', 'balanced', 'aggressive', 'user_interaction', 'browser_idle', 'custom');
            if (in_array($input['tag_load_strategy'], $valid_strategies)) {
                $sanitized['tag_load_strategy'] = $input['tag_load_strategy'];
            }
        }

        // Sanitize custom delay
        if (isset($input['tag_custom_delay'])) {
            $delay = intval($input['tag_custom_delay']);
            $sanitized['tag_custom_delay'] = max(0, min(10000, $delay));
        }

        return $sanitized;
    }

    /**
     * Sanitize script field
     *
     * @param string $input Raw script content
     * @return string Sanitized script content
     */
    private function sanitize_script_field($input) {
        // Allow HTML tags and JavaScript
        // We trust admin users, but strip PHP tags for safety
        $sanitized = trim($input);
        
        // Remove PHP tags
        $sanitized = preg_replace('/<\?php.*?\?>/s', '', $sanitized);
        $sanitized = preg_replace('/<\?.*?\?>/s', '', $sanitized);
        
        return $sanitized;
    }
}
