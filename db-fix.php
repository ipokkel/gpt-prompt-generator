<?php
/**
 * Database Fix Script
 * 
 * This script manually creates the required database tables for the GPT Prompt Generator plugin.
 * Run this script directly from the browser to create missing tables.
 */

// Load WordPress
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Remove security check temporarily for troubleshooting
// (We'll remove this file when we're done)

// Make sure our plugin is loaded
if ( ! class_exists( 'GPTPG_Database' ) ) {
    require_once( dirname( __FILE__ ) . '/includes/class-gptpg-database.php' );
}

echo '<h1>GPT Prompt Generator Database Fix</h1>';
echo '<p>Attempting to create missing database tables...</p>';

// Manually run the table creation function
GPTPG_Database::create_tables();

// Verify if tables were created
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

echo '<h2>Results:</h2>';
echo '<ul>';

foreach ( $tables_to_check as $table ) {
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
    
    if ( $table_exists ) {
        echo "<li>✅ Table <code>$table</code> exists.</li>";
    } else {
        echo "<li>❌ Table <code>$table</code> does NOT exist.</li>";
    }
}

echo '</ul>';

// Check if we have the database upgrade functions for version 0.0.5
if ( method_exists( 'GPTPG_Database', 'upgrade_0_0_5' ) ) {
    echo '<h2>Running 0.0.5 Upgrade</h2>';
    echo '<p>This will create the normalized tables including sessions table...</p>';
    
    // Use reflection to call the private method
    $class = new ReflectionClass('GPTPG_Database');
    $method = $class->getMethod('upgrade_0_0_5');
    $method->setAccessible(true);
    $method->invoke(null);
    
    echo '<p>Upgrade complete.</p>';
}

echo '<h2>Next Steps</h2>';
echo '<p>If tables are still missing, you may need to deactivate and reactivate the plugin.</p>';
echo '<p><a href="' . admin_url( 'plugins.php' ) . '">Go to Plugins</a></p>';
