<?php
/**
 * Cache Warmer
 *
 * Predictively generates and caches image variants based on:
 * - Common responsive breakpoints
 * - Page template analysis
 * - Recent image usage patterns
 * - Configurable warming strategies
 *
 * @package CoreBoost
 * @since 3.1.0
 */

namespace CoreBoost\Core;

use CoreBoost\PublicCore\Image_Responsive_Resizer;
use CoreBoost\PublicCore\Image_Format_Optimizer;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Cache_Warmer
 *
 * Intelligently pre-generates image variants to optimize first-view performance
 */
class Cache_Warmer {
    
    /**
     * Common responsive breakpoints (mobile-first)
     *
     * @var array
     */
    private static $common_breakpoints = array(
        400,   // Small mobile
        600,   // Large mobile / small tablet
        800,   // Tablet
        1024,  // Small desktop
        1200,  // Medium desktop
        1600,  // Large desktop / 2x mobile
    );
    
    /**
     * Maximum images to warm per batch
     *
     * @var int
     */
    private static $batch_size = 10;
    
    /**
     * Initialize cache warming hooks
     */
    public static function init() {
        // Schedule daily cache warming
        if (!wp_next_scheduled('coreboost_warm_cache')) {
            wp_schedule_event(time(), 'daily', 'coreboost_warm_cache');
        }
        
        add_action('coreboost_warm_cache', array(__CLASS__, 'warm_recent_images'));
        
        // Warm cache for newly uploaded images immediately
        add_action('add_attachment', array(__CLASS__, 'warm_new_image'), 10, 1);
    }
    
    /**
     * Warm cache for newly uploaded image
     *
     * Generates variants at common breakpoints immediately after upload.
     *
     * @param int $attachment_id Attachment ID
     */
    public static function warm_new_image($attachment_id) {
        $image_url = wp_get_attachment_url($attachment_id);
        
        if (!$image_url) {
            return;
        }
        
        // Check if image format conversion is enabled
        $options = get_option('coreboost_options', array());
        if (!isset($options['enable_image_format_conversion']) || !$options['enable_image_format_conversion']) {
            return;
        }
        
        error_log("CoreBoost: Warming cache for new image: {$image_url}");
        
        // Get image metadata
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata || !isset($metadata['width'], $metadata['height'])) {
            return;
        }
        
        $original_width = $metadata['width'];
        $original_height = $metadata['height'];
        
        // Generate variants at common breakpoints
        self::warm_image_at_breakpoints($image_url, $original_width, $original_height);
    }
    
    /**
     * Warm cache for recent images
     *
     * Daily task that pre-generates variants for recently accessed images.
     */
    public static function warm_recent_images() {
        global $wpdb;
        
        error_log("CoreBoost: Starting cache warming for recent images");
        
        // Get options
        $options = get_option('coreboost_options', array());
        if (!isset($options['enable_image_format_conversion']) || !$options['enable_image_format_conversion']) {
            error_log("CoreBoost: Cache warming skipped - format conversion disabled");
            return;
        }
        
        // Get recently uploaded images (last 30 days)
        $recent_images = $wpdb->get_results(
            "SELECT ID 
             FROM {$wpdb->posts} 
             WHERE post_type = 'attachment' 
             AND post_mime_type LIKE 'image/%'
             AND post_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY post_date DESC 
             LIMIT " . self::$batch_size
        );
        
        if (empty($recent_images)) {
            error_log("CoreBoost: No recent images found for warming");
            return;
        }
        
        $warmed_count = 0;
        
        foreach ($recent_images as $image) {
            $image_url = wp_get_attachment_url($image->ID);
            if (!$image_url) {
                continue;
            }
            
            $metadata = wp_get_attachment_metadata($image->ID);
            if (!$metadata || !isset($metadata['width'], $metadata['height'])) {
                continue;
            }
            
            $original_width = $metadata['width'];
            $original_height = $metadata['height'];
            
            // Warm cache for this image
            if (self::warm_image_at_breakpoints($image_url, $original_width, $original_height)) {
                $warmed_count++;
            }
            
            // Avoid overwhelming the server
            usleep(100000); // 100ms delay between images
        }
        
        error_log("CoreBoost: Cache warming completed - {$warmed_count} images warmed");
    }
    
    /**
     * Warm cache for specific image at common breakpoints
     *
     * Generates responsive variants at predefined breakpoints.
     *
     * @param string $image_url Image URL
     * @param int $original_width Original image width
     * @param int $original_height Original image height
     * @return bool True if variants were generated
     */
    private static function warm_image_at_breakpoints($image_url, $original_width, $original_height) {
        // Get format optimizer and resizer instances
        $options = get_option('coreboost_options', array());
        $format_optimizer = new Image_Format_Optimizer($options);
        $resizer = new Image_Responsive_Resizer($options, $format_optimizer);
        
        $generated_count = 0;
        
        // Generate variants at each breakpoint that's smaller than original
        foreach (self::$common_breakpoints as $breakpoint_width) {
            if ($breakpoint_width >= $original_width) {
                continue; // Skip breakpoints larger than original
            }
            
            // Calculate proportional height
            $aspect_ratio = $original_height / $original_width;
            $breakpoint_height = round($breakpoint_width * $aspect_ratio);
            
            // Check if variant already exists in cache
            $avif_cached = Variant_Cache::get_variant($image_url, 'avif', $breakpoint_width);
            $webp_cached = Variant_Cache::get_variant($image_url, 'webp', $breakpoint_width);
            
            if ($avif_cached && $webp_cached) {
                continue; // Already cached
            }
            
            // Generate the responsive variant
            $file_path = Path_Helper::url_to_path($image_url);
            if (file_exists($file_path)) {
                // Use reflection to call private method (temporary solution)
                $reflection = new \ReflectionClass($resizer);
                $method = $reflection->getMethod('generate_responsive_variant');
                $method->setAccessible(true);
                
                if ($method->invoke($resizer, $file_path, $breakpoint_width, $breakpoint_height)) {
                    $generated_count++;
                }
            }
        }
        
        if ($generated_count > 0) {
            error_log("CoreBoost: Generated {$generated_count} variants for {$image_url}");
        }
        
        return $generated_count > 0;
    }
    
    /**
     * Warm specific images
     *
     * Public method to warm cache for specific image IDs.
     * Useful for manual warming or batch operations.
     *
     * @param array $attachment_ids Array of attachment IDs
     * @return array Statistics: ['total' => int, 'warmed' => int, 'skipped' => int]
     */
    public static function warm_images($attachment_ids) {
        $stats = array(
            'total' => count($attachment_ids),
            'warmed' => 0,
            'skipped' => 0,
        );
        
        foreach ($attachment_ids as $attachment_id) {
            $image_url = wp_get_attachment_url($attachment_id);
            if (!$image_url) {
                $stats['skipped']++;
                continue;
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (!$metadata || !isset($metadata['width'], $metadata['height'])) {
                $stats['skipped']++;
                continue;
            }
            
            $original_width = $metadata['width'];
            $original_height = $metadata['height'];
            
            if (self::warm_image_at_breakpoints($image_url, $original_width, $original_height)) {
                $stats['warmed']++;
            } else {
                $stats['skipped']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get common breakpoints
     *
     * @return array Common responsive breakpoints
     */
    public static function get_breakpoints() {
        return self::$common_breakpoints;
    }
    
    /**
     * Set custom breakpoints
     *
     * Allows customization of warming breakpoints.
     *
     * @param array $breakpoints Array of width values in pixels
     */
    public static function set_breakpoints($breakpoints) {
        if (is_array($breakpoints) && !empty($breakpoints)) {
            self::$common_breakpoints = $breakpoints;
            sort(self::$common_breakpoints);
        }
    }
}
