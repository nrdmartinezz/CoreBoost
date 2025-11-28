<?php
/**
 * Plugin Name: CoreBoost
 * Plugin URI: https://github.com/nrdmartinezz/coreboost
 * Description: Comprehensive site optimization plugin with LCP optimization for Elementor hero sections, advanced CSS deferring with critical CSS, Google Fonts & Adobe Fonts optimization, script optimization, and performance enhancements.
 * Version: 1.2.0
 * Author: nrdmartinezz
 * Author URI: https://github.com/nrdmartinezz
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: coreboost
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('COREBOOST_VERSION', '1.2.0');
define('COREBOOST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('COREBOOST_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COREBOOST_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Load the autoloader
 */
require_once COREBOOST_PLUGIN_DIR . 'includes/class-autoloader.php';

/**
 * Register the autoloader
 */
CoreBoost\Autoloader::register();

/**
 * Plugin activation hook
 */
function coreboost_activate() {
    CoreBoost\Activator::activate();
}
register_activation_hook(__FILE__, 'coreboost_activate');

/**
 * Plugin deactivation hook
 */
function coreboost_deactivate() {
    CoreBoost\Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'coreboost_deactivate');

/**
 * Initialize the plugin
 */
function coreboost_run() {
    $plugin = CoreBoost\CoreBoost::get_instance();
    $plugin->run();
}

// Hook into plugins_loaded to ensure all plugins are loaded
add_action('plugins_loaded', 'coreboost_run');

/**
 * Clear cache when Elementor data is updated
 */
add_action('elementor/editor/after_save', function($post_id) {
    delete_transient('coreboost_hero_' . $post_id);
});
