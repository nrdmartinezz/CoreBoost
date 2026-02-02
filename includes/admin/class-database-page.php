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
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
        
        // Handle cleanup actions early to avoid headers already sent
        add_action('admin_init', array($this, 'early_handle_cleanup_actions'), 5);
    }
    
    /**
     * Handle cleanup actions early (before headers sent)
     */
    public function early_handle_cleanup_actions() {
        // Only run on our database page
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($page !== 'coreboost-database') {
            return;
        }
        
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
                
            case 'clear_script_metrics':
                if (wp_verify_nonce($nonce, 'coreboost_clear_script_metrics')) {
                    delete_option('coreboost_script_metrics');
                    delete_option('coreboost_pattern_effectiveness');
                    $redirect_args['metrics_cleared'] = '1';
                }
                break;
                
            case 'clear_backups':
                if (wp_verify_nonce($nonce, 'coreboost_clear_backups')) {
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'coreboost_options_backup_%'");
                    $redirect_args['backups_cleared'] = '1';
                }
                break;
                
            case 'clear_legacy_variants':
                if (wp_verify_nonce($nonce, 'coreboost_clear_legacy_variants')) {
                    // Delete variant files
                    $upload_dir = wp_upload_dir();
                    $variants_path = $upload_dir['basedir'] . '/coreboost-variants/';
                    if (is_dir($variants_path)) {
                        $this->recursive_delete($variants_path);
                    }
                    // Delete variant-related database entries
                    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'coreboost_variant_cache_%'");
                    delete_option('coreboost_compression_analytics');
                    delete_option('coreboost_image_variant_audit_log');
                    $redirect_args['legacy_variants_cleared'] = '1';
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
            wp_safe_redirect(add_query_arg($redirect_args, remove_query_arg(array('action', '_wpnonce'))));
            exit;
        }
    }
    
    /**
     * Render the database page
     */
    public function render() {
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
        
        // Get script metrics count
        $script_metrics = get_option('coreboost_script_metrics', array());
        $script_entries = count($script_metrics);
        
        // Get transient count
        $transient_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_coreboost_%'"
        );
        
        // Estimate total size
        $total_size = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE 'coreboost_%' OR option_name LIKE '_transient_coreboost_%'"
        );
        $size_formatted = $total_size ? size_format($total_size) : '0 B';
        
        // Check for leftover variant files
        $upload_dir = wp_upload_dir();
        $variants_path = $upload_dir['basedir'] . '/coreboost-variants/';
        $variant_files_exist = is_dir($variants_path);
        $variant_size = 0;
        if ($variant_files_exist) {
            $variant_size = $this->get_directory_size($variants_path);
        }
        
        ?>
        <div class="coreboost-db-stat">
            <div class="coreboost-db-stat-value"><?php echo esc_html($script_entries); ?></div>
            <div class="coreboost-db-stat-label"><?php _e('Script Metrics', 'coreboost'); ?></div>
        </div>
        
        <div class="coreboost-db-stat">
            <div class="coreboost-db-stat-value"><?php echo esc_html($transient_count); ?></div>
            <div class="coreboost-db-stat-label"><?php _e('Transients', 'coreboost'); ?></div>
        </div>
        
        <div class="coreboost-db-stat">
            <div class="coreboost-db-stat-value"><?php echo esc_html($size_formatted); ?></div>
            <div class="coreboost-db-stat-label"><?php _e('Estimated DB Usage', 'coreboost'); ?></div>
        </div>
        
        <?php if ($variant_files_exist && $variant_size > 0): ?>
        <div class="coreboost-db-stat" style="background: #fff3cd; border-left: 4px solid #ffc107;">
            <div class="coreboost-db-stat-value"><?php echo size_format($variant_size); ?></div>
            <div class="coreboost-db-stat-label"><?php _e('Legacy Variant Files', 'coreboost'); ?></div>
        </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Get directory size recursively
     *
     * @param string $path Directory path
     * @return int Size in bytes
     */
    private function get_directory_size($path) {
        $size = 0;
        if (!is_dir($path)) {
            return $size;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
        
        return $size;
    }
    
    /**
     * Recursively delete a directory and its contents
     *
     * @param string $path Directory path
     * @return bool Success
     */
    private function recursive_delete($path) {
        if (!is_dir($path)) {
            return false;
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }
        
        return @rmdir($path);
    }
    

    
    /**
     * Render cleanup actions
     */
    private function render_cleanup_actions() {
        // Check for legacy variant files
        $upload_dir = wp_upload_dir();
        $variants_path = $upload_dir['basedir'] . '/coreboost-variants/';
        $has_legacy_variants = is_dir($variants_path);
        
        $actions = array(
            'clear_transients' => array(
                'label' => __('Clear All Transients', 'coreboost'),
                'description' => __('Delete all CoreBoost transient data', 'coreboost'),
                'nonce' => 'coreboost_clear_transients',
            ),
            'clear_script_metrics' => array(
                'label' => __('Clear Script Metrics', 'coreboost'),
                'description' => __('Reset script performance data', 'coreboost'),
                'nonce' => 'coreboost_clear_script_metrics',
            ),
            'clear_backups' => array(
                'label' => __('Clear Backup Options', 'coreboost'),
                'description' => __('Remove old migration backup options', 'coreboost'),
                'nonce' => 'coreboost_clear_backups',
            ),
        );
        
        // Add legacy variant cleanup if files exist
        if ($has_legacy_variants) {
            $actions['clear_legacy_variants'] = array(
                'label' => __('Clear Legacy Variant Files', 'coreboost'),
                'description' => __('Delete the old coreboost-variants folder and related database entries', 'coreboost'),
                'nonce' => 'coreboost_clear_legacy_variants',
            );
        }
        
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
        $retention_days = isset($this->options['analytics_retention_days']) 
            ? intval($this->options['analytics_retention_days']) 
            : 30;
        ?>
        <table class="coreboost-db-settings-table">
            <tr>
                <th><label for="analytics_retention_days"><?php _e('Data Retention', 'coreboost'); ?></label></th>
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
                        <?php _e('How long to keep metrics and performance data before automatic cleanup.', 'coreboost'); ?>
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
     * Handle cleanup actions (legacy - now handled in early_handle_cleanup_actions)
     * 
     * @deprecated 3.1.0 Actions are now handled via admin_init hook
     */
    private function handle_cleanup_actions() {
        // Actions are now handled early via admin_init hook
        // This method is kept for backwards compatibility
    }
    
    /**
     * Render notices
     */
    private function render_notices() {
        $notices = array(
            'transients_cleared' => __('All transients cleared successfully!', 'coreboost'),
            'metrics_cleared' => __('Script metrics cleared successfully!', 'coreboost'),
            'backups_cleared' => __('Backup options cleared successfully!', 'coreboost'),
            'full_reset' => __('Full database reset completed!', 'coreboost'),
            'legacy_variants_cleared' => __('Legacy variant files and data cleared successfully!', 'coreboost'),
        );
        
        foreach ($notices as $key => $message) {
            if (filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS) === '1') {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }
}
