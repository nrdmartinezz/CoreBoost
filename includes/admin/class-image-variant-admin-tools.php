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

use CoreBoost\Core\Variant_Cache;

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
        
        // Add AJAX handlers for cache management
        $loader->add_action('wp_ajax_coreboost_clear_variant_cache', $this, 'ajax_clear_variant_cache');
        $loader->add_action('wp_ajax_coreboost_rebuild_variant_cache', $this, 'ajax_rebuild_variant_cache');
        $loader->add_action('wp_ajax_coreboost_get_cache_stats', $this, 'ajax_get_cache_stats');
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
            <?php $this->render_cache_stats(); ?>
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
                
                <hr style="margin: 20px 0;">
                
                <h3><?php echo esc_html(__('Cache Management', 'coreboost')); ?></h3>
                
                <button type="button" class="button" onclick="coreboostClearCache()">
                    <?php echo esc_html(__('Clear Variant Cache', 'coreboost')); ?>
                </button>
                
                <button type="button" class="button button-secondary" onclick="coreboostRebuildCache()">
                    <?php echo esc_html(__('Rebuild Cache from Filesystem', 'coreboost')); ?>
                </button>
                
                <button type="button" class="button button-link-delete" onclick="coreboostCleanupAll()">
                    <?php echo esc_html(__('Delete All Variants', 'coreboost')); ?>
                </button>
                
                <p class="description">
                    <?php echo esc_html(__('Warning: Deletion actions will free storage but will require regeneration on next page view (on-demand mode).', 'coreboost')); ?>
                </p>
                
                <hr style="margin: 20px 0;">
                
                <h3><?php echo esc_html(__('Browser Cache Headers', 'coreboost')); ?></h3>
                
                <?php $this->render_cache_headers_status(); ?>
                
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
            
            function coreboostClearCache() {
                if (!confirm('<?php echo esc_js(__('Clear the variant cache? Cache will be rebuilt automatically on next page load.', 'coreboost')); ?>')) {
                    return;
                }
                
                var data = {
                    action: 'coreboost_clear_variant_cache',
                    nonce: '<?php echo esc_js(wp_create_nonce('coreboost_image_variants')); ?>'
                };
                
                jQuery.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('Variant cache cleared successfully.', 'coreboost')); ?>');
                        location.reload();
                    } else {
                        alert('<?php echo esc_js(__('Error clearing cache: ', 'coreboost')); ?>' + response.data);
                    }
                });
            }
            
            function coreboostRebuildCache() {
                if (!confirm('<?php echo esc_js(__('Rebuild cache from filesystem? This will scan all variants and may take a few minutes.', 'coreboost')); ?>')) {
                    return;
                }
                
                var data = {
                    action: 'coreboost_rebuild_variant_cache',
                    nonce: '<?php echo esc_js(wp_create_nonce('coreboost_image_variants')); ?>'
                };
                
                var button = event.target;
                button.disabled = true;
                button.textContent = '<?php echo esc_js(__('Rebuilding...', 'coreboost')); ?>';
                
                jQuery.post(ajaxurl, data, function(response) {
                    button.disabled = false;
                    button.textContent = '<?php echo esc_js(__('Rebuild Cache from Filesystem', 'coreboost')); ?>';
                    
                    if (response.success) {
                        alert('<?php echo esc_js(__('Cache rebuilt: ', 'coreboost')); ?>' + 
                              response.data.cached + '<?php echo esc_js(__(' variants cached, ', 'coreboost')); ?>' +
                              response.data.errors + '<?php echo esc_js(__(' errors', 'coreboost')); ?>');
                        location.reload();
                    } else {
                        alert('<?php echo esc_js(__('Error rebuilding cache: ', 'coreboost')); ?>' + response.data);
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
    
    /**
     * Render cache statistics section
     *
     * @return void
     */
    private function render_cache_stats() {
        $cache_entries = Variant_Cache::get_total_entries();
        $cache_stats = Variant_Cache::get_stats();
        
        ?>
        <div class="coreboost-admin-card">
            <h2><?php echo esc_html(__('Variant Cache Statistics', 'coreboost')); ?></h2>
            
            <table class="coreboost-stats-table">
                <tr>
                    <td><strong><?php echo esc_html(__('Cached Images', 'coreboost')); ?></strong></td>
                    <td><?php echo (int)$cache_entries; ?> images</td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html(__('Runtime Cache Size', 'coreboost')); ?></strong></td>
                    <td><?php echo (int)$cache_stats['cache_size']; ?> entries (current request)</td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html(__('Cache Hit Rate', 'coreboost')); ?></strong></td>
                    <td>
                        <?php if ($cache_stats['total_requests'] > 0): ?>
                            <?php echo number_format($cache_stats['hit_rate'], 1); ?>% 
                            (<?php echo (int)($cache_stats['runtime_hits'] + $cache_stats['persistent_hits']); ?> hits / 
                            <?php echo (int)$cache_stats['total_requests']; ?> requests)
                        <?php else: ?>
                            No requests yet
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html(__('Runtime Hits', 'coreboost')); ?></strong></td>
                    <td><?php echo (int)$cache_stats['runtime_hits']; ?> (instant lookups)</td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html(__('Persistent Hits', 'coreboost')); ?></strong></td>
                    <td><?php echo (int)$cache_stats['persistent_hits']; ?> (database lookups)</td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html(__('Filesystem Hits', 'coreboost')); ?></strong></td>
                    <td><?php echo (int)$cache_stats['filesystem_hits']; ?> (cache warmed)</td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html(__('Cache Misses', 'coreboost')); ?></strong></td>
                    <td><?php echo (int)$cache_stats['misses']; ?> (variants not found)</td>
                </tr>
            </table>
            
            <p style="margin-top: 15px; color: #666;">
                <?php echo esc_html(__('The variant cache eliminates filesystem lookups by storing URL-to-variant mappings. Higher hit rates indicate better performance.', 'coreboost')); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * AJAX: Clear variant cache
     *
     * @return void
     */
    public function ajax_clear_variant_cache() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'coreboost_image_variants')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permission');
        }
        
        // Clear cache
        $deleted_chunks = Variant_Cache::clear_all();
        
        wp_send_json_success(array(
            'message' => 'Cache cleared successfully',
            'deleted_chunks' => $deleted_chunks,
        ));
    }
    
    /**
     * AJAX: Rebuild variant cache from filesystem
     *
     * @return void
     */
    public function ajax_rebuild_variant_cache() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'coreboost_image_variants')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permission');
        }
        
        // Rebuild cache
        $result = Variant_Cache::rebuild_from_filesystem();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Cache rebuilt successfully',
                'scanned' => $result['stats']['scanned'],
                'cached' => $result['stats']['cached'],
                'errors' => $result['stats']['errors'],
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Get current cache statistics
     *
     * @return void
     */
    public function ajax_get_cache_stats() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'coreboost_image_variants')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permission');
        }
        
        $stats = Variant_Cache::get_stats();
        $total_entries = Variant_Cache::get_total_entries();
        
        wp_send_json_success(array(
            'total_entries' => $total_entries,
            'stats' => $stats,
        ));
    }
    
    /**
     * Render cache headers status section
     *
     * Displays .htaccess status and regenerate button for browser caching.
     *
     * @return void
     */
    private function render_cache_headers_status() {
        // Check if Variant_Cache_Headers class exists
        if (!class_exists('CoreBoost\\Core\\Variant_Cache_Headers')) {
            echo '<p class="description">' . esc_html__('Cache headers management not available.', 'coreboost') . '</p>';
            return;
        }
        
        $status = \CoreBoost\Core\Variant_Cache_Headers::verify_htaccess();
        
        // Detect server type
        $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
        $is_litespeed = stripos($server_software, 'litespeed') !== false;
        $is_apache = stripos($server_software, 'apache') !== false;
        $is_nginx = stripos($server_software, 'nginx') !== false;
        
        $server_type = 'Unknown';
        if ($is_litespeed) {
            $server_type = 'LiteSpeed (Apache-compatible ✓)';
        } elseif ($is_apache) {
            $server_type = 'Apache';
        } elseif ($is_nginx) {
            $server_type = 'Nginx (requires manual config)';
        }
        
        ?>
        <table class="coreboost-stats-table" style="margin-bottom: 15px;">
            <tr>
                <td><strong><?php echo esc_html__('Server Type', 'coreboost'); ?></strong></td>
                <td><?php echo esc_html($server_type); ?></td>
            </tr>
            <tr>
                <td><strong><?php echo esc_html__('.htaccess Status', 'coreboost'); ?></strong></td>
                <td>
                    <?php if ($status['exists'] && $status['current']) : ?>
                        <span style="color: #46b450;">✓ <?php echo esc_html__('Configured and current', 'coreboost'); ?></span>
                    <?php elseif ($status['exists']) : ?>
                        <span style="color: #ffb900;">⚠ <?php echo esc_html__('Exists but outdated', 'coreboost'); ?></span>
                    <?php else : ?>
                        <span style="color: #dc3232;">✗ <?php echo esc_html__('Not configured', 'coreboost'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($status['exists']) : ?>
            <tr>
                <td><strong><?php echo esc_html__('Location', 'coreboost'); ?></strong></td>
                <td><code style="font-size: 11px;"><?php echo esc_html($status['path']); ?></code></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <button type="button" class="button button-secondary" onclick="coreboostRegenerateCacheHeaders()" id="coreboost-regen-headers-btn">
            <?php echo esc_html__('Regenerate Cache Headers', 'coreboost'); ?>
        </button>
        
        <span id="coreboost-headers-status" style="margin-left: 10px;"></span>
        
        <p class="description" style="margin-top: 10px;">
            <?php echo esc_html__('This creates/updates the .htaccess file with proper browser cache headers for converted images (1 year cache).', 'coreboost'); ?>
            <?php if ($is_litespeed) : ?>
                <br><strong><?php echo esc_html__('LiteSpeed detected:', 'coreboost'); ?></strong> 
                <?php echo esc_html__('Cache will be automatically purged after regeneration.', 'coreboost'); ?>
            <?php endif; ?>
        </p>
        
        <?php if ($is_nginx) : ?>
        <div class="notice notice-warning inline" style="margin-top: 15px;">
            <p><strong><?php echo esc_html__('Nginx Server Detected', 'coreboost'); ?></strong></p>
            <p><?php echo esc_html__('Nginx does not support .htaccess files. You need to add cache headers manually to your nginx configuration:', 'coreboost'); ?></p>
            <pre style="background: #f6f7f7; padding: 10px; overflow-x: auto; font-size: 11px;"><?php echo esc_html(\CoreBoost\Core\Variant_Cache_Headers::get_nginx_config()); ?></pre>
        </div>
        <?php endif; ?>
        
        <script>
        function coreboostRegenerateCacheHeaders() {
            var btn = document.getElementById('coreboost-regen-headers-btn');
            var status = document.getElementById('coreboost-headers-status');
            
            btn.disabled = true;
            btn.textContent = '<?php echo esc_js(__('Regenerating...', 'coreboost')); ?>';
            status.textContent = '';
            
            var data = {
                action: 'coreboost_regenerate_cache_headers',
                nonce: '<?php echo esc_js(wp_create_nonce('coreboost_cache_headers')); ?>'
            };
            
            jQuery.post(ajaxurl, data, function(response) {
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js(__('Regenerate Cache Headers', 'coreboost')); ?>';
                
                if (response.success) {
                    status.innerHTML = '<span style="color: #46b450;">✓ ' + response.data.message + '</span>';
                    // Reload after short delay to show updated status
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    status.innerHTML = '<span style="color: #dc3232;">✗ ' + response.data.message + '</span>';
                }
            }).fail(function() {
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js(__('Regenerate Cache Headers', 'coreboost')); ?>';
                status.innerHTML = '<span style="color: #dc3232;">✗ <?php echo esc_js(__('Request failed', 'coreboost')); ?></span>';
            });
        }
        </script>
        <?php
    }
}
