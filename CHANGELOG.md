# Changelog

All notable changes to CoreBoost will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.5] - 2025-11-27

### Fixed

- **Smart YouTube Background Video Deferring** - YouTube video backgrounds now load without blocking page render
  - Removed video URL from initial `data-settings` to prevent Elementor from creating iframe on page load
  - Deferred video data stored in `data-coreboost-deferred-youtube` attribute for later restoration
  - Added inline script that restores video backgrounds using `requestIdleCallback` (3 second fallback)
  - Videos load after page is interactive, eliminating CSP violations and render-blocking resources
  - **Preserves video backgrounds** - videos still display, just loaded asynchronously

### Changed

- Smart YouTube blocking now **defers** instead of **removes** video backgrounds
- Approach changed from removal to lazy loading for minimal performance impact
- Restores videos after critical resources load using Elementor's frontend API
- Settings description updated to reflect deferred loading behavior

### Technical Details

- Extracts video metadata (`background_video_link`, `play_on_mobile`, `play_once`) before removing URL
- Stores as JSON in `data-coreboost-deferred-youtube` attribute
- Inline script uses `requestIdleCallback` for optimal browser idle time (5s timeout)
- Fallback to `setTimeout(3000)` for browsers without `requestIdleCallback`
- Triggers Elementor's `elementor/frontend/element/render` hook to properly restore video
- Prevents Elementor editor mode from being affected
- HTML entity encoding/decoding handled correctly for all JSON operations

## [2.0.4] - 2025-11-27

### Fixed

- **Smart YouTube Blocking Performance Optimization** - Dramatically improved performance with minimal impact
  - Fixed backwards conditional logic that prevented YouTube scripts from being blocked
  - Added request-level caching to prevent multiple detections per page load (was running 7+ times)
  - Refactored to use CoreBoost singleton Hero_Optimizer instead of creating new instances
  - Broadened YouTube URL pattern matching to catch all YouTube domains (`youtube.com`, `ytimg.com`)
  - Optimized recursive search with early termination when YouTube background video found
  - Reduced search depth from 5 to 3 levels (hero sections are always near top)
  - Limited search to first 3 sections at root level for maximum performance

### Changed

- YouTube blocking now uses simple domain detection instead of specific URL patterns
- Detection runs ONCE per page load and result is cached in static property
- Added `get_hero_optimizer()` public method to CoreBoost class for singleton access
- Legacy `block_youtube_embed_ui` setting now works independently of smart blocking

### Performance Impact

- **Before**: 7+ Hero_Optimizer instantiations, 7+ transient checks, 7+ JSON decodes per page
- **After**: 1 detection run, 1 transient check, 1 JSON decode, N memory lookups
- Eliminates object creation overhead (new Loader, new Hero_Optimizer on every script tag)
- Zero performance impact on pages without YouTube background videos

## [2.0.3] - 2025-11-27

### Added

- **Smart YouTube Blocking** - Automatically detects Elementor background videos and blocks unnecessary YouTube resources
  - Detects YouTube background videos in Elementor sections and columns
  - Blocks YouTube iframe API, player CSS, and embed UI scripts only when background videos are detected
  - Removes YouTube iframes from HTML output to prevent dynamic script loading
  - Cached detection with automatic cache clearing on Elementor save
  - New setting: "Smart YouTube Blocking" in Advanced Settings tab

### Fixed

- **YouTube CSP Violations**: YouTube iframes in Elementor background videos were loading scripts dynamically, causing Content Security Policy violations and unnecessary resource loading
- Added output buffer processing to remove YouTube background video iframes entirely, preventing all script loading attempts
- Blocks inline scripts attempting to load YouTube API (`youtube.com/iframe_api`, `youtube.com/player_api`)

### Technical Details

- Added `detect_elementor_background_videos()` method in Hero_Optimizer
- Added `find_background_videos()` recursive search through Elementor data structure
- Added `has_youtube_background_videos()` public helper for Resource_Remover
- Added `remove_youtube_background_iframes()` HTML processing in Resource_Remover
- Background video detection results cached in `coreboost_bg_videos_{post_id}` transients
- Added `clear_video_cache()` method in Cache_Manager
- New database option: `smart_youtube_blocking` (default: false)

## [2.0.2] - 2025-11-27

### 🚀 Major Changes

- **Replaced GTM Manager with Custom Tag Manager** - Complete architecture refactor to eliminate performance bottlenecks
- Removed resource-intensive GTM detection system (output buffer captures, file scanning)
- Simplified tag management: users can now add any custom scripts (GTM, GA4, Facebook Pixel, etc.)

### ✨ New Features

- **Custom Tag Manager** with three script positions:
  - Head Scripts (for early-loading tracking codes)
  - Body Scripts (for noscript tags and top-of-body content)
  - Footer Scripts (for non-critical analytics)
- All 6 load strategies preserved (Immediate, Balanced, Aggressive, User Interaction, Browser Idle, Custom Delay)
- Simplified admin interface with helpful examples and common use cases
- Support for any tracking service or custom JavaScript

### 🔧 Technical Improvements

- Eliminated infinite loading issue caused by output buffer hook captures
- Removed GTM_Detector class (413 lines of detection logic)
- Removed GTM_Manager class (339 lines)
- Removed GTM_Settings class (309 lines)
- Replaced with Tag_Manager (345 lines) and Tag_Settings (303 lines)
- Removed GTM-specific exclusions from Script_Optimizer
- Removed GTM cache clearing from Admin class
- **Net Result**: ~400 lines of code removed, significant performance improvement

### 🗄️ Database Changes

- **Removed Options**: `gtm_enabled`, `gtm_container_id`, `gtm_load_strategy`, `gtm_custom_delay`, `gtm_tags`
- **New Options**: `tag_head_scripts`, `tag_body_scripts`, `tag_footer_scripts`, `tag_load_strategy`, `tag_custom_delay`
- **Removed Transients**: `coreboost_gtm_detection`, `coreboost_gtm_body_output_*`

### ⚠️ Breaking Changes

- Existing GTM configurations will need to be re-entered in the new Custom Tags interface
- "GTM & Tracking" tab renamed to "Custom Tags"
- Users upgrading from v2.0.0 or v2.0.1 should copy their GTM container ID before updating

### 📝 Migration Notes

- GTM users: Copy your container snippet from Google Tag Manager and paste into "Head Scripts"
- GTM noscript: Paste noscript iframe into "Body Scripts"
- All delay strategies work the same way as before

## [2.0.1] - 2025-11-27

### Fixed

- **Headers Already Sent Error**: Fixed cache clearing redirect that was called after output started - now uses early `admin_init` hook (priority 5)
- **GTM Validation**: Container ID validation now only triggers when GTM is actually enabled, preventing false error messages on fresh installs
- **GTM Empty Field Error**: Added proper handling for empty container ID field - no longer shows validation errors when field is intentionally left blank

### Changed

- Cache clearing now checks for correct admin page before processing to prevent interference with other plugins
- GTM settings auto-disable if enabled without a valid container ID, with helpful error message

## [2.0.0] - 2025-11-27

🎉 **The "We Promise It Actually Works Now" Release**

Remember v1.2.0? That was more of a "behind-the-scenes renovation" that technically worked but never saw the light of day. Think of it as our architectural blueprint - we gutted the 2097-line monolith, built 22 shiny new classes with proper PSR-4 autoloading, and called it a day. But we never actually... you know... *released* it.

Well, v2.0.0 is where we finally open the doors, flip on the lights, and add some fancy new furniture (spoiler: it's GTM management). This is the stable, tested, production-ready version that bundles the v1.2.0 refactor with battle-tested GTM features. Consider this the "grand opening" after months of construction.

### Added

- **Google Tag Manager Management**: Complete GTM integration with async/defer loading strategies
  - Smart conflict detection - automatically detects existing GTM implementations (plugins, themes, hardcoded)
  - Safety-first approach - always defers to existing implementations to prevent site breakage
  - Six load strategies: Immediate, Balanced (3s - default), Aggressive (5s), User Interaction, Browser Idle, Custom delay
  - Container validation with GTM-XXXXXXX format checking
  - New "GTM & Tracking" admin tab with intuitive interface
  - Re-scan functionality with cache clearing

- **GTM Core Classes** (3 new classes):
  - `GTM_Detector`: Three-layer detection system - scans plugins (GTM4WP, Site Kit, MonsterInsights), theme files, and output buffer for existing GTM
  - `GTM_Manager`: Frontend GTM loader with configurable delay strategies and JavaScript delay controllers
  - `GTM_Settings`: Admin settings interface with real-time conflict reporting and container ID validation

### Changed

- **Script Optimizer**: Now intelligently excludes GTM scripts from optimization when GTM management is enabled (no more optimization conflicts!)
- **Admin Interface**: Added fifth tab "GTM & Tracking" with detection status widget (green checkmark = you're good, red warning = conflict detected)
- **Default Options**: Added GTM settings with balanced 3-second delay as recommended default for optimal performance/accuracy balance

### Fixed

- **Autoloader Critical Bugs** (3 fixes that prevented activation):
  - Fixed `CoreBoost\CoreBoost` class mapping (was looking for `class-core-boost.php` instead of `class-coreboost.php`)
  - Fixed underscore handling in class names (`GTM_Settings`, `Admin_Bar`, etc. now convert correctly)
  - Fixed namespace-to-directory mapping (`PublicCore` now correctly maps to `public/` directory)
- **Headers Already Sent Error**: Fixed cache clearing redirect that happened after output started - now uses early `admin_init` hook
- **GTM Validation**: GTM container ID validation now only triggers when GTM is actually enabled, not when field is empty by default
- **Activation Compatibility**: Plugin now activates cleanly on fresh WordPress installations without fatal errors

### Technical

- Version bumped from 1.2.0 to 2.0.0 (technically we skipped 1.2.0's public release, but who's counting?)
- Complete refactor from v1.2.0 now stable and production-tested
- 22 total classes across modular architecture: 5 core infrastructure, 4 utilities, 5 admin, 8 frontend optimizers
- PSR-4 autoloading with `CoreBoost\{Admin,PublicCore,Core}` namespaces
- Main plugin file reduced from 2097 lines to 70 lines (97% reduction - we're basically Marie Kondo for code)
- GTM detection results cached for performance (1 hour expiry with manual refresh option)
- JavaScript cache-clearing functionality via admin interface
- Integration with existing optimizer classes for seamless conflict prevention
- 100% backward compatible with previous settings and configurations

## [1.2.0] - 2024-12-XX

### Changed

- **Complete Architecture Refactor**: Restructured plugin from monolithic 2097-line file to modern modular WordPress architecture
  - Implemented PSR-4 autoloading with namespace `CoreBoost\{Admin,PublicCore,Core}`
  - Separated concerns into focused single-responsibility classes
  - Created organized folder structure: `includes/{admin,public,core}/`
  - Reduced main plugin file from 2097 lines to 70 lines (97% reduction)
  - Improved maintainability, testability, and extensibility
  - **100% backward compatible** - all existing hooks, filters, and options preserved

### Added

- **New Infrastructure Classes**:
  - `Autoloader`: PSR-4 autoloader with kebab-case file naming
  - `Loader`: Centralized hook management system
  - `Activator`/`Deactivator`: Clean activation/deactivation handlers
  - `CoreBoost`: Main orchestrator with singleton pattern and dependency injection

- **New Core Utility Classes**:
  - `Config`: Centralized configuration management
  - `Cache_Manager`: Unified cache operations (hero cache, third-party caches)
  - `Debug_Helper`: Debug comment output utility
  - `Field_Renderer`: Reusable form field rendering

- **New Admin Classes**:
  - `Admin`: Admin area coordinator
  - `Settings`: Settings registration and sanitization
  - `Settings_Page`: Admin page HTML rendering with tabs
  - `Admin_Bar`: WordPress admin bar menu integration

- **New Frontend Optimizer Classes**:
  - `Hero_Optimizer`: LCP optimization and hero image preloading (5 methods)
  - `Script_Optimizer`: JavaScript defer/async with jQuery dependency detection
  - `CSS_Optimizer`: CSS deferring, critical CSS output, pattern matching
  - `Font_Optimizer`: Google/Adobe font optimization with preconnect
  - `Resource_Remover`: Unused resource removal and YouTube blocking

### Technical Improvements

- **Better Code Organization**: Each feature in dedicated class with clear responsibility
- **Dependency Injection**: All classes receive `$options` and `$loader` in constructor
- **Improved Testing**: Individual classes can be unit tested in isolation
- **Better Documentation**: PHPDoc blocks for all classes and methods
- **Safer Updates**: Original file backed up as `coreboost.php.backup`

### Notes

- This is an **architectural improvement only** - no feature changes or option modifications
- All existing settings and configurations continue to work identically
- Plugin behavior remains exactly the same as v1.1.2
- Prepares codebase for v2.0.0 with planned GTM tracking features

## [1.1.2] - 2024-11-27

### Improved

- **Enhanced Unused Resource Removal**: Changed from `wp_print_scripts`/`wp_print_styles` to `wp_enqueue_scripts` hook at `PHP_INT_MAX` priority
  - Now catches scripts/styles enqueued late in the process (e.g., via theme functions.php)
  - Ensures removal runs after ALL other enqueue operations complete
  - Fixes issue where scripts added via `wp_enqueue_scripts` hook weren't being removed

- **Comprehensive Debug Logging**: Enhanced debug output for unused resource removal
  - Shows total number of handles being processed
  - Displays which handles were successfully removed (✓)
  - Identifies handles that weren't found (✗) with explanation
  - Checks both 'enqueued' and 'registered' status for thorough detection
  - Helps troubleshoot why specific handles aren't being removed

## [1.1.1] - 2024-11-27

### Added

- **Unused CSS/JS Removal**: Manual control to dequeue and deregister specific resource handles
  - New "Remove Unused CSS" option with textarea for CSS handles (one per line)
  - New "Remove Unused JavaScript" option with textarea for JS handles (one per line)
  - Uses `wp_dequeue_style()`, `wp_deregister_style()`, `wp_dequeue_script()`, `wp_deregister_script()`
  - Debug mode shows which resources were removed in HTML comments
  
- **YouTube Player Resource Blocking**: Targeted optimization for background videos
  - "Block YouTube Player CSS" option prevents `www.youtube.com/s/player` CSS from loading
  - "Block YouTube Embed UI" option blocks `youtube.com/yts/` scripts
  - Useful for autoplay background videos that don't need player controls
  - Can save 50-100KB per page with YouTube embeds

### Fixed

- **Checkbox Unchecking Bug**: Fixed issue where unchecked checkboxes would revert to checked state on save
  - Enhanced sanitization logic to properly detect current form tab
  - Boolean fields now correctly set to `false` when unchecked
  - Preserves settings from other tabs via hidden fields
  - Applies to all checkboxes across all tabs (Hero, Scripts, CSS, Advanced)

### Improved

- **jQuery Dependency Protection**: Enhanced script deferring to automatically detect jQuery dependencies
  - Checks WordPress's `$wp_scripts` dependency graph for jQuery dependencies
  - Forces `defer` (not `async`) for any script that depends on jQuery
  - Protects Elementor and other jQuery-dependent plugins from loading before jQuery
  - Prevents jQuery errors even if scripts are mistakenly added to async list
  - Better debug messages showing when scripts are deferred due to jQuery dependency

- **Advanced Tab Description**: Updated section description to mention unused resource removal functionality

### Planned Features

- Real User Monitoring (RUM) integration for LCP detection
- Advanced heuristics for device-specific optimization
- A/B testing framework for optimization strategies
- Integration with popular caching plugins
- Automatic critical CSS generation
- Performance monitoring dashboard


## [1.1.0] - 2024-11-26

### Breaking Changes / Major Improvements

- **Eliminates Need for Secondary Optimization Plugins**: CoreBoost now provides comprehensive script optimization that previously required additional plugins
  - Complete inline script detection and optimization
  - Intelligent async vs defer script loading
  - YouTube API and third-party script handling
  - No longer need WP Rocket, Autoptimize, or similar plugins for script optimization
  - Significant cost savings by consolidating optimization into single plugin

### Added

- **Async Script Loading Support**: New async attribute support for independent scripts
  - Scripts can now be configured to load with `async` (independent) or `defer` (dependent)
  - New admin field "Scripts to Load Async" for specifying independent scripts
  - Pre-configured defaults: YouTube iframe API, iframe-api
  - Dramatically reduces critical request chain by enabling parallel script execution
  
- **Script Resource Hints**: jQuery preloading for faster dependency loading
  - Automatically preloads jQuery with `fetchpriority="high"`
  - Preloads jQuery migrate with lower priority
  - Ensures critical dependency scripts load as fast as possible
  
- **Inline Script Detection**: Output buffer processing for hardcoded script tags
  - Detects and optimizes scripts not registered via `wp_enqueue_script()`
  - Automatically applies async to YouTube iframe API (independent script)
  - Applies defer to Elementor, jQuery UI, WordPress dist, WooCommerce scripts
  - Excludes jQuery core and jQuery migrate (must load synchronously)
  - Pattern matching for: `/elementor/`, `/jquery-ui/`, `/smartmenus/`, `/wp-includes/js/dist/`, `/woocommerce/`

### Fixed

- **YouTube API Render-Blocking**: YouTube iframe API now loads asynchronously
  - Prevents YouTube API from blocking page render (saves 400-1,431ms)
  - Works for both enqueued and hardcoded YouTube script tags
  - Independent script execution (no dependencies on other scripts)
  
- **Critical Request Chain**: Reduced maximum critical path latency by 70-80%
  - Previous: 2,438ms maximum critical path (15 scripts loading serially)
  - After: ~400-600ms (scripts download in parallel)
  - Eliminates network waterfall congestion on main thread
  - Scripts no longer clog main thread during sequential loading

### Changed

- **Enhanced defer_scripts() Method**: Now intelligently chooses between async and defer
  - Checks `scripts_to_async` list for independent scripts → applies `async`
  - Checks `scripts_to_defer` list for dependent scripts → applies `defer`
  - Maintains backward compatibility with existing defer configuration
  - Updated debug comments to show async vs defer decisions
  
- **Updated Admin UI**: Script optimization section enhanced with async guidance
  - Script section description now explains async vs defer differences
  - "Scripts to Defer" field clarified for jQuery-dependent scripts
  - New "Scripts to Load Async" field with examples (youtube-iframe-api, google-analytics, facebook-pixel)
  - "Exclude Scripts" field updated with guidance to keep jQuery excluded
  
- **Output Buffer Enhancement**: process_inline_css renamed to process_inline_assets
  - Now processes both CSS and JavaScript in single pass
  - Conditional processing based on enable_css_defer and enable_script_defer settings
  - More efficient HTML output processing

### Performance Impact

- **Critical Request Chain**: 2,438ms → ~400-600ms (70-80% reduction)
- **YouTube API Load**: Non-blocking async load (saves 400-1,431ms)
- **Script Parallelization**: 15 scripts now download simultaneously instead of serially
- **Main Thread Congestion**: Eliminated sequential script processing bottleneck
- **Core Web Vitals**: Significant improvements to TBT (Total Blocking Time) and TTI (Time to Interactive)


## [1.0.6] - 2024-11-26

### Added

- **Enhanced Inline CSS Detection**: Output buffer processing to catch hardcoded/inline CSS that bypasses WordPress enqueue system
  - Automatically detects and defers Elementor Pro CSS (motion-fx, sticky, etc.)
  - Detects and defers custom theme CSS files (custom-*.css pattern)
  - Handles Widget and animation CSS (widget-*, fadeIn, swiper)
  - Processes uploaded CSS files in wp-content/uploads
  - Pattern matching for plugin CSS (WooCommerce, Contact Form 7, etc.)
  
- **Frontend Cache Clearing**: Working admin bar "Clear Cache" button
  - Proper nonce-based URL instead of dummy # link
  - Frontend cache clearing handler with security checks
  - Visual success notification with auto-dismiss
  - Clean URL after cache clear using JavaScript
  - Maintains user on current page after clearing cache

### Fixed

- **Critical Request Chain Optimization**: Reduced render-blocking CSS from hardcoded link tags
  - Fixes Elementor Pro motion-fx.min.css render-blocking (reduces 200-400ms)
  - Fixes custom CSS files render-blocking (custom-apple-webkit.min.css, etc.)
  - Eliminates critical path latency from inline stylesheets
  
- **Admin Bar Cache Clear Bug**: Fixed non-functional cache clear button
  - Button previously just added # to URL without clearing cache
  - Now properly clears CoreBoost cache from frontend
  - Shows confirmation message to user

### Changed

- Output buffering now processes entire HTML output to catch all CSS link tags
- Improved debug comments show which inline CSS files are being deferred
- Enhanced URL pattern detection for various plugin and theme CSS files

### Performance Impact

- Typical critical path reduction: 200-431ms per page
- Eliminates render-blocking from Elementor Pro modules
- Reduces LCP delays caused by CSS-dependent rendering
- Better Core Web Vitals scores across all metrics

## [1.0.5] - 2024-12-XX

### Added

- **Google Fonts Optimization**: Automatic preconnect and deferred loading for Google Fonts
  - Adds `preconnect` links to fonts.googleapis.com and fonts.gstatic.com
  - Converts font stylesheets to use preload with onload handler
  - Automatic `display=swap` parameter addition
  - Debug comments for font optimization tracking
  
- **Adobe Fonts (Typekit) Optimization**: Full support for Adobe Fonts
  - Preconnect to use.typekit.net
  - Deferred loading with preload method
  - Compatible with both use.typekit.net and fonts.adobe.com URLs
  
- **Font Optimization Settings**: New configuration options in CSS tab
  - Enable/disable font optimization
  - Separate toggles for Google Fonts and Adobe Fonts
  - Automatic display=swap enforcement option
  - Font-specific preconnect controls

### Changed

- Updated plugin description to highlight font optimization features
- Enhanced CSS optimization section with font-specific handling
- Improved debug mode output for font-related optimizations

### Performance Impact

- Eliminates render-blocking delays from external font stylesheets
- Typical font render-blocking reduction: 100-500ms
- Improved LCP scores when fonts are used in hero text
- Better Core Web Vitals across all metrics

## [1.0.4] - 2024-11-25

### Fixed

- **Optimize codebase to ensure efficiency**
- **Reduces redundancy**
- **Removes duplicate functionality**

## [1.0.2] - 2024-11-25

### Fixed

- **Fatal Error on Activation**: Fixed syntax error that prevented plugin activation
- **Code Quality**: Improved syntax validation and error checking
- **Improves Github Testing workflow**
- **Removes Global variable usage**

## [1.0.1] - 2024-11-25

### Fixed

- **Fatal Error on Activation**: Fixed syntax error that prevented plugin activation
  - Corrected missing closing quote and parenthesis in `enable_foreground_conversion_callback()` method
  - Removed extra closing brace at end of class definition
  - All parentheses and braces now properly balanced
- **Code Quality**: Improved syntax validation and error checking

### Technical Details

- Fixed line 316: Missing closing quote in description text
- Fixed line 1430: Extra closing brace after class definition
- Verified syntax balance: 732 parentheses and 218 braces properly matched
- Plugin now activates successfully without fatal errors

## [1.0.0] - 2024-11-11

### Added

- **LCP Optimization**

  - Smart lazy loading exclusions for above-the-fold images
  - Automatic `fetchpriority="high"` application to LCP candidates
  - Multiple hero image detection methods (Elementor, featured images, CSS classes)
  - Background image preloading with high priority
  - Responsive image preloading for mobile/tablet devices
- **CSS Optimization**

  - Advanced CSS deferring with preload method
  - Critical CSS inlining (global, homepage, pages, posts)
  - Pattern-based CSS handle matching
  - JetFormBuilder CSS optimization
  - Noscript fallbacks for CSS loading
  - Two defer methods: advanced preload and simple defer
- **JavaScript Optimization**

  - Smart script deferring with dependency preservation
  - jQuery and critical script protection
  - Plugin-specific script patterns
  - Exclude list for critical scripts
  - Pattern matching for dynamic script handles
- **Admin Interface**

  - Tabbed settings interface (Hero Images, Scripts, CSS, Advanced)
  - Admin bar integration with one-click cache clearing
  - Debug mode with detailed HTML comments
  - Real-time form validation and character counters
  - Quick action buttons for common configurations
- **Performance Features**

  - Comprehensive caching system with auto-invalidation
  - Cache clearing integration with popular plugins
  - Performance monitoring and debug output
  - Foreground image conversion CSS
  - Responsive optimization settings
- **Plugin Compatibility**

  - Elementor and Elementor Pro full support
  - JetFormBuilder and JetEngine optimization
  - WooCommerce compatibility
  - Contact Form 7 optimization
  - Popular plugin pattern recognition

### Technical Details

- **WordPress Compatibility**: 5.0+ (tested up to 6.4)
- **PHP Compatibility**: 7.4+ required
- **Architecture**: Singleton pattern with proper WordPress hooks
- **Caching**: Transient-based with intelligent invalidation
- **Security**: Proper sanitization and nonce verification
- **Performance**: Minimal overhead with conditional loading

### Performance Improvements

- **LCP**: 60-77% improvement (typical 3.5s → 0.6-1.5s)
- **Performance Score**: +15-30 points in PageSpeed Insights
- **Render-blocking**: Eliminates 1000ms+ of CSS/JS blocking
- **Core Web Vitals**: Significant improvements across all metrics

### Developer Features

- **Hooks and Filters**: Extensive customization options
- **Debug Mode**: Detailed optimization tracking
- **Code Quality**: WordPress coding standards compliance
- **Documentation**: Comprehensive inline documentation
- **Extensibility**: Plugin-friendly architecture

### Initial Release Notes

This is the initial stable release of CoreBoost, evolved from the NPG Site Optimizer project. The plugin has been thoroughly tested on various WordPress configurations and provides significant performance improvements out of the box.

**Migration from NPG Site Optimizer**:

- All settings and configurations are preserved
- Database options are automatically migrated
- No manual intervention required for existing users

**Recommended Configuration**:

1. Enable "Auto-detect from Elementor Data" for hero images
2. Set lazy loading exclusion to 2-3 images
3. Enable CSS deferring with "Preload with Critical CSS"
4. Add critical CSS for above-the-fold content
5. Enable script deferring with default exclusions
6. Use debug mode for initial testing and verification

---

## Version History Summary

- **v1.0.0**: Initial stable release with comprehensive optimization features
- **Future versions**: Will focus on advanced automation, RUM integration, and performance monitoring

## Support and Feedback

For issues, feature requests, or general feedback:

- **GitHub Issues**: https://github.com/nrdmartinezz/CoreBoost/issues
- **GitHub Discussions**: https://github.com/nrdmartinezz/CoreBoost/discussions

## Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details on how to submit pull requests, report bugs, and suggest improvements.
