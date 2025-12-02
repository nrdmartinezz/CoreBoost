<?php
/**
 * Image Responsive Resizer
 *
 * Generates appropriately-sized image variants for responsive delivery:
 * - Detects oversized images (actual > rendered dimensions)
 * - Calculates optimal breakpoints (1x, 1.5x, 2x for pixel density)
 * - Generates resized variants via WP_Image_Editor
 * - Pipes resized images to format optimizer for AVIF/WebP conversion
 * - Provides variant data for srcset/sizes attribute generation
 * - Processes in background via Action Scheduler (non-blocking)
 *
 * @package CoreBoost
 * @since 2.8.0
 */

namespace CoreBoost\PublicCore;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Image_Responsive_Resizer
 *
 * Handles responsive image variant generation for PSI compliance
 */
class Image_Responsive_Resizer {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Format optimizer instance
     *
     * @var Image_Format_Optimizer
     */
    private $format_optimizer;
    
    /**
     * Minimum file size savings to trigger resize (bytes)
     * PSI threshold: 4KB = 4096 bytes
     *
     * @var int
     */
    private $size_threshold = 4096;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param Image_Format_Optimizer $format_optimizer Format optimizer instance
     */
    public function __construct($options = array(), $format_optimizer = null) {
        $this->options = $options;
        $this->format_optimizer = $format_optimizer;
        
        // Register Action Scheduler hook for background processing
        if (function_exists('add_action')) {
            add_action('coreboost_resize_responsive_image', array($this, 'handle_background_resize'), 10, 3);
        }
    }
    
    /**
     * Detect and queue oversized images for responsive resizing
     *
     * Scans HTML for img tags, compares actual vs rendered dimensions,
     * and queues images that exceed the size threshold for background processing.
     * Works with both regular img tags and picture tags with AVIF/WebP variants.
     *
     * @param string $html HTML content to scan
     * @return int Number of images queued
     */
    public function detect_and_queue_oversized_images($html) {
        // Pattern to match img tags with width/height attributes
        $pattern = '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i';
        
        if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            return 0;
        }
        
        $queued_count = 0;
        
        foreach ($matches as $match) {
            $img_tag = $match[0];
            $image_url = $match[1];
            
            // Skip if not a local image
            if (!$this->is_local_image($image_url)) {
                continue;
            }
            
            // Extract width and height attributes (rendered dimensions)
            $rendered_width = $this->extract_attribute($img_tag, 'width');
            $rendered_height = $this->extract_attribute($img_tag, 'height');
            
            if (!$rendered_width || !$rendered_height) {
                continue;
            }
            
            // Get actual image dimensions from original file
            $file_path = $this->url_to_path($image_url);
            if (!file_exists($file_path)) {
                continue;
            }
            
            $actual_dims = @getimagesize($file_path);
            if (!$actual_dims) {
                continue;
            }
            
            $actual_width = $actual_dims[0];
            $actual_height = $actual_dims[1];
            
            // Check if image is oversized (needs responsive variants)
            if ($actual_width <= $rendered_width && $actual_height <= $rendered_height) {
                continue;
            }
            
            // Get file size of the VARIANT being served (AVIF/WebP), not original
            $variant_path = $this->get_served_variant_path($file_path);
            $served_size = file_exists($variant_path) ? @filesize($variant_path) : @filesize($file_path);
            
            if (!$served_size) {
                continue;
            }
            
            // Calculate area ratio to estimate size savings
            $rendered_area = $rendered_width * $rendered_height;
            $actual_area = $actual_width * $actual_height;
            $area_ratio = $rendered_area / $actual_area;
            
            // Estimate resized file size (compression isn't perfectly linear, but close enough)
            $estimated_size = $served_size * $area_ratio;
            $estimated_savings = $served_size - $estimated_size;
            
            // Queue if savings exceed threshold (4KB)
            if ($estimated_savings >= $this->size_threshold) {
                $this->queue_responsive_resize($image_url, $rendered_width, $rendered_height);
                $queued_count++;
                
                error_log(sprintf(
                    "CoreBoost: Queued oversized image - Actual: %dx%d (%s), Rendered: %dx%d, Estimated savings: %s",
                    $actual_width,
                    $actual_height,
                    $this->format_bytes($served_size),
                    $rendered_width,
                    $rendered_height,
                    $this->format_bytes($estimated_savings)
                ));
            }
        }
        
        if ($queued_count > 0) {
            error_log("CoreBoost: Queued {$queued_count} oversized images for responsive resizing");
        }
        
        return $queued_count;
    }
    
    /**
     * Queue responsive resize for background processing
     *
     * Uses Action Scheduler to process image resizing without blocking page load.
     *
     * @param string $image_url Image URL to resize
     * @param int $rendered_width Rendered width in pixels
     * @param int $rendered_height Rendered height in pixels
     * @return bool True if queued successfully
     */
    public function queue_responsive_resize($image_url, $rendered_width, $rendered_height) {
        // Check if already queued (avoid duplicates)
        $transient_key = 'coreboost_resize_queued_' . md5($image_url);
        if (get_transient($transient_key)) {
            return false;
        }
        
        // Mark as queued (24 hour expiration)
        set_transient($transient_key, true, DAY_IN_SECONDS);
        
        // Use Action Scheduler if available
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                time(),
                'coreboost_resize_responsive_image',
                array(
                    'image_url' => $image_url,
                    'rendered_width' => (int)$rendered_width,
                    'rendered_height' => (int)$rendered_height,
                ),
                'coreboost'
            );
            return true;
        }
        
        // Fallback to WP-Cron
        wp_schedule_single_event(
            time(),
            'coreboost_resize_responsive_image',
            array(
                'image_url' => $image_url,
                'rendered_width' => (int)$rendered_width,
                'rendered_height' => (int)$rendered_height,
            )
        );
        
        return true;
    }
    
    /**
     * Background resize handler
     *
     * Called by Action Scheduler to process queued resize tasks.
     * Generates multiple size variants (1x, 1.5x, 2x) and converts to AVIF/WebP.
     *
     * @param string $image_url Image URL to resize
     * @param int $rendered_width Rendered width in pixels
     * @param int $rendered_height Rendered height in pixels
     * @return bool True if at least one variant generated
     */
    public function handle_background_resize($image_url, $rendered_width, $rendered_height) {
        $file_path = $this->url_to_path($image_url);
        
        if (!file_exists($file_path)) {
            error_log("CoreBoost: Resize failed - file not found: {$file_path}");
            return false;
        }
        
        // Calculate optimal sizes (1x, 1.5x, 2x)
        $sizes = $this->calculate_optimal_sizes($rendered_width, $rendered_height, $file_path);
        
        if (empty($sizes)) {
            return false;
        }
        
        $generated_count = 0;
        
        // Generate each size variant
        foreach ($sizes as $size_data) {
            $width = $size_data['width'];
            $height = $size_data['height'];
            $label = $size_data['label'];
            
            // Generate resized variant
            if ($this->generate_responsive_variant($file_path, $width, $height)) {
                $generated_count++;
            }
        }
        
        if ($generated_count > 0) {
            error_log("CoreBoost: Generated {$generated_count} responsive variants for: {$image_url}");
        }
        
        return $generated_count > 0;
    }
    
    /**
     * Calculate optimal responsive sizes
     *
     * Generates 1x (exact rendered), 1.5x (high-DPI), and 2x (retina) variants.
     * Caps all sizes at original image dimensions.
     *
     * @param int $rendered_width Rendered width in pixels
     * @param int $rendered_height Rendered height in pixels
     * @param string $file_path Path to original image
     * @return array Array of size data: ['width' => int, 'height' => int, 'label' => string]
     */
    private function calculate_optimal_sizes($rendered_width, $rendered_height, $file_path) {
        // Get original dimensions
        $original_dims = @getimagesize($file_path);
        if (!$original_dims) {
            return array();
        }
        
        $original_width = $original_dims[0];
        $original_height = $original_dims[1];
        
        $sizes = array();
        
        // 1x - Exact rendered size
        $sizes[] = array(
            'width' => min((int)$rendered_width, $original_width),
            'height' => min((int)$rendered_height, $original_height),
            'label' => '1x',
        );
        
        // 1.5x - High-DPI displays
        $width_1_5x = min((int)($rendered_width * 1.5), $original_width);
        $height_1_5x = min((int)($rendered_height * 1.5), $original_height);
        
        // Only add if different from 1x
        if ($width_1_5x > $rendered_width) {
            $sizes[] = array(
                'width' => $width_1_5x,
                'height' => $height_1_5x,
                'label' => '1.5x',
            );
        }
        
        // 2x - Retina displays
        $width_2x = min((int)($rendered_width * 2), $original_width);
        $height_2x = min((int)($rendered_height * 2), $original_height);
        
        // Only add if different from 1.5x
        if ($width_2x > $width_1_5x) {
            $sizes[] = array(
                'width' => $width_2x,
                'height' => $height_2x,
                'label' => '2x',
            );
        }
        
        return $sizes;
    }
    
    /**
     * Generate responsive variant at specific dimensions
     *
     * Uses WP_Image_Editor to resize image, then pipes through
     * format optimizer to generate AVIF and WebP versions.
     *
     * @param string $file_path Original image path
     * @param int $width Target width
     * @param int $height Target height
     * @return bool True if variant generated successfully
     */
    private function generate_responsive_variant($file_path, $width, $height) {
        // Load image editor
        $editor = wp_get_image_editor($file_path);
        
        if (is_wp_error($editor)) {
            error_log('CoreBoost: Image editor error: ' . $editor->get_error_message());
            return false;
        }
        
        // Resize image
        $resize_result = $editor->resize($width, $height, false);
        
        if (is_wp_error($resize_result)) {
            error_log('CoreBoost: Resize error: ' . $resize_result->get_error_message());
            return false;
        }
        
        // Generate output path with width descriptor
        $resized_path = $this->get_responsive_variant_path($file_path, $width);
        
        // Create directory if needed
        $output_dir = dirname($resized_path);
        if (!wp_mkdir_p($output_dir)) {
            return false;
        }
        
        // Save resized image
        $save_result = $editor->save($resized_path);
        
        if (is_wp_error($save_result)) {
            error_log('CoreBoost: Save error: ' . $save_result->get_error_message());
            return false;
        }
        
        // Generate AVIF and WebP variants of the resized image
        if ($this->format_optimizer) {
            $this->format_optimizer->generate_avif_variant($resized_path);
            $this->format_optimizer->generate_webp_variant($resized_path);
        }
        
        return true;
    }
    
    /**
     * Get available responsive variants for image
     *
     * Returns array of available AVIF/WebP variants with width descriptors
     * for building srcset attributes.
     *
     * @param string $image_url Original image URL
     * @return array Array of variants: ['width' => int, 'avif' => url, 'webp' => url]
     */
    public function get_available_responsive_variants($image_url) {
        $file_path = $this->url_to_path($image_url);
        $upload_dir = wp_upload_dir();
        $variants_base = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants' . DIRECTORY_SEPARATOR;
        
        // Get relative path
        $relative_path = str_replace($upload_dir['basedir'] . DIRECTORY_SEPARATOR, '', $file_path);
        $path_info = pathinfo($relative_path);
        
        $variant_dir = $variants_base . $path_info['dirname'];
        
        if (!is_dir($variant_dir)) {
            return array();
        }
        
        // Scan for variants matching pattern: {filename}-{width}w.{ext}
        $pattern = $path_info['filename'] . '-*w.*';
        $files = glob($variant_dir . DIRECTORY_SEPARATOR . $pattern);
        
        if (empty($files)) {
            return array();
        }
        
        $variants = array();
        
        foreach ($files as $file) {
            $basename = basename($file);
            
            // Extract width from filename: image-400w.avif -> 400
            if (preg_match('/-(\d+)w\.(avif|webp)$/i', $basename, $matches)) {
                $width = (int)$matches[1];
                $format = strtolower($matches[2]);
                
                if (!isset($variants[$width])) {
                    $variants[$width] = array('width' => $width);
                }
                
                // Convert path to URL
                $variants[$width][$format] = $this->path_to_url($file);
            }
        }
        
        // Sort by width
        ksort($variants);
        
        return array_values($variants);
    }
    
    /**
     * Get responsive variant path with width descriptor
     *
     * @param string $file_path Original image path
     * @param int $width Width of variant
     * @return string Path to variant file
     */
    private function get_responsive_variant_path($file_path, $width) {
        $upload_dir = wp_upload_dir();
        $variants_base = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants' . DIRECTORY_SEPARATOR;
        
        // Get relative path from uploads
        $relative_path = str_replace($upload_dir['basedir'] . DIRECTORY_SEPARATOR, '', $file_path);
        $path_info = pathinfo($relative_path);
        
        // Build path: /coreboost-variants/{year}/{month}/{filename}-{width}w.{ext}
        $variant_path = $variants_base . $path_info['dirname'] . DIRECTORY_SEPARATOR . 
                       $path_info['filename'] . '-' . $width . 'w.' . $path_info['extension'];
        
        return $variant_path;
    }
    
    /**
     * Check if image is local (not external CDN)
     *
     * @param string $image_url Image URL
     * @return bool True if local image
     */
    private function is_local_image($image_url) {
        $site_url = home_url();
        return strpos($image_url, $site_url) === 0;
    }
    
    /**
     * Extract attribute value from HTML tag
     *
     * @param string $tag HTML tag
     * @param string $attribute Attribute name
     * @return string|null Attribute value or null if not found
     */
    private function extract_attribute($tag, $attribute) {
        $pattern = '/' . $attribute . '=["\']?([0-9]+)["\']?/i';
        
        if (preg_match($pattern, $tag, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Convert image URL to local file path
     *
     * @param string $url Image URL
     * @return string Local file path
     */
    private function url_to_path($url) {
        $site_url = home_url();
        
        if (strpos($url, $site_url) === 0) {
            $path = str_replace($site_url, '', $url);
            $result = ABSPATH . ltrim($path, '/');
            return str_replace('/', DIRECTORY_SEPARATOR, $result);
        }
        
        // Handle relative paths
        if (strpos($url, '/') === 0) {
            $result = ABSPATH . ltrim($url, '/');
            return str_replace('/', DIRECTORY_SEPARATOR, $result);
        }
        
        return str_replace('/', DIRECTORY_SEPARATOR, $url);
    }
    
    /**
     * Convert local file path to URL
     *
     * @param string $path Local file path
     * @return string Image URL
     */
    private function path_to_url($path) {
        $site_url = home_url();
        $abspath = str_replace('\\', '/', ABSPATH);
        $path = str_replace('\\', '/', $path);
        
        $relative = str_replace($abspath, '', $path);
        $relative = ltrim($relative, '/');
        
        return $site_url . '/' . $relative;
    }
    
    /**
     * Get path to the variant file that's actually being served
     *
     * Checks for AVIF variant first (best compression), then WebP,
     * then falls back to original. This ensures we calculate savings
     * based on the actual file being delivered to browsers.
     *
     * @param string $original_path Path to original image
     * @return string Path to variant being served
     */
    private function get_served_variant_path($original_path) {
        $upload_dir = wp_upload_dir();
        $variants_base = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants' . DIRECTORY_SEPARATOR;
        
        // Get relative path from uploads
        $relative_path = str_replace($upload_dir['basedir'] . DIRECTORY_SEPARATOR, '', $original_path);
        $path_info = pathinfo($relative_path);
        
        // Check for AVIF variant (primary format)
        $avif_path = $variants_base . $path_info['dirname'] . DIRECTORY_SEPARATOR . 
                     $path_info['filename'] . '.avif';
        
        if (file_exists($avif_path)) {
            return $avif_path;
        }
        
        // Check for WebP variant (fallback)
        $webp_path = $variants_base . $path_info['dirname'] . DIRECTORY_SEPARATOR . 
                     $path_info['filename'] . '.webp';
        
        if (file_exists($webp_path)) {
            return $webp_path;
        }
        
        // No variants found, return original
        return $original_path;
    }
    
    /**
     * Format bytes into human-readable string
     *
     * @param int $bytes Number of bytes
     * @return string Formatted string (e.g., "62.3 KiB")
     */
    private function format_bytes($bytes) {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MiB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KiB';
        }
        return $bytes . ' B';
    }
}
