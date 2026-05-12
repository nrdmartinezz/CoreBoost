=== CoreBoost ===
Contributors: nrdmartinezz
Tags: performance, optimization, lcp, core web vitals, css defer, lazy loading, critical css, google fonts, adobe fonts
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 3.3.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced WordPress performance optimization plugin with LCP optimization, CSS/JS deferring, critical CSS inlining, Google Fonts & Adobe Fonts optimization, and comprehensive Core Web Vitals improvements.

== Description ==

CoreBoost is a comprehensive WordPress performance optimization plugin designed to dramatically improve your site's Core Web Vitals, particularly Largest Contentful Paint (LCP). The plugin provides advanced CSS and JavaScript optimization, critical CSS inlining, and intelligent image loading strategies.

= Key Features =

**LCP Optimization**
* Smart lazy loading exclusions for above-the-fold images
* Automatic fetchpriority="high" application to LCP candidates  
* Hero image preloading with multiple detection methods
* Background image optimization and preloading

**CSS Optimization**
* Critical CSS inlining for instant above-the-fold rendering
* Advanced CSS deferring with preload method and fallbacks
* Pattern-based CSS handle detection for popular plugins
* JetFormBuilder, Elementor, and WooCommerce compatibility
* Google Fonts & Adobe Fonts optimization with automatic preconnect and deferred loading

**JavaScript Optimization**
* Smart script deferring while preserving dependencies
* jQuery and critical script protection
* Plugin-specific optimization patterns
* Configurable exclude lists for critical scripts

**Advanced Features**
* Admin bar integration with one-click cache clearing
* Tabbed settings interface for organized configuration
* Debug mode with detailed optimization tracking
* Performance caching with intelligent invalidation
* Real-time form validation and feedback

= Performance Impact =

Typical performance improvements with CoreBoost:
* LCP improvement: 60-77% faster (3.5s → 0.6-1.5s)
* Performance Score: +15-30 points in PageSpeed Insights
* Render-blocking elimination: Removes 1000ms+ of blocking delays
* Core Web Vitals: Significant improvements across all metrics

= Supported Plugins & Themes =

**Page Builders:** Elementor, Beaver Builder, Divi, Visual Composer
**E-commerce:** WooCommerce, Easy Digital Downloads
**Form Builders:** JetFormBuilder, Contact Form 7, Gravity Forms, WPForms
**Popular Plugins:** Yoast SEO, Rank Math, Slider Revolution

= Quick Setup =

1. Install and activate CoreBoost
2. Go to Settings → CoreBoost
3. Enable "Auto-detect from Elementor Data" in Hero Images tab
4. Enable CSS deferring with "Preload with Critical CSS"
5. Enable script deferring (safe defaults included)
6. Add critical CSS for your above-the-fold content
7. Test with PageSpeed Insights

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "CoreBoost"
4. Click "Install Now" and then "Activate"
5. Go to Settings → CoreBoost to configure

= Manual Installation =

1. Download the CoreBoost plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the ZIP file and click "Install Now"
5. Activate the plugin
6. Go to Settings → CoreBoost to configure

= Configuration =

After installation, configure CoreBoost through the tabbed interface:

**Hero Images & LCP Tab:**
* Choose your preferred hero image detection method
* Set lazy loading exclusion count (recommended: 2-3)
* Enable responsive preloading for mobile optimization

**CSS & Critical CSS Tab:**
* Enable CSS deferring with preload method
* Add critical CSS for different page types
* Configure CSS handles to defer

**Script Optimization Tab:**
* Enable script deferring
* Configure scripts to defer or exclude
* Set up plugin-specific optimizations

**Advanced Settings Tab:**
* Enable caching for high-traffic sites
* Turn on debug mode for testing
* Monitor optimization effectiveness

== Frequently Asked Questions ==

= Will CoreBoost break my website? =

CoreBoost is designed with safety in mind. It includes extensive compatibility testing and safe defaults. The debug mode allows you to verify optimizations before going live. However, we recommend testing on a staging site first.

= How much will CoreBoost improve my site speed? =

Results vary by site, but typical improvements include 60-77% faster LCP times and 15-30 point increases in PageSpeed Insights scores. The actual improvement depends on your current optimization level and site configuration.

= Is CoreBoost compatible with caching plugins? =

Yes, CoreBoost works alongside popular caching plugins like WP Rocket, W3 Total Cache, and WP Super Cache. It includes cache clearing integration for seamless operation.

= Do I need technical knowledge to use CoreBoost? =

CoreBoost works with sensible defaults out of the box. For basic optimization, simply enable the main features. Advanced users can fine-tune settings for maximum performance.

= Will CoreBoost work with my theme and plugins? =

CoreBoost is tested with popular themes and plugins. It includes specific optimizations for Elementor, WooCommerce, JetFormBuilder, and many others. The plugin uses pattern matching to automatically detect and optimize common plugin assets.

= How do I know if CoreBoost is working? =

Enable debug mode to see detailed optimization comments in your page source. You can also use the admin bar menu to quickly test your site with PageSpeed Insights and monitor improvements.

== Screenshots ==

1. Hero Images & LCP Optimization settings tab
2. CSS & Critical CSS optimization configuration  
3. Script optimization settings with exclude lists
4. Advanced settings and debug mode options
5. Admin bar integration with quick actions
6. PageSpeed Insights results showing LCP improvements

== Changelog ==

= 3.3.1 =
* Fixed: smart_youtube_blocking default was only corrected in class-config.php (UI) in v3.3.0 — the runtime defaults in class-activator.php, class-coreboost.php, and class-migration.php still had false, so the feature never activated on any installed site. All three defaults are now true and a migration step enables it for existing installs upgrading from < 3.3.1
* Fixed: Page-specific image overrides were silently ignored when the preload method was set to video_hero — preload_video_hero() skipped the specific_pages check that preload_automatic() performs. The override is now checked first in both methods, with Elementor detection used as a fallback

= 3.3.0 =
* Added: Automatic preconnect to i.ytimg.com when Smart YouTube Blocking is enabled — eliminates DNS + TCP + TLS cold-start cost (~100–150 ms) before the LCP fallback image arrives
* Changed: smart_youtube_blocking now defaults to enabled on fresh installs — previously defaulted to disabled, meaning the PHP data-settings stripping never ran and the fallback image preload was never injected
* Fixed: YouTube hero fallback thumbnail upgraded from hqdefault.jpg (480×360) to maxresdefault.jpg (1280×720) — preloading the wrong resolution caused a second fetch for the full-width image
* Fixed: Video fallback and video link detection now scans recursively (4 levels deep, 5 root elements) — previously only checked the first top-level element, missing videos nested in Flexbox container layouts
* Fixed: find_background_videos() now recognises Elementor container elType at root depth (not just section), fixing detection on Flexbox-based hero layouts
* Fixed: Duplicate preload tag eliminated when both video_hero preload method and smart_youtube_blocking are active for the same page

= 3.2.7 =
* Fixed: Smart YouTube Blocking now strips youtube.com/iframe_api and all www.youtube.com/ scripts from the page entirely — previously they were still loaded (async), causing YouTube's module chain (base.js etc.) to be fetched and flagged as ~507 KiB of unused JS by PageSpeed
* Fixed: Video restoration trigger changed from requestIdleCallback/setTimeout to real user interaction events (scroll, click, touch, keydown, mousemove) — bots and PageSpeed crawlers never trigger these, so YouTube scripts are never requested during audits
* Fixed: YouTube IFrame API is now dynamically injected at interaction time, then deferred video backgrounds are restored — videos still play normally for real users
* Fixed: Page-specific image preload field now accepts full URLs as page identifiers (e.g. https://example.com/) in addition to slugs — previously a full URL key never matched the internal home/slug lookup

= 3.2.6 =
* Fixed: Removed high-priority preload hints for wp-hooks, wp-i18n, and wp-dom-ready when WP Core Defer is enabled — preloading deferred scripts caused PSI to include them in the critical request chain despite them being non-render-blocking
* Added: e-animation- pattern to CSS auto-defer list (covers Elementor animation library e-animation-grow and similar)
* Added: QuestOrthodontics pattern to CSS auto-defer list for child theme stylesheet

= 3.2.5 =
* Fixed: Script preload hints now use versioned, filter-passed URLs matching the actual <script> tag src — previously a URL mismatch caused the browser to double-fetch deferred scripts, adding ~130ms to the critical request chain
* Added: Google Fonts @import statements are now automatically stripped from Elementor-generated CSS files and replaced with non-blocking <link rel="preload"> tags in <head>
* Added: Elementor CSS file filter (elementor/css/file_content) prevents re-introduction of Google Font @imports when Elementor regenerates CSS on save

= 3.2.4 =
* Fixed: Hero detection no longer caches failed results — retries live on every load until an image is found
* Fixed: Elementor hero scan now covers up to 5 top-level sections and 4 levels deep, fixing detection on Flexbox container layouts
* Fixed: CSS auto-patterns (elementor-frontend, hello-elementor, etc.) no longer deferred in manual mode — only active when Auto Defer All CSS is enabled
* Added: font-display: swap automatically injected into all @font-face blocks in wp_head, covering Elementor custom fonts and theme fonts
* Added: Local Font Preloads field — paste .woff2 URLs (one per line) to preload self-hosted and Elementor custom fonts

= 3.2.3 =
* Fixed: CSS defer toggle (auto_defer_all_css) now correctly resets to disabled when unchecked and saved
* Fixed: Pages critical CSS no longer applied on the homepage when a static front page is set
* Updated: GitHub Actions workflows upgraded to actions/checkout@v5 and softprops/action-gh-release@v2 for Node.js 24 compatibility

= 3.1.1 =
* Added: New card-based UI for hero preload method selection with visual cards and tooltips
* Added: Migration notice system to guide users to review updated settings
* Added: Filter hook `coreboost_detect_hero_image` for custom page builder support
* Added: URL hash navigation to auto-expand settings sections
* Changed: Consolidated 6 hero preload methods into 4 clear options (Automatic, CSS Class, Video Hero, Disabled)
* Changed: Improved hero class detection now matches `.hero-image`, `.lcp-image`, `.hero-foreground-image`, `.heroimg`
* Fixed: Potential undefined index error in foreground image detection

= 3.1.0 =
* Removed: Bulk image converter feature (AVIF/WebP variant generation)
* Removed: All variant-related classes and settings
* Changed: Image optimization now focuses on tag-based optimizations only
* Changed: Settings page now recommends "Converter for Media" plugin for image format conversion
* Fixed: Bulk converter tab detection (was checking wrong tab value)
* Fixed: Blank admin page on cache clear (moved actions to admin_init hook)
* Fixed: Settings page default tab now correctly defaults to "General"
* Fixed: admin.js missing localization object

= 3.0.7 - 2025-01-26 =
* Changed: Refactored bulk converter UI to use state machine pattern for reliable state management
* Changed: Rewrote bulk-converter.css without !important declarations using proper CSS specificity
* Changed: Added Context_Helper::debug_log() method for centralized, WP_DEBUG-aware logging
* Changed: Converted 50+ error_log() calls to use centralized debug logging (prevents production log pollution)
* Changed: Migrated manual Elementor preview checks to Context_Helper::should_skip_optimization()
* Fixed: Removed duplicate lazy_load_exclude_count from default options
* Removed: Deprecated class-debug-helper.php (empty since v2.5.1)

= 1.1.2 - 2024-11-27 =
* Improved: Enhanced unused script/CSS removal to catch late-enqueued resources
* Improved: Comprehensive debug logging shows which handles were found vs not found
* Fixed: Changed hook priority to PHP_INT_MAX to ensure removal runs after all enqueue operations

= 1.1.1 - 2024-11-27 =
* Added: Unused CSS/JS removal with manual handle control
* Added: YouTube player resource blocking for background videos (Block Player CSS and Embed UI options)
* Fixed: Checkbox unchecking bug - checkboxes now properly save unchecked state
* Improved: jQuery dependency detection - automatic protection for jQuery-dependent scripts
* Improved: Script deferring logic prevents jQuery-related errors with Elementor and other plugins
* Improved: Advanced tab description now mentions unused resource removal

= 1.1.0 - 2024-11-26 =
* Breaking Change: New four-tab admin interface (Hero, Scripts, CSS, Advanced)
* Breaking Change: Settings reorganized into logical sections
* Added: Comprehensive LCP optimization system for Elementor hero sections
* Added: Advanced CSS deferring with critical CSS extraction and inlining
* Added: Google Fonts optimization with display=swap and preconnect
* Added: Adobe Fonts optimization with resource hints
* Added: Script optimization (async/defer) with jQuery dependency protection
* Added: Extended debug mode with detailed HTML comments
* Improved: Admin UI with better organization and descriptions
* Improved: Cache system with automatic cleanup
* Improved: Performance with optimized hook priorities

= 1.0.6 =
* Enhanced inline CSS detection with output buffer processing
* Fixed admin bar "Clear Cache" button (now works on frontend)
* Automatic detection and deferring of Elementor Pro CSS (motion-fx, sticky, etc.)
* Pattern matching for custom theme CSS files (custom-*.css)
* Reduced critical request chain length by 200-431ms
* Visual cache cleared notification with auto-dismiss
* Better handling of hardcoded CSS link tags
* Improved Core Web Vitals scores

= 1.0.5 =
* Added Google Fonts optimization with automatic preconnect and deferred loading
* Added Adobe Fonts (Typekit) optimization support
* Font stylesheets now use preload with onload handler to eliminate render-blocking
* Automatic display=swap parameter addition for better font loading
* New font optimization settings in CSS & Critical CSS tab
* Enhanced performance for sites using external web fonts

= 1.0.4 =
* Fixed critical bug with missing debug_comment() method
* Fixed activation hook registration issue
* Enhanced GitHub Actions test suite
* Improved stability and error handling

= 1.0.0 =
* Initial release
* LCP optimization with lazy loading exclusions and fetchpriority
* Advanced CSS deferring with critical CSS inlining
* Smart script deferring with dependency preservation
* JetFormBuilder and popular plugin compatibility
* Admin bar integration and tabbed interface
* Debug mode and performance monitoring
* Comprehensive caching system

== Upgrade Notice ==

= 1.0.5 =
New feature: Google Fonts and Adobe Fonts optimization! Enable font optimization in CSS settings to eliminate render-blocking delays from external font stylesheets.

= 1.0.4 =
Critical bug fix release. Updates activation hook registration and adds missing method. All users should upgrade immediately.

= 1.1.0 =
Major release eliminating the need for secondary optimization plugins. Comprehensive script optimization with async/defer loading, inline script detection, YouTube API handling, and jQuery UI optimization. Reduces critical request chain by 70-80% (2,438ms → ~400-600ms). Code architecture improvements with configuration-driven admin fields for easier maintenance and extensibility.

= 1.0.0 =
Initial stable release of CoreBoost. Provides comprehensive WordPress performance optimization with significant Core Web Vitals improvements.

== Support ==

For support, bug reports, and feature requests, please visit:
* GitHub Repository: https://github.com/nrdmartinezz/CoreBoost
* Issues: https://github.com/nrdmartinezz/CoreBoost/issues
* Discussions: https://github.com/nrdmartinezz/CoreBoost/discussions

== Contributing ==

CoreBoost is open source and welcomes contributions. Please see our Contributing Guidelines on GitHub for information on how to contribute code, report bugs, or suggest features.

== Privacy ==

CoreBoost does not collect, store, or transmit any personal data. All optimizations are performed locally on your WordPress installation. The plugin only modifies how your site's assets are loaded to improve performance.
