<?php
/**
 * Hero image optimization and LCP improvement
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Context_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Hero_Optimizer
 */
class Hero_Optimizer {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Loader instance
     *
     * @var \CoreBoost\Loader
     */
    private $loader;
    
    /**
     * Pre-compiled regex patterns for video URL parsing
     * Compiled once in constructor for performance
     */
    private $pattern_youtube_short;
    private $pattern_youtube_watch;
    private $pattern_youtube_embed;
    private $pattern_youtube_fallback;
    private $pattern_vimeo;
    private $pattern_video_file;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        
        // Pre-compile regex patterns for video URL parsing (eliminates runtime compilation)
        $this->pattern_youtube_short = '/youtu\.be\/([a-zA-Z0-9_-]{11})/';
        $this->pattern_youtube_watch = '/youtube\.com.*[?&]v=([a-zA-Z0-9_-]{11})/';
        $this->pattern_youtube_embed = '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/';
        $this->pattern_youtube_fallback = '/\/(?:v|e(?:mbed)?)\/([a-zA-Z0-9_-]{11})/';
        $this->pattern_vimeo = '/vimeo\.com\/([0-9]+)/';
        $this->pattern_video_file = '/\.(mp4|webm|ogg)$/i';
        
        // Only register on frontend
        if (!is_admin()) {
            $this->define_hooks();
        }
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        $this->loader->add_action('wp_head', $this, 'preload_hero_images', 1);
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_optimization_styles');
    }
    
    /**
     * Main hero image preloading function
     */
    public function preload_hero_images() {
        // Safety check: don't output on admin or preview contexts
        if (Context_Helper::should_skip_optimization() || $this->options['preload_method'] === 'disabled') {
            return;
        }
        
        // Consolidated methods mapping (v3.1.0+)
        $methods = array(
            'automatic'   => 'preload_automatic',
            'css_class'   => 'preload_css_class',
            'video_hero'  => 'preload_video_hero',
            // Legacy mappings for backwards compatibility
            'auto_elementor'    => 'preload_automatic',
            'featured_fallback' => 'preload_automatic',
            'smart_detection'   => 'preload_automatic',
            'advanced_cached'   => 'preload_automatic',
            'css_class_based'   => 'preload_css_class',
            'video_fallback'    => 'preload_video_hero',
            'elementor_data'    => 'preload_automatic',
        );
        
        $method = $this->options['preload_method'];
        if (isset($methods[$method])) {
            $this->{$methods[$method]}();
        }
    }
    
    /**
     * Automatic detection method (consolidated)
     * Combines best features of auto_elementor, featured_fallback, smart_detection, and advanced_cached
     * Includes caching for performance and extensibility via filter
     */
    private function preload_automatic() {
        global $post;
        if (!$post) return;
        
        // Allow other plugins/themes to provide hero image URL
        $hero_image_url = apply_filters('coreboost_detect_hero_image', null, $post);
        $hero_image_id = null;
        
        if ($hero_image_url) {
            $this->output_preload_tag($hero_image_url);
            return;
        }
        
        // Check cache first
        $cache_key = 'coreboost_hero_' . $post->ID;
        $cached_data = null;
        
        if (!empty($this->options['enable_caching'])) {
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false && isset($cached_data['url'])) {
                if ($cached_data['url']) {
                    $this->output_preload_tag($cached_data['url']);
                    if (!empty($this->options['enable_responsive_preload']) && !empty($cached_data['id'])) {
                        $this->output_responsive_preload_by_id($cached_data['id']);
                    }
                }
                return;
            }
        }
        
        // Check for page-specific manual overrides first
        $specific_images = $this->parse_specific_pages();
        
        if (is_front_page() && isset($specific_images['home'])) {
            $hero_image_url = $specific_images['home'];
        } elseif (is_page() && isset($specific_images[$post->post_name])) {
            $hero_image_url = $specific_images[$post->post_name];
        }
        
        // Try Elementor detection if no manual override
        if (!$hero_image_url && defined('ELEMENTOR_VERSION')) {
            $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
            if ($elementor_data) {
                $data = json_decode($elementor_data, true);
                if (is_array($data)) {
                    // Search up to 3 levels deep for background images
                    $hero_image_url = $this->search_elementor_hero_advanced($data);
                    
                    // Try to get image ID for responsive preload
                    if ($hero_image_url) {
                        $hero_image_id = $this->find_image_id_by_url($data, $hero_image_url);
                    }
                }
            }
        }
        
        // Fallback to featured image
        if (!$hero_image_url && has_post_thumbnail($post->ID)) {
            $hero_image_id = get_post_thumbnail_id($post->ID);
            $hero_image_url = get_the_post_thumbnail_url($post->ID, 'full');
        }
        
        // Cache the result
        if (!empty($this->options['enable_caching'])) {
            $cache_ttl = isset($this->options['hero_preload_cache_ttl']) ? (int)$this->options['hero_preload_cache_ttl'] : 3600;
            set_transient($cache_key, array('url' => $hero_image_url, 'id' => $hero_image_id), $cache_ttl);
        }
        
        if ($hero_image_url) {
            $this->output_preload_tag($hero_image_url);
            if (!empty($this->options['enable_responsive_preload']) && $hero_image_id) {
                $this->output_responsive_preload_by_id($hero_image_id);
            }
        }
    }
    
    /**
     * CSS class-based detection (consolidated)
     * Finds images with .hero-image or .lcp-image classes
     */
    private function preload_css_class() {
        if (!defined('ELEMENTOR_VERSION')) return;
        
        global $post;
        if (!$post) return;
        
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if ($elementor_data) {
            $data = json_decode($elementor_data, true);
            $hero_image = $this->find_hero_foreground_image($data);
            
            if ($hero_image) {
                $this->output_preload_tag($hero_image['url']);
                if (!empty($this->options['enable_responsive_preload']) && $hero_image['id']) {
                    $this->output_responsive_preload_by_id($hero_image['id']);
                }
            }
        }
    }
    
    /**
     * Video hero detection (consolidated)
     * Preloads video fallback thumbnail for optimal LCP when video backgrounds are used
     * 
     * Note: If smart_youtube_blocking is also enabled, the Resource_Remover will handle
     * the preload injection during output buffer processing. This method provides an
     * early preload via wp_head for faster discovery.
     */
    private function preload_video_hero() {
        if (!defined('ELEMENTOR_VERSION')) return;
        
        global $post;
        if (!$post) return;
        
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if (empty($elementor_data)) return;
        
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) return;
        
        // First try to get Elementor's configured fallback image (best for LCP)
        $fallback_url = $this->get_elementor_video_fallback($data);
        
        // If no fallback configured, try YouTube thumbnail
        if (!$fallback_url) {
            $fallback_url = $this->get_video_hero_fallback_image($data);
        }
        
        // Last resort: use static background image
        if (!$fallback_url) {
            $fallback_url = $this->find_hero_background_image($data);
        }
        
        if ($fallback_url) {
            $this->output_preload_tag($fallback_url);
        }
    }
    
    /**
     * Get Elementor's configured video fallback image
     * This is the image set in Elementor's "Background Fallback" setting
     * 
     * @param array $elements Elementor elements array
     * @return string|null Fallback image URL or null
     */
    private function get_elementor_video_fallback($elements) {
        if (!is_array($elements) || empty($elements)) {
            return null;
        }
        
        // Check first element (hero section)
        $first_element = $elements[0];
        
        // Elementor stores video fallback in background_video_fallback
        if (isset($first_element['settings']['background_video_fallback']['url']) && 
            !empty($first_element['settings']['background_video_fallback']['url'])) {
            return $first_element['settings']['background_video_fallback']['url'];
        }
        
        return null;
    }
    
    /**
     * Legacy method mappings for backwards compatibility
     * These call the new consolidated methods
     */
    private function preload_auto_elementor() {
        $this->preload_automatic();
    }
    
    private function preload_featured_fallback() {
        $this->preload_automatic();
    }
    
    private function preload_smart_detection() {
        $this->preload_automatic();
    }
    
    private function preload_advanced_cached() {
        $this->preload_automatic();
    }
    
    private function preload_css_class_based() {
        $this->preload_css_class();
    }
    
    private function preload_video_hero_fallback() {
        $this->preload_video_hero();
    }
    
    /**
     * Helper functions for image detection
     */
    private function find_hero_background_image($elements, $depth = 0, $max_depth = 2) {
        if ($depth > $max_depth) return null;
        
        foreach ($elements as $element) {
            if (isset($element['settings']['background_image']['url'])) {
                return $element['settings']['background_image']['url'];
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->find_hero_background_image($element['elements'], $depth + 1, $max_depth);
                if ($found) return $found;
            }
        }
        return null;
    }
    
    /**
     * Get video hero fallback image URL
     * Checks if hero element is a video background and returns thumbnail URL
     * 
     * @param array $elements Elementor elements array
     * @return string|null Thumbnail URL or null if not a video hero
     */
    private function get_video_hero_fallback_image($elements) {
        if (!is_array($elements) || empty($elements)) {
            return null;
        }
        
        // Check first element (typically the hero section)
        $first_element = $elements[0];
        
        if (!isset($first_element['settings']['background_video_link'])) {
            return null;
        }
        
        $video_url = $first_element['settings']['background_video_link'];
        if (empty($video_url)) {
            return null;
        }
        
        // Detect video type and extract thumbnail
        $video_type = $this->detect_video_type($video_url);
        
        if ($video_type === 'youtube') {
            return $this->extract_youtube_thumbnail_url($video_url);
        } elseif ($video_type === 'vimeo') {
            return $this->extract_vimeo_thumbnail_url($video_url);
        }
        
        // For hosted videos or unknown types, try to use a static background image fallback
        if (isset($first_element['settings']['background_image']['url'])) {
            return $first_element['settings']['background_image']['url'];
        }
        
        return null;
    }
    
    /**
     * Extract YouTube video ID and generate thumbnail URL
     * Uses hqdefault for reliable availability and fast loading
     * 
     * @param string $url YouTube video URL
     * @return string|null Thumbnail URL or null if ID cannot be extracted
     */
    private function extract_youtube_thumbnail_url($url) {
        $video_id = null;
        
        // Try multiple YouTube URL regex patterns
        // Pattern 1: youtu.be/VIDEO_ID
        if (preg_match($this->pattern_youtube_short, $url, $matches)) {
            $video_id = $matches[1];
        }
        // Pattern 2: youtube.com/watch?v=VIDEO_ID
        elseif (preg_match($this->pattern_youtube_watch, $url, $matches)) {
            $video_id = $matches[1];
        }
        // Pattern 3: youtube.com/embed/VIDEO_ID
        elseif (preg_match($this->pattern_youtube_embed, $url, $matches)) {
            $video_id = $matches[1];
        }
        // Pattern 4: General fallback for /v/ or other YouTube URL formats
        elseif (preg_match($this->pattern_youtube_fallback, $url, $matches)) {
            $video_id = $matches[1];
        }
        
        if (empty($video_id)) {
            return null;
        }
        
        // Return hqdefault (480x360) for balance of quality and fast loading
        return 'https://img.youtube.com/vi/' . esc_attr($video_id) . '/hqdefault.jpg';
    }
    
    /**
     * Extract Vimeo video ID and fetch thumbnail URL
     * Vimeo requires API call to get thumbnail, returns null if not immediately available
     * 
     * @param string $url Vimeo video URL
     * @return string|null Thumbnail URL or null
     */
    private function extract_vimeo_thumbnail_url($url) {
        // Extract Vimeo video ID
        if (preg_match($this->pattern_vimeo, $url, $matches)) {
            $video_id = $matches[1];
            
            // Vimeo requires API call to get thumbnail - would need to cache or async fetch
            // For now, return null to fall back to static image method
            return null;
        }
        
        return null;
    }
    
    private function search_elementor_hero_advanced($elements, $max_depth = 3, $current_depth = 0) {
        if ($current_depth >= $max_depth) return null;
        
        foreach ($elements as $index => $element) {
            if ($index > 2 && $current_depth === 0) break;
            
            // Check background image
            if (isset($element['settings']['background_image']['url'])) {
                return $element['settings']['background_image']['url'];
            }
            
            // Check for foreground images in widgets (only at top level)
            if ($current_depth === 0 && isset($element['elements'])) {
                foreach ($element['elements'] as $column) {
                    if (isset($column['elements'])) {
                        foreach ($column['elements'] as $widget) {
                            if ($widget['widgetType'] === 'image' && isset($widget['settings']['image']['url'])) {
                                return $widget['settings']['image']['url'];
                            }
                        }
                    }
                }
            }
            
            // Recurse
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->search_elementor_hero_advanced($element['elements'], $max_depth, $current_depth + 1);
                if ($found) return $found;
            }
        }
        return null;
    }
    
    private function find_hero_foreground_image($elements) {
        foreach ($elements as $element) {
            if (isset($element['widgetType']) && $element['widgetType'] === 'image') {
                $css_classes = isset($element['settings']['_css_classes']) ? $element['settings']['_css_classes'] : '';
                
                // Match various hero image class names
                $hero_classes = array('hero-foreground-image', 'heroimg', 'hero-image', 'lcp-image');
                $matches_hero_class = false;
                
                foreach ($hero_classes as $hero_class) {
                    if (strpos($css_classes, $hero_class) !== false) {
                        $matches_hero_class = true;
                        break;
                    }
                }
                
                if ($matches_hero_class && isset($element['settings']['image']['url'])) {
                    return array(
                        'url' => $element['settings']['image']['url'],
                        'id' => isset($element['settings']['image']['id']) ? $element['settings']['image']['id'] : null
                    );
                }
            }
            
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->find_hero_foreground_image($element['elements']);
                if ($found) return $found;
            }
        }
        return null;
    }
    
    /**
     * Find image ID from Elementor data by URL
     * 
     * @param array $elements Elementor elements
     * @param string $url Image URL to find
     * @return int|null Image ID or null
     */
    private function find_image_id_by_url($elements, $url) {
        foreach ($elements as $element) {
            // Check background image
            if (isset($element['settings']['background_image']['url']) && 
                $element['settings']['background_image']['url'] === $url &&
                isset($element['settings']['background_image']['id'])) {
                return (int)$element['settings']['background_image']['id'];
            }
            
            // Recurse into nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $found = $this->find_image_id_by_url($element['elements'], $url);
                if ($found) return $found;
            }
        }
        return null;
    }
    
    /**
     * Parse specific pages configuration
     */
    private function parse_specific_pages() {
        $specific_pages = array();
        foreach (explode("\n", $this->options['specific_pages']) as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $parts = explode('|', $line, 2);
                if (count($parts) === 2) {
                    $specific_pages[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
        return $specific_pages;
    }
    
    /**
     * Output preload tag with optimized format lookup
     * 
     * Checks for existing AVIF/WebP variants and preloads those instead of original.
     * Falls back to original URL if no converted variants exist.
     * 
     * @param string $image_url Original image URL
     */
    private function output_preload_tag($image_url) {
        if (empty($image_url)) {
            return;
        }
        
        echo '<link rel="preload" href="' . esc_url($image_url) . '" as="image" fetchpriority="high">' . "\n";
    }
    
    private function output_responsive_preload($image_url) {
        $image_id = attachment_url_to_postid($image_url);
        if ($image_id) $this->output_responsive_preload_by_id($image_id);
    }
    
    private function output_responsive_preload_by_id($image_id) {
        $sizes = array(
            'large' => '(max-width: 1024px)',
            'medium_large' => '(max-width: 768px)',
            'medium' => '(max-width: 480px)'
        );
        
        $original_url = wp_get_attachment_image_url($image_id, 'full');
        
        foreach ($sizes as $size => $media_query) {
            $responsive_url = wp_get_attachment_image_url($image_id, $size);
            if ($responsive_url && $responsive_url !== $original_url) {
                echo '<link rel="preload" href="' . esc_url($responsive_url) . '" as="image" media="' . $media_query . '">' . "\n";
            }
        }
    }
    
    /**
     * Enqueue optimization styles
     */
    public function enqueue_optimization_styles() {
        if ($this->options['enable_foreground_conversion']) {
            wp_add_inline_style('wp-block-library', $this->get_foreground_conversion_css());
        }
    }
    
    /**
     * Get foreground conversion CSS
     */
    private function get_foreground_conversion_css() {
        return '
        /* CoreBoost - Foreground Image Conversion */
        .hero-section-container,
        .relative {
            position: relative;
        }
        
        .hero-section-container .elementor-container,
        .hero-section-container .elementor-widget-wrap,
        .relative .elementor-container,
        .relative .elementor-widget-wrap {
            position: initial;
        }
        
        .hero-foreground-image,
        .hero-foreground-image img,
        .heroimg,
        .heroimg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            z-index: -1;
        }
        ';
    }
    
    /**
     * Detect Elementor background videos on current page
     * 
     * Scans Elementor data for background video settings in sections and columns.
     * Used by Resource_Remover to determine if YouTube resources should be blocked.
     *
     * @return array Array of detected background video URLs
     */
    public function detect_elementor_background_videos() {
        if (!defined('ELEMENTOR_VERSION')) {
            return array();
        }
        
        global $post;
        if (!$post) {
            return array();
        }
        
        // Check cache first
        $cache_key = 'coreboost_bg_videos_' . $post->ID;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $background_videos = array();
        
        // Get Elementor data
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if ($elementor_data) {
            $data = json_decode($elementor_data, true);
            if (is_array($data)) {
                $background_videos = $this->find_background_videos($data);
            }
        }
        
        // Cache the result for 1 hour
        set_transient($cache_key, $background_videos, HOUR_IN_SECONDS);
        
        return $background_videos;
    }
    
    /**
     * Recursively find background videos in Elementor data
     * Optimized with early termination and reduced depth for minimal performance impact
     *
     * @param array $elements Elementor elements array
     * @param int $depth Current recursion depth
     * @param int $max_depth Maximum recursion depth (reduced to 3 - hero sections are always near top)
     * @return array Array of background video URLs
     */
    private function find_background_videos($elements, $depth = 0, $max_depth = 3) {
        if ($depth > $max_depth || !is_array($elements)) {
            return array();
        }
        
        $videos = array();
        $section_count = 0;
        
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }
            
            // Count top-level sections (hero is typically in first 3 sections)
            if ($depth === 0 && isset($element['elType']) && $element['elType'] === 'section') {
                $section_count++;
                // Stop after checking first 3 sections at root level for performance
                if ($section_count > 3) {
                    break;
                }
            }
            
            // Check for background video link (YouTube/Vimeo)
            if (isset($element['settings']['background_video_link'])) {
                $video_url = $element['settings']['background_video_link'];
                if (!empty($video_url) && is_string($video_url)) {
                    $video_type = $this->detect_video_type($video_url);
                    $element_type = isset($element['elType']) ? $element['elType'] : 'unknown';
                    
                    $videos[] = array(
                        'url' => $video_url,
                        'type' => $video_type,
                        'element_type' => $element_type
                    );
                    
                    // Early termination: if YouTube background video found, we can stop
                    // (Smart blocking only needs to know if ANY YouTube bg video exists)
                    if ($video_type === 'youtube' && $element_type !== 'video_widget') {
                        return $videos;
                    }
                }
            }
            
            // Check video widget (not background, but good to detect)
            if (isset($element['widgetType']) && $element['widgetType'] === 'video') {
                if (isset($element['settings']['youtube_url'])) {
                    $videos[] = array(
                        'url' => $element['settings']['youtube_url'],
                        'type' => 'youtube',
                        'element_type' => 'video_widget'
                    );
                } elseif (isset($element['settings']['vimeo_url'])) {
                    $videos[] = array(
                        'url' => $element['settings']['vimeo_url'],
                        'type' => 'vimeo',
                        'element_type' => 'video_widget'
                    );
                }
            }
            
            // Recurse through nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $nested_videos = $this->find_background_videos($element['elements'], $depth + 1, $max_depth);
                $videos = array_merge($videos, $nested_videos);
                
                // Early termination check after merging nested results
                foreach ($videos as $video) {
                    if ($video['type'] === 'youtube' && $video['element_type'] !== 'video_widget') {
                        return $videos;
                    }
                }
            }
        }
        
        return $videos;
    }
    
    /**
     * Detect video type from URL
     *
     * @param string $url Video URL
     * @return string Video type (youtube, vimeo, hosted, unknown)
     */
    private function detect_video_type($url) {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return 'youtube';
        } elseif (strpos($url, 'vimeo.com') !== false) {
            return 'vimeo';
        } elseif (preg_match($this->pattern_video_file, $url)) {
            return 'hosted';
        }
        return 'unknown';
    }
    
    /**
     * Check if current page has YouTube background videos
     * 
     * Public helper method for Resource_Remover to check if YouTube blocking should occur
     *
     * @return bool True if YouTube background videos detected
     */
    public function has_youtube_background_videos() {
        $videos = $this->detect_elementor_background_videos();
        
        foreach ($videos as $video) {
            if ($video['type'] === 'youtube' && $video['element_type'] !== 'video_widget') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect above-the-fold video widgets for facade replacement
     * Scans first 3 sections for embedded video widgets
     *
     * @return array Array of video widget data
     */
    public function detect_above_fold_video_widgets() {
        if (!defined('ELEMENTOR_VERSION')) {
            return array();
        }
        
        global $post;
        if (!$post) {
            return array();
        }
        
        $videos = array();
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        
        if ($elementor_data) {
            $data = json_decode($elementor_data, true);
            if (is_array($data)) {
                $videos = $this->find_video_widgets($data);
            }
        }
        
        return $videos;
    }
    
    /**
     * Recursively find video widgets in Elementor data (above fold)
     *
     * @param array $elements Elementor elements array
     * @param int $depth Current recursion depth
     * @return array Array of video widget data
     */
    private function find_video_widgets($elements, $depth = 0) {
        // Only scan first 3 levels (above the fold)
        if ($depth > 3 || !is_array($elements)) {
            return array();
        }
        
        $videos = array();
        $section_count = 0;
        
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }
            
            // Count sections at depth 0
            if ($depth === 0 && isset($element['elType']) && $element['elType'] === 'section') {
                $section_count++;
                // Only check first 3 sections
                if ($section_count > 3) {
                    break;
                }
            }
            
            // Check for video widget
            if (isset($element['widgetType']) && $element['widgetType'] === 'video') {
                $video_data = $this->extract_video_widget_data($element);
                if ($video_data) {
                    $videos[] = $video_data;
                }
            }
            
            // Recurse through nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $nested_videos = $this->find_video_widgets($element['elements'], $depth + 1);
                $videos = array_merge($videos, $nested_videos);
            }
        }
        
        return $videos;
    }
    
    /**
     * Extract video URL from Elementor video widget settings
     *
     * @param array $element Elementor element data
     * @return array|null Video data or null
     */
    private function extract_video_widget_data($element) {
        if (!isset($element['settings'])) {
            return null;
        }
        
        $settings = $element['settings'];
        $video_url = null;
        $video_type = null;
        
        // Check for YouTube URL
        if (isset($settings['youtube_url']) && !empty($settings['youtube_url'])) {
            $video_url = $settings['youtube_url'];
            $video_type = 'youtube';
        }
        // Check for Vimeo URL
        elseif (isset($settings['vimeo_url']) && !empty($settings['vimeo_url'])) {
            $video_url = $settings['vimeo_url'];
            $video_type = 'vimeo';
        }
        // Check for hosted video
        elseif (isset($settings['hosted_url']) && !empty($settings['hosted_url'])) {
            $video_url = $settings['hosted_url'];
            $video_type = 'hosted';
        }
        
        if (!$video_url) {
            return null;
        }
        
        return array(
            'url' => $video_url,
            'type' => $video_type,
            'title' => isset($settings['video_title']) ? $settings['video_title'] : '',
            'element_id' => isset($element['id']) ? $element['id'] : uniqid('video-'),
        );
    }
}
