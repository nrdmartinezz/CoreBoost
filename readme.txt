=== CoreBoost ===
Contributors: nrdmartinezz
Tags: performance, optimization, lcp, core web vitals, css defer, lazy loading, critical css
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced WordPress performance optimization plugin with LCP optimization, CSS/JS deferring, critical CSS inlining, and comprehensive Core Web Vitals improvements.

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
