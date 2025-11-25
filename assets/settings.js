jQuery(document).ready(function($) {
    // Tab switching functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).attr('href').split('tab=')[1];
        var currentUrl = window.location.href.split('&tab=')[0].split('#')[0];
        
        // Add current tab parameter
        var newUrl = currentUrl + '&tab=' + targetTab;
        
        window.location.href = newUrl;
    });
    
    // Add current tab as hidden field to preserve tab state after form submission
    var currentTab = new URLSearchParams(window.location.search).get('tab') || 'hero';
    $('form').append('<input type="hidden" name="current_tab" value="' + currentTab + '">');
    
    // Redirect to same tab after form submission
    $('form').on('submit', function() {
        var form = $(this);
        var action = form.attr('action');
        var currentTab = $('input[name="current_tab"]').val();
        
        // Add tab parameter to form action
        if (action.indexOf('?') === -1) {
            form.attr('action', action + '?tab=' + currentTab);
        } else {
            form.attr('action', action + '&tab=' + currentTab);
        }
    });
    
    // Auto-save draft functionality for critical CSS
    var criticalCssFields = [
        'textarea[name="npg_so_options[critical_css_global]"]',
        'textarea[name="npg_so_options[critical_css_home]"]',
        'textarea[name="npg_so_options[critical_css_pages]"]',
        'textarea[name="npg_so_options[critical_css_posts]"]'
    ];
    
    criticalCssFields.forEach(function(selector) {
        $(selector).on('input', function() {
            var $field = $(this);
            var fieldName = $field.attr('name');
            var value = $field.val();
            
            // Save to localStorage as draft
            localStorage.setItem('npg_so_draft_' + fieldName, value);
            
            // Show draft indicator
            if (!$field.siblings('.draft-indicator').length) {
                $field.after('<span class="draft-indicator" style="color: #666; font-size: 12px; margin-left: 10px;">Draft saved</span>');
            }
            
            // Remove draft indicator after 3 seconds
            setTimeout(function() {
                $field.siblings('.draft-indicator').fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        });
    });
    
    // Restore drafts on page load
    criticalCssFields.forEach(function(selector) {
        var $field = $(selector);
        var fieldName = $field.attr('name');
        var draft = localStorage.getItem('npg_so_draft_' + fieldName);
        
        if (draft && draft !== $field.val()) {
            $field.after('<div class="draft-restore" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">' +
                '<strong>Draft found:</strong> You have unsaved changes. ' +
                '<button type="button" class="button button-small restore-draft">Restore Draft</button> ' +
                '<button type="button" class="button button-small dismiss-draft">Dismiss</button>' +
                '</div>');
        }
    });
    
    // Handle draft restoration
    $(document).on('click', '.restore-draft', function() {
        var $button = $(this);
        var $field = $button.closest('.draft-restore').prev('textarea');
        var fieldName = $field.attr('name');
        var draft = localStorage.getItem('npg_so_draft_' + fieldName);
        
        if (draft) {
            $field.val(draft);
            $button.closest('.draft-restore').fadeOut(function() {
                $(this).remove();
            });
        }
    });
    
    // Handle draft dismissal
    $(document).on('click', '.dismiss-draft', function() {
        var $button = $(this);
        var $field = $button.closest('.draft-restore').prev('textarea');
        var fieldName = $field.attr('name');
        
        localStorage.removeItem('npg_so_draft_' + fieldName);
        $button.closest('.draft-restore').fadeOut(function() {
            $(this).remove();
        });
    });
    
    // Clear drafts on successful form submission
    $('form').on('submit', function() {
        criticalCssFields.forEach(function(selector) {
            var $field = $(selector);
            var fieldName = $field.attr('name');
            localStorage.removeItem('npg_so_draft_' + fieldName);
        });
    });
    
    // Add character count for critical CSS fields
    criticalCssFields.forEach(function(selector) {
        var $field = $(selector);
        var $counter = $('<div class="char-counter" style="text-align: right; font-size: 12px; color: #666; margin-top: 5px;"></div>');
        $field.after($counter);
        
        function updateCounter() {
            var length = $field.val().length;
            var color = length > 14000 ? '#d63638' : (length > 10000 ? '#dba617' : '#666');
            $counter.html('<span style="color: ' + color + ';">' + length + ' characters</span> (recommended: under 14KB)');
        }
        
        $field.on('input', updateCounter);
        updateCounter();
    });
    
    // Add syntax highlighting hint for CSS fields
    criticalCssFields.forEach(function(selector) {
        var $field = $(selector);
        $field.attr('spellcheck', 'false');
        $field.css({
            'font-family': 'Consolas, Monaco, "Courier New", monospace',
            'font-size': '13px',
            'line-height': '1.4'
        });
    });
    
    // Add quick actions for common script handles
    var commonScripts = [
        'contact-form-7',
        'wc-cart-fragments',
        'elementor-frontend',
        'woocommerce-general'
    ];
    
    var $scriptsField = $('textarea[name="npg_so_options[scripts_to_defer]"]');
    if ($scriptsField.length) {
        var $quickActions = $('<div class="quick-actions" style="margin-top: 10px;"></div>');
        $quickActions.append('<strong>Quick Add:</strong> ');
        
        commonScripts.forEach(function(script) {
            var $button = $('<button type="button" class="button button-small" style="margin: 2px;">' + script + '</button>');
            $button.on('click', function() {
                var currentValue = $scriptsField.val();
                var scripts = currentValue.split('\n').filter(function(s) { return s.trim(); });
                
                if (scripts.indexOf(script) === -1) {
                    scripts.push(script);
                    $scriptsField.val(scripts.join('\n'));
                }
            });
            $quickActions.append($button);
        });
        
        $scriptsField.after($quickActions);
    }
    
    // Add validation for page-specific images format
    var $specificPagesField = $('textarea[name="npg_so_options[specific_pages]"]');
    if ($specificPagesField.length) {
        $specificPagesField.on('blur', function() {
            var value = $(this).val();
            var lines = value.split('\n');
            var hasError = false;
            
            lines.forEach(function(line) {
                line = line.trim();
                if (line && line.indexOf('|') === -1) {
                    hasError = true;
                }
            });
            
            if (hasError) {
                if (!$(this).siblings('.format-error').length) {
                    $(this).after('<div class="format-error" style="color: #d63638; font-size: 12px; margin-top: 5px;">Format should be: page_slug|image_url</div>');
                }
            } else {
                $(this).siblings('.format-error').remove();
            }
        });
    }
});
