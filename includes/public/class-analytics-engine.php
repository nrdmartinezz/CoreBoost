<?php
/**
 * Analytics Engine for CoreBoost
 *
 * Tracks script loading performance, pattern effectiveness, and provides metrics
 * for the dashboard. Collects data on script sizes, load times, and optimization impact.
 *
 * @package CoreBoost
 * @subpackage Analytics
 * @version 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CoreBoost_Analytics_Engine {
	/**
	 * Script metrics storage
	 *
	 * @var array
	 */
	private $script_metrics = array();

	/**
	 * Pattern effectiveness tracking
	 *
	 * @var array
	 */
	private $pattern_effectiveness = array();

	/**
	 * Performance recommendations
	 *
	 * @var array
	 */
	private $recommendations = array();

	/**
	 * A/B test data
	 *
	 * @var array
	 */
	private $ab_test_data = array();

	/**
	 * Debug mode
	 *
	 * @var bool
	 */
	private $debug_mode = false;

	/**
	 * WordPress options instance
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Constructor
	 *
	 * @param array $options Plugin options.
	 * @param bool  $debug_mode Debug mode flag.
	 */
	public function __construct( $options = array(), $debug_mode = false ) {
		// Only initialize on frontend (not admin or AJAX requests)
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$this->options     = $options;
		$this->debug_mode  = $debug_mode;
		$this->load_metrics();
	}

	/**
	 * Load metrics from storage
	 *
	 * @return void
	 */
	private function load_metrics() {
		$stored_metrics = get_option( 'coreboost_script_metrics', array() );
		if ( ! empty( $stored_metrics ) ) {
			$this->script_metrics = $stored_metrics;
		}

		$stored_patterns = get_option( 'coreboost_pattern_effectiveness', array() );
		if ( ! empty( $stored_patterns ) ) {
			$this->pattern_effectiveness = $stored_patterns;
		}

		$stored_tests = get_option( 'coreboost_ab_tests', array() );
		if ( ! empty( $stored_tests ) ) {
			$this->ab_test_data = $stored_tests;
		}
	}

	/**
	 * Record script loading metric
	 *
	 * @param string $handle Script handle.
	 * @param array  $data Script data (size, load_time, strategy, excluded, etc).
	 * @return void
	 */
	public function record_script_metric( $handle, $data ) {
		$timestamp = current_time( 'timestamp' );

		if ( ! isset( $this->script_metrics[ $handle ] ) ) {
			$this->script_metrics[ $handle ] = array(
				'first_seen' => $timestamp,
				'records'    => array(),
				'stats'      => array(),
			);
		}

		// Add record with timestamp
		$record = array_merge(
			$data,
			array(
				'timestamp' => $timestamp,
				'date'      => current_time( 'mysql' ),
			)
		);

		// Keep last 100 records per script
		$this->script_metrics[ $handle ]['records'][] = $record;
		if ( count( $this->script_metrics[ $handle ]['records'] ) > 100 ) {
			array_shift( $this->script_metrics[ $handle ]['records'] );
		}

		// Update statistics
		$this->update_script_stats( $handle );

		// Save to database
		update_option( 'coreboost_script_metrics', $this->script_metrics );

		if ( $this->debug_mode ) {
			error_log( "CoreBoost: Recorded metric for $handle - " . json_encode( $record ) );
		}
	}

	/**
	 * Update statistics for a script
	 *
	 * @param string $handle Script handle.
	 * @return void
	 */
	private function update_script_stats( $handle ) {
		$records = $this->script_metrics[ $handle ]['records'];

		if ( empty( $records ) ) {
			return;
		}

		$sizes     = array_column( $records, 'size' );
		$load_times = array_column( $records, 'load_time' );
		$excluded_count = count( array_filter( $records, fn( $r ) => ! empty( $r['excluded'] ) ) );

		$this->script_metrics[ $handle ]['stats'] = array(
			'total_records'    => count( $records ),
			'avg_size'         => array_sum( $sizes ) / count( $sizes ),
			'max_size'         => max( $sizes ),
			'avg_load_time'    => array_sum( $load_times ) / count( $load_times ),
			'max_load_time'    => max( $load_times ),
			'excluded_count'   => $excluded_count,
			'exclusion_rate'   => ( $excluded_count / count( $records ) ) * 100,
			'last_updated'     => current_time( 'mysql' ),
		);
	}

	/**
	 * Record pattern effectiveness
	 *
	 * @param string $pattern Pattern used.
	 * @param int    $scripts_affected Number of scripts affected.
	 * @param int    $bytes_saved Bytes saved by this pattern.
	 * @return void
	 */
	public function record_pattern_effectiveness( $pattern, $scripts_affected, $bytes_saved ) {
		if ( ! isset( $this->pattern_effectiveness[ $pattern ] ) ) {
			$this->pattern_effectiveness[ $pattern ] = array(
				'first_seen'      => current_time( 'timestamp' ),
				'times_used'      => 0,
				'scripts_affected' => 0,
				'bytes_saved'     => 0,
				'last_updated'    => current_time( 'mysql' ),
			);
		}

		$this->pattern_effectiveness[ $pattern ]['times_used']++;
		$this->pattern_effectiveness[ $pattern ]['scripts_affected'] += $scripts_affected;
		$this->pattern_effectiveness[ $pattern ]['bytes_saved'] += $bytes_saved;
		$this->pattern_effectiveness[ $pattern ]['last_updated'] = current_time( 'mysql' );

		update_option( 'coreboost_pattern_effectiveness', $this->pattern_effectiveness );

		if ( $this->debug_mode ) {
			error_log( "CoreBoost: Pattern '$pattern' effectiveness updated - Scripts: $scripts_affected, Bytes: $bytes_saved" );
		}
	}

	/**
	 * Get dashboard summary
	 *
	 * @return array Dashboard data.
	 */
	public function get_dashboard_summary() {
		$total_scripts = count( $this->script_metrics );
		$total_size    = 0;
		$total_load_time = 0;
		$excluded_count = 0;

		foreach ( $this->script_metrics as $handle => $data ) {
			if ( ! empty( $data['stats'] ) ) {
				$total_size += $data['stats']['avg_size'] ?? 0;
				$total_load_time += $data['stats']['avg_load_time'] ?? 0;
				$excluded_count += $data['stats']['excluded_count'] ?? 0;
			}
		}

		$total_pattern_bytes = array_sum( array_column( $this->pattern_effectiveness, 'bytes_saved' ) );

		return array(
			'total_scripts'           => $total_scripts,
			'total_size_kb'           => round( $total_size / 1024, 2 ),
			'total_load_time_ms'      => round( $total_load_time, 2 ),
			'scripts_excluded'        => $excluded_count,
			'exclusion_rate'          => $total_scripts > 0 ? round( ( $excluded_count / $total_scripts ) * 100, 2 ) : 0,
			'total_patterns'          => count( $this->pattern_effectiveness ),
			'total_bytes_saved'       => $total_pattern_bytes,
			'bytes_saved_mb'          => round( $total_pattern_bytes / ( 1024 * 1024 ), 2 ),
			'top_patterns'            => $this->get_top_patterns( 5 ),
			'slowest_scripts'         => $this->get_slowest_scripts( 5 ),
			'largest_scripts'         => $this->get_largest_scripts( 5 ),
			'generation_time'         => current_time( 'mysql' ),
		);
	}

	/**
	 * Get top performing patterns
	 *
	 * @param int $limit Number of patterns to return.
	 * @return array Top patterns.
	 */
	private function get_top_patterns( $limit = 5 ) {
		$patterns = $this->pattern_effectiveness;

		// Sort by bytes saved
		usort(
			$patterns,
			function( $a, $b ) {
				return $b['bytes_saved'] <=> $a['bytes_saved'];
			}
		);

		return array_slice( $patterns, 0, $limit );
	}

	/**
	 * Get slowest loading scripts
	 *
	 * @param int $limit Number of scripts to return.
	 * @return array Slowest scripts.
	 */
	private function get_slowest_scripts( $limit = 5 ) {
		$slowest = array();

		foreach ( $this->script_metrics as $handle => $data ) {
			if ( ! empty( $data['stats'] ) ) {
				$slowest[] = array(
					'handle'         => $handle,
					'avg_load_time'  => $data['stats']['avg_load_time'],
					'max_load_time'  => $data['stats']['max_load_time'],
					'avg_size_kb'    => round( $data['stats']['avg_size'] / 1024, 2 ),
				);
			}
		}

		usort(
			$slowest,
			function( $a, $b ) {
				return $b['avg_load_time'] <=> $a['avg_load_time'];
			}
		);

		return array_slice( $slowest, 0, $limit );
	}

	/**
	 * Get largest scripts
	 *
	 * @param int $limit Number of scripts to return.
	 * @return array Largest scripts.
	 */
	private function get_largest_scripts( $limit = 5 ) {
		$largest = array();

		foreach ( $this->script_metrics as $handle => $data ) {
			if ( ! empty( $data['stats'] ) ) {
				$largest[] = array(
					'handle'      => $handle,
					'avg_size_kb' => round( $data['stats']['avg_size'] / 1024, 2 ),
					'max_size_kb' => round( $data['stats']['max_size'] / 1024, 2 ),
					'avg_load_time' => $data['stats']['avg_load_time'],
				);
			}
		}

		usort(
			$largest,
			function( $a, $b ) {
				return $b['avg_size_kb'] <=> $a['avg_size_kb'];
			}
		);

		return array_slice( $largest, 0, $limit );
	}

	/**
	 * Generate performance recommendations
	 *
	 * @return array Recommendations.
	 */
	public function generate_recommendations() {
		$this->recommendations = array();

		// Check for large unoptimized scripts
		$largest_scripts = $this->get_largest_scripts( 10 );
		foreach ( $largest_scripts as $script ) {
			if ( $script['avg_size_kb'] > 100 ) {
				$this->recommendations[] = array(
					'type'       => 'critical',
					'title'      => 'Large Script Detected',
					'description' => $script['handle'] . ' is ' . $script['avg_size_kb'] . 'KB. Consider deferring or async loading.',
					'action'     => 'Review exclusion settings for this script',
					'impact'     => 'high',
				);
			}
		}

		// Check for slow scripts
		$slowest_scripts = $this->get_slowest_scripts( 5 );
		foreach ( $slowest_scripts as $script ) {
			if ( $script['avg_load_time'] > 500 ) {
				$this->recommendations[] = array(
					'type'        => 'warning',
					'title'       => 'Slow Script Loading',
					'description' => $script['handle'] . ' loads in ' . round( $script['avg_load_time'], 2 ) . 'ms. Consider using event hijacking.',
					'action'      => 'Enable event hijacking for non-critical scripts',
					'impact'      => 'medium',
				);
			}
		}

		// Check for optimization opportunities
		$total_excluded = array_sum( array_column( array_column( $this->script_metrics, 'stats' ), 'excluded_count' ) );
		if ( $total_excluded > 10 ) {
			$this->recommendations[] = array(
				'type'        => 'info',
				'title'       => 'Optimization Working',
				'description' => $total_excluded . ' scripts are being excluded. Great job!',
				'action'      => 'Monitor performance metrics regularly',
				'impact'      => 'positive',
			);
		}

		// Check if patterns are effective
		if ( count( $this->pattern_effectiveness ) > 0 ) {
			$total_bytes_saved = array_sum( array_column( $this->pattern_effectiveness, 'bytes_saved' ) );
			if ( $total_bytes_saved > ( 1024 * 100 ) ) { // 100KB
				$this->recommendations[] = array(
					'type'        => 'success',
					'title'       => 'Patterns Saving Data',
					'description' => 'Your patterns have saved ' . round( $total_bytes_saved / 1024, 2 ) . 'KB of data transfer.',
					'action'      => 'Continue using current pattern strategy',
					'impact'      => 'positive',
				);
			}
		}

		return $this->recommendations;
	}

	/**
	 * Start A/B test
	 *
	 * @param string $test_name Test name.
	 * @param string $variant_a Control variant.
	 * @param string $variant_b Treatment variant.
	 * @param int    $duration Test duration in seconds.
	 * @return bool
	 */
	public function start_ab_test( $test_name, $variant_a, $variant_b, $duration = 86400 ) {
		$test_id = 'ab_' . sanitize_key( $test_name );

		$this->ab_test_data[ $test_id ] = array(
			'name'           => $test_name,
			'variant_a'      => $variant_a,
			'variant_b'      => $variant_b,
			'started_at'     => current_time( 'timestamp' ),
			'duration'       => $duration,
			'enabled'        => true,
			'metrics_a'      => array(),
			'metrics_b'      => array(),
		);

		update_option( 'coreboost_ab_tests', $this->ab_test_data );

		return true;
	}

	/**
	 * Record A/B test metric
	 *
	 * @param string $test_id Test ID.
	 * @param string $variant Variant (a or b).
	 * @param array  $metric Metric data.
	 * @return void
	 */
	public function record_ab_metric( $test_id, $variant, $metric ) {
		if ( ! isset( $this->ab_test_data[ $test_id ] ) ) {
			return;
		}

		if ( 'a' === $variant ) {
			$this->ab_test_data[ $test_id ]['metrics_a'][] = $metric;
		} else {
			$this->ab_test_data[ $test_id ]['metrics_b'][] = $metric;
		}

		update_option( 'coreboost_ab_tests', $this->ab_test_data );
	}

	/**
	 * Get A/B test results
	 *
	 * @param string $test_id Test ID.
	 * @return array Test results with statistical analysis.
	 */
	public function get_ab_test_results( $test_id ) {
		if ( ! isset( $this->ab_test_data[ $test_id ] ) ) {
			return array();
		}

		$test = $this->ab_test_data[ $test_id ];

		// Calculate statistics
		$metrics_a = $test['metrics_a'];
		$metrics_b = $test['metrics_b'];

		$results = array(
			'test_name'     => $test['name'],
			'variant_a'     => $test['variant_a'],
			'variant_b'     => $test['variant_b'],
			'is_active'     => $test['enabled'],
			'started_at'    => $test['started_at'],
			'duration'      => $test['duration'],
			'samples_a'     => count( $metrics_a ),
			'samples_b'     => count( $metrics_b ),
		);

		// Only calculate if we have samples
		if ( ! empty( $metrics_a ) ) {
			$load_times_a = array_column( $metrics_a, 'load_time' );
			$results['avg_load_time_a'] = array_sum( $load_times_a ) / count( $load_times_a );
			$results['std_dev_a']        = $this->calculate_std_dev( $load_times_a );
		}

		if ( ! empty( $metrics_b ) ) {
			$load_times_b = array_column( $metrics_b, 'load_time' );
			$results['avg_load_time_b'] = array_sum( $load_times_b ) / count( $load_times_b );
			$results['std_dev_b']        = $this->calculate_std_dev( $load_times_b );
		}

		// Calculate improvement
		if ( isset( $results['avg_load_time_a'] ) && isset( $results['avg_load_time_b'] ) ) {
			$improvement = ( ( $results['avg_load_time_a'] - $results['avg_load_time_b'] ) / $results['avg_load_time_a'] ) * 100;
			$results['improvement_percent'] = round( $improvement, 2 );
			$results['winner'] = $improvement > 0 ? 'variant_b' : 'variant_a';
		}

		return $results;
	}

	/**
	 * Calculate standard deviation
	 *
	 * @param array $data Data array.
	 * @return float Standard deviation.
	 */
	private function calculate_std_dev( $data ) {
		if ( empty( $data ) ) {
			return 0;
		}

		$avg = array_sum( $data ) / count( $data );
		$sum_of_squares = array_sum(
			array_map(
				function( $x ) use ( $avg ) {
					return pow( $x - $avg, 2 );
				},
				$data
			)
		);

		return sqrt( $sum_of_squares / count( $data ) );
	}

	/**
	 * Clear old metrics (keep last 30 days)
	 *
	 * @return int Number of records cleared.
	 */
	public function cleanup_old_metrics() {
		$cutoff_time = current_time( 'timestamp' ) - ( 30 * 24 * 60 * 60 ); // 30 days
		$cleared     = 0;

		foreach ( $this->script_metrics as $handle => &$data ) {
			if ( ! empty( $data['records'] ) ) {
				$original_count = count( $data['records'] );
				$data['records'] = array_filter(
					$data['records'],
					function( $record ) use ( $cutoff_time ) {
						return $record['timestamp'] > $cutoff_time;
					}
				);
				$cleared += $original_count - count( $data['records'] );
			}
		}

		update_option( 'coreboost_script_metrics', $this->script_metrics );

		return $cleared;
	}

	/**
	 * Export analytics data
	 *
	 * @return array Complete analytics data.
	 */
	public function export_analytics() {
		return array(
			'dashboard_summary'       => $this->get_dashboard_summary(),
			'script_metrics'          => $this->script_metrics,
			'pattern_effectiveness'   => $this->pattern_effectiveness,
			'recommendations'         => $this->generate_recommendations(),
			'ab_tests'                => $this->ab_test_data,
			'exported_at'             => current_time( 'mysql' ),
		);
	}

	/**
	 * Get debug info
	 *
	 * @return array Debug information.
	 */
	public function get_debug_info() {
		return array(
			'scripts_tracked'     => count( $this->script_metrics ),
			'patterns_tracked'    => count( $this->pattern_effectiveness ),
			'ab_tests_active'     => count( array_filter( $this->ab_test_data, fn( $t ) => $t['enabled'] ) ),
			'memory_usage'        => size_format( memory_get_usage( true ) ),
			'metrics_stored'      => array_sum( array_map( fn( $m ) => count( $m['records'] ?? array() ), $this->script_metrics ) ),
		);
	}
}
