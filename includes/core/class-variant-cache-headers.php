<?php
/**
 * Variant Cache Headers Manager
 *
 * Manages HTTP cache headers for image variants:
 * - Creates .htaccess rules for Apache servers
 * - Provides nginx configuration examples
 * - Sets appropriate cache lifetimes
 * - Handles cache-control headers
 *
 * @package CoreBoost
 * @since 3.1.0
 */

namespace CoreBoost\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Variant_Cache_Headers
 *
 * Manages browser caching for image variants
 */
class Variant_Cache_Headers {
    
    /**
     * Default cache lifetime in seconds (1 year)
     */
    const DEFAULT_CACHE_LIFETIME = 31536000;
    
    /**
     * Initialize cache headers management
     */
    public static function init() {
        // Create .htaccess when variants directory is created
        \add_action('coreboost_variants_dir_created', array(__CLASS__, 'create_htaccess'));
        
        // Recreate .htaccess on plugin activation
        if (function_exists('register_activation_hook')) {
            register_activation_hook(COREBOOST_PLUGIN_FILE, array(__CLASS__, 'create_htaccess'));
        }
    }
    
    /**
     * Create .htaccess file in variants directory
     *
     * Adds cache headers for AVIF and WebP files.
     *
     * @return bool True if created successfully
     */
    public static function create_htaccess() {
        if (!function_exists('wp_upload_dir')) {
            return false;
        }
        
        $upload_dir = \wp_upload_dir();
        $variants_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants';
        
        // Create directory if it doesn't exist
        if (!is_dir($variants_dir)) {
            if (!\wp_mkdir_p($variants_dir)) {
                return false;
            }
        }
        
        $htaccess_file = $variants_dir . DIRECTORY_SEPARATOR . '.htaccess';
        
        // Get cache lifetime from settings
        $options = \get_option('coreboost_options', array());
        $cache_lifetime = isset($options['variant_cache_lifetime']) 
            ? (int)$options['variant_cache_lifetime'] 
            : self::DEFAULT_CACHE_LIFETIME;
        
        // Generate .htaccess content
        $htaccess_content = self::generate_htaccess_content($cache_lifetime);
        
        // Write .htaccess file
        $result = file_put_contents($htaccess_file, $htaccess_content);
        
        if ($result !== false) {
            error_log("CoreBoost: Created .htaccess for variants directory with {$cache_lifetime}s cache lifetime");
            return true;
        }
        
        error_log("CoreBoost: Failed to create .htaccess for variants directory");
        return false;
    }
    
    /**
     * Generate .htaccess content
     *
     * @param int $cache_lifetime Cache lifetime in seconds
     * @return string .htaccess content
     */
    private static function generate_htaccess_content($cache_lifetime) {
        $cache_days = round($cache_lifetime / 86400);
        
        return <<<HTACCESS
# CoreBoost Image Variant Cache Headers
# Generated automatically - do not edit manually
# Cache lifetime: {$cache_days} days ({$cache_lifetime} seconds)

<IfModule mod_headers.c>
    # Set cache headers for AVIF and WebP images
    <FilesMatch "\.(avif|webp)$">
        # Enable caching
        Header set Cache-Control "public, max-age={$cache_lifetime}, immutable"
        
        # Set Expires header (for older browsers)
        ExpiresActive On
        ExpiresDefault "access plus {$cache_days} days"
        
        # Add ETag for cache validation
        FileETag MTime Size
        
        # Disable Last-Modified (immutable files don't need it)
        Header unset Last-Modified
    </FilesMatch>
</IfModule>

<IfModule mod_expires.c>
    # Fallback expires rules if mod_headers not available
    ExpiresActive On
    ExpiresByType image/avif "access plus {$cache_days} days"
    ExpiresByType image/webp "access plus {$cache_days} days"
</IfModule>

# Prevent directory listing
Options -Indexes

# Allow access to image files only
<FilesMatch "\.(avif|webp)$">
    Require all granted
</FilesMatch>

HTACCESS;
    }
    
    /**
     * Get nginx configuration example
     *
     * Returns nginx config snippet for cache headers.
     *
     * @return string Nginx configuration
     */
    public static function get_nginx_config() {
        $options = \get_option('coreboost_options', array());
        $cache_lifetime = isset($options['variant_cache_lifetime']) 
            ? (int)$options['variant_cache_lifetime'] 
            : self::DEFAULT_CACHE_LIFETIME;
        
        $cache_days = round($cache_lifetime / 86400);
        
        return <<<NGINX
# CoreBoost Image Variant Cache Headers for Nginx
# Add this to your nginx server block

location ~* /wp-content/uploads/coreboost-variants/.*\.(avif|webp)$ {
    # Enable caching
    add_header Cache-Control "public, max-age={$cache_lifetime}, immutable";
    
    # Add Expires header (for older browsers)
    expires {$cache_days}d;
    
    # Enable gzip compression (optional, these are already compressed)
    gzip off;
    
    # Allow access
    access_log off;
    log_not_found off;
}

NGINX;
    }
    
    /**
     * Verify .htaccess exists and is current
     *
     * Checks if .htaccess file exists and matches current settings.
     *
     * @return array Status information
     */
    public static function verify_htaccess() {
        if (!function_exists('wp_upload_dir')) {
            return array(
                'exists' => false,
                'current' => false,
                'writable' => false,
                'message' => 'WordPress functions not available',
            );
        }
        
        $upload_dir = \wp_upload_dir();
        $variants_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants';
        $htaccess_file = $variants_dir . DIRECTORY_SEPARATOR . '.htaccess';
        
        $exists = file_exists($htaccess_file);
        $writable = is_dir($variants_dir) && is_writable($variants_dir);
        
        // Check if content is current
        $current = false;
        if ($exists) {
            $content = file_get_contents($htaccess_file);
            $options = \get_option('coreboost_options', array());
            $cache_lifetime = isset($options['variant_cache_lifetime']) 
                ? (int)$options['variant_cache_lifetime'] 
                : self::DEFAULT_CACHE_LIFETIME;
            
            // Check if cache lifetime matches
            $current = (strpos($content, "max-age={$cache_lifetime}") !== false);
        }
        
        return array(
            'exists' => $exists,
            'current' => $current,
            'writable' => $writable,
            'path' => $htaccess_file,
            'message' => self::get_status_message($exists, $current, $writable),
        );
    }
    
    /**
     * Get status message
     *
     * @param bool $exists File exists
     * @param bool $current Content is current
     * @param bool $writable Directory is writable
     * @return string Status message
     */
    private static function get_status_message($exists, $current, $writable) {
        if (!$exists && !$writable) {
            return 'Variants directory is not writable. Cannot create .htaccess file.';
        }
        
        if (!$exists) {
            return '.htaccess file does not exist. Click "Create .htaccess" to generate it.';
        }
        
        if (!$current) {
            return '.htaccess file exists but cache settings are outdated. Click "Update .htaccess" to refresh.';
        }
        
        return '.htaccess file exists and is up to date.';
    }
    
    /**
     * Delete .htaccess file
     *
     * Removes .htaccess from variants directory.
     *
     * @return bool True if deleted successfully
     */
    public static function delete_htaccess() {
        if (!function_exists('wp_upload_dir')) {
            return false;
        }
        
        $upload_dir = \wp_upload_dir();
        $variants_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants';
        $htaccess_file = $variants_dir . DIRECTORY_SEPARATOR . '.htaccess';
        
        if (file_exists($htaccess_file)) {
            return unlink($htaccess_file);
        }
        
        return true;
    }
    
    /**
     * Get default cache lifetime
     *
     * @return int Cache lifetime in seconds
     */
    public static function get_default_cache_lifetime() {
        return self::DEFAULT_CACHE_LIFETIME;
    }
    
    /**
     * Format cache lifetime for display
     *
     * @param int $seconds Cache lifetime in seconds
     * @return string Formatted string (e.g., "1 year", "6 months")
     */
    public static function format_cache_lifetime($seconds) {
        $days = $seconds / 86400;
        
        if ($days >= 365) {
            $years = round($days / 365, 1);
            return $years . ' year' . ($years != 1 ? 's' : '');
        }
        
        if ($days >= 30) {
            $months = round($days / 30);
            return $months . ' month' . ($months != 1 ? 's' : '');
        }
        
        if ($days >= 7) {
            $weeks = round($days / 7);
            return $weeks . ' week' . ($weeks != 1 ? 's' : '');
        }
        
        return round($days) . ' day' . ($days != 1 ? 's' : '');
    }
}
