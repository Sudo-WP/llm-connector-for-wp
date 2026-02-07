jQuery(document).ready(function($) {
    'use strict';

    // Reveal/Hide API key toggle
    $('.reveal-api-key').on('click', function(e) {
        e.preventDefault();
        
        var keyId = $(this).data('key-id');
        var $button = $(this);
        var $container = $button.closest('.api-key-container');
        var $display = $container.find('.api-key-display[data-key-id="' + keyId + '"]');
        var $hidden = $display.find('.api-key-hidden');
        var $full = $display.find('.api-key-full');
        
        if ($button.hasClass('revealed')) {
            // Hide the key
            $full.hide();
            $hidden.show();
            $button.removeClass('revealed');
            $button.html('<span class="dashicons dashicons-visibility"></span> Reveal');
            $button.attr('title', 'Click to reveal full API key');
        } else {
            // Reveal the key
            $hidden.hide();
            $full.show().addClass('revealed');
            $button.addClass('revealed');
            $button.html('<span class="dashicons dashicons-hidden"></span> Hide');
            $button.attr('title', 'Click to hide API key');
        }
    });

    // Copy API key to clipboard (modern approach)
    $('.copy-api-key').on('click', function(e) {
        e.preventDefault();
        
        var apiKey = $(this).data('key');
        var $button = $(this);
        var $icon = $button.find('.dashicons');
        
        // Use modern Clipboard API if available
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(apiKey).then(function() {
                // Success
                $button.addClass('copied');
                $icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
                
                // Reset button after 2 seconds
                setTimeout(function() {
                    $button.removeClass('copied');
                    $icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy:', err);
                fallbackCopy(apiKey, $button, $icon);
            });
        } else {
            // Fallback for older browsers
            fallbackCopy(apiKey, $button, $icon);
        }
    });
    
    // Fallback copy method for older browsers
    function fallbackCopy(text, $button, $icon) {
        var $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                $button.addClass('copied');
                $icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
                
                setTimeout(function() {
                    $button.removeClass('copied');
                    $icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
                }, 2000);
            } else {
                alert('Failed to copy API key. Please copy manually:\n\n' + text);
            }
        } catch(err) {
            console.error('Failed to copy:', err);
            alert('Failed to copy API key. Please copy manually:\n\n' + text);
        }
        
        $temp.remove();
    }

    // Double-click to reveal temporarily (hover effect alternative)
    $('.api-key-display').on('dblclick', function(e) {
        e.preventDefault();
        var keyId = $(this).data('key-id');
        var $container = $(this).closest('.api-key-container');
        var $revealBtn = $container.find('.reveal-api-key[data-key-id="' + keyId + '"]');
        
        if (!$revealBtn.hasClass('revealed')) {
            $revealBtn.trigger('click');
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                if ($revealBtn.hasClass('revealed')) {
                    $revealBtn.trigger('click');
                }
            }, 5000);
        }
    });

    // Confirm before disabling connector if it's currently enabled
    $('form').on('submit', function(e) {
        var enabledCheckbox = $('input[name="wp_llm_connector_settings[enabled]"]');
        var wasEnabled = enabledCheckbox.data('initial-state') !== undefined 
            ? enabledCheckbox.data('initial-state') 
            : enabledCheckbox.is(':checked');
        
        if (wasEnabled && !enabledCheckbox.is(':checked')) {
            if (!confirm('Are you sure you want to disable the LLM Connector? All API access will be blocked.')) {
                e.preventDefault();
                enabledCheckbox.prop('checked', true);
                return false;
            }
        }
        
        // Store initial state
        enabledCheckbox.data('initial-state', enabledCheckbox.is(':checked'));
    });

    // Initialize checkbox states
    $('input[name="wp_llm_connector_settings[enabled]"]').data(
        'initial-state', 
        $('input[name="wp_llm_connector_settings[enabled]"]').is(':checked')
    );

    // Warning when disabling read-only mode
    $('input[name="wp_llm_connector_settings[read_only_mode]"]').on('change', function() {
        if (!$(this).is(':checked')) {
            if (!confirm('WARNING: Disabling read-only mode will allow LLMs to make changes to your site. This is not recommended unless you fully trust the LLM provider and have backups in place.\n\nContinue anyway?')) {
                $(this).prop('checked', true);
            }
        }
    });
    
    // Tooltip helper for API key display
    $('.api-key-display').attr('title', 'Double-click to temporarily reveal for 5 seconds');
});
