# GPT Prompt Generator

Generate a customized ChatGPT prompt template based on Tooltip Recipe Posts, including fetching and processing external post content, extracting GitHub code snippets, and producing a ready-to-use prompt.

## Description

GPT Prompt Generator is an internal WordPress plugin that streamlines the process of creating ChatGPT prompts for rewriting Tooltip Recipe Posts. It features a multi-step form interface that allows users to:

1. Submit a Tooltip Recipe Post URL
2. Review, add, edit, or remove GitHub code snippet links from the post
3. Generate a formatted prompt with both the post content and code snippets
4. Copy the generated prompt to clipboard for use with ChatGPT

The plugin uses customizable prompt templates and handles cross-domain post fetching, HTML to Markdown conversion, and GitHub API integration for code retrieval.

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
2. Enter a Tooltip Recipe Post URL and click "Fetch Post Content"
3. Review and modify the detected GitHub/Gist links
4. Click "Process Snippets" to fetch code content
5. Click "Generate Prompt" to create the formatted prompt
6. Copy the prompt to clipboard and paste it into ChatGPT

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

- **Cross-Domain Issues**: Make sure the Tooltip Recipe Post site allows cross-origin requests
- **GitHub Rate Limits**: Add a GitHub token in the settings to increase rate limits
- **Empty Content**: Check if the post URL is valid and accessible
- **Code Snippet Errors**: Verify that GitHub/Gist links are correctly formatted

## Developer Notes

The plugin uses custom database tables to store:
1. Fetched post content
2. Code snippet URLs and content
3. Generated prompts

Data is cleaned up automatically based on the configured expiry time.
