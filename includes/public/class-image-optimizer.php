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
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        
        // Initialize Phase 2 components if format conversion enabled
        if (!empty($options['enable_image_format_conversion'])) {
            $this->format_optimizer = new Image_Format_Optimizer($options);
            $this->lifecycle_manager = new Image_Variant_Lifecycle_Manager($options, $this->format_optimizer);
            
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
     * @param string $html HTML content
     * @return string Modified HTML with optimized images
     */
    public function optimize_images($html) {
        // Check if any image optimization is enabled
        if (!$this->is_optimization_enabled()) {
            return $html;
        }
        
        // Apply Phase 1 optimizations in order of impact
        if (!empty($this->options['enable_lazy_loading'])) {
            $html = $this->add_lazy_loading($html);
        }
        
        if (!empty($this->options['add_width_height_attributes'])) {
            $html = $this->add_width_height_attributes($html);
        }
        
        if (!empty($this->options['generate_aspect_ratio_css'])) {
            $html = $this->generate_aspect_ratio_css($html);
        }
        
        if (!empty($this->options['add_decoding_async'])) {
            $html = $this->add_decoding_async($html);
        }
        
        // Apply Phase 2 format optimization if enabled
        if (!empty($this->options['enable_image_format_conversion']) && $this->format_optimizer) {
            error_log("CoreBoost: Phase 2 format optimization enabled");
            $html = $this->apply_format_optimization($html);
            $html = $this->optimize_css_background_images($html);
            $html = $this->inject_css_overrides_inline($html);
        }
        
        return $html;
    }
    
    /**
     * Apply image format optimization (Phase 2)
     *
     * Processes images and converts them to modern formats (AVIF/WebP)
     * with HTML5 <picture> tag rendering for format selection.
     * Variants should be pre-generated via bulk conversion or auto-upload hooks.
     *
     * @param string $html HTML content
     * @return string Modified HTML with <picture> tags
     */
    private function apply_format_optimization($html) {
        // Process <img> tags with src attributes
        $html = preg_replace_callback(
            '/<img\s+([^>]*)>/i',
            function($matches) {
                $attrs = $matches[1];
                
                // Extract src URL
                $src_match = [];
                if (!preg_match('/\s+src=["\']?([^"\'\s>]+)["\']?/i', $attrs, $src_match)) {
                    return $matches[0];
                }
                
                $src_url = $src_match[1];
                
                // Check if should optimize this image
                if (!$this->format_optimizer->should_optimize_image($src_url)) {
                    return $matches[0];
                }
                
                // Check if ANY variants exist (AVIF or WebP)
                $avif_url = $this->format_optimizer->get_variant_from_cache($src_url, 'avif');
                $webp_url = $this->format_optimizer->get_variant_from_cache($src_url, 'webp');
                
                // If no variants found at all, skip picture tag enhancement (use original img)
                if (!$avif_url && !$webp_url) {
                    return $matches[0];
                }
                
                // Extract alt text and classes
                $alt_match = [];
                $alt = preg_match('/\s+alt=["\']?([^"\']*)["\']/i', $attrs, $alt_match) 
                    ? $alt_match[1] 
                    : '';
                
                $class_match = [];
                $classes = preg_match('/\s+class=["\']([^"\']*)["\']/', $attrs, $class_match) 
                    ? $class_match[1] 
                    : '';
                
                // Render picture tag with variants
                return $this->format_optimizer->render_picture_tag($src_url, $alt, $classes);
            },
            $html
        );
        
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
     * Add native lazy loading to images
     *
     * @param string $html HTML content
     * @return string Modified HTML
     */
    private function add_lazy_loading($html) {
        $lazy_load_exclude_count = isset($this->options['lazy_load_exclude_count']) 
            ? (int)$this->options['lazy_load_exclude_count'] 
            : 2;
        
        $image_count = 0;
        
        $html = preg_replace_callback(
            '/<img\s+([^>]*?)(?:loading=["\'](?:eager|lazy)["\'])?([^>]*)>/i',
            function($matches) use (&$image_count, $lazy_load_exclude_count) {
                $before_loading = $matches[1];
                $after_loading = $matches[2];
                $image_count++;
                
                // Skip first X images (LCP images)
                if ($image_count <= $lazy_load_exclude_count) {
                    // Remove any existing loading attribute and don't add lazy
                    $before_loading = preg_replace('/\s+loading=["\'](?:eager|lazy)["\']/i', '', $before_loading);
                    $after_loading = preg_replace('/\s+loading=["\'](?:eager|lazy)["\']/i', '', $after_loading);
                    return '<img ' . $before_loading . $after_loading . '>';
                }
                
                // Check if already has loading attribute
                if (preg_match('/\s+loading=["\']lazy["\']/i', $before_loading . $after_loading)) {
                    return $matches[0]; // Already lazy loaded
                }
                
                // Remove any existing loading attribute
                $before_loading = preg_replace('/\s+loading=["\'](?:eager|lazy)["\']/i', '', $before_loading);
                $after_loading = preg_replace('/\s+loading=["\'](?:eager|lazy)["\']/i', '', $after_loading);
                
                // Add lazy loading
                return '<img ' . $before_loading . ' loading="lazy"' . $after_loading . '>';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Add width and height attributes to images
     *
     * @param string $html HTML content
     * @return string Modified HTML
     */
    private function add_width_height_attributes($html) {
        $html = preg_replace_callback(
            '/<img\s+([^>]*)>/i',
            function($matches) {
                $img_tag = $matches[0];
                $attrs = $matches[1];
                
                // Check if already has width and height
                if (preg_match('/\s+width=["\']?\d+["\']?/i', $attrs) 
                    && preg_match('/\s+height=["\']?\d+["\']?/i', $attrs)) {
                    return $img_tag;
                }
                
                // Try to extract src and get image dimensions
                $src_match = [];
                if (preg_match('/\s+src=["\']([^\'"]+)["\']/i', $attrs, $src_match)) {
                    $src = $src_match[1];
                    $dimensions = $this->get_image_dimensions($src);
                    
                    if ($dimensions) {
                        // Add width and height if not present
                        if (!preg_match('/\s+width=/i', $attrs)) {
                            $attrs = ' width="' . esc_attr($dimensions['width']) . '"' . $attrs;
                        }
                        if (!preg_match('/\s+height=/i', $attrs)) {
                            $attrs = ' height="' . esc_attr($dimensions['height']) . '"' . $attrs;
                        }
                        
                        return '<img ' . $attrs . '>';
                    }
                }
                
                return $img_tag;
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Get image dimensions from various sources
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
     * Generate aspect ratio CSS for images
     *
     * @param string $html HTML content
     * @return string Modified HTML
     */
    private function generate_aspect_ratio_css($html) {
        $aspect_ratios = array();
        $counter = 0;
        
        $html = preg_replace_callback(
            '/<img\s+([^>]*)>/i',
            function($matches) use (&$aspect_ratios, &$counter) {
                $img_tag = $matches[0];
                $attrs = $matches[1];
                
                // Check if already has aspect-ratio style
                if (preg_match('/style=["\']([^"\']*aspect-ratio[^"\']*)["\']/', $attrs)) {
                    return $img_tag;
                }
                
                // Try to get width and height for aspect ratio
                $width_match = [];
                $height_match = [];
                
                if (preg_match('/\s+width=["\']?(\d+)["\']?/i', $attrs, $width_match) 
                    && preg_match('/\s+height=["\']?(\d+)["\']?/i', $attrs, $height_match)) {
                    
                    $width = (int)$width_match[1];
                    $height = (int)$height_match[1];
                    
                    if ($width > 0 && $height > 0) {
                        $aspect_ratio = round(($width / $height), 4);
                        $counter++;
                        $unique_id = 'cb-img-' . $counter;
                        
                        // Add class for aspect ratio styling
                        $attrs = preg_replace(
                            '/class=["\']([^"\']*)["\']/',
                            'class="$1 ' . $unique_id . '"',
                            $attrs
                        );
                        
                        // If no class attribute exists, add one
                        if (!preg_match('/class=/i', $attrs)) {
                            $attrs = ' class="' . $unique_id . '"' . $attrs;
                        }
                        
                        // Store for CSS generation
                        $aspect_ratios[$unique_id] = $aspect_ratio;
                        
                        return '<img ' . $attrs . '>';
                    }
                }
                
                return $img_tag;
            },
            $html
        );
        
        // Generate CSS for aspect ratios if any found
        if (!empty($aspect_ratios)) {
            $css = "\n<style>\n/* CoreBoost Image Aspect Ratios */\n";
            foreach ($aspect_ratios as $class => $ratio) {
                $css .= ".$class { aspect-ratio: {$ratio}; }\n";
            }
            $css .= "</style>\n";
            
            // Inject before closing head tag
            $html = str_replace('</head>', $css . '</head>', $html);
        }
        
        return $html;
    }
    
    /**
     * Add decoding="async" to images
     *
     * @param string $html HTML content
     * @return string Modified HTML
     */
    private function add_decoding_async($html) {
        $lazy_load_exclude_count = isset($this->options['lazy_load_exclude_count']) 
            ? (int)$this->options['lazy_load_exclude_count'] 
            : 2;
        
        $image_count = 0;
        
        $html = preg_replace_callback(
            '/<img\s+([^>]*)>/i',
            function($matches) use (&$image_count, $lazy_load_exclude_count) {
                $attrs = $matches[1];
                $image_count++;
                
                // Skip first X images (they should decode synchronously for LCP)
                if ($image_count <= $lazy_load_exclude_count) {
                    return $matches[0];
                }
                
                // Check if already has decoding attribute
                if (preg_match('/\s+decoding=/i', $attrs)) {
                    return $matches[0];
                }
                
                // Add decoding async
                return '<img ' . $attrs . ' decoding="async">';
            },
            $html
        );
        
        return $html;
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
            
            // Convert URL to path
            $css_path = str_replace(home_url(), ABSPATH, $css_url);
            $css_path = str_replace('/', DIRECTORY_SEPARATOR, $css_path);
            
            // Remove query strings from path
            $css_path = preg_replace('/\?.*$/', '', $css_path);
            
            if (!file_exists($css_path)) {
                continue;
            }
            
            error_log("CoreBoost: Reading CSS file: {$css_path}");
            
            // Read CSS file
            $css_content = file_get_contents($css_path);
            if ($css_content === false) {
                continue;
            }
            
            // Find background images that can be optimized
            if (preg_match_all('/([^{]*)\{([^}]*background[^}]*url\([^)]+\)[^}]*)\}/is', $css_content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $selector = trim($match[1]);
                    $declarations = $match[2];
                    
                    $replacements_made = 0;
                    $optimized_declarations = $this->process_css_background_images($declarations, $replacements_made);
                    
                    if ($replacements_made > 0) {
                        $overrides[] = $selector . ' { ' . $optimized_declarations . ' }';
                        error_log("CoreBoost: Created override for selector: {$selector}");
                    }
                }
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