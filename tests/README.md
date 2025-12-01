# CoreBoost Testing Suite

Test the bulk image converter and AJAX endpoints without needing full WordPress admin access.

## 🧪 Test Methods

### Method 1: PHP Unit Tests (Standalone)

Run the PHP test script directly:

```bash
cd tests
php bulk-converter-test.php
```

**What it tests:**
- Batch size calculation logic
- Progress tracking calculations
- Error handling
- Nonce parameter handling
- Scan simulation with different image counts

**No WordPress required!** This uses mocked WordPress functions.

---

### Method 2: HTML AJAX Tester (Browser-Based)

1. Open `ajax-endpoint-test.html` in your browser
2. Enter your WordPress site URL
3. Get a nonce value:
   - Go to your WordPress admin → Settings → CoreBoost → Images tab
   - Open browser console (F12)
   - Run: `document.querySelector('input[name="coreboost_nonce"]').value`
   - Copy the value
4. Paste nonce into the test page
5. Click test buttons to test each endpoint

**What it tests:**
- Real AJAX calls to your WordPress site
- Scan uploads endpoint
- Process batch endpoint
- Stop conversion endpoint
- Clear cache endpoint

**Requires:** Active WordPress site with CoreBoost installed

---

### Method 3: Browser Console Testing

In your WordPress admin (Settings → CoreBoost → Images):

```javascript
// Get nonce
const nonce = document.querySelector('input[name="coreboost_nonce"]').value;

// Test scan
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        action: 'coreboost_scan_uploads',
        _wpnonce: nonce,
    }),
})
.then(r => r.json())
.then(data => console.log('Scan Result:', data))
.catch(err => console.error('Error:', err));

// Test batch processing
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        action: 'coreboost_bulk_convert_batch',
        batch: 1,
        _wpnonce: nonce,
    }),
})
.then(r => r.json())
.then(data => console.log('Batch Result:', data))
.catch(err => console.error('Error:', err));

// Test stop
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        action: 'coreboost_bulk_convert_stop',
        _wpnonce: nonce,
    }),
})
.then(r => r.json())
.then(data => console.log('Stop Result:', data))
.catch(err => console.error('Error:', err));
```

---

### Method 4: cURL Testing

Test from command line:

```bash
# Get nonce first (from browser console or form)
NONCE="your_nonce_here"
SITE_URL="https://your-site.com"

# Test scan
curl -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=coreboost_scan_uploads&_wpnonce=$NONCE"

# Test batch
curl -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=coreboost_bulk_convert_batch&batch=1&_wpnonce=$NONCE"

# Test stop
curl -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=coreboost_bulk_convert_stop&_wpnonce=$NONCE"
```

---

## 📊 Expected Responses

### Successful Scan Response
```json
{
  "success": true,
  "data": {
    "count": 42,
    "batch_size": 15,
    "total_batches": 3,
    "estimated_time_minutes": 1,
    "recommendation": "..."
  }
}
```

### Successful Batch Response
```json
{
  "success": true,
  "data": {
    "batch": 1,
    "processed": 15,
    "complete": false,
    "message": "Batch 1 processed successfully"
  }
}
```

### Error Response
```json
{
  "success": false,
  "data": {
    "message": "Security check failed"
  }
}
```

---

## 🔍 Troubleshooting

### 400 Bad Request
- **Cause**: Nonce validation failed
- **Fix**: Get fresh nonce value from form
- **Check**: `check_ajax_referer()` is using correct action name

### 403 Forbidden
- **Cause**: User doesn't have `manage_options` capability
- **Fix**: Log in as admin user

### Undefined ajaxurl
- **Cause**: JavaScript not properly localized
- **Fix**: Verify `wp_localize_script()` is called
- **Check**: `coreBoostAdmin.ajaxurl` exists in console

### Empty Response
- **Cause**: PHP error or fatal error
- **Fix**: Check WordPress debug.log
- **Enable**: `define('WP_DEBUG', true)` in wp-config.php

---

## 🎯 Quick Test Checklist

- [ ] PHP unit tests pass (Method 1)
- [ ] Scan endpoint returns image count (Method 2)
- [ ] Batch endpoint processes images (Method 2)
- [ ] Stop endpoint sets flag (Method 2)
- [ ] No 400 errors in browser console
- [ ] No JavaScript errors in console
- [ ] Nonce validation works
- [ ] Progress updates every 3 seconds
- [ ] Completion message displays

---

## 📝 Notes

- **Nonce expires**: Get fresh nonce if tests fail after ~12 hours
- **Cross-origin**: HTML tester must be on same domain as WordPress
- **Permissions**: Must be logged in as admin
- **Cache**: Clear browser cache if JavaScript doesn't update
