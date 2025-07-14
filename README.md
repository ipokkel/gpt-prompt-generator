# GPT Prompt Generator

Generate a customized ChatGPT prompt template based on Tooltip Recipe Posts, including processing user-provided markdown content, extracting GitHub code snippets, and producing a ready-to-use prompt.

## Description

GPT Prompt Generator is an internal WordPress plugin that streamlines the process of creating ChatGPT prompts for rewriting Tooltip Recipe Posts. It features a multi-step form interface that allows users to:

1. Submit a Tooltip Recipe Post URL
2. Manually paste the post content in Markdown format (with browser extension suggestions for converting HTML to Markdown)
3. Review, add, edit, or remove GitHub code snippet links (optional)
4. Generate a formatted prompt with both the post content and code snippets
5. Copy the generated prompt to clipboard for use with ChatGPT

The plugin uses customizable prompt templates and handles GitHub API integration for code retrieval, while allowing users to manually supply markdown content for posts that may be behind paywalls or require authentication.

## Installation

1. Upload the `gpt-prompt-generator` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > GPT Prompt Generator to configure the plugin
4. Add a GitHub personal access token (optional, improves rate limits)
5. Use the shortcode `[gptpg_prompt_form]` on any page or select a page in settings to display the form

## Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- Composer (for installing dependencies)

## Configuration

1. **Admin Settings Page**: Navigate to Settings > GPT Prompt Generator in the WordPress admin
2. **Prompt Template**: Customize the template used for generating prompts
3. **GitHub API Token**: Add a GitHub token to increase API rate limits
4. **Expiry Time**: Set how long fetched data should be stored
5. **Front-end Page**: Select a page to automatically include the prompt generator form

## Usage

1. Navigate to the page with the form (either via the shortcode or the selected page)
2. Enter a Tooltip Recipe Post URL and click "Continue to Next Step"
3. Manually paste the post title and content in Markdown format
   - Use browser extensions suggested by the form to convert HTML content to Markdown if needed
4. Add, edit, or remove GitHub/Gist code snippet links (optional)
5. Click "Process Snippets" if using code snippets, or proceed directly to generating the prompt
6. Click "Generate Prompt" to create the formatted prompt
7. Copy the prompt to clipboard and paste it into ChatGPT

## Security and Access

- Access to the form is restricted to logged-in users
- Custom hooks are available to modify access control
- All user inputs are sanitized and validated
- GitHub tokens are stored securely

## Advanced

The plugin provides several hooks for customization:

- `gptpg_restricted_access_message`: Filter the access restriction message
- `gptpg_generated_prompt`: Filter the final generated prompt
- `gptpg_user_can_access`: Filter to customize user access control

## Troubleshooting

- **GitHub Rate Limits**: Add a GitHub token in the settings to increase rate limits for code snippet retrieval
- **Markdown Formatting**: Ensure proper Markdown formatting when pasting content in Step 2
- **Code Snippet Errors**: Verify that GitHub/Gist links are correctly formatted
- **Browser Extension Issues**: If suggested extensions don't work well, try a different one from the list

## Browser Extensions for Markdown Conversion

The plugin suggests browser extensions for converting HTML content to Markdown based on your browser. These suggestions are dynamic and display different options based on browser detection. A comprehensive list of supported extensions is maintained in the plugin's `briefs/browser-extension-list.md` file.

Supported browsers include:
- Chrome/Chromium
- Firefox
- Safari
- Microsoft Edge

## Developer Notes

The plugin uses custom database tables to store:
1. Post URLs and user-provided markdown content
2. Code snippet URLs and content (optional)
3. Generated prompts

Data is cleaned up automatically based on the configured expiry time.

The workflow has been optimized to handle paywalled or member-only content by allowing users to manually paste markdown content instead of attempting to automatically fetch it.
