<?php
/**
 * GPT Prompt Generator - Database Repair Script
 *
 * This script forces recreation of all required database tables.
 * To use: Load this script in your browser via http://your-site.local/wp-content/plugins/gpt-prompt-generator/gptpg-fix-tables.php
 */

// Initialize WordPress
require_once('../../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('You need to be an administrator to run this script.');
}

// Remove existing DB version to force table creation
delete_option('gptpg_db_version');

// Include necessary files
require_once(plugin_dir_path(__FILE__) . 'includes/class-gptpg-database.php');

echo "<h1>GPT Prompt Generator - Database Repair</h1>";
echo "<pre>";

// Force table creation
echo "Creating database tables...\n";
GPTPG_Database::create_tables();
echo "Basic tables created.\n\n";

// Also run the 0.0.5 upgrade to create normalized tables
echo "Creating normalized tables...\n";
GPTPG_Database::upgrade_0_0_5();
echo "Normalized tables created.\n\n";

// Update DB version
update_option('gptpg_db_version', '0.0.5');
echo "Database version updated to 0.0.5\n\n";

// Check if tables exist
global $wpdb;
$tables_to_check = [
    $wpdb->prefix . 'gptpg_posts',
    $wpdb->prefix . 'gptpg_code_snippets',
    $wpdb->prefix . 'gptpg_prompts',
    $wpdb->prefix . 'gptpg_unique_posts',
    $wpdb->prefix . 'gptpg_unique_snippets',
    $wpdb->prefix . 'gptpg_unique_prompts',
    $wpdb->prefix . 'gptpg_sessions',
    $wpdb->prefix . 'gptpg_session_snippets'
];

echo "Checking tables existence:\n";
foreach ($tables_to_check as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    echo "- $table: " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

echo "</pre>";
echo "<p>Process complete. Please check the results above to ensure all tables exist.</p>";
echo "<p><a href='/wp-admin/'>Return to WordPress Dashboard</a></p>";
