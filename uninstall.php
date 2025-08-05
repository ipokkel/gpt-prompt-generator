<?php
/**
 * Uninstall script for GPT Prompt Generator
 *
 * This file is executed when the plugin is uninstalled (deleted) through WordPress admin.
 * It respects the admin's choice for data retention set in plugin settings.
 *
 * @since 0.0.16
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Get the admin's preference for data retention
$data_retention = get_option( 'gptpg_uninstall_data_retention', 'keep' );

// Only proceed with data removal if admin chose to remove all data
if ( 'remove' === $data_retention ) {
	global $wpdb;

	// Remove all plugin options
	$option_names = array(
		'gptpg_prompt_template',
		'gptpg_github_token',
		'gptpg_expiry_time',
		'gptpg_form_page_id',
		'gptpg_fetch_strategy',
		'gptpg_debug_logging',
		'gptpg_debug_mode',
		'gptpg_use_plugin_logs',
		'gptpg_uninstall_data_retention',
		'gptpg_db_version',  // Database version tracking
	);

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}

	// Remove any transients that might exist
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gptpg_%' OR option_name LIKE '_transient_timeout_gptpg_%'" );

	// Remove plugin database tables (order matters: drop child tables before parent tables)
	$table_names = array(
		$wpdb->prefix . 'gptpg_unique_snippets', // Child table - has foreign key to posts
		$wpdb->prefix . 'gptpg_unique_prompts',  // Child table - has foreign key to posts
		$wpdb->prefix . 'gptpg_unique_posts',    // Parent table - must be dropped last
	);

	foreach ( $table_names as $table_name ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	// Remove any uploaded plugin files (logs, etc.)
	$upload_dir = wp_upload_dir();
	$plugin_upload_dir = $upload_dir['basedir'] . '/gptpg-logs';
	
	if ( is_dir( $plugin_upload_dir ) ) {
		// Remove log files
		$log_files = glob( $plugin_upload_dir . '/*.log' );
		if ( $log_files ) {
			foreach ( $log_files as $log_file ) {
				if ( is_file( $log_file ) ) {
					unlink( $log_file );
				}
			}
		}
		
		// Remove directory if empty
		@rmdir( $plugin_upload_dir );
	}

	// Clear any object cache that might contain plugin data
	wp_cache_flush();
}

// If 'keep' was selected, do nothing - all data remains intact for potential reinstallation
