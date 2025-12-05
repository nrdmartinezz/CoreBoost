<?php
/**
 * Image Conversion Diagnostic Tool
 * 
 * Run this script to diagnose image conversion issues in CoreBoost
 * 
 * Usage: php tests/diagnose-image-conversion.php
 */

echo "===========================================\n";
echo "CoreBoost Image Conversion Diagnostics\n";
echo "===========================================\n\n";

// 1. PHP Version Check
echo "1. PHP VERSION\n";
echo "   Version: " . PHP_VERSION . "\n";
echo "   Required: 7.4+\n";
echo "   Status: " . (version_compare(PHP_VERSION, '7.4.0') >= 0 ? '✓ OK' : '✗ FAIL') . "\n\n";

// 2. GD Library Check
echo "2. GD LIBRARY\n";
$gd_loaded = extension_loaded('gd');
echo "   Extension Loaded: " . ($gd_loaded ? '✓ YES' : '✗ NO') . "\n";

if ($gd_loaded) {
    $gd_info = gd_info();
    echo "   GD Version: " . ($gd_info['GD Version'] ?? 'Unknown') . "\n";
    echo "   JPEG Support: " . (($gd_info['JPEG Support'] ?? false) ? '✓ YES' : '✗ NO') . "\n";
    echo "   PNG Support: " . (($gd_info['PNG Support'] ?? false) ? '✓ YES' : '✗ NO') . "\n";
    echo "   WebP Support: " . (($gd_info['WebP Support'] ?? false) ? '✓ YES' : '✗ NO') . "\n";
    echo "   AVIF Support: " . (($gd_info['AVIF Support'] ?? false) ? '✓ YES' : '✗ NO') . "\n";
} else {
    echo "   ✗ GD library not installed!\n";
}
echo "\n";

// 3. Image Function Availability
echo "3. IMAGE FUNCTIONS\n";
echo "   imagecreatefromjpeg: " . (function_exists('imagecreatefromjpeg') ? '✓ YES' : '✗ NO') . "\n";
echo "   imagecreatefrompng: " . (function_exists('imagecreatefrompng') ? '✓ YES' : '✗ NO') . "\n";
echo "   imagewebp: " . (function_exists('imagewebp') ? '✓ YES' : '✗ NO') . "\n";
echo "   imageavif: " . (function_exists('imageavif') ? '✓ YES (PHP 8.1+)' : '✗ NO (Requires PHP 8.1+)') . "\n";
echo "\n";

// 4. File System Checks
echo "4. FILE SYSTEM\n";

// Check if we're in WordPress context
$in_wordpress = defined('ABSPATH');
echo "   WordPress Context: " . ($in_wordpress ? '✓ YES' : '✗ NO (Standalone)') . "\n";

if ($in_wordpress) {
    $upload_dir = wp_upload_dir();
    $uploads_path = $upload_dir['basedir'];
    $variants_dir = $uploads_path . '/coreboost-variants';
    
    echo "   Uploads Directory: $uploads_path\n";
    echo "   Uploads Writable: " . (is_writable($uploads_path) ? '✓ YES' : '✗ NO') . "\n";
    echo "   Variants Directory: $variants_dir\n";
    echo "   Variants Exists: " . (is_dir($variants_dir) ? '✓ YES' : '✗ NO') . "\n";
    
    if (is_dir($variants_dir)) {
        echo "   Variants Writable: " . (is_writable($variants_dir) ? '✓ YES' : '✗ NO') . "\n";
        
        // Count variant files
        $avif_count = count(glob($variants_dir . '/**/*.avif', GLOB_NOSORT));
        $webp_count = count(glob($variants_dir . '/**/*.webp', GLOB_NOSORT));
        echo "   AVIF Files: $avif_count\n";
        echo "   WebP Files: $webp_count\n";
    }
} else {
    $temp_dir = sys_get_temp_dir();
    echo "   Temp Directory: $temp_dir\n";
    echo "   Temp Writable: " . (is_writable($temp_dir) ? '✓ YES' : '✗ NO') . "\n";
}
echo "\n";

// 5. Memory and Resource Limits
echo "5. RESOURCE LIMITS\n";
echo "   Memory Limit: " . ini_get('memory_limit') . "\n";
echo "   Max Execution Time: " . ini_get('max_execution_time') . "s\n";
echo "   Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "   Post Max Size: " . ini_get('post_max_size') . "\n";
echo "\n";

// 6. Test Image Conversion
echo "6. LIVE CONVERSION TEST\n";

if (!$gd_loaded) {
    echo "   ✗ Cannot test - GD library not available\n\n";
} else {
    echo "   Creating test image...\n";
    
    // Create a test image
    $test_image = imagecreatetruecolor(100, 100);
    $red = imagecolorallocate($test_image, 255, 0, 0);
    imagefilledrectangle($test_image, 0, 0, 100, 100, $red);
    
    $temp_dir = sys_get_temp_dir();
    $test_jpeg = $temp_dir . '/coreboost-test-' . uniqid() . '.jpg';
    
    $jpeg_created = imagejpeg($test_image, $test_jpeg, 90);
    imagedestroy($test_image);
    
    if ($jpeg_created) {
        echo "   ✓ Test JPEG created: $test_jpeg\n";
        echo "   File size: " . filesize($test_jpeg) . " bytes\n";
        
        // Try WebP conversion
        if (function_exists('imagewebp')) {
            $test_webp = $temp_dir . '/coreboost-test-' . uniqid() . '.webp';
            $img = imagecreatefromjpeg($test_jpeg);
            
            if ($img) {
                $webp_result = @imagewebp($img, $test_webp, 85);
                imagedestroy($img);
                
                if ($webp_result && file_exists($test_webp)) {
                    echo "   ✓ WebP conversion successful!\n";
                    echo "   WebP size: " . filesize($test_webp) . " bytes\n";
                    echo "   Compression ratio: " . round((1 - filesize($test_webp) / filesize($test_jpeg)) * 100) . "%\n";
                    @unlink($test_webp);
                } else {
                    echo "   ✗ WebP conversion failed\n";
                    echo "   Error: " . (error_get_last()['message'] ?? 'Unknown') . "\n";
                }
            } else {
                echo "   ✗ Could not load test JPEG for WebP conversion\n";
            }
        } else {
            echo "   - WebP test skipped (function not available)\n";
        }
        
        // Try AVIF conversion
        if (function_exists('imageavif')) {
            $test_avif = $temp_dir . '/coreboost-test-' . uniqid() . '.avif';
            $img = imagecreatefromjpeg($test_jpeg);
            
            if ($img) {
                $avif_result = @imageavif($img, $test_avif, 85);
                imagedestroy($img);
                
                if ($avif_result && file_exists($test_avif)) {
                    echo "   ✓ AVIF conversion successful!\n";
                    echo "   AVIF size: " . filesize($test_avif) . " bytes\n";
                    echo "   Compression ratio: " . round((1 - filesize($test_avif) / filesize($test_jpeg)) * 100) . "%\n";
                    @unlink($test_avif);
                } else {
                    echo "   ✗ AVIF conversion failed\n";
                    echo "   Error: " . (error_get_last()['message'] ?? 'Unknown') . "\n";
                }
            } else {
                echo "   ✗ Could not load test JPEG for AVIF conversion\n";
            }
        } else {
            echo "   - AVIF test skipped (function not available - requires PHP 8.1+)\n";
        }
        
        @unlink($test_jpeg);
    } else {
        echo "   ✗ Failed to create test JPEG\n";
    }
}
echo "\n";

// 7. Recommendations
echo "7. RECOMMENDATIONS\n";

$issues = [];
$warnings = [];

if (!$gd_loaded) {
    $issues[] = "Install PHP GD extension";
}

if (!function_exists('imagewebp')) {
    $issues[] = "WebP support not available - update GD library";
}

if (!function_exists('imageavif')) {
    $warnings[] = "AVIF support not available - upgrade to PHP 8.1+ for optimal performance";
}

if (version_compare(PHP_VERSION, '8.1.0') < 0) {
    $warnings[] = "PHP 8.1+ recommended for AVIF support";
}

if ($in_wordpress && isset($variants_dir) && !is_dir($variants_dir)) {
    $warnings[] = "Variants directory doesn't exist - will be created on first conversion";
}

if (ini_get('memory_limit') !== '-1' && intval(ini_get('memory_limit')) < 256) {
    $warnings[] = "Low memory limit may affect large image processing";
}

if (!empty($issues)) {
    echo "   CRITICAL ISSUES:\n";
    foreach ($issues as $issue) {
        echo "   ✗ $issue\n";
    }
}

if (!empty($warnings)) {
    echo "   WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "   ⚠ $warning\n";
    }
}

if (empty($issues) && empty($warnings)) {
    echo "   ✓ All systems operational!\n";
}

echo "\n";
echo "===========================================\n";
echo "Diagnostic Complete\n";
echo "===========================================\n";

// Return exit code based on critical issues
exit(empty($issues) ? 0 : 1);
