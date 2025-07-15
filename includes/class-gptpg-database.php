<?php
/**
 * GPTPG Database Handler
 *
 * Handles database operations for the GPT Prompt Generator plugin.
 *
 * @package GPT_Prompt_Generator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GPTPG_Database class
 *
 * Handles creation and management of custom database tables.
 */
class GPTPG_Database {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		// Register activation hook to create tables.
		register_activation_hook( GPTPG_PLUGIN_FILE, array( __CLASS__, 'create_tables' ) );
		
		// Check if we need to update the database.
		add_action( 'plugins_loaded', array( __CLASS__, 'check_version' ) );
	}

	/**
	 * Get the database version.
	 *
	 * @return string Current database version.
	 */
	public static function get_db_version() {
		return get_option( 'gptpg_db_version', '1.0.0' );
	}

	/**
	 * Check if we need to update the database.
	 */
	public static function check_version() {
		$current_db_version = self::get_db_version();
		
		// If versions don't match, perform necessary upgrades
		if ( $current_db_version !== GPTPG_VERSION ) {
			// Create or update tables
			self::create_tables();
			
			// Perform version-specific upgrades
			if ( version_compare( $current_db_version, '0.0.4', '<' ) ) {
				self::upgrade_0_0_4();
			}
			
			// Update the database version
			update_option( 'gptpg_db_version', GPTPG_VERSION );
		}
	}
	
	/**
	 * Upgrade database to version 0.0.4
	 * - Renames user_edited column to is_user_edited in gptpg_code_snippets table
	 */
	private static function upgrade_0_0_4() {
		global $wpdb;
		
		$table_snippets = $wpdb->prefix . 'gptpg_code_snippets';
		
		// Check if the column exists before attempting to rename
		$column_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM {$table_snippets} LIKE 'user_edited'"
		);
		
		if ( !empty( $column_exists ) ) {
			// Rename column from user_edited to is_user_edited
			$wpdb->query(
				"ALTER TABLE {$table_snippets} CHANGE `user_edited` `is_user_edited` tinyint(1) NOT NULL DEFAULT 0"
			);
		}
	}

	/**
	 * Create database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// Table for storing fetched posts.
		$table_posts = $wpdb->prefix . 'gptpg_posts';
		$sql_posts = "CREATE TABLE $table_posts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(255) NOT NULL,
			post_url varchar(2083) NOT NULL,
			post_title text NOT NULL,
			post_content longtext NOT NULL,
			post_content_markdown longtext NOT NULL,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY expires_at (expires_at)
		) $charset_collate;";
		dbDelta( $sql_posts );

		// Table for storing code snippets.
		$table_snippets = $wpdb->prefix . 'gptpg_code_snippets';
		$sql_snippets = "CREATE TABLE $table_snippets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(255) NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			snippet_url varchar(2083) NOT NULL,
			snippet_type varchar(50) NOT NULL,
			snippet_content longtext,
			is_user_edited tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY post_id (post_id)
		) $charset_collate;";
		dbDelta( $sql_snippets );

		// Table for storing generated prompts.
		$table_prompts = $wpdb->prefix . 'gptpg_prompts';
		$sql_prompts = "CREATE TABLE $table_prompts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(255) NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			prompt_content longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY post_id (post_id)
		) $charset_collate;";
		dbDelta( $sql_prompts );

		// Update database version.
		update_option( 'gptpg_db_version', GPTPG_VERSION );
		
		// Schedule cleanup of expired sessions.
		if ( ! wp_next_scheduled( 'gptpg_cleanup_expired_data' ) ) {
			wp_schedule_event( time(), 'daily', 'gptpg_cleanup_expired_data' );
		}
	}

	/**
	 * Cleanup expired data from database.
	 */
	public static function cleanup_expired_data() {
		global $wpdb;
		
		// Get current time.
		$current_time = current_time( 'mysql', true );
		
		// Delete expired posts and related data.
		$table_posts = $wpdb->prefix . 'gptpg_posts';
		$expired_posts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM $table_posts WHERE expires_at < %s",
				$current_time
			)
		);
		
		if ( ! empty( $expired_posts ) ) {
			$table_snippets = $wpdb->prefix . 'gptpg_code_snippets';
			$table_prompts = $wpdb->prefix . 'gptpg_prompts';
			
			// Delete related snippets.
			$wpdb->query( "DELETE FROM $table_snippets WHERE post_id IN (" . implode( ',', $expired_posts ) . ")" );
			
			// Delete related prompts.
			$wpdb->query( "DELETE FROM $table_prompts WHERE post_id IN (" . implode( ',', $expired_posts ) . ")" );
			
			// Delete expired posts.
			$wpdb->query( "DELETE FROM $table_posts WHERE id IN (" . implode( ',', $expired_posts ) . ")" );
		}
	}

	/**
	 * Store fetched post data.
	 *
	 * @param string $session_id    Session ID.
	 * @param string $post_url      URL of the fetched post.
	 * @param string $post_title    Title of the fetched post.
	 * @param string $post_content  HTML content of the fetched post.
	 * @param string $post_content_markdown Markdown version of the post content.
	 * @param int    $expires_in    Seconds until expiration (default 60 minutes).
	 * 
	 * @return int|false Post ID on success, false on failure.
	 */
	public static function store_post( $session_id, $post_url, $post_title, $post_content, $post_content_markdown, $expires_in = 3600 ) {
		global $wpdb;
		
		$table_posts = $wpdb->prefix . 'gptpg_posts';
		
		$result = $wpdb->insert(
			$table_posts,
			array(
				'session_id'           => $session_id,
				'post_url'             => $post_url,
				'post_title'           => $post_title,
				'post_content'         => $post_content,
				'post_content_markdown' => $post_content_markdown,
				'created_at'           => current_time( 'mysql', true ),
				'expires_at'           => gmdate( 'Y-m-d H:i:s', time() + $expires_in ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Store code snippet.
	 *
	 * @param string $session_id      Session ID.
	 * @param int    $post_id         Post ID.
	 * @param string $snippet_url     URL of the code snippet.
	 * @param string $snippet_type    Type of snippet (github, gist, raw).
	 * @param string $snippet_content Optional content of the snippet.
	 * @param bool   $is_user_edited  Whether this snippet was edited by the user.
	 * 
	 * @return int|false Snippet ID on success, false on failure.
	 */
	public static function store_snippet( $session_id, $post_id, $snippet_url, $snippet_type, $snippet_content = '', $is_user_edited = false ) {
		global $wpdb;
		
		$table_snippets = $wpdb->prefix . 'gptpg_code_snippets';
		
		$result = $wpdb->insert(
			$table_snippets,
			array(
				'session_id'      => $session_id,
				'post_id'         => $post_id,
				'snippet_url'     => $snippet_url,
				'snippet_type'    => $snippet_type,
				'snippet_content' => $snippet_content,
				'is_user_edited'  => $is_user_edited ? 1 : 0,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
		);
		
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Store generated prompt.
	 *
	 * @param string $session_id     Session ID.
	 * @param int    $post_id        Post ID.
	 * @param string $prompt_content Generated prompt content.
	 * 
	 * @return int|false Prompt ID on success, false on failure.
	 */
	public static function store_prompt( $session_id, $post_id, $prompt_content ) {
		global $wpdb;
		
		$table_prompts = $wpdb->prefix . 'gptpg_prompts';
		
		$result = $wpdb->insert(
			$table_prompts,
			array(
				'session_id'     => $session_id,
				'post_id'        => $post_id,
				'prompt_content' => $prompt_content,
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s' )
		);
		
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get post data by session ID.
	 *
	 * @param string $session_id Session ID.
	 * 
	 * @return object|null Post data on success, null on failure.
	 */
	public static function get_post_by_session( $session_id ) {
		global $wpdb;
		
		$table_posts = $wpdb->prefix . 'gptpg_posts';
		
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_posts WHERE session_id = %s ORDER BY id DESC LIMIT 1",
				$session_id
			)
		);
	}

	/**
	 * Get code snippets by session ID.
	 *
	 * @param string $session_id Session ID.
	 * 
	 * @return array Array of code snippets.
	 */
	public static function get_snippets_by_session( $session_id ) {
		global $wpdb;
		
		$table_snippets = $wpdb->prefix . 'gptpg_code_snippets';
		
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_snippets WHERE session_id = %s ORDER BY id ASC",
				$session_id
			)
		);
	}

	/**
	 * Delete snippet by ID.
	 *
	 * @param int $snippet_id Snippet ID.
	 * 
	 * @return bool True on success, false on failure.
	 */
	public static function delete_snippet( $snippet_id ) {
		global $wpdb;
		
		$table_snippets = $wpdb->prefix . 'gptpg_code_snippets';
		
		return $wpdb->delete(
			$table_snippets,
			array( 'id' => $snippet_id ),
			array( '%d' )
		);
	}

	/**
	 * Update snippet.
	 *
	 * @param int    $snippet_id      Snippet ID.
	 * @param string $snippet_url     URL of the code snippet.
	 * @param string $snippet_content Content of the snippet.
	 * 
	 * @return bool True on success, false on failure.
	 */
	public static function update_snippet( $snippet_id, $snippet_url, $snippet_content ) {
		global $wpdb;
		
		$table_snippets = $wpdb->prefix . 'gptpg_code_snippets';
		
		return $wpdb->update(
			$table_snippets,
			array(
				'snippet_url'     => $snippet_url,
				'snippet_content' => $snippet_content,
				'is_user_edited'  => 1,
			),
			array( 'id' => $snippet_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Get generated prompt by session ID.
	 *
	 * @param string $session_id Session ID.
	 * 
	 * @return object|null Prompt data on success, null on failure.
	 */
	public static function get_prompt_by_session( $session_id ) {
		global $wpdb;
		
		$table_prompts = $wpdb->prefix . 'gptpg_prompts';
		
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_prompts WHERE session_id = %s ORDER BY id DESC LIMIT 1",
				$session_id
			)
		);
	}
}

// Initialize the database class.
GPTPG_Database::init();
