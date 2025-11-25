jQuery(document).ready(function($) {
    // Handle admin bar cache clearing
    $('.npg-so-clear-cache-link').on('click', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var originalText = $link.text();
        
        // Update link text to show clearing status
        $link.text(npg_so_ajax.clearing_text);
        
        // Perform AJAX request
        $.ajax({
            url: npg_so_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'npg_so_clear_cache',
                nonce: npg_so_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $link.text(npg_so_ajax.cleared_text);
                    
                    // Show success message if we're on the admin page
                    if ($('.wrap').length) {
                        $('.wrap').prepend('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                    }
                    
                    // Reset text after 2 seconds
                    setTimeout(function() {
                        $link.text(originalText);
                    }, 2000);
                } else {
                    $link.text(npg_so_ajax.error_text);
                    setTimeout(function() {
                        $link.text(originalText);
                    }, 2000);
                }
            },
            error: function() {
                $link.text(npg_so_ajax.error_text);
                setTimeout(function() {
                    $link.text(originalText);
                }, 2000);
            }
        });
    });
    
    // Add visual feedback for admin bar menu
    $('#wp-admin-bar-npg-site-optimizer').hover(
        function() {
            $(this).addClass('hover');
        },
        function() {
            $(this).removeClass('hover');
        }
    );
});
