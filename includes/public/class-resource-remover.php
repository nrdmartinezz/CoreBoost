<?php
/**
 * Unused resource removal and blocking
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Debug_Helper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Resource_Remover
 */
class Resource_Remover {
    
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
     * Cached YouTube detection result for current request
     * Prevents multiple detections per page load
     *
     * @var bool|null
     */
    private static $youtube_detection_cache = null;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        // Only register on frontend
        if (!is_admin()) {
            $this->define_hooks();
        }
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        $this->loader->add_action('wp_enqueue_scripts', $this, 'remove_unused_styles', 999);
        $this->loader->add_action('wp_enqueue_scripts', $this, 'remove_unused_scripts', 999);
        $this->loader->add_filter('script_loader_tag', $this, 'block_youtube_resources', 10, 3);
        $this->loader->add_filter('style_loader_tag', $this, 'block_youtube_style_resources', 10, 4);
        $this->loader->add_action('template_redirect', $this, 'start_output_buffer', 1);
    }
    
    /**
     * Remove unused CSS files
     */
    public function remove_unused_styles() {
        if (!$this->options['enable_unused_css_removal'] || empty($this->options['unused_css_list'])) {
            return;
        }
        
        $handles = array_filter(array_map('trim', explode("\n", $this->options['unused_css_list'])));
        
        if ($this->options['debug_mode'] && !empty($handles)) {
            Debug_Helper::comment("CoreBoost: Attempting to remove " . count($handles) . " CSS handle(s): " . implode(', ', $handles), $this->options['debug_mode']);
        }
        
        foreach ($handles as $handle) {
            if (wp_style_is($handle, 'enqueued') || wp_style_is($handle, 'registered')) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
                
                if ($this->options['debug_mode']) {
                    Debug_Helper::comment("✓ Removed unused CSS: {$handle}", $this->options['debug_mode']);
                }
            } else {
                if ($this->options['debug_mode']) {
                    Debug_Helper::comment("✗ CSS handle not found: {$handle} (not enqueued or registered)", $this->options['debug_mode']);
                }
            }
        }
    }
    
    /**
     * Remove unused JavaScript files
     */
    public function remove_unused_scripts() {
        if (!$this->options['enable_unused_js_removal'] || empty($this->options['unused_js_list'])) {
            return;
        }
        
        $handles = array_filter(array_map('trim', explode("\n", $this->options['unused_js_list'])));
        
        if ($this->options['debug_mode'] && !empty($handles)) {
            Debug_Helper::comment("CoreBoost: Attempting to remove " . count($handles) . " JS handle(s): " . implode(', ', $handles), $this->options['debug_mode']);
        }
        
        foreach ($handles as $handle) {
            if (wp_script_is($handle, 'enqueued') || wp_script_is($handle, 'registered')) {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
                
                if ($this->options['debug_mode']) {
                    Debug_Helper::comment("✓ Removed unused script: {$handle}", $this->options['debug_mode']);
                }
            } else {
                if ($this->options['debug_mode']) {
                    Debug_Helper::comment("✗ Script handle not found: {$handle} (not enqueued or registered)", $this->options['debug_mode']);
                }
            }
        }
    }
    
    /**
     * Block YouTube player resources from script tags
     */
    public function block_youtube_resources($tag, $handle, $src) {
        // Check if this is a YouTube resource
        $is_youtube = (strpos($src, 'youtube.com') !== false || strpos($src, 'ytimg.com') !== false);
        
        if (!$is_youtube) {
            return $tag;
        }
        
        // Smart YouTube blocking - block all YouTube scripts if background videos detected
        if (isset($this->options['smart_youtube_blocking']) && $this->options['smart_youtube_blocking']) {
            if ($this->should_block_youtube_resources()) {
                if ($this->options['debug_mode']) {
                    Debug_Helper::comment("Blocked YouTube script (background video detected): {$src}", $this->options['debug_mode']);
                    return "<!-- CoreBoost: Blocked YouTube script (background video detected) -->\n";
                }
                return '';
            }
        }
        
        // Legacy setting - block YouTube embed UI scripts independently
        if (isset($this->options['block_youtube_embed_ui']) && $this->options['block_youtube_embed_ui']) {
            if (strpos($src, 'youtube.com/yts/') !== false) {
                if ($this->options['debug_mode']) {
                    Debug_Helper::comment("Blocked YouTube embed UI script: {$src}", $this->options['debug_mode']);
                    return "<!-- CoreBoost: Blocked YouTube embed UI script -->\n";
                }
                return '';
            }
        }
        
        return $tag;
    }
    
    /**
     * Block YouTube player resources from style tags
     */
    public function block_youtube_style_resources($html, $handle, $href, $media) {
        // Smart YouTube blocking - only block if background videos detected
        if (isset($this->options['smart_youtube_blocking']) && $this->options['smart_youtube_blocking']) {
            if (!$this->should_block_youtube_resources()) {
                return $html;
            }
        }
        
        // Block YouTube player CSS
        if ($this->options['block_youtube_player_css'] && strpos($href, 'www.youtube.com/s/player') !== false) {
            if ($this->options['debug_mode']) {
                Debug_Helper::comment("Blocked YouTube player CSS: {$href}", $this->options['debug_mode']);
                return "<!-- CoreBoost: Blocked YouTube player CSS -->\n";
            }
            return '';
        }
        
        // Block YouTube CSS when smart blocking enabled and background videos detected
        if (isset($this->options['smart_youtube_blocking']) && $this->options['smart_youtube_blocking']) {
            if (strpos($href, 'youtube.com') !== false || strpos($href, 'ytimg.com') !== false) {
                if ($this->options['debug_mode']) {
                    Debug_Helper::comment("Blocked YouTube CSS (background video detected): {$href}", $this->options['debug_mode']);
                    return "<!-- CoreBoost: Blocked YouTube CSS (background video detected) -->\n";
                }
                return '';
            }
        }
        
        return $html;
    }
    
    /**
     * Check if YouTube resources should be blocked (smart blocking logic)
     * Uses request-level caching to prevent multiple detections per page load
     *
     * @return bool True if resources should be blocked
     */
    private function should_block_youtube_resources() {
        // Check static cache first (prevents multiple detections per request)
        if (self::$youtube_detection_cache !== null) {
            return self::$youtube_detection_cache;
        }
        
        // Default to not blocking
        self::$youtube_detection_cache = false;
        
        // Only detect if Elementor is active
        if (!defined('ELEMENTOR_VERSION')) {
            return self::$youtube_detection_cache;
        }
        
        // Get Hero_Optimizer from CoreBoost singleton (avoid creating new instance)
        if (class_exists('CoreBoost\CoreBoost')) {
            $coreboost = \CoreBoost\CoreBoost::get_instance();
            $hero_optimizer = $coreboost->get_hero_optimizer();
            
            if ($hero_optimizer) {
                $has_youtube_bg = $hero_optimizer->has_youtube_background_videos();
                
                if ($has_youtube_bg) {
                    if ($this->options['debug_mode']) {
                        Debug_Helper::comment("YouTube background video detected - blocking unnecessary resources", $this->options['debug_mode']);
                    }
                    self::$youtube_detection_cache = true;
                }
            }
        }
        
        return self::$youtube_detection_cache;
    }
    
    /**
     * Start output buffer to catch inline/hardcoded CSS and scripts
     */
    public function start_output_buffer() {
        if (is_admin()) {
            return;
        }
        if ($this->options['enable_css_defer'] || $this->options['enable_script_defer'] || 
            $this->options['enable_inline_script_removal'] || $this->options['enable_inline_style_removal'] ||
            (isset($this->options['smart_youtube_blocking']) && $this->options['smart_youtube_blocking'])) {
            ob_start(array($this, 'process_inline_assets'));
        }
    }
    
    /**
     * Process inline CSS and scripts in HTML output
     */
    public function process_inline_assets($html) {
        if (is_admin()) {
            return $html;
        }
        
        // Remove YouTube background video iframes if smart blocking enabled
        if (isset($this->options['smart_youtube_blocking']) && $this->options['smart_youtube_blocking']) {
            $html = $this->remove_youtube_background_iframes($html);
        }
        
        // Remove inline scripts and styles by ID first
        $html = $this->remove_inline_scripts_by_id($html);
        $html = $this->remove_inline_styles_by_id($html);
        
        // Process CSS
        if ($this->options['enable_css_defer']) {
            $css_pattern = '/<link\s+([^>]*\s+)?rel=["\']stylesheet["\']([^>]*\s+)?href=["\']([^"\'\']+)["\']([^>]*)>/i';
            $html = preg_replace_callback($css_pattern, array($this, 'process_inline_css_callback'), $html);
        }
        
        // Process Scripts
        if ($this->options['enable_script_defer']) {
            $script_pattern = '/<script([^>]*)src=["\']([^"\'\']+)["\']([^>]*)><\/script>/i';
            $html = preg_replace_callback($script_pattern, array($this, 'process_inline_script_callback'), $html);
        }
        
        return $html;
    }
    
    /**
     * Defer YouTube background video iframes from Elementor sections
     * Keeps the video background but loads it after page render to prevent blocking
     * 
     * @param string $html HTML content
     * @return string Modified HTML
     */
    private function remove_youtube_background_iframes($html) {
        if (!$this->should_block_youtube_resources()) {
            return $html;
        }
        
        // STRATEGY: Defer iframe creation by storing video URL in data attribute
        // Elementor will NOT create iframe if background_video_link is missing from data-settings
        // We'll use JavaScript to restore it after page load for deferred loading
        $html = preg_replace_callback(
            '/data-settings=(["\'])([^"\']+)\1/i',
            function($matches) {
                $quote = $matches[1];
                $encoded_json = $matches[2];
                
                // Decode HTML entities (Elementor encodes &quot; as &quot;, etc.)
                $json = html_entity_decode($encoded_json, ENT_QUOTES | ENT_HTML5);
                
                // Check if this contains background_video_link before parsing
                if (strpos($json, 'background_video_link') === false) {
                    return $matches[0];
                }
                
                // Decode the JSON settings
                $settings = json_decode($json, true);
                
                if (is_array($settings) && isset($settings['background_video_link'])) {
                    $video_url = $settings['background_video_link'];
                    
                    // Only defer if it's a YouTube video
                    if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                        
                        // Store the video URL in a separate data attribute for later restoration
                        $video_data = array(
                            'url' => $video_url,
                            'play_on_mobile' => isset($settings['background_play_on_mobile']) ? $settings['background_play_on_mobile'] : '',
                            'play_once' => isset($settings['background_play_once']) ? $settings['background_play_once'] : ''
                        );
                        
                        // Remove video settings to prevent immediate iframe creation
                        unset($settings['background_video_link']);
                        unset($settings['background_play_on_mobile']);
                        unset($settings['background_video_fallback']);
                        unset($settings['background_play_once']);
                        
                        // Keep background_background as 'video' so element has video styling
                        // Just without the actual iframe URL
                        
                        // Re-encode without the video URL
                        $new_json = json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        $new_encoded = htmlspecialchars($new_json, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
                        
                        // Encode deferred video data as data attribute
                        $video_json = json_encode($video_data);
                        $video_encoded = htmlspecialchars($video_json, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
                        
                        if ($this->options['debug_mode']) {
                            return 'data-settings=' . $quote . $new_encoded . $quote . ' data-coreboost-deferred-youtube="' . $video_encoded . '"';
                        }
                        return 'data-settings=' . $quote . $new_encoded . $quote . ' data-coreboost-deferred-youtube="' . $video_encoded . '"';
                    }
                }
                
                // Return original if not YouTube or can't parse
                return $matches[0];
            },
            $html
        );
        
        // Add inline script to restore video backgrounds after page load
        // This triggers Elementor's video handler to recreate iframes after critical resources are loaded
        $script = <<<'SCRIPT'
<script>
(function() {
    // Wait for Elementor to load and be ready
    if (window.elementorFrontend && typeof window.elementorFrontend.isEditMode === 'function') {
        // In editor mode, don't defer
        return;
    }
    
    // Defer video restoration until after page interactive
    if ('requestIdleCallback' in window) {
        requestIdleCallback(function() {
            restoreYouTubeDeferredVideos();
        }, { timeout: 5000 });
    } else {
        // Fallback: defer by 3 seconds
        setTimeout(restoreYouTubeDeferredVideos, 3000);
    }
    
    function restoreYouTubeDeferredVideos() {
        var elements = document.querySelectorAll('[data-coreboost-deferred-youtube]');
        elements.forEach(function(element) {
            try {
                var deferredData = JSON.parse(element.getAttribute('data-coreboost-deferred-youtube'));
                if (!deferredData || !deferredData.url) return;
                
                // Get current settings
                var settingsAttr = element.getAttribute('data-settings');
                if (!settingsAttr) return;
                
                var settings = JSON.parse(settingsAttr);
                
                // Restore video settings
                settings.background_video_link = deferredData.url;
                if (deferredData.play_on_mobile) settings.background_play_on_mobile = deferredData.play_on_mobile;
                if (deferredData.play_once) settings.background_play_once = deferredData.play_once;
                
                // Update data-settings
                element.setAttribute('data-settings', JSON.stringify(settings));
                
                // Remove deferred attribute
                element.removeAttribute('data-coreboost-deferred-youtube');
                
                // Trigger Elementor frontend to re-render this section
                if (window.elementorFrontend && window.elementorFrontend.hooks && window.elementorFrontend.hooks.doAction) {
                    window.elementorFrontend.hooks.doAction('elementor/frontend/element/render', element);
                }
            } catch (e) {
                console.error('CoreBoost: Error restoring deferred YouTube video:', e);
            }
        });
    }
})();
</script>
SCRIPT;
        
        // Add script before closing body tag if we found deferred videos
        if (strpos($html, 'data-coreboost-deferred-youtube') !== false) {
            $html = preg_replace('/<\/body>/i', $script . "\n</body>", $html, 1);
        }
        
        if ($this->options['debug_mode']) {
            // Add debug info at the beginning of body
            $debug_comment = "<!-- CoreBoost: Smart YouTube blocking active - video backgrounds deferred for non-blocking load -->\n";
            $html = preg_replace('/(<body[^>]*>)/i', "$1\n" . $debug_comment, $html, 1);
        }
        
        return $html;
    }
    
    /**
     * Remove inline scripts by ID attribute
     */
    private function remove_inline_scripts_by_id($html) {
        if (!$this->options['enable_inline_script_removal'] || empty($this->options['inline_script_ids'])) {
            return $html;
        }
        
        $ids = array_filter(array_map('trim', explode("\n", $this->options['inline_script_ids'])));
        
        if ($this->options['debug_mode'] && !empty($ids)) {
            $debug_comment = "<!-- CoreBoost: Attempting to remove " . count($ids) . " inline script(s) by ID: " . implode(', ', $ids) . " -->\n";
            $html = preg_replace('/(<head[^>]*>)/i', "$1\n" . $debug_comment, $html, 1);
        }
        
        foreach ($ids as $id) {
            $id_escaped = preg_quote($id, '/');
            
            // Match script tags with this ID and remove them along with their content
            $pattern = '/<script[^>]*\sid=["\']' . $id_escaped . '["\'][^>]*>.*?<\/script>\s*/is';
            $count = 0;
            $html = preg_replace($pattern, '', $html, -1, $count);
            
            if ($count === 0) {
                // Try alternate pattern where id is first attribute
                $pattern = '/<script\s+id=["\']' . $id_escaped . '["\'][^>]*>.*?<\/script>\s*/is';
                $html = preg_replace($pattern, '', $html, -1, $count);
            }
            
            if ($this->options['debug_mode']) {
                if ($count > 0) {
                    $debug_comment = "<!-- CoreBoost: ✓ Removed inline script with ID: {$id} -->\n";
                } else {
                    $debug_comment = "<!-- CoreBoost: ✗ Inline script ID not found: {$id} -->\n";
                }
                $html = preg_replace('/(<head[^>]*>)/i', "$1\n" . $debug_comment, $html, 1);
            }
        }
        
        return $html;
    }
    
    /**
     * Remove inline style tags by ID attribute
     */
    private function remove_inline_styles_by_id($html) {
        if (!$this->options['enable_inline_style_removal'] || empty($this->options['inline_style_ids'])) {
            return $html;
        }
        
        $ids = array_filter(array_map('trim', explode("\n", $this->options['inline_style_ids'])));
        
        if ($this->options['debug_mode'] && !empty($ids)) {
            $debug_comment = "<!-- CoreBoost: Attempting to remove " . count($ids) . " inline style(s) by ID: " . implode(', ', $ids) . " -->\n";
            $html = preg_replace('/(<head[^>]*>)/i', "$1\n" . $debug_comment, $html, 1);
        }
        
        foreach ($ids as $id) {
            $id_escaped = preg_quote($id, '/');
            
            // Match style tags with this ID and remove them along with their content
            $pattern = '/<style[^>]*\sid=["\']' . $id_escaped . '["\'][^>]*>.*?<\/style>\s*/is';
            $count = 0;
            $html = preg_replace($pattern, '', $html, -1, $count);
            
            if ($count === 0) {
                // Try alternate pattern where id is first attribute
                $pattern = '/<style\s+id=["\']' . $id_escaped . '["\'][^>]*>.*?<\/style>\s*/is';
                $html = preg_replace($pattern, '', $html, -1, $count);
            }
            
            if ($this->options['debug_mode']) {
                if ($count > 0) {
                    $debug_comment = "<!-- CoreBoost: ✓ Removed inline style with ID: {$id} -->\n";
                } else {
                    $debug_comment = "<!-- CoreBoost: ✗ Inline style ID not found: {$id} -->\n";
                }
                $html = preg_replace('/(<head[^>]*>)/i', "$1\n" . $debug_comment, $html, 1);
            }
        }
        
        return $html;
    }
    
    /**
     * Callback for processing individual script tags
     */
    private function process_inline_script_callback($matches) {
        $full_tag = $matches[0];
        $before_src = $matches[1];
        $src = $matches[2];
        $after_src = $matches[3];
        
        // Skip if already has defer or async
        if (strpos($full_tag, ' defer') !== false || strpos($full_tag, ' async') !== false) {
            return $full_tag;
        }
        
        // Exclude jQuery and jQuery UI core
        if (strpos($src, '/jquery/jquery.min.js') !== false || 
            strpos($src, '/jquery-migrate') !== false ||
            strpos($src, 'jquery.min.js') !== false ||
            strpos($src, 'jquery.js') !== false ||
            strpos($src, '/jquery-ui-core') !== false ||
            strpos($src, 'jquery-ui.min.js') !== false ||
            strpos($src, '/ui/core.min.js') !== false) {
            return $full_tag;
        }
        
        $should_defer = false;
        $use_async = false;
        
        // Check for YouTube iframe API (independent - use async)
        if (strpos($src, 'youtube.com/iframe_api') !== false || strpos($src, 'www.youtube.com/') !== false) {
            $use_async = true;
            $should_defer = true;
            Debug_Helper::comment('Using async for inline YouTube API: ' . basename($src), $this->options['debug_mode']);
        }
        // Elementor scripts (dependent - use defer)
        elseif (strpos($src, '/elementor/') !== false || strpos($src, '/elementor-pro/') !== false) {
            $should_defer = true;
            Debug_Helper::comment('Deferring inline Elementor script: ' . basename($src), $this->options['debug_mode']);
        }
        // smartmenus (dependent - use defer)
        elseif (strpos($src, '/smartmenus/') !== false) {
            $should_defer = true;
            Debug_Helper::comment('Deferring inline smartmenus script: ' . basename($src), $this->options['debug_mode']);
        }
        // Other jQuery UI components (not core - can defer)
        elseif (strpos($src, '/jquery-ui/') !== false && strpos($src, 'core.min.js') === false) {
            $should_defer = true;
            Debug_Helper::comment('Deferring inline jQuery UI component: ' . basename($src), $this->options['debug_mode']);
        }
        // WordPress core dist scripts
        elseif (strpos($src, '/wp-includes/js/dist/') !== false) {
            $should_defer = true;
            Debug_Helper::comment('Deferring inline WordPress script: ' . basename($src), $this->options['debug_mode']);
        }
        // WooCommerce scripts
        elseif (strpos($src, '/woocommerce/') !== false) {
            $should_defer = true;
            Debug_Helper::comment('Deferring inline WooCommerce script: ' . basename($src), $this->options['debug_mode']);
        }
        
        if (!$should_defer) {
            return $full_tag;
        }
        
        // Add async or defer attribute
        $attribute = $use_async ? ' async' : ' defer';
        return '<script' . $before_src . $attribute . ' src="' . $src . '"' . $after_src . '></script>';
    }
    
    /**
     * Callback for processing individual CSS link tags
     */
    private function process_inline_css_callback($matches) {
        $full_tag = $matches[0];
        $href = $matches[3];
        
        // Check if this CSS should be deferred based on URL patterns
        $should_defer = false;
        
        // Elementor Pro patterns
        if (strpos($href, '/elementor-pro/assets/css/') !== false) {
            $should_defer = true;
            Debug_Helper::comment("Deferring inline Elementor Pro CSS: {$href}", $this->options['debug_mode']);
        }
        // Elementor patterns
        elseif (strpos($href, '/elementor/assets/css/') !== false) {
            $should_defer = true;
            Debug_Helper::comment("Deferring inline Elementor CSS: {$href}", $this->options['debug_mode']);
        }
        // WooCommerce patterns
        elseif (strpos($href, '/woocommerce/assets/css/') !== false) {
            $should_defer = true;
            Debug_Helper::comment("Deferring inline WooCommerce CSS: {$href}", $this->options['debug_mode']);
        }
        // Contact Form 7
        elseif (strpos($href, '/contact-form-7/') !== false) {
            $should_defer = true;
            Debug_Helper::comment("Deferring inline Contact Form 7 CSS: {$href}", $this->options['debug_mode']);
        }
        // Custom theme CSS files
        elseif (preg_match('/\/custom-[a-z0-9\-]+\.min\.css$/i', $href) || 
                preg_match('/\/custom-[a-z0-9\-]+\.css$/i', $href)) {
            $should_defer = true;
            Debug_Helper::comment("Deferring inline Custom CSS: {$href}", $this->options['debug_mode']);
        }
        // Widget and animation CSS
        elseif (strpos($href, '/widget-') !== false || 
                strpos($href, '/fadeIn') !== false ||
                strpos($href, '/swiper') !== false) {
            $should_defer = true;
            Debug_Helper::comment("Deferring inline Widget/Animation CSS: {$href}", $this->options['debug_mode']);
        }
        // Plugin CSS in uploads folder
        elseif (strpos($href, '/uploads/') !== false && strpos($href, '.css') !== false) {
            $should_defer = true;
            Debug_Helper::comment("Deferring inline Uploaded CSS: {$href}", $this->options['debug_mode']);
        }
        
        if (!$should_defer) {
            return $full_tag;
        }
        
        // Check if already has an ID to avoid duplicates
        if (strpos($full_tag, 'id=') !== false) {
            preg_match('/id=["\']([^"\'\']+)["\']/', $full_tag, $id_match);
            $id = isset($id_match[1]) ? $id_match[1] : '';
            
            // Skip if already processed
            if (strpos($id, '-preload') !== false || strpos($id, '-noscript') !== false) {
                return $full_tag;
            }
        }
        
        // Generate a unique ID based on the filename
        $filename = basename($href, '.css');
        $unique_id = 'coreboost-inline-' . sanitize_key($filename);
        
        // Convert to preload method
        if ($this->options['css_defer_method'] === 'preload_with_critical') {
            $preload_html = '<link rel="preload" href="' . esc_url($href) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" id="' . esc_attr($unique_id) . '-preload">';
            $noscript_html = '<noscript><link rel="stylesheet" href="' . esc_url($href) . '" id="' . esc_attr($unique_id) . '-noscript"></noscript>';
            return $preload_html . "\n" . $noscript_html;
        } else {
            // Simple defer method
            $deferred_html = str_replace('rel="stylesheet"', 'rel="stylesheet" media="print" onload="this.media=\'all\'"', $full_tag);
            $deferred_html = str_replace("rel='stylesheet'", "rel='stylesheet' media='print' onload=\"this.media='all'\"", $deferred_html);
            return $deferred_html . '<noscript>' . $full_tag . '</noscript>';
        }
    }
}
