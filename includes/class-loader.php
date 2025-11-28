<?php
/**
 * Register all actions and filters for the plugin
 *
 * @package CoreBoost
 * @since 1.2.0
 */

namespace CoreBoost;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Loader
 */
class Loader {
    
    /**
     * Array of actions registered with WordPress
     *
     * @var array
     */
    protected $actions;
    
    /**
     * Array of filters registered with WordPress
     *
     * @var array
     */
    protected $filters;
    
    /**
     * Initialize the collections
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }
    
    /**
     * Add a new action to the collection
     *
     * @param string $hook The name of the WordPress action
     * @param object $component A reference to the instance of the object
     * @param string $callback The name of the function definition on the component
     * @param int $priority Optional. The priority at which the function should be fired
     * @param int $accepted_args Optional. The number of arguments that should be passed
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Add a new filter to the collection
     *
     * @param string $hook The name of the WordPress filter
     * @param object $component A reference to the instance of the object
     * @param string $callback The name of the function definition on the component
     * @param int $priority Optional. The priority at which the function should be fired
     * @param int $accepted_args Optional. The number of arguments that should be passed
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Utility function to register hooks
     *
     * @param array $hooks The collection of hooks
     * @param string $hook The name of the WordPress filter
     * @param object $component A reference to the instance of the object
     * @param string $callback The name of the function definition on the component
     * @param int $priority The priority at which the function should be fired
     * @param int $accepted_args The number of arguments that should be passed
     * @return array The collection of hooks
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );
        
        return $hooks;
    }
    
    /**
     * Register the filters and actions with WordPress
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
