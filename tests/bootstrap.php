<?php
/**
 * PHPUnit Bootstrap File for CoreBoost
 * 
 * Sets up test environment without requiring full WordPress installation.
 * Provides minimal mocks for WordPress functions needed by tests.
 * 
 * @package CoreBoost
 * @subpackage Tests
 */

// Define test constants
define('COREBOOST_TESTING', true);
define('ABSPATH', dirname(__DIR__) . '/');
define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
define('COREBOOST_PLUGIN_DIR', dirname(__DIR__) . '/');

// Autoloader for CoreBoost classes
require_once dirname(__DIR__) . '/includes/class-autoloader.php';
\CoreBoost\Autoloader::register();

/**
 * Mock WordPress Functions
 * These are minimal implementations for testing purposes
 */

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null) {
        $upload_path = sys_get_temp_dir() . '/coreboost-test-uploads';
        return array(
            'path' => $upload_path,
            'url' => 'http://example.com/wp-content/uploads',
            'subdir' => '',
            'basedir' => $upload_path,
            'baseurl' => 'http://example.com/wp-content/uploads',
            'error' => false,
        );
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        if (file_exists($target)) {
            return @is_dir($target);
        }
        
        $target = str_replace('//', '/', $target);
        $target = rtrim($target, '/');
        
        if (empty($target)) {
            $target = '/';
        }
        
        if (@mkdir($target, 0755, true)) {
            return true;
        }
        
        return @is_dir($target);
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null) {
        return 'http://example.com' . $path;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return strip_tags($str);
    }
}

if (!function_exists('wp_check_filetype')) {
    function wp_check_filetype($filename, $mimes = null) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $types = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
        );
        
        foreach ($types as $exts => $mime) {
            if (preg_match('!(^|\\|)' . preg_quote($ext) . '($|\\|)!', $exts)) {
                return array(
                    'ext' => $ext,
                    'type' => $mime,
                    'proper_filename' => false,
                );
            }
        }
        
        return array(
            'ext' => false,
            'type' => false,
            'proper_filename' => false,
        );
    }
}

if (!function_exists('wp_tempnam')) {
    function wp_tempnam($filename = '', $dir = '') {
        if (empty($dir)) {
            $dir = sys_get_temp_dir();
        }
        return tempnam($dir, 'coreboost-test-');
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return true;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $error_message;
        
        public function __construct($code = '', $message = '') {
            $this->error_message = $message;
        }
        
        public function get_error_message() {
            return $this->error_message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// Ensure temp directory exists for tests
$temp_dir = sys_get_temp_dir() . '/coreboost-test-uploads';
if (!is_dir($temp_dir)) {
    wp_mkdir_p($temp_dir);
}

echo "CoreBoost Test Bootstrap Loaded\n";
echo "Test uploads directory: $temp_dir\n";
