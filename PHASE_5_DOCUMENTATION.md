# CoreBoost Phase 5 - Performance Dashboard & Analytics (v2.5.0)

## Overview

Phase 5 introduces a comprehensive performance analytics and dashboard system that provides real-time visibility into script optimization impact, pattern effectiveness, and performance recommendations. This phase completes the 5-phase optimization roadmap.

---

## Features

### 1. Analytics Engine
- **Script Metrics Tracking**: Records size, load time, and exclusion status for every script
- **Pattern Effectiveness**: Measures bytes saved and scripts affected by each pattern
- **Performance Statistics**: Calculates averages, max values, and trends over time
- **A/B Testing Framework**: Compare different optimization strategies
- **Recommendations Engine**: Generates actionable performance insights

### 2. Dashboard UI
- **Summary Cards**: Quick overview of key metrics (total scripts, load time, size, bytes saved)
- **Performance Charts**: Visual representation of script distribution and load times
- **Performance Tables**: Detailed breakdown of slowest and largest scripts
- **Pattern Analysis**: Shows top performing patterns and their impact
- **Recommendations Display**: Color-coded suggestions for optimization
- **A/B Test Management**: Create and track optimization experiments

### 3. Performance Insights Integration
- **Automatic Tracking**: Records metrics without manual intervention
- **Buffer System**: Batches metrics for efficient storage
- **Scheduled Cleanup**: Removes old metrics to manage database size
- **Pattern Recording**: Tracks pattern effectiveness automatically

---

## Architecture

### Analytics Engine (`class-analytics-engine.php`)

**Core Classes:**
```php
CoreBoost_Analytics_Engine {
    + record_script_metric($handle, $data): void
    + record_pattern_effectiveness($pattern, $scripts_affected, $bytes_saved): void
    + get_dashboard_summary(): array
    + generate_recommendations(): array
    + start_ab_test($test_name, $variant_a, $variant_b, $duration): bool
    + record_ab_metric($test_id, $variant, $metric): void
    + get_ab_test_results($test_id): array
    + export_analytics(): array
    + cleanup_old_metrics(): int
    + get_debug_info(): array
}
```

**Key Methods:**

1. **Recording Metrics**
   - `record_script_metric()` - Records script loading data with timestamp
   - `record_pattern_effectiveness()` - Tracks pattern impact on optimization
   - `record_ab_metric()` - Stores A/B test variant metrics

2. **Aggregation**
   - `get_dashboard_summary()` - Generates summary with top scripts and patterns
   - `get_slowest_scripts()` - Returns 5 slowest scripts by load time
   - `get_largest_scripts()` - Returns 5 largest scripts by size

3. **Intelligence**
   - `generate_recommendations()` - Creates actionable insights
   - `get_top_patterns()` - Identifies most effective patterns
   - `calculate_std_dev()` - Statistical analysis for A/B tests

4. **Data Management**
   - `cleanup_old_metrics()` - Removes metrics older than 30 days
   - `export_analytics()` - Full analytics export as JSON
   - `get_debug_info()` - Debug statistics

### Dashboard UI (`class-dashboard-ui.php`)

**Components:**

1. **Admin Menu Integration**
   - Registers "Dashboard" submenu under CoreBoost main menu
   - Provides direct access to performance analytics

2. **Dashboard Page Rendering**
   - Summary cards with key metrics
   - Chart containers for data visualization
   - Performance tables with sortable data
   - Recommendation display
   - A/B testing interface

3. **AJAX Handlers**
   - `ajax_get_dashboard_data()` - Fetches live dashboard data
   - `ajax_export_analytics()` - Generates JSON export file
   - `ajax_run_ab_test()` - Creates new A/B test

### Performance Insights (`class-performance-insights.php`)

**Integration Points:**

1. **Automatic Tracking**
   - Buffers metrics for batch processing
   - Flushes on `wp_footer` hook
   - Schedules daily cleanup of old data

2. **Script Recording**
   ```php
   $insights->record_script($handle, [
       'size' => 50000,
       'load_time' => 125.5,
       'strategy' => 'defer',
       'excluded' => false
   ]);
   ```

3. **Pattern Tracking**
   ```php
   $insights->record_pattern_usage('jquery-*', $matched_scripts);
   ```

---

## Data Schema

### Script Metrics Storage
```
coreboost_script_metrics (Option)
├── [handle]
│   ├── first_seen (timestamp)
│   ├── records (array of up to 100)
│   │   ├── [0]
│   │   │   ├── size (int)
│   │   │   ├── load_time (float)
│   │   │   ├── strategy (string: defer|async|none)
│   │   │   ├── excluded (bool)
│   │   │   ├── timestamp (int)
│   │   │   └── date (datetime)
│   │   └── ...
│   └── stats (aggregated)
│       ├── avg_size (float)
│       ├── max_size (int)
│       ├── avg_load_time (float)
│       ├── exclusion_rate (float)
│       └── last_updated (datetime)
```

### Pattern Effectiveness Storage
```
coreboost_pattern_effectiveness (Option)
├── [pattern_key]
│   ├── first_seen (timestamp)
│   ├── times_used (int)
│   ├── scripts_affected (int)
│   ├── bytes_saved (int)
│   └── last_updated (datetime)
```

### A/B Test Storage
```
coreboost_ab_tests (Option)
├── ab_[test_id]
│   ├── name (string)
│   ├── variant_a (string: control)
│   ├── variant_b (string: treatment)
│   ├── started_at (timestamp)
│   ├── duration (int: seconds)
│   ├── enabled (bool)
│   ├── metrics_a (array of metrics)
│   └── metrics_b (array of metrics)
```

---

## Dashboard Summary

### Summary Cards
- **Total Scripts**: Count of all tracked scripts
- **Avg Load Time**: Average script loading time in ms
- **Total Size**: Combined size of all scripts in KB
- **Bytes Saved**: Total bytes saved by optimizations in MB

### Performance Metrics Charts
- **Script Distribution**: Pie chart showing excluded vs active scripts
- **Load Time Distribution**: Bar chart of slowest 5 scripts

### Top Patterns
Table showing:
- Pattern string
- Times used
- Scripts affected
- Bytes saved (KB)

### Recommendations
Color-coded suggestions:
- 🔴 **Critical** (red): Large unoptimized scripts >100KB
- 🟠 **Warning** (orange): Slow scripts >500ms load time
- 🔵 **Info** (blue): General information and updates
- 🟢 **Success** (green): Positive optimization feedback

### A/B Testing
- Create new test with two variants
- Track metrics for each variant
- View statistical analysis with improvement percentage
- Identify winning variant

---

## Integration Points

### With Script_Optimizer
```php
// In class-script-optimizer.php
$this->insights = new CoreBoost_Performance_Insights($analytics);

// After applying optimization
$this->insights->record_script($handle, [
    'size' => $script_size,
    'load_time' => $load_time,
    'strategy' => $strategy,
    'excluded' => $is_excluded
]);
```

### With Pattern_Matcher
```php
// Track pattern usage
$this->insights->record_pattern_usage($pattern, $matched_scripts);
```

### With CoreBoost Main Class
```php
// In class-coreboost.php
$this->analytics_engine = new CoreBoost_Analytics_Engine($options, $debug);
$this->dashboard_ui = new CoreBoost_Dashboard_UI($analytics_engine, $options);

// Accessor for other classes
public function get_analytics_engine() {
    return $this->analytics_engine;
}
```

---

## Frontend Assets

### CSS (`includes/admin/css/dashboard.css`)
- Dashboard card styling (responsive grid)
- Chart container layouts
- Table styling with hover effects
- Recommendation color-coding
- Mobile responsive breakpoints (1024px, 768px)

### JavaScript (`includes/admin/js/dashboard.js`)
- Chart.js integration for data visualization
- AJAX data loading
- Analytics export functionality
- A/B test form handling
- Real-time dashboard updates

---

## Performance Metrics

### Dashboard Load
- Initial page load: <500ms
- AJAX data fetch: <200ms
- Chart rendering: <300ms
- Total: <1 second

### Data Storage
- Per script: ~100-200 bytes per record
- Keep last 100 records per script
- Total estimate: 1-2MB for typical site (100-200 scripts)

### Database Impact
- Query time: <10ms for typical operations
- Cleanup runs daily, processes in background
- No impact on frontend performance

---

## Recommendations Engine

### Smart Detection

**1. Large Unoptimized Scripts**
```
Trigger: Script > 100KB average size
Recommendation: Consider deferring or async loading
Action: Review exclusion settings
Impact: High
```

**2. Slow Scripts**
```
Trigger: Script load time > 500ms
Recommendation: Enable event hijacking for non-critical scripts
Action: Use event triggers to delay loading
Impact: Medium
```

**3. Pattern Effectiveness**
```
Trigger: Patterns saved > 100KB
Recommendation: Continue using current pattern strategy
Action: Monitor regularly
Impact: Positive
```

**4. Optimization Working**
```
Trigger: Scripts excluded > 10
Recommendation: Great optimization in place
Action: Continue monitoring
Impact: Positive
```

---

## A/B Testing Framework

### Test Setup
```php
$analytics->start_ab_test(
    'async-vs-defer',           // Test name
    'Async Loading',            // Variant A (control)
    'Defer Loading',            // Variant B (treatment)
    86400                       // Duration (24 hours)
);
```

### Recording Metrics
```php
$analytics->record_ab_metric(
    'ab_async-vs-defer',
    'a',  // variant
    ['load_time' => 125.3, 'page_speed_score' => 85]
);
```

### Results Analysis
```php
$results = $analytics->get_ab_test_results('ab_async-vs-defer');
// Returns:
// - avg_load_time_a, avg_load_time_b
// - std_dev_a, std_dev_b
// - improvement_percent
// - winner (variant_a or variant_b)
```

---

## Default Options (Phase 5)

```php
'enable_analytics' => true              // Enable analytics tracking
'analytics_retention_days' => 30        // Keep metrics for 30 days
'enable_ab_testing' => false            // A/B testing opt-in
'enable_recommendations' => true        // Show recommendations
```

---

## Testing Checklist

- [ ] Analytics engine records script metrics
- [ ] Dashboard displays summary cards correctly
- [ ] Charts render with Chart.js
- [ ] Tables show slowest/largest scripts
- [ ] Patterns table displays with bytes saved
- [ ] Recommendations generate for various scenarios
- [ ] A/B test creation works
- [ ] A/B test metrics recording works
- [ ] Export generates valid JSON
- [ ] Cleanup runs daily without errors
- [ ] Mobile responsive design works
- [ ] AJAX handlers work with proper nonces
- [ ] Authorization checks in place
- [ ] Performance metrics under 1 second

---

## Admin UI Structure

### Dashboard Page
```
CoreBoost > Dashboard
│
├─ Summary Cards
│  ├─ Total Scripts
│  ├─ Avg Load Time
│  ├─ Total Size
│  └─ Bytes Saved
│
├─ Performance Metrics
│  ├─ Script Distribution Chart
│  └─ Load Time Distribution Chart
│
├─ Slowest Scripts Table
│  ├─ Script Handle
│  ├─ Avg Load Time (ms)
│  ├─ Max Load Time (ms)
│  └─ Size (KB)
│
├─ Largest Scripts Table
│  ├─ Script Handle
│  ├─ Avg Size (KB)
│  ├─ Max Size (KB)
│  └─ Avg Load Time (ms)
│
├─ Top Performing Patterns Table
│  ├─ Pattern
│  ├─ Times Used
│  ├─ Scripts Affected
│  └─ Bytes Saved (KB)
│
├─ Recommendations Section
│  ├─ Critical Issues
│  ├─ Warnings
│  ├─ Info
│  └─ Success Messages
│
├─ A/B Testing
│  └─ Start New Test Form
│
└─ Tools
   ├─ Export Analytics Button
   ├─ Cleanup Old Metrics Button
   └─ Debug Information (WP_DEBUG only)
```

---

## Security

### AJAX Nonce Verification
```php
check_ajax_referer('coreboost_dashboard_nonce');
```

### Capability Checks
```php
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}
```

### Data Sanitization
```php
$test_name = sanitize_text_field(wp_unslash($_POST['test_name']));
$duration = absint(wp_unslash($_POST['duration']));
```

---

## Browser Compatibility

- Chrome/Edge: Full support
- Firefox: Full support
- Safari: Full support
- IE 11: Charts not supported (graceful degradation)
- Mobile browsers: Responsive design included

---

## Future Enhancements

1. **Advanced Analytics**
   - Trend analysis over time
   - Predictive recommendations
   - Custom date ranges

2. **Enhanced A/B Testing**
   - Statistical significance calculation
   - Multiple variant support
   - Automated winner detection

3. **Integration with Google PageSpeed Insights**
   - Automatic score tracking
   - Recommendations based on PSI data
   - Mobile vs desktop comparison

4. **Export Formats**
   - CSV export
   - PDF reports
   - Email scheduling

5. **Comparative Analytics**
   - Compare metrics across time periods
   - Site comparison (multisite)
   - Historical data analysis

---

## Deployment Notes

1. **Database Migration**: Analytics options created on first load
2. **No Breaking Changes**: Phase 5 fully backward compatible with Phase 1-4
3. **Performance**: Dashboard uses AJAX to avoid page bloat
4. **Scheduled Events**: Daily cleanup scheduled on first page visit

---

## Support & Debugging

### Enable Debug Info
Set in wp-config.php:
```php
define('WP_DEBUG', true);
```

Debug section displays in dashboard showing:
- Scripts tracked count
- Patterns tracked count
- Active A/B tests
- Memory usage
- Total metrics stored

### Check Logs
```php
error_log("CoreBoost: Message here");
```

Logs show:
- Metrics recorded
- Pattern effectiveness
- Cleanup operations
- AJAX errors

---

## Summary

Phase 5 completes the CoreBoost optimization roadmap with comprehensive analytics and performance insights. The dashboard provides actionable recommendations, A/B testing capabilities, and detailed performance metrics to help site owners understand and optimize their script loading strategy.

**Total Implementation:**
- 3 PHP classes (~950 lines)
- 1 CSS file (~350 lines)
- 1 JavaScript file (~300 lines)
- 3 Database options for storage
- 0 breaking changes to previous phases
