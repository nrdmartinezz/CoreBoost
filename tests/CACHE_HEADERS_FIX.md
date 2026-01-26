# CoreBoost Cache Headers Fix

## Quick Fix - Run in WordPress

Copy and paste this code into **Appearance > Theme Editor** (in functions.php) or use a code snippet plugin:

```php
<?php
// Force create .htaccess with cache headers
add_action('init', function() {
    if (isset($_GET['coreboost_fix_cache'])) {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        echo '<h1>CoreBoost Cache Headers Fix</h1>';
        
        // Force create .htaccess
        $result = \CoreBoost\Core\Variant_Cache_Headers::create_htaccess(true);
        
        echo '<p><strong>Result:</strong> ' . ($result ? 'SUCCESS ✓' : 'FAILED ✗') . '</p>';
        
        // Get status
        $status = \CoreBoost\Core\Variant_Cache_Headers::debug_status();
        
        echo '<h2>Status:</h2>';
        echo '<ul>';
        echo '<li>Directory exists: ' . ($status['dir_exists'] ? 'YES' : 'NO') . '</li>';
        echo '<li>.htaccess exists: ' . ($status['htaccess_exists'] ? 'YES' : 'NO') . '</li>';
        echo '<li>Directory: ' . htmlspecialchars($status['variants_dir']) . '</li>';
        echo '</ul>';
        
        if ($status['htaccess_exists']) {
            echo '<h3>.htaccess content:</h3>';
            echo '<pre>' . htmlspecialchars($status['htaccess_content']) . '</pre>';
        }
        
        echo '<p><a href="' . admin_url() . '">Back to Admin</a></p>';
        
        exit;
    }
});
```

Then visit: `https://your-site.com/?coreboost_fix_cache=1`

---

## Alternative: WP-CLI Command

If you have SSH/command line access:

```bash
wp eval 'CoreBoost\Core\Variant_Cache_Headers::create_htaccess(true);'
```

---

## Alternative: PHP Code Snippet

Use a code snippet plugin (Code Snippets, WPCode, etc.) and run this once:

```php
\CoreBoost\Core\Variant_Cache_Headers::create_htaccess(true);
```

---

## Manual .htaccess Creation

If all else fails, manually create this file:

**Path:** `/wp-content/uploads/coreboost-variants/.htaccess`

**Content:**
```apache
# CoreBoost Image Variant Cache Headers
# Generated automatically - do not edit manually
# Cache lifetime: 365 days (31536000 seconds)

# Enable expires module
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/avif "access plus 365 days"
    ExpiresByType image/webp "access plus 365 days"
</IfModule>

# Set cache control headers
<IfModule mod_headers.c>
    <FilesMatch "\.(avif|webp)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
        Header unset Last-Modified
    </FilesMatch>
</IfModule>

# Prevent directory listing
Options -Indexes
```

---

## Verify It's Working

1. Open browser DevTools (F12)
2. Go to Network tab
3. Load your site
4. Find an AVIF or WebP file
5. Check Response Headers for:
   - `Cache-Control: public, max-age=31536000, immutable`
   - `Expires:` (date 1 year in future)

---

## If Using Nginx

.htaccess won't work on Nginx. Add this to your nginx config:

```nginx
location ~* /wp-content/uploads/coreboost-variants/.*\.(avif|webp)$ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    expires 365d;
    access_log off;
}
```

Then reload nginx: `sudo nginx -s reload`
