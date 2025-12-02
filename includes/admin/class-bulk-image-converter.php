<?php
/**
 * Bulk Image Conversion Handler
 *
 * Manages bulk conversion of WordPress uploads to AVIF/WebP:
 * - Scans uploads folder for images
 * - Calculates optimal batch sizes
 * - Processes batches via AJAX
 * - Tracks progress and provides estimates
 * - Auto-generates variants on new uploads
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
 * Class Bulk_Image_Converter
 *
 * Handles bulk image variant generation and progress tracking
 */
class Bulk_Image_Converter {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Image format optimizer
     *
     * @var \CoreBoost\PublicCore\Image_Format_Optimizer
     */
    private $format_optimizer;
    
    /**
     * Loader instance
     *
     * @var \CoreBoost\Loader
     */
    private $loader;
    
    /**
     * Transient key for progress tracking
     *
     * @var string
     */
    private $progress_key = 'coreboost_bulk_conversion_progress';
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\PublicCore\Image_Format_Optimizer $format_optimizer Format optimizer
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options = array(), $format_optimizer = null, $loader = null) {
        $this->options = $options;
        $this->format_optimizer = $format_optimizer;
        $this->loader = $loader;
        
        if ($this->loader) {
            $this->define_hooks();
        }
        
        error_log('CoreBoost: Bulk_Image_Converter constructed');
        error_log('CoreBoost: Loader exists: ' . ($loader ? 'yes' : 'no'));
        error_log('CoreBoost: Format optimizer passed: ' . ($format_optimizer ? 'yes' : 'no'));
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        // AJAX endpoint for batch processing
        $this->loader->add_action('wp_ajax_coreboost_bulk_convert_batch', $this, 'ajax_process_batch');
        
        // AJAX endpoint for scanning
        $this->loader->add_action('wp_ajax_coreboost_scan_uploads', $this, 'ajax_scan_uploads');
        
        // AJAX endpoint for stopping conversion
        $this->loader->add_action('wp_ajax_coreboost_bulk_convert_stop', $this, 'ajax_stop_conversion');
        
        // Hook into media upload for auto-generation
        $this->loader->add_action('wp_handle_upload', $this, 'auto_generate_on_upload', 10, 1);

        error_log('CoreBoost: Registering bulk converter AJAX hooks');
    }
    


    /**
     * AJAX: Scan uploads folder and count images
     */
    public function ajax_scan_uploads() {
        // Verify nonce and permissions
        check_ajax_referer('coreboost_bulk_converter');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Check if format optimizer is initialized
        if (!$this->format_optimizer) {
            wp_send_json_error(array(
                'message' => 'Image format optimizer not initialized. Please enable "Generate AVIF/WebP Variants" in CoreBoost Settings → Images tab, save settings, then try again.'
            ));
        }
        
        error_log('CoreBoost: ajax_scan_uploads called');
        error_log('CoreBoost: format_optimizer: ' . ($this->format_optimizer ? 'initialized' : 'NULL'));
        error_log('CoreBoost: options: ' . print_r($this->options, true));
        
        $images = $this->scan_uploads_folder();
        $count = count($images);
        
        if ($count === 0) {
            wp_send_json_success(array(
                'count' => 0,
                'message' => 'No images found in uploads folder',
            ));
        }
        
        // Calculate batch size and estimate
        $batch_info = $this->calculate_batch_strategy($count);
        
        // Check if this is a start_conversion request (delete variants) or just a stats check (keep variants)
        $start_conversion = isset($_POST['start_conversion']) && $_POST['start_conversion'] === 'true';
        
        // Only delete existing variants when starting a new conversion
        if ($start_conversion) {
            $this->delete_existing_variants();
        }
        
        // Store image list in transient for batch processing
        set_transient(
            $this->progress_key . '_images',
            $images,
            HOUR_IN_SECONDS * 6
        );
        
        // Initialize progress
        $progress = array(
            'total_images' => $count,
            'processed_images' => 0,
            'current_batch' => 1,
            'total_batches' => $batch_info['total_batches'],
            'batch_size' => $batch_info['batch_size'],
            'estimated_time_minutes' => $batch_info['estimated_time_minutes'],
            'start_time' => current_time('timestamp'),
        );
        
        set_transient(
            $this->progress_key,
            $progress,
            HOUR_IN_SECONDS * 6
        );
        
        // Count how many images already have variants
        $converted_count = $this->count_converted_images($images);
        
        wp_send_json_success(array(
            'count' => $count,
            'converted' => $converted_count,
            'batch_size' => $batch_info['batch_size'],
            'total_batches' => $batch_info['total_batches'],
            'estimated_time_minutes' => $batch_info['estimated_time_minutes'],
            'recommendation' => $batch_info['recommendation'],
        ));
    }
    
    /**
     * AJAX: Process one batch of images
     */
    public function ajax_process_batch() {
        // Increase memory limit for image processing
        @ini_set('memory_limit', '512M');
        
        // Set max execution time
        @set_time_limit(300); // 5 minutes per batch
        
        // Verify nonce and permissions
        check_ajax_referer('coreboost_bulk_converter');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Check if conversion was stopped
        if (get_transient('coreboost_bulk_conversion_stop')) {
            delete_transient('coreboost_bulk_conversion_stop');
            wp_send_json_error('Conversion stopped by user');
        }
        
        // Check if format optimizer is initialized
        if (!$this->format_optimizer) {
            wp_send_json_error('Image format optimizer not initialized.');
        }
        
        $progress = get_transient($this->progress_key);
        if (!$progress) {
            wp_send_json_error('Progress not found');
        }
        
        $images = get_transient($this->progress_key . '_images');
        if (!$images) {
            wp_send_json_error('Images not found');
        }
        
        try {
            // Calculate batch range
            $start_index = $progress['processed_images'];
            $end_index = min($start_index + $progress['batch_size'], count($images));
            
            // Process batch
            $batch_images = array_slice($images, $start_index, $progress['batch_size']);
            $batch_results = $this->process_image_batch($batch_images);
            
            // Update progress
            $progress['processed_images'] = $end_index;
            $progress['current_batch'] = floor($end_index / $progress['batch_size']) + 1;
            
            $is_complete = $end_index >= count($images);
            
            set_transient(
                $this->progress_key,
                $progress,
                HOUR_IN_SECONDS * 6
            );
            
            // Calculate time remaining
            $elapsed = current_time('timestamp') - $progress['start_time'];
            $per_image = $elapsed / max(1, $progress['processed_images']);
            $remaining_images = count($images) - $progress['processed_images'];
            $remaining_seconds = ceil($per_image * $remaining_images);
            $remaining_minutes = ceil($remaining_seconds / 60);
            
            wp_send_json_success(array(
                'processed' => $progress['processed_images'],
                'total' => count($images),
                'current_batch' => $progress['current_batch'],
                'total_batches' => $progress['total_batches'],
                'percentage' => round(($progress['processed_images'] / count($images)) * 100, 1),
                'remaining_minutes' => max(0, $remaining_minutes),
                'is_complete' => $is_complete,
                'batch_results' => $batch_results,
            ));
        } catch (\Exception $e) {
            error_log('CoreBoost: Batch processing error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Batch processing failed: ' . $e->getMessage(),
                'batch' => $progress['current_batch'],
            ));
        }
    }
    
    /**
     * AJAX: Stop bulk conversion
     */
    public function ajax_stop_conversion() {
        // Verify nonce and permissions
        check_ajax_referer('coreboost_bulk_converter');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Set stop flag
        set_transient('coreboost_bulk_conversion_stop', true, HOUR_IN_SECONDS);
        
        wp_send_json_success(array(
            'message' => 'Conversion stopped. Progress saved. You can resume conversion later.',
        ));
    }
    
    /**
     * Process a batch of images
     *
     * @param array $image_paths Array of image file paths
     * @return array Results with counts
     */
    private function process_image_batch($image_paths) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        );
        
        // Ensure format optimizer is available
        if (!$this->format_optimizer) {
            error_log('CoreBoost: Format optimizer not initialized in batch processing');
            return $results;
        }
        
        foreach ($image_paths as $image_path) {
            if (!$this->format_optimizer->should_optimize_image($image_path)) {
                $results['skipped']++;
                continue;
            }
            
            try {
                // Generate AVIF variant
                $avif = $this->format_optimizer->generate_avif_variant($image_path);
                
                // Generate WebP variant
                $webp = $this->format_optimizer->generate_webp_variant($image_path);
                
                if ($avif || $webp) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                error_log('CoreBoost bulk conversion error: ' . $e->getMessage());
                $results['failed']++;
            }
            
            // Free memory after each image to prevent memory exhaustion
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        return $results;
    }
    
    /**
     * Auto-generate variants on new image upload
     *
     * @param array $upload Upload data
     * @return array Upload data (unchanged)
     */
    public function auto_generate_on_upload($upload) {
        // Early return if no file or format optimizer not initialized
        if (empty($upload['file'])) {
            return $upload;
        }
        
        // Don't process if format optimizer not available
        if (!$this->format_optimizer) {
            error_log('CoreBoost: Format optimizer not available for auto-generation on upload');
            return $upload;
        }
        
        // Only process in WordPress context
        if (!function_exists('wp_upload_dir')) {
            return $upload;
        }
        
        $file_path = $upload['file'];
        
        // Convert to absolute path if needed
        if (strpos($file_path, ABSPATH) === false) {
            $upload_dir = wp_upload_dir();
            if ($upload_dir && isset($upload_dir['basedir'])) {
                $file_path = $upload_dir['basedir'] . '/' . $file_path;
            } else {
                error_log('CoreBoost: Could not determine upload directory');
                return $upload;
            }
        }
        
        // Check if should optimize
        try {
            if (!$this->format_optimizer->should_optimize_image($file_path)) {
                return $upload;
            }
        } catch (\Exception $e) {
            error_log('CoreBoost: Error checking if image should optimize: ' . $e->getMessage());
            return $upload;
        }
        
        // Generate variants in background
        try {
            $this->format_optimizer->generate_avif_variant($file_path);
            $this->format_optimizer->generate_webp_variant($file_path);
        } catch (\Exception $e) {
            error_log('CoreBoost auto-generate error: ' . $e->getMessage());
        }
        
        return $upload;
    }
    
    /**
     * Scan uploads folder for all JPEG and PNG images
     *
     * @return array Array of image file paths
     */
    private function scan_uploads_folder() {
        // Only call in WordPress context
        if (!function_exists('wp_upload_dir')) {
            error_log('CoreBoost: WordPress not loaded, cannot scan uploads');
            return array();
        }
        
        $upload_dir = wp_upload_dir();
        
        // Validate upload directory exists
        if (!$upload_dir || !isset($upload_dir['basedir']) || !is_dir($upload_dir['basedir'])) {
            error_log('CoreBoost: Invalid uploads directory');
            return array();
        }
        
        $uploads_path = $upload_dir['basedir'];
        $images = array();
        
        try {
            // Recursive directory scan
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($uploads_path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                
                $ext = strtolower($file->getExtension());
                if (in_array($ext, array('jpg', 'jpeg', 'png'))) {
                    $images[] = $file->getRealPath();
                }
            }
        } catch (\Exception $e) {
            error_log('CoreBoost: Error scanning uploads folder: ' . $e->getMessage());
        }
        
        return $images;
    }
    
    /**
     * Delete existing variants folder
     */
    private function delete_existing_variants() {
        // Only call in WordPress context
        if (!function_exists('wp_upload_dir')) {
            error_log('CoreBoost: WordPress not loaded, cannot delete variants');
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $variants_dir = $upload_dir['basedir'] . '/coreboost-variants';
        
        if (is_dir($variants_dir)) {
            // Use WordPress filesystem API
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
            global $wp_filesystem;
            
            if ($wp_filesystem && $wp_filesystem->is_dir($variants_dir)) {
                $wp_filesystem->delete($variants_dir, true);
            }
        }
    }
    
    /**
     * Calculate optimal batch strategy based on image count
     *
     * @param int $image_count Total images to process
     * @return array Batch strategy with size, count, time estimate, and recommendation
     */
    private function calculate_batch_strategy($image_count) {
        // Detect which formats PHP supports
        $avif_supported = function_exists('imageavif');
        $webp_supported = function_exists('imagewebp');
        
        // Average processing times (seconds per image)
        $webp_time = $webp_supported ? 2 : 0; // WebP is fast
        $avif_time = $avif_supported ? 10 : 0; // AVIF is slower
        $total_time_per_image = max(1, $webp_time + $avif_time); // Minimum 1 second
        
        // Use smaller batch sizes to prevent memory exhaustion
        // GD library loads images entirely into memory
        if ($image_count < 100) {
            // Small site: use batches of 5 for visible progress and memory safety
            $batch_size = min($image_count, 5);
            $estimated_minutes = ceil(($image_count * $total_time_per_image) / 60);
            $recommendation = null;
        } else if ($image_count < 500) {
            // Small-medium: batch of 5 (reduced from 15)
            $batch_size = 5;
            $estimated_minutes = ceil(($image_count * $total_time_per_image) / 60);
            $recommendation = null;
        } else if ($image_count < 2000) {
            // Medium: batch of 8 (reduced from 30)
            $batch_size = 8;
            $estimated_minutes = ceil(($image_count * $total_time_per_image) / 60);
            $recommendation = null;
        } else if ($image_count < 5000) {
            // Large: batch of 10 (reduced from 50)
            $batch_size = 10;
            $estimated_minutes = ceil(($image_count * $total_time_per_image) / 60);
            $recommendation = 'Consider using Cloudflare or Amazon S3 for faster processing of large image libraries.';
        } else {
            // Very large: recommend CDN, use very small batches
            $batch_size = 10;
            $estimated_minutes = ceil(($image_count * $total_time_per_image) / 60);
            $recommendation = 'Your site has ' . number_format($image_count) . ' images. We strongly recommend offloading image optimization to Cloudflare or Amazon S3 for significantly faster processing.';
        }
        
        $total_batches = ceil($image_count / $batch_size);
        
        return array(
            'batch_size' => $batch_size,
            'total_batches' => $total_batches,
            'estimated_time_minutes' => $estimated_minutes,
            'recommendation' => $recommendation,
        );
    }
    
    /**
     * Count how many images already have converted variants
     *
     * @param array $images Array of image file paths
     * @return int Number of images with existing variants
     */
    private function count_converted_images($images) {
        if (!$this->format_optimizer) {
            return 0;
        }
        
        $upload_dir = wp_upload_dir();
        $variants_dir = $upload_dir['basedir'] . '/coreboost-variants/';
        
        if (!is_dir($variants_dir)) {
            return 0;
        }
        
        $converted_count = 0;
        
        foreach ($images as $image_path) {
            // Get relative path from uploads folder
            $relative_path = str_replace($upload_dir['basedir'] . '/', '', $image_path);
            $path_info = pathinfo($relative_path);
            
            // Build variant path
            $variant_base = $variants_dir . $path_info['dirname'] . '/' . $path_info['filename'];
            
            // Check if either AVIF or WebP variant exists
            $avif_exists = file_exists($variant_base . '.avif');
            $webp_exists = file_exists($variant_base . '.webp');
            
            if ($avif_exists || $webp_exists) {
                $converted_count++;
            }
        }
        
        return $converted_count;
    }
}
