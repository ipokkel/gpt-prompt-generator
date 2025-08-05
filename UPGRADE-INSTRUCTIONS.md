# GPT Prompt Generator - Upgrade Instructions

## Version 0.0.15 - Database Reset and Backward Compatibility Removal

This version removes all backward compatibility code and legacy session tables. The plugin now acts as if it is a fresh install every time it's activated.

### Changes Made

1. **Removed Legacy Tables**:
   - `wp_gptpg_sessions` (no longer used)
   - `wp_gptpg_session_snippets` (no longer used)

2. **Removed Session-Based Functionality**:
   - All session-related AJAX handlers have been removed
   - Session verification functions have been removed
   - Transient token logic that used session_id has been removed

3. **Added Database Reset Functionality**:
   - New `reset_database()` method in `GPTPG_Database` class
   - New `fresh_install()` method that ensures clean state on activation
   - Plugin now completely resets database tables on activation

### What This Means for Users

- All existing data will be removed when the plugin is reactivated
- The plugin will behave as if it's being installed for the first time
- No legacy code or tables will be used
- Improved performance and simplified architecture

### Upgrade Process

1. **Backup Your Data** (if needed):
   - If you have important data in the plugin, export it before upgrading
   - Note that the session-based data model has already been deprecated

2. **Upgrade the Plugin**:
   - Replace the plugin files with the new version
   - Activate the plugin (this will trigger a fresh install)

3. **Verify Installation**:
   - Check that the plugin works correctly
   - All new data will be stored using the post_id-based model

### For Developers

- The plugin now only uses three database tables:
  - `wp_gptpg_unique_posts`
  - `wp_gptpg_unique_snippets`
  - `wp_gptpg_unique_prompts`

- All data is now directly associated with posts via post_id
- No more session management or session-based storage

### Testing

The plugin has been tested to ensure it works correctly as a fresh install with no legacy data or code.

If you encounter any issues after upgrading, please contact support.
