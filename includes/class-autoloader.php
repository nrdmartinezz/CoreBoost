<?php
/**
 * Autoloader for CoreBoost classes
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Autoloader
 */
class Autoloader {
    
    /**
     * Register autoloader
     */
    public static function register() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }
    
    /**
     * Autoload classes
     *
     * @param string $class_name The class name to load
     */
    public static function autoload($class_name) {
        // Only autoload CoreBoost classes
        if (strpos($class_name, 'CoreBoost\\') !== 0) {
            return;
        }
        
        // Remove namespace prefix
        $class_name = str_replace('CoreBoost\\', '', $class_name);
        
        // Convert namespace separators to directory separators
        $class_name = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
        
        // Convert class name to filename (e.g., Admin_Settings -> class-admin-settings.php)
        $class_parts = explode(DIRECTORY_SEPARATOR, $class_name);
        $class_file = array_pop($class_parts);
        
        // Convert directory names to lowercase and handle special mappings
        // PublicCore -> public, Admin -> admin, Core -> core
        $class_parts = array_map(function($part) {
            // Special case: PublicCore maps to 'public' directory
            if ($part === 'PublicCore') {
                return 'public';
            }
            return strtolower($part);
        }, $class_parts);
        
        // Handle special case for main CoreBoost class
        if ($class_file === 'CoreBoost') {
            $class_file = 'class-coreboost.php';
        } else {
            // Convert underscores to hyphens first (GTM_Settings -> GTM-Settings)
            $class_file = str_replace('_', '-', $class_file);
            
            // Convert CamelCase to kebab-case
            $class_file = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_file));
            $class_file = 'class-' . $class_file . '.php';
        }
        
        // Rebuild path
        $class_parts[] = $class_file;
        $file_path = COREBOOST_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $class_parts);
        
        // Load the file if it exists
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}
