<?php
/**
 * Image Variant Lifecycle Manager
 *
 * Manages the complete lifecycle of image variants:
 * - Auto-generation on image upload
 * - Auto-cleanup on image deletion
 * - Orphaned variant detection and cleanup
 * - Storage efficiency monitoring
 * - Audit logging for all operations
 *
 * @package CoreBoost
 * @since 2.7.0
 */

namespace CoreBoost\PublicCore;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Image_Variant_Lifecycle_Manager
 *
 * Automates image variant management and cleanup
 */
class Image_Variant_Lifecycle_Manager {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Image format optimizer instance
     *
     * @var Image_Format_Optimizer
     */
    private $format_optimizer;
    
    /**
     * Variants storage directory
     *
     * @var string
     */
    private $variants_dir;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param Image_Format_Optimizer $format_optimizer Format optimizer instance
     */
    public function __construct($options = array(), $format_optimizer = null) {
        $this->options = $options;
        $this->format_optimizer = $format_optimizer ?: new Image_Format_Optimizer($options);
        
        // Defer upload directory initialization (may not exist in CLI context)
        $this->variants_dir = null;
    }
    
    /**
     * Get variants directory path (lazy initialization)
     *
     * Initializes upload directory on first access to support CLI contexts
     * where WordPress may not be fully loaded during instantiation.
     *
     * @return string|null Path to variants directory or null if not available
     */
    private function get_variants_dir() {
        // Initialize on first access
        if ($this->variants_dir === null && function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            if ($upload_dir && isset($upload_dir['basedir'])) {
                $this->variants_dir = $upload_dir['basedir'] . '/coreboost-variants';
            } else {
                return null;
            }
        }
        return $this->variants_dir;
    }
    
    /**
     * Register hooks for lifecycle management
     *
     * Connects to WordPress media library events:
     * - add_attachment: Trigger variant generation on upload
     * - delete_attachment: Cleanup variants on deletion
     * - wp_generate_attachment_metadata: Detect edits
     * - WP-Cron: Weekly orphan cleanup
     *
     * @param \CoreBoost\Loader $loader Loader instance for registering hooks
     * @return void
     */
    public function register_hooks($loader) {
        // Image upload hook
        $loader->add_action('add_attachment', $this, 'on_image_upload');
        
        // Image deletion hook
        $loader->add_action('delete_attachment', $this, 'on_image_delete');
        
        // Image edit hook
        $loader->add_filter('wp_generate_attachment_metadata', $this, 'on_image_edit', 10, 2);
        
        // WP-Cron cleanup event
        $loader->add_action('coreboost_cleanup_orphaned_variants', $this, 'cleanup_orphaned_variants');
    }
    
    /**
     * Handle image upload - trigger variant generation
     *
     * Called when new image is added to media library.
     * Queues variant generation in background.
     *
     * @param int $attachment_id Attachment post ID
     * @return void
     */
    public function on_image_upload($attachment_id) {
        // Get attachment post and file path
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return;
        }
        
        // Get file path
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return;
        }
        
        // Check if should optimize (JPEG, etc)
        if (!$this->format_optimizer->should_optimize_image($file)) {
            return;
        }
        
        // Queue generation in background
        $this->format_optimizer->queue_background_generation($file, array('avif', 'webp'));
        
        // Log operation
        $this->audit_log('upload_queued', $attachment_id, array(
            'file' => basename($file),
            'file_size' => filesize($file),
        ));
    }
    
    /**
     * Handle image deletion - cleanup variants
     *
     * Called when image is deleted from media library.
     * Removes all associated variants to prevent orphaned files.
     *
     * Hook runs BEFORE actual deletion, so we can still get file info.
     *
     * @param int $attachment_id Attachment post ID
     * @return void
     */
    public function on_image_delete($attachment_id) {
        // Get file path before deletion
        $file = get_attached_file($attachment_id);
        if (!$file) {
            return;
        }
        
        // Delete variants for this image
        $deleted_count = $this->delete_variants_for_image($file);
        
        if ($deleted_count > 0) {
            $this->audit_log('delete_variants', $attachment_id, array(
                'deleted_variants' => $deleted_count,
                'file' => basename($file),
            ));
        }
    }
    
    /**
     * Handle image edit - regenerate variants
     *
     * Called when image metadata is regenerated (e.g., image is re-uploaded).
     * Flags existing variants for regeneration.
     *
     * @param array $metadata Image metadata
     * @param int $attachment_id Attachment post ID
     * @return array Unchanged metadata (pass-through filter)
     */
    public function on_image_edit($metadata, $attachment_id) {
        // Get file path
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return $metadata;
        }
        
        // Check if should optimize
        if (!$this->format_optimizer->should_optimize_image($file)) {
            return $metadata;
        }
        
        // Delete old variants (will be regenerated)
        $deleted_count = $this->delete_variants_for_image($file);
        
        // Queue regeneration
        $this->format_optimizer->queue_background_generation($file, array('avif', 'webp'));
        
        $this->audit_log('regenerate_queued', $attachment_id, array(
            'deleted_variants' => $deleted_count,
            'file' => basename($file),
        ));
        
        return $metadata;
    }
    
    /**
     * Cleanup orphaned variants
     *
     * Scheduled via WP-Cron (weekly). Finds variant directories
     * for images that no longer exist in media library or aren't used.
     *
     * @return array Cleanup report with stats
     */
    public function cleanup_orphaned_variants() {
        $report = array(
            'start_time' => time(),
            'orphaned_found' => 0,
            'orphaned_deleted' => 0,
            'bytes_freed' => 0,
            'errors' => array(),
        );
        
        // Check if variants directory exists
        $variants_dir = $this->get_variants_dir();
        if (!$variants_dir || !is_dir($variants_dir)) {
            return $report;
        }
        
        // Scan variant directories - CRITICAL FIX: Handle scandir() false returns
        $scan_result = scandir($variants_dir);
        if ($scan_result === false) {
            error_log('[CoreBoost] Failed to scan variants directory: ' . $variants_dir);
            return $report;
        }
        $variant_dirs = array_diff($scan_result, array('.', '..')); 
        
        foreach ($variant_dirs as $image_hash) {
            $image_hash_path = $variants_dir . '/' . $image_hash;
            
            if (!is_dir($image_hash_path)) {
                continue;
            }
            
            // Try to find corresponding source image
            $source_found = $this->find_source_image($image_hash);
            
            if (!$source_found) {
                // Orphaned image - delete all variants
                $bytes = $this->delete_variant_directory($image_hash_path);
                $report['orphaned_found']++;
                $report['orphaned_deleted']++;
                $report['bytes_freed'] += $bytes;
            }
        }
        
        $report['end_time'] = time();
        $report['duration_seconds'] = $report['end_time'] - $report['start_time'];
        
        // Log cleanup operation
        $this->audit_log('cleanup_orphaned', 0, $report);
        
        return $report;
    }
    
    /**
     * Get orphaned images
     *
     * Finds all variant directories for images that don't exist
     * or aren't referenced in posts.
     *
     * @return array Array of orphaned image hashes
     */
    public function get_orphaned_images() {
        $orphaned = array();
        
        // Check if variants directory exists
        if (!is_dir($this->variants_dir)) {
            return $orphaned;
        }
        
        // Scan variant directories - CRITICAL FIX: Handle scandir() false returns
        $scan_result = scandir($this->variants_dir);
        if ($scan_result === false) {
            error_log('[CoreBoost] Failed to scan variants directory: ' . $this->variants_dir);
            return $orphaned;
        }
        $variant_dirs = array_diff($scan_result, array('.', '..'));
        
        foreach ($variant_dirs as $image_hash) {
            $image_hash_path = $this->variants_dir . '/' . $image_hash;
            
            if (!is_dir($image_hash_path)) {
                continue;
            }
            
            // Check if source image exists
            $source_found = $this->find_source_image($image_hash);
            
            if (!$source_found) {
                $orphaned[] = $image_hash;
            }
        }
        
        return $orphaned;
    }
    
    /**
     * Estimate storage usage
     *
     * Calculates total variant storage, breakdown by format,
     * and used vs orphaned breakdown.
     *
     * @return array Storage statistics
     */
    public function estimate_storage_usage() {
        $stats = array(
            'total_size' => 0,
            'total_files' => 0,
            'by_format' => array(
                'avif' => array('size' => 0, 'files' => 0),
                'webp' => array('size' => 0, 'files' => 0),
            ),
            'by_status' => array(
                'used' => array('size' => 0, 'files' => 0),
                'orphaned' => array('size' => 0, 'files' => 0),
            ),
            'count_images' => 0,
        );
        
        // Check if variants directory exists
        if (!is_dir($this->variants_dir)) {
            return $stats;
        }
        
        // Get orphaned images for quick lookup
        $orphaned_hashes = array_flip($this->get_orphaned_images());
        
        // Scan variant directories - CRITICAL FIX: Handle scandir() false returns
        $scan_result = scandir($this->variants_dir);
        if ($scan_result === false) {
            error_log('[CoreBoost] Failed to scan variants directory: ' . $this->variants_dir);
            return $stats;
        }
        $variant_dirs = array_diff($scan_result, array('.', '..'));
        
        foreach ($variant_dirs as $image_hash) {
            $image_hash_path = $this->variants_dir . '/' . $image_hash;
            
            if (!is_dir($image_hash_path)) {
                continue;
            }
            
            $stats['count_images']++;
            $is_orphaned = isset($orphaned_hashes[$image_hash]);
            $status_key = $is_orphaned ? 'orphaned' : 'used';
            
            // Scan format directories - CRITICAL FIX: Handle scandir() false returns
            $format_scan = scandir($image_hash_path);
            if ($format_scan === false) {
                error_log('[CoreBoost] Failed to scan image hash directory: ' . $image_hash_path);
                continue;
            }
            $formats = array_diff($format_scan, array('.', '..'));
            
            foreach ($formats as $format) {
                $format_path = $image_hash_path . '/' . $format;
                
                if (!is_dir($format_path)) {
                    continue;
                }
                
                // Get files in format directory - CRITICAL FIX: Handle scandir() false returns
                $file_scan = scandir($format_path);
                if ($file_scan === false) {
                    error_log('[CoreBoost] Failed to scan format directory: ' . $format_path);
                    continue;
                }
                $files = array_diff($file_scan, array('.', '..'));
                
                foreach ($files as $file) {
                    $file_path = $format_path . '/' . $file;
                    
                    if (is_file($file_path)) {
                        $size = filesize($file_path);
                        if ($size !== false) {
                            $stats['total_size'] += $size;
                            $stats['total_files']++;
                            
                            // Track by format
                            if (isset($stats['by_format'][$format])) {
                                $stats['by_format'][$format]['size'] += $size;
                                $stats['by_format'][$format]['files']++;
                            }
                            
                            // Track by status
                            $stats['by_status'][$status_key]['size'] += $size;
                            $stats['by_status'][$status_key]['files']++;
                        }
                    }
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Audit log entry
     *
     * Logs variant operations to wp_options for tracking and debugging.
     * Keeps last 1000 entries (rotates older).
     *
     * @param string $action Action type (e.g., 'upload_queued', 'delete_variants')
     * @param int $image_id Attachment ID (0 for system operations)
     * @param array $details Operation details
     * @return void
     */
    public function audit_log($action, $image_id, $details = array()) {
        // Get existing log
        $log = get_option('coreboost_image_variant_audit_log', array());
        
        // Create log entry
        $entry = array(
            'timestamp' => time(),
            'date' => current_time('mysql'),
            'action' => $action,
            'image_id' => (int)$image_id,
            'details' => $details,
        );
        
        // Add to log
        $log[] = $entry;
        
        // Keep only last 1000 entries
        if (count($log) > 1000) {
            $log = array_slice($log, -1000);
        }
        
        // Save updated log
        update_option('coreboost_image_variant_audit_log', $log);
    }
    
    /**
     * Get audit log entries
     *
     * Retrieves recent audit log entries for display in admin dashboard.
     *
     * @param int $limit Maximum entries to return
     * @param int $offset Offset for pagination
     * @return array Array of log entries
     */
    public function get_audit_log($limit = 100, $offset = 0) {
        $log = get_option('coreboost_image_variant_audit_log', array());
        
        // Return most recent entries first
        $log = array_reverse($log);
        
        // Apply pagination
        return array_slice($log, $offset, $limit);
    }
    
    /**
     * Regenerate variants for specific image
     *
     * Force regenerate all variants for given image ID.
     * Deletes existing variants first, then queues generation.
     *
     * @param int $attachment_id Attachment post ID
     * @return array Generated variant paths
     */
    public function regenerate_variants_for_image($attachment_id) {
        // Get file path
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return array();
        }
        
        // Delete existing variants
        $this->delete_variants_for_image($file);
        
        // Generate new variants based on generation mode
        $generation_mode = isset($this->options['image_generation_mode']) 
            ? $this->options['image_generation_mode'] 
            : 'on-demand';
        
        if ($generation_mode === 'eager') {
            // Generate immediately
            $this->format_optimizer->generate_variants($file, array('avif', 'webp'));
        } else {
            // Queue for background processing
            $this->format_optimizer->queue_background_generation($file, array('avif', 'webp'));
        }
        
        $this->audit_log('regenerate_manual', $attachment_id, array(
            'file' => basename($file),
            'generation_mode' => $generation_mode,
        ));
        
        return array(
            'avif' => $this->format_optimizer->get_variant_from_cache($file, 'avif'),
            'webp' => $this->format_optimizer->get_variant_from_cache($file, 'webp'),
        );
    }
    
    /**
     * Delete all variants
     *
     * Admin function to bulk delete all variant directories.
     * Used for cleanup or strategy changes.
     * REQUIRES CONFIRMATION to prevent accidental deletion.
     *
     * @param bool $confirmed Safety confirmation (must be true)
     * @return array Deletion report with stats
     */
    public function delete_all_variants($confirmed = false) {
        $report = array(
            'confirmed' => $confirmed,
            'deleted_count' => 0,
            'bytes_freed' => 0,
            'errors' => array(),
        );
        
        if (!$confirmed) {
            $report['errors'][] = 'Deletion not confirmed - operation cancelled for safety';
            return $report;
        }
        
        // Check if variants directory exists
        if (!is_dir($this->variants_dir)) {
            return $report;
        }
        
        // Scan and delete all variant directories - CRITICAL FIX: Handle scandir() false returns
        $scan_result = scandir($this->variants_dir);
        if ($scan_result === false) {
            error_log('[CoreBoost] Failed to scan variants directory: ' . $this->variants_dir);
            $report['errors'][] = 'Failed to read variants directory';
            return $report;
        }
        $variant_dirs = array_diff($scan_result, array('.', '..'));
        
        foreach ($variant_dirs as $image_hash) {
            $image_hash_path = $this->variants_dir . '/' . $image_hash;
            
            if (is_dir($image_hash_path)) {
                $bytes = $this->delete_variant_directory($image_hash_path);
                $report['deleted_count']++;
                $report['bytes_freed'] += $bytes;
            }
        }
        
        $this->audit_log('delete_all_variants', 0, $report);
        
        return $report;
    }
    
    /**
     * Delete variants for specific image
     *
     * Removes all variant files (AVIF, WebP, etc) for given image.
     * Does not delete source image.
     *
     * @param string $image_path Image file path
     * @return int Number of variants deleted
     */
    private function delete_variants_for_image($image_path) {
        // Create hash from image path
        $image_hash = md5($image_path);
        $image_hash_path = $this->variants_dir . '/' . $image_hash;
        
        if (!is_dir($image_hash_path)) {
            return 0;
        }
        
        $deleted_count = 0;
        $this->delete_variant_directory($image_hash_path, true);
        
        // Count deleted files - CRITICAL FIX: Handle scandir() false returns
        $formats = array('avif', 'webp');
        foreach ($formats as $format) {
            $format_path = $image_hash_path . '/' . $format;
            if (is_dir($format_path)) {
                $format_scan = scandir($format_path);
                if ($format_scan !== false) {
                    $deleted_count += count(array_diff($format_scan, array('.', '..')));
                }
            }
        }
        
        return $deleted_count;
    }
    
    /**
     * Delete variant directory recursively
     *
     * Removes variant directory and all contents.
     * Returns total bytes freed.
     *
     * @param string $dir_path Directory path to delete
     * @param bool $count_only Only count bytes, don't delete
     * @return int Total bytes freed
     */
    private function delete_variant_directory($dir_path, $count_only = false) {
        $bytes_freed = 0;
        
        if (!is_dir($dir_path)) {
            return 0;
        }
        
        // CRITICAL FIX: Handle scandir() false return (errors return false, not array)
        $scan_result = scandir($dir_path);
        if ($scan_result === false) {
            error_log('[CoreBoost] Failed to scan directory: ' . $dir_path);
            return 0;
        }
        
        $files = array_diff($scan_result, array('.', '..'));
        
        foreach ($files as $file) {
            $file_path = $dir_path . '/' . $file;
            
            if (is_dir($file_path)) {
                // Recursively delete subdirectory
                $bytes_freed += $this->delete_variant_directory($file_path, $count_only);
            } else if (is_file($file_path)) {
                $file_size = filesize($file_path);
                if ($file_size !== false) {
                    $bytes_freed += $file_size;
                }
                if (!$count_only) {
                    // CRITICAL FIX: Check unlink return value for error handling
                    if (!@unlink($file_path)) {
                        error_log('[CoreBoost] Failed to delete file: ' . $file_path);
                    }
                }
            }
        }
        
        // Delete empty directory
        if (!$count_only && is_dir($dir_path)) {
            if (!@rmdir($dir_path)) {
                error_log('[CoreBoost] Failed to remove directory: ' . $dir_path);
            }
        }
        
        return $bytes_freed;
    }
    
    /**
     * Find source image for variant hash
     *
     * Attempts to locate source JPEG for a variant directory.
     * Checks media library and actual file system.
     *
     * @param string $image_hash MD5 hash of image path
     * @return bool|string Source image path if found, false otherwise
     */
    private function find_source_image($image_hash) {
        global $wpdb;
        
        // Search WordPress media library for matching files
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'numberposts' => -1,
        ));
        
        foreach ($attachments as $attachment) {
            $file = get_attached_file($attachment->ID);
            if ($file && md5($file) === $image_hash) {
                return $file;
            }
        }
        
        return false;
    }
}
