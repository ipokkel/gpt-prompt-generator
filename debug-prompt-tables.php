<?php
/**
 * Debug script to examine prompt tables and data
 */

// Load WordPress environment
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

global $wpdb;

// Get post ID for the URL
$post_url = 'https://www.paidmembershipspro.com/unlock-content-specific-dates/';
$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}gptpg_unique_posts WHERE post_url = %s", $post_url ) );

echo "Post URL: $post_url\n";
echo "Post ID: $post_id\n\n";

// Show table structures
echo "=== TABLE STRUCTURES ===\n\n";

$tables = [
    'unique_prompts',
    'session_prompts',
    'sessions',
];

foreach ( $tables as $table ) {
    $table_name = $wpdb->prefix . 'gptpg_' . $table;
    $structure = $wpdb->get_results( "DESCRIBE $table_name", ARRAY_A );
    
    echo "Table: $table_name\n";
    foreach ( $structure as $column ) {
        echo " - {$column['Field']} ({$column['Type']})\n";
    }
    echo "\n";
}

// Show session data for this post
echo "=== SESSIONS FOR THIS POST ===\n\n";
$sessions = $wpdb->get_results(
    $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gptpg_sessions WHERE post_id = %d", $post_id ),
    ARRAY_A
);

if ( empty( $sessions ) ) {
    echo "No sessions found for this post\n";
} else {
    foreach ( $sessions as $session ) {
        echo "Session ID: {$session['session_id']}, Created: {$session['created_at']}\n";
    }
}
echo "\n";

// Show unique prompts related to this post by post_id
echo "=== UNIQUE PROMPTS FOR THIS POST (BY POST_ID) ===\n\n";
$prompts_by_post = $wpdb->get_results(
    $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gptpg_unique_prompts WHERE prompt_post_id = %d", $post_id ),
    ARRAY_A
);

if ( empty( $prompts_by_post ) ) {
    echo "No prompts found directly linked to this post ID\n";
} else {
    foreach ( $prompts_by_post as $prompt ) {
        echo "Prompt ID: {$prompt['prompt_id']}, Content (excerpt): " . substr( $prompt['prompt_content'], 0, 100 ) . "...\n";
    }
}
echo "\n";

// Show session_prompts data (links between sessions and prompts)
echo "=== SESSION-PROMPT LINKS ===\n\n";
if ( empty( $sessions ) ) {
    echo "No sessions to check for prompt links\n";
} else {
    $session_ids = array_map( function( $s ) { return $s['session_id']; }, $sessions );
    $session_ids_in = "'" . implode( "','", $session_ids ) . "'";
    
    $session_prompts = $wpdb->get_results( 
        "SELECT sp.*, p.prompt_content FROM {$wpdb->prefix}gptpg_session_prompts sp 
        LEFT JOIN {$wpdb->prefix}gptpg_unique_prompts p ON sp.prompt_id = p.prompt_id
        WHERE sp.session_id IN ($session_ids_in)",
        ARRAY_A
    );
    
    if ( empty( $session_prompts ) ) {
        echo "No prompt links found for these sessions\n";
    } else {
        foreach ( $session_prompts as $link ) {
            echo "Session ID: {$link['session_id']}, Prompt ID: {$link['prompt_id']}, Content (excerpt): " . 
                 substr( $link['prompt_content'], 0, 100 ) . "...\n";
        }
    }
}

// Check if prompt_post_id column exists in unique_prompts table
$check_column = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}gptpg_unique_prompts LIKE 'prompt_post_id'", ARRAY_A );
echo "\n=== SCHEMA CHECK ===\n";
echo "Does prompt_post_id column exist? " . (!empty($check_column) ? "YES" : "NO") . "\n";

// Check for any prompts in the system
echo "\n=== ALL PROMPTS COUNT ===\n";
$all_prompts_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gptpg_unique_prompts" );
echo "Total prompts in database: $all_prompts_count\n";

// Search for the post title in prompt content as a last resort
echo "\n=== SEARCH BY POST TITLE ===\n";
$post_title = $wpdb->get_var( $wpdb->prepare( "SELECT post_title FROM {$wpdb->prefix}gptpg_unique_posts WHERE post_id = %d", $post_id ) );
if ( $post_title ) {
    $like_title = '%' . $wpdb->esc_like( $post_title ) . '%';
    $prompts_with_title = $wpdb->get_results( 
        $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gptpg_unique_prompts WHERE prompt_content LIKE %s", $like_title ),
        ARRAY_A
    );
    
    if ( empty( $prompts_with_title ) ) {
        echo "No prompts found containing post title: $post_title\n";
    } else {
        echo "Found prompts containing post title: " . count( $prompts_with_title ) . "\n";
        foreach ( $prompts_with_title as $prompt ) {
            echo "Prompt ID: {$prompt['prompt_id']}, Content (excerpt): " . substr( $prompt['prompt_content'], 0, 100 ) . "...\n";
        }
    }
}
