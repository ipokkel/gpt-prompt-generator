<?php
/**
 * This file triggers database upgrades for development testing.
 */

// Ensure this is only called directly
if (!defined('ABSPATH') && !isset($_GET['run_upgrade'])) {
    die('This file cannot be accessed directly.');
}

// Load WordPress
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

// Verify user is logged in as admin
if (!current_user_can('manage_options')) {
    die('You need to be an administrator to run this script.');
}

// Create a temporary class to run database updates
class GPTPG_DB_Update_Helper {
    /**
     * Run the manual database upgrade
     */
    public static function run_upgrade() {
        global $wpdb;
        
        echo '<h2>Running Database Upgrades</h2>';
        
        // 1. Add user_id column to all tables
        $tables = array(
            $wpdb->prefix . 'gptpg_posts',
            $wpdb->prefix . 'gptpg_code_snippets',
            $wpdb->prefix . 'gptpg_prompts'
        );
        
        echo '<h3>Adding user_id column to tables</h3>';
        
        foreach ($tables as $table) {
            // Check if the column already exists to avoid errors
            $column_exists = $wpdb->get_results(
                "SHOW COLUMNS FROM {$table} LIKE 'user_id'"
            );
            
            if (empty($column_exists)) {
                // Add user_id column after the id column
                $wpdb->query(
                    "ALTER TABLE {$table} ADD COLUMN `user_id` bigint(20) unsigned DEFAULT NULL AFTER `id`"
                );
                echo "<p>Added user_id column to {$table}</p>";
            } else {
                echo "<p>user_id column already exists in {$table}</p>";
            }
        }
        
        // 2. Rename user_edited column to is_user_edited in code_snippets table if needed
        $table_snippets = $wpdb->prefix . 'gptpg_code_snippets';
        
        echo '<h3>Checking user_edited column rename</h3>';
        
        // Check if the column exists before attempting to rename
        $column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM {$table_snippets} LIKE 'user_edited'"
        );
        
        if (!empty($column_exists)) {
            // Check if is_user_edited already exists
            $is_user_edited_exists = $wpdb->get_results(
                "SHOW COLUMNS FROM {$table_snippets} LIKE 'is_user_edited'"
            );
            
            if (empty($is_user_edited_exists)) {
                // Rename column from user_edited to is_user_edited
                $wpdb->query(
                    "ALTER TABLE {$table_snippets} CHANGE `user_edited` `is_user_edited` tinyint(1) NOT NULL DEFAULT 0"
                );
                echo "<p>Renamed user_edited column to is_user_edited in {$table_snippets}</p>";
            } else {
                // Drop user_edited column since is_user_edited already exists
                $wpdb->query(
                    "ALTER TABLE {$table_snippets} DROP COLUMN `user_edited`"
                );
                echo "<p>Dropped redundant user_edited column in {$table_snippets}</p>";
            }
        } else {
            echo "<p>user_edited column doesn't exist in {$table_snippets}</p>";
        }
        
        // 3. Update database version
        update_option('gptpg_db_version', GPTPG_VERSION);
        echo '<p>Updated database version to: ' . GPTPG_VERSION . '</p>';
    }
}

// Run the upgrade
GPTPG_DB_Update_Helper::run_upgrade();

echo '<p><a href="' . admin_url('admin.php?page=gptpg-settings') . '">Return to plugin settings</a></p>';
