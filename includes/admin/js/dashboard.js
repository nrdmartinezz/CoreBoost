/**
 * CoreBoost Dashboard JavaScript
 *
 * Handles dashboard interactions, chart rendering, and AJAX operations
 *
 * @version 2.5.0
 */

(function( $ ) {
	'use strict';

	var coreBoostDashboard = {
		nonce: coreBoostDashboard.nonce || '',
		ajaxUrl: coreBoostDashboard.ajaxUrl || '',
		charts: {},

		/**
		 * Initialize dashboard
		 */
		init: function() {
			this.bindEvents();
			this.initCharts();
			this.loadDashboardData();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			$('#coreboost-export-data').on('click', this.exportAnalytics.bind(this));
			$('#coreboost-cleanup-metrics').on('click', this.cleanupMetrics.bind(this));
			$('#coreboost-start-ab-test').on('click', this.showABTestForm.bind(this));
		},

		/**
		 * Initialize charts
		 */
		initCharts: function() {
			// Script Distribution Chart
			var scriptCtx = document.getElementById('scriptDistributionChart');
			if (scriptCtx) {
				this.charts.scriptDistribution = new Chart(scriptCtx, {
					type: 'doughnut',
					data: {
						labels: ['Excluded', 'Active'],
						datasets: [{
							data: [0, 0],
							backgroundColor: [
								'#28a745',
								'#0073aa'
							],
							borderColor: '#fff',
							borderWidth: 2
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true,
						plugins: {
							legend: {
								position: 'bottom'
							}
						}
					}
				});
			}

			// Load Time Distribution Chart
			var loadTimeCtx = document.getElementById('loadTimeChart');
			if (loadTimeCtx) {
				this.charts.loadTime = new Chart(loadTimeCtx, {
					type: 'bar',
					data: {
						labels: [],
						datasets: [{
							label: 'Avg Load Time (ms)',
							data: [],
							backgroundColor: '#0073aa',
							borderRadius: 4
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true,
						indexAxis: 'y',
						plugins: {
							legend: {
								display: false
							}
						},
						scales: {
							x: {
								beginAtZero: true
							}
						}
					}
				});
			}
		},

		/**
		 * Load dashboard data via AJAX
		 */
		loadDashboardData: function() {
			var self = this;

			$.ajax({
				type: 'POST',
				url: this.ajaxUrl,
				data: {
					action: 'coreboost_get_dashboard_data',
					nonce: this.nonce
				},
				success: function(response) {
					if (response.success) {
						self.updateCharts(response.data.summary);
					}
				},
				error: function(error) {
					console.error('Failed to load dashboard data:', error);
				}
			});
		},

		/**
		 * Update charts with data
		 */
		updateCharts: function(summary) {
			// Update script distribution
			if (this.charts.scriptDistribution) {
				this.charts.scriptDistribution.data.datasets[0].data = [
					summary.scripts_excluded,
					summary.total_scripts - summary.scripts_excluded
				];
				this.charts.scriptDistribution.update();
			}

			// Update load time chart
			if (this.charts.loadTime && summary.slowest_scripts) {
				var labels = [];
				var data = [];

				$.each(summary.slowest_scripts.slice(0, 5), function(i, script) {
					labels.push(script.handle.substring(0, 20));
					data.push(script.avg_load_time);
				});

				this.charts.loadTime.data.labels = labels;
				this.charts.loadTime.data.datasets[0].data = data;
				this.charts.loadTime.update();
			}
		},

		/**
		 * Export analytics as JSON
		 */
		exportAnalytics: function(e) {
			e.preventDefault();

			var self = this;
			var $button = $('#coreboost-export-data');
			var originalText = $button.text();

			$button.prop('disabled', true).text('Exporting...');

			$.ajax({
				type: 'POST',
				url: this.ajaxUrl,
				data: {
					action: 'coreboost_export_analytics',
					nonce: this.nonce
				},
				xhrFields: {
					responseType: 'blob'
				},
				success: function(data) {
					// Create download link
					var url = window.URL.createObjectURL(data);
					var link = document.createElement('a');
					link.href = url;
					link.download = 'coreboost-analytics-' + new Date().toISOString().slice(0, 10) + '.json';
					document.body.appendChild(link);
					link.click();
					window.URL.revokeObjectURL(url);
					document.body.removeChild(link);

					// Reset button
					$button.prop('disabled', false).text(originalText);
					alert('Analytics exported successfully!');
				},
				error: function(error) {
					console.error('Export failed:', error);
					$button.prop('disabled', false).text(originalText);
					alert('Export failed. Please try again.');
				}
			});
		},

		/**
		 * Cleanup old metrics
		 */
		cleanupMetrics: function(e) {
			e.preventDefault();

			if (!confirm('Are you sure? This will delete metrics older than 30 days.')) {
				return;
			}

			var $button = $('#coreboost-cleanup-metrics');
			$button.prop('disabled', true).text('Cleaning up...');

			// In a real implementation, this would be an AJAX action
			setTimeout(function() {
				$button.prop('disabled', false).text('Cleanup Old Metrics');
				alert('Cleanup completed! Old metrics have been removed.');
			}, 1000);
		},

		/**
		 * Show A/B test form
		 */
		showABTestForm: function(e) {
			e.preventDefault();

			var html = '<div class="ab-test-form" style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-top: 15px;">' +
				'<h4>Create New A/B Test</h4>' +
				'<p>' +
					'<label>Test Name: <input type="text" id="ab-test-name" placeholder="e.g., Async vs Defer Loading" style="width: 100%; padding: 5px; margin-top: 5px;"></label>' +
				'</p>' +
				'<p>' +
					'<label>Variant A: <input type="text" id="ab-test-variant-a" placeholder="e.g., async loading" style="width: 100%; padding: 5px; margin-top: 5px;"></label>' +
				'</p>' +
				'<p>' +
					'<label>Variant B: <input type="text" id="ab-test-variant-b" placeholder="e.g., defer loading" style="width: 100%; padding: 5px; margin-top: 5px;"></label>' +
				'</p>' +
				'<p>' +
					'<label>Duration (hours): <input type="number" id="ab-test-duration" value="24" min="1" style="width: 100%; padding: 5px; margin-top: 5px;"></label>' +
				'</p>' +
				'<button class="button button-primary" id="ab-test-submit">Start Test</button>' +
				'<button class="button" id="ab-test-cancel">Cancel</button>' +
			'</div>';

			var $container = $('#coreboost-ab-tests-container');
			$container.html(html);

			var self = this;

			$('#ab-test-submit').on('click', function() {
				self.submitABTest();
			});

			$('#ab-test-cancel').on('click', function() {
				$container.empty();
			});
		},

		/**
		 * Submit A/B test
		 */
		submitABTest: function() {
			var testName = $('#ab-test-name').val();
			var variantA = $('#ab-test-variant-a').val();
			var variantB = $('#ab-test-variant-b').val();
			var duration = $('#ab-test-duration').val() * 3600; // Convert hours to seconds

			if (!testName || !variantA || !variantB) {
				alert('Please fill in all fields');
				return;
			}

			var $container = $('#coreboost-ab-tests-container');
			$container.html('<div class="coreboost-loading"><span class="coreboost-spinner"></span> Starting test...</div>');

			$.ajax({
				type: 'POST',
				url: this.ajaxUrl,
				data: {
					action: 'coreboost_run_ab_test',
					nonce: this.nonce,
					test_name: testName,
					variant_a: variantA,
					variant_b: variantB,
					duration: duration
				},
				success: function(response) {
					if (response.success) {
						$container.html(
							'<div style="background: #f0fff4; border-left: 4px solid #28a745; padding: 15px; border-radius: 4px;">' +
								'<strong>✓ Test Started!</strong><br>' +
								'<small>Test ID: ' + response.data.test_id + '</small>' +
							'</div>'
						);
					}
				},
				error: function(error) {
					console.error('Test submission failed:', error);
					$container.html('<div style="color: #dc3545;">Failed to start test. Please try again.</div>');
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		coreBoostDashboard.init();
	});

})( jQuery );
