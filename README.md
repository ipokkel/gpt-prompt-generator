# GPT Prompt Generator

Generate a customized ChatGPT prompt template based on Tooltip Recipe Posts, including processing user-provided markdown content, extracting GitHub code snippets, and producing a ready-to-use prompt.

## Description

GPT Prompt Generator is an internal WordPress plugin that streamlines the process of creating ChatGPT prompts for rewriting Tooltip Recipe Posts. It features a multi-step form interface that allows users to:

1. Submit a Tooltip Recipe Post URL
2. Review and edit the automatically converted Markdown content (or manually paste content if automatic conversion fails or for posts behind paywalls)
3. Review, add, edit, or remove GitHub code snippet links (optional)
4. Generate a formatted prompt with both the post content and code snippets
5. Copy the generated prompt to clipboard for use with ChatGPT

The plugin automatically attempts to fetch and convert post content to Markdown format, significantly reducing manual work. It uses customizable prompt templates and handles GitHub API integration for code retrieval, while providing fallback options for manual content entry when automatic conversion is not possible.

## Installation

1. Upload the `gpt-prompt-generator` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to GPT Prompt Generator > Settings to configure the plugin
4. Add a GitHub personal access token (optional, improves rate limits)
5. Use the shortcode `[gptpg_prompt_form]` on any page or select a page in settings to display the form

## Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- Composer (for installing dependencies)

## Configuration

1. **Admin Settings Page**: Navigate to GPT Prompt Generator > Settings in the WordPress admin
2. **Prompt Template**: Add and customize the template used for generating prompts
3. **GitHub API Token**: Add a GitHub token to increase API rate limits
4. **Expiry Time**: Set how long fetched data should be stored
5. **Front-end Page**: Select a page to automatically include the prompt generator form

## Usage

**Note**: Before using the form, ensure you have configured a prompt template in the admin settings (GPT Prompt Generator > Settings). The plugin requires a template to function properly.

1. Navigate to the page with the form (either via the shortcode or the selected page)
2. Enter a Tooltip Recipe Post URL and click "Continue to Next Step"
   - The plugin automatically fetches and converts the post content to Markdown
3. Review and edit the automatically converted Markdown content as needed
   - For posts behind paywalls or if automatic conversion fails, manually paste the content
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

## Debug Logging & Issue Reporting

The GPT Prompt Generator includes a comprehensive debug logging system to help identify and resolve issues during testing and development.

### Debug Modes

The plugin supports three debug modes that can be configured via **Settings > GPT Prompt Generator**:

- **🟢 Production Mode**: No debug logging (clean user experience)
- **🟡 Review Mode**: Info, warning, and error logging (recommended for testing and support)
- **🔴 Debug Mode**: All logging including debug messages (for developers)

### Configuration Priority

Debug mode can be controlled through multiple methods with the following priority:

1. **`GPTPG_DEBUG_MODE` constant** (highest priority - can be set in `wp-config.php`)
2. **WordPress option** `gptpg_debug_mode` (controlled via admin UI)
3. **`WP_DEBUG` constant** (fallback)

#### Setting Debug Mode via wp-config.php

```php
// Force debug mode (highest priority)
define( 'GPTPG_DEBUG_MODE', 'debug' );    // Full debug logging
define( 'GPTPG_DEBUG_MODE', 'review' );   // Review mode logging
define( 'GPTPG_DEBUG_MODE', 'production' ); // No logging
```

### Plugin-Specific Log Files (Optional)

By default, logs are written to WordPress's standard debug log. You can enable plugin-specific log files:

#### Enable via Constant
```php
// Enable plugin-specific log files
define( 'GPTPG_USE_PLUGIN_LOGS', true );
```

#### Log File Location
When enabled, logs are written to:
```
/wp-content/uploads/gptpg-logs/gptpg-debug.log
```

**Features:**
- 🔒 **Protected by .htaccess** - Log files are not web-accessible
- 🔄 **Automatic rotation** - Files are rotated when they exceed 10MB
- 📅 **Timestamped entries** - Each log entry includes a timestamp
- 🧹 **Old log cleanup** - Previous logs are kept as `.old` backups

### Issue Reporting Guide

When reporting issues, please enable **Review Mode** and collect the following information:

#### 📊 Debug Information to Collect

1. **Browser Console Logs**
   - Press `F12` → Console tab → Filter for "GPTPG" → Copy all messages

2. **WordPress Debug Log**
   - Check `/wp-content/debug.log` for GPTPG entries (timestamp important)
   - Or check plugin logs at `/wp-content/uploads/gptpg-logs/gptpg-debug.log`

3. **Environment Information**
   - WordPress version
   - Browser type and version
   - Plugin version
   - Debug mode setting

4. **Steps to Reproduce**
   - Exact sequence of actions that trigger the issue

5. **Expected vs Actual Results**
   - What should happen vs what actually happens

#### 🔧 Quick Debug Tips

- **Console Filtering**: In browser console, type "GPTPG" in filter box to show only plugin messages
- **Log File Location**: `/wp-content/debug.log` or `/wp-content/uploads/gptpg-logs/gptpg-debug.log`
- **Timestamp Matching**: Note the exact time when issue occurs for easier log correlation
- **Reproducibility**: Try to reproduce the issue 2-3 times to confirm consistency

#### 📋 Issue Report Template

```markdown
**Bug Report: GPT Prompt Generator**

**Environment:**
- WordPress Version: 
- Plugin Version: 
- Browser: 
- Debug Mode: Review

**Steps to Reproduce:**
1. 
2. 
3. 

**Expected Result:**


**Actual Result:**


**Browser Console Logs:**
```
[Paste GPTPG console messages here]
```

**WordPress Debug Log:**
```
[Paste relevant GPTPG log entries here]
```

**Additional Context:**

```

#### 🚀 Create Issue

[Report Issue on GitHub](https://github.com/ipokkel/gpt-prompt-generator/issues/new) | [View Existing Issues](https://github.com/ipokkel/gpt-prompt-generator/issues)

---

## Developer Notes

The plugin uses custom database tables to store:
1. Post URLs and user-provided markdown content
2. Code snippet URLs and content (optional)
3. Generated prompts

Data is cleaned up automatically based on the configured expiry time.

The workflow has been optimized to handle paywalled or member-only content by allowing users to manually paste markdown content instead of attempting to automatically fetch it.
