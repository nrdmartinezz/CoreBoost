<?php
/**
 * Compression Analytics Tracker
 *
 * Tracks image optimization metrics:
 * - Original vs compressed file sizes
 * - Format-specific compression ratios
 * - Storage space saved
 * - Cache hit rates
 * - Bandwidth savings estimates
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
 * Class Compression_Analytics
 *
 * Provides detailed analytics on image compression and caching performance
 */
class Compression_Analytics {
    
    /**
     * Database option key for analytics data
     */
    const ANALYTICS_OPTION = 'coreboost_compression_analytics';
    
    /**
     * Maximum analytics entries to keep
     */
    const MAX_ENTRIES = 1000;
    
    /**
     * Track variant generation
     *
     * Records size savings when a variant is generated.
     *
     * @param string $image_url Original image URL
     * @param string $format Variant format (avif, webp)
     * @param int $original_size Original file size in bytes
     * @param int $variant_size Variant file size in bytes
     * @param int|null $width Width descriptor for responsive variants
     */
    public static function track_variant_generation($image_url, $format, $original_size, $variant_size, $width = null) {
        $analytics = self::get_analytics();
        
        // Calculate compression ratio
        $saved_bytes = $original_size - $variant_size;
        $compression_ratio = $original_size > 0 ? ($variant_size / $original_size) * 100 : 0;
        
        // Store analytics entry
        $entry = array(
            'timestamp' => time(),
            'image_url' => $image_url,
            'format' => $format,
            'width' => $width,
            'original_size' => $original_size,
            'variant_size' => $variant_size,
            'saved_bytes' => $saved_bytes,
            'compression_ratio' => round($compression_ratio, 2),
        );
        
        // Add to analytics array
        $analytics['variants'][] = $entry;
        
        // Update aggregated stats
        if (!isset($analytics['aggregated'][$format])) {
            $analytics['aggregated'][$format] = array(
                'total_originals' => 0,
                'total_variants' => 0,
                'total_saved' => 0,
                'count' => 0,
            );
        }
        
        $analytics['aggregated'][$format]['total_originals'] += $original_size;
        $analytics['aggregated'][$format]['total_variants'] += $variant_size;
        $analytics['aggregated'][$format]['total_saved'] += $saved_bytes;
        $analytics['aggregated'][$format]['count']++;
        
        // Trim to max entries (keep most recent)
        if (count($analytics['variants']) > self::MAX_ENTRIES) {
            $analytics['variants'] = array_slice($analytics['variants'], -self::MAX_ENTRIES);
        }
        
        self::save_analytics($analytics);
    }
    
    /**
     * Track cache hit
     *
     * Records when a cached variant is served.
     *
     * @param string $image_url Original image URL
     * @param string $format Variant format
     * @param string $cache_layer Cache layer: runtime, persistent, filesystem
     */
    public static function track_cache_hit($image_url, $format, $cache_layer) {
        $analytics = self::get_analytics();
        
        // Initialize cache hits tracking
        if (!isset($analytics['cache_hits'])) {
            $analytics['cache_hits'] = array(
                'runtime' => 0,
                'persistent' => 0,
                'filesystem' => 0,
            );
        }
        
        if (isset($analytics['cache_hits'][$cache_layer])) {
            $analytics['cache_hits'][$cache_layer]++;
        }
        
        self::save_analytics($analytics);
    }
    
    /**
     * Get analytics data
     *
     * @return array Analytics data structure
     */
    public static function get_analytics() {
        $analytics = \get_option(self::ANALYTICS_OPTION, array());
        
        // Initialize structure if empty
        if (empty($analytics)) {
            $analytics = array(
                'variants' => array(),
                'aggregated' => array(),
                'cache_hits' => array(
                    'runtime' => 0,
                    'persistent' => 0,
                    'filesystem' => 0,
                ),
            );
        }
        
        return $analytics;
    }
    
    /**
     * Save analytics data
     *
     * @param array $analytics Analytics data
     */
    private static function save_analytics($analytics) {
        \update_option(self::ANALYTICS_OPTION, $analytics, false);
    }
    
    /**
     * Get aggregated statistics
     *
     * Returns human-readable summary of compression performance.
     *
     * @return array Aggregated statistics
     */
    public static function get_aggregated_stats() {
        $analytics = self::get_analytics();
        $aggregated = isset($analytics['aggregated']) ? $analytics['aggregated'] : array();
        
        $stats = array();
        
        foreach (array('avif', 'webp') as $format) {
            if (!isset($aggregated[$format])) {
                $stats[$format] = array(
                    'count' => 0,
                    'total_saved' => 0,
                    'total_saved_formatted' => '0 B',
                    'avg_compression' => 0,
                    'avg_compression_formatted' => '0%',
                );
                continue;
            }
            
            $data = $aggregated[$format];
            $avg_compression = $data['total_originals'] > 0 
                ? (($data['total_variants'] / $data['total_originals']) * 100) 
                : 0;
            
            $stats[$format] = array(
                'count' => $data['count'],
                'total_saved' => $data['total_saved'],
                'total_saved_formatted' => Path_Helper::format_bytes($data['total_saved']),
                'avg_compression' => round($avg_compression, 2),
                'avg_compression_formatted' => round($avg_compression, 1) . '%',
            );
        }
        
        // Calculate totals
        $total_saved = ($stats['avif']['total_saved'] ?? 0) + ($stats['webp']['total_saved'] ?? 0);
        $total_count = ($stats['avif']['count'] ?? 0) + ($stats['webp']['count'] ?? 0);
        
        $stats['total'] = array(
            'count' => $total_count,
            'total_saved' => $total_saved,
            'total_saved_formatted' => Path_Helper::format_bytes($total_saved),
        );
        
        // Cache hit statistics
        $cache_hits = isset($analytics['cache_hits']) ? $analytics['cache_hits'] : array();
        $total_hits = array_sum($cache_hits);
        
        $stats['cache'] = array(
            'runtime_hits' => $cache_hits['runtime'] ?? 0,
            'persistent_hits' => $cache_hits['persistent'] ?? 0,
            'filesystem_hits' => $cache_hits['filesystem'] ?? 0,
            'total_hits' => $total_hits,
            'hit_rate' => $total_hits > 0 ? round((($cache_hits['runtime'] ?? 0) + ($cache_hits['persistent'] ?? 0)) / $total_hits * 100, 1) : 0,
        );
        
        return $stats;
    }
    
    /**
     * Get recent conversions
     *
     * Returns most recent variant conversions with details.
     *
     * @param int $limit Number of entries to return
     * @return array Recent conversion entries
     */
    public static function get_recent_conversions($limit = 20) {
        $analytics = self::get_analytics();
        $variants = isset($analytics['variants']) ? $analytics['variants'] : array();
        
        // Get most recent entries
        $recent = array_slice($variants, -$limit);
        
        // Reverse to show newest first
        return array_reverse($recent);
    }
    
    /**
     * Get compression breakdown by format
     *
     * Returns detailed breakdown of compression performance per format.
     *
     * @return array Format breakdown statistics
     */
    public static function get_format_breakdown() {
        $analytics = self::get_analytics();
        $aggregated = isset($analytics['aggregated']) ? $analytics['aggregated'] : array();
        
        $breakdown = array();
        
        foreach ($aggregated as $format => $data) {
            $avg_original = $data['count'] > 0 ? ($data['total_originals'] / $data['count']) : 0;
            $avg_variant = $data['count'] > 0 ? ($data['total_variants'] / $data['count']) : 0;
            $avg_saved = $data['count'] > 0 ? ($data['total_saved'] / $data['count']) : 0;
            $avg_ratio = $avg_original > 0 ? (($avg_variant / $avg_original) * 100) : 0;
            
            $breakdown[$format] = array(
                'count' => $data['count'],
                'total_original_size' => $data['total_originals'],
                'total_variant_size' => $data['total_variants'],
                'total_saved' => $data['total_saved'],
                'avg_original_size' => round($avg_original),
                'avg_variant_size' => round($avg_variant),
                'avg_saved' => round($avg_saved),
                'avg_compression_ratio' => round($avg_ratio, 2),
                'total_saved_formatted' => Path_Helper::format_bytes($data['total_saved']),
                'avg_original_formatted' => Path_Helper::format_bytes($avg_original),
                'avg_variant_formatted' => Path_Helper::format_bytes($avg_variant),
                'avg_saved_formatted' => Path_Helper::format_bytes($avg_saved),
            );
        }
        
        return $breakdown;
    }
    
    /**
     * Clear analytics data
     *
     * Resets all analytics tracking.
     */
    public static function clear_analytics() {
        \\delete_option(self::ANALYTICS_OPTION);
        Context_Helper::debug_log("Compression analytics cleared");
    }
    
    /**
     * Export analytics data
     *
     * Returns analytics data in exportable format (CSV-ready).
     *
     * @return array Array of conversion entries
     */
    public static function export_data() {
        $analytics = self::get_analytics();
        $variants = isset($analytics['variants']) ? $analytics['variants'] : array();
        
        $export = array();
        
        foreach ($variants as $entry) {
            $export[] = array(
                'timestamp' => date('Y-m-d H:i:s', $entry['timestamp']),
                'image_url' => $entry['image_url'],
                'format' => $entry['format'],
                'width' => $entry['width'] ?? 'original',
                'original_size' => Path_Helper::format_bytes($entry['original_size']),
                'variant_size' => Path_Helper::format_bytes($entry['variant_size']),
                'saved_bytes' => Path_Helper::format_bytes($entry['saved_bytes']),
                'compression_ratio' => $entry['compression_ratio'] . '%',
            );
        }
        
        return $export;
    }
}
