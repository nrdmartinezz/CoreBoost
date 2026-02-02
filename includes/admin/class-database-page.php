<?php
/**
 * Database Page for CoreBoost Admin
 *
 * Displays database usage stats, cleanup actions, and maintenance options.
 *
 * @package CoreBoost
 * @since 3.1.0
 */

namespace CoreBoost\Admin;

use CoreBoost\Core\Compression_Analytics;
use CoreBoost\Core\Variant_Cache;
use CoreBoost\Core\Path_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Database_Page
 */
class Database_Page {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Maximum recommended variant cache chunks
     *
     * @var int
     */
    const RECOMMENDED_MAX_CHUNKS = 100;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
    }
    
    /**
     * Render the database page
     */
    public function render() {
        $this->handle_cleanup_actions();
        
        ?>
        <div class="wrap coreboost-database">
            <div class="coreboost-page-header">
                <div class="coreboost-logo">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <h1><?php _e('CoreBoost', 'coreboost'); ?> <span><?php _e('Database', 'coreboost'); ?></span></h1>
            </div>
            
            <?php $this->render_notices(); ?>
            
            <!-- Database Statistics -->
            <div class="coreboost-db-section">
                <h2><span class="dashicons dashicons-chart-pie"></span> <?php _e('Database Statistics', 'coreboost'); ?></h2>
                <div class="coreboost-db-stats">
                    <?php $this->render_database_stats(); ?>
                </div>
            </div>
            
            <!-- Variant Cache Warning -->
            <?php $this->render_variant_cache_warning(); ?>
            
            <!-- Cleanup Actions -->
            <div class="coreboost-db-section">
                <h2><span class="dashicons dashicons-trash"></span> <?php _e('Cleanup Actions', 'coreboost'); ?></h2>
                <p class="description"><?php _e('Use these tools to clean up accumulated data and free database space.', 'coreboost'); ?></p>
                <div class="coreboost-cleanup-actions">
                    <?php $this->render_cleanup_actions(); ?>
                </div>
            </div>
            
            <!-- Database Settings -->
            <div class="coreboost-db-section">
                <h2><span class="dashicons dashicons-admin-generic"></span> <?php _e('Database Settings', 'coreboost'); ?></h2>
                <form method="post" action="options.php">
                    <?php 
                    settings_fields('coreboost_database_options_group');
                    $this->render_database_settings();
                    submit_button(__('Save Settings', 'coreboost'));
                    ?>
                </form>
            </div>
            
            <!-- Danger Zone -->
            <div class="coreboost-db-section coreboost-danger-zone">
                <h2><span class="dashicons dashicons-warning"></span> <?php _e('Danger Zone', 'coreboost'); ?></h2>
                <?php $this->render_danger_zone(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render database statistics
     */
    private function render_database_stats() {
        global $wpdb;
        
        // Get variant cache stats
        $variant_chunks = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'coreboost_variant_cache_%'"
        );
        
        // Get total variant entries
        $variant_entries = 0;
        $variant_data = $wpdb->get_results(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE 'coreboost_variant_cache_%'"
        );
        foreach ($variant_data as $row) {
            $data = maybe_unserialize($row->option_value);
            if (is_array($data)) {
                $variant_entries += count($data);
            }
        }
        
        // Get compression analytics count
        $compression_data = get_option('coreboost_compression_analytics', array());
        $compression_entries = isset($compression_data['conversions']) ? count($compression_data['conversions']) : 0;
        
        // Get script metrics count
        $script_metrics = get_option('coreboost_script_metrics', array());
        $script_entries = count($script_metrics);
        
        // Get audit log count
        $audit_log = get_option('coreboost_image_variant_audit_log', array());
        $audit_entries = count($audit_log);
        
        // Get transient count
        $transient_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_coreboost_%'"
        );
        
        // Estimate total size
        $total_size = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE 'coreboost_%' OR option_name LIKE '_transient_coreboost_%'"
        );
        $size_formatted = $total_size ? size_format($total_size) : '0 B';
        
        ?>
        <div class="coreboost-db-stat">
            <div class="coreboost-db-stat-value"><?php echo esc_html($variant_entries); ?></div>
            <div class="coreboost-db-stat-label"><?php _e('Variant Cache Entries', 'coreboost'); ?></div>
        </div>
        
        <div class="coreboost-db-stat">
            <div class="coreboost-db-stat-value"><?php echo esc_html($variant_chunks); ?></div>
            <div class="coreboost-db-stat-label"><?php _e('Variant Cache Chunks', 'coreboost'); ?></div>
        </div>
        
        <div class="coreboost-db-stat">
            <div class="coreboost-db-stat-value"><?php echo esc_html($compression_entries); ?></div>
            <div class="coreboost-db-stat-label"><?php _e('Compression Analytics', 'coreboost'); ?></div>
        </div>
        
        <div class="coreboost-db-stat">
            <div class="coreboost-db-stat-value"><?php echo esc_html($script_entries); ?></div>
            <div class="coreboost-db-stat-label"><?php _e('Script Metrics', 'coreboost'); ?></div>
        </div>
        
        <div class="coreboost-db-stat">
            <div class="coreboost-db-stat-value"><?php echo esc_html($audit_entries); ?></div>
            <div class="coreboost-db-stat-label"><?php _e('Audit Log Entries', 'coreboost'); ?></div>
        </div>
        
        <div class="coreboost-db-stat">
            <div class="coreboost-db-stat-value"><?php echo esc_html($transient_count); ?></div>
            <div class="coreboost-db-stat-label"><?php _e('Transients', 'coreboost'); ?></div>
        </div>
        
        <div class="coreboost-db-stat">
            <div class="coreboost-db-stat-value"><?php echo esc_html($size_formatted); ?></div>
            <div class="coreboost-db-stat-label"><?php _e('Estimated DB Usage', 'coreboost'); ?></div>
        </div>
        <?php
    }
    
    /**
     * Render variant cache warning if approaching limit
     */
    private function render_variant_cache_warning() {
        global $wpdb;
        
        $variant_chunks = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'coreboost_variant_cache_%'"
        );
        
        $max_chunks = isset($this->options['variant_cache_max_chunks']) 
            ? intval($this->options['variant_cache_max_chunks']) 
            : self::RECOMMENDED_MAX_CHUNKS;
        
        $auto_prune = !empty($this->options['variant_cache_auto_prune']);
        
        if ($variant_chunks >= ($max_chunks * 0.8)) {
            $percentage = round(($variant_chunks / $max_chunks) * 100);
            ?>
            <div class="coreboost-warning-box">
                <span class="dashicons dashicons-warning"></span>
                <div class="coreboost-warning-content">
                    <h4><?php _e('Variant Cache Approaching Limit', 'coreboost'); ?></h4>
                    <p>
                        <?php printf(
                            __('Your variant cache is at %1$d%% capacity (%2$d of %3$d chunks). Consider clearing old entries or enabling auto-pruning.', 'coreboost'),
                            $percentage,
                            $variant_chunks,
                            $max_chunks
                        ); ?>
                    </p>
                    <?php if (!$auto_prune): ?>
                        <p>
                            <a href="#coreboost-auto-prune-setting" class="button button-small">
                                <?php _e('Enable Auto-Pruning', 'coreboost'); ?>
                            </a>
                            <?php
                            $clear_url = wp_nonce_url(
                                admin_url('admin.php?page=coreboost-database&action=clear_variant_cache'),
                                'coreboost_clear_variant_cache'
                            );
                            ?>
                            <a href="<?php echo esc_url($clear_url); ?>" class="button button-small">
                                <?php _e('Clear Variant Cache Now', 'coreboost'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render cleanup actions
     */
    private function render_cleanup_actions() {
        $actions = array(
            'clear_transients' => array(
                'label' => __('Clear All Transients', 'coreboost'),
                'description' => __('Delete all CoreBoost transient data', 'coreboost'),
                'nonce' => 'coreboost_clear_transients',
            ),
            'clear_variant_cache' => array(
                'label' => __('Clear Variant Cache', 'coreboost'),
                'description' => __('Clear image variant URL mappings (keeps files)', 'coreboost'),
                'nonce' => 'coreboost_clear_variant_cache',
            ),
            'clear_analytics' => array(
                'label' => __('Clear Analytics Data', 'coreboost'),
                'description' => __('Reset compression analytics', 'coreboost'),
                'nonce' => 'coreboost_clear_analytics',
            ),
            'clear_script_metrics' => array(
                'label' => __('Clear Script Metrics', 'coreboost'),
                'description' => __('Reset script performance data', 'coreboost'),
                'nonce' => 'coreboost_clear_script_metrics',
            ),
            'clear_audit_log' => array(
                'label' => __('Clear Audit Log', 'coreboost'),
                'description' => __('Delete variant audit log entries', 'coreboost'),
                'nonce' => 'coreboost_clear_audit_log',
            ),
            'clear_backups' => array(
                'label' => __('Clear Backup Options', 'coreboost'),
                'description' => __('Remove old migration backup options', 'coreboost'),
                'nonce' => 'coreboost_clear_backups',
            ),
        );
        
        foreach ($actions as $action => $data) {
            $url = wp_nonce_url(
                admin_url('admin.php?page=coreboost-database&action=' . $action),
                $data['nonce']
            );
            ?>
            <div class="coreboost-cleanup-item">
                <div class="coreboost-cleanup-info">
                    <h4><?php echo esc_html($data['label']); ?></h4>
                    <p><?php echo esc_html($data['description']); ?></p>
                </div>
                <a href="<?php echo esc_url($url); ?>" class="button">
                    <?php _e('Clean', 'coreboost'); ?>
                </a>
            </div>
            <?php
        }
    }
    
    /**
     * Render database settings
     */
    private function render_database_settings() {
        $max_chunks = isset($this->options['variant_cache_max_chunks']) 
            ? intval($this->options['variant_cache_max_chunks']) 
            : self::RECOMMENDED_MAX_CHUNKS;
        
        $auto_prune = !empty($this->options['variant_cache_auto_prune']);
        
        $retention_days = isset($this->options['analytics_retention_days']) 
            ? intval($this->options['analytics_retention_days']) 
            : 30;
        ?>
        <table class="coreboost-db-settings-table">
            <tr>
                <th><label for="variant_cache_max_chunks"><?php _e('Variant Cache Max Chunks', 'coreboost'); ?></label></th>
                <td>
                    <input type="number" 
                           id="variant_cache_max_chunks" 
                           name="coreboost_options[variant_cache_max_chunks]" 
                           value="<?php echo esc_attr($max_chunks); ?>" 
                           min="10" 
                           max="1000" 
                           step="10">
                    <p class="description">
                        <?php _e('Maximum number of variant cache chunks before warning. Each chunk holds ~100 images.', 'coreboost'); ?>
                    </p>
                </td>
            </tr>
            <tr id="coreboost-auto-prune-setting">
                <th><label for="variant_cache_auto_prune"><?php _e('Auto-Prune Variant Cache', 'coreboost'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="variant_cache_auto_prune" 
                               name="coreboost_options[variant_cache_auto_prune]" 
                               value="1" 
                               <?php checked($auto_prune); ?>>
                        <?php _e('Automatically remove oldest chunks when limit is reached', 'coreboost'); ?>
                    </label>
                    <p class="description" style="color: #dc3232;">
                        <strong><?php _e('Warning:', 'coreboost'); ?></strong>
                        <?php _e('Enabling this may cause some images to temporarily lose their optimized variants until re-cached.', 'coreboost'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="analytics_retention_days"><?php _e('Analytics Retention', 'coreboost'); ?></label></th>
                <td>
                    <input type="number" 
                           id="analytics_retention_days" 
                           name="coreboost_options[analytics_retention_days]" 
                           value="<?php echo esc_attr($retention_days); ?>" 
                           min="7" 
                           max="365" 
                           step="1">
                    <?php _e('days', 'coreboost'); ?>
                    <p class="description">
                        <?php _e('How long to keep analytics and metrics data before automatic cleanup.', 'coreboost'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render danger zone
     */
    private function render_danger_zone() {
        $reset_url = wp_nonce_url(
            admin_url('admin.php?page=coreboost-database&action=full_reset'),
            'coreboost_full_reset'
        );
        ?>
        <div class="coreboost-danger-item">
            <h4><?php _e('Full Database Reset', 'coreboost'); ?></h4>
            <p><?php _e('This will delete ALL CoreBoost data from the database including analytics, caches, and audit logs. Your plugin settings will be preserved.', 'coreboost'); ?></p>
            <a href="<?php echo esc_url($reset_url); ?>" 
               class="button button-secondary" 
               onclick="return confirm('<?php echo esc_js(__('Are you sure? This action cannot be undone!', 'coreboost')); ?>');">
                <?php _e('Reset All Data', 'coreboost'); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Handle cleanup actions
     */
    private function handle_cleanup_actions() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
        $nonce = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        
        if (!$action || !$nonce) {
            return;
        }
        
        global $wpdb;
        $redirect_args = array();
        
        switch ($action) {
            case 'clear_transients':
                if (wp_verify_nonce($nonce, 'coreboost_clear_transients')) {
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_coreboost_%'");
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_coreboost_%'");
                    $redirect_args['transients_cleared'] = '1';
                }
                break;
                
            case 'clear_variant_cache':
                if (wp_verify_nonce($nonce, 'coreboost_clear_variant_cache')) {
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'coreboost_variant_cache_%'");
                    $redirect_args['variant_cache_cleared'] = '1';
                }
                break;
                
            case 'clear_analytics':
                if (wp_verify_nonce($nonce, 'coreboost_clear_analytics')) {
                    delete_option('coreboost_compression_analytics');
                    $redirect_args['analytics_cleared'] = '1';
                }
                break;
                
            case 'clear_script_metrics':
                if (wp_verify_nonce($nonce, 'coreboost_clear_script_metrics')) {
                    delete_option('coreboost_script_metrics');
                    delete_option('coreboost_pattern_effectiveness');
                    $redirect_args['metrics_cleared'] = '1';
                }
                break;
                
            case 'clear_audit_log':
                if (wp_verify_nonce($nonce, 'coreboost_clear_audit_log')) {
                    delete_option('coreboost_image_variant_audit_log');
                    $redirect_args['audit_cleared'] = '1';
                }
                break;
                
            case 'clear_backups':
                if (wp_verify_nonce($nonce, 'coreboost_clear_backups')) {
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'coreboost_options_backup_%'");
                    $redirect_args['backups_cleared'] = '1';
                }
                break;
                
            case 'full_reset':
                if (wp_verify_nonce($nonce, 'coreboost_full_reset')) {
                    // Clear everything except main options
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'coreboost_%' AND option_name != 'coreboost_options' AND option_name != 'coreboost_version'");
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_coreboost_%'");
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_coreboost_%'");
                    $redirect_args['full_reset'] = '1';
                }
                break;
        }
        
        if (!empty($redirect_args)) {
            wp_redirect(add_query_arg($redirect_args, remove_query_arg(array('action', '_wpnonce'))));
            exit;
        }
    }
    
    /**
     * Render notices
     */
    private function render_notices() {
        $notices = array(
            'transients_cleared' => __('All transients cleared successfully!', 'coreboost'),
            'variant_cache_cleared' => __('Variant cache cleared successfully!', 'coreboost'),
            'analytics_cleared' => __('Analytics data cleared successfully!', 'coreboost'),
            'metrics_cleared' => __('Script metrics cleared successfully!', 'coreboost'),
            'audit_cleared' => __('Audit log cleared successfully!', 'coreboost'),
            'backups_cleared' => __('Backup options cleared successfully!', 'coreboost'),
            'full_reset' => __('Full database reset completed!', 'coreboost'),
        );
        
        foreach ($notices as $key => $message) {
            if (filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS) === '1') {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }
}
