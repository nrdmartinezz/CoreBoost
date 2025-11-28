# CoreBoost API Reference - Quick Guide

## Phase 1-2: Script Exclusions

### Recording Exclusions (Phase 1-2)
```php
// In Script_Optimizer or custom code
$exclusions = new CoreBoost\PublicCore\Script_Exclusions($options);

// Add to exclusion
if ($exclusions->is_excluded('jquery-core')) {
    // Script will be deferred
}

// Get all exclusions with layers
$all = $exclusions->get_user_exclusions();
```

---

## Phase 3: Pattern Matching

### Using Pattern Matcher
```php
// Create instance
$matcher = new CoreBoost_Pattern_Matcher($debug = false);

// Exact match
if ($matcher->exact_match('script-handle', 'script-handle')) {
    // Match found
}

// Wildcard pattern
if ($matcher->wildcard_match('jquery-ui-core', 'jquery-*')) {
    // Wildcard match found
}

// Regex pattern
if ($matcher->regex_match('elementor-frontend', '/^elementor[-_]/i')) {
    // Regex match found
}

// Check against plugin profiles
$profile = $matcher->get_plugin_profile('elementor');
if ($matcher->matches('elementor-frontend', $profile)) {
    // Matches Elementor profile
}

// Available profiles
$profiles = $matcher->get_available_profiles();
// Returns: ['elementor', 'woocommerce', 'cf7', 'gravity-forms', ...]

// Get statistics
$stats = $matcher->get_stats();
// Returns: ['queries' => 150, 'matches' => 45, 'cache_hits' => 120]
```

### Integration with Script_Exclusions
```php
// Patterns are automatically loaded in Script_Exclusions Layer 4-5
// Access through is_excluded() method which checks:
// Layer 1: Built-in defaults
// Layer 2: User patterns
// Layer 3: Filters
// Layer 4: Wildcard/Regex patterns (Phase 3)
// Layer 5: Plugin profiles (Phase 3)

$exclusions = new CoreBoost\PublicCore\Script_Exclusions($options);
if ($exclusions->is_excluded('some-script')) {
    // Automatically checks all 5 layers
}
```

---

## Phase 4: Event Hijacking

### Event Hijacker Usage
```php
// Create instance
$hijacker = new CoreBoost_Event_Hijacker($options);

// Register trigger
$hijacker->register_listener('user_interaction', [
    'events' => ['mousedown', 'touchstart', 'scroll'],
    'debounce' => 100
]);

// Get load queue
$queue = $hijacker->get_load_queue();
// Returns scripts queued for loading

// Generate hijack script
$script = $hijacker->generate_hijack_script();
// Returns JavaScript that hijacks page events

// Record metrics
$hijacker->record_metric('page_load_time', 2500);

// Get metrics
$metrics = $hijacker->get_metrics();
```

### Integration with Script_Optimizer
```php
// In Script_Optimizer constructor
if (!empty($options['enable_event_hijacking'])) {
    $this->event_hijacker = new CoreBoost_Event_Hijacker($options);
    
    // After applying optimization
    $hijack_script = $this->event_hijacker->generate_hijack_script();
}
```

---

## Phase 5: Analytics & Dashboard

### Recording Metrics
```php
// Get analytics engine
$analytics = CoreBoost::get_instance()->get_analytics_engine();

// Record script metric
$analytics->record_script_metric('jquery-core', [
    'size' => 30000,
    'load_time' => 125.5,
    'strategy' => 'defer',
    'excluded' => false
]);

// Record pattern effectiveness
$analytics->record_pattern_effectiveness(
    'jquery-*',      // Pattern
    5,               // Scripts affected
    150000           // Bytes saved
);

// Get dashboard summary
$summary = $analytics->get_dashboard_summary();
// Returns: [
//     'total_scripts' => 45,
//     'total_size_kb' => 250.5,
//     'total_load_time_ms' => 1250.75,
//     'scripts_excluded' => 15,
//     'exclusion_rate' => 33.33,
//     'total_patterns' => 8,
//     'total_bytes_saved' => 500000,
//     'bytes_saved_mb' => 0.48,
//     'top_patterns' => [...],
//     'slowest_scripts' => [...],
//     'largest_scripts' => [...]
// ]

// Generate recommendations
$recommendations = $analytics->generate_recommendations();
// Returns array of recommendation objects
```

### A/B Testing
```php
// Start test
$analytics->start_ab_test(
    'async-vs-defer',        // Test name
    'Async Loading',         // Variant A (control)
    'Defer Loading',         // Variant B (treatment)
    86400                    // Duration (24 hours)
);

// Record metric for variant
$analytics->record_ab_metric(
    'ab_async-vs-defer',
    'a',  // 'a' or 'b'
    ['load_time' => 125.3, 'page_speed' => 85]
);

// Get results
$results = $analytics->get_ab_test_results('ab_async-vs-defer');
// Returns: [
//     'test_name' => 'async-vs-defer',
//     'variant_a' => 'Async Loading',
//     'variant_b' => 'Defer Loading',
//     'avg_load_time_a' => 125.3,
//     'avg_load_time_b' => 110.2,
//     'improvement_percent' => 12.03,
//     'winner' => 'variant_b'
// ]

// Export analytics
$export = $analytics->export_analytics();
// Returns complete analytics data structure
```

### Performance Insights Integration
```php
// Create insights tracker
$insights = new CoreBoost_Performance_Insights($analytics);

// Record script
$insights->record_script('jquery-core', [
    'size' => 30000,
    'load_time' => 125.5,
    'strategy' => 'defer',
    'excluded' => false
]);

// Record pattern usage
$insights->record_pattern_usage('jquery-*', $matched_scripts_array);

// Manually flush buffer
$insights->flush_metrics_buffer();

// Control tracking
$insights->disable_tracking();  // Stop recording
$insights->enable_tracking();   // Resume recording
```

### Dashboard UI
```php
// Dashboard automatically registers when initialized
// Access via: WordPress Admin > CoreBoost > Dashboard

// AJAX endpoints available:
// - coreboost_get_dashboard_data
// - coreboost_export_analytics
// - coreboost_run_ab_test

// Frontend JavaScript API
// (in includes/admin/js/dashboard.js)
// coreBoostDashboard.loadDashboardData()
// coreBoostDashboard.exportAnalytics()
// coreBoostDashboard.submitABTest()
```

---

## Filter Hooks

### Phase 1-2 Filters
```php
// Modify script exclusions
add_filter('coreboost_script_exclusions', function($exclusions) {
    $exclusions[] = 'my-custom-script';
    return $exclusions;
});

// Modify load strategies
add_filter('coreboost_load_strategies', function($strategies) {
    $strategies['my-custom'] = 'defer';
    return $strategies;
});
```

### Phase 3 Filters
```php
// Modify pattern exclusions
add_filter('coreboost_pattern_exclusions', function($patterns) {
    $patterns[] = [
        'type' => 'wildcard',
        'value' => 'my-plugin-*'
    ];
    return $patterns;
});

// Add custom plugin profile
add_filter('coreboost_plugin_profiles', function($profiles) {
    $profiles['my-plugin'] = [
        'exact' => ['my-plugin-main'],
        'wildcard' => ['my-plugin-*'],
        'regex' => ['/^my[-_]plugin/i']
    ];
    return $profiles;
});
```

### Phase 4 Filters
```php
// Modify event triggers
add_filter('coreboost_event_triggers', function($triggers) {
    $triggers['custom_event'] = [
        'events' => ['custom-load-trigger'],
        'debounce' => 200
    ];
    return $triggers;
});
```

### Phase 5 Filters
```php
// Modify recommendations
add_filter('coreboost_recommendations', function($recommendations) {
    $recommendations[] = [
        'type' => 'info',
        'title' => 'Custom Recommendation',
        'description' => 'Your message',
        'action' => 'Action to take',
        'impact' => 'low'
    ];
    return $recommendations;
});

// Modify analytics retention
add_filter('coreboost_analytics_retention_days', function() {
    return 60;  // Keep 60 days instead of 30
});

// Enable/disable analytics
add_filter('coreboost_enable_analytics', function() {
    return true;
});
```

---

## Action Hooks

### Phase 1-2 Actions
```php
// Before script optimization
do_action('coreboost_before_script_optimization', $script_handle, $options);

// After script optimization
do_action('coreboost_after_script_optimization', $script_handle, $strategy, $options);
```

### Phase 5 Actions
```php
// Before analytics flush
do_action('coreboost_before_metrics_flush', $metrics_buffer);

// After metrics recorded
do_action('coreboost_after_metrics_recorded', $handle, $metric_data);

// Daily cleanup
do_action('wp_scheduled_event_coreboost_cleanup');
```

---

## WordPress Options

### Main Options
```php
// Get all CoreBoost options
$options = get_option('coreboost_options');

// Common options structure:
[
    // Phase 1-2
    'enable_script_defer' => true,
    'scripts_to_defer' => "contact-form-7\nwc-cart-fragments",
    'exclude_scripts' => "jquery-core\njquery-migrate",
    
    // Phase 3
    'script_wildcard_patterns' => "jquery-ui-*\nbootstrap-*",
    'script_regex_patterns' => "/^elementor[-_]/i\n/^woo[-_]/i",
    'script_plugin_profiles' => "elementor,woocommerce",
    
    // Phase 4
    'enable_event_hijacking' => true,
    'event_hijack_triggers' => "user_interaction,browser_idle",
    'script_load_priority' => "standard",
    
    // Phase 5
    'enable_analytics' => true,
    'analytics_retention_days' => 30,
    'enable_ab_testing' => false,
    'enable_recommendations' => true
]
```

### Analytics Options
```php
// Script metrics
$metrics = get_option('coreboost_script_metrics', []);

// Pattern effectiveness
$patterns = get_option('coreboost_pattern_effectiveness', []);

// A/B tests
$tests = get_option('coreboost_ab_tests', []);
```

---

## Common Tasks

### Track a Custom Script
```php
// In your optimization code
$analytics = CoreBoost::get_instance()->get_analytics_engine();
$analytics->record_script_metric('my-custom-script', [
    'size' => 25000,
    'load_time' => 150.25,
    'strategy' => 'defer',
    'excluded' => false
]);
```

### Check if Pattern Matched
```php
// In custom code
$matcher = new CoreBoost_Pattern_Matcher();
$profile = $matcher->get_plugin_profile('woocommerce');

if ($matcher->matches('my-script', $profile)) {
    // Script matches WooCommerce profile
}
```

### Compare Two Loading Strategies
```php
// Create A/B test
$analytics = CoreBoost::get_instance()->get_analytics_engine();

$analytics->start_ab_test(
    'custom-test',
    'Strategy A',
    'Strategy B',
    3600  // 1 hour test
);

// Record some metrics...
// Later:

$results = $analytics->get_ab_test_results('ab_custom-test');
if ($results['improvement_percent'] > 10) {
    echo "Strategy B is better!";
}
```

### Export Analytics for Analysis
```php
// Get all analytics
$data = $analytics->export_analytics();

// Send to file
file_put_contents(
    'analytics.json',
    wp_json_encode($data, JSON_PRETTY_PRINT)
);

// Or log
error_log(print_r($data, true));
```

---

## Admin Access

### Get Analytics Engine
```php
$coreboost = CoreBoost::get_instance();
$analytics = $coreboost->get_analytics_engine();
$optimizer = $coreboost->get_hero_optimizer();
```

### Access Dashboard Data
```php
// In admin code only
if (current_user_can('manage_options')) {
    $summary = $analytics->get_dashboard_summary();
    $recommendations = $analytics->generate_recommendations();
    $debug = $analytics->get_debug_info();
}
```

---

## Database Queries

### Check Metrics Size
```sql
SELECT 
    option_name,
    ROUND(CHARACTER_LENGTH(option_value)/1024/1024, 2) AS size_mb
FROM wp_options 
WHERE option_name LIKE 'coreboost_%'
ORDER BY CHARACTER_LENGTH(option_value) DESC;
```

### Find Largest Script
```php
$metrics = get_option('coreboost_script_metrics', []);
$largest = [];
$max_size = 0;

foreach ($metrics as $handle => $data) {
    if (!empty($data['stats']['max_size']) && $data['stats']['max_size'] > $max_size) {
        $max_size = $data['stats']['max_size'];
        $largest = ['handle' => $handle, 'size' => $max_size];
    }
}

echo "Largest script: {$largest['handle']} ({$largest['size']} bytes)";
```

---

## Version Compatibility

- **Phase 1-2**: v2.2.0+
- **Phase 3**: v2.3.0+
- **Phase 4**: v2.4.0+
- **Phase 5**: v2.5.0+ (CURRENT)

All phases are backward compatible.

---

## Performance Tips

1. **Use Pattern Matching**: Reduces per-script processing
2. **Enable Analytics Selectively**: Can disable for high-traffic sites
3. **Run Cleanup Regularly**: Keeps database size manageable
4. **Cache Dashboard Data**: Use transients for frequently accessed metrics
5. **Use A/B Tests Sparingly**: Each test adds storage overhead

---

**Last Updated**: 2025-11-28
**Version**: 2.5.0
**Status**: Production Ready
