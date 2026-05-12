<?php
/**
 * Script defer and async optimization
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Context_Helper;// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Script_Optimizer
 */
class Script_Optimizer {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Loader instance
     *
     * @var \CoreBoost\Loader
     */
    private $loader;
    
    /**
     * Script exclusions instance
     *
     * @var Script_Exclusions
     */
    private $exclusions;
    
    /**
     * Event hijacker instance (Phase 4)
     *
     * @var Event_Hijacker
     */
    private $event_hijacker;

    /**
     * Number of scripts deferred/asynced on this request.
     * Flushed to the coreboost_scripts_deferred_total option once via wp_footer.
     *
     * @var int
     */
    private $deferred_this_request = 0;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param \CoreBoost\Loader $loader Loader instance
     */
    public function __construct($options, $loader) {
        $this->options = $options;
        $this->loader = $loader;
        // Only register on frontend
        if (!is_admin()) {
            $this->exclusions = new Script_Exclusions($options);
            
            // Initialize event hijacker if enabled (Phase 4)
            if (!empty($options['enable_event_hijacking'])) {
                $this->event_hijacker = new Event_Hijacker($options);
            }
            
            $this->define_hooks();
        }
    }
    
    /**
     * Define hooks
     */
    private function define_hooks() {
        $this->loader->add_filter('script_loader_tag', $this, 'defer_scripts', 10, 2);
        // Priority 0: shim must appear before any wp-i18n after-scripts that call wp.i18n.setLocaleData()
        $this->loader->add_action('wp_head', $this, 'inject_wp_core_shims', 0);
        $this->loader->add_action('wp_head', $this, 'add_script_resource_hints', 2);
        $this->loader->add_action('wp_footer', $this, 'flush_defer_count', 99);
    }

    /**
     * Persist the deferred-script count to the DB once per page load.
     * Called via wp_footer so we only do a single option write per request.
     */
    public function flush_defer_count() {
        if ( $this->deferred_this_request > 0 ) {
            $current = (int) get_option( 'coreboost_scripts_deferred_total', 0 );
            update_option( 'coreboost_scripts_deferred_total', $current + $this->deferred_this_request, false );
        }
    }
    
    /**
     * Script deferring with async support for independent scripts
     * Ensures jQuery-dependent scripts use defer (not async) to maintain execution order
     */
    public function defer_scripts($tag, $handle) {
        if (!$this->options['enable_script_defer'] || is_admin()) return $tag;
        
        // WordPress Core Script Defer - handle wp-hooks, wp-i18n, wp-dom-ready
        // These are deferred separately to break critical request chains.
        // The wp_core shim (injected at wp_head priority 0) proxies wp.hooks / wp.i18n /
        // wp.domReady so that any after-inline-scripts that depend on these globals will
        // queue their calls and replay them once the real module finishes loading.
        if (!empty($this->options['enable_wp_core_defer'])) {
            $wp_core_handles = array('wp-hooks', 'wp-i18n', 'wp-dom-ready');
            if (in_array($handle, $wp_core_handles)) {
                // Only add defer if not already present
                if (strpos($tag, ' defer') === false && strpos($tag, ' async') === false) {
                    return str_replace(' src', ' defer src', $tag);
                }
                return $tag;
            }
        }
        
        // Check excluded scripts using new exclusions system
        if ($this->exclusions->is_excluded($handle)) {
            return $tag;
        }
        
        // Check if script has jQuery as a dependency - these MUST use defer (not async)
        global $wp_scripts;
        $has_jquery_dependency = false;
        if (isset($wp_scripts->registered[$handle])) {
            $deps = $wp_scripts->registered[$handle]->deps;
            if (!empty($deps)) {
                $jquery_deps = array('jquery', 'jquery-core', 'jquery-migrate', 'jquery-ui-core');
                foreach ($jquery_deps as $jquery_dep) {
                    if (in_array($jquery_dep, $deps)) {
                        $has_jquery_dependency = true;
                        break;
                    }
                }
            }
        }
        
        // Check if this script should be processed
        $scripts_to_defer = array_filter(array_map('trim', explode("\n", $this->options['scripts_to_defer'])));
        $scripts_to_async = array_filter(array_map('trim', explode("\n", $this->options['scripts_to_async'])));
        
        // Determine if script should use async or defer
        // IMPORTANT: Scripts with jQuery dependencies MUST use defer (not async) to maintain execution order
        $use_async = in_array($handle, $scripts_to_async) && !$has_jquery_dependency;
        $use_defer = empty($scripts_to_defer) || in_array($handle, $scripts_to_defer);
        
        // Force defer for jQuery-dependent scripts (even if mistakenly in async list)
        if ($has_jquery_dependency && in_array($handle, $scripts_to_async)) {
            $use_async = false;
            $use_defer = true;
        }
        
        if ($use_async) {
            $this->deferred_this_request++;
            return str_replace(' src', ' async src', $tag);
        } elseif ($use_defer) {
            $this->deferred_this_request++;
            return str_replace(' src', ' defer src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Inject a JavaScript shim for WordPress core globals that may be called by
     * after-inline-scripts before the deferred module files have loaded.
     *
     * When enable_wp_core_defer defers wp-hooks, wp-i18n, and wp-dom-ready, any
     * plugin that called wp_set_script_translations() or wp_add_inline_script()
     * with position 'after' will have those inline scripts run synchronously at
     * HTML-parse time — before the deferred module executes. Without the shim those
     * calls (e.g. wp.i18n.setLocaleData(), wp.hooks.addFilter()) would throw a
     * TypeError because the globals are not yet defined.
     *
     * The shim installs a property descriptor with a setter on each global. Inline
     * callers see a lightweight proxy that queues their calls. When the deferred
     * module finally sets the real value (window.wp.i18n = …) the setter fires,
     * replays every queued call against the real implementation, and then removes
     * itself so subsequent accesses go directly to the module.
     */
    public function inject_wp_core_shims() {
        if (empty($this->options['enable_wp_core_defer'])) {
            return;
        }
        if (Context_Helper::should_skip_optimization()) {
            return;
        }
        ?>
<script id="coreboost-wp-core-shims">
(function(){
    if(typeof window.wp==='undefined')window.wp={};
    var wp=window.wp;

    /**
     * Install a setter-based proxy on window.wp[prop].
     * queue  – method calls to replay once the real module loads.
     * extras – extra method stubs beyond the minimal ones needed.
     */
    function installShim(prop, stubs) {
        if (wp[prop] && !wp[prop].__cbShim) return; // real module already loaded
        var _real = null;
        var _queue = [];
        var _shim = { __cbShim: true };
        stubs.forEach(function(fn) {
            _shim[fn] = function() {
                _queue.push({ fn: fn, args: Array.prototype.slice.call(arguments) });
            };
        });
        Object.defineProperty(wp, prop, {
            get: function() { return _real || _shim; },
            set: function(real) {
                _real = real;
                // Flush queued calls into the now-loaded real module
                _queue.forEach(function(item) {
                    if (typeof real[item.fn] === 'function') {
                        real[item.fn].apply(real, item.args);
                    }
                });
                _queue = [];
                // Replace with a simple value property so future sets work normally
                Object.defineProperty(wp, prop, { value: real, writable: true, configurable: true, enumerable: true });
            },
            configurable: true,
            enumerable: true
        });
    }

    installShim('i18n',    ['setLocaleData', 'resetLocaleData', '__', '_x', '_n', '_nx', 'sprintf', 'isRTL']);
    installShim('hooks',   ['addFilter', 'removeFilter', 'applyFilters', 'addAction', 'removeAction', 'doAction', 'currentFilter', 'hasFilter']);
    installShim('domReady', ['default']);
})();
</script>
        <?php
    }

    /**
     * Add resource hints for critical scripts
     */
    public function add_script_resource_hints() {
        // Skip if disabled or in admin/AJAX/Elementor preview contexts
        if (!$this->options['enable_script_defer'] || Context_Helper::should_skip_optimization()) {
            return;
        }
        
        global $wp_scripts;
        if (!isset($wp_scripts->registered)) {
            return;
        }
        
        // Preload jQuery and jQuery UI as critical dependencies.
        // jQuery is excluded from deferral (it's synchronous and other inline scripts depend on it),
        // so preloading it here lets the browser start fetching it early while parsing HTML.
        // NOTE: wp-hooks, wp-i18n, wp-dom-ready are intentionally NOT preloaded here when
        // enable_wp_core_defer is on — those scripts are deferred (non-render-blocking) so
        // adding a high-priority preload hint would cause PSI to count them in the critical
        // request chain even though they don't block render. Deferred scripts are fetched
        // naturally after HTML parsing without a preload hint.
        $critical_scripts = array(
            'jquery-core' => 'high',
            'jquery-migrate' => 'low',
            'jquery-ui-core' => 'low'
        );
        
        foreach ($critical_scripts as $handle => $priority) {
            if (isset($wp_scripts->registered[$handle])) {
                $src = $wp_scripts->registered[$handle]->src;

                // Build the versioned URL exactly as WordPress does in WP_Scripts::do_item():
                // append ?ver=, then pass through script_loader_src so any site filter
                // (e.g. remove_cssjs_ver stripping the query string) is also applied here.
                // This guarantees the preload href matches the <script src> attribute exactly,
                // so the browser can match them and avoid fetching the file twice.
                $ver = $wp_scripts->registered[$handle]->ver;
                if ($ver === false) {
                    $ver = $wp_scripts->default_version;
                }
                if (!empty($ver)) {
                    $src = add_query_arg('ver', $ver, $src);
                }
                $src = apply_filters('script_loader_src', $src, $handle);

                // Resolve root-relative and protocol-relative paths to full URLs.
                if (strpos($src, '//') === 0) {
                    $src = 'https:' . $src;
                } elseif (strpos($src, '/') === 0) {
                    $src = site_url($src);
                }

                if ($src) {
                    echo '<link rel="preload" href="' . esc_url($src) . '" as="script" fetchpriority="' . esc_attr($priority) . '">' . "\n";
                }
            }
        }
    }
    
    /**
     * Check if a script has inline scripts attached that would break if deferred
     *
     * @param string $handle Script handle
     * @return bool True if script has inline scripts attached
     */
    private function has_inline_scripts($handle) {
        global $wp_scripts;
        
        if (!isset($wp_scripts->registered[$handle])) {
            return false;
        }
        
        $script = $wp_scripts->registered[$handle];
        
        // Check for inline scripts added via wp_add_inline_script()
        if (!empty($script->extra['before']) || !empty($script->extra['after'])) {
            return true;
        }
        
        // Check for localized data via wp_localize_script()
        if (!empty($script->extra['data'])) {
            return true;
        }
        
        return false;
    }
}
