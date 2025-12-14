<?php
/**
 * Cache Consistency Checker
 *
 * Validates and repairs cache inconsistencies:
 * - Verifies filesystem variants match cache entries
 * - Detects orphaned cache entries (missing files)
 * - Detects orphaned files (missing cache entries)
 * - Repairs mismatches automatically
 * - Provides detailed diagnostic reports
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
 * Class Cache_Consistency_Checker
 *
 * Ensures cache and filesystem remain synchronized
 */
class Cache_Consistency_Checker {
    
    /**
     * Perform full consistency check
     *
     * Scans both cache and filesystem to detect inconsistencies.
     *
     * @param bool $auto_repair Automatically repair inconsistencies
     * @return array Diagnostic report
     */
    public static function check_consistency($auto_repair = false) {
        $report = array(
            'timestamp' => time(),
            'cache_entries' => 0,
            'filesystem_variants' => 0,
            'consistent' => 0,
            'orphaned_cache' => 0,
            'orphaned_files' => 0,
            'repaired' => 0,
            'errors' => array(),
        );
        
        // Get all cache entries
        $cache_entries = self::get_all_cache_entries();
        $report['cache_entries'] = count($cache_entries);
        
        // Get all filesystem variants
        $filesystem_variants = self::scan_filesystem_variants();
        $report['filesystem_variants'] = count($filesystem_variants);
        
        // Check cache entries against filesystem
        foreach ($cache_entries as $entry) {
            $image_url = $entry['url'];
            $formats = $entry['formats'];
            
            foreach ($formats as $format => $variant_url) {
                if ($variant_url === null) {
                    continue;
                }
                
                $variant_path = Path_Helper::url_to_path($variant_url);
                
                if (file_exists($variant_path)) {
                    $report['consistent']++;
                } else {
                    $report['orphaned_cache']++;
                    $report['errors'][] = "Cache entry without file: {$variant_url}";
                    
                    if ($auto_repair) {
                        // Remove from cache
                        Variant_Cache::delete_variants($image_url);
                        $report['repaired']++;
                    }
                }
            }
            
            // Check responsive variants
            if (isset($entry['responsive'])) {
                foreach ($entry['responsive'] as $format => $widths) {
                    foreach ($widths as $width => $variant_url) {
                        $variant_path = Path_Helper::url_to_path($variant_url);
                        
                        if (file_exists($variant_path)) {
                            $report['consistent']++;
                        } else {
                            $report['orphaned_cache']++;
                            $report['errors'][] = "Cache entry without file (responsive {$width}w): {$variant_url}";
                            
                            if ($auto_repair) {
                                Variant_Cache::delete_variants($image_url);
                                $report['repaired']++;
                            }
                        }
                    }
                }
            }
        }
        
        // Check filesystem variants against cache
        foreach ($filesystem_variants as $variant_data) {
            $original_url = $variant_data['original_url'];
            $format = $variant_data['format'];
            $width = $variant_data['width'];
            
            // Check if cached
            $cached = Variant_Cache::get_variant($original_url, $format, $width);
            
            if ($cached === null) {
                $report['orphaned_files']++;
                $report['errors'][] = "Filesystem variant without cache: {$variant_data['path']}";
                
                if ($auto_repair) {
                    // Add to cache
                    $variant_url = Path_Helper::path_to_url($variant_data['path']);
                    Variant_Cache::set_variant($original_url, $format, $variant_url, $width);
                    $report['repaired']++;
                }
            }
        }
        
        return $report;
    }
    
    /**
     * Get all cache entries
     *
     * Retrieves all variant cache entries from database.
     *
     * @return array Array of cache entries
     */
    private static function get_all_cache_entries() {
        global $wpdb;
        
        $chunks = $wpdb->get_results(
            "SELECT option_value FROM {$wpdb->options} 
             WHERE option_name LIKE 'coreboost_variant_cache_%'",
            \ARRAY_A
        );
        
        $entries = array();
        
        foreach ($chunks as $chunk) {
            $data = \maybe_unserialize($chunk['option_value']);
            if (is_array($data)) {
                foreach ($data as $image_hash => $image_data) {
                    $entries[] = array(
                        'url' => $image_data['url'] ?? '',
                        'formats' => array(
                            'avif' => $image_data['avif'] ?? null,
                            'webp' => $image_data['webp'] ?? null,
                        ),
                        'responsive' => $image_data['responsive'] ?? array(),
                    );
                }
            }
        }
        
        return $entries;
    }
    
    /**
     * Scan filesystem for all variant files
     *
     * @return array Array of variant file data
     */
    private static function scan_filesystem_variants() {
        $upload_dir = \wp_upload_dir();
        $variants_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants';
        
        if (!is_dir($variants_dir)) {
            return array();
        }
        
        $variants = array();
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($variants_dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            $path = $file->getPathname();
            $basename = $file->getBasename();
            $extension = strtolower($file->getExtension());
            
            // Only process AVIF/WebP variants
            if (!in_array($extension, array('avif', 'webp'))) {
                continue;
            }
            
            // Extract width if responsive variant
            $width = null;
            if (preg_match('/-(\d+)w\.' . $extension . '$/i', $basename, $matches)) {
                $width = (int)$matches[1];
            }
            
            // Try to determine original image URL
            $original_url = self::get_original_url_from_variant($path, $extension);
            
            $variants[] = array(
                'path' => $path,
                'format' => $extension,
                'width' => $width,
                'original_url' => $original_url,
            );
        }
        
        return $variants;
    }
    
    /**
     * Get original image URL from variant path
     *
     * @param string $variant_path Variant filesystem path
     * @param string $format Variant format
     * @return string|null Original image URL or null
     */
    private static function get_original_url_from_variant($variant_path, $format) {
        $upload_dir = \wp_upload_dir();
        $variants_base = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants' . DIRECTORY_SEPARATOR;
        
        // Get relative path from variants directory
        $relative = str_replace($variants_base, '', $variant_path);
        
        // Remove width descriptor if present
        $relative = preg_replace('/-\d+w\.' . $format . '$/', '', $relative);
        
        // Try original extensions
        $original_extensions = array('jpg', 'jpeg', 'png', 'gif');
        
        foreach ($original_extensions as $ext) {
            $original_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 
                           preg_replace('/\.' . $format . '$/', '.' . $ext, $relative);
            
            if (file_exists($original_path)) {
                return Path_Helper::path_to_url($original_path);
            }
        }
        
        return null;
    }
    
    /**
     * Generate consistency report
     *
     * Creates human-readable consistency report.
     *
     * @return string HTML formatted report
     */
    public static function generate_report() {
        $report = self::check_consistency(false);
        
        $html = '<div class="coreboost-consistency-report">';
        $html .= '<h3>Cache Consistency Report</h3>';
        $html .= '<p>Generated: ' . date('Y-m-d H:i:s', $report['timestamp']) . '</p>';
        
        $html .= '<table class="widefat">';
        $html .= '<tr><th>Metric</th><th>Value</th></tr>';
        $html .= '<tr><td>Cache Entries</td><td>' . $report['cache_entries'] . '</td></tr>';
        $html .= '<tr><td>Filesystem Variants</td><td>' . $report['filesystem_variants'] . '</td></tr>';
        $html .= '<tr><td>Consistent</td><td style="color: green;">' . $report['consistent'] . '</td></tr>';
        $html .= '<tr><td>Orphaned Cache Entries</td><td style="color: orange;">' . $report['orphaned_cache'] . '</td></tr>';
        $html .= '<tr><td>Orphaned Files</td><td style="color: orange;">' . $report['orphaned_files'] . '</td></tr>';
        $html .= '</table>';
        
        if (!empty($report['errors'])) {
            $html .= '<h4>Issues Found:</h4>';
            $html .= '<ul style="max-height: 400px; overflow-y: auto;">';
            foreach (array_slice($report['errors'], 0, 50) as $error) {
                $html .= '<li>' . esc_html($error) . '</li>';
            }
            if (count($report['errors']) > 50) {
                $html .= '<li><em>... and ' . (count($report['errors']) - 50) . ' more</em></li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p style="color: green; font-weight: bold;">✓ All variants are consistent!</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Schedule daily consistency checks
     */
    public static function init() {
        // Schedule daily consistency check
        if (!\wp_next_scheduled('coreboost_check_cache_consistency')) {
            \wp_schedule_event(time(), 'daily', 'coreboost_check_cache_consistency');
        }
        
        \add_action('coreboost_check_cache_consistency', array(__CLASS__, 'daily_check'));
    }
    
    /**
     * Daily automated consistency check
     */
    public static function daily_check() {
        $report = self::check_consistency(true); // Auto-repair enabled
        
        error_log("CoreBoost: Daily consistency check completed - " .
                 "Consistent: {$report['consistent']}, " .
                 "Orphaned cache: {$report['orphaned_cache']}, " .
                 "Orphaned files: {$report['orphaned_files']}, " .
                 "Repaired: {$report['repaired']}");
    }
}
