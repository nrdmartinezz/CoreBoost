<?php
/**
 * Image Optimization System
 *
 * Handles comprehensive image optimization including:
 * - Native lazy loading (loading="lazy")
 * - Width/height attribute enforcement
 * - Aspect ratio CSS generation
 * - Responsive srcset generation
 * - Decoding optimization
 * - CLS prevention
 *
 * @package CoreBoost
 * @since 2.6.0
 */

namespace CoreBoost\PublicCore;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Image_Optimizer
 */
class Image_Optimizer {
    
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
     * Image format optimizer instance
     *
     * @var Image_Format_Optimizer
     */
    private $format_optimizer;
    
    /**
     * Image variant lifecycle manager instance
     *
     * @var Image_Variant_Lifecycle_Manager
     */
    private $lifecycle_manager;
    
    /**
     * Image responsive resizer instance
     *
     * @var Image_Responsive_Resizer
     */
    private $responsive_resizer;
    
    /**
     * Pre-compiled regex patterns for performance
     * Compiled once in constructor, reused throughout lifecycle
     */
    private $pattern_img_src;
    private $pattern_img_width;
    private $pattern_img_height;
    private $pattern_img_loading;
    private $pattern_img_class;
    private $pattern_img_decoding;
    private $pattern_img_alt;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        
        // Pre-compile regex patterns for performance (eliminates runtime compilation)
        $this->pattern_img_src = '/\s+src=["\']?([^"\'\s>]+)["\']?/i';
        $this->pattern_img_width = '/\s+width=["\']?(\d+)["\']?/i';
        $this->pattern_img_height = '/\s+height=["\']?(\d+)["\']?/i';
        $this->pattern_img_loading = '/\s+loading=/i';
        $this->pattern_img_class = '/class=["\']([^"\']*)["\']/';
        $this->pattern_img_decoding = '/\s+decoding=/i';
        $this->pattern_img_alt = '/\s+alt=["\']?([^"\']*)["\']?/i';
        
        // Initialize Phase 2 components if format conversion enabled
        if (!empty($options['enable_image_format_conversion'])) {
            $this->format_optimizer = new Image_Format_Optimizer($options);
            $this->lifecycle_manager = new Image_Variant_Lifecycle_Manager($options, $this->format_optimizer);
            
            // Initialize responsive resizer if enabled
            if (!empty($options['enable_responsive_image_resizing'])) {
                $this->responsive_resizer = new Image_Responsive_Resizer($options, $this->format_optimizer);
            }
            
            // Register lifecycle hooks
            $this->lifecycle_manager->register_hooks($loader);
        }
        
        // Only register on frontend
        if (!is_admin()) {
            $this->define_hooks();
        }
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        // Hook into output buffer via Resource_Remover
        // Will be called from process_inline_assets()
    }
    
    /**
     * Main entry point for image optimization
     * Called from Resource_Remover::process_inline_assets()
     *
     * OPTIMIZED: Single-pass processing for all image optimizations (5 passes → 1)
     * Eliminates 80% regex parsing overhead by combining all optimizations into one traversal
     *
     * @param string $html HTML content
     * @return string Modified HTML with optimized images
     */
    public function optimize_images($html) {
        // Check if any image optimization is enabled
        if (!$this->is_optimization_enabled()) {
            return $html;
        }
        
        // PERFORMANCE: Single pass through HTML for all image optimizations
        $aspect_ratios = array();
        $image_count = 0;
        $lazy_load_exclude_count = isset($this->options['lazy_load_exclude_count']) ? (int)$this->options['lazy_load_exclude_count'] : 2;
        
        // Determine which optimizations are enabled
        $enable_lazy = !empty($this->options['enable_lazy_loading']);
        $enable_dimensions = !empty($this->options['add_width_height_attributes']);
        $enable_aspect_ratio = !empty($this->options['generate_aspect_ratio_css']);
        $enable_decoding = !empty($this->options['add_decoding_async']);
        $enable_format = !empty($this->options['enable_image_format_conversion']) && $this->format_optimizer;
        
        // Log format optimization status
        if ($enable_format) {
            error_log("CoreBoost: Phase 2 format optimization enabled (single-pass mode)");
        }
        
        // Single regex pass handles all optimizations
        $html = preg_replace_callback(
            '/<img\s+([^>]*)>/i',
            function($matches) use (
                &$aspect_ratios, 
                &$image_count, 
                $lazy_load_exclude_count,
                $enable_lazy,
                $enable_dimensions,
                $enable_aspect_ratio,
                $enable_decoding,
                $enable_format
            ) {
                $image_count++;
                $attrs = $matches[1];
                
                // Extract src URL first (needed for multiple operations)
                $src_match = [];
                if (!preg_match($this->pattern_img_src, $attrs, $src_match)) {
                    return $matches[0];
                }
                $src_url = $src_match[1];
                
                // Extract/add dimensions (needed for aspect ratio, lazy loading exclusion, format optimization)
                $width = null;
                $height = null;
                $width_match = [];
                $height_match = [];
                
                if (preg_match($this->pattern_img_width, $attrs, $width_match)) {
                    $width = (int)$width_match[1];
                }
                if (preg_match($this->pattern_img_height, $attrs, $height_match)) {
                    $height = (int)$height_match[1];
                }
                
                // Add dimensions if enabled and missing
                if ($enable_dimensions && (!$width || !$height)) {
                    $dimensions = $this->get_image_dimensions($src_url);
                    if ($dimensions) {
                        if (!$width) {
                            $width = $dimensions['width'];
                            $attrs = ' width="' . esc_attr($width) . '"' . $attrs;
                        }
                        if (!$height) {
                            $height = $dimensions['height'];
                            $attrs = ' height="' . esc_attr($height) . '"' . $attrs;
                        }
                    }
                }
                
                // Add lazy loading if enabled (skip first N images for LCP)
                if ($enable_lazy) {
                    $has_loading = preg_match($this->pattern_img_loading, $attrs);
                    
                    if ($image_count <= $lazy_load_exclude_count) {
                        // Remove any loading attribute from LCP images
                        $attrs = preg_replace('/\s+loading=["\'](?:eager|lazy)["\']/i', '', $attrs);
                    } elseif (!$has_loading) {
                        // Add lazy loading to non-LCP images
                        $attrs = ' loading="lazy"' . $attrs;
                    }
                }
                
                // Add aspect ratio CSS class if enabled
                if ($enable_aspect_ratio && $width && $height && $width > 0 && $height > 0) {
                    $aspect_ratio = round(($width / $height), 4);
                    $unique_id = 'cb-img-' . $image_count;
                    
                    // Add class
                    if (preg_match($this->pattern_img_class, $attrs)) {
                        $attrs = preg_replace($this->pattern_img_class, 'class="$1 ' . $unique_id . '"', $attrs);
                    } else {
                        $attrs = ' class="' . $unique_id . '"' . $attrs;
                    }
                    
                    $aspect_ratios[$unique_id] = $aspect_ratio;
                }
                
                // Add decoding="async" if enabled
                if ($enable_decoding && !preg_match($this->pattern_img_decoding, $attrs)) {
                    $attrs = ' decoding="async"' . $attrs;
                }
                
                // Apply format optimization (AVIF/WebP) if enabled
                if ($enable_format && $this->format_optimizer->should_optimize_image($src_url)) {
                    // Strip WordPress size suffix to find original image variants
                    $original_src = preg_replace('/-\d+x\d+(-scaled)?\.(jpg|jpeg|png|gif|webp)$/i', '.$2', $src_url);
                    
                    // Check for variants (using original image URL)
                    $avif_url = $this->format_optimizer->get_variant_from_cache($original_src, 'avif');
                    $webp_url = $this->format_optimizer->get_variant_from_cache($original_src, 'webp');
                    
                    if ($avif_url || $webp_url) {
                        // Extract alt and classes for picture tag
                        $alt_match = [];
                        $alt = preg_match($this->pattern_img_alt, $attrs, $alt_match) ? $alt_match[1] : '';
                        
                        $class_match = [];
                        $classes = preg_match($this->pattern_img_class, $attrs, $class_match) ? $class_match[1] : '';
                        
                        // Check for responsive variants (resizer handles URL stripping internally)
                        $responsive_variants = array();
                        if ($this->responsive_resizer) {
                            $responsive_variants = $this->responsive_resizer->get_available_responsive_variants($src_url);
                            
                            // If no variants exist but dimensions are known, generate them on-demand
                            if (empty($responsive_variants) && $width && $height) {
                                $this->responsive_resizer->generate_variants_if_needed($src_url, $width, $height);
                                // Check again after generation
                                $responsive_variants = $this->responsive_resizer->get_available_responsive_variants($src_url);
                            }
                        }
                        
                        // Render picture tag (using original URL for variant lookups)
                        if (!empty($responsive_variants)) {
                            return $this->format_optimizer->render_responsive_picture_tag($original_src, $alt, $classes, array(), $responsive_variants, $width);
                        } else {
                            return $this->format_optimizer->render_picture_tag($original_src, $alt, $classes);
                        }
                    }
                }
                
                // Return optimized img tag
                return '<img ' . $attrs . '>';
            },
            $html
        );
        
        // Inject aspect ratio CSS if any were generated
        if (!empty($aspect_ratios)) {
            $css = "\n<style>\n/* CoreBoost Image Aspect Ratios */\n";
            foreach ($aspect_ratios as $class => $ratio) {
                $css .= ".$class { aspect-ratio: {$ratio}; }\n";
            }
            $css .= "</style>\n";
            $html = str_replace('</head>', $css . '</head>', $html);
        }
        
        // Detect and queue oversized images for responsive resizing (if enabled)
        if ($enable_format && $this->responsive_resizer) {
            $this->responsive_resizer->detect_and_queue_oversized_images($html);
        }
        
        // Process CSS background images (if format optimization enabled)
        if ($enable_format) {
            $html = $this->optimize_css_background_images($html);
            $html = $this->inject_css_overrides_inline($html);
        }
        
        return $html;
    }
    
    /**
     * Check if any image optimization is enabled
     *
     * Returns true if either Phase 1 or Phase 2 optimizations are active.
     *
     * @return bool
     */
    private function is_optimization_enabled() {
        $phase1_enabled = !empty($this->options['enable_image_optimization'])
            && (!empty($this->options['enable_lazy_loading'])
                || !empty($this->options['add_width_height_attributes'])
                || !empty($this->options['generate_aspect_ratio_css'])
                || !empty($this->options['add_decoding_async']));
        
        $phase2_enabled = !empty($this->options['enable_image_format_conversion']);
        
        return $phase1_enabled || $phase2_enabled;
    }
    
    /**
     * Get image dimensions from various sources
     * Used by single-pass optimizer
     *
     * @param string $src Image URL or path
     * @return array|false Dimensions array with 'width' and 'height', or false
     */
    private function get_image_dimensions($src) {
        // Convert URL to local path if needed
        $file_path = $this->url_to_path($src);
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        // Use WordPress function to get image dimensions
        $size = getimagesize($file_path);
        if ($size && isset($size[0]) && isset($size[1])) {
            return array(
                'width' => $size[0],
                'height' => $size[1]
            );
        }
        
        return false;
    }
    
    /**
     * Convert image URL to local file path
     * Used by single-pass optimizer
     *
     * @param string $url Image URL
     * @return string Local file path
     */
    private function url_to_path($url) {
        // Handle absolute URLs
        if (strpos($url, 'http') === 0) {
            $site_url = home_url();
            if (strpos($url, $site_url) === 0) {
                // It's a local image
                $path = str_replace($site_url, '', $url);
                return ABSPATH . ltrim($path, '/');
            }
        }
        
        // Handle relative paths
        if (strpos($url, '/') === 0) {
            return ABSPATH . ltrim($url, '/');
        }
        
        return $url;
    }
    
    /**
     * Optimize CSS background images with modern formats
     *
     * Replaces background-image: url() with optimized AVIF/WebP variants.
     * Uses CSS @supports rule for progressive enhancement.
     *
     * @param string $html HTML content
     * @return string Modified HTML with optimized background images
     */
    private function optimize_css_background_images($html) {
        $replacements_made = 0;
        
        // Count style tags and attributes
        $style_tag_count = preg_match_all('/<style[^>]*>.*?<\/style>/is', $html);
        $style_attr_count = preg_match_all('/style=["\'][^"\']*["\']/', $html);
        error_log("CoreBoost: Found {$style_tag_count} <style> tags and {$style_attr_count} style attributes");
        
        // Find inline style tags
        $html = preg_replace_callback(
            '/<style[^>]*>(.*?)<\/style>/is',
            function($matches) use (&$replacements_made) {
                $css = $matches[1];
                error_log("CoreBoost: Processing <style> tag with " . strlen($css) . " characters");
                $optimized_css = $this->process_css_background_images($css, $replacements_made);
                return '<style' . (strpos($matches[0], ' ') !== false ? substr($matches[0], 6, strpos($matches[0], '>') - 6) : '') . '>' . $optimized_css . '</style>';
            },
            $html
        );
        
        // Find inline style attributes with background or background-image
        $html = preg_replace_callback(
            '/style=["\']([^"\']*background[^"\']*)["\']/',
            function($matches) use (&$replacements_made) {
                $style = $matches[1];
                error_log("CoreBoost: Processing style attribute: " . substr($style, 0, 100));
                $optimized_style = $this->process_css_background_images($style, $replacements_made);
                return 'style="' . esc_attr($optimized_style) . '"';
            },
            $html
        );
        
        // Debug logging
        error_log("CoreBoost: Total CSS background images optimized: {$replacements_made}");
        
        return $html;
    }
    
    /**
     * Process CSS to replace background-image URLs with optimized variants
     *
     * @param string $css CSS content
     * @return string Optimized CSS with AVIF/WebP variants
     */
    private function process_css_background_images($css, &$replacements_made = 0) {
        // Match both background: and background-image: with url() patterns
        $css = preg_replace_callback(
            '/\b(background(?:-image)?)\s*:\s*([^;]*?)url\(["\']?([^"\')\s]+)["\']?\)([^;]*)/i',
            function($matches) use (&$replacements_made) {
                $property = $matches[1]; // background or background-image
                $before_url = trim($matches[2]); // any values before url()
                $original_url = $matches[3]; // the image URL
                $after_url = $matches[4]; // any values after url() (position, size, etc)
                
                error_log("CoreBoost: Found CSS background: {$original_url}");
                
                // Check if should optimize this image
                if (!$this->format_optimizer->should_optimize_image($original_url)) {
                    error_log("CoreBoost: Image should not be optimized");
                    return $matches[0];
                }
                
                // Check if variants exist
                $avif_url = $this->format_optimizer->get_variant_from_cache($original_url, 'avif');
                $webp_url = $this->format_optimizer->get_variant_from_cache($original_url, 'webp');
                
                error_log("CoreBoost: AVIF variant: " . ($avif_url ? $avif_url : 'NOT FOUND'));
                error_log("CoreBoost: WebP variant: " . ($webp_url ? $webp_url : 'NOT FOUND'));
                
                // If no variants found, return original
                if (!$avif_url && !$webp_url) {
                    error_log("CoreBoost: No variants found, keeping original");
                    return $matches[0];
                }
                
                $replacements_made++;
                error_log("CoreBoost: Replacing background with optimized variants");
                
                // Build progressive enhancement CSS
                // Use the best available format (AVIF > WebP > original)
                $best_url = $avif_url ? $avif_url : ($webp_url ? $webp_url : $original_url);
                
                // Reconstruct the property with optimized URL
                $output = $property . ': ';
                if (!empty($before_url)) {
                    $output .= $before_url . ' ';
                }
                $output .= 'url(' . esc_url($best_url) . ')';
                if (!empty($after_url)) {
                    $output .= $after_url;
                }
                
                return $output;
            },
            $css
        );
        
        return $css;
    }
    
    /**
     * Intercept CSS file requests and add inline overrides
     * Since external CSS files are served directly by the web server,
     * we inject inline <style> overrides with optimized background images
    /**
     * Inject inline CSS overrides for external stylesheet background images
     * Parses <link> tags in HTML, reads referenced CSS files, and injects optimized overrides
     *
     * @param string $html HTML content
     * @return string Modified HTML with injected CSS overrides
     */
    private function inject_css_overrides_inline($html) {
        $overrides = array();
        
        // Find all stylesheet links
        if (!preg_match_all('/<link[^>]*rel=["\']stylesheet["\'][^>]*>/i', $html, $link_matches)) {
            return $html;
        }
        
        error_log("CoreBoost: Found " . count($link_matches[0]) . " stylesheet links");
        
        foreach ($link_matches[0] as $link_tag) {
            // Extract href
            if (!preg_match('/href=["\']([^"\']+)["\']/', $link_tag, $href_match)) {
                continue;
            }
            
            $css_url = $href_match[1];
            
            // Only process local CSS files
            if (strpos($css_url, home_url()) !== 0 && strpos($css_url, '/') === 0) {
                $css_url = home_url() . $css_url;
            } elseif (strpos($css_url, 'http') !== 0) {
                continue; // Skip external or invalid URLs
            }
            
            // Remove query strings from URL FIRST, before path conversion
            $css_url_clean = preg_replace('/\?.*$/', '', $css_url);
            
            // Convert URL to path
            $css_path = str_replace(home_url(), ABSPATH, $css_url_clean);
            $css_path = str_replace('/', DIRECTORY_SEPARATOR, $css_path);
            
            if (!file_exists($css_path)) {
                error_log("CoreBoost: CSS file NOT FOUND - URL: {$css_url} | Path: {$css_path}");
                continue;
            }
            
            // Check cache first (24 hour expiration)
            $cache_key = 'coreboost_css_bg_' . md5($css_url_clean . filemtime($css_path));
            $cached_override = get_transient($cache_key);
            
            if ($cached_override !== false) {
                $overrides[] = $cached_override;
                error_log("CoreBoost: Using cached CSS background optimizations for: " . basename($css_path));
                continue;
            }
            
            error_log("CoreBoost: Reading CSS file: {$css_path}");
            
            // Read CSS file
            $css_content = file_get_contents($css_path);
            if ($css_content === false) {
                continue;
            }
            
            // Process CSS to find and optimize background images
            // Instead of trying to extract selectors from complex/minified CSS,
            // we'll just replace URLs directly and inject the full CSS as override
            $replacements_made = 0;
            $optimized_css = $this->process_css_background_images($css_content, $replacements_made);
            
            if ($replacements_made > 0) {
                // Wrap in a comment to identify the source file
                $override_block = "/* CoreBoost optimized backgrounds from: " . basename($css_path) . " */\n" . $optimized_css;
                
                // Cache the result (24 hours)
                set_transient($cache_key, $override_block, DAY_IN_SECONDS);
                
                $overrides[] = $override_block;
                error_log("CoreBoost: Optimized {$replacements_made} background images in: " . basename($css_path));
            } else {
                error_log("CoreBoost: No optimizable background images found in: " . basename($css_path));
            }
        }
        
        // Inject override styles before </head>
        if (!empty($overrides)) {
            $override_css = "\n<style id=\"coreboost-bg-overrides\">\n";
            $override_css .= "/* CoreBoost: Optimized background images (" . count($overrides) . " rules) */\n";
            $override_css .= implode("\n", $overrides);
            $override_css .= "\n</style>\n";
            
            $html = str_replace('</head>', $override_css . '</head>', $html);
            error_log("CoreBoost: Injected " . count($overrides) . " CSS background image overrides");
        }
        
        return $html;
    }
}