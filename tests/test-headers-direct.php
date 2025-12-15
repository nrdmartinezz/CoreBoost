<?php
/**
 * Test Cache Headers - Direct Header Check
 * Upload to WordPress root: test-headers-direct.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load WordPress
foreach (array(__DIR__ . '/wp-load.php', __DIR__ . '/../wp-load.php') as $path) {
    if (file_exists($path)) {
        require_once($path);
        break;
    }
}

if (!function_exists('wp_upload_dir')) {
    die('ERROR: Cannot load WordPress');
}

$upload_dir = wp_upload_dir();
$variants_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants';
$htaccess_file = $variants_dir . DIRECTORY_SEPARATOR . '.htaccess';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Cache Headers</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .box { background: white; padding: 20px; border-radius: 8px; max-width: 900px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #0073aa; }
        .success { color: #46b450; }
        .error { color: #dc3232; }
        .warning { color: #f56e28; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #0073aa; color: white; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🔍 Cache Headers Diagnostic</h1>

        <h2>1. Server Info</h2>
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>Server Software</td>
                <td><?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></td>
            </tr>
            <tr>
                <td>Is Apache?</td>
                <td class="<?php echo (stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'apache') !== false) ? 'success' : 'error'; ?>">
                    <?php echo (stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'apache') !== false) ? '✓ YES' : '✗ NO'; ?>
                </td>
            </tr>
            <tr>
                <td>Is Nginx?</td>
                <td class="<?php echo (stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'nginx') !== false) ? 'warning' : ''; ?>">
                    <?php echo (stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'nginx') !== false) ? '⚠ YES (needs nginx config)' : 'NO'; ?>
                </td>
            </tr>
        </table>

        <h2>2. .htaccess File</h2>
        <table>
            <tr>
                <th>Check</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>Directory Exists</td>
                <td class="<?php echo is_dir($variants_dir) ? 'success' : 'error'; ?>">
                    <?php echo is_dir($variants_dir) ? '✓ YES' : '✗ NO'; ?>
                </td>
            </tr>
            <tr>
                <td>.htaccess Exists</td>
                <td class="<?php echo file_exists($htaccess_file) ? 'success' : 'error'; ?>">
                    <?php echo file_exists($htaccess_file) ? '✓ YES' : '✗ NO'; ?>
                </td>
            </tr>
            <?php if (file_exists($htaccess_file)): ?>
            <tr>
                <td>File Size</td>
                <td><?php echo filesize($htaccess_file); ?> bytes</td>
            </tr>
            <tr>
                <td>Readable</td>
                <td class="<?php echo is_readable($htaccess_file) ? 'success' : 'error'; ?>">
                    <?php echo is_readable($htaccess_file) ? '✓ YES' : '✗ NO'; ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <?php if (file_exists($htaccess_file)): ?>
        <h3>.htaccess Content:</h3>
        <pre><?php echo htmlspecialchars(file_get_contents($htaccess_file)); ?></pre>
        <?php endif; ?>

        <h2>3. Find Variant Files to Test</h2>
        <?php
        $test_files = array();
        if (is_dir($variants_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($variants_dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            $count = 0;
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), array('avif', 'webp'))) {
                    $test_files[] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file->getPathname());
                    $count++;
                    if ($count >= 3) break; // Only show first 3
                }
            }
        }
        
        if (empty($test_files)) {
            echo '<p class="warning">⚠ No AVIF/WebP files found in variants directory</p>';
        } else {
            echo '<p class="success">✓ Found ' . count($test_files) . ' test files</p>';
        }
        ?>

        <h2>4. Test Actual Headers</h2>
        <?php if (!empty($test_files)): ?>
            <p>Testing headers from actual variant files:</p>
            
            <?php foreach ($test_files as $test_url): ?>
                <h3>Testing: <code><?php echo basename($test_url); ?></code></h3>
                <?php
                // Try to fetch headers
                $headers = @get_headers($test_url, 1);
                
                if ($headers) {
                    echo '<table>';
                    echo '<tr><th>Header</th><th>Value</th><th>Status</th></tr>';
                    
                    // Check Cache-Control
                    $cache_control = $headers['Cache-Control'] ?? 'Not Set';
                    $has_cache = (is_string($cache_control) && strpos($cache_control, 'max-age=31536000') !== false);
                    echo '<tr>';
                    echo '<td><strong>Cache-Control</strong></td>';
                    echo '<td><code>' . htmlspecialchars($cache_control) . '</code></td>';
                    echo '<td class="' . ($has_cache ? 'success' : 'error') . '">';
                    echo $has_cache ? '✓ CORRECT' : '✗ MISSING/WRONG';
                    echo '</td>';
                    echo '</tr>';
                    
                    // Check Expires
                    $expires = $headers['Expires'] ?? 'Not Set';
                    echo '<tr>';
                    echo '<td><strong>Expires</strong></td>';
                    echo '<td><code>' . htmlspecialchars($expires) . '</code></td>';
                    echo '<td>' . ($expires !== 'Not Set' ? '✓' : '✗') . '</td>';
                    echo '</tr>';
                    
                    // Check Content-Type
                    $content_type = $headers['Content-Type'] ?? 'Unknown';
                    echo '<tr>';
                    echo '<td><strong>Content-Type</strong></td>';
                    echo '<td><code>' . htmlspecialchars($content_type) . '</code></td>';
                    echo '<td></td>';
                    echo '</tr>';
                    
                    echo '</table>';
                    
                    echo '<p><strong>Full URL:</strong> <a href="' . htmlspecialchars($test_url) . '" target="_blank">' . htmlspecialchars($test_url) . '</a></p>';
                    
                } else {
                    echo '<p class="error">✗ Could not fetch headers (file might not be accessible)</p>';
                }
                ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="warning">No files to test. Generate some image variants first.</p>
        <?php endif; ?>

        <h2>5. Diagnosis</h2>
        <?php
        $is_apache = stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'apache') !== false;
        $is_nginx = stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'nginx') !== false;
        $htaccess_exists = file_exists($htaccess_file);
        
        if ($is_nginx) {
            echo '<div style="background: #f56e28; color: white; padding: 15px; border-radius: 4px;">';
            echo '<h3 style="margin-top: 0; color: white;">⚠ NGINX DETECTED</h3>';
            echo '<p><strong>Problem:</strong> .htaccess files do NOT work on Nginx servers!</p>';
            echo '<p><strong>Solution:</strong> You need to add cache headers to your Nginx configuration file.</p>';
            echo '<p><strong>Add this to your nginx config:</strong></p>';
            echo '<pre style="background: #fff; color: #333;">location ~* /wp-content/uploads/coreboost-variants/.*\.(avif|webp)$ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    expires 365d;
    access_log off;
}</pre>';
            echo '<p>Then reload nginx: <code>sudo nginx -s reload</code></p>';
            echo '</div>';
        } elseif (!$htaccess_exists) {
            echo '<p class="error">✗ .htaccess file missing - run the cache fix script first</p>';
        } elseif (!empty($test_files)) {
            $has_correct_headers = false;
            foreach ($test_files as $test_url) {
                $headers = @get_headers($test_url, 1);
                if ($headers && isset($headers['Cache-Control']) && strpos($headers['Cache-Control'], 'max-age=31536000') !== false) {
                    $has_correct_headers = true;
                    break;
                }
            }
            
            if ($has_correct_headers) {
                echo '<div style="background: #46b450; color: white; padding: 15px; border-radius: 4px;">';
                echo '<h3 style="margin-top: 0; color: white;">✓ HEADERS ARE WORKING!</h3>';
                echo '<p>Cache headers are being sent correctly.</p>';
                echo '<p><strong>Next steps:</strong></p>';
                echo '<ul>';
                echo '<li>Clear browser cache completely</li>';
                echo '<li>Clear CDN cache (Cloudflare, etc.)</li>';
                echo '<li>Wait 5-10 minutes for DNS/cache propagation</li>';
                echo '<li>Test PageSpeed Insights again</li>';
                echo '</ul>';
                echo '</div>';
            } else {
                echo '<div style="background: #dc3232; color: white; padding: 15px; border-radius: 4px;">';
                echo '<h3 style="margin-top: 0; color: white;">✗ HEADERS NOT WORKING</h3>';
                echo '<p><strong>Possible issues:</strong></p>';
                echo '<ul>';
                echo '<li><strong>mod_headers not enabled:</strong> Contact your host to enable Apache mod_headers</li>';
                echo '<li><strong>mod_expires not enabled:</strong> Contact your host to enable Apache mod_expires</li>';
                echo '<li><strong>AllowOverride not set:</strong> Apache config needs "AllowOverride All" for the uploads directory</li>';
                echo '<li><strong>Local Dev Server:</strong> Local by Flywheel might not apply .htaccess properly</li>';
                echo '</ul>';
                echo '<p><strong>Test manually:</strong> Open DevTools (F12) → Network tab → Load an AVIF/WebP file → Check Response Headers</p>';
                echo '</div>';
            }
        }
        ?>

        <hr>
        <p><a href="coreboost-cache-fix.php">← Back to Cache Fix</a></p>
    </div>
</body>
</html>
