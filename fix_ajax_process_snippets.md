# Fix for "Post not found or expired" Error

## Problem
The `ajax_process_snippets` method in `class-gptpg-form-handler.php` is trying to access `$post_data_result['post_data']`, but the `get_post_data_by_id` method returns the data directly.

## Fix Required
In `/Users/theuniscoetzee/Local Sites/pmpro35/app/public/wp-content/plugins/gpt-prompt-generator/includes/class-gptpg-form-handler.php`, around line 319-322:

### Current Code (WRONG):
```php
// Get post data from the database
$post_data_result = GPTPG_Database::get_post_data_by_id( $post_id );
if ( ! $post_data_result || empty( $post_data_result['post_data'] ) ) {
    wp_send_json_error( array( 'message' => __( 'Post not found or expired.', 'gpt-prompt-generator' ) ) );
}
$post_data = (object) $post_data_result['post_data'];
```

### Fixed Code (CORRECT):
```php
// Get post data from the database
$post_data_result = GPTPG_Database::get_post_data_by_id( $post_id );
if ( ! $post_data_result || isset( $post_data_result['error'] ) ) {
    wp_send_json_error( array( 'message' => __( 'Post not found or expired.', 'gpt-prompt-generator' ) ) );
}
$post_data = (object) $post_data_result;
```

## What Changed:
1. Line 319: Changed `empty( $post_data_result['post_data'] )` to `isset( $post_data_result['error'] )`
2. Line 322: Changed `$post_data_result['post_data']` to `$post_data_result`

## Why This Fixes It:
- `get_post_data_by_id` returns the post data directly as an array with keys like 'post_id', 'post_title', etc.
- When there's an error, it returns `array('error' => 'Post not found')`
- The method was incorrectly looking for a 'post_data' key that doesn't exist

Please make these changes manually and test the form again.
