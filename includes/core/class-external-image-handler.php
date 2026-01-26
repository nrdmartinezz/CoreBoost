<?php
/**
 * External Image Handler
 *
 * Handles external and CDN images:
 * - Validates trusted domains (whitelist)
 * - Downloads and caches external images
 * - Generates variants for external images
 * - Hash-based storage to avoid conflicts
 *
 * @package CoreBoost
 * @since 3.1.0
 */

namespace CoreBoost\Core;

use CoreBoost\PublicCore\Image_Format_Optimizer;
use CoreBoost\Core\Context_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class External_Image_Handler
 *
 * Manages caching and optimization of external/CDN images
 */
class External_Image_Handler {
    
    /**
     * Trusted CDN domains (default whitelist)
     *
     * @var array
     */
    private static $trusted_domains = array(
        'wp.com',
        'wordpress.com',
        'gravatar.com',
        'githubusercontent.com',
        'cloudinary.com',
        'imgur.com',
        'photobucket.com',
        'unsplash.com',
        'pexels.com',
        'pixabay.com',
    );
    
    /**
     * Check if URL is external
     *
     * @param string $url Image URL
     * @return bool True if external
     */
    public static function is_external_url($url) {
        $site_url = \get_site_url();
        $site_domain = parse_url($site_url, PHP_URL_HOST);
        $url_domain = parse_url($url, PHP_URL_HOST);
        
        return $url_domain && $url_domain !== $site_domain;
    }
    
    /**
     * Check if external domain is trusted
     *
     * @param string $url Image URL
     * @return bool True if trusted
     */
    public static function is_trusted_domain($url) {
        $domain = parse_url($url, PHP_URL_HOST);
        
        if (!$domain) {
            return false;
        }
        
        // Check against trusted domains list
        foreach (self::$trusted_domains as $trusted) {
            if (strpos($domain, $trusted) !== false) {
                return true;
            }
        }
        
        // Check custom whitelist from settings
        $options = \get_option('coreboost_options', array());
        if (isset($options['external_image_domains'])) {
            $custom_domains = explode("\n", $options['external_image_domains']);
            foreach ($custom_domains as $custom) {
                $custom = trim($custom);
                if (!empty($custom) && strpos($domain, $custom) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Download and cache external image
     *
     * Downloads external image to local cache directory.
     *
     * @param string $external_url External image URL
     * @return string|null Local cached file path or null on failure
     */
    public static function cache_external_image($external_url) {
        // Validate URL
        if (!filter_var($external_url, FILTER_VALIDATE_URL)) {
            Context_Helper::debug_log("Invalid external URL: {$external_url}");
            return null;
        }
        
        // Check if domain is trusted
        if (!self::is_trusted_domain($external_url)) {
            Context_Helper::debug_log("Untrusted domain for external image: {$external_url}");
            return null;
        }
        
        // Generate hash-based filename to avoid conflicts
        $hash = md5($external_url);
        $extension = pathinfo(parse_url($external_url, PHP_URL_PATH), PATHINFO_EXTENSION);
        
        // Fallback to jpg if no extension
        if (empty($extension) || !in_array(strtolower($extension), array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
            $extension = 'jpg';
        }
        
        // Create cache directory
        $upload_dir = \wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-external';
        
        if (!\wp_mkdir_p($cache_dir)) {
            Context_Helper::debug_log('Failed to create external cache directory');
            return null;
        }
        
        $local_path = $cache_dir . DIRECTORY_SEPARATOR . $hash . '.' . $extension;
        
        // Return cached file if already exists
        if (file_exists($local_path)) {
            // Check if file is older than 7 days (refresh cache)
            $age = time() - filemtime($local_path);
            if ($age < (7 * \DAY_IN_SECONDS)) {
                return $local_path;
            }
        }
        
        // Download image
        $response = \wp_remote_get($external_url, array(
            'timeout' => 15,
            'sslverify' => true,
        ));
        
        if (\is_wp_error($response)) {
            Context_Helper::debug_log('Failed to download external image: ' . $response->get_error_message());
            return null;
        }
        
        $body = \wp_remote_retrieve_body($response);
        if (empty($body)) {
            Context_Helper::debug_log("Empty response for external image: {$external_url}");
            return null;
        }
        
        // Save to local cache
        $saved = file_put_contents($local_path, $body);
        if ($saved === false) {
            Context_Helper::debug_log('Failed to save external image to cache');
            return null;
        }
        
        // Validate it's actually an image
        $image_info = @getimagesize($local_path);
        if (!$image_info) {
            unlink($local_path);
            Context_Helper::debug_log("Downloaded file is not a valid image: {$external_url}");
            return null;
        }
        
        Context_Helper::debug_log("Cached external image: {$external_url} -> {$local_path}");
        return $local_path;
    }
    
    /**
     * Generate variants for external image
     *
     * Downloads external image if needed, then generates AVIF/WebP variants.
     *
     * @param string $external_url External image URL
     * @param Image_Format_Optimizer $format_optimizer Format optimizer instance
     * @return array|null Variant URLs array or null on failure
     */
    public static function generate_external_variants($external_url, $format_optimizer) {
        // Download and cache external image
        $local_path = self::cache_external_image($external_url);
        
        if (!$local_path) {
            return null;
        }
        
        // Generate AVIF and WebP variants
        $avif_path = $format_optimizer->generate_avif_variant($local_path);
        $webp_path = $format_optimizer->generate_webp_variant($local_path);
        
        $variants = array();
        
        if ($avif_path && file_exists($avif_path)) {
            $variants['avif'] = Path_Helper::path_to_url($avif_path);
            
            // Cache the variant URL
            Variant_Cache::set_variant($external_url, 'avif', $variants['avif']);
        }
        
        if ($webp_path && file_exists($webp_path)) {
            $variants['webp'] = Path_Helper::path_to_url($webp_path);
            
            // Cache the variant URL
            Variant_Cache::set_variant($external_url, 'webp', $variants['webp']);
        }
        
        return !empty($variants) ? $variants : null;
    }
    
    /**
     * Clean up old external image cache
     *
     * Removes cached external images older than specified days.
     *
     * @param int $days Age threshold in days
     * @return array Cleanup statistics
     */
    public static function cleanup_old_cache($days = 30) {
        $upload_dir = \wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-external';
        
        if (!is_dir($cache_dir)) {
            return array(
                'deleted' => 0,
                'bytes_freed' => 0,
            );
        }
        
        $deleted = 0;
        $bytes_freed = 0;
        $threshold = time() - ($days * \DAY_IN_SECONDS);
        
        $files = glob($cache_dir . DIRECTORY_SEPARATOR . '*');
        
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            
            $mtime = filemtime($file);
            if ($mtime < $threshold) {
                $size = filesize($file);
                if (unlink($file)) {
                    $deleted++;
                    $bytes_freed += $size;
                }
            }
        }
        
        Context_Helper::debug_log("Cleaned up {$deleted} external images, freed " . Path_Helper::format_bytes($bytes_freed));
        
        return array(
            'deleted' => $deleted,
            'bytes_freed' => $bytes_freed,
        );
    }
    
    /**
     * Add custom trusted domain
     *
     * @param string $domain Domain to trust
     */
    public static function add_trusted_domain($domain) {
        $options = \get_option('coreboost_options', array());
        
        if (!isset($options['external_image_domains'])) {
            $options['external_image_domains'] = '';
        }
        
        $domains = explode("\n", $options['external_image_domains']);
        $domains[] = trim($domain);
        $options['external_image_domains'] = implode("\n", array_unique(array_filter($domains)));
        
        \update_option('coreboost_options', $options);
    }
    
    /**
     * Get trusted domains list
     *
     * @return array List of trusted domains
     */
    public static function get_trusted_domains() {
        $all_domains = self::$trusted_domains;
        
        $options = \get_option('coreboost_options', array());
        if (isset($options['external_image_domains'])) {
            $custom_domains = explode("\n", $options['external_image_domains']);
            foreach ($custom_domains as $domain) {
                $domain = trim($domain);
                if (!empty($domain)) {
                    $all_domains[] = $domain;
                }
            }
        }
        
        return array_unique($all_domains);
    }
}
