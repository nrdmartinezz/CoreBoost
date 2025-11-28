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
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        $this->define_hooks();
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
        // Smart YouTube blocking - only block if background videos detected
        if (isset($this->options['smart_youtube_blocking']) && $this->options['smart_youtube_blocking']) {
            if (!$this->should_block_youtube_resources()) {
                return $tag;
            }
        }
        
        // Block YouTube iframe API (loaded by background videos but not needed)
        if (strpos($src, 'youtube.com/iframe_api') !== false || strpos($src, 'youtube.com/www-widgetapi') !== false) {
            if (isset($this->options['smart_youtube_blocking']) && $this->options['smart_youtube_blocking']) {
                if ($this->options['debug_mode']) {
                    Debug_Helper::comment("Blocked YouTube iframe API (background video detected): {$src}", $this->options['debug_mode']);
                    return "<!-- CoreBoost: Blocked YouTube iframe API (background video detected) -->\n";
                }
                return '';
            }
        }
        
        // Block YouTube embed UI scripts (legacy setting)
        if ($this->options['block_youtube_embed_ui'] && strpos($src, 'youtube.com/yts/') !== false) {
            if ($this->options['debug_mode']) {
                Debug_Helper::comment("Blocked YouTube embed UI script: {$src}", $this->options['debug_mode']);
                return "<!-- CoreBoost: Blocked YouTube embed UI script -->\n";
            }
            return '';
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
     *
     * @return bool True if resources should be blocked
     */
    private function should_block_youtube_resources() {
        // Check if we have a Hero_Optimizer instance via CoreBoost
        global $wp_filter;
        
        // Try to get hero optimizer from the global CoreBoost instance
        if (class_exists('CoreBoost\CoreBoost')) {
            $coreboost = \CoreBoost\CoreBoost::get_instance();
            
            // We need to access the hero optimizer through reflection or create a new instance
            // For simplicity, we'll create a temporary instance to check for videos
            if (defined('ELEMENTOR_VERSION')) {
                // Create temporary Hero_Optimizer just for detection
                $temp_hero = new Hero_Optimizer($this->options, new \CoreBoost\Loader());
                $has_youtube_bg = $temp_hero->has_youtube_background_videos();
                
                if ($has_youtube_bg) {
                    if ($this->options['debug_mode']) {
                        Debug_Helper::comment("YouTube background video detected - blocking unnecessary resources", $this->options['debug_mode']);
                    }
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Start output buffer to catch inline/hardcoded CSS and scripts
     */
    public function start_output_buffer() {
        if (is_admin()) {
            return;
        }
        if ($this->options['enable_css_defer'] || $this->options['enable_script_defer'] || 
            $this->options['enable_inline_script_removal'] || $this->options['enable_inline_style_removal']) {
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
