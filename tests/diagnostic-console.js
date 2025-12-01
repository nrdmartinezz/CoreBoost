/**
 * CoreBoost Settings Diagnostic Tool
 * 
 * Run this in WordPress admin console to check your current settings
 * and diagnose why bulk converter might not be working.
 * 
 * Copy-paste into browser console on CoreBoost settings page.
 */

(function() {
    console.log('=== CoreBoost Bulk Converter Diagnostic ===\n');
    
    // Check if we're on the right page
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('page') !== 'coreboost') {
        console.error('❌ Not on CoreBoost settings page. Navigate to Settings → CoreBoost first.');
        return;
    }
    
    // Check if UI elements exist
    const elements = {
        startBtn: document.getElementById('coreboost-start-bulk'),
        stopBtn: document.getElementById('coreboost-stop-bulk'),
        nonceField: document.querySelector('input[name="coreboost_nonce"]'),
        formatCheckbox: document.querySelector('input[name="coreboost_options[enable_image_format_conversion]"]'),
    };
    
    console.log('1. UI Elements Check:');
    console.log('   Start Button:', elements.startBtn ? '✅ Found' : '❌ Missing');
    console.log('   Stop Button:', elements.stopBtn ? '✅ Found' : '❌ Missing');
    console.log('   Nonce Field:', elements.nonceField ? '✅ Found' : '❌ Missing');
    console.log('   Format Conversion Checkbox:', elements.formatCheckbox ? '✅ Found' : '❌ Missing');
    
    if (elements.nonceField) {
        console.log('   Nonce Value:', elements.nonceField.value ? '✅ Present' : '❌ Empty');
    }
    
    // Check format conversion setting
    console.log('\n2. Image Format Conversion Status:');
    if (elements.formatCheckbox) {
        const isEnabled = elements.formatCheckbox.checked;
        console.log('   Enabled:', isEnabled ? '✅ YES' : '❌ NO (This is likely the issue!)');
        
        if (!isEnabled) {
            console.warn('   ⚠️ SOLUTION: Check the "Generate AVIF/WebP Variants" checkbox and click "Save Changes"');
        }
    }
    
    if (elements.startBtn) {
        const formatEnabled = elements.startBtn.getAttribute('data-format-enabled');
        console.log('   Button data-format-enabled:', formatEnabled === '1' ? '✅ Enabled' : '❌ Disabled');
        console.log('   Button disabled state:', elements.startBtn.disabled ? '❌ Disabled' : '✅ Enabled');
    }
    
    // Check JavaScript globals
    console.log('\n3. JavaScript Configuration:');
    console.log('   coreBoostAdmin:', typeof coreBoostAdmin !== 'undefined' ? '✅ Loaded' : '❌ Missing');
    if (typeof coreBoostAdmin !== 'undefined') {
        console.log('   - ajaxurl:', coreBoostAdmin.ajaxurl || '❌ Missing');
        console.log('   - nonce:', coreBoostAdmin.nonce ? '✅ Present' : '❌ Missing');
    }
    console.log('   window.ajaxurl:', typeof ajaxurl !== 'undefined' ? '✅ ' + ajaxurl : '❌ Missing');
    
    // Try a test AJAX call
    console.log('\n4. Testing AJAX Endpoint...');
    
    const nonce = (typeof coreBoostAdmin !== 'undefined' ? coreBoostAdmin.nonce : elements.nonceField?.value) || '';
    const url = (typeof coreBoostAdmin !== 'undefined' ? coreBoostAdmin.ajaxurl : window.ajaxurl) || '/wp-admin/admin-ajax.php';
    
    if (!nonce) {
        console.error('   ❌ Cannot test: No nonce available');
        return;
    }
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'coreboost_scan_uploads',
            _wpnonce: nonce,
        }),
    })
    .then(response => {
        console.log('   Response Status:', response.status);
        if (response.status === 200) {
            console.log('   ✅ AJAX endpoint responding');
        } else if (response.status === 400) {
            console.error('   ❌ 400 Bad Request - Check error details below');
        } else {
            console.warn('   ⚠️ Unexpected status:', response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('   Response Data:', data);
        
        if (data.success) {
            console.log('   ✅ Scan successful!');
            console.log('   - Images found:', data.data.count);
            console.log('   - Batch size:', data.data.batch_size);
            console.log('   - Total batches:', data.data.total_batches);
        } else {
            console.error('   ❌ Error:', data.data?.message || 'Unknown error');
            
            if (data.data?.message?.includes('not initialized') || data.data?.message?.includes('AVIF/WebP')) {
                console.warn('   💡 SOLUTION: Enable "Generate AVIF/WebP Variants" checkbox below and save settings.');
            }
        }
    })
    .catch(error => {
        console.error('   ❌ AJAX request failed:', error.message);
    });
    
    console.log('\n=== Diagnostic Complete ===');
    console.log('\nIf you see errors above:');
    console.log('1. Enable "Generate AVIF/WebP Variants" checkbox');
    console.log('2. Click "Save Changes" button');
    console.log('3. Refresh the page');
    console.log('4. Run this diagnostic again');
    
})();
