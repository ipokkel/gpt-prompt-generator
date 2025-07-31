<?php
/**
 * GPTPG Form Handler
 *
 * Handles form submissions and AJAX requests for the front-end form.
 *
 * @package GPT_Prompt_Generator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GPTPG_Form_Handler class
 *
 * Manages form submissions and AJAX endpoints for the front-end form.
 */
class GPTPG_Form_Handler {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		// Register AJAX handlers
		add_action( 'wp_ajax_gptpg_store_markdown', array( __CLASS__, 'ajax_store_markdown' ) );
		add_action( 'wp_ajax_nopriv_gptpg_store_markdown', array( __CLASS__, 'ajax_store_markdown' ) );
		add_action( 'wp_ajax_gptpg_fetch_post', array( __CLASS__, 'ajax_fetch_post' ) );
		add_action( 'wp_ajax_nopriv_gptpg_fetch_post', array( __CLASS__, 'ajax_fetch_post' ) );
		add_action( 'wp_ajax_gptpg_process_markdown', array( __CLASS__, 'process_markdown' ) );
		add_action( 'wp_ajax_nopriv_gptpg_process_markdown', array( __CLASS__, 'process_markdown' ) );
		add_action( 'wp_ajax_gptpg_process_snippets', array( __CLASS__, 'ajax_process_snippets' ) );
		add_action( 'wp_ajax_nopriv_gptpg_process_snippets', array( __CLASS__, 'ajax_process_snippets' ) );
		add_action( 'wp_ajax_gptpg_generate_prompt', array( __CLASS__, 'ajax_generate_prompt' ) );
		add_action( 'wp_ajax_nopriv_gptpg_generate_prompt', array( __CLASS__, 'ajax_generate_prompt' ) );
		add_action( 'wp_ajax_gptpg_reset_form', array( __CLASS__, 'reset_form' ) );
		add_action( 'wp_ajax_nopriv_gptpg_reset_form', array( __CLASS__, 'reset_form' ) );
		add_action( 'wp_ajax_gptpg_verify_session', array( __CLASS__, 'verify_session' ) );
		add_action( 'wp_ajax_nopriv_gptpg_verify_session', array( __CLASS__, 'verify_session' ) );
		add_action( 'wp_ajax_gptpg_get_fresh_nonce', array( __CLASS__, 'get_fresh_nonce' ) );
		add_action( 'wp_ajax_nopriv_gptpg_get_fresh_nonce', array( __CLASS__, 'get_fresh_nonce' ) );

		// Check if GPTPG_Database class exists and initialize it
		if ( class_exists( 'GPTPG_Database' ) ) {
			GPTPG_Database::init();
		}
	}



	/**
	 * AJAX handler for fetching a post by URL.
	 */
	public static function ajax_fetch_post() {
		// Check nonce (enhanced for anonymous users)
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed - nonce missing.', 'gpt-prompt-generator' ) ) );
		}
		
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
		

		
		if ( ! wp_verify_nonce( $nonce, 'gptpg-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed - invalid nonce.', 'gpt-prompt-generator' ) ) );
		}

		// Note: Login restriction removed - plugin now accessible to all users

		// Check if post URL was provided
		if ( ! isset( $_POST['post_url'] ) || empty( $_POST['post_url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid post URL.', 'gpt-prompt-generator' ) ) );
		}

		// Get post URL
		$post_url = esc_url_raw( wp_unslash( $_POST['post_url'] ) );

		// Fetch post content
		$post_data = self::fetch_post_content( $post_url );

		// Check for errors
		if ( is_wp_error( $post_data ) ) {
			wp_send_json_error( array( 'message' => $post_data->get_error_message() ) );
		}

		// Convert HTML to Markdown
		if ( ! class_exists( 'League\HTMLToMarkdown\HtmlConverter' ) ) {
			require_once GPTPG_PLUGIN_DIR . 'vendor/autoload.php';
		}

		// Enhanced HTML to Markdown conversion
		$post_content_markdown = self::convert_html_to_markdown( $post_data['content'] );

		// Store post data in database
		$expiry_time = intval( get_option( 'gptpg_expiry_time', 3600 ) );
		$store_result = GPTPG_Database::store_post(
			$post_url,
			$post_data['title'],
			$post_data['content'],
			$post_content_markdown,
			$expiry_time
		);
		


		// Check if we got a valid result array
		if ( ! isset( $store_result['post_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to store post data.', 'gpt-prompt-generator' ) ) );
		}
		
		$post_id = $store_result['post_id'];
		$is_duplicate = isset( $store_result['is_duplicate'] ) ? $store_result['is_duplicate'] : false;
		
		// If this is a duplicate post, fetch associated data
		$existing_data = array();
		if ( $is_duplicate ) {
			$existing_data = GPTPG_Database::get_post_data_by_id( $post_id );
		}

		// Extract GitHub/Gist links from post content
		$github_links = GPTPG_GitHub_Handler::extract_github_urls( $post_data['content'] );

		// Store code snippets in database
		$snippet_results = [];
		foreach ( $github_links as $link ) {
			$snippet_type = GPTPG_GitHub_Handler::get_github_url_type( $link );
			if ( $snippet_type ) {
				$snippet_result = GPTPG_Database::store_snippet(
					$post_id,
					$link,
					$snippet_type
				);
				
				if (isset($snippet_result['is_duplicate']) && $snippet_result['is_duplicate']) {
					$snippet_results[] = [
						'url' => $link,
						'is_duplicate' => true
					];
				}
			}
		}

		// Prepare response data
		$response_data = array(
			'post_id'      => $post_id,
			'post_title'   => $post_data['title'],
			'markdown_content' => $post_content_markdown, // Include converted markdown for all posts
			'github_links' => $github_links,
			'is_duplicate_post' => $is_duplicate,
			'duplicate_snippets' => $snippet_results
		);
		
		// Add existing data for duplicate posts
		if ( $is_duplicate && !empty( $existing_data ) ) {
			// Force boolean values for flags
			$response_data['has_markdown'] = !empty( $existing_data['has_markdown'] ) ? true : false;
			$response_data['has_snippets'] = !empty( $existing_data['has_snippets'] ) ? true : false;
			$response_data['has_prompt'] = !empty( $existing_data['has_prompt'] ) ? true : false;
			
			// Explicitly check snippets array and update flag
			if ( !empty( $existing_data['snippets'] ) && is_array( $existing_data['snippets'] ) && count( $existing_data['snippets'] ) > 0 ) {
				$response_data['has_snippets'] = true;
			}
			
			// Explicitly check prompt and update flag
			if ( !empty( $existing_data['prompt'] ) ) {
				$response_data['has_prompt'] = true;
			}
			
			// Include content if available
			if ( !empty( $existing_data['markdown_content'] ) ) {
				$response_data['markdown_content'] = $existing_data['markdown_content'];
			}
			
			if ( !empty( $existing_data['snippets'] ) ) {
				$response_data['snippets'] = $existing_data['snippets'];
			}
			
			if ( !empty( $existing_data['prompt'] ) ) {
				$response_data['prompt'] = $existing_data['prompt'];
			}
			
			// Add ALL debug info directly from the database response
			$response_data['_debug'] = $existing_data['_debug'] ?? array(
				'has_markdown_raw' => $existing_data['has_markdown'],
				'has_snippets_raw' => $existing_data['has_snippets'],
				'has_prompt_raw' => $existing_data['has_prompt'],
				'snippets_count' => is_array($existing_data['snippets']) ? count($existing_data['snippets']) : 0,
				'prompt_length' => !empty($existing_data['prompt']) ? strlen($existing_data['prompt']) : 0
			);
			
			// Include the debug property too
			if ( isset( $existing_data['debug'] ) ) {
				$response_data['debug'] = $existing_data['debug'];
			}
		}
		
		// Return success response
		wp_send_json_success( $response_data );
	}

	/**
	 * AJAX handler for storing and processing markdown content.
	 */
	public static function ajax_store_markdown() {
		// Check nonce (enhanced for anonymous users)
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed - nonce missing.', 'gpt-prompt-generator' ) ) );
		}
		
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'gptpg-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed - invalid nonce.', 'gpt-prompt-generator' ) ) );
		}

		// Note: Login restriction removed - plugin now accessible to all users

		// Check if post URL is provided
		if ( ! isset( $_POST['post_url'] ) || empty( $_POST['post_url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Post URL is required.', 'gpt-prompt-generator' ) ) );
		}

		// Check if content is provided
		if ( ! isset( $_POST['post_content'] ) || empty( $_POST['post_content'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Post content is required.', 'gpt-prompt-generator' ) ) );
		}

		// Sanitize inputs
		$post_url = esc_url_raw( wp_unslash( $_POST['post_url'] ) );
		$post_content = wp_kses_post( wp_unslash( $_POST['post_content'] ) );

		// Extract title from markdown content
		$post_title = self::extract_title_from_markdown( $post_content );
		
		// Store the markdown content in the database
		$post_store_result = GPTPG_Database::store_post( $post_url, $post_title, '', $post_content );
		
		if ( ! $post_store_result || is_wp_error( $post_store_result ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to store markdown content.', 'gpt-prompt-generator' ) ) );
		}
		
		// Use GitHub links from Step 1 if provided, otherwise extract from content
		$github_links = array();
		if ( isset( $_POST['github_links'] ) && is_array( $_POST['github_links'] ) ) {
			// Use the actual detected GitHub links from Step 1
			$github_links = array_map( 'esc_url_raw', $_POST['github_links'] );
			GPTPG_Logger::info("Using GitHub links from Step 1: " . json_encode($github_links), 'Form Handler');
		} else {
			// Fallback: extract from content (should not happen in normal flow)
			GPTPG_Logger::debug("No GitHub links from Step 1, extracting from content (length: " . strlen($post_content) . ")", 'Form Handler');
			GPTPG_Logger::debug("Content preview: " . substr($post_content, 0, 500) . "...", 'Form Handler');
			$github_links = GPTPG_GitHub_Handler::extract_github_urls( $post_content );
			GPTPG_Logger::info("Extracted " . count($github_links) . " GitHub links: " . json_encode($github_links), 'Form Handler');
		}
		
		// Check if this is a duplicate post and get existing snippets
		$response_data = array(
			'post_id' => $post_store_result['post_id'],
			'github_links' => $github_links
		);
		
		// Get post ID from URL to check for existing snippets
		$post_id = GPTPG_Database::post_exists($post_url);
		GPTPG_Logger::info("ajax_store_markdown - Post ID for URL {$post_url}: " . ($post_id ? $post_id : 'NOT FOUND'), 'Form Handler');
		if ($post_id) {
			// Fetch existing snippets for this post
			$existing_snippets = GPTPG_Database::get_snippets_by_post_id($post_id);
			GPTPG_Logger::info("Found " . count($existing_snippets) . " existing snippets for post ID {$post_id}", 'Form Handler');
			if (!empty($existing_snippets)) {
				// Prepare snippets for JSON response
				$snippet_data = array();
				foreach ($existing_snippets as $snippet) {
					$snippet_url = isset($snippet->snippet_url) ? $snippet->snippet_url : '';
					GPTPG_Logger::debug("Processing snippet: " . $snippet_url, 'Form Handler');
					$snippet_data[] = array(
						'id'         => isset($snippet->id) ? $snippet->id : (isset($snippet->snippet_id) ? $snippet->snippet_id : 0),
						'url'        => $snippet_url,
						'type'       => isset($snippet->snippet_type) ? $snippet->snippet_type : '',
						'has_content' => isset($snippet->snippet_content) ? !empty($snippet->snippet_content) : false,
					);
				}
				$response_data['snippets'] = $snippet_data;
				$response_data['has_snippets'] = true;
				GPTPG_Logger::info("Added snippets to response: " . json_encode($snippet_data), 'Form Handler');
			} else {
				GPTPG_Logger::info("No snippets found for post ID {$post_id}", 'Form Handler');
			}
		} else {
			GPTPG_Logger::warning("No post found for URL {$post_url}", 'Form Handler');
		}

		// Success response
		wp_send_json_success($response_data);


	}

	/**
	 * AJAX handler for processing code snippets.
	 */
	public static function ajax_process_snippets() {
		// Check nonce (enhanced for anonymous users)
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed - nonce missing.', 'gpt-prompt-generator' ) ) );
		}
		
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'gptpg-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed - invalid nonce.', 'gpt-prompt-generator' ) ) );
		}

		// Note: Login restriction removed - plugin now accessible to all users

		// Check if post ID was provided
		if ( ! isset( $_POST['post_id'] ) || empty( $_POST['post_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'gpt-prompt-generator' ) ) );
		}

		// Get post ID
		$post_id = intval( $_POST['post_id'] );

		// Get post data from the database
		$post_data_result = GPTPG_Database::get_post_data_by_id( $post_id );
		if ( ! $post_data_result || isset( $post_data_result['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Post not found or expired.', 'gpt-prompt-generator' ) ) );
		}
		$post_data = (object) $post_data_result;

		// Check if code snippets were provided
		if ( ! isset( $_POST['snippets'] ) || ! is_array( $_POST['snippets'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No code snippets provided.', 'gpt-prompt-generator' ) ) );
		}

		// Get the snippets array
		$snippets = wp_unslash( $_POST['snippets'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Get existing snippets from the database by post_id
		$existing_snippets = GPTPG_Database::get_snippets_by_post_id( $post_id );
		
		// Debug: Check structure of existing snippets
		if ( ! empty( $existing_snippets ) ) {
			GPTPG_Logger::debug("First existing snippet structure: " . json_encode($existing_snippets[0]), 'Form Handler');
		}
		
		// Try different field names for ID extraction
		$existing_ids = wp_list_pluck( $existing_snippets, 'snippet_id' );
		if ( empty( $existing_ids ) ) {
			// Try 'id' field instead
			$existing_ids = wp_list_pluck( $existing_snippets, 'id' );
		}
		
		// Convert to integers to ensure type consistency
		$existing_ids = array_map( 'intval', $existing_ids );
		
		GPTPG_Logger::debug("Existing snippet IDs extracted: " . json_encode($existing_ids), 'Form Handler');

		// Process snippets
		$processed_ids = array();
		$errors        = array();

		// Process each snippet
		GPTPG_Logger::info("ajax_process_snippets - Processing " . count($snippets) . " snippets", 'Form Handler');
		foreach ( $snippets as $snippet ) {
			// Sanitize data
			$snippet_id  = isset( $snippet['id'] ) ? intval( $snippet['id'] ) : 0;
			$snippet_url = isset( $snippet['url'] ) ? esc_url_raw( $snippet['url'] ) : '';
			GPTPG_Logger::debug("Processing snippet: ID={$snippet_id}, URL={$snippet_url}", 'Form Handler');

			// Skip if URL is empty
			if ( empty( $snippet_url ) ) {
				continue;
			}

			// Validate GitHub URL
			$url_type = GPTPG_GitHub_Handler::get_github_url_type( $snippet_url );
			if ( ! $url_type ) {
				$errors[] = sprintf(
					/* translators: %s: URL */
					__( 'Invalid GitHub URL: %s', 'gpt-prompt-generator' ),
					$snippet_url
				);
				continue;
			}

			// Fetch code content
			$code_content = GPTPG_GitHub_Handler::fetch_code_content( $snippet_url );
			if ( is_wp_error( $code_content ) ) {
				$errors[] = sprintf(
					/* translators: %1$s: URL, %2$s: Error message */
					__( 'Failed to fetch code from %1$s: %2$s', 'gpt-prompt-generator' ),
					$snippet_url,
					$code_content->get_error_message()
				);
				continue;
			}

			// Process based on whether it's an existing snippet or a new one
			if ( $snippet_id && in_array( $snippet_id, $existing_ids, true ) ) {
				// Update existing snippet
				GPTPG_Database::update_snippet( $snippet_id, $snippet_url, $code_content );
				$processed_ids[] = $snippet_id;
			} else {
				// Add new snippet
				$post_id = isset($post_data->post_id) ? $post_data->post_id : 0; // Add property check to prevent PHP warning
				GPTPG_Logger::debug("About to store snippet - post_id={$post_id}, url={$snippet_url}, type={$url_type}", 'Form Handler');
				$snippet_result = GPTPG_Database::store_snippet(
					$post_id, // Using post_id from normalized schema with safety check
					$snippet_url,
					$url_type,
					$code_content,
					true // User edited
				);
				GPTPG_Logger::debug("store_snippet result: " . json_encode($snippet_result), 'Form Handler');
				
				if ( isset($snippet_result['snippet_id']) ) {
					$new_snippet_id = $snippet_result['snippet_id'];
					$processed_ids[] = $new_snippet_id;
					
					// Check if it's a duplicate
					if ( isset($snippet_result['is_duplicate']) && $snippet_result['is_duplicate'] ) {
						$snippet['is_duplicate'] = true;
					}
				}
			}
		}

		// Handle deletions (snippets in the database but not in the submitted list)
		foreach ( $existing_ids as $existing_id ) {
			if ( ! in_array( $existing_id, $processed_ids, true ) ) {
				GPTPG_Database::delete_snippet( $existing_id );
			}
		}

		// Get updated snippets from the database
		$updated_snippets = GPTPG_Database::get_snippets_by_post_id( $post_id );

		// Prepare snippets for JSON response
		$snippet_data = array();
		foreach ( $updated_snippets as $snippet ) {
			$snippet_data[] = array(
				'id'         => isset($snippet->snippet_id) ? $snippet->snippet_id : 0,
				'url'        => isset($snippet->snippet_url) ? $snippet->snippet_url : '',
				'type'       => isset($snippet->snippet_type) ? $snippet->snippet_type : '',
				'has_content' => isset($snippet->snippet_content) ? !empty($snippet->snippet_content) : false,
			);
		}

		// Return success response
		wp_send_json_success( array(
			'snippets' => $snippet_data,
			'errors'   => $errors,
		) );
	}

	/**
	 * AJAX handler for generating a prompt.
	 */
	public static function ajax_generate_prompt() {
		// Check nonce (enhanced for anonymous users)
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed - nonce missing.', 'gpt-prompt-generator' ) ) );
		}
		
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'gptpg-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed - invalid nonce.', 'gpt-prompt-generator' ) ) );
		}

		// Note: Login restriction removed - plugin now accessible to all users

		// Check if post ID was provided
		if ( ! isset( $_POST['post_id'] ) || empty( $_POST['post_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'gpt-prompt-generator' ) ) );
		}

		// Get post ID
		$post_id = intval( $_POST['post_id'] );

		// Get post data from the database
		$post_data_result = GPTPG_Database::get_post_data_by_id( $post_id );
		if ( ! $post_data_result || isset( $post_data_result['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Post not found or expired.', 'gpt-prompt-generator' ) ) );
		}
		$post_data = (object) $post_data_result;

		// Get snippets from the database (now optional)
		$snippets = GPTPG_Database::get_snippets_by_post_id( $post_id );
		// We now allow empty snippets in our new workflow
		if ( ! is_array( $snippets ) ) {
			$snippets = array();
		}

		// Generate the prompt
		$prompt_content = GPTPG_Prompt_Generator::generate_prompt( $post_data, $snippets );

		// Store the generated prompt in the database
		$prompt_result = GPTPG_Database::store_prompt( $post_data->post_id, $prompt_content );
		
		// Check for duplicate prompt
		$is_duplicate = isset($prompt_result['is_duplicate']) ? $prompt_result['is_duplicate'] : false;

		// Return success response
		wp_send_json_success( array(
			'prompt' => $prompt_content,
			'is_duplicate' => $is_duplicate
		) );
	}

	/**
	 * Enhanced fetch post content from URL with multi-strategy approach.
	 *
	 * @param string $url The URL to fetch content from.
	 * @return array|WP_Error Post data array with 'title' and 'content' keys, or WP_Error on failure.
	 */
	private static function fetch_post_content( $url ) {
		// Make sure URL is valid
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', __( 'Invalid URL format.', 'gpt-prompt-generator' ) );
		}

		// Use enhanced multi-strategy approach
		return self::fetch_post_content_enhanced( $url );
	}

	/**
	 * Enhanced multi-strategy content fetching.
	 *
	 * @param string $url The URL to fetch content from.
	 * @return array|WP_Error Post data array or WP_Error on failure.
	 */
	private static function fetch_post_content_enhanced( $url ) {
		$failures = array();
		$strategy = get_option( 'gptpg_fetch_strategy', 'enhanced' );

		// Strategy 1: Try internal access first (fastest and bypasses restrictions)
		if ( 'enhanced' === $strategy ) {
			$internal_result = self::fetch_content_internally( $url );
			if ( ! is_wp_error( $internal_result ) ) {
				self::log_fetch_attempt( $url, 'success', array( 'method' => 'internal' ) );
				return $internal_result;
			} else {
				$failures[] = 'internal_fetch_failed';
			}
		}

		// Strategy 2: Try cookie-free external fetch (recommended for PMPro LPV)
		if ( in_array( $strategy, array( 'enhanced', 'cookie_free_only' ), true ) ) {
			$cookie_free_result = self::fetch_content_without_cookies( $url );
			if ( ! is_wp_error( $cookie_free_result ) ) {
				$failures_detected = self::detect_fetch_failures( null, $cookie_free_result['content'], $url );
				if ( empty( $failures_detected ) ) {
					self::log_fetch_attempt( $url, 'success', array( 'method' => 'cookie_free' ) );
					return $cookie_free_result;
				} else {
					$failures = array_merge( $failures, $failures_detected );
				}
			} else {
				$failures[] = 'cookie_free_fetch_failed';
			}
		}

		// Strategy 3: Standard fetch (fallback)
		if ( 'enhanced' === $strategy ) {
			$standard_result = self::fetch_content_standard( $url );
			if ( ! is_wp_error( $standard_result ) ) {
				$failures_detected = self::detect_fetch_failures( null, $standard_result['content'], $url );
				if ( empty( $failures_detected ) ) {
					self::log_fetch_attempt( $url, 'success', array( 'method' => 'standard' ) );
					return $standard_result;
				} else {
					$failures = array_merge( $failures, $failures_detected );
				}
			} else {
				$failures[] = 'standard_fetch_failed';
			}
		}

		// All strategies failed
		self::log_fetch_attempt( $url, 'failed', $failures );
		$error_message = self::generate_user_friendly_error( $failures );
		return new WP_Error( 'all_strategies_failed', $error_message );
	}

	/**
	 * Fetch content internally for same-site URLs.
	 *
	 * @param string $url The URL to fetch content from.
	 * @return array|WP_Error Post data array or WP_Error on failure.
	 */
	private static function fetch_content_internally( $url ) {
		$site_url = get_site_url();
		if ( 0 !== strpos( $url, $site_url ) ) {
			return new WP_Error( 'not_internal', __( 'URL is not from the same site.', 'gpt-prompt-generator' ) );
		}

		// Extract post ID from URL if possible
		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post && 'publish' === $post->post_status ) {
				return array(
					'title'   => get_the_title( $post ),
					'content' => apply_filters( 'the_content', $post->post_content ),
				);
			}
		}

		return new WP_Error( 'internal_fetch_failed', __( 'Could not fetch content internally.', 'gpt-prompt-generator' ) );
	}

	/**
	 * Fetch content without cookies to bypass PMPro LPV restrictions.
	 *
	 * @param string $url The URL to fetch content from.
	 * @return array|WP_Error Post data array or WP_Error on failure.
	 */
	private static function fetch_content_without_cookies( $url ) {
		$args = array(
			'timeout'     => 30,
			'redirection' => 5,
			'sslverify'   => true,
			'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . '; GPT-Prompt-Generator/' . GPTPG_VERSION,
			'cookies'     => array(), // Explicitly clear cookies
		);

		return self::perform_http_request( $url, $args );
	}

	/**
	 * Standard content fetch with default settings.
	 *
	 * @param string $url The URL to fetch content from.
	 * @return array|WP_Error Post data array or WP_Error on failure.
	 */
	private static function fetch_content_standard( $url ) {
		$args = array(
			'timeout'     => 30,
			'redirection' => 5,
			'sslverify'   => true,
			'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . '; GPT-Prompt-Generator/' . GPTPG_VERSION,
		);

		return self::perform_http_request( $url, $args );
	}

	/**
	 * Perform HTTP request with content extraction.
	 *
	 * @param string $url The URL to fetch content from.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error Post data array or WP_Error on failure.
	 */
	private static function perform_http_request( $url, $args ) {
		// Make the request
		$response = wp_remote_get( $url, $args );

		// Check for errors
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check response code
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error(
				'request_failed',
				sprintf(
					/* translators: %s: HTTP status code */
					__( 'Failed to fetch post content. Status code: %s', 'gpt-prompt-generator' ),
					$response_code
				)
			);
		}

		// Get response body
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error( 'empty_response', __( 'Received empty response from the server.', 'gpt-prompt-generator' ) );
		}

		return self::extract_content_from_html( $body );
	}

	/**
	 * Extract content from HTML body.
	 *
	 * @param string $html_body The HTML body content.
	 * @return array|WP_Error Post data array or WP_Error on failure.
	 */
	private static function extract_content_from_html( $html_body ) {
		// Create a DOMDocument from the HTML
		$doc = new DOMDocument();
		
		// Suppress warnings from invalid HTML
		libxml_use_internal_errors( true );
		$doc->loadHTML( $html_body );
		libxml_clear_errors();

		// Extract title
		$title = '';
		$title_tags = $doc->getElementsByTagName( 'title' );
		if ( $title_tags->length > 0 ) {
			$title = $title_tags->item( 0 )->textContent;
		}

		// Find the post content
		$content = '';
		$xpath = new DOMXPath( $doc );

		// Enhanced selectors to find the main content
		$selectors = array(
			'//article',
			'//div[contains(@class, "post-content")]',
			'//div[contains(@class, "entry-content")]',
			'//div[contains(@class, "content")]',
			'//div[contains(@class, "post-body")]',
			'//div[contains(@class, "article-content")]',
			'//main',
			'//div[contains(@class, "main")]',
			'//section[contains(@class, "content")]',
		);

		foreach ( $selectors as $selector ) {
			$nodes = $xpath->query( $selector );
			if ( $nodes->length > 0 ) {
				$content_node = $nodes->item( 0 );
				$content = $doc->saveHTML( $content_node );
				break;
			}
		}

		// If still no content found, use the body
		if ( empty( $content ) ) {
			$body_tags = $doc->getElementsByTagName( 'body' );
			if ( $body_tags->length > 0 ) {
				$content = $doc->saveHTML( $body_tags->item( 0 ) );
			}
		}

		// Return post data
		return array(
			'title'   => $title,
			'content' => $content,
		);
	}

	/**
	 * Detect various failure conditions in fetched content.
	 *
	 * @param mixed  $response HTTP response (unused in current implementation).
	 * @param string $content The fetched HTML content.
	 * @param string $url The original URL.
	 * @return array Array of detected failures.
	 */
	private static function detect_fetch_failures( $response, $content, $url ) {
		$failures = array();

		// Content Quality Checks
		if ( strlen( strip_tags( $content ) ) < 100 ) {
			$failures[] = 'insufficient_content';
		}

		// PMPro LPV Detection
		if ( strpos( $content, 'pmprolpv' ) !== false ) {
			$failures[] = 'pmpro_lpv_detected';
		}

		// Extract the text content from body for better context analysis
		// and avoid false positives from URL paths or metadata
		$stripped_content = strip_tags($content);
		
		// Create a DOM document to analyze the actual content areas
		$dom = new DOMDocument();
		@$dom->loadHTML($content);
		$xpath = new DOMXPath($dom);
		
		// Look specifically in content areas, not navigation or headers
		$content_nodes = $xpath->query('//article | //div[contains(@class, "content")] | //div[contains(@class, "post-content")] | //div[contains(@class, "entry-content")]');
		$main_content = '';
		
		// Extract text from content areas
		if ($content_nodes->length > 0) {
			foreach ($content_nodes as $node) {
				$main_content .= $node->textContent . ' ';
			}
		} else {
			// If no specific content areas found, use the stripped content
			$main_content = $stripped_content;
		}
		
		// Paywall/Login Detection
		$paywall_indicators = array(
			'data-paywall',
			'subscription-required',
			'login-required',
			'premium-content',
			'members-only',
			'paywall',
			'subscribe-to-continue',
		);

		// Check for indicators in the main content, not in URLs or navigation
		foreach ( $paywall_indicators as $indicator ) {
			// Look for indicators that are standalone words or phrases, not part of URLs
			// by using word boundary checks
			if ( preg_match('/\b' . preg_quote($indicator, '/') . '\b/i', $main_content) ) {
				$failures[] = 'paywall_detected';
				break;
			}
		}

		// Common paywall text patterns
		$paywall_patterns = array(
			'/subscribe.*to.*continue/i',
			'/become.*member.*to.*read/i',
			'/this.*content.*is.*premium/i',
			'/sign.*in.*to.*view/i',
			'/login.*to.*read.*more/i',
			'/membership.*required/i',
		);

		foreach ( $paywall_patterns as $pattern ) {
			if ( preg_match( $pattern, $main_content ) ) {
				$failures[] = 'paywall_content_detected';
				break;
			}
		}

		return $failures;
	}

	/**
	 * Generate user-friendly error message based on detected failures.
	 *
	 * @param array $failures Array of detected failure types.
	 * @return string User-friendly error message.
	 */
	private static function generate_user_friendly_error( $failures ) {
		if ( empty( $failures ) ) {
			return __( 'Content fetching failed for unknown reasons.', 'gpt-prompt-generator' );
		}

		// Check for specific failure types and provide helpful messages
		if ( in_array( 'pmpro_lpv_detected', $failures, true ) || in_array( 'paywall_detected', $failures, true ) || in_array( 'paywall_content_detected', $failures, true ) ) {
			return __( 'This content appears to be behind a paywall or membership restriction. Please try copying and pasting the content manually using the form below.', 'gpt-prompt-generator' );
		}

		if ( in_array( 'insufficient_content', $failures, true ) ) {
			return __( 'The fetched content appears to be too short or incomplete. Please try copying and pasting the content manually using the form below.', 'gpt-prompt-generator' );
		}

		if ( in_array( 'internal_fetch_failed', $failures, true ) && in_array( 'cookie_free_fetch_failed', $failures, true ) && in_array( 'standard_fetch_failed', $failures, true ) ) {
			return __( 'Unable to fetch content using any available method. Please try copying and pasting the content manually using the form below.', 'gpt-prompt-generator' );
		}

		return __( 'Content fetching encountered some issues. Please try copying and pasting the content manually using the form below.', 'gpt-prompt-generator' );
	}

	/**
	 * Log fetch attempts for debugging purposes.
	 *
	 * @param string $url The URL that was fetched.
	 * @param string $result The result ('success' or 'failed').
	 * @param array  $details Additional details about the fetch attempt.
	 */
	private static function log_fetch_attempt( $url, $result, $details = array() ) {
		if ( ! get_option( 'gptpg_debug_logging', false ) ) {
			return;
		}

		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'url'       => $url,
			'result'    => $result,
			'details'   => $details,
		);

		GPTPG_Logger::debug( 'Fetch Log: ' . wp_json_encode( $log_entry ), 'Form Handler' );
	}

	/**
	 * Enhanced HTML to Markdown conversion with better error handling.
	 *
	 * @param string $html_content The HTML content to convert.
	 * @return string The converted Markdown content.
	 */
	private static function convert_html_to_markdown( $html_content ) {
		if ( empty( $html_content ) ) {
			return '';
		}

		// Check if the HTML to Markdown converter is available
		if ( ! class_exists( 'League\HTMLToMarkdown\HtmlConverter' ) ) {
			return self::convert_html_alternative( $html_content );
		}

		// Try primary conversion with league/html-to-markdown
		try {
			$converter = new League\HTMLToMarkdown\HtmlConverter( array(
				'strip_tags'        => false,
				'hard_break'        => true,
				'remove_nodes'      => 'script style nav footer header aside',
				'strip_placeholder_links' => true,
			) );

			// Pre-process HTML to improve conversion quality
			$cleaned_html = self::preprocess_html_for_conversion( $html_content );
			$markdown = $converter->convert( $cleaned_html );

			// Validate the conversion result
			if ( self::validate_markdown_conversion( $html_content, $markdown ) ) {
				return self::post_process_markdown( $markdown );
			}
		} catch ( Exception $e ) {
			// Log the error for debugging
			if ( get_option( 'gptpg_debug_logging', false ) ) {
				GPTPG_Logger::error( 'HTML to Markdown conversion error: ' . $e->getMessage(), 'Form Handler' );
			}
		}

		// Fallback to alternative conversion methods
		return self::convert_html_alternative( $html_content );
	}

	/**
	 * Preprocess HTML to improve conversion quality.
	 *
	 * @param string $html The raw HTML content.
	 * @return string The preprocessed HTML.
	 */
	private static function preprocess_html_for_conversion( $html ) {
		// Remove problematic elements that cause conversion issues
		$html = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $html );
		$html = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $html );
		$html = preg_replace( '/<nav[^>]*>.*?<\/nav>/is', '', $html );
		$html = preg_replace( '/<footer[^>]*>.*?<\/footer>/is', '', $html );
		$html = preg_replace( '/<aside[^>]*>.*?<\/aside>/is', '', $html );

		// Fix common HTML issues
		$html = str_replace( array( '&nbsp;', '\u00a0' ), ' ', $html );
		$html = preg_replace( '/\s+/', ' ', $html ); // Normalize whitespace

		return $html;
	}

	/**
	 * Validate if the Markdown conversion was successful.
	 *
	 * @param string $original_html The original HTML content.
	 * @param string $markdown The converted Markdown content.
	 * @return bool True if conversion is valid, false otherwise.
	 */
	private static function validate_markdown_conversion( $original_html, $markdown ) {
		// Basic validation checks
		if ( empty( $markdown ) ) {
			if ( get_option( 'gptpg_debug_logging', false ) ) {
				GPTPG_Logger::warning( 'Markdown conversion failed - empty result', 'Form Handler' );
			}
			return false;
		}

		// Check for minimum content length (more reasonable threshold)
		$markdown_text_length = strlen( strip_tags( $markdown ) );
		if ( $markdown_text_length < 20 ) {
			if ( get_option( 'gptpg_debug_logging', false ) ) {
				GPTPG_Logger::warning( 'Markdown conversion failed - too short: ' . $markdown_text_length . ' characters', 'Form Handler' );
			}
			return false;
		}

		// For longer content, do a more lenient comparison
		$original_text_length = strlen( strip_tags( $original_html ) );
		if ( $original_text_length > 500 && $markdown_text_length < ( $original_text_length * 0.2 ) ) {
			if ( get_option( 'gptpg_debug_logging', false ) ) {
				GPTPG_Logger::warning( 'Markdown conversion failed - too short compared to original. Original: ' . $original_text_length . ', Markdown: ' . $markdown_text_length, 'Form Handler' );
			}
			return false;
		}

		if ( get_option( 'gptpg_debug_logging', false ) ) {
			GPTPG_Logger::info( 'Markdown conversion validated successfully. Length: ' . $markdown_text_length, 'Form Handler' );
		}

		return true;
	}

	/**
	 * Post-process the converted Markdown to improve quality.
	 *
	 * @param string $markdown The converted Markdown content.
	 * @return string The post-processed Markdown.
	 */
	private static function post_process_markdown( $markdown ) {
		// Clean up excessive whitespace
		$markdown = preg_replace( '/\n\s*\n\s*\n/', "\n\n", $markdown );
		$markdown = preg_replace( '/^\s+|\s+$/m', '', $markdown );

		// Remove empty links
		$markdown = preg_replace( '/\[\]\([^)]*\)/', '', $markdown );

		// Clean up heading formatting
		$markdown = preg_replace( '/^(#{1,6})\s*(.*)\s*$/m', '$1 $2', $markdown );

		return trim( $markdown );
	}

	/**
	 * Alternative HTML to Markdown conversion methods.
	 *
	 * @param string $html_content The HTML content to convert.
	 * @return string The converted content.
	 */
	private static function convert_html_alternative( $html_content ) {
		if ( get_option( 'gptpg_debug_logging', false ) ) {
			error_log( 'GPTPG: Using fallback conversion methods' );
		}

		// Method 1: Enhanced strip_tags with basic formatting preservation
		$markdown = self::strip_tags_preserve_formatting( $html_content );

		if ( get_option( 'gptpg_debug_logging', false ) ) {
			error_log( 'GPTPG: Fallback method 1 result length: ' . strlen( trim( $markdown ) ) );
		}

		// Method 2: If Method 1 fails, use enhanced strip_tags
		if ( empty( $markdown ) || strlen( trim( $markdown ) ) < 20 ) {
			$markdown = self::enhanced_strip_tags( $html_content );
			if ( get_option( 'gptpg_debug_logging', false ) ) {
				error_log( 'GPTPG: Fallback method 2 result length: ' . strlen( trim( $markdown ) ) );
			}
		}

		// Method 3: Last resort - simple wp_strip_all_tags
		if ( empty( $markdown ) || strlen( trim( $markdown ) ) < 20 ) {
			$markdown = wp_strip_all_tags( $html_content );
			// Clean up whitespace
			$markdown = preg_replace( '/\s+/', ' ', $markdown );
			$markdown = trim( $markdown );
			if ( get_option( 'gptpg_debug_logging', false ) ) {
				error_log( 'GPTPG: Fallback method 3 result length: ' . strlen( $markdown ) );
			}
		}

		return $markdown;
	}

	/**
	 * Enhanced strip_tags that preserves some formatting.
	 *
	 * @param string $html The HTML content.
	 * @return string The formatted text content.
	 */
	private static function strip_tags_preserve_formatting( $html ) {
		// Convert common HTML elements to markdown-like formatting
		$replacements = array(
			'/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/i' => "\n\n$2\n\n",
			'/<p[^>]*>(.*?)<\/p>/i'             => "\n\n$1\n\n",
			'/<br\s*\/?>/i'                     => "\n",
			'/<strong[^>]*>(.*?)<\/strong>/i'   => "**$1**",
			'/<b[^>]*>(.*?)<\/b>/i'             => "**$1**",
			'/<em[^>]*>(.*?)<\/em>/i'           => "*$1*",
			'/<i[^>]*>(.*?)<\/i>/i'             => "*$1*",
			'/<code[^>]*>(.*?)<\/code>/i'       => "`$1`",
			'/<a[^>]*href=["\']([^"\'>]*)["\'][^>]*>(.*?)<\/a>/i' => "[$2]($1)",
			'/<li[^>]*>(.*?)<\/li>/i'           => "\n- $1",
			'/<blockquote[^>]*>(.*?)<\/blockquote>/i' => "\n> $1\n",
		);

		$text = $html;
		foreach ( $replacements as $pattern => $replacement ) {
			$text = preg_replace( $pattern, $replacement, $text );
		}

		// Remove remaining HTML tags
		$text = strip_tags( $text );

		// Clean up whitespace
		$text = preg_replace( '/\n\s*\n\s*\n/', "\n\n", $text );
		$text = trim( $text );

		return $text;
	}

	/**
	 * Enhanced strip_tags method for better content extraction.
	 *
	 * @param string $html The HTML content.
	 * @return string The cleaned text content.
	 */
	private static function enhanced_strip_tags( $html ) {
		// Remove unwanted elements completely
		$html = preg_replace( '/<(script|style|nav|footer|header|aside)[^>]*>.*?<\/\1>/is', '', $html );

		// Add spacing for block elements
		$html = preg_replace( '/<\/(div|p|h[1-6]|article|section|blockquote|ul|ol|li)>/i', '$0\n\n', $html );
		$html = preg_replace( '/<(br|hr)\s*\/?>/i', '\n', $html );

		// Remove all HTML tags
		$text = wp_strip_all_tags( $html );

		// Clean up whitespace
		$text = preg_replace( '/\n\s*\n\s*\n/', "\n\n", $text );
		$text = preg_replace( '/^\s+|\s+$/m', '', $text );
		$text = trim( $text );

		return $text;
	}

	/**
	 * Extract title from markdown content.
	 *
	 * @param string $markdown The markdown content.
	 * @return string The extracted title or empty string if not found.
	 */
	public static function extract_title_from_markdown( $markdown ) {
		// Try to find heading level 1 (#) at start of content
		if ( preg_match( '/^\s*#\s+(.+?)\s*$/m', $markdown, $matches ) ) {
			return trim( $matches[1] );
		}

		// Try to find the first non-empty line as a fallback
		$lines = explode( "\n", $markdown );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) && strpos( $line, '#' ) !== 0 ) { // Not an empty line or heading
				return $line;
			}
		}

		return ''; // Return empty string if no title found
	}

	/**
	 * Clean markdown content by removing author/footer information.
	 *
	 * @param string $content The markdown content to clean.
	 * @return string Cleaned markdown content.
	 */
	public static function clean_markdown_content( $content ) {
		// Pattern to match author information sections
		$patterns = array(
			// Match content starting with author gravatar/image
			'/!\[.*?Team\]\(.*?\)\s*.*?Author:.*?(---|-$|$)/s',
			// Match content starting with "Author:" 
			'/Author:.*?(---|-$|$)/s',
			// Match content with "Was this article helpful?" 
			'/Was this article helpful\?[\s\S]*?($)/s',
			// Match "Source:" line at the end
			'/Source: \[.*?\]\(.*?\)\s*$/s',
			// Match "Paid Memberships Pro is recommended by our customers" section
			'/\*\*Paid Memberships Pro is recommended[\s\S]*?($)/s',
			// Match "Free Course:" section
			'/Free Course:.*?\)\s*$/s',
			// Match any horizontal rule with only whitespace after it (likely end of content)
			'/---\s*$/s'
		);

		// Apply all patterns
		foreach ( $patterns as $pattern ) {
			$content = preg_replace( $pattern, '', $content );
		}

		// Trim any trailing whitespace
		$content = rtrim( $content );

		 return $content;
	}

	/**
	 * AJAX handler to get a fresh nonce.
	 * This helps resolve nonce timing/context issues.
	 */
	public static function get_fresh_nonce() {
		// No nonce verification needed for getting a fresh nonce
		// This is a safe operation that just generates a new nonce
		
		$fresh_nonce = wp_create_nonce( 'gptpg-nonce' );
		

		
		wp_send_json_success( array( 'nonce' => $fresh_nonce ) );
	}


}

// Initialize the form handler
GPTPG_Form_Handler::init();
