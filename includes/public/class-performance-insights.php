<?php
/**
 * Performance Insights Tracker for CoreBoost
 *
 * Integrates with Script_Optimizer to record script metrics and performance data
 *
 * @package CoreBoost
 * @subpackage Analytics
 * @version 2.5.0
 */

namespace CoreBoost\PublicCore;

use CoreBoost\Core\Context_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Performance_Insights {
	/**
	 * Analytics engine instance
	 *
	 * @var Analytics_Engine
	 */
	private $analytics = null;

	/**
	 * Script metrics buffer
	 *
	 * @var array
	 */
	private $metrics_buffer = array();

	/**
	 * Track metrics flag
	 *
	 * @var bool
	 */
	private $track_enabled = true;

	/**
	 * Constructor
	 *
	 * @param Analytics_Engine $analytics Analytics engine.
	 */
	public function __construct( $analytics ) {
		// Only initialize on frontend (not admin or AJAX requests)
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$this->analytics = $analytics;
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Track script loading on wp_footer (after all scripts loaded)
		add_action( 'wp_footer', array( $this, 'flush_metrics_buffer' ), 99 );
		
		// Cleanup old metrics daily
		add_action( 'wp_scheduled_event_coreboost_cleanup', array( $this, 'schedule_cleanup' ) );
		
		// Schedule cleanup on first run
		if ( ! wp_next_scheduled( 'wp_scheduled_event_coreboost_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_scheduled_event_coreboost_cleanup' );
		}
	}

	/**
	 * Record script loading data
	 *
	 * @param string $handle Script handle.
	 * @param array  $data Script data (size, load_time, strategy, etc).
	 * @return void
	 */
	public function record_script( $handle, $data ) {
		if ( ! $this->track_enabled || ! $this->analytics ) {
			return;
		}

		// Add to buffer for batch processing
		$this->metrics_buffer[ $handle ] = array_merge(
			array(
				'handle'      => $handle,
				'timestamp'   => current_time( 'timestamp' ),
			),
			$data
		);
	}

	/**
	 * Record pattern effectiveness
	 *
	 * @param string $pattern Pattern used.
	 * @param array  $matched_scripts Scripts matched by pattern.
	 * @return void
	 */
	public function record_pattern_usage( $pattern, $matched_scripts ) {
		if ( ! $this->analytics ) {
			return;
		}

		$scripts_affected = count( $matched_scripts );
		$bytes_saved      = 0;

		// Calculate bytes saved
		foreach ( $matched_scripts as $script ) {
			$bytes_saved += isset( $script['size'] ) ? intval( $script['size'] ) : 0;
		}

		$this->analytics->record_pattern_effectiveness( $pattern, $scripts_affected, $bytes_saved );
	}

	/**
	 * Flush metrics buffer to storage
	 *
	 * @return void
	 */
	public function flush_metrics_buffer() {
		if ( empty( $this->metrics_buffer ) || ! $this->analytics ) {
			return;
		}

		foreach ( $this->metrics_buffer as $handle => $data ) {
			$this->analytics->record_script_metric( $handle, $data );
		}

		$this->metrics_buffer = array();
	}

	/**
	 * Schedule cleanup of old metrics
	 *
	 * @return void
	 */
	public function schedule_cleanup() {
		if ( ! $this->analytics ) {
			return;
		}

		$cleared = $this->analytics->cleanup_old_metrics();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Context_Helper::debug_log( "Cleaned up $cleared old metric records" );
		}
	}

	/**
	 * Enable metric tracking
	 *
	 * @return void
	 */
	public function enable_tracking() {
		$this->track_enabled = true;
	}

	/**
	 * Disable metric tracking
	 *
	 * @return void
	 */
	public function disable_tracking() {
		$this->track_enabled = false;
	}

	/**
	 * Get metrics buffer
	 *
	 * @return array
	 */
	public function get_buffer() {
		return $this->metrics_buffer;
	}

	/**
	 * Clear metrics buffer
	 *
	 * @return void
	 */
	public function clear_buffer() {
		$this->metrics_buffer = array();
	}
}
