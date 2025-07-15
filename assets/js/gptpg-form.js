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
        postUrl: '',
        postTitle: '',
        postContent: '',
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
                GPTPG_Form.fetchPostUrl();
            });
            
            // Markdown content form
            $('#gptpg-markdown-form').on('submit', function(e) {
                e.preventDefault();
                GPTPG_Form.processMarkdownContent();
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
        
        // Fetch and process post URL
        fetchPostUrl: function() {
            const urlForm = $('#gptpg-url-form');
            const urlInput = $('#gptpg-post-url');
            const url = urlInput.val().trim();
            
            // Clear previous errors and warnings
            GPTPG_Form.clearError(urlForm);
            GPTPG_Form.clearWarning(urlForm);
            
            // Basic URL validation
            if (!url || !url.match(/^https?:\/\/.+/)) {
                GPTPG_Form.showError(urlForm, gptpg_vars.error_invalid_url);
                return;
            }
            
            // Store URL
            GPTPG_Form.postUrl = url;
            
            // Show loading indicator
            GPTPG_Form.showLoading(urlForm);
            
            // Send AJAX request to fetch post data
            $.ajax({
                url: gptpg_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'gptpg_fetch_post',
                    nonce: gptpg_vars.nonce,
                    post_url: url
                },
                success: function(response) {
                    GPTPG_Form.hideLoading(urlForm);
                    
                    if (response.success) {
                        // Store session ID and post title
                        GPTPG_Form.sessionId = response.data.session_id;
                        GPTPG_Form.postTitle = response.data.post_title;
                        
                        // Display URL in next step
                        $('#gptpg-display-url').text(url);
                        
                        // Check if this is a duplicate post
                        if (response.data.is_duplicate_post) {
                            // Clear any previous duplicate content options
                            $('#gptpg-duplicate-options').remove();
                            
                            // Create duplicate content options container
                            let duplicateOptionsHtml = '<div id="gptpg-duplicate-options" class="gptpg-duplicate-options">';
                            duplicateOptionsHtml += '<h3>This post has been processed before</h3>';
                            duplicateOptionsHtml += '<p>You have the following options:</p>';
                            duplicateOptionsHtml += '<div class="gptpg-duplicate-buttons">';
                            
                            // Debug logging removed
                            
                            // Always show markdown button for duplicate posts since we should have markdown content
                            duplicateOptionsHtml += '<button type="button" id="gptpg-view-markdown" class="button">View Existing Markdown</button>';
                            
                            // Check if we have existing snippets - more flexible checking
                            if (response.data.has_snippets === true || 
                                response.data.has_snippets === 'true' || 
                                response.data.has_snippets === 1 || 
                                (response.data.snippets && response.data.snippets.length > 0)) {

                                duplicateOptionsHtml += '<button type="button" id="gptpg-view-snippets" class="button">View Existing Snippets</button>';
                            }
                            
                            // Check if we have existing prompt - more flexible checking
                            if (response.data.has_prompt === true || 
                                response.data.has_prompt === 'true' || 
                                response.data.has_prompt === 1 || 
                                response.data.prompt) {

                                duplicateOptionsHtml += '<button type="button" id="gptpg-view-prompt" class="button">View Existing Prompt</button>';
                            }
                            
                            // Add option to update content
                            duplicateOptionsHtml += '<button type="button" id="gptpg-update-content" class="button button-primary">Update Existing Content</button>';
                            duplicateOptionsHtml += '</div></div>';
                            
                            // Show warning with options
                            GPTPG_Form.showWarning(urlForm, duplicateOptionsHtml);
                            
                            // Add click handlers
                            $('#gptpg-view-markdown').on('click', function() {
                                // Load existing markdown content
                                GPTPG_Form.navigateToStep(2);
                                // Pre-populate markdown field
                                if (response.data.markdown_content) {
                                    $('#gptpg-markdown-content').val(response.data.markdown_content);
                                }
                            });
                            
                            $('#gptpg-view-snippets').on('click', function() {
                                // Navigate to snippets step
                                GPTPG_Form.navigateToStep(3);
                                // Pre-populate snippets
                                GPTPG_Form.populateExistingSnippets(response.data.snippets || []);
                            });
                            
                            $('#gptpg-view-prompt').on('click', function() {
                                // Navigate to prompt step
                                GPTPG_Form.navigateToStep(4);
                                // Pre-populate prompt
                                if (response.data.prompt) {
                                    $('#gptpg-generated-prompt').val(response.data.prompt);
                                    $('#gptpg-prompt-container').show();
                                }
                            });
                            
                            $('#gptpg-update-content').on('click', function() {
                                // Navigate to step 2 to continue the normal flow
                                GPTPG_Form.navigateToStep(2);
                            });
                            
                        } else {
                            // Navigate to step 2
                            GPTPG_Form.navigateToStep(2);
                        }
                        
                        // Check for duplicate snippets
                        if (response.data.duplicate_snippets && response.data.duplicate_snippets.length > 0) {
                            let snippetsList = '';
                            response.data.duplicate_snippets.forEach(function(snippet) {
                                snippetsList += '<li>' + snippet.url + '</li>';
                            });
                            
                            if (snippetsList) {
                                GPTPG_Form.showWarning($('#gptpg-step-2'), 
                                    'Some code snippets have been processed before:<ul>' + snippetsList + '</ul>');
                            }
                        }
                    } else {
                        GPTPG_Form.showError(urlForm, response.data.message || gptpg_vars.error_fetch_failed);
                    }
                },
                error: function() {
                    GPTPG_Form.hideLoading(urlForm);
                    GPTPG_Form.showError(urlForm, gptpg_vars.error_ajax_failed);
                }
            });
        },
        
        // Process markdown content
        processMarkdownContent: function() {
            const markdownForm = $('#gptpg-markdown-form');
            const contentInput = $('#gptpg-markdown-content');
            
            const content = contentInput.val().trim();
            
            // Clear previous errors
            GPTPG_Form.clearError(markdownForm);
            
            // Validate inputs
            if (!content) {
                GPTPG_Form.showError(markdownForm, 'Please paste the markdown content of the post.');
                return;
            }
            
            // Show loading indicator
            GPTPG_Form.showLoading(markdownForm);
            
            // Store values
            GPTPG_Form.postTitle = ''; // Empty title
            GPTPG_Form.postContent = content;
            
            // Send AJAX request to store content in session
            $.ajax({
                url: gptpg_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'gptpg_store_markdown',
                    nonce: gptpg_vars.nonce,
                    post_url: GPTPG_Form.postUrl,
                    post_content: content
                },
                success: function(response) {
                    GPTPG_Form.hideLoading(markdownForm);
                    
                    if (response.success) {
                        // Store session ID for future requests
                        GPTPG_Form.sessionId = response.data.session_id;
                        
                        // Extract GitHub links if any
                        if (response.data.github_links && response.data.github_links.length > 0) {
                            GPTPG_Form.populateSnippets(response.data.github_links);
                        } else {
                            // Add empty snippet field if no links found
                            GPTPG_Form.addSnippetField();
                        }
                        
                        // Navigate to step 3
                        GPTPG_Form.navigateToStep(3);
                    } else {
                        GPTPG_Form.showError(markdownForm, response.data.message || 'Failed to process markdown content.');
                    }
                },
                error: function() {
                    GPTPG_Form.hideLoading(markdownForm);
                    GPTPG_Form.showError(markdownForm, gptpg_vars.error_ajax_failed);
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
        
        // Populate existing snippets from database
        populateExistingSnippets: function(snippets) {
            const container = $('#gptpg-snippets-container');
            
            // Clear container
            container.empty();
            
            // Add snippets
            if (snippets && snippets.length > 0) {
                snippets.forEach(function(snippet) {
                    // Get URL from snippet object
                    const url = snippet.url || '';
                    // Get ID from snippet object
                    const id = snippet.id || 0;
                    
                    // Add new row
                    const row = $('<div class="gptpg-snippet-row"></div>');
                    row.data('id', id);
                    
                    // Add hidden ID field
                    const idField = $('<input type="hidden" class="gptpg-snippet-id">');
                    idField.val(id);
                    row.append(idField);
                    
                    // Add URL input field
                    const urlContainer = $('<div class="gptpg-snippet-url"></div>');
                    const urlInput = $('<input type="text" class="gptpg-snippet-url-input" placeholder="https://github.com/user/repo/...">');
                    urlInput.val(url);
                    urlContainer.append(urlInput);
                    row.append(urlContainer);
                    
                    // Add delete button
                    const actions = $('<div class="gptpg-snippet-actions"></div>');
                    const deleteButton = $('<button type="button" class="button gptpg-delete-snippet">Remove</button>');
                    actions.append(deleteButton);
                    row.append(actions);
                    
                    // Add to container
                    container.append(row);
                });
            } else {
                // Add an empty field if no snippets
                GPTPG_Form.addSnippetField();
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
            
            // Snippets are now optional since we already have markdown content
            if (snippets.length === 0) {
                // No snippets, just proceed to step 4
                this.navigateToStep(4);
                return;
            }
            
            // Show loading indicator
            this.showLoading($('#gptpg-step-3'));
            
            // Clear previous errors
            this.clearError($('#gptpg-step-3'));
            
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
                    GPTPG_Form.hideLoading($('#gptpg-step-3'));
                    
                    if (response.success) {
                        // Store snippets
                        GPTPG_Form.snippets = response.data.snippets;
                        
                        // Check for duplicate snippets
                        let duplicateWarnings = [];
                        let hasWarnings = false;
                        
                        // Add duplicate snippet warnings
                        if (response.data.snippets && response.data.snippets.length > 0) {
                            response.data.snippets.forEach(function(snippet) {
                                if (snippet.is_duplicate) {
                                    duplicateWarnings.push('The snippet at URL <strong>' + snippet.url + '</strong> was already processed before. The existing snippet has been updated.');
                                    hasWarnings = true;
                                }
                            });
                        }
                        
                        // Show existing errors if any
                        if (response.data.errors && response.data.errors.length > 0) {
                            const errorMessages = response.data.errors.join('<br>');
                            duplicateWarnings.push(errorMessages);
                            hasWarnings = true;
                        }
                        
                        // Display all warnings
                        if (hasWarnings) {
                            const warningHtml = duplicateWarnings.join('<br><br>');
                            GPTPG_Form.showWarning($('#gptpg-step-3'), warningHtml);
                            
                            // Add a small delay before proceeding to ensure the warning is seen
                            setTimeout(function() {
                                GPTPG_Form.navigateToStep(4);
                            }, 2000);
                        } else {
                            GPTPG_Form.clearWarning($('#gptpg-step-3'));
                            // Navigate to step 4
                            GPTPG_Form.navigateToStep(4);
                        }
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
        
        // Generate prompt
        generatePrompt: function() {
            // Show loading indicator
            this.showLoading($('#gptpg-step-4'));
            
            // Clear previous errors
            this.clearError($('#gptpg-step-4'));
            
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
                    GPTPG_Form.hideLoading($('#gptpg-step-4'));
                    
                    if (response.success) {
                        // Store the prompt
                        GPTPG_Form.prompt = response.data.prompt;
                        
                        // Display the prompt
                        $('#gptpg-generated-prompt').val(response.data.prompt);
                        $('#gptpg-prompt-container').show();
                        
                        // Setup copy button
                        $('#gptpg-copy-prompt').on('click', GPTPG_Form.copyToClipboard);
                        
                        // Setup new button
                        $('#gptpg-start-new').on('click', GPTPG_Form.reset);
                        
                        // Check for duplicate prompt
                        if (response.data.is_duplicate_prompt) {
                            GPTPG_Form.showWarning($('#gptpg-step-4'), 
                                'This prompt was generated before for similar content. The existing prompt has been updated.');
                        }
                    } else {
                        GPTPG_Form.showError($('#gptpg-step-4'), response.data.message);
                    }
                },
                error: function() {
                    GPTPG_Form.hideLoading($('#gptpg-step-4'));
                    GPTPG_Form.showError($('#gptpg-step-4'), 'Failed to connect to the server. Please try again.');
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
