<?php
/**
 * Unused resource removal and blocking
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
     * Image optimizer instance
     *
     * @var Image_Optimizer
     */
    private $image_optimizer;
    
    /**
     * Cached YouTube detection result for current request
     * Prevents multiple detections per page load
     *
     * @var bool|null
     */
    private static $youtube_detection_cache = null;
    
    /**
     * First YouTube video fallback URL for hero preload
     * Only the first (hero) video gets preloaded for LCP optimization
     *
     * @var string|null
     */
    private $youtube_fallback_preload_url = null;
    
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
            // Initialize image optimizer
            $this->image_optimizer = new Image_Optimizer($options, $loader);
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
     * Remove unused CSS files.
     * Supports the same pattern matching as CSS defer: exact, trailing-dash prefix,
     * wildcard (*), and partial (contains) — so handles entered here behave
     * identically to those in the Defer CSS field.
     */
    public function remove_unused_styles() {
        if (!$this->options['enable_unused_css_removal'] || empty($this->options['unused_css_list'])) {
            return;
        }

        $patterns = array_filter(array_map('trim', explode("\n", $this->options['unused_css_list'])));

        global $wp_styles;
        foreach (array_keys($wp_styles->registered) as $handle) {
            foreach ($patterns as $pattern) {
                if ($this->handle_matches_pattern($handle, $pattern)) {
                    wp_dequeue_style($handle);
                    wp_deregister_style($handle);
                    break;
                }
            }
        }
    }

    /**
     * Remove unused JavaScript files.
     * Supports the same pattern matching as JS defer: exact, trailing-dash prefix,
     * wildcard (*), and partial (contains).
     */
    public function remove_unused_scripts() {
        if (!$this->options['enable_unused_js_removal'] || empty($this->options['unused_js_list'])) {
            return;
        }

        $patterns = array_filter(array_map('trim', explode("\n", $this->options['unused_js_list'])));

        global $wp_scripts;
        foreach (array_keys($wp_scripts->registered) as $handle) {
            foreach ($patterns as $pattern) {
                if ($this->handle_matches_pattern($handle, $pattern)) {
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                    break;
                }
            }
        }
    }

    /**
     * Match a registered handle against a user-supplied pattern.
     * Rules (applied in order):
     *   1. Exact match — or exact match with a '-css' suffix appended.
     *   2. Trailing-dash prefix — e.g. "elementor-post-" matches "elementor-post-123".
     *   3. Wildcard — e.g. "elementor*frontend" (uses * as .* regex).
     *   4. Partial / contains — e.g. "swiper" matches "my-swiper-6".
     *
     * @param string $handle  The registered style/script handle.
     * @param string $pattern One line from the user's handle list.
     * @return bool
     */
    private function handle_matches_pattern($handle, $pattern) {
        if (empty($pattern)) {
            return false;
        }

        // 1. Exact match (with optional -css suffix tolerance)
        if ($handle === $pattern || $handle === $pattern . '-css') {
            return true;
        }

        // 2. Trailing-dash prefix: "widget-" matches "widget-recent-posts"
        if (substr($pattern, -1) === '-' && strpos($handle, rtrim($pattern, '-')) === 0) {
            return true;
        }

        // 3. Wildcard
        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
            return (bool) preg_match($regex, $handle);
        }

        // 4. Partial / contains
        return strpos($handle, $pattern) !== false;
    }
    
    /**
     * Block YouTube player resources from script tags.
     *
     * When smart_youtube_blocking is enabled we strip the YouTube IFrame API
     * script entirely from the initial HTML. The restoration script (injected
     * before </body>) dynamically injects the API only on first user interaction,
     * so PageSpeed/bots never trigger it and real users still get working videos.
     */
    public function block_youtube_resources($tag, $handle, $src) {
        // Check if this is a YouTube resource
        $is_youtube = (strpos($src, 'youtube.com') !== false || strpos($src, 'ytimg.com') !== false);

        if (!$is_youtube) {
            return $tag;
        }

        // Smart blocking: remove the YouTube IFrame API script from the page entirely.
        // The restoration script will inject it dynamically on first user interaction.
        if (isset($this->options['smart_youtube_blocking']) && $this->options['smart_youtube_blocking']) {
            if (strpos($src, 'youtube.com/iframe_api') !== false ||
                strpos($src, 'www.youtube.com/') !== false) {
                return '';
            }
        }

        // Legacy setting - block YouTube embed UI scripts independently
        if (isset($this->options['block_youtube_embed_ui']) && $this->options['block_youtube_embed_ui']) {
            if (strpos($src, 'youtube.com/yts/') !== false) {
                return '';
            }
        }

        return $tag;
    }
    
    /**
     * Block YouTube player resources from style tags
     * Note: smart_youtube_blocking does NOT block styles - it only defers iframe creation
     */
    public function block_youtube_style_resources($html, $handle, $href, $media) {
        // NOTE: smart_youtube_blocking does NOT block YouTube CSS
        // We only defer iframe creation, not block resources
        
        // Legacy setting - block YouTube player CSS independently (separate from smart blocking)
        if (isset($this->options['block_youtube_player_css']) && $this->options['block_youtube_player_css']) {
            if (strpos($href, 'www.youtube.com/s/player') !== false) {
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
        // Skip in admin, AJAX, or Elementor preview contexts
        if (Context_Helper::should_skip_optimization()) {
            return;
        }
        if ($this->options['enable_css_defer'] || $this->options['enable_script_defer'] ||
            $this->options['enable_inline_script_removal'] || $this->options['enable_inline_style_removal'] ||
            (isset($this->options['smart_youtube_blocking']) && $this->options['smart_youtube_blocking']) ||
            !empty($this->options['enable_lcp_foreground_injection'])) {
            ob_start(array($this, 'process_inline_assets'));
        }
    }
    
    /**
     * Process inline CSS and scripts in HTML output
     */
    public function process_inline_assets($html) {
        // Skip in admin, AJAX, or Elementor preview contexts
        if (Context_Helper::should_skip_optimization()) {
            return $html;
        }
        
        // Remove YouTube background video iframes if smart blocking enabled
        if (isset($this->options['smart_youtube_blocking']) && $this->options['smart_youtube_blocking']) {
            $html = $this->remove_youtube_background_iframes($html);
        }

        // Inject LCP foreground image for elements marked with the cb-lcp class
        if (!empty($this->options['enable_lcp_foreground_injection'])) {
            $html = $this->inject_lcp_foreground_image($html);
        }
        
        // Remove inline scripts and styles by ID first
        $html = $this->remove_inline_scripts_by_id($html);
        $html = $this->remove_inline_styles_by_id($html);
        
        // Process CSS
        if ($this->options['enable_css_defer']) {
            $css_pattern = '/<link\s+([^>]*\s+)?rel=["\']stylesheet["\']([^>]*\s+)?href=["\']([^"\'\']+)["\']([^>]*)>/i';
            $html = preg_replace_callback($css_pattern, array($this, 'process_inline_css_callback'), $html);
        }
        
        // Process Scripts - both with src and without closing tag
        if ($this->options['enable_script_defer']) {
            // Match script tags with src attribute (handles various quote styles and attributes)
            $script_pattern = '/<script\s+([^>]*?)src=["\']([^\'\"]+?)["\']([^>]*)>/i';
            $html = preg_replace_callback($script_pattern, array($this, 'process_inline_script_callback'), $html);
            
            // Also process external scripts by URL pattern matching for non-WordPress registered scripts
            $html = $this->defer_scripts_by_url($html);
        }
        
        // Extract hero preload (marker-based only)
        if (!empty($this->options['enable_hero_preload_extraction'])) {
            $html = $this->extract_hero_preload_from_buffer($html);
        }
        
        // Optimize images (lazy loading, width/height, aspect ratio)
        if (!empty($this->options['enable_image_optimization']) && isset($this->image_optimizer)) {
            Context_Helper::debug_log("Before image optimization - HTML contains: " . (strpos($html, '1000_F_507464080') !== false ? 'YES' : 'NO'));
            
            $html = $this->image_optimizer->optimize_images($html);
            
            Context_Helper::debug_log("After image optimization - HTML contains <picture>: " . (strpos($html, '<picture>') !== false ? 'YES' : 'NO'));
            Context_Helper::debug_log("After image optimization - HTML contains coreboost-variants: " . (strpos($html, 'coreboost-variants') !== false ? 'YES' : 'NO'));
            // Show a sample of the modified HTML around one image
            if (preg_match('/(<picture>.*?1000_F_507464080.*?<\/picture>)/s', $html, $matches)) {
                Context_Helper::debug_log("Full picture tag found: " . substr($matches[0], 0, 300));
            } else if (preg_match('/(<img[^>]*1000_F_507464080[^>]*>)/s', $html, $matches)) {
                Context_Helper::debug_log("Only img tag found (no picture wrapper): " . substr($matches[0], 0, 300));
            }
        }
        
        return $html;
    }
    
    /**
     * Scan elements marked with the cb-lcp CSS class, extract the background image URL
     * via a 4-level cascade, and inject a <link rel="preload" fetchpriority="high"> into
     * <head>. No <img> element is injected — the preload alone fixes the PSI
     * "resource load delay" metric by ensuring the browser's preload scanner discovers
     * the image URL during initial HTML parsing, before any CSS is downloaded.
     *
     * Elementor's video fallback is always present as an inline style="background: url(...)"
     * rendered by PHP — it paints without JS, so a native <img> is not needed.
     *
     * @param string $html Full page HTML.
     * @return string Modified HTML.
     */
    private function inject_lcp_foreground_image($html) {
        $preload_url       = null;
        $elementor_meta_url = null; // Lazy cache for Level 4 _elementor_data post meta read

        // Simpler outer regex — no subcapture groups.
        // Matches any opening tag that contains "cb-lcp" anywhere in its attributes.
        // All attribute extraction runs on $matches[0] (the full tag string) so no
        // attribute is ever outside the search scope due to PCRE backtracking.
        $html = preg_replace_callback(
            '/<[a-z][a-z0-9]*\s[^>]*\bcb-lcp\b[^>]*>/i',
            function($matches) use (&$preload_url, &$elementor_meta_url) {
                $full_tag  = $matches[0];
                $image_url = null;

                // Level 1: data-settings JSON — present when smart_youtube_blocking is OFF
                // (background_video_fallback has not yet been stripped from this attribute).
                if (preg_match('/\bdata-settings\s*=\s*"([^"]+)"/', $full_tag, $ds)) {
                    $settings = json_decode(html_entity_decode($ds[1], ENT_QUOTES | ENT_HTML5), true);
                    if (is_array($settings)) {
                        if (!empty($settings['background_video_fallback']['url'])) {
                            $image_url = $settings['background_video_fallback']['url'];
                        } elseif (!empty($settings['background_image']['url'])) {
                            $image_url = $settings['background_image']['url'];
                        }
                    }
                }

                // Level 2: data-coreboost-deferred-youtube — present when smart_youtube_blocking
                // IS on and has already moved background_video_fallback out of data-settings.
                if (!$image_url && preg_match('/\bdata-coreboost-deferred-youtube\s*=\s*"([^"]+)"/', $full_tag, $dyt)) {
                    $deferred = json_decode(html_entity_decode($dyt[1], ENT_QUOTES | ENT_HTML5), true);
                    if (is_array($deferred)) {
                        if (is_array($deferred['fallback']) && !empty($deferred['fallback']['url'])) {
                            $image_url = $deferred['fallback']['url'];
                        } elseif (is_string($deferred['fallback']) && !empty($deferred['fallback'])) {
                            $image_url = $deferred['fallback'];
                        }
                    }
                }

                // Level 3: inline style attribute.
                // Per Elementor's architecture the video fallback image is applied as a CSS
                // background shorthand directly on the section/container wrapper element:
                //   style="background: url('...') 50% 50%; background-size: cover;"
                // Present when the inline style is rendered server-side by PHP.
                if (!$image_url && preg_match('/\bstyle\s*=\s*"([^"]+)"/', $full_tag, $st)) {
                    if (preg_match('/\bbackground(?:-image)?\s*:[^;]*url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/', $st[1], $bg)) {
                        $image_url = $bg[1];
                    }
                }

                // Level 4: _elementor_data post meta (Method A from research doc).
                // Final fallback when Levels 1–3 all miss — e.g. when the inline style is
                // applied by Elementor JS rather than server-side PHP. Reads the page's
                // Elementor JSON from the database and scans the first 5 top-level elements
                // for background_video_fallback.url. Result is cached in $elementor_meta_url
                // so the DB query only runs once even if multiple cb-lcp elements are present.
                if (!$image_url && defined('ELEMENTOR_VERSION')) {
                    global $post;
                    if ($post && $elementor_meta_url === null) {
                        $elementor_meta_url = false; // Sentinel — prevents re-querying on next element
                        $raw = get_post_meta($post->ID, '_elementor_data', true);
                        if ($raw) {
                            $meta_elements = json_decode($raw, true);
                            if (is_array($meta_elements)) {
                                foreach (array_slice($meta_elements, 0, 5) as $el) {
                                    if (!empty($el['settings']['background_video_fallback']['url'])) {
                                        $elementor_meta_url = $el['settings']['background_video_fallback']['url'];
                                        break;
                                    }
                                    if (!empty($el['settings']['background_image']['url'])) {
                                        $elementor_meta_url = $el['settings']['background_image']['url'];
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    if ($elementor_meta_url) {
                        $image_url = $elementor_meta_url;
                    }
                }

                if (!$image_url) {
                    return $full_tag;
                }

                // Capture the first resolved URL so we can emit a <link rel="preload">
                // into <head> below. The tag itself is not modified — no <img> injected.
                if ($preload_url === null) {
                    $preload_url = $image_url;
                }

                return $full_tag;
            },
            $html
        );

        // Emit a <link rel="preload"> into <head> for the resolved LCP image so the
        // browser discovers it during head parsing. Skip if Hero_Optimizer already
        // emitted a preload tag for this URL during wp_head (prevents duplicate).
        if ($preload_url && !Hero_Optimizer::is_url_preloaded($preload_url)) {
            $preload_tag = '<link rel="preload" href="' . esc_url($preload_url) . '" as="image" fetchpriority="high">' . "\n";
            $html = str_replace('</head>', $preload_tag . '</head>', $html);
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
        $self = $this; // Capture $this for closure to modify class property
        $html = preg_replace_callback(
            '/data-settings=(["\'])([^"\']+)\1/i',
            function($matches) use ($self) {
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
                        
                        // Store the video URL and ALL related settings for later restoration
                        $video_data = array(
                            'url' => $video_url,
                            'play_on_mobile' => isset($settings['background_play_on_mobile']) ? $settings['background_play_on_mobile'] : '',
                            'play_once' => isset($settings['background_play_once']) ? $settings['background_play_once'] : '',
                            'fallback' => isset($settings['background_video_fallback']) ? $settings['background_video_fallback'] : ''
                        );
                        
                        // Capture first fallback URL for hero preload (LCP optimization)
                        if ($self->youtube_fallback_preload_url === null && isset($settings['background_video_fallback'])) {
                            $fallback = $settings['background_video_fallback'];
                            // Elementor stores fallback as array with 'url' and 'id' keys
                            if (is_array($fallback) && isset($fallback['url']) && !empty($fallback['url'])) {
                                $self->youtube_fallback_preload_url = $fallback['url'];
                            } elseif (is_string($fallback) && !empty($fallback)) {
                                $self->youtube_fallback_preload_url = $fallback;
                            }
                        }
                        
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
        
        // Inject preload link for hero video fallback image (LCP optimization)
        // Skip if the Hero_Optimizer already emitted this tag during wp_head (prevents duplicate).
        if ($this->youtube_fallback_preload_url &&
            !Hero_Optimizer::is_url_preloaded($this->youtube_fallback_preload_url)) {
            $preload_url = $this->youtube_fallback_preload_url;

            $preload_tag = '<link rel="preload" href="' . esc_url($preload_url) . '" as="image" fetchpriority="high">' . "\n";
            $html = str_replace('</head>', $preload_tag . '</head>', $html);

            if (!empty($this->options['debug_mode'])) {
                Context_Helper::debug_log('YouTube fallback preload injected via output buffer: ' . $preload_url);
            }
        }
        
        // Add inline script to restore video backgrounds after page load
        // This triggers Elementor's video handler to recreate iframes after critical resources are loaded
        $script = <<<'SCRIPT'
<script>
(function() {
    var DEBUG = false; // Set to true to see restoration logs
    var triggered = false;

    function log() {
        if (DEBUG) console.log.apply(console, ['[CoreBoost YouTube]'].concat(Array.prototype.slice.call(arguments)));
    }

    // Wait for Elementor to load and be ready
    if (window.elementorFrontend && typeof window.elementorFrontend.isEditMode === 'function' && window.elementorFrontend.isEditMode()) {
        log('In editor mode, skipping video deferral');
        return;
    }

    // Wait for Elementor frontend to be fully initialized before restoring videos
    function waitForElementor(callback, maxWait) {
        var waited = 0;
        var interval = 100;
        var check = setInterval(function() {
            waited += interval;
            if (window.elementorFrontend && window.elementorFrontend.elementsHandler) {
                clearInterval(check);
                log('Elementor ready after', waited, 'ms');
                callback();
            } else if (waited >= maxWait) {
                clearInterval(check);
                log('Elementor not ready after', maxWait, 'ms, attempting restoration anyway');
                callback();
            }
        }, interval);
    }

    // Dynamically inject the YouTube IFrame API then restore deferred videos.
    // Only fires once, only on real user interaction — bots/PageSpeed never trigger this.
    function onUserInteraction() {
        if (triggered) return;
        triggered = true;

        // Remove listeners immediately so they don't fire again
        var events = ['scroll', 'click', 'touchstart', 'keydown', 'mousemove'];
        events.forEach(function(evt) {
            document.removeEventListener(evt, onUserInteraction, { passive: true });
        });

        log('User interaction detected, loading YouTube API');

        // Inject the IFrame API script dynamically
        var apiScript = document.createElement('script');
        apiScript.src = 'https://www.youtube.com/iframe_api';
        apiScript.async = true;
        apiScript.onload = function() {
            log('YouTube API loaded, restoring videos');
            waitForElementor(restoreYouTubeDeferredVideos, 5000);
        };
        // Fallback: restore even if API fails to load
        apiScript.onerror = function() {
            log('YouTube API failed to load, attempting restoration anyway');
            waitForElementor(restoreYouTubeDeferredVideos, 5000);
        };
        document.head.appendChild(apiScript);
    }

    // Bind to first user interaction events
    var interactionEvents = ['scroll', 'click', 'touchstart', 'keydown', 'mousemove'];
    interactionEvents.forEach(function(evt) {
        document.addEventListener(evt, onUserInteraction, { passive: true, once: true });
    });
    
    function restoreYouTubeDeferredVideos() {
        var elements = document.querySelectorAll('[data-coreboost-deferred-youtube]');
        log('Found', elements.length, 'deferred YouTube elements');
        
        if (elements.length === 0) return;
        
        elements.forEach(function(element, index) {
            try {
                var deferredData = JSON.parse(element.getAttribute('data-coreboost-deferred-youtube'));
                if (!deferredData || !deferredData.url) {
                    log('Element', index, 'missing deferred data or URL');
                    return;
                }
                
                // Get current settings
                var settingsAttr = element.getAttribute('data-settings');
                if (!settingsAttr) {
                    log('Element', index, 'missing data-settings');
                    return;
                }
                
                var settings = JSON.parse(settingsAttr);
                log('Restoring video for element', index, ':', deferredData.url);
                
                // Restore ALL video settings including fallback
                settings.background_video_link = deferredData.url;
                if (deferredData.play_on_mobile) settings.background_play_on_mobile = deferredData.play_on_mobile;
                if (deferredData.play_once) settings.background_play_once = deferredData.play_once;
                if (deferredData.fallback) {
                    settings.background_video_fallback = deferredData.fallback;
                    log('Restored fallback image:', deferredData.fallback.url || deferredData.fallback);
                }
                
                // Update data-settings with restored video info
                element.setAttribute('data-settings', JSON.stringify(settings));
                
                // Remove deferred attribute
                element.removeAttribute('data-coreboost-deferred-youtube');
                
                // Get the element's model ID for Elementor
                var modelCid = element.getAttribute('data-model-cid');
                var $element = jQuery(element);
                var elementType = element.getAttribute('data-element_type');
                
                log('Element type:', elementType, 'Model CID:', modelCid);
                
                // Try multiple methods to reinitialize the video background
                var reinitialized = false;
                
                // Method 1: Direct YouTube iframe injection (most reliable)
                // This bypasses Elementor's handler system entirely
                try {
                    var videoId = extractYouTubeId(deferredData.url);
                    if (videoId) {
                        var bgVideoContainer = element.querySelector('.elementor-background-video-container');
                        if (!bgVideoContainer) {
                            // Create the container if it doesn't exist
                            bgVideoContainer = document.createElement('div');
                            bgVideoContainer.className = 'elementor-background-video-container';
                            var bgOverlay = element.querySelector('.elementor-background-overlay');
                            if (bgOverlay) {
                                bgOverlay.parentNode.insertBefore(bgVideoContainer, bgOverlay);
                            } else {
                                element.insertBefore(bgVideoContainer, element.firstChild);
                            }
                        }
                        
                        // Apply Elementor's background video container styles
                        bgVideoContainer.style.cssText = 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden; z-index: 0; pointer-events: none;';
                        
                        // Create YouTube iframe with proper parameters
                        var iframe = document.createElement('iframe');
                        iframe.className = 'elementor-background-video-embed';
                        iframe.setAttribute('frameborder', '0');
                        iframe.setAttribute('allowfullscreen', '1');
                        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
                        
                        // Calculate dimensions to cover container (16:9 aspect ratio for YouTube)
                        // This mimics Elementor's approach - scale iframe larger than container to eliminate black bars
                        var containerWidth = bgVideoContainer.offsetWidth || element.offsetWidth;
                        var containerHeight = bgVideoContainer.offsetHeight || element.offsetHeight;
                        var videoAspectRatio = 16 / 9;
                        var containerAspectRatio = containerWidth / containerHeight;
                        
                        var iframeWidth, iframeHeight;
                        if (containerAspectRatio > videoAspectRatio) {
                            // Container is wider than video - scale by width
                            iframeWidth = containerWidth;
                            iframeHeight = containerWidth / videoAspectRatio;
                        } else {
                            // Container is taller than video - scale by height
                            iframeHeight = containerHeight;
                            iframeWidth = containerHeight * videoAspectRatio;
                        }
                        
                        // Apply styles with calculated dimensions, centered via transform
                        iframe.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: ' + iframeWidth + 'px; height: ' + iframeHeight + 'px; pointer-events: none; border: 0;';
                        
                        // Build YouTube embed URL with autoplay, mute, loop settings
                        var embedUrl = 'https://www.youtube.com/embed/' + videoId + '?';
                        var params = [
                            'autoplay=1',
                            'controls=0',
                            'mute=1',
                            'loop=1',
                            'playlist=' + videoId,
                            'playsinline=1',
                            'rel=0',
                            'showinfo=0',
                            'modestbranding=1',
                            'enablejsapi=1',
                            'origin=' + window.location.origin
                        ];
                        iframe.src = embedUrl + params.join('&');
                        
                        // Clear existing content and add iframe
                        bgVideoContainer.innerHTML = '';
                        bgVideoContainer.appendChild(iframe);
                        
                        // Ensure parent section has proper positioning context
                        var computedStyle = window.getComputedStyle(element);
                        if (computedStyle.position === 'static') {
                            element.style.position = 'relative';
                        }
                        
                        log('Method 1 (Direct iframe injection) succeeded for element', index);
                        reinitialized = true;
                    }
                } catch (e) {
                    log('Method 1 failed:', e.message);
                }
                
                // Method 2: Use Elementor's elementsHandler to run ready triggers
                if (!reinitialized && window.elementorFrontend && window.elementorFrontend.elementsHandler) {
                    try {
                        if (typeof window.elementorFrontend.elementsHandler.runReadyTrigger === 'function') {
                            window.elementorFrontend.elementsHandler.runReadyTrigger($element);
                            log('Method 2 (runReadyTrigger) fired for element', index);
                        }
                    } catch (e) {
                        log('Method 2 failed:', e.message);
                    }
                }
                
                // Method 3: Fire Elementor hook actions
                if (window.elementorFrontend && window.elementorFrontend.hooks && window.elementorFrontend.hooks.doAction) {
                    try {
                        // Fire the element ready hook
                        window.elementorFrontend.hooks.doAction('frontend/element_ready/global', $element);
                        if (elementType) {
                            window.elementorFrontend.hooks.doAction('frontend/element_ready/' + elementType, $element);
                        }
                        log('Method 3 (hooks.doAction) fired for element', index);
                    } catch (e) {
                        log('Method 3 failed:', e.message);
                    }
                }
                
            } catch (e) {
                console.error('CoreBoost: Error restoring deferred YouTube video:', e);
            }
        });
        
        log('Video restoration complete');
    }
    
    // Helper function to extract YouTube video ID from various URL formats
    function extractYouTubeId(url) {
        if (!url) return null;
        var patterns = [
            /youtu\.be\/([a-zA-Z0-9_-]{11})/,
            /youtube\.com.*[?&]v=([a-zA-Z0-9_-]{11})/,
            /youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/,
            /youtube\.com\/v\/([a-zA-Z0-9_-]{11})/
        ];
        for (var i = 0; i < patterns.length; i++) {
            var match = url.match(patterns[i]);
            if (match && match[1]) return match[1];
        }
        return null;
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
                    $debug_comment = "<!-- CoreBoost: Inline script with ID: {$id} -->\n";
                } else {
                    $debug_comment = "<!-- CoreBoost: Inline script ID not found: {$id} -->\n";
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
                    $debug_comment = "<!-- CoreBoost: Inline style with ID: {$id} -->\n";
                } else {
                    $debug_comment = "<!-- CoreBoost: Inline style ID not found: {$id} -->\n";
                }
                $html = preg_replace('/(<head[^>]*>)/i', "$1\n" . $debug_comment, $html, 1);
            }
        }
        
        return $html;
    }
    
    /**
     * Callback for processing individual script tags
     */
    /**
     * Defer external scripts by URL pattern matching
     * Catches scripts injected by external systems (Cloudflare, themes) that don't use wp_enqueue_script
     *
     * @param string $html HTML content
     * @return string Modified HTML
     */
    private function defer_scripts_by_url($html) {
        // Get exclusions to check against
        $exclusions = $this->get_url_exclusions();
        $smart_youtube = !empty($this->options['smart_youtube_blocking']);

        // Pattern to catch external scripts not caught by main regex
        // Matches: <script ...src="..." ...> with various attribute orders
        $pattern = '/<script\s+([^>]*?)src=["\']([^\'\"]+?)["\']([^>]*)>/i';

        $html = preg_replace_callback($pattern, function($matches) use ($exclusions, $smart_youtube) {
            $full_tag = $matches[0];
            $before_src = $matches[1];
            $src = $matches[2];
            $after_src = $matches[3];

            // Strip YouTube scripts entirely when smart blocking is active
            if ($smart_youtube) {
                if (strpos($src, 'youtube.com/iframe_api') !== false ||
                    strpos($src, 'www.youtube.com/') !== false) {
                    return '';
                }
            }

            // Skip if already has defer or async
            if (strpos($full_tag, ' defer') !== false || strpos($full_tag, ' async') !== false) {
                return $full_tag;
            }
            
            // Check if this script should be deferred
            foreach ($exclusions['should_defer_async'] as $pattern) {
                if (stripos($src, $pattern) !== false) {
                    // Add async to break render chain for independent scripts
                    return str_replace('src=', 'async src=', $full_tag);
                }
            }
            
            foreach ($exclusions['should_defer'] as $pattern) {
                if (stripos($src, $pattern) !== false) {
                    // Add defer for potentially dependent scripts
                    return str_replace('src=', 'defer src=', $full_tag);
                }
            }
            
            // Check exclusions to skip
            foreach ($exclusions['skip'] as $pattern) {
                if (stripos($src, $pattern) !== false) {
                    return $full_tag;
                }
            }
            
            return $full_tag;
        }, $html);
        
        return $html;
    }
    
    /**
     * Get URL patterns for script deferring decisions
     *
     * @return array
     */
    private function get_url_exclusions() {
        return array(
            // Scripts that should use async (independent, break render chain)
            'should_defer_async' => array(
                'email-decode',           // Cloudflare email protection
                'hello-frontend',         // Hello Elementor theme
                // NOTE: YouTube scripts are stripped entirely when smart_youtube_blocking is on.
                // These patterns are only reached when that setting is disabled.
                'youtube.com/iframe_api', // YouTube API
                'www.youtube.com/',       // YouTube embeds
            ),
            // Scripts that should use defer (potentially dependent on others)
            'should_defer' => array(
                'elementor',
                'elementor-pro',
                'smartmenus',
            ),
            // Scripts to skip (critical path, must not defer)
            'skip' => array(
                'jquery.min.js',
                'jquery.js',
                'jquery-core',
                'jquery-migrate',
                'jquery-ui-core',
                'jquery-ui.min.js',
                'wp-embed',
                // WordPress core dist scripts — use full WP path so Elementor's own
                // dist/i18n.min.js (served under /plugins/elementor/) is NOT skipped.
                // wp-hooks, wp-i18n, wp-dom-ready are intentionally omitted here;
                // they are deferred by Script_Optimizer when enable_wp_core_defer is on.
                '/wp-includes/js/dist/url',
                '/wp-includes/js/dist/api-fetch',
                '/wp-includes/js/dist/data',
                '/wp-includes/js/dist/element',
                'wp-polyfill',
            )
        );
    }

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
        
        // Exclude critical WordPress core scripts that have inline "after" setup scripts
        // These MUST load synchronously OR be explicitly deferred by enable_wp_core_defer
        // (which injects a shim that safely queues dependent calls).
        // wp-hooks, wp-i18n, wp-dom-ready are intentionally left OUT here —
        // Script_Optimizer::defer_scripts() handles them when enable_wp_core_defer is on.
        $critical_wp_scripts = array(
            '/wp-includes/js/dist/url',            // wp-url
            '/wp-includes/js/dist/api-fetch',      // wp-api-fetch
            '/wp-includes/js/dist/data',           // wp-data
            '/wp-includes/js/dist/element',        // wp-element
            '/wp-includes/js/dist/components',     // wp-components
            '/wp-includes/js/dist/blocks',         // wp-blocks
            '/wp-includes/js/dist/editor',         // wp-editor
            '/wp-includes/js/dist/block-editor',   // wp-block-editor
            'wp-polyfill',                         // Polyfills
        );
        
        foreach ($critical_wp_scripts as $critical_script) {
            if (strpos($src, $critical_script) !== false) {
                return $full_tag;
            }
        }
        
        $should_defer = false;
        $use_async = false;

        // When smart YouTube blocking is active, strip YouTube scripts entirely from HTML.
        // The restoration JS will inject the IFrame API dynamically on first user interaction.
        if (isset($this->options['smart_youtube_blocking']) && $this->options['smart_youtube_blocking']) {
            if (strpos($src, 'youtube.com/iframe_api') !== false || strpos($src, 'www.youtube.com/') !== false) {
                return '';
            }
        }

        // Check for YouTube iframe API (independent - use async) when smart blocking is OFF
        if (strpos($src, 'youtube.com/iframe_api') !== false || strpos($src, 'www.youtube.com/') !== false) {
            $use_async = true;
            $should_defer = true;
        }
        // Elementor scripts (dependent - use defer)
        elseif (strpos($src, '/elementor/') !== false || strpos($src, '/elementor-pro/') !== false) {
            $should_defer = true;
        }
        // smartmenus (dependent - use defer)
        elseif (strpos($src, '/smartmenus/') !== false) {
            $should_defer = true;
        }
        // Other jQuery UI components (not core - can defer)
        elseif (strpos($src, '/jquery-ui/') !== false && strpos($src, 'core.min.js') === false) {
            $should_defer = true;
        }
        // WordPress core dist scripts - ONLY defer non-critical ones
        // Critical scripts (i18n, hooks, etc.) are excluded above
        elseif (strpos($src, '/wp-includes/js/dist/') !== false) {
            // Only defer these specific safe dist scripts
            $safe_to_defer = array(
                '/vendor/',           // Vendor bundles
                '/format-library',    // Format library
                '/nux',              // NUX (new user experience)
                '/notices',          // Notices
                '/viewport',         // Viewport
                '/a11y',             // Accessibility
            );
            foreach ($safe_to_defer as $safe_script) {
                if (strpos($src, $safe_script) !== false) {
                    $should_defer = true;
                    break;
                }
            }
        }
        // WooCommerce scripts
        elseif (strpos($src, '/woocommerce/') !== false) {
            $should_defer = true;
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
        }
        // Elementor patterns
        elseif (strpos($href, '/elementor/assets/css/') !== false) {
            $should_defer = true;
        }
        // WooCommerce patterns
        elseif (strpos($href, '/woocommerce/assets/css/') !== false) {
            $should_defer = true;
        }
        // Contact Form 7
        elseif (strpos($href, '/contact-form-7/') !== false) {
            $should_defer = true;
        }
        // Custom theme CSS files
        elseif (preg_match('/\/custom-[a-z0-9\-]+\.min\.css$/i', $href) || 
                preg_match('/\/custom-[a-z0-9\-]+\.css$/i', $href)) {
            $should_defer = true;
        }
        // Widget and animation CSS
        elseif (strpos($href, '/widget-') !== false || 
                strpos($href, '/fadeIn') !== false ||
                strpos($href, '/swiper') !== false) {
            $should_defer = true;
        }
        // Plugin CSS in uploads folder
        elseif (strpos($href, '/uploads/') !== false && strpos($href, '.css') !== false) {
            $should_defer = true;
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

    /**
     * Extract hero image URL from marked element in buffer and inject preload tag
     * Handles both direct background images and responsive/nested images
     *
     * @param string $html The HTML buffer content
     * @return string The HTML buffer with hero preload tag injected
     */
    private function extract_hero_preload_from_buffer($html) {
        global $post;
        
        if (!$post) {
            return $html;
        }
        
        $image_url = null;
        
        // PRIORITY: Check page-specific images configuration first
        $specific_pages = $this->parse_specific_pages();
        
        // Check for home page
        if (is_front_page() && isset($specific_pages['home'])) {
            $image_url = $specific_pages['home'];
        } 
        // Check for specific page by slug
        elseif (is_page() && isset($specific_pages[$post->post_name])) {
            $image_url = $specific_pages[$post->post_name];
        }
        
        // If we found an image URL from page-specific config, inject and return
        if (!empty($image_url)) {
            return $this->inject_hero_preload($html, $image_url);
        }
        
        // No page-specific image configured
        return $html;
    }
    
    /**
     * Parse page-specific images configuration from settings
     * Supports both slug-based keys (e.g. "home", "about") and full page URLs.
     * When a full URL is entered, it is normalised to a slug automatically.
     *
     * @return array Page-specific image URLs keyed by page slug
     */
    private function parse_specific_pages() {
        $specific_pages = array();

        if (empty($this->options['specific_pages'])) {
            return $specific_pages;
        }

        foreach (explode("\n", $this->options['specific_pages']) as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $parts = explode('|', $line, 2);
                if (count($parts) === 2) {
                    $page_slug = trim($parts[0]);
                    $image_url = trim($parts[1]);

                    // If the key looks like a full URL, normalise it to a slug.
                    if (filter_var($page_slug, FILTER_VALIDATE_URL)) {
                        $home_url = trailingslashit(home_url());
                        if (trailingslashit($page_slug) === $home_url) {
                            $page_slug = 'home';
                        } else {
                            $path      = trim(parse_url($page_slug, PHP_URL_PATH), '/');
                            $segments  = array_filter(explode('/', $path));
                            $page_slug = !empty($segments) ? end($segments) : 'home';
                        }
                    }

                    if (!empty($page_slug) && !empty($image_url)) {
                        $specific_pages[$page_slug] = $image_url;
                    }
                }
            }
        }

        return $specific_pages;
    }

    /**
     * Inject preload link tag for hero image before closing head tag
     *
     * @param string $html The HTML buffer content
     * @param string $image_url The image URL to preload
     * @return string The HTML buffer with preload tag injected
     */
    private function inject_hero_preload($html, $image_url) {
        // Check if we already have a preload for this URL to avoid duplicates
        if (strpos($html, 'preload" href="' . esc_url($image_url)) !== false) {
            return $html;
        }
        
        // Check cache first
        $post_id = get_the_ID();
        $cache_key = 'coreboost_hero_preload_' . (int) $post_id;
        $cached_preload = get_transient($cache_key);
        
        // If not in cache, create preload tag and cache it
        if ($cached_preload === false) {
            $preload_tag = '<link rel="preload" href="' . esc_url($image_url) . '" as="image" fetchpriority="high">' . "\n";
            
            // Get cache TTL from settings (default 30 days)
            $ttl = !empty($this->options['hero_preload_cache_ttl']) ? $this->options['hero_preload_cache_ttl'] : (30 * 24 * 60 * 60);
            
            set_transient($cache_key, $preload_tag, $ttl);
            $cached_preload = $preload_tag;
        }
        
        // Inject before closing head tag
        $html = str_replace('</head>', $cached_preload . '</head>', $html);
        
        return $html;
    }
}
