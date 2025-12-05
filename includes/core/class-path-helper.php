<?php
/**
 * Path Helper Utility
 *
 * Centralized utility for path and URL conversions.
 * Eliminates code duplication across multiple optimizer classes.
 *
 * @package CoreBoost
 * @since 2.8.1
 */

namespace CoreBoost\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Path_Helper
 *
 * Provides static utility methods for path/URL manipulation
 */
class Path_Helper {
    
    /**
     * Convert image URL to local file path
     *
     * Handles absolute URLs, relative paths, and WordPress URLs.
     * Normalizes path separators for cross-platform compatibility.
     *
     * @param string $url Image URL or path
     * @return string Local file path with normalized separators
     */
    public static function url_to_path($url) {
        // Handle absolute URLs
        if (strpos($url, 'http') === 0) {
            $site_url = home_url();
            if (strpos($url, $site_url) === 0) {
                // It's a local image - convert to path
                $path = str_replace($site_url, '', $url);
                $result = ABSPATH . ltrim($path, '/');
                // Normalize path separators for Windows
                return str_replace('/', DIRECTORY_SEPARATOR, $result);
            }
            // External URL - return as-is
            return $url;
        }
        
        // Handle relative paths
        if (strpos($url, '/') === 0) {
            $result = ABSPATH . ltrim($url, '/');
            // Normalize path separators for Windows
            return str_replace('/', DIRECTORY_SEPARATOR, $result);
        }
        
        // Already a path - normalize separators
        return str_replace('/', DIRECTORY_SEPARATOR, $url);
    }
    
    /**
     * Convert local file path to URL
     *
     * Converts filesystem path back to URL for use in HTML.
     * Handles Windows and Unix path separators.
     *
     * @param string $path Local file path
     * @return string Image URL
     */
    public static function path_to_url($path) {
        $site_url = home_url();
        $abspath = ABSPATH;
        
        // Normalize path separators (Windows compatibility)
        $path = str_replace('\\', '/', $path);
        $abspath = str_replace('\\', '/', $abspath);
        
        // Remove absolute path and make relative
        $relative = str_replace($abspath, '', $path);
        $relative = ltrim($relative, '/');
        
        // Combine with site URL
        return $site_url . '/' . $relative;
    }
    
    /**
     * Get image dimensions from file
     *
     * Extracts width and height from image file.
     * Works with both URLs and filesystem paths.
     *
     * @param string $file_path Local file path or URL
     * @return array|false Array with 'width' and 'height' keys, or false on error
     */
    public static function get_image_dimensions($file_path) {
        // Convert URL to path if needed
        if (strpos($file_path, 'http') === 0) {
            $file_path = self::url_to_path($file_path);
        }
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $size = @getimagesize($file_path);
        if ($size && isset($size[0]) && isset($size[1])) {
            return array(
                'width' => (int)$size[0],
                'height' => (int)$size[1]
            );
        }
        
        return false;
    }
    
    /**
     * Format bytes into human-readable string
     *
     * Converts byte count to MiB, KiB, or B with proper formatting.
     *
     * @param int $bytes Number of bytes
     * @return string Formatted string (e.g., "62.3 KiB", "1.5 MiB")
     */
    public static function format_bytes($bytes) {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MiB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KiB';
        }
        return $bytes . ' B';
    }
    
    /**
     * Get variant file path for image
     *
     * Constructs the path where variant should be stored.
     * Format: /coreboost-variants/[relative-path]/[filename].ext
     *
     * @param string $image_path Original image path
     * @param string $format Format: 'avif', 'webp', etc.
     * @param int|null $width Optional width for responsive variants (e.g., 800 for "image-800w.avif")
     * @return string Full path to variant file
     */
    public static function get_variant_path($image_path, $format = 'avif', $width = null) {
        $upload_dir = wp_upload_dir();
        $variants_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants' . DIRECTORY_SEPARATOR;
        
        // Normalize path separators for consistent comparison
        $image_path_normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $image_path);
        $uploads_base = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $upload_dir['basedir']);
        
        // Get relative path from uploads folder
        $relative_path = str_replace($uploads_base . DIRECTORY_SEPARATOR, '', $image_path_normalized);
        
        // If the image isn't in uploads dir, just use the basename
        if ($relative_path === $image_path_normalized) {
            $relative_path = basename($image_path);
        }
        
        $path_info = pathinfo($relative_path);
        
        // Build filename with optional width descriptor
        $filename = $path_info['filename'];
        if ($width !== null) {
            $filename .= '-' . $width . 'w';
        }
        $filename .= '.' . $format;
        
        // Build variant path: /coreboost-variants/[dirname]/[filename].ext
        // Handle case where dirname is '.' (current directory)
        $dirname = ($path_info['dirname'] === '.' || $path_info['dirname'] === '') ? '' : $path_info['dirname'] . DIRECTORY_SEPARATOR;
        $variant_path = $variants_dir . $dirname . $filename;
        
        return $variant_path;
    }
    
    /**
     * Check if URL is a local image (not external CDN)
     *
     * @param string $url Image URL
     * @return bool True if local image
     */
    public static function is_local_url($url) {
        // Skip data URIs
        if (strpos($url, 'data:') === 0) {
            return false;
        }
        
        // Skip external URLs
        $site_url = home_url();
        
        // Relative paths are local
        if (strpos($url, 'http') !== 0) {
            return true;
        }
        
        // Check if URL matches site domain
        return strpos($url, $site_url) === 0;
    }
}
