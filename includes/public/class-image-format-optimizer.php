<?php
/**
 * Image Format Optimizer
 *
 * Handles intelligent image format conversion and delivery:
 * - Detects browser format capabilities (AVIF, WebP, JPEG)
 * - Generates AVIF and WebP variants on-demand
 * - Renders HTML5 <picture> tags with format fallbacks
 * - Manages variant caching and lifecycle
 * - Provides format-specific quality settings
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
 * Class Image_Format_Optimizer
 *
 * Intelligently converts and delivers images in modern formats
 */
class Image_Format_Optimizer {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Variants storage directory
     *
     * @var string
     */
    private $variants_dir;
    
    /**
     * AVIF quality setting (75-95)
     *
     * @var int
     */
    private $avif_quality;
    
    /**
     * WebP quality setting (75-95)
     *
     * @var int
     */
    private $webp_quality;
    
    /**
     * Generation mode (on-demand or eager)
     *
     * @var string
     */
    private $generation_mode;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options = array()) {
        $this->options = $options;
        
        // Set variant storage directory
        $upload_dir = wp_upload_dir();
        $this->variants_dir = $upload_dir['basedir'] . '/coreboost-variants';
        
        // Set quality settings from options
        $this->avif_quality = isset($options['avif_quality']) 
            ? (int)$options['avif_quality'] 
            : 85;
        
        $this->webp_quality = isset($options['webp_quality']) 
            ? (int)$options['webp_quality'] 
            : 85;
        
        $this->generation_mode = isset($options['image_generation_mode']) 
            ? $options['image_generation_mode'] 
            : 'on-demand';
        
        // Register WP-Cron action handler
        // CRITICAL FIX: This hook handler must be registered or background generation never runs
        add_action('coreboost_generate_image_variants', array($this, 'handle_background_generation'), 10, 2);
    }
    
    /**
     * Detect browser format capability from Accept header
     *
     * Parses HTTP Accept header to determine which image formats
     * the browser supports. Returns format in order of preference.
     *
     * @return string Format: 'avif', 'webp', or 'jpeg'
     */
    public function detect_browser_format() {
        $accept = isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field($_SERVER['HTTP_ACCEPT']) : '';
        
        // Check for AVIF support (AV1 codec)
        if (strpos($accept, 'image/avif') !== false) {
            return 'avif';
        }
        
        // Check for WebP support
        if (strpos($accept, 'image/webp') !== false) {
            return 'webp';
        }
        
        // Default to JPEG
        return 'jpeg';
    }
    
    /**
     * Check if image should be optimized
     *
     * Validates that image meets criteria for format conversion:
     * - Is JPEG format
     * - Dimensions larger than minimum threshold
     * - Not a remote image
     *
     * @param string $image_url Image URL or path
     * @return bool True if image should be optimized
     */
    public function should_optimize_image($image_url) {
        // Convert URL to path
        $file_path = $this->url_to_path($image_url);
        
        // Check if file exists locally
        if (!file_exists($file_path)) {
            return false;
        }
        
        // Optimize JPEG and PNG images
        // Use wp_check_filetype for PHP 8.1+ compatibility (mime_content_type deprecated)
        $file_type = wp_check_filetype($file_path);
        $mime_type = isset($file_type['type']) ? $file_type['type'] : '';
        
        $supported_types = array('image/jpeg', 'image/png');
        $is_supported = in_array($mime_type, $supported_types) || 
                       preg_match('/\.(?:jpe?g|png)$/i', $file_path);
        
        if (!$is_supported) {
            return false;
        }
        
        // Get image dimensions
        $dimensions = $this->get_image_dimensions($file_path);
        if (!$dimensions) {
            return false;
        }
        
        // No minimum size threshold - optimize all images
        // Google doesn't discriminate based on pixel dimensions
        // Even small images (thumbnails, icons) benefit from modern format conversion
        
        return true;
    }
    
    /**
     * Generate AVIF variant of image
     *
     * Converts JPEG or PNG image to AVIF format with configured quality.
     * Preserves PNG alpha channel (transparency) when present.
     * Uses GD Library if available.
     *
     * @param string $image_path Path to source image (JPEG or PNG)
     * @return string|null Path to generated AVIF file, or null on error
     */
    public function generate_avif_variant($image_path) {
        // Validate file exists
        if (!file_exists($image_path)) {
            return null;
        }
        
        // Check if GD library available
        if (!extension_loaded('gd')) {
            return null;
        }
        
        try {
            // Generate output path
            $output_path = $this->get_variant_path($image_path, 'avif');
            
            // Create directory if needed
            $output_dir = dirname($output_path);
            if (!wp_mkdir_p($output_dir)) {
                return null;
            }
            
            // Load source image based on file type
            $source = $this->load_image_resource($image_path);
            if (!$source) {
                return null;
            }
            
            // For PHP 8.1+, use imageavif if available
            if (function_exists('imageavif')) {
                // Save as AVIF with quality setting
                if (imageavif($source, $output_path, $this->avif_quality)) {
                    imagedestroy($source);
                    return $output_path;
                }
            }
            
            imagedestroy($source);
            return null;
            
        } catch (\Exception $e) {
            // Log error but don't break processing
            error_log('CoreBoost AVIF generation error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate WebP variant of image
     *
     * Converts JPEG or PNG image to WebP format with configured quality.
     * Preserves PNG alpha channel (transparency) when present.
     * Uses GD Library.
     *
     * @param string $image_path Path to source image (JPEG or PNG)
     * @return string|null Path to generated WebP file, or null on error
     */
    public function generate_webp_variant($image_path) {
        // Validate file exists
        if (!file_exists($image_path)) {
            return null;
        }
        
        // Check if GD library available
        if (!extension_loaded('gd')) {
            return null;
        }
        
        try {
            // Generate output path
            $output_path = $this->get_variant_path($image_path, 'webp');
            
            // Create directory if needed
            $output_dir = dirname($output_path);
            if (!wp_mkdir_p($output_dir)) {
                return null;
            }
            
            // Load source image based on file type
            $source = $this->load_image_resource($image_path);
            if (!$source) {
                return null;
            }
            
            // Check if imagewebp function available
            if (!function_exists('imagewebp')) {
                imagedestroy($source);
                return null;
            }
            
            // Save as WebP with quality setting
            if (imagewebp($source, $output_path, $this->webp_quality)) {
                imagedestroy($source);
                return $output_path;
            }
            
            imagedestroy($source);
            return null;
            
        } catch (\Exception $e) {
            // Log error but don't break processing
            error_log('CoreBoost WebP generation error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Load image resource from file
     *
     * Loads JPEG or PNG image from filesystem into GD image resource.
     * Handles both formats transparently with proper error handling.
     *
     * @param string $image_path Path to image file
     * @return resource|false GD image resource or false on error
     */
    private function load_image_resource($image_path) {
        if (!file_exists($image_path)) {
            return false;
        }
        
        $file_ext = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
        
        try {
            if ($file_ext === 'png') {
                // Load PNG - GD will handle transparency
                // Don't force alpha handling as it can cause issues
                return imagecreatefrompng($image_path);
                
            } else if ($file_ext === 'jpg' || $file_ext === 'jpeg') {
                // Load JPEG
                return imagecreatefromjpeg($image_path);
            }
            
            return false;
            
        } catch (\Exception $e) {
            error_log('CoreBoost: Image resource loading error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get variant from cache or filesystem
     *
     * Checks if variant file exists for given format.
     * Useful for checking cached variants before generation.
     *
     * @param string $image_url Image URL or path
     * @param string $format Format: 'avif' or 'webp'
     * @return string|null URL to variant file, or null if not found
     */
    public function get_variant_from_cache($image_url, $format = 'avif') {
        // Convert URL to path
        $file_path = $this->url_to_path($image_url);
        
        // Get variant path
        $variant_path = $this->get_variant_path($file_path, $format);
        
        // Check if variant exists
        if (file_exists($variant_path)) {
            // Convert back to URL
            return $this->path_to_url($variant_path);
        }
        
        return null;
    }
    
    /**
     * Render HTML5 picture tag with format fallbacks
     *
     * Creates a <picture> element with multiple format sources:
     * - AVIF (primary, best compression)
     * - WebP (fallback for older browsers)
     * - JPEG (ultimate fallback)
     * Includes srcset for retina (2x) support.
     *
     * @param string $original_url Original JPEG image URL
     * @param string $alt Alt text for image
     * @param string $classes CSS classes for img tag
     * @param array $attrs Additional attributes for img tag
     * @return string HTML5 picture tag
     */
    public function render_picture_tag($original_url, $alt = '', $classes = '', $attrs = array()) {
        $html = '<picture>';
        
        // Get dimensions for srcset
        $file_path = $this->url_to_path($original_url);
        $dimensions = $this->get_image_dimensions($file_path);
        
        // AVIF source (primary)
        $avif_url = $this->get_variant_from_cache($original_url, 'avif');
        if ($avif_url) {
            // Build srcset with proper escaping - don't escape the entire string!
            $srcset = esc_url($avif_url) . ' 1x';
            if ($dimensions) {
                $srcset .= ', ' . esc_url($avif_url) . ' 2x';
            }
            $html .= '<source srcset="' . $srcset . '" type="image/avif">';
        }
        
        // WebP source (fallback)
        $webp_url = $this->get_variant_from_cache($original_url, 'webp');
        if ($webp_url) {
            // Build srcset with proper escaping - don't escape the entire string!
            $srcset = esc_url($webp_url) . ' 1x';
            if ($dimensions) {
                $srcset .= ', ' . esc_url($webp_url) . ' 2x';
            }
            $html .= '<source srcset="' . $srcset . '" type="image/webp">';
        }
        
        // Build img tag attributes
        $img_attrs = 'src="' . esc_url($original_url) . '"';
        $img_attrs .= ' alt="' . esc_attr($alt) . '"';
        
        if (!empty($classes)) {
            $img_attrs .= ' class="' . esc_attr($classes) . '"';
        }
        
        if ($dimensions) {
            $img_attrs .= ' width="' . (int)$dimensions['width'] . '"';
            $img_attrs .= ' height="' . (int)$dimensions['height'] . '"';
        }
        
        // Add any additional attributes
        foreach ($attrs as $key => $value) {
            $img_attrs .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        
        $html .= '<img ' . $img_attrs . '>';
        $html .= '</picture>';
        
        return $html;
    }
    
    /**
     * Queue background variant generation
     *
     * Adds image to processing queue for non-blocking variant generation.
     * Uses Action Scheduler or WP-Cron for background processing.
     *
     * @param string $image_url Image URL to process
     * @param array $formats Formats to generate ('avif', 'webp')
     * @return bool True if queued successfully
     */
    public function queue_background_generation($image_url, $formats = array('avif', 'webp')) {
        // Use Action Scheduler if available (better scheduling)
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                time(),
                'coreboost_generate_image_variants',
                array(
                    'image_url' => $image_url,
                    'formats' => $formats,
                ),
                'coreboost'
            );
            return true;
        }
        
        // Fallback to WP-Cron
        wp_schedule_single_event(
            time(),
            'coreboost_generate_image_variants',
            array(
                'image_url' => $image_url,
                'formats' => $formats,
            )
        );
        
        return true;
    }
    
    /**
     * Background generation handler for WP-Cron
     *
     * Called by coreboost_generate_image_variants action when WP-Cron fires.
     * Handles both on-demand queued generation and eager pre-generation.
     *
     * @param string $image_url Image URL to process
     * @param array $formats Formats to generate ('avif', 'webp')
     * @return void
     */
    public function handle_background_generation($image_url, $formats = array('avif', 'webp')) {
        // Validate input
        if (empty($image_url)) {
            return;
        }
        
        // Ensure formats is an array
        if (!is_array($formats)) {
            $formats = array('avif', 'webp');
        }
        
        // Generate the variants
        $this->generate_variants($image_url, $formats);
    }
    
    /**
     * Generate variants for image (callback for queue)
     *
     * Processes variant generation for queued image.
     * Called via Action Scheduler or WP-Cron.
     *
     * @param string $image_url Image URL to process
     * @param array $formats Formats to generate
     * @return bool True if at least one variant generated
     */
    public function generate_variants($image_url, $formats = array('avif', 'webp')) {
        // Convert URL to path
        $file_path = $this->url_to_path($image_url);
        
        // Validate file exists
        if (!file_exists($file_path)) {
            return false;
        }
        
        $generated = false;
        
        // Generate each requested format
        foreach ($formats as $format) {
            switch ($format) {
                case 'avif':
                    if ($this->generate_avif_variant($file_path)) {
                        $generated = true;
                    }
                    break;
                    
                case 'webp':
                    if ($this->generate_webp_variant($file_path)) {
                        $generated = true;
                    }
                    break;
            }
        }
        
        return $generated;
    }
    
    /**
     * Get image dimensions from file
     *
     * Extracts width and height from image file.
     * Uses WordPress getimagesize or GD functions.
     *
     * @param string $file_path Local file path
     * @return array|false Array with 'width' and 'height' keys, or false
     */
    private function get_image_dimensions($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        $size = getimagesize($file_path);
        if ($size && isset($size[0]) && isset($size[1])) {
            return array(
                'width' => (int)$size[0],
                'height' => (int)$size[1]
            );
        }
        
        return false;
    }
    
    /**
     * Convert image URL to local file path
     *
     * Handles absolute URLs, relative paths, and WordPress URLs.
     *
     * @param string $url Image URL or path
     * @return string Local file path
     */
    private function url_to_path($url) {
        // Handle absolute URLs
        if (strpos($url, 'http') === 0) {
            $site_url = home_url();
            if (strpos($url, $site_url) === 0) {
                // It's a local image - convert to path
                $path = str_replace($site_url, '', $url);
                return ABSPATH . ltrim($path, '/');
            }
        }
        
        // Handle relative paths
        if (strpos($url, '/') === 0) {
            return ABSPATH . ltrim($url, '/');
        }
        
        // Already a path
        return $url;
    }
    
    /**
     * Convert local file path to URL
     *
     * Converts filesystem path back to URL for use in HTML.
     *
     * @param string $path Local file path
     * @return string Image URL
     */
    private function path_to_url($path) {
        $site_url = home_url();
        $abspath = ABSPATH;
        
        // Remove absolute path and make relative
        $relative = str_replace($abspath, '', $path);
        $relative = ltrim($relative, '/');
        
        // Combine with site URL
        return $site_url . '/' . $relative;
    }
    
    /**
     * Get variant file path for image
     *
     * Constructs the path where variant should be stored.
     * Format: /variants/[image-id]/[format]/[filename].ext
     *
     * @param string $image_path Original image path
     * @param string $format Format: 'avif' or 'webp'
     * @return string Full path to variant file
     */
    private function get_variant_path($image_path, $format = 'avif') {
        // Get filename and remove extension
        $filename = basename($image_path);
        $filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);
        
        // Create unique image ID from path
        $image_id = md5($image_path);
        
        // Build variant path: /variants/[image-id]/[format]/[filename].ext
        $variant_filename = $filename_no_ext . '.' . $format;
        $variant_path = $this->variants_dir . '/' . $image_id . '/' . $format . '/' . $variant_filename;
        
        return $variant_path;
    }
    
    /**
     * Estimate size savings from format conversion
     *
     * Calculates compression savings by comparing file sizes.
     * Useful for reporting optimization results.
     *
     * @param string $original_path Original image path
     * @param string $avif_path AVIF variant path (optional)
     * @param string $webp_path WebP variant path (optional)
     * @return array Array with savings data:
     *   - original_size: int
     *   - avif_size: int
     *   - webp_size: int
     *   - avif_savings_bytes: int
     *   - webp_savings_bytes: int
     *   - avif_savings_percent: float
     *   - webp_savings_percent: float
     */
    public function estimate_size_savings($original_path, $avif_path = null, $webp_path = null) {
        $original_size = file_exists($original_path) ? filesize($original_path) : 0;
        $avif_size = ($avif_path && file_exists($avif_path)) ? filesize($avif_path) : 0;
        $webp_size = ($webp_path && file_exists($webp_path)) ? filesize($webp_path) : 0;
        
        return array(
            'original_size' => $original_size,
            'avif_size' => $avif_size,
            'webp_size' => $webp_size,
            'avif_savings_bytes' => max(0, $original_size - $avif_size),
            'webp_savings_bytes' => max(0, $original_size - $webp_size),
            'avif_savings_percent' => $original_size > 0 ? round((($original_size - $avif_size) / $original_size) * 100, 1) : 0,
            'webp_savings_percent' => $original_size > 0 ? round((($original_size - $webp_size) / $original_size) * 100, 1) : 0,
        );
    }
    
    /**
     * Check if image is used on site
     *
     * Searches posts and meta data to determine if image
     * is actively used on the site (for orphan detection).
     *
     * @param int $attachment_id WordPress attachment ID
     * @return bool True if image is used
     */
    public function is_image_used_on_site($attachment_id) {
        global $wpdb;
        
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return false;
        }
        
        $guid = $attachment->guid;
        $filename = basename($guid);
        
        // Search in post content
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->posts WHERE post_content LIKE %s AND ID != %d",
                '%' . $wpdb->esc_like($filename) . '%',
                $attachment_id
            )
        );
        
        if ($count > 0) {
            return true;
        }
        
        // Search in post meta
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_value LIKE %s",
                '%' . $wpdb->esc_like($filename) . '%'
            )
        );
        
        return $count > 0;
    }
}
