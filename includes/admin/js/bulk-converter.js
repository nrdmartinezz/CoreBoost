/**
 * CoreBoost Bulk Image Converter
 * 
 * Handles scanning, processing, and progress tracking for bulk image conversion
 * to AVIF/WebP formats with live counters and circular progress indicators.
 *
 * @package CoreBoost
 * @since 2.7.0
 * @updated 3.1.0 - Refactored to use state machine pattern with CSS classes
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

    // State machine states
    const STATES = {
        IDLE: 'idle',
        SCANNING: 'scanning',
        PROCESSING: 'processing',
        COMPLETE: 'complete',
        ERROR: 'error',
        STOPPED: 'stopped'
    };

    // Configuration
    const config = {
        pollInterval: 3000, // 3 seconds
        ajaxurl: (typeof coreBoostAdmin !== 'undefined' ? coreBoostAdmin.ajaxurl : window.ajaxurl) || '/wp-admin/admin-ajax.php',
        nonce: (typeof coreBoostAdmin !== 'undefined' ? coreBoostAdmin.nonce : document.querySelector('input[name="coreboost_nonce"]')?.value) || '',
    };

    // State - using state machine pattern
    let state = {
        current: STATES.IDLE,
        imageCount: 0,
        imagesConverted: 0,
        currentBatch: 0,
        totalBatches: 0,
        startTime: null,
        batchSize: 0,
        pollTimer: null,
        elapsedTimer: null,
    };

    /**
     * Transition to a new state and update UI accordingly
     * @param {string} newState - One of STATES values
     */
    function transitionTo(newState) {
        const container = document.getElementById('coreboost-bulk-converter');
        if (!container) return;

        // Remove all state classes
        Object.values(STATES).forEach(s => container.classList.remove('is-' + s));
        
        // Add new state class
        container.classList.add('is-' + newState);
        state.current = newState;
        
        // Update status text based on state
        const statusMap = {
            [STATES.IDLE]: { text: 'Not started', color: 'idle' },
            [STATES.SCANNING]: { text: 'Scanning images...', color: 'processing' },
            [STATES.PROCESSING]: { text: 'Processing...', color: 'processing' },
            [STATES.COMPLETE]: { text: 'Complete', color: 'complete' },
            [STATES.ERROR]: { text: 'Error', color: 'error' },
            [STATES.STOPPED]: { text: 'Stopped', color: 'processing' }
        };

        const status = statusMap[newState];
        if (status && elements.statusText) {
            elements.statusText.textContent = status.text;
            elements.statusText.className = 'coreboost-status coreboost-status--' + status.color;
        }

        console.log('CoreBoost: State transition to', newState);
    }

    /**
     * Check if currently in a running state
     */
    function isRunning() {
        return state.current === STATES.SCANNING || state.current === STATES.PROCESSING;
    }

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
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout

            const response = await fetch(config.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'coreboost_clear_cache',
                    nonce: config.nonce,
                }),
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }

            const data = await response.json();
            if (data.success) {
                console.log('Cache cleared successfully after bulk conversion');
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                console.warn('Cache clear timed out after 30 seconds');
                if (window.CoreBoostErrorLogger) {
                    window.CoreBoostErrorLogger.logError('cache', 'clearCacheAfterConversion', error, { timeout: 30000 });
                }
            } else {
                console.warn('Could not clear cache:', error);
                if (window.CoreBoostErrorLogger) {
                    window.CoreBoostErrorLogger.logError('cache', 'clearCacheAfterConversion', error);
                }
            }
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

        // Ensure we're in processing state
        if (state.current !== STATES.PROCESSING) {
            transitionTo(STATES.PROCESSING);
        }

        // Update progress text with batch results if available
        let progressText = 'Batch ' + progress.currentBatch + ' of ' + progress.totalBatches + ' (' + percentage + '%)';
        if (progress.batchResults) {
            const results = progress.batchResults;
            progressText += ' - ' + results.success + ' success, ' + results.failed + ' failed, ' + results.skipped + ' skipped';
        }
        elements.progressText.textContent = progressText;

        // Update remaining time (elapsed time updates via interval)
        elements.timeRemaining.textContent = 'Remaining: ' + formatTime(remainingSeconds);
        
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
        
        transitionTo(STATES.ERROR);
        elements.errorText.textContent = message;
        resetUI();
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        elements.successText.textContent = message;
        transitionTo(STATES.COMPLETE);
        resetUI();
    }

    /**
     * Hide messages - handled by CSS state classes now
     */
    function hideMessages() {
        // Messages visibility controlled by parent state class
    }

    /**
     * Reset UI to initial state
     */
    function resetUI() {
        // Stop elapsed timer
        stopElapsedTimer();
        
        // Reset state values
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
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 60000); // 1 minute timeout

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
            signal: controller.signal
        })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.data?.message || 'Failed to scan images');
            }
            return data.data;
        })
        .catch(error => {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                const timeoutError = new Error('Scan timed out after 1 minute');
                if (window.CoreBoostErrorLogger) {
                    window.CoreBoostErrorLogger.logError('conversion', 'scanImages', timeoutError, { timeout: 60000 });
                }
                throw timeoutError;
            }
            if (window.CoreBoostErrorLogger) {
                window.CoreBoostErrorLogger.logError('conversion', 'scanImages', error, { operation: 'fetch' });
            }
            throw error;
        });
    }

    async function startConversion() {
        // Check if format conversion is enabled
        const formatEnabled = elements.startBtn.getAttribute('data-format-enabled') === '1';
        if (!formatEnabled) {
            showError('Image Format Conversion is disabled. Please enable "Generate AVIF/WebP Variants" in the settings below and save before using the bulk converter.');
            return;
        }

        state.startTime = Date.now();
        hideMessages();
        transitionTo(STATES.SCANNING);

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

            // Reset progress bar
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

            // Transition to processing and start
            transitionTo(STATES.PROCESSING);
            
            // Start elapsed time ticker
            startElapsedTimer();

            // Begin async batch processing
            await processAllBatches();
        } catch (error) {
            if (window.CoreBoostErrorLogger) {
                window.CoreBoostErrorLogger.logError('conversion', 'startConversion', error, {
                    operation: 'initial scan'
                });
            }
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

        while (state.currentBatch < state.totalBatches && isRunning()) {
            state.currentBatch++;

            try {
                // Create AbortController with timeout to prevent hanging requests
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 120000); // 2 minute timeout

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
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }

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
                if (error.name === 'AbortError') {
                    console.error('Batch ' + state.currentBatch + ' timed out after 2 minutes');
                    if (window.CoreBoostErrorLogger) {
                        window.CoreBoostErrorLogger.logError('conversion', 'processBatch', error, {
                            batch: state.currentBatch,
                            totalBatches: state.totalBatches,
                            timeout: 120000
                        });
                    }
                } else {
                    console.error('Error processing batch ' + state.currentBatch + ':', error.message);
                    if (window.CoreBoostErrorLogger) {
                        window.CoreBoostErrorLogger.logError('conversion', 'processBatch', error, {
                            batch: state.currentBatch,
                            totalBatches: state.totalBatches
                        });
                    }
                }
                totalFailed += state.batchSize;
                
                // Add delay after error to prevent rapid-fire failures
                await new Promise(resolve => setTimeout(resolve, 2000));
            }
            
            // Add a small delay between batches to give server time to recover memory
            await new Promise(resolve => setTimeout(resolve, 1000));
        }

        // Check if stopped by user
        if (!isRunning()) {
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
        
        transitionTo(STATES.COMPLETE);
        
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
        elements.successText.textContent = message;
        
        setTimeout(() => {
            transitionTo(STATES.IDLE);
        }, 5000);
    }

    /**
     * Stop bulk conversion
     */
    function stopConversion() {
        transitionTo(STATES.STOPPED);
        
        // Stop elapsed timer
        stopElapsedTimer();

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
            transitionTo(STATES.IDLE);
        }, 2000);
    }

    /**
     * Load initial statistics on page load
     */
    async function loadInitialStats() {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout

            const response = await fetch(config.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'coreboost_scan_uploads',
                    _wpnonce: config.nonce,
                }),
                signal: controller.signal
            });

            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }

            const data = await response.json();
            
            console.log('CoreBoost: loadInitialStats received data:', data);
            
            if (data.success && data.data) {
                const stats = data.data;
                
                console.log('CoreBoost: Stats from server:', stats);
                console.log('CoreBoost: Converted count:', stats.converted);
                
                // Update initial image count
                if (elements.imageCountText && stats.count) {
                    elements.imageCountText.textContent = stats.count;
                }
                
                // Update the images converted counter with actual count from scan
                if (elements.imagesConvertedText && stats.converted !== undefined) {
                    console.log('CoreBoost: Setting imagesConvertedText to:', stats.converted);
                    elements.imagesConvertedText.textContent = stats.converted;
                    state.imagesConverted = stats.converted;
                }
                
                // Update stats dashboard with initial data
                updateStatsDashboard({
                    total: stats.count || 0,
                    converted: stats.converted || 0,
                    orphaned: 0
                });
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                console.warn('Loading initial statistics timed out after 30 seconds');
                if (window.CoreBoostErrorLogger) {
                    window.CoreBoostErrorLogger.logError('stats', 'loadInitialStats', error, { timeout: 30000 });
                }
            } else {
                console.warn('Could not load initial statistics:', error);
                if (window.CoreBoostErrorLogger) {
                    window.CoreBoostErrorLogger.logError('stats', 'loadInitialStats', error);
                }
            }
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
