<?php
/**
 * Overview Page for CoreBoost Admin
 *
 * Displays analytics, quick actions, and optimization status.
 *
 * @package CoreBoost
 * @since 3.1.0
 */

namespace CoreBoost\Admin;

use CoreBoost\Core\Cache_Manager;
use CoreBoost\Core\Compression_Analytics;
use CoreBoost\Core\Variant_Cache;
use CoreBoost\PublicCore\Analytics_Engine;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Overview_Page
 */
class Overview_Page {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
    }
    
    /**
     * Render the dashboard page
     */
    public function render() {
        $this->handle_quick_actions();
        
        $logo_path = COREBOOST_PLUGIN_DIR . 'assets/images/coreboost-logo.png';
        $logo_url = COREBOOST_PLUGIN_URL . 'assets/images/coreboost-logo.png';
        $has_logo = file_exists($logo_path);
        
        ?>
        <div class="wrap coreboost-dashboard">
            <!-- Hero Section with Logo -->
            <div class="coreboost-hero-section">
                <div class="coreboost-hero-content">
                    <?php if ($has_logo) : ?>
                        <img src="<?php echo esc_url($logo_url); ?>" 
                             alt="CoreBoost" 
                             class="coreboost-hero-logo">
                    <?php else : ?>
                        <div class="coreboost-hero-logo coreboost-hero-logo-fallback">
                            <span class="dashicons dashicons-performance"></span>
                        </div>
                    <?php endif; ?>
                    <div class="coreboost-hero-text">
                        <h1><?php _e('CoreBoost', 'coreboost'); ?> <span><?php _e('Dashboard', 'coreboost'); ?></span></h1>
                        <p class="coreboost-hero-tagline"><?php _e('Supercharge your WordPress performance', 'coreboost'); ?></p>
                    </div>
                </div>
            </div>
            
            <?php $this->render_notices(); ?>
            
            <!-- Analytics Cards -->
            <div class="coreboost-section coreboost-analytics-section">
                <h2><span class="dashicons dashicons-chart-bar"></span> <?php _e('Performance Analytics', 'coreboost'); ?></h2>
                <div class="coreboost-cards">
                    <?php $this->render_analytics_cards(); ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="coreboost-section coreboost-actions-section">
                <h2><span class="dashicons dashicons-superhero-alt"></span> <?php _e('Quick Actions', 'coreboost'); ?></h2>
                <div class="coreboost-quick-actions">
                    <?php $this->render_quick_actions(); ?>
                </div>
            </div>
            
            <!-- Optimization Status -->
            <div class="coreboost-section coreboost-status-section">
                <h2><span class="dashicons dashicons-yes-alt"></span> <?php _e('Optimization Status', 'coreboost'); ?></h2>
                <div class="coreboost-status-grid">
                    <?php $this->render_optimization_status(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render analytics cards
     */
    private function render_analytics_cards() {
        // Get compression analytics
        $compression_stats = Compression_Analytics::get_aggregated_stats();
        $total_saved = $compression_stats['total']['total_saved_formatted'] ?? '0 B';
        
        // Get script analytics
        $analytics_engine = new Analytics_Engine($this->options, false);
        $dashboard_summary = $analytics_engine->get_dashboard_summary();
        
        // Calculate metrics
        $scripts_optimized = $dashboard_summary['total_scripts'] ?? 0;
        $scripts_deferred = $scripts_optimized - ($dashboard_summary['scripts_excluded'] ?? 0);
        $bytes_saved_mb = $dashboard_summary['bytes_saved_mb'] ?? 0;
        
        // Estimated load time improvement (rough calculation based on deferred scripts)
        $load_improvement = $scripts_deferred > 0 ? min($scripts_deferred * 50, 500) : 0;
        ?>
        <div class="coreboost-card-stat green">
            <span class="dashicons dashicons-chart-area coreboost-card-icon"></span>
            <div class="coreboost-card-value"><?php echo esc_html($total_saved); ?></div>
            <div class="coreboost-card-label"><?php _e('File Size Reduction', 'coreboost'); ?></div>
        </div>
        
        <div class="coreboost-card-stat blue">
            <span class="dashicons dashicons-performance coreboost-card-icon"></span>
            <div class="coreboost-card-value"><?php echo esc_html($scripts_deferred); ?></div>
            <div class="coreboost-card-label"><?php _e('Requests Optimized', 'coreboost'); ?></div>
        </div>
        
        <div class="coreboost-card-stat orange">
            <span class="dashicons dashicons-dashboard coreboost-card-icon"></span>
            <div class="coreboost-card-value"><?php echo esc_html($load_improvement); ?>ms</div>
            <div class="coreboost-card-label"><?php _e('Est. Load Speed Improvement', 'coreboost'); ?></div>
        </div>
        <?php
    }
    
    /**
     * Render quick actions
     */
    private function render_quick_actions() {
        $clear_cache_url = wp_nonce_url(
            admin_url('admin.php?page=coreboost&action=clear_all_caches'),
            'coreboost_clear_all_caches'
        );
        
        $clear_hero_url = wp_nonce_url(
            admin_url('admin.php?page=coreboost&action=clear_hero_cache'),
            'coreboost_clear_hero_cache'
        );
        
        $pagespeed_url = 'https://pagespeed.web.dev/';
        ?>
        <a href="<?php echo esc_url($clear_cache_url); ?>" class="button button-primary">
            <span class="dashicons dashicons-trash"></span>
            <?php _e('Clear All Caches', 'coreboost'); ?>
        </a>
        
        <a href="<?php echo esc_url($clear_hero_url); ?>" class="button">
            <span class="dashicons dashicons-images-alt2"></span>
            <?php _e('Clear Hero Cache', 'coreboost'); ?>
        </a>
        
        <a href="<?php echo esc_url($pagespeed_url); ?>" class="button" target="_blank">
            <span class="dashicons dashicons-performance"></span>
            <?php _e('Run PageSpeed Test', 'coreboost'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=coreboost-optimizations'); ?>" class="button">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('Configure Optimizations', 'coreboost'); ?>
        </a>
        <?php
    }
    
    /**
     * Render optimization status
     */
    private function render_optimization_status() {
        $features = array(
            'enable_script_defer' => __('Script Deferring', 'coreboost'),
            'enable_css_defer' => __('CSS Deferring', 'coreboost'),
            'enable_image_optimization' => __('Image Optimization', 'coreboost'),
            'enable_image_format_conversion' => __('AVIF/WebP Conversion', 'coreboost'),
            'enable_lazy_loading' => __('Lazy Loading', 'coreboost'),
            'enable_font_optimization' => __('Font Optimization', 'coreboost'),
            'enable_hero_preload_extraction' => __('Hero Preloading', 'coreboost'),
            'enable_caching' => __('Caching', 'coreboost'),
        );
        
        foreach ($features as $option_key => $label) {
            $enabled = !empty($this->options[$option_key]);
            $status_class = $enabled ? 'enabled' : 'disabled';
            $badge_class = $enabled ? 'on' : 'off';
            $badge_text = $enabled ? __('ON', 'coreboost') : __('OFF', 'coreboost');
            ?>
            <div class="coreboost-status-item <?php echo esc_attr($status_class); ?>">
                <span class="coreboost-status-label"><?php echo esc_html($label); ?></span>
                <span class="coreboost-status-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($badge_text); ?></span>
            </div>
            <?php
        }
    }
    
    /**
     * Handle quick actions
     */
    private function handle_quick_actions() {
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
        $nonce = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        
        if ($action === 'clear_all_caches' && $nonce && wp_verify_nonce($nonce, 'coreboost_clear_all_caches')) {
            Cache_Manager::flush_all_caches();
            wp_redirect(add_query_arg('cache_cleared', '1', remove_query_arg(array('action', '_wpnonce'))));
            exit;
        }
        
        if ($action === 'clear_hero_cache' && $nonce && wp_verify_nonce($nonce, 'coreboost_clear_hero_cache')) {
            Cache_Manager::clear_hero_cache();
            wp_redirect(add_query_arg('hero_cleared', '1', remove_query_arg(array('action', '_wpnonce'))));
            exit;
        }
    }
    
    /**
     * Render notices
     */
    private function render_notices() {
        if (filter_input(INPUT_GET, 'cache_cleared', FILTER_SANITIZE_SPECIAL_CHARS) === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('All caches cleared successfully!', 'coreboost') . '</p></div>';
        }
        
        if (filter_input(INPUT_GET, 'hero_cleared', FILTER_SANITIZE_SPECIAL_CHARS) === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Hero cache cleared successfully!', 'coreboost') . '</p></div>';
        }
    }
}
