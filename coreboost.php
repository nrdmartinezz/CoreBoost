<?php
/**
 * Plugin Name: CoreBoost
 * Plugin URI: https://github.com/nrdmartinezz/coreboost
 * Description: Comprehensive site optimization plugin with LCP optimization for Elementor hero sections, advanced CSS deferring with critical CSS, Google Fonts & Adobe Fonts optimization, script optimization, and performance enhancements.
 * Version: 3.0.6
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

// CRITICAL: Kill all CoreBoost output in Elementor preview context BEFORE anything runs
// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
$elementor_preview = isset($_GET['elementor-preview']) ? sanitize_text_field( wp_unslash( $_GET['elementor-preview'] ) ) : '';
$action = isset($_POST['action']) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
if (!empty($elementor_preview) || (defined('DOING_AJAX') && DOING_AJAX && !empty($action) && strpos($action, 'elementor') !== false)) {
    // Early exit hook to prevent any CoreBoost output in Elementor contexts
    add_action('init', function() {
        // Ensure no frontend optimizers touch AJAX/preview requests
        define('COREBOOST_ELEMENTOR_PREVIEW', true);
    }, 0);
}

// Define plugin constants
define('COREBOOST_VERSION', '3.0.6');

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
 * Initialize GitHub-based updates (if available)
 * Note: For private repositories, ensure GITHUB_TOKEN environment variable is set
 * or manually add authentication in wp-config.php:
 * define('COREBOOST_GITHUB_TOKEN', 'your-github-token');
 */
$updateCheckerPath = COREBOOST_PLUGIN_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';
if (file_exists($updateCheckerPath)) {
    require_once $updateCheckerPath;
    use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

    try {
        $updateChecker = PucFactory::buildUpdateChecker(
            'https://github.com/nrdmartinezz/CoreBoost',
            __FILE__,
            'coreboost'
        );

        // Set authentication for private repositories
        // Check for GitHub token in wp-config.php constant first, then environment variable
        $githubToken = defined('COREBOOST_GITHUB_TOKEN') ? COREBOOST_GITHUB_TOKEN : getenv('GITHUB_TOKEN');
        
        if (!empty($githubToken)) {
            $updateChecker->setAuthentication($githubToken);
        }

        // Use GitHub releases
        $updateChecker->setBranch('main');
        
        // Only enable release assets if we can access them
        try {
            $updateChecker->getVcsApi()->enableReleaseAssets();
        } catch (Exception $e) {
            // Release assets may not be available for private repos without proper auth
            error_log('CoreBoost: Could not enable release assets: ' . $e->getMessage());
        }
    } catch (Exception $e) {
        // Silently fail if update checker can't be initialized
        // Plugin will still function normally
        error_log('CoreBoost: Update checker initialization failed: ' . $e->getMessage());
    }
}

/**
 * Load Phase 5 classes (Analytics & Dashboard)
 * These are global namespace classes that need manual loading
 */
require_once COREBOOST_PLUGIN_DIR . 'includes/public/class-analytics-engine.php';
require_once COREBOOST_PLUGIN_DIR . 'includes/admin/class-dashboard-ui.php';
require_once COREBOOST_PLUGIN_DIR . 'includes/public/class-performance-insights.php';

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
    delete_transient('coreboost_bg_videos_' . $post_id);
    delete_transient('coreboost_hero_preload_' . $post_id);
});
