jQuery(document).ready(function($) {
    // Handle admin bar cache clearing
    $('.coreboost-clear-cache-link').on('click', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var originalText = $link.text();
        
        // Update link text to show clearing status
        $link.text(coreboost_ajax.clearing_text);
        
        // Perform AJAX request
        $.ajax({
            url: coreboost_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'coreboost_clear_cache',
                nonce: coreboost_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $link.text(coreboost_ajax.cleared_text);
                    
                    // Show success message if we're on the admin page
                    if ($('.wrap').length) {
                        $('.wrap').prepend('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                    }
                    
                    // Reset text after 2 seconds
                    setTimeout(function() {
                        $link.text(originalText);
                    }, 2000);
                } else {
                    $link.text(coreboost_ajax.error_text);
                    setTimeout(function() {
                        $link.text(originalText);
                    }, 2000);
                }
            },
            error: function() {
                $link.text(coreboost_ajax.error_text);
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
});
