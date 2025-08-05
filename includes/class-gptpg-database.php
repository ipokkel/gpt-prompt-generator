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
		// Register activation hook for fresh install.
		register_activation_hook( GPTPG_PLUGIN_FILE, array( __CLASS__, 'fresh_install' ) );
		
		// Check if we need to update the database.
		add_action( 'plugins_loaded', array( __CLASS__, 'check_version' ) );
	}

	/**
	 * Fresh install of the plugin.
	 * This will reset the database to ensure a clean state.
	 */
	public static function fresh_install() {
		// Reset database to ensure clean state
		self::reset_database();
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
	 * Add foreign key constraint if it doesn't exist.
	 *
	 * @param string $table_name The table to add the constraint to.
	 * @param string $column_name The column name for the foreign key.
	 * @param string $ref_table_name The referenced table name.
	 * @param string $ref_column_name The referenced column name.
	 */
	public static function add_foreign_key_constraint( $table_name, $column_name, $ref_table_name, $ref_column_name ) {
		global $wpdb;
		
		// Generate constraint name
		$constraint_name = 'fk_' . str_replace( $wpdb->prefix, '', $table_name ) . '_' . $column_name;
		
		// Check if constraint already exists
		$existing_constraint = $wpdb->get_var( $wpdb->prepare(
			"SELECT CONSTRAINT_NAME 
			 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
			 WHERE TABLE_SCHEMA = %s 
			   AND TABLE_NAME = %s 
			   AND COLUMN_NAME = %s 
			   AND REFERENCED_TABLE_NAME IS NOT NULL",
			DB_NAME,
			$table_name,
			$column_name
		) );
		
		if ( ! $existing_constraint ) {
			// Add the foreign key constraint (table/column names can't be parameterized)
			$sql = sprintf(
				"ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`%s`) ON DELETE CASCADE",
				esc_sql( $table_name ),
				esc_sql( $constraint_name ),
				esc_sql( $column_name ),
				esc_sql( $ref_table_name ),
				esc_sql( $ref_column_name )
			);
			
			$result = $wpdb->query( $sql );
			
			if ( $result === false ) {
				GPTPG_Logger::warning( 
					'Failed to add foreign key constraint: ' . $constraint_name . ' - ' . $wpdb->last_error, 
					'Database'
				);
			} else {
				GPTPG_Logger::info( 
					'Successfully added foreign key constraint: ' . $constraint_name, 
					'Database'
				);
			}
		} else {
			GPTPG_Logger::debug( 
				'Foreign key constraint already exists: ' . $existing_constraint, 
				'Database'
			);
		}
	}

	/**
	 * Check if we need to update the database.
	 */
	public static function check_version() {
		$current_db_version = self::get_db_version();
		
		// If versions don't match, create tables
		if ( $current_db_version !== GPTPG_VERSION ) {
			self::create_tables();
			update_option( 'gptpg_db_version', GPTPG_VERSION );
		}
	}

	/**
	 * Create database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// Table for storing unique posts
		$table_unique_posts = $wpdb->prefix . 'gptpg_unique_posts';
		$sql_unique_posts = "CREATE TABLE $table_unique_posts (
			post_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_url varchar(2083) NOT NULL,
			post_title text NOT NULL,
			post_content longtext NOT NULL,
			post_content_markdown longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY (post_id),
			UNIQUE KEY post_url (post_url(191))
		) $charset_collate;";
		dbDelta( $sql_unique_posts );

		// Table for storing unique code snippets associated with posts
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		$sql_unique_snippets = "CREATE TABLE $table_unique_snippets (
			snippet_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			snippet_url varchar(2083) NOT NULL,
			snippet_type varchar(50) NOT NULL,
			snippet_content longtext,
			is_user_edited tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (snippet_id),
			UNIQUE KEY snippet_url (snippet_url(191)),
			KEY post_id (post_id)
		) $charset_collate;";
		dbDelta( $sql_unique_snippets );
		
		// Add foreign key constraint separately to avoid dbDelta issues
		self::add_foreign_key_constraint( $table_unique_snippets, 'post_id', $table_unique_posts, 'post_id' );

		// Table for storing unique prompts
		$table_unique_prompts = $wpdb->prefix . 'gptpg_unique_prompts';
		$sql_unique_prompts = "CREATE TABLE $table_unique_prompts (
			prompt_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			prompt_content longtext NOT NULL,
			prompt_hash varchar(32) GENERATED ALWAYS AS (MD5(prompt_content)) STORED,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (prompt_id),
			UNIQUE KEY prompt_hash (prompt_hash),
			KEY post_id (post_id)
		) $charset_collate;";
		dbDelta( $sql_unique_prompts );
		
		// Add foreign key constraint for prompts table
		self::add_foreign_key_constraint( $table_unique_prompts, 'post_id', $table_unique_posts, 'post_id' );

		// Update database version
		update_option( 'gptpg_db_version', GPTPG_VERSION );

		// Schedule cleanup of expired data
		if ( ! wp_next_scheduled( 'gptpg_cleanup_expired_data' ) ) {
			wp_schedule_event( time(), 'daily', 'gptpg_cleanup_expired_data' );
		}
	}

	/**
	 * Cleanup expired data from database.
	 */
	public static function cleanup_expired_data() {
		global $wpdb;
		
		// Get current time
		$current_time = current_time( 'mysql', true );
		
		// Delete expired posts and related data
		$table_unique_posts = $wpdb->prefix . 'gptpg_unique_posts';
		$expired_posts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM $table_unique_posts WHERE expires_at < %s",
				$current_time
			)
		);
		
		if ( ! empty( $expired_posts ) ) {
			// Note: Snippets will be auto-deleted due to FOREIGN KEY CASCADE
			// Delete expired posts
			$wpdb->query( 
				"DELETE FROM $table_unique_posts WHERE post_id IN (" . implode( ',', array_map( 'intval', $expired_posts ) ) . ")"
			);
		}
	}

	/**
	 * Check if a post with the given URL already exists.
	 *
	 * @param string $post_url URL to check
	 * @return int|false Post ID if exists, false otherwise
	 */
	public static function post_exists( $post_url ) {
		global $wpdb;
		$table_unique_posts = $wpdb->prefix . 'gptpg_unique_posts';
		
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $table_unique_posts WHERE post_url = %s LIMIT 1",
				$post_url
			)
		);
		
		return $post_id ? (int) $post_id : false;
	}
	
	/**
	 * Get all data associated with a post ID, including markdown, snippets, and prompts.
	 *
	 * @param int $post_id Post ID to get data for
	 * @return array Associative array with post data, including has_markdown, has_snippets, has_prompt flags
	 */
	public static function get_post_data_by_id( $post_id ) {
		global $wpdb;
		$table_unique_posts = $wpdb->prefix . 'gptpg_unique_posts';
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		$table_unique_prompts = $wpdb->prefix . 'gptpg_unique_prompts';
		
		// Get the post details
		$post_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_unique_posts WHERE post_id = %d LIMIT 1",
				$post_id
			),
			ARRAY_A
		);
		
		if (!$post_data) {
			return array('error' => 'Post not found');
		}
		
		// Initialize result array
		$result = array(
			'post_id' => $post_id,
			'post_title' => $post_data['post_title'],
			'post_url' => $post_data['post_url'],
			'has_markdown' => !empty($post_data['post_content_markdown']),
			'markdown_content' => $post_data['post_content_markdown'],
			'has_snippets' => false,
			'snippets' => array(),
			'has_prompt' => false,
			'prompt' => '',
		);
		
		// Get snippets directly associated with this post
		$snippets = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_unique_snippets WHERE post_id = %d ORDER BY created_at DESC",
				$post_id
			),
			ARRAY_A
		);
		
		if ($snippets && count($snippets) > 0) {
			$result['has_snippets'] = true;
			$result['snippets'] = array_map(function($snippet) {
				return array(
					'id' => $snippet['snippet_id'],
					'url' => $snippet['snippet_url'],
					'type' => $snippet['snippet_type'],
					'content' => $snippet['snippet_content'],
					'is_user_edited' => $snippet['is_user_edited']
				);
			}, $snippets);
		}
		
		// Search for prompts by post title (since we don't store direct post-prompt association)
		$post_title = $post_data['post_title'];
		$like_title = '%' . $wpdb->esc_like( $post_title ) . '%';
		$prompt_data = $wpdb->get_row(
			$wpdb->prepare( 
				"SELECT * FROM $table_unique_prompts WHERE prompt_content LIKE %s ORDER BY created_at DESC LIMIT 1", 
				$like_title 
			),
			ARRAY_A
		);
		
		if ( $prompt_data ) {
			$result['has_prompt'] = true;
			$result['prompt'] = $prompt_data['prompt_content'];
		}
		
		return $result;
	}

	/**
	 * Store fetched post data.
	 *
	 * @param string $post_url      URL of the fetched post.
	 * @param string $post_title    Title of the fetched post.
	 * @param string $post_content  HTML content of the fetched post.
	 * @param string $post_content_markdown Markdown version of the post content.
	 * @param int    $expires_in    Seconds until expiration (default 60 minutes).
	 * 
	 * @return array Array with post_id and is_duplicate flag.
	 */
	public static function store_post( $post_url, $post_title, $post_content, $post_content_markdown, $expires_in = 3600 ) {
		global $wpdb;
		
		$table_unique_posts = $wpdb->prefix . 'gptpg_unique_posts';
		
		$current_time = current_time( 'mysql', true );
		$expires_time = gmdate( 'Y-m-d H:i:s', time() + $expires_in );
		
		// Check if post already exists by URL
		$existing_post_id = self::post_exists( $post_url );
		$is_duplicate = false;
		
		if ( $existing_post_id ) {
			// Post exists, update it if needed
			$wpdb->update(
				$table_unique_posts,
				array(
					'post_title' => $post_title,
					'post_content' => $post_content,
					'post_content_markdown' => $post_content_markdown,
					'updated_at' => $current_time,
					'expires_at' => $expires_time,
				),
				array( 'post_id' => $existing_post_id ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			
			$post_id = $existing_post_id;
			$is_duplicate = true;
		} else {
			// Create new post
			$wpdb->insert(
				$table_unique_posts,
				array(
					'post_url' => $post_url,
					'post_title' => $post_title,
					'post_content' => $post_content,
					'post_content_markdown' => $post_content_markdown,
					'created_at' => $current_time,
					'updated_at' => $current_time,
					'expires_at' => $expires_time,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			
			$post_id = $wpdb->insert_id;
		}
		
		return array(
			'post_id' => $post_id,
			'is_duplicate' => $is_duplicate
		);
	}

	/**
	 * Check if a snippet with the given URL already exists.
	 *
	 * @param string $snippet_url URL to check
	 * @return int|false Snippet ID if exists, false otherwise
	 */
	public static function snippet_exists( $snippet_url ) {
		global $wpdb;
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		
		$snippet_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT snippet_id FROM $table_unique_snippets WHERE snippet_url = %s LIMIT 1",
				$snippet_url
			)
		);
		
		return $snippet_id ? (int) $snippet_id : false;
	}

	/**
	 * Store code snippet.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $snippet_url     URL of the code snippet.
	 * @param string $snippet_type    Type of snippet (github, gist, raw).
	 * @param string $snippet_content Optional content of the snippet.
	 * @param bool   $is_user_edited  Whether this snippet was edited by the user.
	 * 
	 * @return array Array with snippet_id and is_duplicate flag.
	 */
	public static function store_snippet( $post_id, $snippet_url, $snippet_type, $snippet_content = '', $is_user_edited = false ) {
		global $wpdb;
		
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		
		$current_time = current_time( 'mysql', true );
		
		// Check if snippet already exists by URL
		$existing_snippet_id = self::snippet_exists( $snippet_url );
		$is_duplicate = false;
		
		if ( $existing_snippet_id ) {
			// Snippet exists, update it if needed and if user edited
			if ( $is_user_edited ) {
				$wpdb->update(
					$table_unique_snippets,
					array(
						'snippet_content' => $snippet_content,
						'is_user_edited' => $is_user_edited ? 1 : 0,
						'updated_at' => $current_time,
					),
					array( 'snippet_id' => $existing_snippet_id ),
					array( '%s', '%d', '%s' ),
					array( '%d' )
				);
			}
			
			$snippet_id = $existing_snippet_id;
			$is_duplicate = true;
		} else {
			// Create new snippet with direct post_id association
			$wpdb->insert(
				$table_unique_snippets,
				array(
					'post_id' => $post_id,
					'snippet_url' => $snippet_url,
					'snippet_type' => $snippet_type,
					'snippet_content' => $snippet_content,
					'is_user_edited' => $is_user_edited ? 1 : 0,
					'created_at' => $current_time,
					'updated_at' => $current_time,
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
			
			$snippet_id = $wpdb->insert_id;
		}
		
		return array(
			'snippet_id' => $snippet_id,
			'is_duplicate' => $is_duplicate
		);
	}

	/**
	 * Check if a prompt with the given content already exists.
	 *
	 * @param string $prompt_content Prompt content to check
	 * @return int|false Prompt ID if exists, false otherwise
	 */
	public static function prompt_exists( $prompt_content ) {
		global $wpdb;
		$table_unique_prompts = $wpdb->prefix . 'gptpg_unique_prompts';
		
		$prompt_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT prompt_id FROM $table_unique_prompts WHERE MD5(prompt_content) = MD5(%s) LIMIT 1",
				$prompt_content
			)
		);
		
		return $prompt_id ? (int) $prompt_id : false;
	}

	/**
	 * Store a generated prompt.
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $prompt_content Generated prompt content.
	 * 
	 * @return array Array with prompt_id and is_duplicate flag.
	 */
	public static function store_prompt( $post_id, $prompt_content ) {
		global $wpdb;
		
		$table_unique_prompts = $wpdb->prefix . 'gptpg_unique_prompts';
		
		$current_time = current_time( 'mysql', true );
		
		// Check if prompt already exists by content hash
		$existing_prompt_id = self::prompt_exists( $prompt_content );
		$is_duplicate = false;
		
		if ( $existing_prompt_id ) {
			// Prompt exists, just get the ID
			$prompt_id = $existing_prompt_id;
			$is_duplicate = true;
		} else {
			// Create new prompt associated with post_id
			$wpdb->insert(
				$table_unique_prompts,
				array(
					'post_id' => $post_id,
					'prompt_content' => $prompt_content,
					'created_at' => $current_time,
					'updated_at' => $current_time,
				),
				array( '%d', '%s', '%s', '%s' )
			);
			
			$prompt_id = $wpdb->insert_id;
		}
		
		return array(
			'prompt_id' => $prompt_id,
			'is_duplicate' => $is_duplicate
		);
	}



	/**
	 * Get snippets associated with a specific post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of snippet objects.
	 */
	public static function get_snippets_by_post_id( $post_id ) {
		global $wpdb;
		
		GPTPG_Logger::debug("get_snippets_by_post_id called with post_id: {$post_id}", 'Database');
		
		// Query snippets directly by post_id from the unique_snippets table
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		
		$query = $wpdb->prepare(
			"SELECT * FROM {$table_unique_snippets} WHERE post_id = %d ORDER BY created_at DESC",
			$post_id
		);
		
		GPTPG_Logger::debug("Using direct post_id query: {$query}", 'Database');
		$results = $wpdb->get_results( $query );
		GPTPG_Logger::debug("Direct query returned " . count($results) . " results", 'Database');
		
		if ( !empty( $results ) ) {
			GPTPG_Logger::info("Found " . count($results) . " snippets for post ID {$post_id}", 'Database');
			return $results;
		}
		
		GPTPG_Logger::info("No snippets found for post ID {$post_id}", 'Database');
		return array();
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
		
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		
		// Delete the snippet directly
		return $wpdb->delete(
			$table_unique_snippets,
			array( 'snippet_id' => $snippet_id ),
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
		
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		$current_time = current_time( 'mysql', true );
		
		// Update the snippet content
		$snippet_updated = $wpdb->update(
			$table_unique_snippets,
			array(
				'snippet_url'     => $snippet_url,
				'snippet_content' => $snippet_content,
				'updated_at'      => $current_time,
			),
			array( 'snippet_id' => $snippet_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		
		// Update the is_user_edited flag directly in the snippet
		$wpdb->update(
			$table_unique_snippets,
			array(
				'is_user_edited' => 1,
			),
			array( 'snippet_id' => $snippet_id ),
			array( '%d' ),
			array( '%d' )
		);
		
		return ($snippet_updated !== false);
	}

	/**
	 * Reset all plugin data and tables.
	 * This will drop all plugin tables and recreate them, effectively making it a fresh install.
	 */
	public static function reset_database() {
		global $wpdb;
		
		// List of all current plugin tables (order matters: drop child tables before parent tables)
		$tables = array(
			'gptpg_unique_snippets', // Child table - has foreign key to posts
			'gptpg_unique_prompts',  // Child table - has foreign key to posts
			'gptpg_unique_posts'     // Parent table - must be dropped last
		);
		
		// Drop each table
		foreach ($tables as $table) {
			$table_name = $wpdb->prefix . $table;
			$wpdb->query("DROP TABLE IF EXISTS `$table_name`");
		}
		
		// Reset database version option
		delete_option('gptpg_db_version');
		
		// Recreate tables
		self::create_tables();
	}


}

// Initialize the database class.
GPTPG_Database::init();
