<?php
/**
 * Settings Facade - Orchestrates Settings Management
 *
 * Acts as a facade/orchestrator for settings management.
 * Delegates to specialized classes following Single Responsibility Principle.
 *
 * @package CoreBoost
 * @since 1.2.0
 * @version 2.7.0 - Refactored into facade pattern
 */

namespace CoreBoost\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Settings
 *
 * Facade that orchestrates:
 * - Settings_Registry: Field registration
 * - Settings_Renderer: Field rendering
 * - Settings_Sanitizer: Input validation
 */
class Settings {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Settings registry
     *
     * @var Settings_Registry
     */
    private $registry;
    
    /**
     * Settings renderer
     *
     * @var Settings_Renderer
     */
    private $renderer;
    
    /**
     * Settings sanitizer
     *
     * @var Settings_Sanitizer
     */
    private $sanitizer;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
        
        // Initialize specialized components
        $this->renderer = new Settings_Renderer($options);
        $this->registry = new Settings_Registry($options, $this->renderer);
        
        // Sanitizer needs access to delegated settings
        $this->sanitizer = new Settings_Sanitizer(
            $this->registry->get_tag_settings(),
            $this->registry->get_script_settings(),
            $this->registry->get_advanced_settings()
        );
    }
    
    /**
     * Register settings
     *
     * Delegates to Settings_Registry
     */
    public function register_settings() {
        $this->registry->register_all_settings(array($this, 'sanitize_options'));
    }
    
    /**
     * Sanitize options
     *
     * Delegates to Settings_Sanitizer
     *
     * @param array $input Raw input from form
     * @return array Sanitized options
     */
    public function sanitize_options($input) {
        return $this->sanitizer->sanitize_options($input);
    }
}
