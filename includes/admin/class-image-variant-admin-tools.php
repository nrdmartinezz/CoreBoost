<?php
/**
 * Admin Tools for Image Variant Management
 *
 * Provides admin dashboard and tools for managing image variants:
 * - Variant storage statistics and breakdown
 * - Bulk action buttons (regenerate, delete, view log)
 * - Audit log display with filtering
 * - Manual variant regeneration
 * - Storage usage monitoring
 *
 * @package CoreBoost
 * @since 2.7.0
 */

namespace CoreBoost\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Image_Variant_Admin_Tools
 *
 * Manages admin interface for image variant operations
 */
class Image_Variant_Admin_Tools {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Lifecycle manager instance
     *
     * @var \CoreBoost\PublicCore\Image_Variant_Lifecycle_Manager
     */
    private $lifecycle_manager;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\PublicCore\Image_Variant_Lifecycle_Manager $lifecycle_manager Lifecycle manager
     */
    public function __construct($options = array(), $lifecycle_manager = null) {
        $this->options = $options;
        $this->lifecycle_manager = $lifecycle_manager;
    }
    
    /**
     * Register admin hooks and menu
     *
     * @param \CoreBoost\Loader $loader Loader instance
     * @return void
     */
    public function register_hooks($loader) {
        // Add admin menu for image variants
        $loader->add_action('admin_menu', $this, 'add_admin_menu');
        
        // Handle admin actions
        $loader->add_action('admin_init', $this, 'handle_admin_actions');
        
        // Add AJAX handlers for async operations
        $loader->add_action('wp_ajax_coreboost_regenerate_variants', $this, 'ajax_regenerate_variants');
        $loader->add_action('wp_ajax_coreboost_delete_orphaned_variants', $this, 'ajax_delete_orphaned_variants');
        $loader->add_action('wp_ajax_coreboost_cleanup_all_variants', $this, 'ajax_cleanup_all_variants');
    }
    
    /**
     * Add admin menu for image variants
     *
     * Adds submenu under CoreBoost settings for variant management.
     *
     * @return void
     */
    public function add_admin_menu() {
        // Only add if format conversion enabled
        if (empty($this->options['enable_image_format_conversion'])) {
            return;
        }
        
        add_submenu_page(
            'coreboost-dashboard',
            __('Image Variants', 'coreboost'),
            __('Image Variants', 'coreboost'),
            'manage_options',
            'coreboost-image-variants',
            array($this, 'render_dashboard')
        );
    }
    
    /**
     * Render admin dashboard
     *
     * Displays variant management dashboard with statistics and controls.
     *
     * @return void
     */
    public function render_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'coreboost'));
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__('Image Variant Management', 'coreboost')); ?></h1>
            
            <?php $this->render_storage_stats(); ?>
            <?php $this->render_action_buttons(); ?>
            <?php $this->render_audit_log(); ?>
        </div>
        <?php
    }
    
    /**
     * Render storage statistics section
     *
     * @return void
     */
    private function render_storage_stats() {
        if (!$this->lifecycle_manager) {
            return;
        }
        
        $stats = $this->lifecycle_manager->estimate_storage_usage();
        
        $total_mb = round($stats['total_size'] / (1024 * 1024), 2);
        $avif_mb = round($stats['by_format']['avif']['size'] / (1024 * 1024), 2);
        $webp_mb = round($stats['by_format']['webp']['size'] / (1024 * 1024), 2);
        $used_mb = round($stats['by_status']['used']['size'] / (1024 * 1024), 2);
        $orphaned_mb = round($stats['by_status']['orphaned']['size'] / (1024 * 1024), 2);
        
        ?>
        <div class="coreboost-admin-card">
            <h2><?php echo esc_html(__('Storage Statistics', 'coreboost')); ?></h2>
            
            <table class="coreboost-stats-table">
                <tr>
                    <td><strong><?php echo esc_html(__('Total Variants', 'coreboost')); ?></strong></td>
                    <td><?php echo (int)$stats['total_files']; ?> files</td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html(__('Total Storage', 'coreboost')); ?></strong></td>
                    <td><?php echo number_format($total_mb, 2); ?> MB</td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html(__('Number of Images', 'coreboost')); ?></strong></td>
                    <td><?php echo (int)$stats['count_images']; ?></td>
                </tr>
            </table>
            
            <h3><?php echo esc_html(__('Breakdown by Format', 'coreboost')); ?></h3>
            
            <table class="coreboost-stats-table">
                <tr>
                    <td><strong><?php echo esc_html(__('AVIF', 'coreboost')); ?></strong></td>
                    <td>
                        <?php echo (int)$stats['by_format']['avif']['files']; ?> files / 
                        <?php echo number_format($avif_mb, 2); ?> MB
                    </td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html(__('WebP', 'coreboost')); ?></strong></td>
                    <td>
                        <?php echo (int)$stats['by_format']['webp']['files']; ?> files / 
                        <?php echo number_format($webp_mb, 2); ?> MB
                    </td>
                </tr>
            </table>
            
            <h3><?php echo esc_html(__('Breakdown by Status', 'coreboost')); ?></h3>
            
            <table class="coreboost-stats-table">
                <tr>
                    <td><strong><?php echo esc_html(__('In Use', 'coreboost')); ?></strong></td>
                    <td>
                        <?php echo (int)$stats['by_status']['used']['files']; ?> files / 
                        <?php echo number_format($used_mb, 2); ?> MB
                    </td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html(__('Orphaned', 'coreboost')); ?></strong></td>
                    <td>
                        <?php echo (int)$stats['by_status']['orphaned']['files']; ?> files / 
                        <?php echo number_format($orphaned_mb, 2); ?> MB
                        <em>(<?php echo esc_html(__('can be deleted', 'coreboost')); ?>)</em>
                    </td>
                </tr>
            </table>
        </div>
        
        <style>
            .coreboost-admin-card {
                background: #fff;
                border: 1px solid #ccc;
                border-radius: 4px;
                padding: 20px;
                margin-top: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            
            .coreboost-stats-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            
            .coreboost-stats-table tr {
                border-bottom: 1px solid #f0f0f0;
            }
            
            .coreboost-stats-table td {
                padding: 10px;
            }
            
            .coreboost-stats-table tr:hover {
                background-color: #f5f5f5;
            }
        </style>
        <?php
    }
    
    /**
     * Render action buttons section
     *
     * @return void
     */
    private function render_action_buttons() {
        ?>
        <div class="coreboost-admin-card">
            <h2><?php echo esc_html(__('Variant Management', 'coreboost')); ?></h2>
            
            <p><?php echo esc_html(__('Use these tools to manage image variants:', 'coreboost')); ?></p>
            
            <form method="post">
                <?php wp_nonce_field('coreboost_image_variants', 'coreboost_nonce'); ?>
                
                <button type="button" class="button button-primary" onclick="coreboostRegenerateVariants()">
                    <?php echo esc_html(__('Regenerate All Variants', 'coreboost')); ?>
                </button>
                
                <button type="button" class="button" onclick="coreboostDeleteOrphaned()">
                    <?php echo esc_html(__('Delete Orphaned Variants', 'coreboost')); ?>
                </button>
                
                <button type="button" class="button button-link-delete" onclick="coreboostCleanupAll()">
                    <?php echo esc_html(__('Delete All Variants', 'coreboost')); ?>
                </button>
                
                <p class="description">
                    <?php echo esc_html(__('Warning: Deletion actions will free storage but will require regeneration on next page view (on-demand mode).', 'coreboost')); ?>
                </p>
            </form>
        </div>
        
        <script>
            function coreboostRegenerateVariants() {
                if (!confirm('<?php echo esc_js(__('This will regenerate all image variants. Continue?', 'coreboost')); ?>')) {
                    return;
                }
                
                var data = {
                    action: 'coreboost_regenerate_variants',
                    nonce: '<?php echo esc_js(wp_create_nonce('coreboost_image_variants')); ?>'
                };
                
                jQuery.post(ajaxurl, data, function(response) {
                    alert('<?php echo esc_js(__('Variant regeneration queued in background.', 'coreboost')); ?>');
                    location.reload();
                });
            }
            
            function coreboostDeleteOrphaned() {
                if (!confirm('<?php echo esc_js(__('This will delete all orphaned (unused) image variants. Continue?', 'coreboost')); ?>')) {
                    return;
                }
                
                var data = {
                    action: 'coreboost_delete_orphaned_variants',
                    nonce: '<?php echo esc_js(wp_create_nonce('coreboost_image_variants')); ?>'
                };
                
                jQuery.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('Orphaned variants deleted. Storage freed.', 'coreboost')); ?>');
                        location.reload();
                    }
                });
            }
            
            function coreboostCleanupAll() {
                if (!confirm('<?php echo esc_js(__('WARNING: This will delete ALL image variants! Images will need to be regenerated. Are you absolutely sure?', 'coreboost')); ?>')) {
                    return;
                }
                
                if (!confirm('<?php echo esc_js(__('This action cannot be undone. Delete all variants?', 'coreboost')); ?>')) {
                    return;
                }
                
                var data = {
                    action: 'coreboost_cleanup_all_variants',
                    nonce: '<?php echo esc_js(wp_create_nonce('coreboost_image_variants')); ?>'
                };
                
                jQuery.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('All variants deleted. Storage freed.', 'coreboost')); ?>');
                        location.reload();
                    }
                });
            }
        </script>
        <?php
    }
    
    /**
     * Render audit log section
     *
     * @return void
     */
    private function render_audit_log() {
        if (!$this->lifecycle_manager) {
            return;
        }
        
        $log = $this->lifecycle_manager->get_audit_log(50, 0);
        
        ?>
        <div class="coreboost-admin-card">
            <h2><?php echo esc_html(__('Recent Operations', 'coreboost')); ?></h2>
            
            <?php if (empty($log)): ?>
                <p><?php echo esc_html(__('No operations logged yet.', 'coreboost')); ?></p>
            <?php else: ?>
                <table class="wp-list-table fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html(__('Date/Time', 'coreboost')); ?></th>
                            <th><?php echo esc_html(__('Action', 'coreboost')); ?></th>
                            <th><?php echo esc_html(__('Image ID', 'coreboost')); ?></th>
                            <th><?php echo esc_html(__('Details', 'coreboost')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($log as $entry): ?>
                            <tr>
                                <td><?php echo esc_html($entry['date']); ?></td>
                                <td><strong><?php echo esc_html($entry['action']); ?></strong></td>
                                <td><?php echo (int)$entry['image_id']; ?></td>
                                <td>
                                    <small>
                                        <?php
                                        if (is_array($entry['details'])) {
                                            echo esc_html(json_encode($entry['details']));
                                        } else {
                                            echo esc_html($entry['details']);
                                        }
                                        ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle admin actions (forms, nonces, etc)
     *
     * @return void
     */
    public function handle_admin_actions() {
        // Handled via AJAX
    }
    
    /**
     * AJAX: Regenerate all variants
     *
     * @return void
     */
    public function ajax_regenerate_variants() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'coreboost_image_variants')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permission');
        }
        
        // Queue regeneration for all media
        global $wpdb;
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'numberposts' => -1,
        ));
        
        foreach ($attachments as $attachment) {
            if ($this->lifecycle_manager) {
                $this->lifecycle_manager->regenerate_variants_for_image($attachment->ID);
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Queued regeneration for %d images', 'coreboost'), count($attachments))
        ));
    }
    
    /**
     * AJAX: Delete orphaned variants
     *
     * @return void
     */
    public function ajax_delete_orphaned_variants() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'coreboost_image_variants')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permission');
        }
        
        if (!$this->lifecycle_manager) {
            wp_send_json_error('Lifecycle manager not available');
        }
        
        // Cleanup orphaned variants
        $report = $this->lifecycle_manager->cleanup_orphaned_variants();
        
        wp_send_json_success(array(
            'deleted' => $report['orphaned_deleted'],
            'freed_bytes' => $report['bytes_freed'],
            'freed_mb' => round($report['bytes_freed'] / (1024 * 1024), 2)
        ));
    }
    
    /**
     * AJAX: Delete all variants
     *
     * @return void
     */
    public function ajax_cleanup_all_variants() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'coreboost_image_variants')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permission');
        }
        
        if (!$this->lifecycle_manager) {
            wp_send_json_error('Lifecycle manager not available');
        }
        
        // Delete all variants with confirmation
        $report = $this->lifecycle_manager->delete_all_variants(true);
        
        if ($report['confirmed']) {
            wp_send_json_success(array(
                'deleted' => $report['deleted_count'],
                'freed_bytes' => $report['bytes_freed'],
                'freed_mb' => round($report['bytes_freed'] / (1024 * 1024), 2)
            ));
        } else {
            wp_send_json_error('Deletion not confirmed');
        }
    }
}
