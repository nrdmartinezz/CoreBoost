<?php
/**
 * GTM detection and conflict prevention
 *
 * @package CoreBoost
 * @since 2.0.0
 */

namespace CoreBoost\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GTM_Detector
 */
class GTM_Detector {
    
    /**
     * Detected GTM containers
     *
     * @var array
     */
    private static $detected_containers = null;
    
    /**
     * Check if GTM is already present on the site
     *
     * @return array Array with 'found' boolean and 'sources' array
     */
    public static function detect_existing_gtm() {
        if (self::$detected_containers !== null) {
            return self::$detected_containers;
        }
        
        $result = array(
            'found' => false,
            'containers' => array(),
            'sources' => array()
        );
        
        // Check for GTM plugins
        $plugin_checks = self::check_gtm_plugins();
        if (!empty($plugin_checks)) {
            $result['found'] = true;
            $result['sources'] = array_merge($result['sources'], $plugin_checks);
        }
        
        // Check theme files
        $theme_checks = self::check_theme_files();
        if (!empty($theme_checks)) {
            $result['found'] = true;
            $result['sources'] = array_merge($result['sources'], $theme_checks);
        }
        
        // Check output buffer (catches hardcoded GTM)
        $output_checks = self::check_output_buffer();
        if (!empty($output_checks)) {
            $result['found'] = true;
            $result['sources'] = array_merge($result['sources'], $output_checks);
            $result['containers'] = array_merge($result['containers'], $output_checks);
        }
        
        self::$detected_containers = $result;
        return $result;
    }
    
    /**
     * Check for GTM plugins
     *
     * @return array
     */
    private static function check_gtm_plugins() {
        $sources = array();
        
        // Google Tag Manager for WordPress (DuracellTomi)
        if (class_exists('GTM4WP')) {
            $gtm_id = get_option('gtm4wp-options');
            $container_id = isset($gtm_id['gtm-code']) ? $gtm_id['gtm-code'] : '';
            $sources[] = array(
                'type' => 'plugin',
                'name' => 'Google Tag Manager for WordPress',
                'container' => $container_id,
                'active' => true,
                'recommendation' => 'Deactivate this plugin before enabling CoreBoost GTM to prevent conflicts.'
            );
        }
        
        // Google Site Kit
        if (class_exists('Google\Site_Kit\Plugin')) {
            $sources[] = array(
                'type' => 'plugin',
                'name' => 'Google Site Kit',
                'container' => 'Unknown',
                'active' => true,
                'recommendation' => 'Site Kit may include GTM. Check your Site Kit settings.'
            );
        }
        
        // MonsterInsights (has GTM addon)
        if (class_exists('MonsterInsights')) {
            $sources[] = array(
                'type' => 'plugin',
                'name' => 'MonsterInsights',
                'container' => 'Unknown',
                'active' => true,
                'recommendation' => 'MonsterInsights may manage tracking. Verify no conflicts exist.'
            );
        }
        
        return $sources;
    }
    
    /**
     * Check theme files for GTM code
     *
     * @return array
     */
    private static function check_theme_files() {
        $sources = array();
        $theme = wp_get_theme();
        $theme_dir = get_template_directory();
        
        $files_to_check = array(
            'header.php',
            'functions.php',
            'footer.php'
        );
        
        foreach ($files_to_check as $file) {
            $file_path = $theme_dir . '/' . $file;
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                
                // Check for GTM script
                if (preg_match('/googletagmanager\.com\/gtm\.js\?id=(GTM-[A-Z0-9]+)/', $content, $matches)) {
                    $sources[] = array(
                        'type' => 'theme',
                        'name' => $theme->get('Name'),
                        'file' => $file,
                        'container' => $matches[1],
                        'recommendation' => 'Remove GTM code from ' . $file . ' before enabling CoreBoost GTM.'
                    );
                }
                
                // Check for GTM noscript
                if (preg_match('/googletagmanager\.com\/ns\.html\?id=(GTM-[A-Z0-9]+)/', $content, $matches)) {
                    // Only add if not already detected
                    $already_detected = false;
                    foreach ($sources as $source) {
                        if ($source['type'] === 'theme' && $source['container'] === $matches[1]) {
                            $already_detected = true;
                            break;
                        }
                    }
                    
                    if (!$already_detected) {
                        $sources[] = array(
                            'type' => 'theme',
                            'name' => $theme->get('Name'),
                            'file' => $file,
                            'container' => $matches[1],
                            'recommendation' => 'Remove GTM noscript from ' . $file . ' before enabling CoreBoost GTM.'
                        );
                    }
                }
            }
        }
        
        return $sources;
    }
    
    /**
     * Check output buffer for GTM
     *
     * @return array
     */
    private static function check_output_buffer() {
        $containers = array();
        
        // Hook into wp_head to capture output
        ob_start();
        do_action('wp_head');
        $head_content = ob_get_clean();
        
        // Find all GTM container IDs in head
        if (preg_match_all('/googletagmanager\.com\/gtm\.js\?id=(GTM-[A-Z0-9]+)/', $head_content, $matches)) {
            foreach ($matches[1] as $container_id) {
                if (!in_array($container_id, $containers)) {
                    $containers[] = $container_id;
                }
            }
        }
        
        return $containers;
    }
    
    /**
     * Get cached detection results
     *
     * @return array|null
     */
    public static function get_cached_detection() {
        return get_transient('coreboost_gtm_detection');
    }
    
    /**
     * Cache detection results
     *
     * @param array $results Detection results
     */
    public static function cache_detection($results) {
        set_transient('coreboost_gtm_detection', $results, HOUR_IN_SECONDS);
    }
    
    /**
     * Clear detection cache
     */
    public static function clear_detection_cache() {
        delete_transient('coreboost_gtm_detection');
    }
    
    /**
     * Check if CoreBoost should skip GTM output
     *
     * @param string $container_id CoreBoost GTM container ID
     * @return bool True if should skip, false if safe to output
     */
    public static function should_skip_gtm_output($container_id) {
        $detection = self::detect_existing_gtm();
        
        if (!$detection['found']) {
            return false; // No conflicts, safe to output
        }
        
        // Check if detected container matches our container
        foreach ($detection['containers'] as $detected_container) {
            if ($detected_container === $container_id) {
                return true; // Same container already exists, skip to prevent duplicate
            }
        }
        
        // Different containers found - still skip to prevent conflicts
        if (!empty($detection['sources'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if optimization should be skipped for GTM-related scripts
     *
     * @param string $handle Script handle
     * @param string $src Script source URL
     * @return bool
     */
    public static function is_gtm_script($handle, $src = '') {
        $gtm_patterns = array(
            'gtm',
            'google-tag-manager',
            'googletagmanager',
            'tag-manager'
        );
        
        // Check handle
        foreach ($gtm_patterns as $pattern) {
            if (stripos($handle, $pattern) !== false) {
                return true;
            }
        }
        
        // Check source URL
        if (!empty($src)) {
            if (stripos($src, 'googletagmanager.com') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate admin notice for detected conflicts
     *
     * @return string HTML for admin notice
     */
    public static function get_conflict_notice() {
        $detection = self::detect_existing_gtm();
        
        if (!$detection['found']) {
            return '';
        }
        
        $notice = '<div class="notice notice-warning is-dismissible">';
        $notice .= '<p><strong>CoreBoost GTM Warning:</strong> Existing Google Tag Manager implementation detected.</p>';
        $notice .= '<ul style="list-style: disc; margin-left: 20px;">';
        
        foreach ($detection['sources'] as $source) {
            $notice .= '<li>';
            $notice .= '<strong>' . esc_html($source['name']) . '</strong> (' . esc_html($source['type']) . ')';
            if (!empty($source['container'])) {
                $notice .= ' - Container: <code>' . esc_html($source['container']) . '</code>';
            }
            if (!empty($source['recommendation'])) {
                $notice .= '<br><em>' . esc_html($source['recommendation']) . '</em>';
            }
            $notice .= '</li>';
        }
        
        $notice .= '</ul>';
        $notice .= '<p><em>CoreBoost will not output GTM to prevent conflicts. Please resolve the above issues before enabling CoreBoost GTM Management.</em></p>';
        $notice .= '</div>';
        
        return $notice;
    }
}
