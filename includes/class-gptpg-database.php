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
			
			if ( version_compare( $current_db_version, '0.0.5', '<' ) ) {
				self::upgrade_0_0_5();
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
	 * Upgrade database to version 0.0.5
	 * - Implements normalized database schema
	 * - Creates new tables for normalized data structure
	 * - Migrates existing data to new tables
	 */
	private static function upgrade_0_0_5() {
		global $wpdb;
		
		// First, create the normalized tables
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
		
		// Table for storing unique code snippets
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		$sql_unique_snippets = "CREATE TABLE $table_unique_snippets (
			snippet_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			snippet_url varchar(2083) NOT NULL,
			snippet_type varchar(50) NOT NULL,
			snippet_content longtext,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (snippet_id),
			UNIQUE KEY snippet_url (snippet_url(191))
		) $charset_collate;";
		dbDelta( $sql_unique_snippets );
		
		// Table for storing unique prompts
		$table_unique_prompts = $wpdb->prefix . 'gptpg_unique_prompts';
		$sql_unique_prompts = "CREATE TABLE $table_unique_prompts (
			prompt_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			prompt_content longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (prompt_id),
			UNIQUE KEY prompt_hash (MD5(prompt_content))
		) $charset_collate;";
		dbDelta( $sql_unique_prompts );
		
		// Table for sessions/processes that link everything together
		$table_sessions = $wpdb->prefix . 'gptpg_sessions';
		$sql_sessions = "CREATE TABLE $table_sessions (
			session_id varchar(255) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			post_id bigint(20) unsigned DEFAULT NULL,
			prompt_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			status varchar(50) DEFAULT 'pending',
			PRIMARY KEY (session_id),
			KEY user_id (user_id),
			KEY post_id (post_id),
			KEY prompt_id (prompt_id)
		) $charset_collate;";
		dbDelta( $sql_sessions );
		
		// Table for session to snippet mappings (many-to-many)
		$table_session_snippets = $wpdb->prefix . 'gptpg_session_snippets';
		$sql_session_snippets = "CREATE TABLE $table_session_snippets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(255) NOT NULL,
			snippet_id bigint(20) unsigned NOT NULL,
			is_user_edited tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY session_snippet (session_id, snippet_id),
			KEY session_id (session_id),
			KEY snippet_id (snippet_id)
		) $charset_collate;";
		dbDelta( $sql_session_snippets );
		
		// Now migrate existing data to the new tables
		self::migrate_data_to_normalized_schema();
	}

	/**
	 * Migrate existing data to the normalized schema.
	 */
	private static function migrate_data_to_normalized_schema() {
		global $wpdb;
		
		// Define our table names
		$table_posts = $wpdb->prefix . 'gptpg_posts';
		$table_snippets = $wpdb->prefix . 'gptpg_code_snippets';
		$table_prompts = $wpdb->prefix . 'gptpg_prompts';
		
		$table_unique_posts = $wpdb->prefix . 'gptpg_unique_posts';
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		$table_unique_prompts = $wpdb->prefix . 'gptpg_unique_prompts';
		$table_sessions = $wpdb->prefix . 'gptpg_sessions';
		$table_session_snippets = $wpdb->prefix . 'gptpg_session_snippets';
		
		// Step 1: Migrate unique posts
		$wpdb->query("INSERT IGNORE INTO $table_unique_posts 
			(post_url, post_title, post_content, post_content_markdown, created_at, updated_at, expires_at)
			SELECT DISTINCT 
				post_url, 
				post_title, 
				post_content, 
				post_content_markdown, 
				created_at, 
				created_at as updated_at,
				expires_at
			FROM $table_posts");
		
		// Step 2: Migrate unique snippets
		$wpdb->query("INSERT IGNORE INTO $table_unique_snippets 
			(snippet_url, snippet_type, snippet_content, created_at, updated_at)
			SELECT DISTINCT 
				snippet_url, 
				snippet_type, 
				snippet_content,
				created_at,
				created_at as updated_at
			FROM $table_snippets");
		
		// Step 3: Migrate unique prompts
		$wpdb->query("INSERT IGNORE INTO $table_unique_prompts 
			(prompt_content, created_at, updated_at)
			SELECT DISTINCT 
				prompt_content,
				created_at,
				created_at as updated_at
			FROM $table_prompts");
		
		// Step 4: Create sessions and link them to posts and prompts
		$wpdb->query("INSERT INTO $table_sessions 
			(session_id, user_id, post_id, prompt_id, created_at, status)
			SELECT DISTINCT
				p.session_id,
				p.user_id,
				up.post_id,
				upr.prompt_id,
				p.created_at,
				'complete' as status
			FROM $table_posts p
			JOIN $table_unique_posts up ON p.post_url = up.post_url
			LEFT JOIN $table_prompts pr ON p.session_id = pr.session_id
			LEFT JOIN $table_unique_prompts upr ON pr.prompt_content = upr.prompt_content");
		
		// Step 5: Create session-snippet mappings
		$wpdb->query("INSERT INTO $table_session_snippets 
			(session_id, snippet_id, is_user_edited, created_at)
			SELECT DISTINCT
				s.session_id,
				us.snippet_id,
				cs.is_user_edited,
				cs.created_at
			FROM $table_snippets cs
			JOIN $table_unique_snippets us ON cs.snippet_url = us.snippet_url
			JOIN $table_sessions s ON cs.session_id = s.session_id");
		
		// Log successful migration
		error_log('GPTPG: Data successfully migrated to normalized schema.');
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
			user_id bigint(20) unsigned DEFAULT NULL,
			session_id varchar(255) NOT NULL,
			post_url varchar(2083) NOT NULL,
			post_title text NOT NULL,
			post_content longtext NOT NULL,
			post_content_markdown longtext NOT NULL,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY expires_at (expires_at),
			KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql_posts );

		// Table for storing code snippets.
		$table_snippets = $wpdb->prefix . 'gptpg_code_snippets';
		$sql_snippets = "CREATE TABLE $table_snippets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			session_id varchar(255) NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			snippet_url varchar(2083) NOT NULL,
			snippet_type varchar(50) NOT NULL,
			snippet_content longtext,
			is_user_edited tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY post_id (post_id),
			KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql_snippets );

		// Table for storing generated prompts.
		$table_prompts = $wpdb->prefix . 'gptpg_prompts';
		$sql_prompts = "CREATE TABLE $table_prompts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			session_id varchar(255) NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			prompt_content longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY post_id (post_id),
			KEY user_id (user_id)
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
		$table_session_snippets = $wpdb->prefix . 'gptpg_session_snippets';
		$table_sessions = $wpdb->prefix . 'gptpg_sessions';
		$table_unique_prompts = $wpdb->prefix . 'gptpg_unique_prompts';
		$table_session_prompts = $wpdb->prefix . 'gptpg_session_prompts';
		
		// Debug log
		$debug = array('post_id' => $post_id);
		
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
		
		// Get all session IDs for this post, sorted by most recent first
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_id, created_at FROM $table_sessions WHERE post_id = %d ORDER BY created_at DESC",
				$post_id
			),
			ARRAY_A
		);
		
		// Debug log
		$debug['sessions_found'] = count($sessions);
		
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
			'debug' => $debug,
		);
		
		// If we have sessions, try to get snippets and prompts from all sessions
		if (!empty($sessions)) {
			// Get all session IDs for IN clause
			$session_ids = array_map(function($session) { return $session['session_id']; }, $sessions);
			
			// Format session IDs for SQL IN clause
			$session_ids_placeholders = implode(',', array_fill(0, count($session_ids), '%s'));
			
			// Get snippets associated with any session for this post
			$query = $wpdb->prepare(
				"SELECT DISTINCT s.* FROM $table_unique_snippets s 
				JOIN $table_session_snippets ss ON s.snippet_id = ss.snippet_id 
				WHERE ss.session_id IN ($session_ids_placeholders)",
				...$session_ids
			);
			
			$debug['snippets_query'] = $query;
			
			$snippets = $wpdb->get_results($query, ARRAY_A);
			$debug['snippets_found'] = $snippets ? count($snippets) : 0;
			
			if ($snippets && count($snippets) > 0) {
				$result['has_snippets'] = true;
				$result['snippets'] = array_map(function($snippet) {
					return array(
						'id' => $snippet['snippet_id'],
						'url' => $snippet['snippet_url'],
						'type' => $snippet['snippet_type'],
						'content' => $snippet['snippet_content']
					);
				}, $snippets);
			}
			
			// Check all session_prompts for any association
			$prompt_check_query = $wpdb->prepare(
				"SELECT COUNT(*) FROM $table_session_prompts WHERE session_id IN ($session_ids_placeholders)",
				...$session_ids
			);
			$debug['prompt_associations_count'] = $wpdb->get_var($prompt_check_query);
			
			// Get prompt associated with any session for this post
			$query = $wpdb->prepare(
				"SELECT DISTINCT p.* FROM $table_unique_prompts p 
				JOIN $table_session_prompts sp ON p.prompt_id = sp.prompt_id 
				WHERE sp.session_id IN ($session_ids_placeholders) LIMIT 1",
				...$session_ids
			);
			
			$debug['prompt_query'] = $query;
			
			$prompt_data = $wpdb->get_row($query, ARRAY_A);
			$debug['prompt_found'] = !empty($prompt_data);
			
			// The direct post ID lookup was removed as prompt_post_id column doesn't exist in schema
			$direct_prompt_data = null;
			$debug['direct_prompt_query'] = 'Skipped - prompt_post_id column not in schema';
			$debug['direct_prompt_found'] = false;
			
			if ($prompt_data) {
				$result['has_prompt'] = true;
				$result['prompt'] = $prompt_data['prompt_content'];
				$debug['prompt_source'] = 'session_linked';
			} elseif ($direct_prompt_data) {
				// If we found a prompt directly linked to the post ID
				$result['has_prompt'] = true;
				$result['prompt'] = $direct_prompt_data['prompt_content'];
				$debug['prompt_source'] = 'direct_post_linked';
			}
		}
		
		// Additional debugging for prompts
		// 1. Check if any prompts exist in the system
		$all_prompts_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_unique_prompts" );
		$debug['all_prompts_count'] = $all_prompts_count;
		
		// 2. Show a sample prompt if any exist (helpful to understand data structure)
		if ( $all_prompts_count > 0 ) {
			$sample_prompt = $wpdb->get_row( "SELECT * FROM $table_unique_prompts LIMIT 1", ARRAY_A );
			if ( $sample_prompt ) {
				$debug['sample_prompt_id'] = $sample_prompt['prompt_id'];
				$debug['sample_prompt_has_post_id'] = isset( $sample_prompt['prompt_post_id'] ) ? 'yes' : 'no';
				
				// Check if column exists in table
				$check_column = $wpdb->get_results( "SHOW COLUMNS FROM $table_unique_prompts LIKE 'prompt_post_id'", ARRAY_A );
				$debug['prompt_post_id_column_exists'] = !empty( $check_column ) ? 'yes' : 'no';
			}
		}
		
		// 3. Search for prompts by post title as a last resort
		$post_title = $post_data['post_title'];
		$like_title = '%' . $wpdb->esc_like( $post_title ) . '%';
		$prompts_with_title = $wpdb->get_results( 
			$wpdb->prepare( "SELECT prompt_id FROM $table_unique_prompts WHERE prompt_content LIKE %s LIMIT 5", $like_title ),
			ARRAY_A
		);
		$debug['prompts_with_title_count'] = count( $prompts_with_title );
		$debug['prompts_with_title_ids'] = array_column( $prompts_with_title, 'prompt_id' );
		
		// If we found prompts by title search, use the first one
		if ( !empty( $prompts_with_title ) ) {
			$title_prompt_id = $prompts_with_title[0]['prompt_id'];
			$title_prompt = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM $table_unique_prompts WHERE prompt_id = %d", $title_prompt_id ),
				ARRAY_A
			);
			
			if ( $title_prompt ) {
				$result['has_prompt'] = true;
				$result['prompt'] = $title_prompt['prompt_content'];
				$debug['prompt_source'] = 'title_search';
			}
		}
		
		// Add final debug info
		$result['debug'] = $debug;
		
		return $result;
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
	 * @return array Array with post_id and is_duplicate flag.
	 */
	public static function store_post( $session_id, $post_url, $post_title, $post_content, $post_content_markdown, $expires_in = 3600 ) {
		global $wpdb;
		
		$table_unique_posts = $wpdb->prefix . 'gptpg_unique_posts';
		$table_sessions = $wpdb->prefix . 'gptpg_sessions';
		
		// Get current user ID if logged in
		$current_user_id = get_current_user_id();
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
		
		// Now create or update the session record
		$session_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_sessions WHERE session_id = %s",
				$session_id
			)
		);
		
		if ( $session_exists ) {
			// Update existing session
			$wpdb->update(
				$table_sessions,
				array(
					'post_id' => $post_id,
				),
				array( 'session_id' => $session_id ),
				array( '%d' ),
				array( '%s' )
			);
		} else {
			// Create new session
			$wpdb->insert(
				$table_sessions,
				array(
					'session_id' => $session_id,
					'user_id' => $current_user_id ? $current_user_id : NULL,
					'post_id' => $post_id,
					'created_at' => $current_time,
					'status' => 'pending',
				),
				array( '%s', '%d', '%d', '%s', '%s' )
			);
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
	 * @param string $session_id      Session ID.
	 * @param int    $post_id         Post ID.
	 * @param string $snippet_url     URL of the code snippet.
	 * @param string $snippet_type    Type of snippet (github, gist, raw).
	 * @param string $snippet_content Optional content of the snippet.
	 * @param bool   $is_user_edited  Whether this snippet was edited by the user.
	 * 
	 * @return array Array with snippet_id and is_duplicate flag.
	 */
	public static function store_snippet( $session_id, $post_id, $snippet_url, $snippet_type, $snippet_content = '', $is_user_edited = false ) {
		global $wpdb;
		
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		$table_session_snippets = $wpdb->prefix . 'gptpg_session_snippets';
		
		// Get current user ID if logged in
		$current_user_id = get_current_user_id();
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
						'updated_at' => $current_time,
					),
					array( 'snippet_id' => $existing_snippet_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
			
			$snippet_id = $existing_snippet_id;
			$is_duplicate = true;
		} else {
			// Create new snippet
			$wpdb->insert(
				$table_unique_snippets,
				array(
					'snippet_url' => $snippet_url,
					'snippet_type' => $snippet_type,
					'snippet_content' => $snippet_content,
					'created_at' => $current_time,
					'updated_at' => $current_time,
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);
			
			$snippet_id = $wpdb->insert_id;
		}
		
		// Now create or update the session-snippet mapping
		$mapping_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_session_snippets WHERE session_id = %s AND snippet_id = %d",
				$session_id, $snippet_id
			)
		);
		
		if ( $mapping_exists ) {
			// Update existing mapping if needed
			if ( $is_user_edited ) {
				$wpdb->update(
					$table_session_snippets,
					array(
						'is_user_edited' => 1,
					),
					array( 
						'session_id' => $session_id,
						'snippet_id' => $snippet_id
					),
					array( '%d' ),
					array( '%s', '%d' )
				);
			}
		} else {
			// Create new mapping
			$wpdb->insert(
				$table_session_snippets,
				array(
					'session_id' => $session_id,
					'snippet_id' => $snippet_id,
					'is_user_edited' => $is_user_edited ? 1 : 0,
					'created_at' => $current_time,
				),
				array( '%s', '%d', '%d', '%s' )
			);
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
	 * Store generated prompt.
	 *
	 * @param string $session_id     Session ID.
	 * @param int    $post_id        Post ID.
	 * @param string $prompt_content Generated prompt content.
	 * 
	 * @return array Array with prompt_id and is_duplicate flag.
	 */
	public static function store_prompt( $session_id, $post_id, $prompt_content ) {
		global $wpdb;
		
		$table_unique_prompts = $wpdb->prefix . 'gptpg_unique_prompts';
		$table_sessions = $wpdb->prefix . 'gptpg_sessions';
		
		// Get current user ID if logged in
		$current_user_id = get_current_user_id();
		$current_time = current_time( 'mysql', true );
		
		// Check if prompt already exists by content hash
		$existing_prompt_id = self::prompt_exists( $prompt_content );
		$is_duplicate = false;
		
		if ( $existing_prompt_id ) {
			// Prompt exists, just get the ID
			$prompt_id = $existing_prompt_id;
			$is_duplicate = true;
		} else {
			// Create new prompt
			$wpdb->insert(
				$table_unique_prompts,
				array(
					'prompt_content' => $prompt_content,
					'created_at' => $current_time,
					'updated_at' => $current_time,
				),
				array( '%s', '%s', '%s' )
			);
			
			$prompt_id = $wpdb->insert_id;
		}
		
		// Now update the session record
		$wpdb->update(
			$table_sessions,
			array(
				'prompt_id' => $prompt_id,
				'status' => 'complete',
			),
			array( 'session_id' => $session_id ),
			array( '%d', '%s' ),
			array( '%s' )
		);
		
		return array(
			'prompt_id' => $prompt_id,
			'is_duplicate' => $is_duplicate
		);
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
		
		$table_sessions = $wpdb->prefix . 'gptpg_sessions';
		$table_unique_posts = $wpdb->prefix . 'gptpg_unique_posts';
		
		$query = "SELECT up.* 
			FROM $table_unique_posts up 
			JOIN $table_sessions s ON up.post_id = s.post_id 
			WHERE s.session_id = %s 
			LIMIT 1";
		
		return $wpdb->get_row(
			$wpdb->prepare($query, $session_id)
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
		
		$table_session_snippets = $wpdb->prefix . 'gptpg_session_snippets';
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		
		$query = "SELECT us.*, ss.is_user_edited 
			FROM $table_unique_snippets us 
			JOIN $table_session_snippets ss ON us.snippet_id = ss.snippet_id 
			WHERE ss.session_id = %s 
			ORDER BY ss.id ASC";
		
		return $wpdb->get_results(
			$wpdb->prepare($query, $session_id)
		);
	}

	/**
	 * Delete snippet by ID from a session.
	 *
	 * @param int    $snippet_id Snippet ID.
	 * @param string $session_id Session ID.
	 * 
	 * @return bool True on success, false on failure.
	 */
	public static function delete_snippet( $snippet_id, $session_id ) {
		global $wpdb;
		
		$table_session_snippets = $wpdb->prefix . 'gptpg_session_snippets';
		
		// We only remove the association between the session and the snippet,
		// not the snippet itself as it might be used by other sessions
		return $wpdb->delete(
			$table_session_snippets,
			array( 
				'snippet_id' => $snippet_id,
				'session_id' => $session_id
			),
			array( '%d', '%s' )
		);
	}

	/**
	 * Update snippet.
	 *
	 * @param int    $snippet_id      Snippet ID.
	 * @param string $session_id      Session ID.
	 * @param string $snippet_url     URL of the code snippet.
	 * @param string $snippet_content Content of the snippet.
	 * 
	 * @return bool True on success, false on failure.
	 */
	public static function update_snippet( $snippet_id, $session_id, $snippet_url, $snippet_content ) {
		global $wpdb;
		
		$table_unique_snippets = $wpdb->prefix . 'gptpg_unique_snippets';
		$table_session_snippets = $wpdb->prefix . 'gptpg_session_snippets';
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
		
		// Mark as user edited in the session-snippets mapping
		$mapping_updated = $wpdb->update(
			$table_session_snippets,
			array(
				'is_user_edited' => 1,
			),
			array( 
				'session_id' => $session_id,
				'snippet_id' => $snippet_id 
			),
			array( '%d' ),
			array( '%s', '%d' )
		);
		
		return ($snippet_updated !== false && $mapping_updated !== false);
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
		
		$table_sessions = $wpdb->prefix . 'gptpg_sessions';
		$table_unique_prompts = $wpdb->prefix . 'gptpg_unique_prompts';
		
		$query = "SELECT up.* 
			FROM $table_unique_prompts up 
			JOIN $table_sessions s ON up.prompt_id = s.prompt_id 
			WHERE s.session_id = %s 
			LIMIT 1";
		
		return $wpdb->get_row(
			$wpdb->prepare($query, $session_id)
		);
	}
}

// Initialize the database class.
GPTPG_Database::init();
