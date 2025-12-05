/**
 * CoreBoost Error Logger
 * 
 * Centralized error logging for async operations to help identify
 * message channel closure issues and other async errors
 *
 * @package CoreBoost
 * @since 2.7.1
 */

(function() {
    'use strict';

    // Only enable in debug mode or if explicitly enabled
    const debugMode = window.coreBoostDebug || false;

    // Error tracking
    const errorLog = [];
    const MAX_LOG_SIZE = 100;

    /**
     * CoreBoost Error Logger
     */
    window.CoreBoostErrorLogger = {
        
        /**
         * Log an error with context
         * 
         * @param {string} category - Error category (e.g., 'fetch', 'ajax', 'gtm', 'conversion')
         * @param {string} operation - Specific operation that failed
         * @param {Error|string} error - The error object or message
         * @param {Object} context - Additional context information
         */
        logError: function(category, operation, error, context) {
            const errorEntry = {
                timestamp: new Date().toISOString(),
                category: category,
                operation: operation,
                message: error instanceof Error ? error.message : String(error),
                errorName: error instanceof Error ? error.name : 'Error',
                stack: error instanceof Error ? error.stack : null,
                context: context || {},
                url: window.location.href
            };

            // Add to log
            errorLog.push(errorEntry);
            
            // Trim log if too large
            if (errorLog.length > MAX_LOG_SIZE) {
                errorLog.shift();
            }

            // Console output in debug mode
            if (debugMode) {
                console.group('CoreBoost Error [' + category + ']');
                console.error('Operation:', operation);
                console.error('Message:', errorEntry.message);
                if (errorEntry.errorName) {
                    console.error('Type:', errorEntry.errorName);
                }
                if (context && Object.keys(context).length > 0) {
                    console.error('Context:', context);
                }
                if (errorEntry.stack) {
                    console.error('Stack:', errorEntry.stack);
                }
                console.groupEnd();
            }

            // Send to server if critical
            if (this.isCriticalError(error)) {
                this.reportToServer(errorEntry);
            }
        },

        /**
         * Check if error is critical
         * 
         * @param {Error|string} error
         * @return {boolean}
         */
        isCriticalError: function(error) {
            const criticalPatterns = [
                'message channel closed',
                'listener indicated an asynchronous response',
                'AbortError',
                'timeout',
                'Network request failed'
            ];

            const errorStr = error instanceof Error ? error.message : String(error);
            return criticalPatterns.some(pattern => errorStr.toLowerCase().includes(pattern.toLowerCase()));
        },

        /**
         * Report error to server
         * 
         * @param {Object} errorEntry
         */
        reportToServer: function(errorEntry) {
            // Only report if ajax endpoint is available
            if (!window.coreboost_ajax && !window.coreBoostAdmin) {
                return;
            }

            const ajaxUrl = window.coreboost_ajax?.ajax_url || window.coreBoostAdmin?.ajaxurl || '/wp-admin/admin-ajax.php';
            const nonce = window.coreboost_ajax?.nonce || window.coreBoostAdmin?.nonce || '';

            // Use sendBeacon if available for reliability
            if (navigator.sendBeacon) {
                const formData = new FormData();
                formData.append('action', 'coreboost_log_error');
                formData.append('nonce', nonce);
                formData.append('error_data', JSON.stringify(errorEntry));
                
                navigator.sendBeacon(ajaxUrl, formData);
            } else {
                // Fallback to fetch with no-await to avoid blocking
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'coreboost_log_error',
                        nonce: nonce,
                        error_data: JSON.stringify(errorEntry)
                    }),
                    keepalive: true
                }).catch(function() {
                    // Silently fail - don't create more errors
                });
            }
        },

        /**
         * Get error log
         * 
         * @return {Array}
         */
        getErrorLog: function() {
            return errorLog.slice();
        },

        /**
         * Clear error log
         */
        clearErrorLog: function() {
            errorLog.length = 0;
        },

        /**
         * Export error log as JSON
         * 
         * @return {string}
         */
        exportLog: function() {
            return JSON.stringify(errorLog, null, 2);
        },

        /**
         * Download error log as file
         */
        downloadLog: function() {
            const logData = this.exportLog();
            const blob = new Blob([logData], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = 'coreboost-error-log-' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    };

    // Global error handler for uncaught promise rejections
    window.addEventListener('unhandledrejection', function(event) {
        // Check if it's a CoreBoost-related error
        const error = event.reason;
        const stack = error instanceof Error ? error.stack : '';
        
        if (stack.includes('coreboost') || stack.includes('CoreBoost')) {
            window.CoreBoostErrorLogger.logError(
                'unhandled-promise',
                'Promise Rejection',
                error,
                {
                    promise: event.promise,
                    source: 'global handler'
                }
            );
        }
    });

    // Expose to console for debugging
    if (debugMode) {
        console.log('CoreBoost Error Logger initialized. Access via window.CoreBoostErrorLogger');
        console.log('Commands: .getErrorLog(), .clearErrorLog(), .exportLog(), .downloadLog()');
    }

})();
