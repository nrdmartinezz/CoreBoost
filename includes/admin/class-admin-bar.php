<?php
/**
 * Admin bar functionality
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Admin_Bar
 */
class Admin_Bar {
    
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
     * Add admin bar menu
     *
     * @param \WP_Admin_Bar $wp_admin_bar WordPress admin bar object
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_menu(array(
            'id'    => 'coreboost',
            'title' => __('CoreBoost', 'coreboost'),
            'href'  => admin_url('options-general.php?page=coreboost'),
            'meta'  => array(
                'title' => __('CoreBoost Settings', 'coreboost'),
            ),
        ));
        
        // Create nonce URL for cache clearing
        $clear_cache_url = add_query_arg(
            array(
                'coreboost_action' => 'clear_cache',
                '_wpnonce' => wp_create_nonce('coreboost_clear_cache_frontend')
            ),
            home_url(add_query_arg(array()))
        );
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'coreboost',
            'id'     => 'coreboost-clear-cache',
            'title'  => __('Clear Cache', 'coreboost'),
            'href'   => $clear_cache_url,
            'meta'   => array(
                'title' => __('Clear CoreBoost Cache', 'coreboost'),
            ),
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'coreboost',
            'id'     => 'coreboost-settings',
            'title'  => __('Settings', 'coreboost'),
            'href'   => admin_url('options-general.php?page=coreboost'),
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'coreboost',
            'id'     => 'coreboost-test-pagespeed',
            'title'  => __('Test PageSpeed', 'coreboost'),
            'href'   => 'https://pagespeed.web.dev/analysis?url=' . urlencode(home_url()),
            'meta'   => array(
                'target' => '_blank',
                'title'  => __('Test this page with PageSpeed Insights', 'coreboost'),
            ),
        ));
    }
}
