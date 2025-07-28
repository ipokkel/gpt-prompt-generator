/**
 * GPT Prompt Generator Admin JavaScript
 * 
 * Handles dynamic behavior on the admin settings page
 */

(function($) {
    'use strict';

    let GPTPG_Admin = {
        
        // Initialize admin functionality
        init: function() {
            this.bindEvents();
            this.updateGuidanceVisibility();
        },

        // Bind event handlers
        bindEvents: function() {
            // Handle debug mode radio button changes
            $('input[name="gptpg_debug_mode"]').on('change', this.handleModeChange.bind(this));
            
            // Handle form submission
            $('#submit').on('click', this.handleFormSubmit.bind(this));
        },

        // Handle debug mode changes
        handleModeChange: function(e) {
            const selectedMode = e.target.value;
            this.updateGuidanceVisibility(selectedMode);
            this.updateModePreview(selectedMode);
        },

        // Update guidance visibility based on mode
        updateGuidanceVisibility: function(mode) {
            const guidanceDiv = $('#gptpg-review-guidance');
            
            // If no mode specified, get current selection
            if (!mode) {
                mode = $('input[name="gptpg_debug_mode"]:checked').val();
            }
            
            if (mode === 'review') {
                guidanceDiv.slideDown(300);
            } else {
                guidanceDiv.slideUp(300);
            }
        },

        // Update mode preview
        updateModePreview: function(mode) {
            const statusBadge = $('.gptpg-mode-badge');
            const loggingStatus = statusBadge.closest('tr').next().find('td');
            
            // Update badge
            statusBadge.removeClass('gptpg-mode-production gptpg-mode-review gptpg-mode-debug')
                      .addClass('gptpg-mode-' + mode)
                      .text(mode.charAt(0).toUpperCase() + mode.slice(1));
            
            // Update logging status
            const isLoggingEnabled = mode !== 'production';
            loggingStatus.html(isLoggingEnabled ? '✅ Yes' : '❌ No');
        },

        // Handle form submission
        handleFormSubmit: function(e) {
            const selectedMode = $('input[name="gptpg_debug_mode"]:checked').val();
            
            // Show confirmation for production mode
            if (selectedMode === 'production') {
                const confirmed = confirm(
                    'You are switching to Production mode. This will disable all debug logging.\n\n' +
                    'Are you sure you want to continue?'
                );
                
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // Show confirmation for debug mode
            if (selectedMode === 'debug') {
                const confirmed = confirm(
                    'You are switching to Debug mode. This will enable verbose logging that may impact performance.\n\n' +
                    'Are you sure you want to continue?'
                );
                
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // Add loading state
            $(e.target).prop('disabled', true).val('Saving...');
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        GPTPG_Admin.init();
    });

})(jQuery);
