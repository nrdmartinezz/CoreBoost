<?php
/**
 * Report Issue Page for CoreBoost Admin
 *
 * Provides GitHub Issues link and debug info collector for bug reports.
 *
 * @package CoreBoost
 * @since 3.1.0
 */

namespace CoreBoost\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Report_Page
 */
class Report_Page {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * GitHub repository URL
     *
     * @var string
     */
    const GITHUB_REPO = 'https://github.com/developer/coreboost';
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
    }
    
    /**
     * Render the report page
     */
    public function render() {
        ?>
        <div class="wrap coreboost-report">
            <div class="coreboost-page-header">
                <div class="coreboost-logo">
                    <span class="dashicons dashicons-sos"></span>
                </div>
                <h1><?php _e('CoreBoost', 'coreboost'); ?> <span><?php _e('Report Issue', 'coreboost'); ?></span></h1>
            </div>
            
            <!-- Report Options -->
            <div class="coreboost-report-section">
                <h2><span class="dashicons dashicons-megaphone"></span> <?php _e('How to Report', 'coreboost'); ?></h2>
                <p><?php _e('Found a bug or have a feature request? We\'d love to hear from you!', 'coreboost'); ?></p>
                
                <div class="coreboost-report-options">
                    <div class="coreboost-report-option">
                        <span class="dashicons dashicons-external"></span>
                        <h3><?php _e('GitHub Issues', 'coreboost'); ?></h3>
                        <p><?php _e('Report bugs or request features on our GitHub repository.', 'coreboost'); ?></p>
                        <a href="<?php echo esc_url(self::GITHUB_REPO . '/issues/new'); ?>" 
                           class="button button-primary coreboost-btn-primary" 
                           target="_blank">
                            <?php _e('Open GitHub Issue', 'coreboost'); ?>
                        </a>
                    </div>
                    
                    <div class="coreboost-report-option">
                        <span class="dashicons dashicons-list-view"></span>
                        <h3><?php _e('View Existing Issues', 'coreboost'); ?></h3>
                        <p><?php _e('Check if your issue has already been reported.', 'coreboost'); ?></p>
                        <a href="<?php echo esc_url(self::GITHUB_REPO . '/issues'); ?>" 
                           class="button coreboost-btn" 
                           target="_blank">
                            <?php _e('Browse Issues', 'coreboost'); ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Debug Information -->
            <div class="coreboost-report-section">
                <h2><span class="dashicons dashicons-info"></span> <?php _e('Debug Information', 'coreboost'); ?></h2>
                <p><?php _e('Copy this information when reporting an issue to help us diagnose the problem faster.', 'coreboost'); ?></p>
                
                <div class="coreboost-debug-info">
                    <textarea id="coreboost-debug-output" readonly rows="20"><?php echo esc_textarea($this->get_debug_info()); ?></textarea>
                    <button type="button" class="button coreboost-btn" id="coreboost-copy-debug">
                        <span class="dashicons dashicons-clipboard"></span>
                        <?php _e('Copy to Clipboard', 'coreboost'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Critical Errors Log -->
            <div class="coreboost-report-section">
                <h2><span class="dashicons dashicons-warning"></span> <?php _e('Recent Critical Errors', 'coreboost'); ?></h2>
                <?php $this->render_critical_errors(); ?>
            </div>
        </div>
        
        <script>
            document.getElementById('coreboost-copy-debug').addEventListener('click', function() {
                var textarea = document.getElementById('coreboost-debug-output');
                textarea.select();
                document.execCommand('copy');
                
                var btn = this;
                var originalText = btn.innerHTML;
                btn.innerHTML = '<span class="dashicons dashicons-yes"></span> <?php echo esc_js(__('Copied!', 'coreboost')); ?>';
                
                setTimeout(function() {
                    btn.innerHTML = originalText;
                }, 2000);
            });
        </script>
        <?php
    }
    
    /**
     * Get debug information
     *
     * @return string Debug info text
     */
    private function get_debug_info() {
        global $wpdb;
        
        // Get active theme
        $theme = wp_get_theme();
        
        // Get active plugins
        $active_plugins = get_option('active_plugins', array());
        $plugin_list = array();
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $plugin_list[] = $plugin_data['Name'] . ' v' . $plugin_data['Version'];
        }
        
        // Get CoreBoost settings summary
        $enabled_features = array();
        $feature_map = array(
            'enable_script_defer' => 'Script Deferring',
            'enable_css_defer' => 'CSS Deferring',
            'enable_image_optimization' => 'Image Optimization',
            'enable_image_format_conversion' => 'Format Conversion',
            'enable_lazy_loading' => 'Lazy Loading',
            'enable_font_optimization' => 'Font Optimization',
            'enable_hero_preload_extraction' => 'Hero Preloading',
            'enable_caching' => 'Caching',
        );
        
        foreach ($feature_map as $key => $label) {
            if (!empty($this->options[$key])) {
                $enabled_features[] = $label;
            }
        }
        
        $debug = "### CoreBoost Debug Information ###\n\n";
        
        $debug .= "## Environment\n";
        $debug .= "WordPress Version: " . get_bloginfo('version') . "\n";
        $debug .= "PHP Version: " . phpversion() . "\n";
        $debug .= "MySQL Version: " . $wpdb->db_version() . "\n";
        $debug .= "Server: " . (isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown') . "\n";
        $debug .= "Memory Limit: " . ini_get('memory_limit') . "\n";
        $debug .= "Max Execution Time: " . ini_get('max_execution_time') . "s\n";
        $debug .= "Multisite: " . (is_multisite() ? 'Yes' : 'No') . "\n\n";
        
        $debug .= "## CoreBoost\n";
        $debug .= "Version: " . COREBOOST_VERSION . "\n";
        $debug .= "Enabled Features: " . (empty($enabled_features) ? 'None' : implode(', ', $enabled_features)) . "\n\n";
        
        $debug .= "## Theme\n";
        $debug .= "Name: " . $theme->get('Name') . "\n";
        $debug .= "Version: " . $theme->get('Version') . "\n";
        $debug .= "Parent Theme: " . ($theme->parent() ? $theme->parent()->get('Name') : 'None') . "\n\n";
        
        $debug .= "## Active Plugins (" . count($plugin_list) . ")\n";
        foreach ($plugin_list as $plugin) {
            $debug .= "- " . $plugin . "\n";
        }
        
        $debug .= "\n## PHP Extensions\n";
        $important_extensions = array('gd', 'imagick', 'curl', 'mbstring', 'zip');
        foreach ($important_extensions as $ext) {
            $debug .= $ext . ": " . (extension_loaded($ext) ? 'Enabled' : 'Disabled') . "\n";
        }
        
        // Check for image format support
        $debug .= "\n## Image Format Support\n";
        if (function_exists('imageavif')) {
            $debug .= "AVIF (GD): Supported\n";
        } else {
            $debug .= "AVIF (GD): Not Supported\n";
        }
        if (function_exists('imagewebp')) {
            $debug .= "WebP (GD): Supported\n";
        } else {
            $debug .= "WebP (GD): Not Supported\n";
        }
        if (class_exists('Imagick')) {
            $imagick = new \Imagick();
            $formats = $imagick->queryFormats();
            $debug .= "AVIF (Imagick): " . (in_array('AVIF', $formats) ? 'Supported' : 'Not Supported') . "\n";
            $debug .= "WebP (Imagick): " . (in_array('WEBP', $formats) ? 'Supported' : 'Not Supported') . "\n";
        }
        
        return $debug;
    }
    
    /**
     * Render critical errors log
     */
    private function render_critical_errors() {
        $errors = get_option('coreboost_critical_errors', array());
        
        if (empty($errors)) {
            ?>
            <div class="coreboost-no-errors">
                <span class="dashicons dashicons-yes-alt"></span>
                <p><?php _e('No critical errors logged. Great!', 'coreboost'); ?></p>
            </div>
            <?php
            return;
        }
        
        // Show last 10 errors
        $errors = array_slice(array_reverse($errors), 0, 10);
        ?>
        <table class="coreboost-errors-table">
            <thead>
                <tr>
                    <th><?php _e('Time', 'coreboost'); ?></th>
                    <th><?php _e('Category', 'coreboost'); ?></th>
                    <th><?php _e('Operation', 'coreboost'); ?></th>
                    <th><?php _e('Message', 'coreboost'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($errors as $error): ?>
                    <tr>
                        <td><?php echo esc_html($error['timestamp'] ?? 'Unknown'); ?></td>
                        <td><?php echo esc_html($error['category'] ?? 'Unknown'); ?></td>
                        <td><?php echo esc_html($error['operation'] ?? 'Unknown'); ?></td>
                        <td><?php echo esc_html($error['message'] ?? 'No message'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
