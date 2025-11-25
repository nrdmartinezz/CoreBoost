# CoreBoost

**Advanced WordPress Performance Optimization Plugin**

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0-orange.svg)](https://github.com/nrdmartinezz/CoreBoost/releases)

CoreBoost is a comprehensive WordPress performance optimization plugin designed to dramatically improve your site's Core Web Vitals, particularly Largest Contentful Paint (LCP), through advanced CSS/JS optimization, critical CSS inlining, and intelligent image loading strategies.

## 🚀 Key Features

### **LCP Optimization**
- **Smart Lazy Loading Exclusions**: Automatically excludes above-the-fold images from lazy loading
- **Fetchpriority High**: Applies `fetchpriority="high"` to LCP candidates for priority loading
- **Hero Image Preloading**: Multiple detection methods for Elementor and other page builders
- **Background Image Optimization**: Converts CSS backgrounds to discoverable preloads

### **CSS Optimization**
- **Critical CSS Inlining**: Page-specific critical CSS for instant above-the-fold rendering
- **Advanced CSS Deferring**: Preload method with JavaScript fallbacks and noscript support
- **Pattern Matching**: Intelligent CSS handle detection including JetFormBuilder, Elementor, WooCommerce
- **Responsive Optimization**: Different critical CSS for different page types

### **JavaScript Optimization**
- **Smart Script Deferring**: Defer non-critical scripts while preserving dependencies
- **jQuery Protection**: Automatically excludes critical scripts like jQuery
- **Plugin Compatibility**: Works with popular plugins (Contact Form 7, WooCommerce, Elementor)

### **Advanced Features**
- **Admin Bar Integration**: One-click cache clearing and performance testing
- **Tabbed Interface**: Organized settings for different optimization areas
- **Debug Mode**: Detailed HTML comments showing optimization status
- **Caching System**: Performance caching with auto-invalidation
- **Real-time Validation**: Form validation and character counters

## 📊 Performance Impact

### **Typical Results**
- **LCP Improvement**: 60-77% faster (3.5s → 0.6-1.5s)
- **Performance Score**: +15-30 points in PageSpeed Insights
- **Render-blocking Elimination**: Removes 1000ms+ of blocking delays
- **Core Web Vitals**: Significant improvements across all metrics

### **Before vs After**
```
Before CoreBoost:
❌ LCP: 3.5-8.3s
❌ Performance Score: 60-75
❌ Render-blocking CSS: 1000ms+
❌ Lazy-loaded LCP images

After CoreBoost:
✅ LCP: 0.6-2.0s
✅ Performance Score: 85-95+
✅ Render-blocking CSS: <200ms
✅ Optimized LCP loading
```

## 🛠️ Installation

### **Method 1: Download Release**
1. Download the latest release from [Releases](https://github.com/nrdmartinezz/CoreBoost/releases)
2. Upload the ZIP file through WordPress Admin → Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Go to Settings → CoreBoost to configure

### **Method 2: Git Clone**
```bash
cd /wp-content/plugins/
git clone https://github.com/nrdmartinezz/CoreBoost.git
```

### **Method 3: WordPress CLI**
```bash
wp plugin install https://github.com/nrdmartinezz/CoreBoost/archive/main.zip --activate
```

## ⚙️ Configuration

### **Quick Setup (2 Minutes)**
1. **Hero Images Tab**: Enable "Auto-detect from Elementor Data"
2. **CSS Tab**: Enable CSS deferring with "Preload with Critical CSS"
3. **Scripts Tab**: Enable script deferring (safe defaults included)
4. **Advanced Tab**: Enable debug mode for testing

### **Advanced Configuration**
- **Critical CSS**: Add page-specific critical CSS for optimal performance
- **Lazy Loading**: Configure how many images to exclude from lazy loading
- **Custom Patterns**: Add specific CSS/JS handles for your theme/plugins
- **Caching**: Enable performance caching for high-traffic sites

## 🎯 Optimization Strategies

### **LCP Optimization**
```php
// Automatic lazy loading exclusion
add_filter('wp_lazy_loading_enabled', 'exclude_lcp_images');

// Fetchpriority high for LCP candidates
add_filter('wp_get_attachment_image_attributes', 'add_lcp_priority');

// Background image preloading
<link rel="preload" href="hero.jpg" as="image" fetchpriority="high">
```

### **CSS Optimization**
```html
<!-- Critical CSS inlined in head -->
<style>/* Critical above-the-fold styles */</style>

<!-- Non-critical CSS deferred -->
<link rel="preload" href="style.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="style.css"></noscript>
```

### **JavaScript Optimization**
```html
<!-- Deferred scripts -->
<script src="script.js" defer></script>

<!-- Critical scripts preserved -->
<script src="jquery.js"></script> <!-- Not deferred -->
```

## 🔧 Supported Plugins & Themes

### **Page Builders**
- ✅ Elementor & Elementor Pro
- ✅ Beaver Builder
- ✅ Divi Builder
- ✅ Visual Composer

### **E-commerce**
- ✅ WooCommerce
- ✅ Easy Digital Downloads
- ✅ WP eCommerce

### **Form Builders**
- ✅ JetFormBuilder (special optimization)
- ✅ Contact Form 7
- ✅ Gravity Forms
- ✅ WPForms
- ✅ Ninja Forms

### **Popular Plugins**
- ✅ Yoast SEO
- ✅ Rank Math
- ✅ Slider Revolution
- ✅ Essential Addons
- ✅ Ultimate Addons

## 📈 Monitoring & Testing

### **Built-in Tools**
- **Admin Bar Menu**: Quick access to cache clearing and PageSpeed testing
- **Debug Mode**: Detailed optimization comments in HTML source
- **Performance Metrics**: Track optimization effectiveness

### **Recommended Testing**
- **PageSpeed Insights**: Test before/after optimization
- **GTmetrix**: Monitor performance over time
- **WebPageTest**: Advanced performance analysis
- **Chrome DevTools**: Real-time performance debugging

## 🐛 Troubleshooting

### **Common Issues**

**CSS Not Loading**
```php
// Check if critical CSS is properly configured
// Ensure noscript fallbacks are present
// Verify CSS handles in debug mode
```

**JavaScript Errors**
```php
// Add problematic scripts to exclude list
// Check jQuery dependencies
// Verify defer compatibility
```

**LCP Not Improving**
```php
// Increase lazy loading exclusion count
// Verify hero image detection
// Check fetchpriority application
```

### **Debug Mode**
Enable debug mode to see detailed optimization information:
```html
<!-- CoreBoost: Lazy loading disabled for LCP candidate -->
<!-- CoreBoost: fetchpriority="high" added to LCP candidate -->
<!-- CoreBoost: CSS DEFERRED - Handle: elementor-frontend -->
```

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### **Development Setup**
```bash
git clone https://github.com/nrdmartinezz/CoreBoost.git
cd CoreBoost
# Make your changes
git checkout -b feature/your-feature
git commit -m "Add your feature"
git push origin feature/your-feature
```

### **Reporting Issues**
- Use the [Issues](https://github.com/nrdmartinezz/CoreBoost/issues) tab
- Include WordPress version, PHP version, and active plugins
- Provide PageSpeed Insights URLs before/after
- Include debug mode output if relevant

## 📝 Changelog

### **Version 1.0.0** (Current)
- ✅ Initial release
- ✅ LCP optimization with lazy loading exclusions
- ✅ Advanced CSS deferring with critical CSS
- ✅ JetFormBuilder compatibility
- ✅ Admin bar integration
- ✅ Tabbed settings interface
- ✅ Debug mode and performance monitoring

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- WordPress Core Team for native lazy loading and fetchpriority APIs
- Elementor team for comprehensive page builder APIs
- Web.dev team for Core Web Vitals guidance and best practices
- Performance optimization community for research and testing

## 📞 Support

- **Documentation**: [Wiki](https://github.com/nrdmartinezz/CoreBoost/wiki)
- **Issues**: [GitHub Issues](https://github.com/nrdmartinezz/CoreBoost/issues)
- **Discussions**: [GitHub Discussions](https://github.com/nrdmartinezz/CoreBoost/discussions)

---

**Made with ❤️ for the WordPress community**

*CoreBoost - Boost your Core Web Vitals*
