/**
 * CoreBoost Bulk Image Converter
 * 
 * Handles scanning, processing, and progress tracking for bulk image conversion
 * to AVIF/WebP formats with live counters and circular progress indicators.
 *
 * @package CoreBoost
 * @since 2.7.0
 */

(function() {
    'use strict';

    // Only run on images tab
    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('tab') || 'hero';
    
    if (currentTab !== 'images') {
        console.log('CoreBoost: Bulk converter only available on Images tab');
        return;
    }

    // Configuration
    const config = {
        pollInterval: 3000, // 3 seconds
        ajaxurl: (typeof coreBoostAdmin !== 'undefined' ? coreBoostAdmin.ajaxurl : window.ajaxurl) || '/wp-admin/admin-ajax.php',
        nonce: (typeof coreBoostAdmin !== 'undefined' ? coreBoostAdmin.nonce : document.querySelector('input[name="coreboost_nonce"]')?.value) || '',
    };

    // State
    let state = {
        isRunning: false,
        isPaused: false,
        imageCount: 0,
        imagesConverted: 0,
        currentBatch: 0,
        totalBatches: 0,
        startTime: null,
        batchSize: 0,
        pollTimer: null,
        elapsedTimer: null,
    };

    // DOM elements
    const elements = {
        startBtn: document.getElementById('coreboost-start-bulk'),
        stopBtn: document.getElementById('coreboost-stop-bulk'),
        statusText: document.getElementById('coreboost-bulk-status'),
        imageCountText: document.getElementById('coreboost-image-count'),
        imagesConvertedText: document.getElementById('coreboost-images-converted'),
        batchSizeText: document.getElementById('coreboost-batch-size'),
        estTimeText: document.getElementById('coreboost-est-time'),
        progressContainer: document.getElementById('coreboost-progress-container'),
        progressBar: document.getElementById('coreboost-progress-bar'),
        progressText: document.getElementById('coreboost-progress-text'),
        timeElapsed: document.getElementById('coreboost-time-elapsed'),
        timeRemaining: document.getElementById('coreboost-time-remaining'),
        errorContainer: document.getElementById('coreboost-error-message'),
        errorText: document.getElementById('coreboost-error-text'),
        successContainer: document.getElementById('coreboost-success-message'),
        successText: document.getElementById('coreboost-success-text'),
    };

    /**
     * Format seconds to human-readable time
     */
    function formatTime(seconds) {
        if (seconds < 60) {
            return Math.floor(seconds) + 's';
        }
        const minutes = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return minutes + 'm ' + secs + 's';
    }

    /**
     * Start elapsed time ticker
     */
    function startElapsedTimer() {
        // Clear any existing timer
        if (state.elapsedTimer) {
            clearInterval(state.elapsedTimer);
        }

        // Update elapsed time every second
        state.elapsedTimer = setInterval(() => {
            if (state.isRunning && state.startTime) {
                const elapsed = Date.now() - state.startTime;
                const elapsedSeconds = elapsed / 1000;
                elements.timeElapsed.textContent = 'Elapsed: ' + formatTime(elapsedSeconds);
            }
        }, 1000);
    }

    /**
     * Stop elapsed time ticker
     */
    function stopElapsedTimer() {
        if (state.elapsedTimer) {
            clearInterval(state.elapsedTimer);
            state.elapsedTimer = null;
        }
    }

    /**
     * Update circular progress indicator
     */
    function updateCircularProgress(elementId, percentage) {
        const circle = document.getElementById(elementId);
        if (!circle) return;
        
        const circumference = 226.19; // 2 * PI * radius (36)
        const offset = circumference - (percentage / 100) * circumference;
        circle.style.strokeDashoffset = offset;
    }

    /**
     * Update statistics dashboard
     */
    function updateStatsDashboard(stats) {
        const dashboard = document.getElementById('coreboost-stats-dashboard');
        if (!dashboard) return;
        
        const total = stats.total || state.imageCount || 0;
        const converted = stats.converted || state.imagesConverted || 0;
        const orphaned = stats.orphaned || 0;
        const unconverted = total - converted;
        
        // Calculate percentages
        const percentConverted = total > 0 ? Math.round((converted / total) * 100) : 0;
        const percentOrphaned = total > 0 ? Math.round((orphaned / total) * 100) : 0;
        const percentUnconverted = total > 0 ? Math.round((unconverted / total) * 100) : 0;
        
        // Update converted
        const percentConvertedEl = document.getElementById('percent-converted');
        const countConvertedEl = document.getElementById('count-converted');
        if (percentConvertedEl) percentConvertedEl.textContent = percentConverted + '%';
        if (countConvertedEl) countConvertedEl.textContent = converted;
        updateCircularProgress('circle-converted', percentConverted);
        
        // Update orphaned
        const percentOrphanedEl = document.getElementById('percent-orphaned');
        const countOrphanedEl = document.getElementById('count-orphaned');
        if (percentOrphanedEl) percentOrphanedEl.textContent = percentOrphaned + '%';
        if (countOrphanedEl) countOrphanedEl.textContent = orphaned;
        updateCircularProgress('circle-orphaned', percentOrphaned);
        
        // Update unconverted
        const percentUnconvertedEl = document.getElementById('percent-unconverted');
        const countUnconvertedEl = document.getElementById('count-unconverted');
        if (percentUnconvertedEl) percentUnconvertedEl.textContent = percentUnconverted + '%';
        if (countUnconvertedEl) countUnconvertedEl.textContent = unconverted;
        updateCircularProgress('circle-unconverted', percentUnconverted);
        
        // Update total
        const countTotalEl = document.getElementById('count-total');
        if (countTotalEl) countTotalEl.textContent = total;
    }

    /**
     * Save progress to localStorage
     */
    function saveProgress() {
        try {
            localStorage.setItem('coreboost_bulk_progress', JSON.stringify({
                currentBatch: state.currentBatch,
                totalBatches: state.totalBatches,
                imageCount: state.imageCount,
                startTime: state.startTime,
                timestamp: Date.now()
            }));
        } catch (e) {
            console.warn('Could not save progress to localStorage:', e);
        }
    }

    /**
     * Load progress from localStorage
     */
    function loadProgress() {
        try {
            const saved = localStorage.getItem('coreboost_bulk_progress');
            if (!saved) return null;
            
            const progress = JSON.parse(saved);
            // Only restore if less than 1 hour old
            if (Date.now() - progress.timestamp < 3600000) {
                return progress;
            }
            localStorage.removeItem('coreboost_bulk_progress');
        } catch (e) {
            console.warn('Could not load progress from localStorage:', e);
        }
        return null;
    }

    /**
     * Clear cache after conversion
     */
    async function clearCacheAfterConversion() {
        try {
            const response = await fetch(config.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'coreboost_clear_cache',
                    nonce: config.nonce,
                }),
            });
            const data = await response.json();
            if (data.success) {
                console.log('Cache cleared successfully after bulk conversion');
            }
        } catch (error) {
            console.warn('Could not clear cache:', error);
        }
    }

    /**
     * Update UI with current progress
     */
    function updateProgress(progress) {
        const percentage = Math.min(100, Math.round((progress.currentBatch / progress.totalBatches) * 100));
        const elapsed = Date.now() - state.startTime;
        const elapsedSeconds = elapsed / 1000;
        
        // Update images converted counter if we have batch results
        if (progress.batchResults) {
            state.imagesConverted += progress.batchResults.success || 0;
            elements.imagesConvertedText.textContent = state.imagesConverted;
        }
        
        // Calculate remaining time
        let remainingSeconds = 0;
        if (progress.totalBatches > 0 && progress.currentBatch > 0) {
            const timePerBatch = elapsedSeconds / progress.currentBatch;
            remainingSeconds = timePerBatch * (progress.totalBatches - progress.currentBatch);
        }

        // Update progress bar
        elements.progressBar.style.width = percentage + '%';
        elements.progressBar.textContent = percentage + '%';

        // Update status text
        elements.statusText.textContent = 'Processing...';
        elements.statusText.style.color = '#F57C00';

        // Update progress text with batch results if available
        let progressText = 'Batch ' + progress.currentBatch + ' of ' + progress.totalBatches + ' (' + percentage + '%)';
        if (progress.batchResults) {
            const results = progress.batchResults;
            progressText += ' - ' + results.success + ' success, ' + results.failed + ' failed, ' + results.skipped + ' skipped';
        }
        elements.progressText.textContent = progressText;

        // Update remaining time (elapsed time updates via interval)
        elements.timeRemaining.textContent = 'Remaining: ' + formatTime(remainingSeconds);

        // Show progress container
        elements.progressContainer.style.display = 'block';
        
        // Update stats dashboard
        updateStatsDashboard({
            total: state.imageCount,
            converted: state.imagesConverted,
            orphaned: 0
        });
    }

    /**
     * Show error message
     */
    function showError(message) {
        // Stop elapsed timer on error
        stopElapsedTimer();
        
        elements.errorText.textContent = message;
        elements.errorContainer.style.display = 'block';
        elements.successContainer.style.display = 'none';
        state.isRunning = false;
        resetUI();
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        elements.successText.textContent = message;
        elements.successContainer.style.display = 'block';
        elements.errorContainer.style.display = 'none';
        state.isRunning = false;
        resetUI();
    }

    /**
     * Hide messages
     */
    function hideMessages() {
        elements.errorContainer.style.display = 'none';
        elements.successContainer.style.display = 'none';
    }

    /**
     * Reset UI to initial state
     */
    function resetUI() {
        state.isRunning = false;
        state.isPaused = false;
        
        // Stop elapsed timer
        stopElapsedTimer();
        
        elements.startBtn.style.display = 'inline-block';
        elements.startBtn.disabled = false;
        elements.stopBtn.style.display = 'none';
        elements.stopBtn.disabled = true;
        
        elements.statusText.textContent = 'Not started';
        elements.statusText.style.color = '#666';
        
        // Reset images converted counter
        state.imagesConverted = 0;
        if (elements.imagesConvertedText) {
            elements.imagesConvertedText.textContent = '0';
        }
        
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    /**
     * Scan uploads folder for images to convert
     */
    function scanImages() {
        return fetch(config.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'coreboost_scan_uploads',
                _wpnonce: config.nonce,
                start_conversion: 'true',
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.data?.message || 'Failed to scan images');
            }
            return data.data;
        });
    }

    async function startConversion() {
        // Check if format conversion is enabled
        const formatEnabled = elements.startBtn.getAttribute('data-format-enabled') === '1';
        if (!formatEnabled) {
            showError('Image Format Conversion is disabled. Please enable "Generate AVIF/WebP Variants" in the settings below and save before using the bulk converter.');
            return;
        }

        state.isRunning = true;
        state.startTime = Date.now();
        hideMessages();

        elements.startBtn.style.display = 'none';
        elements.stopBtn.style.display = 'inline-block';
        elements.stopBtn.disabled = false;
        elements.statusText.textContent = 'Scanning images...';
        elements.statusText.style.color = '#F57C00';

        try {
            const result = await scanImages();
            
            state.imageCount = result.count || 0;
            state.totalBatches = result.total_batches || 1;
            state.batchSize = result.batch_size || 15;
            state.currentBatch = 0;

            // Update UI with scan results
            elements.imageCountText.textContent = state.imageCount;
            elements.batchSizeText.textContent = state.batchSize;
            
            // Use API estimated time if available, otherwise calculate
            const estimatedMinutes = result.estimated_time_minutes || Math.ceil(state.totalBatches * 12 / 60);
            const estimatedSeconds = estimatedMinutes * 60;
            elements.estTimeText.textContent = formatTime(estimatedSeconds);

            // Show progress container
            elements.progressContainer.style.display = 'block';
            elements.progressBar.style.width = '0%';
            elements.progressText.textContent = 'Starting conversion...';

            // Reset images converted counter
            state.imagesConverted = 0;
            if (elements.imagesConvertedText) {
                elements.imagesConvertedText.textContent = '0';
            }

            if (state.imageCount === 0) {
                showSuccess('No images to convert');
                return;
            }

            // Show initial stats
            updateStatsDashboard({
                total: state.imageCount,
                converted: 0,
                orphaned: 0
            });

            // Start elapsed time ticker
            startElapsedTimer();

            // Begin async batch processing
            await processAllBatches();
        } catch (error) {
            showError('Error scanning images: ' + error.message);
        }
    }

    /**
     * Process all batches asynchronously with while loop
     */
    async function processAllBatches() {
        let totalSuccess = 0;
        let totalFailed = 0;
        let totalSkipped = 0;

        while (state.currentBatch < state.totalBatches && state.isRunning) {
            state.currentBatch++;

            try {
                const response = await fetch(config.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'coreboost_bulk_convert_batch',
                        batch: state.currentBatch,
                        _wpnonce: config.nonce,
                    }),
                });

                const data = await response.json();

                if (!data.success) {
                    // Log error but continue with next batch
                    console.error('Batch ' + state.currentBatch + ' failed:', data.data?.message);
                    totalFailed += state.batchSize;
                    continue;
                }

                // Accumulate batch results
                if (data.data.batch_results) {
                    totalSuccess += data.data.batch_results.success || 0;
                    totalFailed += data.data.batch_results.failed || 0;
                    totalSkipped += data.data.batch_results.skipped || 0;
                }

                // Update progress with batch results
                updateProgress({
                    currentBatch: state.currentBatch,
                    totalBatches: state.totalBatches,
                    batchResults: data.data.batch_results,
                });

                // Save progress to localStorage
                saveProgress();

            } catch (error) {
                // Log error but continue processing remaining batches
                console.error('Error processing batch ' + state.currentBatch + ':', error.message);
                totalFailed += state.batchSize;
            }
        }

        // Check if stopped by user
        if (!state.isRunning) {
            return;
        }

        // Clear progress from localStorage
        localStorage.removeItem('coreboost_bulk_progress');

        // Clear page cache (not image cache)
        await clearCacheAfterConversion();

        // Show completion with detailed results
        completeBulkConversion(totalSuccess, totalFailed, totalSkipped);
    }

    /**
     * Complete bulk conversion
     */
    function completeBulkConversion(totalSuccess, totalFailed, totalSkipped) {
        // Stop elapsed timer
        stopElapsedTimer();

        const elapsed = Date.now() - state.startTime;
        const elapsedSeconds = elapsed / 1000;

        elements.progressBar.style.width = '100%';
        elements.progressBar.textContent = '100%';
        elements.statusText.textContent = 'Complete';
        elements.statusText.style.color = '#4CAF50';
        
        // Show detailed results
        const resultsText = totalSuccess + ' converted, ' + totalFailed + ' failed, ' + totalSkipped + ' skipped';
        elements.progressText.textContent = 'Conversion complete: ' + resultsText;
        elements.timeElapsed.textContent = 'Total time: ' + formatTime(elapsedSeconds);
        elements.timeRemaining.textContent = '';

        // Final stats update
        updateStatsDashboard({
            total: state.imageCount,
            converted: totalSuccess,
            orphaned: 0
        });

        let message = 'Bulk conversion completed in ' + formatTime(elapsedSeconds) + '! ';
        message += resultsText + '. ';
        if (totalFailed > 0) {
            message += 'Check browser console for details on failed conversions.';
        }
        showSuccess(message);
        
        setTimeout(() => {
            resetUI();
            elements.progressContainer.style.display = 'none';
        }, 5000);
    }

    /**
     * Stop bulk conversion
     */
    function stopConversion() {
        state.isRunning = false;
        
        // Stop elapsed timer
        stopElapsedTimer();
        
        elements.statusText.textContent = 'Stopped';
        elements.statusText.style.color = '#F57C00';
        elements.stopBtn.disabled = true;

        // Set stop flag in backend
        fetch(config.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'coreboost_bulk_convert_stop',
                _wpnonce: config.nonce,
            }),
        });

        setTimeout(() => {
            resetUI();
        }, 2000);
    }

    /**
     * Load initial statistics on page load
     */
    async function loadInitialStats() {
        try {
            const response = await fetch(config.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'coreboost_scan_uploads',
                    _wpnonce: config.nonce,
                }),
            });
            
            const data = await response.json();
            
            if (data.success && data.data) {
                const stats = data.data;
                
                // Update initial image count
                if (elements.imageCountText && stats.count) {
                    elements.imageCountText.textContent = stats.count;
                }
                
                // Update stats dashboard with initial data
                updateStatsDashboard({
                    total: stats.count || 0,
                    converted: stats.converted || 0,
                    orphaned: 0
                });
            }
        } catch (error) {
            console.warn('Could not load initial statistics:', error);
        }
    }

    /**
     * Initialize event listeners
     */
    function init() {
        if (!elements.startBtn || !elements.stopBtn) {
            console.warn('CoreBoost bulk converter UI elements not found');
            return;
        }

        elements.startBtn.addEventListener('click', function(e) {
            e.preventDefault();
            startConversion();
        });

        elements.stopBtn.addEventListener('click', function(e) {
            e.preventDefault();
            stopConversion();
        });
        
        // Load initial statistics to show current landscape
        loadInitialStats();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
