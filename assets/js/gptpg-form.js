/**
 * GPT Prompt Generator Frontend JavaScript
 * 
 * Handles the multi-step form interactions and AJAX requests.
 */

(function($) {
    'use strict';

    // Form state
    let GPTPG_Form = {
        currentStep: 1,
        sessionId: '',
        postTitle: '',
        snippets: [],
        prompt: '',
        
        // Initialize the form
        init: function() {
            // Step navigation
            $('.gptpg-form-nav-button').on('click', function(e) {
                e.preventDefault();
                const step = $(this).data('step');
                GPTPG_Form.navigateToStep(step);
            });
            
            // URL submission form
            $('#gptpg-url-form').on('submit', function(e) {
                e.preventDefault();
                GPTPG_Form.fetchPostContent();
            });
            
            // Snippets form
            $('#gptpg-snippets-form').on('submit', function(e) {
                e.preventDefault();
                GPTPG_Form.processCodeSnippets();
            });
            
            // Add new snippet button
            $('#gptpg-add-snippet').on('click', function(e) {
                e.preventDefault();
                GPTPG_Form.addSnippetField();
            });
            
            // Generate prompt button
            $('#gptpg-generate-prompt').on('click', function(e) {
                e.preventDefault();
                GPTPG_Form.generatePrompt();
            });
            
            // Copy to clipboard button
            $('#gptpg-copy-prompt').on('click', function(e) {
                e.preventDefault();
                GPTPG_Form.copyToClipboard();
            });
            
            // Start new button
            $('#gptpg-start-new').on('click', function(e) {
                e.preventDefault();
                GPTPG_Form.reset();
            });
            
            // Delete snippet functionality (delegated)
            $('#gptpg-snippets-container').on('click', '.gptpg-delete-snippet', function(e) {
                e.preventDefault();
                $(this).closest('.gptpg-snippet-row').remove();
            });
            
            // Show the first step
            this.navigateToStep(1);
        },
        
        // Navigate to a specific step
        navigateToStep: function(step) {
            // Hide all steps
            $('.gptpg-step').hide();
            
            // Show the current step
            $('#gptpg-step-' + step).show();
            
            // Update the current step
            this.currentStep = parseInt(step);
            
            // Update the step indicator
            $('.gptpg-step-indicator').removeClass('active');
            $('#gptpg-step-indicator-' + step).addClass('active');
        },
        
        // Fetch post content from URL
        fetchPostContent: function() {
            const url = $('#gptpg-post-url').val();
            
            if (!url) {
                this.showError($('#gptpg-step-1'), 'Please enter a valid URL.');
                return;
            }
            
            // Show loading indicator
            this.showLoading($('#gptpg-step-1'));
            
            // Clear previous errors
            this.clearError($('#gptpg-step-1'));
            
            // AJAX request to fetch post content
            $.ajax({
                url: gptpg_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'gptpg_fetch_post',
                    nonce: gptpg_vars.nonce,
                    post_url: url
                },
                success: function(response) {
                    GPTPG_Form.hideLoading($('#gptpg-step-1'));
                    
                    if (response.success) {
                        // Store session data
                        GPTPG_Form.sessionId = response.data.session_id;
                        GPTPG_Form.postTitle = response.data.post_title;
                        GPTPG_Form.snippets = [];
                        
                        // Display post title
                        $('#gptpg-post-title').text(response.data.post_title);
                        
                        // Populate snippets
                        GPTPG_Form.populateSnippets(response.data.github_links);
                        
                        // Navigate to step 2
                        GPTPG_Form.navigateToStep(2);
                    } else {
                        GPTPG_Form.showError($('#gptpg-step-1'), response.data.message);
                    }
                },
                error: function() {
                    GPTPG_Form.hideLoading($('#gptpg-step-1'));
                    GPTPG_Form.showError($('#gptpg-step-1'), 'Failed to connect to the server. Please try again.');
                }
            });
        },
        
        // Populate snippets from GitHub links
        populateSnippets: function(links) {
            const container = $('#gptpg-snippets-container');
            
            // Clear container
            container.empty();
            
            // Add snippets
            if (links && links.length > 0) {
                links.forEach(function(link) {
                    GPTPG_Form.addSnippetField(link);
                });
            } else {
                // Add an empty field if no links found
                GPTPG_Form.addSnippetField();
                
                // Show notification
                $('#gptpg-no-snippets-found').show();
            }
        },
        
        // Add a new snippet field
        addSnippetField: function(url = '') {
            const container = $('#gptpg-snippets-container');
            const index = $('.gptpg-snippet-row').length;
            
            const html = `
                <div class="gptpg-snippet-row">
                    <input type="hidden" class="gptpg-snippet-id" value="">
                    <div class="gptpg-snippet-url">
                        <input type="url" class="gptpg-snippet-url-input" value="${url}" placeholder="Enter GitHub/Gist URL">
                    </div>
                    <div class="gptpg-snippet-actions">
                        <button type="button" class="gptpg-delete-snippet button-link">Delete</button>
                    </div>
                </div>
            `;
            
            container.append(html);
        },
        
        // Process code snippets
        processCodeSnippets: function() {
            // Collect snippets from form
            const snippets = [];
            $('.gptpg-snippet-row').each(function() {
                const id = $(this).find('.gptpg-snippet-id').val();
                const url = $(this).find('.gptpg-snippet-url-input').val();
                
                if (url) {
                    snippets.push({
                        id: id ? parseInt(id) : 0,
                        url: url
                    });
                }
            });
            
            if (snippets.length === 0) {
                this.showError($('#gptpg-step-2'), 'Please add at least one GitHub/Gist URL.');
                return;
            }
            
            // Show loading indicator
            this.showLoading($('#gptpg-step-2'));
            
            // Clear previous errors
            this.clearError($('#gptpg-step-2'));
            
            // AJAX request to process snippets
            $.ajax({
                url: gptpg_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'gptpg_process_snippets',
                    nonce: gptpg_vars.nonce,
                    session_id: this.sessionId,
                    snippets: snippets
                },
                success: function(response) {
                    GPTPG_Form.hideLoading($('#gptpg-step-2'));
                    
                    if (response.success) {
                        // Store snippets
                        GPTPG_Form.snippets = response.data.snippets;
                        
                        // Show errors if any
                        if (response.data.errors && response.data.errors.length > 0) {
                            const errorMessages = response.data.errors.join('<br>');
                            GPTPG_Form.showWarning($('#gptpg-step-2'), errorMessages);
                        } else {
                            GPTPG_Form.clearWarning($('#gptpg-step-2'));
                        }
                        
                        // Navigate to step 3
                        GPTPG_Form.navigateToStep(3);
                    } else {
                        GPTPG_Form.showError($('#gptpg-step-2'), response.data.message);
                    }
                },
                error: function() {
                    GPTPG_Form.hideLoading($('#gptpg-step-2'));
                    GPTPG_Form.showError($('#gptpg-step-2'), 'Failed to connect to the server. Please try again.');
                }
            });
        },
        
        // Generate prompt
        generatePrompt: function() {
            // Show loading indicator
            this.showLoading($('#gptpg-step-3'));
            
            // Clear previous errors
            this.clearError($('#gptpg-step-3'));
            
            // AJAX request to generate prompt
            $.ajax({
                url: gptpg_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'gptpg_generate_prompt',
                    nonce: gptpg_vars.nonce,
                    session_id: this.sessionId
                },
                success: function(response) {
                    GPTPG_Form.hideLoading($('#gptpg-step-3'));
                    
                    if (response.success) {
                        // Store prompt
                        GPTPG_Form.prompt = response.data.prompt;
                        
                        // Display prompt
                        $('#gptpg-generated-prompt').val(GPTPG_Form.prompt);
                        
                        // Show the prompt container
                        $('#gptpg-prompt-container').show();
                    } else {
                        GPTPG_Form.showError($('#gptpg-step-3'), response.data.message);
                    }
                },
                error: function() {
                    GPTPG_Form.hideLoading($('#gptpg-step-3'));
                    GPTPG_Form.showError($('#gptpg-step-3'), 'Failed to connect to the server. Please try again.');
                }
            });
        },
        
        // Copy prompt to clipboard
        copyToClipboard: function() {
            const promptText = $('#gptpg-generated-prompt');
            
            // Select the text
            promptText.select();
            
            try {
                // Copy text to clipboard
                document.execCommand('copy');
                
                // Show success message
                const copyButton = $('#gptpg-copy-prompt');
                const originalText = copyButton.text();
                
                copyButton.text('Copied!');
                
                // Reset button text after 2 seconds
                setTimeout(function() {
                    copyButton.text(originalText);
                }, 2000);
            } catch (err) {
                this.showError($('#gptpg-step-3'), 'Failed to copy text. Please try again or copy manually.');
            }
        },
        
        // Reset form
        reset: function() {
            // Reset state
            this.currentStep = 1;
            this.sessionId = '';
            this.postTitle = '';
            this.snippets = [];
            this.prompt = '';
            
            // Reset form fields
            $('#gptpg-post-url').val('');
            $('#gptpg-snippets-container').empty();
            $('#gptpg-generated-prompt').val('');
            
            // Hide elements
            $('#gptpg-prompt-container').hide();
            $('#gptpg-no-snippets-found').hide();
            
            // Clear errors and warnings
            $('.gptpg-error-message').hide();
            $('.gptpg-warning-message').hide();
            
            // Navigate to step 1
            this.navigateToStep(1);
        },
        
        // Show loading indicator
        showLoading: function(container) {
            container.find('.gptpg-loading').show();
            container.find('button[type="submit"]').prop('disabled', true);
        },
        
        // Hide loading indicator
        hideLoading: function(container) {
            container.find('.gptpg-loading').hide();
            container.find('button[type="submit"]').prop('disabled', false);
        },
        
        // Show error message
        showError: function(container, message) {
            const errorElement = container.find('.gptpg-error-message');
            errorElement.html(message);
            errorElement.show();
        },
        
        // Clear error message
        clearError: function(container) {
            container.find('.gptpg-error-message').hide();
        },
        
        // Show warning message
        showWarning: function(container, message) {
            const warningElement = container.find('.gptpg-warning-message');
            warningElement.html(message);
            warningElement.show();
        },
        
        // Clear warning message
        clearWarning: function(container) {
            container.find('.gptpg-warning-message').hide();
        }
    };
    
    // Initialize form on document ready
    $(document).ready(function() {
        GPTPG_Form.init();
    });
    
})(jQuery);
