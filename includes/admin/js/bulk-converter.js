/**
 * CoreBoost Bulk Image Converter
 * 
 * Handles scanning, processing, and progress tracking for bulk image conversion
 * to AVIF/WebP formats.
 *
 * @package CoreBoost
 * @since 2.7.0
 */

(function() {
    'use strict';

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
        currentBatch: 0,
        totalBatches: 0,
        startTime: null,
        batchSize: 0,
        pollTimer: null,
    };

    // DOM elements
    const elements = {
        startBtn: document.getElementById('coreboost-start-bulk'),
        stopBtn: document.getElementById('coreboost-stop-bulk'),
        statusText: document.getElementById('coreboost-bulk-status'),
        imageCountText: document.getElementById('coreboost-image-count'),
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
     * Update UI with current progress
     */
    function updateProgress(progress) {
        const percentage = Math.min(100, Math.round((progress.currentBatch / progress.totalBatches) * 100));
        const elapsed = Date.now() - state.startTime;
        const elapsedSeconds = elapsed / 1000;
        
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

        // Update progress text
        elements.progressText.textContent = 'Batch ' + progress.currentBatch + ' of ' + progress.totalBatches + ' (' + percentage + '%)';

        // Update time displays
        elements.timeElapsed.textContent = 'Elapsed: ' + formatTime(elapsedSeconds);
        elements.timeRemaining.textContent = 'Remaining: ' + formatTime(remainingSeconds);

        // Show progress container
        elements.progressContainer.style.display = 'block';
    }

    /**
     * Show error message
     */
    function showError(message) {
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
        
        elements.startBtn.style.display = 'inline-block';
        elements.startBtn.disabled = false;
        elements.stopBtn.style.display = 'none';
        elements.stopBtn.disabled = true;
        
        elements.statusText.textContent = 'Not started';
        elements.statusText.style.color = '#666';
        
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

    /**
     * Start bulk conversion process
     */
    function startConversion() {
        state.isRunning = true;
        state.startTime = Date.now();
        hideMessages();

        elements.startBtn.style.display = 'none';
        elements.stopBtn.style.display = 'inline-block';
        elements.stopBtn.disabled = false;
        elements.statusText.textContent = 'Scanning images...';
        elements.statusText.style.color = '#F57C00';

        scanImages()
            .then(result => {
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

                if (state.imageCount === 0) {
                    showSuccess('No images to convert');
                    return;
                }

                // Begin batch processing
                processBatch();
            })
            .catch(error => {
                showError('Error scanning images: ' + error.message);
            });
    }

    /**
     * Process a single batch
     */
    function processBatch() {
        if (!state.isRunning) {
            return;
        }

        state.currentBatch++;

        fetch(config.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'coreboost_bulk_convert_batch',
                batch: state.currentBatch,
                _wpnonce: config.nonce,
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.data?.message || 'Batch processing failed');
            }

            // Update progress
            updateProgress({
                currentBatch: state.currentBatch,
                totalBatches: state.totalBatches,
            });

            // Check if we're done
            if (state.currentBatch >= state.totalBatches) {
                completeBulkConversion();
            } else {
                // Continue with next batch
                processBatch();
            }
        })
        .catch(error => {
            showError('Error processing batch: ' + error.message);
        });
    }

    /**
     * Complete bulk conversion
     */
    function completeBulkConversion() {
        const elapsed = Date.now() - state.startTime;
        const elapsedSeconds = elapsed / 1000;

        elements.progressBar.style.width = '100%';
        elements.progressBar.textContent = '100%';
        elements.statusText.textContent = 'Complete';
        elements.statusText.style.color = '#4CAF50';
        elements.progressText.textContent = 'Successfully converted all ' + state.imageCount + ' images';
        elements.timeElapsed.textContent = 'Total time: ' + formatTime(elapsedSeconds);
        elements.timeRemaining.textContent = '';

        showSuccess('Bulk conversion completed! ' + state.imageCount + ' images converted in ' + formatTime(elapsedSeconds));
        
        setTimeout(() => {
            resetUI();
            elements.progressContainer.style.display = 'none';
        }, 3000);
    }

    /**
     * Stop bulk conversion
     */
    function stopConversion() {
        state.isRunning = false;
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
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
