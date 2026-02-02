jQuery(document).ready(function($) {
    // Initialize CoreBoost Admin UI
    initCoreboostUI();
    
    // Handle admin bar cache clearing
    $('.coreboost-clear-cache-link').on('click', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var originalText = $link.text();
        
        // Update link text to show clearing status
        $link.text(coreboost_ajax.clearing_text);
        
        // Perform AJAX request with timeout
        $.ajax({
            url: coreboost_ajax.ajax_url,
            type: 'POST',
            timeout: 30000, // 30 second timeout
            data: {
                action: 'coreboost_clear_cache',
                nonce: coreboost_ajax.nonce
            },
            success: function(response) {
                if (response && response.success) {
                    $link.text(coreboost_ajax.cleared_text);
                    
                    // Show success message if we're on the admin page
                    if ($('.wrap').length) {
                        showNotice('success', response.data.message || coreboost_ajax.cleared_text);
                    }
                    
                    // Reset text after 2 seconds
                    setTimeout(function() {
                        $link.text(originalText);
                    }, 2000);
                } else {
                    var errorMsg = (response && response.data && response.data.message) 
                        ? response.data.message 
                        : coreboost_ajax.error_text;
                    $link.text(coreboost_ajax.error_text);
                    
                    // Show error message if we're on the admin page
                    if ($('.wrap').length) {
                        showNotice('error', errorMsg);
                    }
                    
                    setTimeout(function() {
                        $link.text(originalText);
                    }, 2000);
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = coreboost_ajax.error_text;
                
                if (status === 'timeout') {
                    errorMessage = 'Cache clear request timed out. Please try again.';
                    console.warn('CoreBoost: Cache clear request timed out after 30 seconds');
                    if (window.CoreBoostErrorLogger) {
                        window.CoreBoostErrorLogger.logError('ajax', 'clearCache', new Error('Request timeout'), {
                            timeout: 30000,
                            status: status
                        });
                    }
                } else {
                    errorMessage = 'Failed to clear cache. Please try again or report the issue.';
                    console.warn('CoreBoost: Cache clear failed:', error);
                    if (window.CoreBoostErrorLogger) {
                        window.CoreBoostErrorLogger.logError('ajax', 'clearCache', error, {
                            status: status,
                            statusCode: xhr.status
                        });
                    }
                }
                
                $link.text(coreboost_ajax.error_text);
                
                // Show error message if we're on the admin page
                if ($('.wrap').length) {
                    showNotice('error', errorMessage);
                }
                
                setTimeout(function() {
                    $link.text(originalText);
                }, 2000);
            }
        });
    });
    
    // GTM detection cache clearing
    window.coreboostClearGTMCache = function() {
        if (confirm('Re-scan the site for existing GTM implementations? This will clear the detection cache.')) {
            // Add a query parameter to reload the page and clear cache
            var url = new URL(window.location.href);
            url.searchParams.set('coreboost_clear_gtm_cache', '1');
            window.location.href = url.toString();
        }
    };
    
    // Add visual feedback for admin bar menu
    $('#wp-admin-bar-coreboost').hover(
        function() {
            $(this).addClass('hover');
        },
        function() {
            $(this).removeClass('hover');
        }
    );
    
    // Collapsible sections functionality
    initCollapsibleSections();
});

/**
 * Initialize CoreBoost UI enhancements
 */
function initCoreboostUI() {
    var $ = jQuery;
    
    // Add entrance animations to cards
    $('.coreboost-card-stat, .coreboost-status-item, .coreboost-db-stat').each(function(index) {
        var $el = $(this);
        $el.css({
            'opacity': '0',
            'transform': 'translateY(20px)'
        });
        
        setTimeout(function() {
            $el.css({
                'transition': 'opacity 0.4s ease, transform 0.4s ease',
                'opacity': '1',
                'transform': 'translateY(0)'
            });
        }, index * 100);
    });
    
    // Animate numbers on stat cards
    $('.coreboost-card-value, .coreboost-db-stat-value').each(function() {
        var $el = $(this);
        var text = $el.text();
        var number = parseInt(text.replace(/[^0-9]/g, ''));
        
        if (!isNaN(number) && number > 0 && number < 10000) {
            $el.text('0');
            animateValue($el[0], 0, number, 1000, text);
        }
    });
    
    // Button hover effects
    $('.coreboost-quick-actions .button, .coreboost-btn').on('mouseenter', function() {
        $(this).css('transform', 'translateY(-2px)');
    }).on('mouseleave', function() {
        $(this).css('transform', 'translateY(0)');
    });
    
    // Tab click animation
    $('.nav-tab').on('click', function(e) {
        var $tab = $(this);
        
        // Add click ripple effect
        $tab.css('transform', 'scale(0.98)');
        setTimeout(function() {
            $tab.css('transform', '');
        }, 150);
    });
    
    // Status item hover effect
    $('.coreboost-status-item').on('mouseenter', function() {
        $(this).css('transform', 'translateX(4px)');
    }).on('mouseleave', function() {
        $(this).css('transform', 'translateX(0)');
    });
}

/**
 * Animate a numeric value
 */
function animateValue(el, start, end, duration, originalText) {
    var startTimestamp = null;
    var suffix = originalText.replace(/[0-9,]/g, '').trim();
    
    var step = function(timestamp) {
        if (!startTimestamp) startTimestamp = timestamp;
        var progress = Math.min((timestamp - startTimestamp) / duration, 1);
        var current = Math.floor(progress * (end - start) + start);
        el.textContent = current.toLocaleString() + (suffix ? ' ' + suffix : '');
        
        if (progress < 1) {
            window.requestAnimationFrame(step);
        } else {
            el.textContent = originalText;
        }
    };
    
    window.requestAnimationFrame(step);
}

/**
 * Show a notice message
 */
function showNotice(type, message) {
    var $ = jQuery;
    var $notice = $('<div class="notice notice-' + type + ' is-dismissible coreboost-notice-animate">' +
                    '<p>' + message + '</p>' +
                    '<button type="button" class="notice-dismiss"></button>' +
                    '</div>');
    
    // Add entrance animation styles
    $notice.css({
        'opacity': '0',
        'transform': 'translateY(-10px)',
        'transition': 'opacity 0.3s ease, transform 0.3s ease'
    });
    
    $('.wrap').prepend($notice);
    
    // Trigger animation
    setTimeout(function() {
        $notice.css({
            'opacity': '1',
            'transform': 'translateY(0)'
        });
    }, 10);
    
    // Handle dismiss button
    $notice.find('.notice-dismiss').on('click', function() {
        $notice.css({
            'opacity': '0',
            'transform': 'translateY(-10px)'
        });
        setTimeout(function() {
            $notice.remove();
        }, 300);
    });
}

/**
 * Initialize collapsible sections
 */
function initCollapsibleSections() {
    var $ = jQuery;
    
    // Get saved section states from localStorage
    var savedStates = localStorage.getItem('coreboost_section_states');
    var sectionStates = savedStates ? JSON.parse(savedStates) : {};
    
    // Apply saved states
    $('.coreboost-collapsible-section').each(function() {
        var $section = $(this);
        var sectionId = $section.data('section');
        
        if (sectionId && sectionStates[sectionId] === 'collapsed') {
            $section.addClass('collapsed');
        }
    });
    
    // Handle section header clicks with smooth animation
    $('.coreboost-section-header').on('click', function(e) {
        var $header = $(this);
        var $section = $header.closest('.coreboost-collapsible-section');
        var $content = $section.find('.coreboost-section-content');
        var sectionId = $section.data('section');
        
        // Toggle collapsed state with animation
        if ($section.hasClass('collapsed')) {
            // Expanding
            $section.removeClass('collapsed');
            $content.css({
                'display': 'block',
                'opacity': '0',
                'transform': 'translateY(-10px)'
            });
            setTimeout(function() {
                $content.css({
                    'transition': 'opacity 0.3s ease, transform 0.3s ease',
                    'opacity': '1',
                    'transform': 'translateY(0)'
                });
            }, 10);
        } else {
            // Collapsing
            $content.css({
                'transition': 'opacity 0.2s ease, transform 0.2s ease',
                'opacity': '0',
                'transform': 'translateY(-10px)'
            });
            setTimeout(function() {
                $section.addClass('collapsed');
                $content.css({
                    'display': 'none',
                    'transition': ''
                });
            }, 200);
        }
        
        // Save state to localStorage
        if (sectionId) {
            sectionStates[sectionId] = $section.hasClass('collapsed') ? 'collapsed' : 'expanded';
            localStorage.setItem('coreboost_section_states', JSON.stringify(sectionStates));
        }
    });
    
    // Expand all functionality
    $(document).on('click', '.coreboost-expand-all', function(e) {
        e.preventDefault();
        $('.coreboost-collapsible-section').each(function(index) {
            var $section = $(this);
            var $content = $section.find('.coreboost-section-content');
            var sectionId = $section.data('section');
            
            setTimeout(function() {
                $section.removeClass('collapsed');
                $content.css({
                    'display': 'block',
                    'opacity': '0'
                });
                setTimeout(function() {
                    $content.css({
                        'transition': 'opacity 0.3s ease',
                        'opacity': '1'
                    });
                }, 10);
                
                if (sectionId) {
                    sectionStates[sectionId] = 'expanded';
                }
            }, index * 50);
        });
        localStorage.setItem('coreboost_section_states', JSON.stringify(sectionStates));
    });
    
    // Collapse all functionality
    $(document).on('click', '.coreboost-collapse-all', function(e) {
        e.preventDefault();
        $('.coreboost-collapsible-section').each(function(index) {
            var $section = $(this);
            var sectionId = $section.data('section');
            
            setTimeout(function() {
                $section.addClass('collapsed');
                
                if (sectionId) {
                    sectionStates[sectionId] = 'collapsed';
                }
            }, index * 50);
        });
        localStorage.setItem('coreboost_section_states', JSON.stringify(sectionStates));
    });
}
