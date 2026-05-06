<?php
/**
 * Font optimization (Google Fonts and Adobe Fonts)
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
 * Class Font_Optimizer
 */
class Font_Optimizer {
    
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
     * Google Font URLs extracted from Elementor CSS @import statements during the current request.
     *
     * @var array
     */
    private $collected_font_urls = array();
    
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
        // Elementor CSS generation hook fires on admin saves and frontend regeneration
        $this->define_elementor_hooks();
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        $this->loader->add_filter('style_loader_tag', $this, 'optimize_font_loading', 10, 4);
        $this->loader->add_action('wp_head', $this, 'add_font_preconnects', 1);
        $this->loader->add_action('wp_head', $this, 'add_custom_preconnects', 1);
        $this->loader->add_action('wp_head', $this, 'output_local_font_preloads', 1);
        // Scan enqueued Elementor CSS files for embedded @import Google Font URLs,
        // patch them out of the file, and collect the URLs for non-blocking preloading.
        // Priority 1 runs before wp_print_styles (priority 8), so the file is already
        // patched when the browser fetches it.
        $this->loader->add_action('wp_head', $this, 'scan_and_patch_css_files', 1);
        // Output <link rel="preload"> for any extracted Google Font URLs (priority 2,
        // after scan fills $collected_font_urls at priority 1).
        $this->loader->add_action('wp_head', $this, 'output_extracted_font_preloads', 2);
        // Output buffer wrapping wp_head to inject font-display:swap into inline @font-face blocks
        // (covers Elementor custom fonts and theme fonts that never go through style_loader_tag)
        $this->loader->add_action('wp_head', $this, 'start_font_display_buffer', 0);
        $this->loader->add_action('wp_head', $this, 'end_font_display_buffer', 999);
    }

    /**
     * Register the Elementor CSS generation hook (fires on admin saves and frontend regeneration).
     */
    private function define_elementor_hooks() {
        if (empty($this->options['enable_font_optimization']) || empty($this->options['defer_google_fonts'])) {
            return;
        }
        $this->loader->add_filter('elementor/css/file_content', $this, 'strip_elementor_font_imports', 10, 1);
    }
    
    /**
     * Optimize font loading
     */
    public function optimize_font_loading($html, $handle, $href, $media) {
        if (!$this->options['enable_font_optimization'] || is_admin()) {
            return $html;
        }
        
        $is_font = false;
        
        // Check if this is a Google Font
        if ($this->options['defer_google_fonts'] && 
            (strpos($href, 'fonts.googleapis.com') !== false || strpos($href, 'fonts.gstatic.com') !== false)) {
            $is_font = true;
        }
        
        // Check if this is an Adobe Font
        if ($this->options['defer_adobe_fonts'] && 
            (strpos($href, 'use.typekit.net') !== false || strpos($href, 'fonts.adobe.com') !== false)) {
            $is_font = true;
        }
        
        if (!$is_font) {
            return $html;
        }
        
        // Add display=swap to font URLs if not present
        if ($this->options['font_display_swap'] && strpos($href, 'display=') === false) {
            $href = add_query_arg('display', 'swap', $href);
        }
        
        // Convert to preload with onload handler for non-blocking load
        $preload_html = '<link rel="preload" href="' . esc_url($href) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" id="' . esc_attr($handle) . '-preload">';
        $noscript_html = '<noscript><link rel="stylesheet" href="' . esc_url($href) . '" id="' . esc_attr($handle) . '-noscript"></noscript>';
        
        return $preload_html . "\n" . $noscript_html;
    }
    
    /**
     * Add font preconnect links
     */
    public function add_font_preconnects() {
        // Don't output on admin or preview contexts
        if (Context_Helper::should_skip_optimization()) {
            return;
        }
        
        if (!$this->options['enable_font_optimization']) {
            return;
        }
        
        $preconnects = array();
        
        if ($this->options['defer_google_fonts']) {
            $preconnects[] = '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>';
            $preconnects[] = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        }
        
        if ($this->options['defer_adobe_fonts']) {
            $preconnects[] = '<link rel="preconnect" href="https://use.typekit.net" crossorigin>';
        }
        
        if (!empty($preconnects)) {
            echo implode("\n", $preconnects) . "\n";
        }
    }

    /**
     * Output preload tags for locally-hosted font files (including Elementor custom fonts).
     * Users list their .woff2 URLs in the Local Font Preloads setting, one per line.
     */
    public function output_local_font_preloads() {
        if (Context_Helper::should_skip_optimization()) {
            return;
        }

        if (!$this->options['enable_font_optimization'] || empty($this->options['local_font_preloads'])) {
            return;
        }

        $urls = array_filter(array_map('trim', explode("\n", $this->options['local_font_preloads'])));

        foreach ($urls as $url) {
            // Allow relative paths (e.g. /wp-content/uploads/...) as well as full URLs
            if (strpos($url, '/') === 0) {
                echo '<link rel="preload" href="' . esc_attr($url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
                continue;
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $parsed = wp_parse_url($url);
            if (empty($parsed['scheme']) || !in_array($parsed['scheme'], array('http', 'https'), true)) {
                continue;
            }

            echo '<link rel="preload" href="' . esc_url($url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        }
    }

    /**
     * Start output buffer at wp_head priority 0 to capture inline @font-face blocks.
     * Only active when font_display_swap is enabled.
     */
    public function start_font_display_buffer() {
        if (!$this->options['enable_font_optimization'] || !$this->options['font_display_swap']) {
            return;
        }

        if (Context_Helper::should_skip_optimization()) {
            return;
        }

        ob_start();
    }

    /**
     * End output buffer at wp_head priority 999 and inject font-display:swap into
     * any @font-face block that doesn't already declare it.
     * Covers Elementor custom fonts, theme fonts, and any other inline @font-face.
     */
    public function end_font_display_buffer() {
        if (!$this->options['enable_font_optimization'] || !$this->options['font_display_swap']) {
            return;
        }

        if (Context_Helper::should_skip_optimization()) {
            return;
        }

        if (!ob_get_level()) {
            return;
        }

        $html = ob_get_clean();

        // Inject font-display: swap into @font-face blocks that don't already have it
        $html = preg_replace_callback(
            '/@font-face\s*\{([^}]+)\}/s',
            function ($matches) {
                $block_content = $matches[1];
                if (strpos($block_content, 'font-display') !== false) {
                    return $matches[0];
                }
                return '@font-face {' . $block_content . 'font-display: swap;}';
            },
            $html
        );

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML already rendered by WordPress core
    }

    /**
     * Scan enqueued Elementor CSS files for embedded Google Fonts @import statements.
     *
     * When Elementor uses the External File CSS mode it writes Google Font URLs directly
     * as @import rules inside its generated CSS files rather than as separate <link> tags.
     * That means WordPress's style_loader_tag filter never sees them, so the normal
     * optimize_font_loading() method is bypassed. The @import causes the browser to
     * pause CSS parsing and fetch Google Fonts synchronously — a render-blocking request.
     *
     * This method:
     *  1. Iterates every handle in $wp_styles->queue looking for Elementor CSS files.
     *  2. Reads each file and regex-scans for @import url(fonts.googleapis.com/...) lines.
     *  3. If found, patches the file on disk to remove the @import (self-healing: the cache
     *     key includes filemtime, so a regenerated file is re-patched on the next request).
     *  4. Stores the extracted URLs in $this->collected_font_urls so that
     *     output_extracted_font_preloads() can emit non-blocking <link rel="preload"> tags.
     *
     * Runs at wp_head priority 1 — before wp_print_styles fires at priority 8 — so the
     * patched file is already on disk when the browser requests it.
     */
    public function scan_and_patch_css_files() {
        if (!$this->options['enable_font_optimization'] || !$this->options['defer_google_fonts']) {
            return;
        }

        if (Context_Helper::should_skip_optimization()) {
            return;
        }

        global $wp_styles;
        if (empty($wp_styles->queue)) {
            return;
        }

        $upload_dir      = wp_upload_dir();
        $upload_base_url = trailingslashit($upload_dir['baseurl']);
        $upload_base_dir = trailingslashit($upload_dir['basedir']);

        foreach ($wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) {
                continue;
            }

            $src = $wp_styles->registered[$handle]->src;
            if (!$src || strpos($src, '/elementor/css/') === false) {
                continue;
            }

            // Strip query string (Elementor appends ?ver=... for cache-busting)
            $src_clean = preg_replace('/\?.*$/', '', $src);

            // Derive absolute file path from the URL
            if (strpos($src_clean, $upload_base_url) === 0) {
                $abspath = $upload_base_dir . substr($src_clean, strlen($upload_base_url));
            } else {
                // Fallback: strip scheme+host and map to ABSPATH
                $parsed = wp_parse_url($src_clean);
                if (empty($parsed['path'])) {
                    continue;
                }
                $abspath = untrailingslashit(ABSPATH) . $parsed['path'];
            }

            if (!file_exists($abspath) || !is_readable($abspath)) {
                continue;
            }

            // Cache result by file path + modification time so a regenerated file is re-scanned
            $cache_key = 'cb_fnt_scan_' . md5($abspath . filemtime($abspath));
            $cached    = get_transient($cache_key);

            if ($cached !== false) {
                // Empty array means file was scanned and had no @imports — skip silently
                if (!empty($cached)) {
                    $this->collected_font_urls = array_merge($this->collected_font_urls, $cached);
                }
                continue;
            }

            $css = file_get_contents($abspath); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            if ($css === false) {
                continue;
            }

            $found_urls = array();
            $patched_css = preg_replace_callback(
                '/@import\s+url\([\'"]?(https?:\/\/fonts\.googleapis\.com[^\'")\s]+)[\'"]?\)\s*;?/i',
                function ($matches) use (&$found_urls) {
                    $found_urls[] = $matches[1];
                    return ''; // Remove the @import so the browser never sees it
                },
                $css
            );

            if (!empty($found_urls)) {
                file_put_contents($abspath, $patched_css); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                $this->collected_font_urls = array_merge($this->collected_font_urls, $found_urls);
            }

            // Cache for 24 hours (invalidated automatically when filemtime changes)
            set_transient($cache_key, $found_urls, DAY_IN_SECONDS);
        }
    }

    /**
     * Output non-blocking <link rel="preload"> tags for Google Font URLs that were
     * extracted from Elementor CSS @import statements by scan_and_patch_css_files().
     *
     * Runs at wp_head priority 2 — just after scan_and_patch_css_files() at priority 1.
     */
    public function output_extracted_font_preloads() {
        if (!$this->options['enable_font_optimization'] || !$this->options['defer_google_fonts']) {
            return;
        }

        if (Context_Helper::should_skip_optimization()) {
            return;
        }

        $urls = array_unique($this->collected_font_urls);
        if (empty($urls)) {
            return;
        }

        foreach ($urls as $url) {
            // Append display=swap if font_display_swap is enabled and not already set
            if ($this->options['font_display_swap'] && strpos($url, 'display=') === false) {
                $url = add_query_arg('display', 'swap', $url);
            }
            echo '<link rel="preload" href="' . esc_url($url) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
            echo '<noscript><link rel="stylesheet" href="' . esc_url($url) . '"></noscript>' . "\n";
        }
    }

    /**
     * Strip Google Fonts @import rules from Elementor-generated CSS content before
     * Elementor writes the file to disk.
     *
     * Hooks into elementor/css/file_content so that freshly regenerated CSS files
     * (triggered by saving a page in Elementor) are clean from the moment they are written.
     * The stripped URLs are persisted in the coreboost_elementor_font_urls site option so
     * that scan_and_patch_css_files() can fall back to them when the transient cache is warm.
     *
     * @param string $css Raw CSS content Elementor is about to write.
     * @return string CSS with Google Fonts @import lines removed.
     */
    public function strip_elementor_font_imports($css) {
        $found_urls = array();

        $css = preg_replace_callback(
            '/@import\s+url\([\'"]?(https?:\/\/fonts\.googleapis\.com[^\'")\s]+)[\'"]?\)\s*;?/i',
            function ($matches) use (&$found_urls) {
                $found_urls[] = $matches[1];
                return '';
            },
            $css
        );

        if (!empty($found_urls)) {
            $existing = get_option('coreboost_elementor_font_urls', array());
            if (!is_array($existing)) {
                $existing = array();
            }
            update_option('coreboost_elementor_font_urls', array_unique(array_merge($existing, $found_urls)), false);
        }

        return $css;
    }

    /**
     * Add custom preconnect links from user settings
     */
    public function add_custom_preconnects() {
        // Don't output on admin or preview contexts
        if (Context_Helper::should_skip_optimization()) {
            return;
        }

        // Get custom preconnect URLs
        $custom_urls = isset($this->options['custom_preconnect_urls']) 
            ? $this->options['custom_preconnect_urls'] 
            : '';

        if (empty($custom_urls)) {
            return;
        }

        // Parse URLs (one per line)
        $urls = array_filter(array_map('trim', explode("\n", $custom_urls)));
        
        if (empty($urls)) {
            return;
        }

        $preconnects = array();

        foreach ($urls as $url) {
            // Skip empty lines
            if (empty($url)) {
                continue;
            }

            // Validate URL - silently skip invalid URLs
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            // Parse URL to get just the origin (scheme + host)
            $parsed = wp_parse_url($url);
            
            // Must have scheme and host
            if (empty($parsed['scheme']) || empty($parsed['host'])) {
                continue;
            }

            // Only allow http/https
            if (!in_array($parsed['scheme'], array('http', 'https'), true)) {
                continue;
            }

            // Build origin URL (scheme + host + optional port)
            $origin = $parsed['scheme'] . '://' . $parsed['host'];
            if (!empty($parsed['port'])) {
                $origin .= ':' . $parsed['port'];
            }

            // Avoid duplicates
            $preconnect_tag = '<link rel="preconnect" href="' . esc_url($origin) . '" crossorigin>';
            if (!in_array($preconnect_tag, $preconnects, true)) {
                $preconnects[] = $preconnect_tag;
            }
        }

        if (!empty($preconnects)) {
            echo "<!-- CoreBoost Custom Preconnects -->\n";
            echo implode("\n", $preconnects) . "\n";
        }
    }
}
