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
        postId: '',
        postUrl: '',
        postTitle: '',
        postContent: '',
        snippets: [],
        prompt: '',
        
        // Initialize the form
        init: function() {
            // Try to restore form state from localStorage
            const savedState = localStorage.getItem('gptpg_form_state');
            if (savedState) {
                try {
                    const state = JSON.parse(savedState);
                    this.postId = state.postId || '';
                    this.postUrl = state.postUrl || '';
                    this.postTitle = state.postTitle || '';
                    this.postContent = state.postContent || '';
                    this.snippets = state.snippets || [];
                    
                    // Don't restore currentStep - always start at step 1 when page loads
                    // but prepare form fields with saved data
                    if (this.postUrl) {
                        $('#gptpg-post-url').val(this.postUrl);
                    }
                    
                    if (this.postContent) {
                        $('#gptpg-post-content').val(this.postContent);
                    }
                    
                    // Show a restore notice if we have data
                    if (this.postId) {
                        const notice = $('<div class="gptpg-notice gptpg-info-message"></div>')
                            .html('You have saved data. Would you like to restore it?');
                        
                        const restoreButton = $('<button>', {
                            'class': 'button button-primary gptpg-restore-btn',
                            'text': 'Restore Data'
                        }).on('click', function(e) {
                            e.preventDefault();
                            GPTPG_Form.restoreSession();
                        });
                        
                        const discardButton = $('<button>', {
                            'class': 'button gptpg-discard-btn',
                            'text': 'Start New'
                        }).on('click', function(e) {
                            e.preventDefault();
                            GPTPG_Form.reset();
                        });
                        
                        notice.append('<br>').append(restoreButton).append(' ').append(discardButton);
                        $('#gptpg-step-1').prepend(notice);
                    }
                } catch (e) {
                    console.error('Error restoring form state:', e);
                    localStorage.removeItem('gptpg_form_state');
                }
            }
            
            // Check for saved state and offer to restore
            GPTPG_Form.checkForSavedState();
            
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
        
        // Check for saved state and offer to restore
        checkForSavedState: function() {
            var savedState = localStorage.getItem('gptpg_form_state');
            
            if (savedState) {
                try {
                    var state = JSON.parse(savedState);
                    var now = new Date().getTime();
                    var savedTime = state.timestamp || 0;
                    
                    // Only show restore option if saved within last 24 hours
                    if (now - savedTime < 24 * 60 * 60 * 1000) {
                        // Show restore option if we have a post ID
                        if (state.postId) {
                            $('#gptpg-restore-state').show();
                            
                            $('#gptpg-restore-button').on('click', function(e) {
                                e.preventDefault();
                                GPTPG_Form.restoreState(state);
                            });
                            
                            $('#gptpg-start-fresh').on('click', function(e) {
                                e.preventDefault();
                                $('#gptpg-restore-state').hide();
                            });
                        }
                    }
                } catch (e) {
                    console.error('Error parsing saved state:', e);
                    localStorage.removeItem('gptpg_form_state');
                }
            }
        },
        
        // Save current form state to localStorage
        saveState: function() {
            const state = {
                postId: this.postId,
                postUrl: this.postUrl,
                postTitle: this.postTitle,
                postContent: this.postContent,
                snippets: this.snippets,
                lastUpdated: new Date().toISOString()
            };
            
            localStorage.setItem('gptpg_form_state', JSON.stringify(state));
        },
        
        // Restore saved session data and navigate to appropriate step
        restoreSession: function() {
            // Remove the restore notice
            $('.gptpg-info-message').remove();
            
            // If we have markdown content, we can go to step 2
            if (this.postContent) {
                $('#gptpg-display-url').text(this.postUrl);
                $('#gptpg-post-content').val(this.postContent);
                this.navigateToStep(2);
            } 
            // If we have snippets, we can go to step 3
            else if (this.snippets && this.snippets.length > 0) {
                $('#gptpg-display-url').text(this.postUrl);
                this.populateSnippets();
                this.navigateToStep(3);
            }
        },
        
        // Navigate to a specific step
        navigateToStep: function(step) {
            console.log('NAVIGATE: Attempting to navigate to step', step);
            
            // Hide all steps with stronger approach to override any inline styles
            $('.gptpg-step').each(function() {
                $(this).hide().attr('style', 'display: none !important');
            });
            console.log('NAVIGATE: All steps hidden');
            
            // Show the current step
            $('#gptpg-step-' + step).show();
            console.log('NAVIGATE: Showing step', step, 'Visible:', $('#gptpg-step-' + step).is(':visible'));
            
            // Update the current step
            this.currentStep = parseInt(step);
            console.log('NAVIGATE: Current step updated to', this.currentStep);
            
            // Update the step indicator
            $('.gptpg-step-indicator').removeClass('active');
            $('#gptpg-step-indicator-' + step).addClass('active');
            console.log('NAVIGATE: Step indicators updated');
        },
        
        // Fetch and process post URL
        fetchPostUrl: function() {
            const urlForm = $('#gptpg-url-form');
            const urlInput = $('#gptpg-post-url');
            const url = urlInput.val().trim();
            
            // Debug: Log initial URL input
            console.log('URL Input Value:', url);
            
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
                    
                    // Debug: Log full response
                    console.log('AJAX Response:', response);
                    
                    console.log('AJAX: Response success status:', response.success);
                    if (response.success) {
                        // Store post ID and post title
                        GPTPG_Form.postId = response.data.post_id;
                        GPTPG_Form.postTitle = response.data.post_title;
                        
                        // Save state to localStorage
                        GPTPG_Form.saveState();
                        
                        // Display URL in next step
                        $('#gptpg-display-url').text(url);
                        
                        // Check if this is a duplicate post
                        console.log('Is duplicate post:', response.data.is_duplicate_post);
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
                            console.log('FLOW: About to navigate to step 2 (non-duplicate path)');
                            GPTPG_Form.navigateToStep(2);
                            console.log('FLOW: After navigation call - current step:', GPTPG_Form.currentStep);
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
                        // Check if the error message indicates a paywall or membership restriction
                        const errorMessage = response.data.message || gptpg_vars.error_fetch_failed;
                        const isPaywallError = errorMessage.toLowerCase().includes('paywall') || 
                                               errorMessage.toLowerCase().includes('membership restriction');
                        
                        if (isPaywallError) {
                            // For paywall errors, show warning but allow proceeding to step 2
                            GPTPG_Form.showWarning(urlForm, errorMessage);
                            
                            // Create a continue button that will allow proceeding to step 2
                            const continueButton = $('<button>', {
                                type: 'button',
                                class: 'button button-primary',
                                text: 'Continue to Step 2',
                                css: { 'margin-top': '10px' }
                            }).on('click', function() {
                                // Display URL in next step
                                $('#gptpg-display-url').text(GPTPG_Form.postUrl);
                                
                                // Navigate to step 2
                                GPTPG_Form.navigateToStep(2);
                                
                                // Show notification on step 2
                                GPTPG_Form.showWarning($('#gptpg-step-2'), 
                                    'This content appears to be behind a paywall or membership restriction. ' +
                                    'Please use browser extensions or manual copy-paste to gather the content and paste it below.');
                            });
                            
                            // Append the button to the warning message
                            $('.gptpg-warning-message').append(continueButton);
                        } else {
                            // For other errors, show error message and stay on step 1
                            GPTPG_Form.showError(urlForm, errorMessage);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    GPTPG_Form.hideLoading(urlForm);
                    console.log('AJAX Error:', status, error);
                    console.log('XHR:', xhr);
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
                    
                    console.log('AJAX: Response success status:', response.success);
                    if (response.success) {
                        // Store post ID for future requests
                        GPTPG_Form.postId = response.data.post_id;
                        
                        // Debug: Log the entire response data
                        console.log('GPTPG DEBUG: Step 2 submission response data:', response.data);
                        
                        // Check if we have existing snippets from a duplicate post
                        if (response.data.snippets && response.data.snippets.length > 0) {
                            console.log('GPTPG DEBUG: Using existing snippets from database:', response.data.snippets);
                            console.log('GPTPG DEBUG: Number of snippets found:', response.data.snippets.length);
                            // Use existing snippets from the database
                            GPTPG_Form.populateExistingSnippets(response.data.snippets);
                        } 
                        // If no existing snippets, extract GitHub links if any
                        else if (response.data.github_links && response.data.github_links.length > 0) {
                            console.log('GPTPG DEBUG: Using GitHub links from markdown:', response.data.github_links);
                            GPTPG_Form.populateSnippets(response.data.github_links);
                        } else {
                            // Add empty snippet field if no links or existing snippets found
                            console.log('GPTPG DEBUG: No snippets or GitHub links found, adding empty field');
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
                    post_id: this.postId,
                    snippets: snippets
                },
                success: function(response) {
                    GPTPG_Form.hideLoading($('#gptpg-step-3'));
                    
                    console.log('AJAX: Response success status:', response.success);
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
                    post_id: this.postId
                },
                success: function(response) {
                    GPTPG_Form.hideLoading($('#gptpg-step-4'));
                    
                    console.log('AJAX: Response success status:', response.success);
                    if (response.success) {
                        // Store the prompt
                        GPTPG_Form.prompt = response.data.prompt;
                        
                        // Display the prompt
                        $('#gptpg-generated-prompt').val(response.data.prompt);
                        $('#gptpg-prompt-container').show();
                        
                        // Setup copy button
                        $('#gptpg-copy-prompt').on('click', GPTPG_Form.copyToClipboard);
                        
                        // Setup new button
                        $('#gptpg-start-new').on('click', function() {
                            GPTPG_Form.reset();
                        });
                        
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
            this.postId = '';
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
        console.log('GPTPG Form: Document ready triggered');
        
        // Debug form elements
        console.log('Step 1 element exists:', $('#gptpg-step-1').length > 0);
        console.log('Step 1 display state:', $('#gptpg-step-1').css('display'));
        console.log('URL input exists:', $('#gptpg-post-url').length > 0);
        
        // Initialize form
        GPTPG_Form.init();
        
        // Debug after init
        console.log('After init - Step 1 display state:', $('#gptpg-step-1').css('display'));
        
        // Force display of step 1 if no step is visible (fallback)
        setTimeout(function() {
            console.log('GPTPG Form: Running visibility check');
            console.log('Visible steps:', $('.gptpg-step:visible').length);
            
            if ($('.gptpg-step:visible').length === 0) {
                console.log('No form step visible, forcing display of step 1');
                $('#gptpg-step-1').show();
                $('.gptpg-step-indicator').removeClass('active');
                $('#gptpg-step-indicator-1').addClass('active');
                
                console.log('After force - Step 1 display state:', $('#gptpg-step-1').css('display'));
            }
        }, 500); // Small delay to ensure other scripts have run
    });
    
})(jQuery);
