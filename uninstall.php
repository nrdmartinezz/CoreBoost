<?php
/**
 * Uninstall CoreBoost
 * 
 * Fired when the plugin is uninstalled via WordPress admin
 *
 * @package CoreBoost
 * @since 3.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
delete_option('coreboost_options');
delete_option('coreboost_version');
delete_option('coreboost_installed_at');
delete_option('coreboost_script_metrics');
delete_option('coreboost_pattern_effectiveness');
delete_option('coreboost_ab_tests');
delete_option('coreboost_image_variant_audit_log');

// Delete all backup options (from migrations)
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE 'coreboost_options_backup_%'"
);

// Delete all transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_coreboost_%' 
    OR option_name LIKE '_transient_timeout_coreboost_%'"
);

// Unschedule all cron jobs
wp_clear_scheduled_hook('coreboost_cleanup_orphaned_variants');

// Delete image variants directory
$upload_dir = wp_upload_dir();
$variants_dir = $upload_dir['basedir'] . '/coreboost-variants';

if (is_dir($variants_dir)) {
    // Recursively delete all files and subdirectories
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($variants_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    
    rmdir($variants_dir);
}

// Clear WordPress caches
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}