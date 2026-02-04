jQuery(document).ready(function($) {
    // Initialize CoreBoost Admin UI
    initCoreboostUI();
    
    // Initialize card selector and tooltips
    initCardSelector();
    
    // Initialize migration notice dismiss
    initMigrationNotice();
    
    // Handle delay JS toggle enabling/disabling related fields
    initDelayJsToggle();
    
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
    
    // Handle hash navigation to auto-expand sections
    handleHashNavigation();
}

/**
 * Handle URL hash navigation to auto-expand sections
 */
function handleHashNavigation() {
    var $ = jQuery;
    var hash = window.location.hash;
    
    if (hash) {
        var sectionId = hash.replace('#', '');
        var $section = $('.coreboost-collapsible-section[data-section="' + sectionId + '"]');
        
        if ($section.length && $section.hasClass('collapsed')) {
            $section.removeClass('collapsed');
            var $content = $section.find('.coreboost-section-content');
            $content.css('display', 'block');
            
            // Scroll to section after a brief delay
            setTimeout(function() {
                $section[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }
}

/**
 * Initialize card selector functionality
 */
function initCardSelector() {
    var $ = jQuery;
    
    // Card click handler - select the card
    $(document).on('click', '.coreboost-method-card', function(e) {
        // Don't trigger if clicking the info button
        if ($(e.target).closest('.coreboost-info-trigger').length) {
            return;
        }
        
        var $card = $(this);
        var $selector = $card.closest('.coreboost-card-selector');
        var $radio = $card.find('.coreboost-card-radio');
        
        // Update selection
        $selector.find('.coreboost-method-card').removeClass('selected');
        $card.addClass('selected');
        $radio.prop('checked', true).trigger('change');
    });
    
    // Keyboard navigation for cards
    $(document).on('keydown', '.coreboost-card-radio', function(e) {
        var $radio = $(this);
        var $card = $radio.closest('.coreboost-method-card');
        var $selector = $card.closest('.coreboost-card-selector');
        var $cards = $selector.find('.coreboost-method-card');
        var currentIndex = $cards.index($card);
        var newIndex = -1;
        
        switch(e.keyCode) {
            case 37: // Left arrow
            case 38: // Up arrow
                newIndex = currentIndex > 0 ? currentIndex - 1 : $cards.length - 1;
                break;
            case 39: // Right arrow
            case 40: // Down arrow
                newIndex = currentIndex < $cards.length - 1 ? currentIndex + 1 : 0;
                break;
            default:
                return;
        }
        
        if (newIndex >= 0) {
            e.preventDefault();
            var $newCard = $cards.eq(newIndex);
            $newCard.find('.coreboost-card-radio').focus().trigger('click');
        }
    });
    
    // Initialize tooltips
    initTooltips();
}

/**
 * Initialize tooltip functionality
 */
function initTooltips() {
    var $ = jQuery;
    var activeTooltip = null;
    
    // Show tooltip on hover/focus
    $(document).on('mouseenter focus', '.coreboost-info-trigger', function(e) {
        e.stopPropagation();
        var $trigger = $(this);
        var $tooltip = $trigger.siblings('.coreboost-tooltip');
        
        showTooltip($trigger, $tooltip);
    });
    
    // Hide tooltip on mouseleave/blur
    $(document).on('mouseleave blur', '.coreboost-info-trigger', function(e) {
        var $trigger = $(this);
        var $tooltip = $trigger.siblings('.coreboost-tooltip');
        
        // Delay hide to allow moving mouse to tooltip
        setTimeout(function() {
            if (!$tooltip.is(':hover') && !$trigger.is(':hover') && !$trigger.is(':focus')) {
                hideTooltip($trigger, $tooltip);
            }
        }, 100);
    });
    
    // Keep tooltip visible when hovering over it
    $(document).on('mouseleave', '.coreboost-tooltip', function() {
        var $tooltip = $(this);
        var $trigger = $tooltip.siblings('.coreboost-info-trigger');
        
        if (!$trigger.is(':hover') && !$trigger.is(':focus')) {
            hideTooltip($trigger, $tooltip);
        }
    });
    
    // Toggle tooltip on click (for mobile/touch)
    $(document).on('click', '.coreboost-info-trigger', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $trigger = $(this);
        var $tooltip = $trigger.siblings('.coreboost-tooltip');
        
        if ($tooltip.hasClass('visible')) {
            hideTooltip($trigger, $tooltip);
        } else {
            // Hide any other visible tooltips
            $('.coreboost-tooltip.visible').each(function() {
                var $other = $(this);
                hideTooltip($other.siblings('.coreboost-info-trigger'), $other);
            });
            showTooltip($trigger, $tooltip);
        }
    });
    
    // Close tooltip on Escape key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) { // Escape
            $('.coreboost-tooltip.visible').each(function() {
                var $tooltip = $(this);
                hideTooltip($tooltip.siblings('.coreboost-info-trigger'), $tooltip);
            });
        }
    });
    
    // Close tooltip when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.coreboost-info-trigger, .coreboost-tooltip').length) {
            $('.coreboost-tooltip.visible').each(function() {
                var $tooltip = $(this);
                hideTooltip($tooltip.siblings('.coreboost-info-trigger'), $tooltip);
            });
        }
    });
    
    function showTooltip($trigger, $tooltip) {
        $trigger.attr('aria-expanded', 'true');
        $tooltip.attr('aria-hidden', 'false').addClass('visible');
    }
    
    function hideTooltip($trigger, $tooltip) {
        $trigger.attr('aria-expanded', 'false');
        $tooltip.attr('aria-hidden', 'true').removeClass('visible');
    }
}

/**
 * Initialize delay JS toggle functionality
 */
function initDelayJsToggle() {
    var $ = jQuery;
    
    // Handle enable_delay_js checkbox change
    $('#enable_delay_js').on('change', function() {
        var isEnabled = $(this).is(':checked');
        
        // Enable/disable all delay JS related fields
        $('#delay_js_trigger input[type="radio"]').prop('disabled', !isEnabled);
        $('#delay_js_timeout').prop('disabled', !isEnabled);
        $('#delay_js_include_inline').prop('disabled', !isEnabled);
        $('#delay_js_use_default_exclusions').prop('disabled', !isEnabled);
        $('#delay_js_exclusions').prop('disabled', !isEnabled);
        
        // Handle custom delay field (only enabled when trigger is custom AND delay JS is enabled)
        var isCustomTrigger = $('input[name="coreboost_options[delay_js_trigger]"]:checked').val() === 'custom_delay';
        $('#delay_js_custom_delay').prop('disabled', !isEnabled || !isCustomTrigger);
    });
    
    // Handle delay_js_trigger radio change to enable/disable custom delay field
    $('input[name="coreboost_options[delay_js_trigger]"]').on('change', function() {
        var isEnabled = $('#enable_delay_js').is(':checked');
        var isCustom = $(this).val() === 'custom_delay';
        $('#delay_js_custom_delay').prop('disabled', !isEnabled || !isCustom);
    });
}

/**
 * Initialize migration notice dismiss functionality
 */
function initMigrationNotice() {
    var $ = jQuery;
    
    $(document).on('click', '.coreboost-migration-notice .notice-dismiss', function() {
        var $notice = $(this).closest('.coreboost-migration-notice');
        var noticeId = $notice.data('notice-id');
        
        // Send AJAX request to dismiss permanently
        $.ajax({
            url: coreboost_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'coreboost_dismiss_notice',
                notice_id: noticeId,
                nonce: coreboost_ajax.nonce
            }
        });
    });
}
