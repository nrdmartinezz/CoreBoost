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

use CoreBoost\Core\Path_Helper;
use CoreBoost\Core\Variant_Cache;

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
     * Static flag to track if .htaccess has been checked this request
     *
     * @var bool
     */
    private static $htaccess_checked = false;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options = array()) {
        $this->options = $options;
        
        // Defer upload directory initialization (may not exist in CLI context)
        $this->variants_dir = null;
        
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
        
        // Register WP-Cron action handler (only if WordPress is loaded)
        if (function_exists('add_action')) {
            add_action('coreboost_generate_image_variants', array($this, 'handle_background_generation'), 10, 2);
        }
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
        // Handle both URLs and filesystem paths
        // If it's already a filesystem path, use it directly
        if (file_exists($image_url)) {
            $file_path = $image_url;
        } else {
            // Convert URL to path
            $file_path = Path_Helper::url_to_path($image_url);
            
            // Check if file exists locally
            if (!file_exists($file_path)) {
                return false;
            }
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
        $dimensions = Path_Helper::get_image_dimensions($file_path);
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
        // Validate input
        if (empty($image_path) || !is_string($image_path)) {
            return null;
        }
        
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
            $output_path = Path_Helper::get_variant_path($image_path, 'avif');
            
            // Create directory if needed
            $output_dir = dirname($output_path);
            if (!wp_mkdir_p($output_dir)) {
                return null;
            }
            
            // Ensure .htaccess exists for cache headers (one-time check per request)
            $this->ensure_htaccess_exists();
            
            // Load source image based on file type
            $source = $this->load_image_resource($image_path);
            if (!$source) {
                return null;
            }
            
            // For PHP 8.1+, use imageavif if available
            if (function_exists('imageavif')) {
                // Alpha channel already preserved by load_image_resource()
                // DO NOT change alphablending settings - would destroy transparency
                
                // Save as AVIF with quality setting
                if (imageavif($source, $output_path, $this->avif_quality)) {
                    imagedestroy($source);
                    
                    // Track compression analytics
                    $original_size = filesize($image_path);
                    $variant_size = filesize($output_path);
                    if ($original_size && $variant_size) {
                        // Extract width from filename if responsive variant
                        $width = null;
                        if (preg_match('/-(\d+)w\.(jpg|jpeg|png|gif)$/i', basename($image_path), $matches)) {
                            $width = (int)$matches[1];
                        }
                        
                        \CoreBoost\Core\Compression_Analytics::track_variant_generation(
                            Path_Helper::path_to_url($image_path),
                            'avif',
                            $original_size,
                            $variant_size,
                            $width
                        );
                    }
                    
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
        // Validate input
        if (empty($image_path) || !is_string($image_path)) {
            return null;
        }
        
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
            $output_path = Path_Helper::get_variant_path($image_path, 'webp');
            
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
            
            // Alpha channel already preserved by load_image_resource()
            // DO NOT change alphablending settings - would destroy transparency
            
            // Save as WebP with quality setting
            if (imagewebp($source, $output_path, $this->webp_quality)) {
                imagedestroy($source);
                
                // Track compression analytics
                $original_size = filesize($image_path);
                $variant_size = filesize($output_path);
                if ($original_size && $variant_size) {
                    // Extract width from filename if responsive variant
                    $width = null;
                    if (preg_match('/-(\d+)w\.(jpg|jpeg|png|gif)$/i', basename($image_path), $matches)) {
                        $width = (int)$matches[1];
                    }
                    
                    \CoreBoost\Core\Compression_Analytics::track_variant_generation(
                        Path_Helper::path_to_url($image_path),
                        'webp',
                        $original_size,
                        $variant_size,
                        $width
                    );
                }
                
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
        
        // Check file size to prevent memory issues with very large images
        $file_size = filesize($image_path);
        $max_size = 50 * 1024 * 1024; // 50MB limit
        if ($file_size > $max_size) {
            error_log("CoreBoost: Skipping large image ({$file_size} bytes): {$image_path}");
            return false;
        }
        
        $file_ext = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
        
        try {
            if ($file_ext === 'png') {
                // Load PNG with proper transparency preservation
                $resource = @imagecreatefrompng($image_path);
                if ($resource === false) {
                    error_log("CoreBoost: Failed to load PNG: {$image_path}");
                    return false;
                }
                
                // Convert palette-based PNG to truecolor for proper alpha handling
                if (function_exists('imagepalettetotruecolor')) {
                    imagepalettetotruecolor($resource);
                }
                
                // Preserve alpha channel for transparent PNGs
                // imagealphablending(false) = disable blending, copy alpha channel literally
                // imagesavealpha(true) = save full alpha channel information
                imagealphablending($resource, false);
                imagesavealpha($resource, true);
                
                return $resource;
                
            } else if ($file_ext === 'jpg' || $file_ext === 'jpeg') {
                // Load JPEG
                $resource = @imagecreatefromjpeg($image_path);
                if ($resource === false) {
                    error_log("CoreBoost: Failed to load JPEG: {$image_path}");
                    return false;
                }
                return $resource;
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
        // Layer 1 & 2: Check runtime and persistent cache
        $cached_url = Variant_Cache::get_variant($image_url, $format);
        if ($cached_url !== null) {
            return $cached_url;
        }
        
        // Layer 3: Filesystem fallback (cache miss - warm cache on hit)
        $file_path = Path_Helper::url_to_path($image_url);
        $variant_path = Path_Helper::get_variant_path($file_path, $format);
        
        // Check if variant exists on filesystem
        if (file_exists($variant_path)) {
            // Convert back to URL
            $variant_url = Path_Helper::path_to_url($variant_path);
            
            // Warm both cache layers for future requests
            Variant_Cache::set_variant($image_url, $format, $variant_url);
            
            return $variant_url;
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
        $file_path = Path_Helper::url_to_path($original_url);
        $dimensions = Path_Helper::get_image_dimensions($file_path);
        
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
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CoreBoost render_picture_tag OUTPUT: " . substr($html, 0, 200) . "...");
        }
        
        return $html;
    }
    
    /**
     * Render responsive picture tag with width descriptors
     *
     * Creates a <picture> element with responsive srcset using width descriptors
     * for different screen sizes and pixel densities. Generates separate sources
     * for AVIF and WebP formats with proper fallbacks.
     *
     * @param string $original_url Original image URL
     * @param string $alt Alt text for image
     * @param string $classes CSS classes for img tag
     * @param array $attrs Additional attributes for img tag
     * @param array $responsive_variants Responsive variants data from resizer
     * @param int $rendered_width Rendered width for sizes attribute
     * @return string HTML5 picture tag with responsive srcset
     */
    public function render_responsive_picture_tag($original_url, $alt = '', $classes = '', $attrs = array(), $responsive_variants = array(), $rendered_width = null) {
        $html = '<picture>';
        
        // Get dimensions for fallback
        $file_path = Path_Helper::url_to_path($original_url);
        $dimensions = Path_Helper::get_image_dimensions($file_path);
        
        if (!empty($responsive_variants)) {
            // Build AVIF srcset with width descriptors
            $avif_srcset = array();
            foreach ($responsive_variants as $variant) {
                if (isset($variant['avif'])) {
                    $avif_srcset[] = esc_url($variant['avif']) . ' ' . $variant['width'] . 'w';
                }
            }
            
            if (!empty($avif_srcset)) {
                $srcset_string = implode(', ', $avif_srcset);
                $sizes = $this->generate_sizes_attribute($rendered_width);
                $html .= '<source srcset="' . $srcset_string . '" sizes="' . esc_attr($sizes) . '" type="image/avif">';
            }
            
            // Build WebP srcset with width descriptors
            $webp_srcset = array();
            foreach ($responsive_variants as $variant) {
                if (isset($variant['webp'])) {
                    $webp_srcset[] = esc_url($variant['webp']) . ' ' . $variant['width'] . 'w';
                }
            }
            
            if (!empty($webp_srcset)) {
                $srcset_string = implode(', ', $webp_srcset);
                $sizes = $this->generate_sizes_attribute($rendered_width);
                $html .= '<source srcset="' . $srcset_string . '" sizes="' . esc_attr($sizes) . '" type="image/webp">';
            }
        } else {
            // Fallback to simple srcset (no responsive variants yet)
            $avif_url = $this->get_variant_from_cache($original_url, 'avif');
            if ($avif_url) {
                $srcset = esc_url($avif_url) . ' 1x';
                if ($dimensions) {
                    $srcset .= ', ' . esc_url($avif_url) . ' 2x';
                }
                $html .= '<source srcset="' . $srcset . '" type="image/avif">';
            }
            
            $webp_url = $this->get_variant_from_cache($original_url, 'webp');
            if ($webp_url) {
                $srcset = esc_url($webp_url) . ' 1x';
                if ($dimensions) {
                    $srcset .= ', ' . esc_url($webp_url) . ' 2x';
                }
                $html .= '<source srcset="' . $srcset . '" type="image/webp">';
            }
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
     * Generate sizes attribute for responsive images
     *
     * Creates appropriate sizes attribute based on rendered width.
     * Uses mobile-first approach with common breakpoints.
     *
     * @param int $rendered_width Rendered width in pixels
     * @return string Sizes attribute value
     */
    private function generate_sizes_attribute($rendered_width) {
        if (!$rendered_width) {
            return '100vw';
        }
        
        // Mobile-first approach
        // Small screens: full width
        // Medium screens (768px+): rendered width or full width if smaller
        // Large screens: exact rendered width
        return sprintf(
            '(max-width: 768px) 100vw, %dpx',
            (int)$rendered_width
        );
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
        $file_path = Path_Helper::url_to_path($image_url);
        
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
     * Convert image URL to local file pathnversion
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
    
    /**
     * Ensure .htaccess file exists for cache headers
     *
     * Creates .htaccess file in variants directory if it doesn't exist.
     * Only checks once per request to avoid performance overhead.
     */
    private function ensure_htaccess_exists() {
        // Skip if already checked this request
        if (self::$htaccess_checked) {
            return;
        }
        
        self::$htaccess_checked = true;
        
        // Skip if Variant_Cache_Headers class not available
        if (!class_exists('CoreBoost\\Core\\Variant_Cache_Headers')) {
            return;
        }
        
        // Verify .htaccess exists
        $status = \CoreBoost\Core\Variant_Cache_Headers::verify_htaccess();
        
        // Create if missing or outdated
        if (!$status['exists'] || !$status['current']) {
            \CoreBoost\Core\Variant_Cache_Headers::create_htaccess();
        }
    }
}
