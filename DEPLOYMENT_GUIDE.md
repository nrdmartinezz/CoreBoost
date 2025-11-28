# CoreBoost v2.5.0 Deployment Guide

## Quick Start

CoreBoost Phase 5 is complete and production-ready. This guide walks through deployment of the final complete system.

---

## Pre-Deployment Verification

### File Checklist
```
✅ Phase 1-2 Files
   includes/public/class-script-exclusions.php
   includes/public/class-script-settings.php
   includes/admin/class-advanced-optimization-settings.php

✅ Phase 3 Files
   includes/public/class-pattern-matcher.php
   (Integrated into Script_Exclusions)

✅ Phase 4 Files
   includes/public/class-event-hijacker.php
   (Integrated into Script_Optimizer)

✅ Phase 5 Files (NEW)
   includes/public/class-analytics-engine.php
   includes/public/class-performance-insights.php
   includes/admin/class-dashboard-ui.php
   includes/admin/css/dashboard.css
   includes/admin/js/dashboard.js

✅ Modified Files
   includes/class-coreboost.php (added Phase 5 initialization)
   includes/public/class-script-optimizer.php (Phase 3-4 integration)
   includes/admin/class-settings.php (Phase 3-4 integration)
```

### Code Quality Checks
```bash
# Syntax validation
php -l includes/public/class-analytics-engine.php
php -l includes/admin/class-dashboard-ui.php
php -l includes/public/class-performance-insights.php

# Check for obvious errors (grep for red flags)
grep -n "TODO\|FIXME\|XXX" includes/admin/class-dashboard-ui.php
grep -n "die\|exit" includes/public/class-analytics-engine.php
```

### Security Audit
```php
✅ AJAX Nonce Verification
   check_ajax_referer('coreboost_dashboard_nonce') in all handlers

✅ Capability Checks
   if (!current_user_can('manage_options')) in all admin functions

✅ Data Sanitization
   sanitize_text_field() for user input
   absint() for numeric values
   wp_unslash() before sanitization

✅ Output Escaping
   esc_html() for text
   esc_attr() for HTML attributes
   wp_json_encode() for JSON
```

---

## Installation Steps

### Step 1: Backup
```bash
# Backup database
mysqldump -u user -p database > backup_$(date +%Y%m%d).sql

# Backup plugin directory
cp -r CoreBoost CoreBoost_backup_$(date +%Y%m%d)
```

### Step 2: Deploy Files
```bash
# Copy all new Phase 5 files to includes/ directories
cp class-analytics-engine.php includes/public/
cp class-dashboard-ui.php includes/admin/
cp class-performance-insights.php includes/public/
cp dashboard.css includes/admin/css/
cp dashboard.js includes/admin/js/

# Update modified files
cp class-coreboost.php includes/
```

### Step 3: Verify Plugin Loads
```php
// In wp-config.php add temporary debug:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check debug.log for errors
tail -f wp-content/debug.log
```

### Step 4: Activate Dashboard
```
WordPress Admin > CoreBoost > Dashboard

Expected: Dashboard page loads with empty metrics
```

---

## Post-Installation Verification

### 1. Admin Menu Registration
```
✅ CoreBoost > Dashboard appears
✅ Dashboard page accessible
✅ No 404 errors in console
```

### 2. Analytics Tracking
```php
// On frontend, verify tracking works:
// Check browser console for any JS errors
// Load page and check WordPress options:
$metrics = get_option('coreboost_script_metrics');
var_dump(!empty($metrics)); // Should be true after page load
```

### 3. Dashboard Data Display
```
✅ Summary cards show data
✅ Charts render (if data available)
✅ Tables display correctly
✅ No JavaScript errors in console
```

### 4. AJAX Endpoints
```bash
# Test export endpoint
curl -X POST "http://site.local/wp-admin/admin-ajax.php" \
  -d "action=coreboost_export_analytics&nonce=NONCE" \
  --user admin:password

# Should return JSON analytics data
```

### 5. Scheduled Events
```php
// Verify cleanup event scheduled:
$crons = _get_cron_array();
foreach ($crons as $timestamp => $cron) {
    if (isset($cron['wp_scheduled_event_coreboost_cleanup'])) {
        echo "✅ Cleanup event scheduled";
    }
}
```

---

## Database Verification

### Check Options Created
```sql
-- MySQL
SELECT option_name, option_value 
FROM wp_options 
WHERE option_name LIKE 'coreboost_%';

-- Expected:
-- coreboost_options (modified, with Phase 5 options)
-- coreboost_script_metrics (new)
-- coreboost_pattern_effectiveness (new)
-- coreboost_ab_tests (new)
```

### Check Option Size
```sql
-- Check if options table growing normally
SELECT 
    SUM(CHAR_LENGTH(option_value)) as total_size,
    COUNT(*) as option_count
FROM wp_options 
WHERE option_name LIKE 'coreboost_%';

-- Expected: <5MB for typical sites
```

---

## Performance Validation

### Frontend Performance
```bash
# Measure page load impact
# Compare with/without Phase 5

# Expected overhead: <1ms per page load
# Reason: Analytics only buffers, flushes on wp_footer
```

### Dashboard Performance
```
Dashboard Load:     <1 second (target)
- Page render:      <500ms
- AJAX data fetch:  <200ms
- Chart rendering:  <300ms
```

### Analytics Recording
```
Per-page overhead:  <1ms
Memory per script:  100-200 bytes
Storage per 30 days: 1-2MB typical
```

### Cleanup Performance
```
Daily cleanup time: <100ms
Database impact:    Negligible (off-peak)
```

---

## Feature Testing Checklist

### Analytics Engine
- [ ] Scripts recorded on page load
- [ ] Pattern effectiveness tracked
- [ ] Metrics stored in options
- [ ] Statistics calculated correctly
- [ ] Cleanup removes old records

### Dashboard UI
- [ ] All summary cards display
- [ ] Charts render with data
- [ ] Tables show correct scripts
- [ ] Recommendations generate
- [ ] A/B test form shows
- [ ] Export button works
- [ ] Mobile responsive

### A/B Testing
- [ ] Can start new test
- [ ] Test name saved
- [ ] Variants recorded
- [ ] Metrics collected
- [ ] Results calculated
- [ ] Winner identified

### Admin Integration
- [ ] Dashboard submenu appears
- [ ] Settings page still works
- [ ] No conflicts with Phase 1-4 features
- [ ] All admin notices display correctly

---

## Troubleshooting

### Dashboard Not Appearing
```php
// Check if Dashboard_UI initialized
add_action('wp_loaded', function() {
    if (!is_admin()) return;
    
    // Should have CoreBoost_Dashboard_UI instance
    if (!class_exists('CoreBoost_Dashboard_UI')) {
        error_log('Dashboard class not found');
    }
});
```

### Analytics Not Recording
```php
// Check if Analytics_Engine initialized
$analytics = CoreBoost::get_instance()->get_analytics_engine();
if (!$analytics) {
    error_log('Analytics engine not initialized');
    return;
}

// Check if option storing data
$metrics = get_option('coreboost_script_metrics');
if (empty($metrics)) {
    error_log('No metrics recorded');
}
```

### AJAX Failing
```
1. Check nonce: coreBoostDashboard.nonce in JS console
2. Verify endpoint: /wp-admin/admin-ajax.php exists
3. Check for PHP errors in debug.log
4. Verify user is admin (manage_options capability)
```

### Charts Not Rendering
```
1. Check Chart.js loads: Look for chart.min.js in Network tab
2. Verify canvas elements exist
3. Check browser console for JS errors
4. Try clearing browser cache
5. Test in different browser
```

### High Memory Usage
```
// Check if metrics table growing too large
$metrics = get_option('coreboost_script_metrics');
$size = strlen(serialize($metrics));
echo size_format($size); // Should be <5MB

// If too large, run cleanup manually:
$analytics = CoreBoost::get_instance()->get_analytics_engine();
$cleared = $analytics->cleanup_old_metrics();
echo "Cleared $cleared records";
```

---

## Configuration Options

### Enable/Disable Analytics
```php
// In wp-config.php or via filters:
add_filter('coreboost_enable_analytics', '__return_false');
```

### Adjust Retention Period
```php
// Keep metrics for 60 days instead of 30:
add_filter('coreboost_analytics_retention_days', function() {
    return 60;
});
```

### Custom Recommendations
```php
// Add custom recommendation:
add_filter('coreboost_recommendations', function($recommendations) {
    $recommendations[] = array(
        'type' => 'info',
        'title' => 'Custom Check',
        'description' => 'Your custom message',
        'action' => 'What to do',
        'impact' => 'low'
    );
    return $recommendations;
});
```

---

## Monitoring

### Daily Checks
```bash
# Check error log for issues
tail -n 100 wp-content/debug.log | grep coreboost

# Verify cleanup ran
grep "cleanup" wp-content/debug.log | tail -n 1

# Check analytics size
wp option get coreboost_script_metrics | wc -c
```

### Weekly Review
```
- Dashboard displays current data
- Recommendations are relevant
- No performance regressions
- A/B tests collecting data properly
- Export function works
```

### Monthly Analysis
```
- Review trends in metrics
- Assess pattern effectiveness
- Evaluate recommendations
- Check A/B test results
- Plan optimizations
```

---

## Rollback Plan

### If Critical Issues Found
```bash
# 1. Deactivate plugin
wp plugin deactivate coreboost

# 2. Restore backup
cp -r CoreBoost_backup_YYYYMMDD/* CoreBoost/

# 3. Restore database
mysql -u user -p database < backup_YYYYMMDD.sql

# 4. Reactivate
wp plugin activate coreboost

# 5. Report issue
# Contact developer with error logs
```

### Graceful Degradation
- Dashboard doesn't load? → Fallback to Settings page
- Analytics fails? → Exclude recording, continue optimization
- AJAX breaks? → Manual export via wp-cli
- Charts fail? → Display table data instead

---

## Success Criteria

**Deployment is successful when:**

✅ Plugin activates without errors
✅ Dashboard page loads and displays
✅ Analytics tracking working (metrics recorded)
✅ AJAX endpoints responding
✅ No performance regression
✅ Admin menu properly organized
✅ All 5 phases working together
✅ No conflicts with other plugins
✅ Scheduled events running
✅ Database options created

---

## Post-Deployment Communication

### For Site Administrators
```
CoreBoost has been updated to v2.5.0!

New Features:
- Performance Dashboard: Monitor script optimization
- Smart Recommendations: Automatic suggestions for improvements
- A/B Testing: Test different loading strategies
- Analytics Export: Download performance data

Access the new dashboard:
WordPress Admin > CoreBoost > Dashboard

No changes needed to existing settings - everything works as before!
```

### For Developers
```
Phase 5 Complete:

All classes properly integrated:
- CoreBoost_Analytics_Engine (script metrics, recommendations, A/B testing)
- CoreBoost_Dashboard_UI (admin dashboard, AJAX handlers)
- CoreBoost_Performance_Insights (integration layer)

New Options:
- coreboost_script_metrics (script performance data)
- coreboost_pattern_effectiveness (pattern impact)
- coreboost_ab_tests (A/B test results)

New Hooks:
- wp_scheduled_event_coreboost_cleanup (daily)
- wp_ajax_coreboost_get_dashboard_data
- wp_ajax_coreboost_export_analytics
- wp_ajax_coreboost_run_ab_test

Full documentation in:
- PHASE_5_DOCUMENTATION.md
- FINAL_SUMMARY_ALL_PHASES.md
```

---

## Support

For issues or questions:

1. **Check Debug Log**: `wp-content/debug.log`
2. **Review Logs**: Check WordPress debug.log for CoreBoost errors
3. **Test Endpoints**: Verify AJAX endpoints responding
4. **Clear Cache**: Clear all caches (object, page, browser)
5. **Disable Plugins**: Test with other plugins disabled

---

## Conclusion

CoreBoost v2.5.0 is now fully deployed with all 5 phases of optimization active. The plugin provides:

✅ Multi-layer script exclusions
✅ Advanced pattern matching
✅ Event-driven loading
✅ Comprehensive analytics
✅ Performance recommendations

**Happy optimizing!**

---

**Version**: 2.5.0
**Status**: Production Ready
**Date**: 2025-11-28
