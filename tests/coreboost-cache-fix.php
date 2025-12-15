<?php
/**
 * Simple CoreBoost Cache Headers Fix
 * Upload to WordPress root and visit: https://your-site.com/coreboost-cache-fix.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load WordPress
$wp_paths = array(
    __DIR__ . '/wp-load.php',
    __DIR__ . '/../wp-load.php',
);

foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        break;
    }
}

if (!function_exists('wp_upload_dir')) {
    die('ERROR: Cannot load WordPress');
}

if (!class_exists('CoreBoost\\Core\\Variant_Cache_Headers')) {
    die('ERROR: CoreBoost plugin not active');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>CoreBoost Cache Fix</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .box { background: white; padding: 20px; border-radius: 8px; max-width: 800px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #0073aa; }
        .success { color: #46b450; font-weight: bold; }
        .error { color: #dc3232; font-weight: bold; }
        .code { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; }
        button { background: #0073aa; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #005177; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🔧 CoreBoost Cache Headers Fix</h1>

<?php
// Handle action
if (isset($_POST['action'])) {
    echo '<h2>Running Fix...</h2>';
    
    try {
        // Get upload directory
        $upload_dir = wp_upload_dir();
        $variants_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants';
        $htaccess_file = $variants_dir . DIRECTORY_SEPARATOR . '.htaccess';
        
        echo '<p><strong>Variants Directory:</strong><br><code>' . htmlspecialchars($variants_dir) . '</code></p>';
        
        // Check directory
        if (!is_dir($variants_dir)) {
            echo '<p class="error">❌ Directory does not exist. Creating...</p>';
            if (!wp_mkdir_p($variants_dir)) {
                echo '<p class="error">❌ FAILED to create directory</p>';
            } else {
                echo '<p class="success">✓ Directory created</p>';
            }
        } else {
            echo '<p class="success">✓ Directory exists</p>';
        }
        
        // Check if writable
        if (!is_writable($variants_dir)) {
            echo '<p class="error">❌ Directory is not writable!</p>';
        } else {
            echo '<p class="success">✓ Directory is writable</p>';
        }
        
        // Create .htaccess
        echo '<p>Creating .htaccess file...</p>';
        $result = \CoreBoost\Core\Variant_Cache_Headers::create_htaccess(true);
        
        if ($result) {
            echo '<p class="success">✓ SUCCESS - .htaccess created!</p>';
            
            // Read and display content
            if (file_exists($htaccess_file)) {
                $content = file_get_contents($htaccess_file);
                echo '<p><strong>.htaccess Content:</strong></p>';
                echo '<div class="code"><pre>' . htmlspecialchars($content) . '</pre></div>';
                
                echo '<p class="success">✓ File size: ' . filesize($htaccess_file) . ' bytes</p>';
            }
        } else {
            echo '<p class="error">❌ FAILED to create .htaccess</p>';
        }
        
        echo '<hr>';
        echo '<h3>Next Steps:</h3>';
        echo '<ol>';
        echo '<li>Clear your browser cache</li>';
        echo '<li>Clear any CDN/proxy cache (Cloudflare, etc.)</li>';
        echo '<li>Test PageSpeed Insights again</li>';
        echo '<li>Check cache headers with DevTools Network tab</li>';
        echo '</ol>';
        
    } catch (Exception $e) {
        echo '<p class="error">❌ ERROR: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
} else {
    // Show initial form
    $upload_dir = wp_upload_dir();
    $variants_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'coreboost-variants';
    $htaccess_file = $variants_dir . DIRECTORY_SEPARATOR . '.htaccess';
    
    echo '<p>This will create/update the .htaccess file for image variant cache headers.</p>';
    
    echo '<h3>Current Status:</h3>';
    echo '<ul>';
    echo '<li>Variants Directory: <code>' . htmlspecialchars($variants_dir) . '</code></li>';
    echo '<li>Directory Exists: ' . (is_dir($variants_dir) ? '<span class="success">YES</span>' : '<span class="error">NO</span>') . '</li>';
    echo '<li>.htaccess Exists: ' . (file_exists($htaccess_file) ? '<span class="success">YES</span>' : '<span class="error">NO</span>') . '</li>';
    
    if (file_exists($htaccess_file)) {
        echo '<li>File Size: ' . filesize($htaccess_file) . ' bytes</li>';
    }
    echo '</ul>';
    
    echo '<form method="post">';
    echo '<input type="hidden" name="action" value="fix">';
    echo '<button type="submit">🔧 Create/Update .htaccess</button>';
    echo '</form>';
}
?>

        <hr>
        <h3>💡 What This Does:</h3>
        <p>Creates an .htaccess file in the coreboost-variants directory with these cache headers:</p>
        <ul>
            <li><code>Cache-Control: public, max-age=31536000, immutable</code></li>
            <li><code>Expires: 365 days</code></li>
        </ul>
        
        <h3>🧪 Verify It Worked:</h3>
        <ol>
            <li>Open DevTools (F12) → Network tab</li>
            <li>Load your site and find an AVIF/WebP image</li>
            <li>Check Response Headers for the Cache-Control header above</li>
        </ol>
    </div>
</body>
</html>
