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

use CoreBoost\Core\Context_Helper;

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
        // Only initialize if WordPress functions are available
        if (!function_exists('add_action')) {
            return;
        }
        
        // Create .htaccess when variants directory is created
        \add_action('coreboost_variants_dir_created', array(__CLASS__, 'create_htaccess'));
        
        // Recreate .htaccess on plugin activation (if constant is defined)
        if (defined('COREBOOST_PLUGIN_FILE') && function_exists('register_activation_hook')) {
            \register_activation_hook(\COREBOOST_PLUGIN_FILE, array(__CLASS__, 'create_htaccess'));
        }
    }
    
    /**
     * Create .htaccess file in variants directory
     *
     * Adds cache headers for AVIF and WebP files.
     *
     * @param bool $force Force recreation even if file exists
     * @return bool True if created successfully
     */
    public static function create_htaccess($force = false) {
        if (!function_exists('wp_upload_dir')) {
            error_log("CoreBoost Cache Headers: wp_upload_dir not available");
            return false;
        }
        
        $upload_dir = \wp_upload_dir();
        $variants_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants';
        $htaccess_file = $variants_dir . DIRECTORY_SEPARATOR . '.htaccess';
        
        error_log("CoreBoost Cache Headers: Starting .htaccess creation");
        error_log("CoreBoost Cache Headers: Variants directory: {$variants_dir}");
        error_log("CoreBoost Cache Headers: .htaccess path: {$htaccess_file}");
        
        // Create directory if it doesn't exist
        if (!is_dir($variants_dir)) {
            error_log("CoreBoost Cache Headers: Directory doesn't exist, creating...");
            if (!\wp_mkdir_p($variants_dir)) {
                error_log("CoreBoost Cache Headers: FAILED to create directory");
                return false;
            }
            error_log("CoreBoost Cache Headers: Directory created successfully");
        } else {
            error_log("CoreBoost Cache Headers: Directory already exists");
        }
        
        // Check if .htaccess already exists
        if (!$force && file_exists($htaccess_file)) {
            error_log("CoreBoost Cache Headers: .htaccess already exists (use force=true to overwrite)");
            $existing_content = file_get_contents($htaccess_file);
            error_log("CoreBoost Cache Headers: Existing content length: " . strlen($existing_content) . " bytes");
            return true;
        }
        
        // Get cache lifetime from settings
        $options = \get_option('coreboost_options', array());
        $cache_lifetime = isset($options['variant_cache_lifetime']) 
            ? (int)$options['variant_cache_lifetime'] 
            : self::DEFAULT_CACHE_LIFETIME;
        
        error_log("CoreBoost Cache Headers: Cache lifetime: {$cache_lifetime} seconds");
        
        // Generate .htaccess content
        $htaccess_content = self::generate_htaccess_content($cache_lifetime);
        error_log("CoreBoost Cache Headers: Generated content length: " . strlen($htaccess_content) . " bytes");
        
        // Check if directory is writable
        if (!is_writable($variants_dir)) {
            error_log("CoreBoost Cache Headers: FAILED - Directory is not writable");
            return false;
        }
        
        // Write .htaccess file
        $result = file_put_contents($htaccess_file, $htaccess_content);
        
        if ($result !== false) {
            error_log("CoreBoost Cache Headers: SUCCESS - Created .htaccess ({$result} bytes written)");
            error_log("CoreBoost Cache Headers: File exists check: " . (file_exists($htaccess_file) ? 'YES' : 'NO'));
            error_log("CoreBoost Cache Headers: File permissions: " . substr(sprintf('%o', fileperms($htaccess_file)), -4));
            return true;
        }
        
        error_log("CoreBoost Cache Headers: FAILED - file_put_contents returned false");
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

# Enable expires module
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/avif "access plus {$cache_days} days"
    ExpiresByType image/webp "access plus {$cache_days} days"
</IfModule>

# Set cache control headers
<IfModule mod_headers.c>
    <FilesMatch "\.(avif|webp)$">
        Header set Cache-Control "public, max-age={$cache_lifetime}, immutable"
        Header unset Last-Modified
    </FilesMatch>
</IfModule>

# Prevent directory listing
Options -Indexes

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
    
    /**
     * Debug cache headers status
     *
     * Returns comprehensive diagnostic information for troubleshooting.
     * Call this to check if .htaccess is properly configured.
     *
     * @return array Diagnostic information
     */
    public static function debug_status() {
        $info = array();
        
        // Check WordPress functions
        $info['wordpress_loaded'] = function_exists('wp_upload_dir');
        
        if (!$info['wordpress_loaded']) {
            $info['error'] = 'WordPress functions not available';
            return $info;
        }
        
        // Get paths
        $upload_dir = \wp_upload_dir();
        $variants_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants';
        $htaccess_file = $variants_dir . DIRECTORY_SEPARATOR . '.htaccess';
        
        $info['variants_dir'] = $variants_dir;
        $info['htaccess_file'] = $htaccess_file;
        $info['variants_url'] = $upload_dir['baseurl'] . '/coreboost-variants';
        
        // Check directory
        $info['dir_exists'] = is_dir($variants_dir);
        $info['dir_writable'] = is_dir($variants_dir) && is_writable($variants_dir);
        
        // Check .htaccess file
        $info['htaccess_exists'] = file_exists($htaccess_file);
        
        if ($info['htaccess_exists']) {
            $content = file_get_contents($htaccess_file);
            $info['htaccess_size'] = strlen($content);
            $info['htaccess_readable'] = true;
            $info['htaccess_content'] = $content;
            
            // Parse cache lifetime from content
            if (preg_match('/max-age=(\d+)/', $content, $matches)) {
                $info['current_max_age'] = (int)$matches[1];
                $info['current_cache_days'] = round($info['current_max_age'] / 86400);
            }
        } else {
            $info['htaccess_size'] = 0;
            $info['htaccess_readable'] = false;
        }
        
        // Get expected settings
        $options = \get_option('coreboost_options', array());
        $info['expected_cache_lifetime'] = isset($options['variant_cache_lifetime']) 
            ? (int)$options['variant_cache_lifetime'] 
            : self::DEFAULT_CACHE_LIFETIME;
        $info['expected_cache_days'] = round($info['expected_cache_lifetime'] / 86400);
        
        // Check if settings match
        $info['settings_match'] = false;
        if ($info['htaccess_exists'] && isset($info['current_max_age'])) {
            $info['settings_match'] = ($info['current_max_age'] === $info['expected_cache_lifetime']);
        }
        
        // Count variant files (use recursive iterator to avoid glob issues)
        if ($info['dir_exists']) {
            try {
                $avif_count = 0;
                $webp_count = 0;
                
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($variants_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $ext = strtolower($file->getExtension());
                        if ($ext === 'avif') $avif_count++;
                        if ($ext === 'webp') $webp_count++;
                    }
                }
                
                $info['avif_count'] = $avif_count;
                $info['webp_count'] = $webp_count;
            } catch (\Exception $e) {
                $info['avif_count'] = 0;
                $info['webp_count'] = 0;
                $info['file_count_error'] = $e->getMessage();
            }
        }
        
        // Check server type
        $info['server_software'] = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
        $info['is_apache'] = (stripos($info['server_software'], 'apache') !== false);
        $info['is_nginx'] = (stripos($info['server_software'], 'nginx') !== false);
        
        // Action needed
        if (!$info['htaccess_exists']) {
            $info['action_needed'] = 'create_htaccess';
            $info['action_message'] = 'Run: \CoreBoost\Core\Variant_Cache_Headers::create_htaccess(true)';
        } elseif (!$info['settings_match']) {
            $info['action_needed'] = 'update_htaccess';
            $info['action_message'] = 'Run: \CoreBoost\Core\Variant_Cache_Headers::create_htaccess(true)';
        } else {
            $info['action_needed'] = 'none';
            $info['action_message'] = 'Cache headers properly configured';
        }
        
        return $info;
    }
}
