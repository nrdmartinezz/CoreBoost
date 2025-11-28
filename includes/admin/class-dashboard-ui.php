<?php
/**
 * Dashboard UI for CoreBoost Analytics
 *
 * Renders the admin dashboard with performance metrics, charts, and recommendations.
 * Provides comprehensive analytics visualization and performance insights.
 *
 * @package CoreBoost
 * @subpackage Admin
 * @version 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CoreBoost_Dashboard_UI {
	/**
	 * Analytics engine instance
	 *
	 * @var CoreBoost_Analytics_Engine
	 */
	private $analytics = null;

	/**
	 * Plugin options
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Constructor
	 *
	 * @param CoreBoost_Analytics_Engine $analytics Analytics engine.
	 * @param array                      $options Plugin options.
	 */
	public function __construct( $analytics, $options = array() ) {
		// Only initialize in admin area
		if ( ! is_admin() ) {
			return;
		}

		$this->analytics = $analytics;
		$this->options   = $options;
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_dashboard_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
		add_action( 'wp_ajax_coreboost_get_dashboard_data', array( $this, 'ajax_get_dashboard_data' ) );
		add_action( 'wp_ajax_coreboost_export_analytics', array( $this, 'ajax_export_analytics' ) );
		add_action( 'wp_ajax_coreboost_run_ab_test', array( $this, 'ajax_run_ab_test' ) );
	}

	/**
	 * Register dashboard page
	 *
	 * @return void
	 */
	public function register_dashboard_page() {
		add_submenu_page(
			'coreboost',
			'Performance Dashboard',
			'Dashboard',
			'manage_options',
			'coreboost-dashboard',
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Enqueue dashboard assets
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	public function enqueue_dashboard_assets( $hook ) {
		if ( 'coreboost_page_coreboost-dashboard' !== $hook ) {
			return;
		}

		// Enqueue Chart.js for data visualization
		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
			array(),
			'3.9.1',
			true
		);

		// Enqueue dashboard script
		wp_enqueue_script(
			'coreboost-dashboard',
			plugins_url( 'js/dashboard.js', __FILE__ ),
			array( 'chart-js', 'jquery' ),
			'2.5.0',
			true
		);

		// Localize script with AJAX nonce
		wp_localize_script(
			'coreboost-dashboard',
			'coreBoostDashboard',
			array(
				'nonce'     => wp_create_nonce( 'coreboost_dashboard_nonce' ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			)
		);

		// Enqueue dashboard styles
		wp_enqueue_style(
			'coreboost-dashboard',
			plugins_url( 'css/dashboard.css', __FILE__ ),
			array(),
			'2.5.0'
		);
	}

	/**
	 * Render dashboard page
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'coreboost' ) );
		}

		$summary = $this->analytics->get_dashboard_summary();
		$recommendations = $this->analytics->generate_recommendations();
		$debug_info = $this->analytics->get_debug_info();
		?>
		<div class="wrap coreboost-dashboard">
			<h1><?php esc_html_e( 'CoreBoost Performance Dashboard', 'coreboost' ); ?></h1>

			<!-- Summary Cards -->
			<div class="coreboost-dashboard-cards">
				<div class="dashboard-card">
					<div class="card-icon">📊</div>
					<div class="card-content">
						<h3><?php esc_html_e( 'Total Scripts', 'coreboost' ); ?></h3>
						<p class="card-value"><?php echo esc_html( $summary['total_scripts'] ); ?></p>
					</div>
				</div>

				<div class="dashboard-card">
					<div class="card-icon">⏱️</div>
					<div class="card-content">
						<h3><?php esc_html_e( 'Avg Load Time', 'coreboost' ); ?></h3>
						<p class="card-value"><?php echo esc_html( $summary['total_load_time_ms'] ); ?>ms</p>
					</div>
				</div>

				<div class="dashboard-card">
					<div class="card-icon">📦</div>
					<div class="card-content">
						<h3><?php esc_html_e( 'Total Size', 'coreboost' ); ?></h3>
						<p class="card-value"><?php echo esc_html( $summary['total_size_kb'] ); ?>KB</p>
					</div>
				</div>

				<div class="dashboard-card">
					<div class="card-icon">✂️</div>
					<div class="card-content">
						<h3><?php esc_html_e( 'Bytes Saved', 'coreboost' ); ?></h3>
						<p class="card-value"><?php echo esc_html( $summary['bytes_saved_mb'] ); ?>MB</p>
					</div>
				</div>
			</div>

			<!-- Main Dashboard Content -->
			<div class="coreboost-dashboard-content">
				<!-- Charts Section -->
				<div class="dashboard-section">
					<h2><?php esc_html_e( 'Performance Metrics', 'coreboost' ); ?></h2>
					<div class="charts-container">
						<div class="chart-wrapper">
							<h3><?php esc_html_e( 'Script Distribution', 'coreboost' ); ?></h3>
							<canvas id="scriptDistributionChart"></canvas>
						</div>
						<div class="chart-wrapper">
							<h3><?php esc_html_e( 'Load Time Distribution', 'coreboost' ); ?></h3>
							<canvas id="loadTimeChart"></canvas>
						</div>
					</div>
				</div>

				<!-- Slowest Scripts -->
				<div class="dashboard-section">
					<h2><?php esc_html_e( 'Slowest Scripts', 'coreboost' ); ?></h2>
					<table class="wp-list-table fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Script Handle', 'coreboost' ); ?></th>
								<th><?php esc_html_e( 'Avg Load Time (ms)', 'coreboost' ); ?></th>
								<th><?php esc_html_e( 'Max Load Time (ms)', 'coreboost' ); ?></th>
								<th><?php esc_html_e( 'Size (KB)', 'coreboost' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $summary['slowest_scripts'] as $script ) : ?>
								<tr>
									<td><code><?php echo esc_html( $script['handle'] ); ?></code></td>
									<td><?php echo esc_html( round( $script['avg_load_time'], 2 ) ); ?></td>
									<td><?php echo esc_html( round( $script['max_load_time'], 2 ) ); ?></td>
									<td><?php echo esc_html( $script['avg_size_kb'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Largest Scripts -->
				<div class="dashboard-section">
					<h2><?php esc_html_e( 'Largest Scripts', 'coreboost' ); ?></h2>
					<table class="wp-list-table fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Script Handle', 'coreboost' ); ?></th>
								<th><?php esc_html_e( 'Avg Size (KB)', 'coreboost' ); ?></th>
								<th><?php esc_html_e( 'Max Size (KB)', 'coreboost' ); ?></th>
								<th><?php esc_html_e( 'Avg Load Time (ms)', 'coreboost' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $summary['largest_scripts'] as $script ) : ?>
								<tr>
									<td><code><?php echo esc_html( $script['handle'] ); ?></code></td>
									<td><?php echo esc_html( $script['avg_size_kb'] ); ?></td>
									<td><?php echo esc_html( $script['max_size_kb'] ); ?></td>
									<td><?php echo esc_html( round( $script['avg_load_time'], 2 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Top Patterns -->
				<div class="dashboard-section">
					<h2><?php esc_html_e( 'Top Performing Patterns', 'coreboost' ); ?></h2>
					<table class="wp-list-table fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Pattern', 'coreboost' ); ?></th>
								<th><?php esc_html_e( 'Times Used', 'coreboost' ); ?></th>
								<th><?php esc_html_e( 'Scripts Affected', 'coreboost' ); ?></th>
								<th><?php esc_html_e( 'Bytes Saved (KB)', 'coreboost' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $summary['top_patterns'] as $pattern ) : ?>
								<tr>
									<td><code><?php echo esc_html( $pattern ); ?></code></td>
									<td><?php echo esc_html( $pattern['times_used'] ); ?></td>
									<td><?php echo esc_html( $pattern['scripts_affected'] ); ?></td>
									<td><?php echo esc_html( round( $pattern['bytes_saved'] / 1024, 2 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Recommendations -->
				<div class="dashboard-section">
					<h2><?php esc_html_e( 'Recommendations', 'coreboost' ); ?></h2>
					<div class="recommendations-container">
						<?php foreach ( $recommendations as $rec ) : ?>
							<div class="recommendation-card recommendation-<?php echo esc_attr( $rec['type'] ); ?>">
								<div class="rec-title">
									<?php
									$icons = array(
										'critical' => '🔴',
										'warning'  => '🟠',
										'info'     => '🔵',
										'success'  => '🟢',
									);
									echo isset( $icons[ $rec['type'] ] ) ? $icons[ $rec['type'] ] . ' ' : '';
									echo esc_html( $rec['title'] );
									?>
								</div>
								<p><?php echo esc_html( $rec['description'] ); ?></p>
								<small><?php echo esc_html( 'Action: ' . $rec['action'] ); ?></small>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- A/B Testing Section -->
				<div class="dashboard-section">
					<h2><?php esc_html_e( 'A/B Testing', 'coreboost' ); ?></h2>
					<button class="button button-primary" id="coreboost-start-ab-test">
						<?php esc_html_e( 'Start New Test', 'coreboost' ); ?>
					</button>
					<div id="coreboost-ab-tests-container"></div>
				</div>

				<!-- Export & Debug -->
				<div class="dashboard-section">
					<h2><?php esc_html_e( 'Tools', 'coreboost' ); ?></h2>
					<button class="button" id="coreboost-export-data">
						<?php esc_html_e( 'Export Analytics', 'coreboost' ); ?>
					</button>
					<button class="button" id="coreboost-cleanup-metrics">
						<?php esc_html_e( 'Cleanup Old Metrics', 'coreboost' ); ?>
					</button>

					<?php if ( current_user_can( 'manage_options' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
						<div class="debug-info" style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
							<h4><?php esc_html_e( 'Debug Information', 'coreboost' ); ?></h4>
							<pre><?php echo esc_html( wp_json_encode( $debug_info, JSON_PRETTY_PRINT ) ); ?></pre>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Get dashboard data
	 *
	 * @return void
	 */
	public function ajax_get_dashboard_data() {
		check_ajax_referer( 'coreboost_dashboard_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$data = array(
			'summary'         => $this->analytics->get_dashboard_summary(),
			'recommendations' => $this->analytics->generate_recommendations(),
		);

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Export analytics
	 *
	 * @return void
	 */
	public function ajax_export_analytics() {
		check_ajax_referer( 'coreboost_dashboard_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$data = $this->analytics->export_analytics();

		// Return as JSON file download
		header( 'Content-Disposition: attachment; filename=coreboost-analytics-' . current_time( 'Y-m-d-H-i-s' ) . '.json' );
		header( 'Content-Type: application/json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * AJAX: Run A/B test
	 *
	 * @return void
	 */
	public function ajax_run_ab_test() {
		check_ajax_referer( 'coreboost_dashboard_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		// Sanitize input
		$test_name = isset( $_POST['test_name'] ) ? sanitize_text_field( wp_unslash( $_POST['test_name'] ) ) : '';
		$variant_a = isset( $_POST['variant_a'] ) ? sanitize_text_field( wp_unslash( $_POST['variant_a'] ) ) : '';
		$variant_b = isset( $_POST['variant_b'] ) ? sanitize_text_field( wp_unslash( $_POST['variant_b'] ) ) : '';
		$duration  = isset( $_POST['duration'] ) ? absint( wp_unslash( $_POST['duration'] ) ) : 86400;

		if ( empty( $test_name ) || empty( $variant_a ) || empty( $variant_b ) ) {
			wp_send_json_error( 'Missing test data' );
		}

		$result = $this->analytics->start_ab_test( $test_name, $variant_a, $variant_b, $duration );

		wp_send_json_success(
			array(
				'message' => 'A/B test started successfully',
				'test_id' => 'ab_' . sanitize_key( $test_name ),
			)
		);
	}
}
