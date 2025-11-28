# CoreBoost v2.0.0 Manual Testing Checklist

## Pre-Testing Setup

- [ ] WordPress 6.1+ installed
- [ ] PHP 7.4+ configured
- [ ] Backup database and files
- [ ] Test on staging/local environment (NOT production)

---

## Installation & Activation

### Fresh Install
- [ ] Upload plugin ZIP via WordPress admin
- [ ] Activate plugin successfully
- [ ] No PHP errors in debug log
- [ ] CoreBoost menu appears in admin sidebar
- [ ] All 5 settings tabs load (Hero, Scripts, CSS, GTM, Advanced)

### Upgrade from v1.x (if applicable)
- [ ] Deactivate old version
- [ ] Install v2.0.0
- [ ] Activate successfully
- [ ] Settings preserved from previous version
- [ ] No database errors

---

## Core Functionality Tests

### 1. Hero Image Optimization
- [ ] **Elementor Detection**: Create page with Elementor hero section
  - [ ] Visit page in incognito mode
  - [ ] View page source - check for `<link rel="preload" ... fetchpriority="high">`
  - [ ] Verify hero image has `fetchpriority="high"` attribute
  - [ ] No lazy loading on hero image

- [ ] **Featured Image Detection**: Create post with featured image
  - [ ] Enable "Featured Image as Hero" option
  - [ ] Check preload tag in source
  - [ ] Verify fetchpriority attribute

- [ ] **Manual URL**: Add custom hero image URL in settings
  - [ ] Save settings
  - [ ] Check preload tag appears on specified page

### 2. Script Optimization
- [ ] Enable script defer/async in settings
- [ ] Visit frontend page
- [ ] View source - scripts should have `defer` or `async`
- [ ] Check jQuery is NOT deferred (dependency preservation)
- [ ] Verify Contact Form 7 / Elementor scripts work correctly
- [ ] Test AJAX functionality (comments, forms)

### 3. CSS Optimization
- [ ] Enable CSS defer in settings
- [ ] Add critical CSS for homepage
- [ ] Visit homepage
- [ ] View source - critical CSS should be `<style>` inline
- [ ] Non-critical CSS should use preload method
- [ ] No FOUC (Flash of Unstyled Content)
- [ ] Check noscript fallback exists

### 4. Font Optimization
- [ ] Page uses Google Fonts
- [ ] Check for `<link rel="preconnect" href="https://fonts.googleapis.com">`
- [ ] Verify `display=swap` parameter on font URLs
- [ ] Adobe Fonts detected if used

---

## GTM Management Tests (v2.0.0 Feature)

### GTM Conflict Detection

#### Test 1: No Existing GTM (Clean Site)
- [ ] Navigate to CoreBoost → GTM & Tracking tab
- [ ] Detection widget shows **green checkmark** "No conflicts detected"
- [ ] Enable GTM toggle is **active** (not disabled)
- [ ] Enter container ID: `GTM-XXXXXXX` (use your real ID)
- [ ] Select load strategy: **Balanced (3 seconds)** (default)
- [ ] Save settings
- [ ] Visit frontend page
- [ ] View source - GTM script should be present
- [ ] Check browser console - no GTM errors
- [ ] Open Google Tag Manager → Preview mode → Verify tags fire

#### Test 2: GTM4WP Plugin Installed
- [ ] Install "Google Tag Manager for WordPress" (GTM4WP) plugin
- [ ] Activate GTM4WP
- [ ] Navigate to CoreBoost → GTM & Tracking tab
- [ ] Detection widget shows **red warning** with conflict details
- [ ] Warning shows: "GTM4WP detected with container GTM-XXXXX"
- [ ] Enable GTM toggle is **disabled** (grayed out)
- [ ] Frontend shows GTM from GTM4WP only (no duplicate)
- [ ] Deactivate GTM4WP
- [ ] Click "Re-scan for GTM" button
- [ ] Detection widget should turn **green** (conflict resolved)

#### Test 3: Theme Hardcoded GTM
- [ ] Add GTM code directly to theme's `header.php`:
  ```html
  <!-- Google Tag Manager -->
  <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
  'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
  })(window,document,'script','dataLayer','GTM-XXXXXXX');</script>
  <!-- End Google Tag Manager -->
  ```
- [ ] Navigate to CoreBoost → GTM & Tracking tab
- [ ] Detection widget shows **red warning** "GTM found in theme files"
- [ ] Shows file path: `header.php` with container ID
- [ ] Enable toggle is **disabled**
- [ ] Remove GTM code from theme
- [ ] Click "Re-scan for GTM"
- [ ] Detection clears successfully

### GTM Load Strategies

#### Test 4: Immediate Load
- [ ] Ensure no GTM conflicts
- [ ] Enable GTM in CoreBoost
- [ ] Select load strategy: **Immediate**
- [ ] Save settings
- [ ] Visit frontend page
- [ ] View source - GTM script has `async` attribute (standard loading)
- [ ] Open browser DevTools → Network tab
- [ ] GTM loads immediately on page load (no delay)

#### Test 5: Balanced Load (3 seconds)
- [ ] Select load strategy: **Balanced (3 seconds)** (default)
- [ ] Save settings
- [ ] Clear browser cache
- [ ] Visit frontend page in incognito mode
- [ ] Open DevTools → Network tab → Filter: `gtm.js`
- [ ] Page loads
- [ ] Wait ~3 seconds
- [ ] GTM script should load after 3 second delay
- [ ] Check console for `window.coreboostGTM` object

#### Test 6: Aggressive Load (5 seconds)
- [ ] Select load strategy: **Aggressive (5 seconds)**
- [ ] Save settings
- [ ] Clear cache, visit page in incognito
- [ ] GTM should load after ~5 second delay
- [ ] Verify in Network tab

#### Test 7: User Interaction Load
- [ ] Select load strategy: **User Interaction**
- [ ] Save settings
- [ ] Visit page in incognito mode
- [ ] **Do NOT scroll or click** for 5 seconds
- [ ] GTM should NOT load yet
- [ ] **Scroll page** or **move mouse**
- [ ] GTM should load immediately after interaction
- [ ] Verify in Network tab

#### Test 8: Browser Idle Load
- [ ] Select load strategy: **Browser Idle**
- [ ] Save settings
- [ ] Visit page in incognito
- [ ] GTM loads when browser is idle (uses `requestIdleCallback`)
- [ ] Timing varies based on browser activity
- [ ] Check Network tab for gtm.js load

#### Test 9: Custom Delay
- [ ] Select load strategy: **Custom delay**
- [ ] Enter custom delay: **7000** (7 seconds)
- [ ] Save settings
- [ ] Visit page, GTM should load after 7 seconds
- [ ] Verify timing in Network tab

### GTM Integration Tests

#### Test 10: GTM + Script Optimizer
- [ ] Enable GTM in CoreBoost
- [ ] Enable Script Defer/Async
- [ ] Visit frontend page
- [ ] View source
- [ ] GTM scripts should **NOT** have defer/async attributes
- [ ] GTM loads according to selected strategy (not affected by optimizer)
- [ ] Other scripts still have defer/async applied

#### Test 11: GTM Noscript Fallback
- [ ] GTM enabled in CoreBoost
- [ ] View page source
- [ ] Search for `<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-`
- [ ] Noscript should be right after `<body>` tag (or in footer if theme doesn't support `wp_body_open`)
- [ ] Disable JavaScript in browser
- [ ] Visit page - GTM iframe should be visible (for tracking without JS)

#### Test 12: Container ID Validation
- [ ] Navigate to GTM settings
- [ ] Enter **invalid** container ID: `GTM-123` (too short)
- [ ] Save settings
- [ ] Error message should appear: "Invalid format"
- [ ] Enter valid ID: `GTM-XXXXXXX`
- [ ] Saves successfully

---

## Performance Tests

### Before/After Comparison
- [ ] Install Query Monitor or similar plugin
- [ ] Test page **without** CoreBoost active
  - [ ] Note: Total page size, # of requests, load time, LCP score
- [ ] Activate CoreBoost with recommended settings
  - [ ] Hero optimization: ON
  - [ ] Script defer: ON
  - [ ] CSS defer: ON with critical CSS
  - [ ] GTM: Balanced (3s)
- [ ] Test same page **with** CoreBoost
  - [ ] Compare: Page size, requests, load time, LCP
  - [ ] LCP should improve by 20-40%
  - [ ] Blocking scripts reduced

### PageSpeed Insights
- [ ] Test URL on https://pagespeed.web.dev/
- [ ] **Before CoreBoost**: Note mobile/desktop scores
- [ ] **After CoreBoost**: Scores should improve
- [ ] Check "Reduce unused JavaScript" - should see GTM delayed
- [ ] Check "Largest Contentful Paint" - should improve with hero preload

---

## Compatibility Tests

### Theme Compatibility
- [ ] Test with Astra theme
- [ ] Test with GeneratePress theme
- [ ] Test with Kadence theme
- [ ] Test with active theme on production site

### Plugin Compatibility
- [ ] **Elementor**: Hero detection works, editor loads correctly
- [ ] **WooCommerce**: Product pages load, checkout works
- [ ] **Contact Form 7**: Forms submit successfully
- [ ] **Yoast SEO**: No conflicts
- [ ] **WP Rocket** (if applicable): No caching conflicts
- [ ] **GTM4WP**: Conflict detection works, defers correctly

### WordPress Versions
- [ ] WordPress 6.4+
- [ ] WordPress 6.3
- [ ] WordPress 6.2 (minimum supported)

### PHP Versions
- [ ] PHP 8.2
- [ ] PHP 8.1
- [ ] PHP 8.0
- [ ] PHP 7.4 (minimum supported)

---

## Admin Interface Tests

### Settings Tabs
- [ ] **Hero Images & LCP tab**: All options save correctly
- [ ] **Script Optimization tab**: Defer/async toggles work
- [ ] **CSS & Critical CSS tab**: Critical CSS textarea saves
- [ ] **GTM & Tracking tab**: All GTM settings save
- [ ] **Advanced Settings tab**: Cache clearing works

### Debug Mode
- [ ] Enable debug mode in Advanced settings
- [ ] Visit frontend page
- [ ] View source - HTML comments should show CoreBoost operations
- [ ] Comments like: `<!-- CoreBoost: GTM loaded with balanced strategy -->`

### Cache Clearing
- [ ] Click "Clear All Caches" button
- [ ] Confirmation message appears
- [ ] Caches cleared (transients, object cache if applicable)
- [ ] GTM detection cache cleared

### Admin Bar
- [ ] Admin bar shows CoreBoost menu item
- [ ] Quick links to settings work
- [ ] Cache clearing from admin bar works

---

## Critical Issues to Check

### Must Not Happen:
- [ ] **No white screen of death** (fatal PHP errors)
- [ ] **No 500 errors** on any page
- [ ] **No duplicate GTM containers** (verify in source - should only see ONE GTM container)
- [ ] **No broken AJAX** (forms, comments, etc.)
- [ ] **No Elementor editor breaking** (can still edit pages)
- [ ] **jQuery still loads before dependent scripts**
- [ ] **Critical CSS doesn't break layout** (no FOUC)
- [ ] **No JavaScript console errors** from CoreBoost

### Should Happen:
- [ ] **LCP improves** (measured via PageSpeed Insights)
- [ ] **GTM conflict detection works** (no duplicates)
- [ ] **Scripts defer without breaking functionality**
- [ ] **Hero images preload correctly**
- [ ] **Settings save and persist**
- [ ] **Plugin deactivates cleanly** (no orphaned data)

---

## Final Checks Before Release

- [ ] All tests above passed ✅
- [ ] No critical issues found
- [ ] Performance improvements verified
- [ ] GTM management works correctly
- [ ] Conflict detection reliable
- [ ] Elementor hero detection successful
- [ ] Documentation reviewed (README.md, CHANGELOG.md)
- [ ] Version number correct in plugin header (2.0.0)
- [ ] Changelog dated correctly

---

## Post-Test Actions

### If Tests Pass:
1. [ ] Update CHANGELOG.md with release date: `[2.0.0] - 2025-11-27`
2. [ ] Commit changes to Code-Refactor branch
3. [ ] Push to GitHub
4. [ ] Create git tag: `git tag v2.0.0`
5. [ ] Push tag: `git push origin v2.0.0`
6. [ ] GitHub Actions creates release automatically
7. [ ] Download ZIP from GitHub Releases
8. [ ] Test ZIP installation one final time
9. [ ] Ready for branding and WordPress.org submission

### If Tests Fail:
1. [ ] Document all issues found
2. [ ] Prioritize critical bugs
3. [ ] Fix issues in code
4. [ ] Re-run failed tests
5. [ ] Repeat until all tests pass

---

## Notes

- Take screenshots of GTM detection widget (green/red states)
- Record PageSpeed Insights scores (before/after)
- Document any unexpected behavior
- Test on multiple devices (desktop, mobile, tablet)
- Use incognito/private browsing to avoid cache issues

**Estimated Testing Time**: 2-3 hours for complete checklist
