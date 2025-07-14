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
		// Nothing to initialize for now
	}

	/**
	 * Generate a unique session ID.
	 *
	 * @return string Unique session ID.
	 */
	public static function generate_session_id() {
		return wp_hash( uniqid( 'gptpg_', true ) . time() );
	}

	/**
	 * AJAX handler for fetching a post by URL.
	 */
	public static function ajax_fetch_post() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gptpg-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'gpt-prompt-generator' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to use this feature.', 'gpt-prompt-generator' ) ) );
		}

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

		// Generate a new session ID
		$session_id = self::generate_session_id();

		// Convert HTML to Markdown
		if ( ! class_exists( 'League\HTMLToMarkdown\HtmlConverter' ) ) {
			require_once GPTPG_PLUGIN_DIR . 'vendor/autoload.php';
		}

		// Check if the HTML to Markdown converter is available
		if ( class_exists( 'League\HTMLToMarkdown\HtmlConverter' ) ) {
			try {
				$converter = new League\HTMLToMarkdown\HtmlConverter( array(
					'strip_tags' => false,
					'hard_break' => true,
				) );
				$post_content_markdown = $converter->convert( $post_data['content'] );
			} catch ( Exception $e ) {
				// Fallback to a simplified conversion
				$post_content_markdown = wp_strip_all_tags( $post_data['content'] );
			}
		} else {
			// Fallback to a simplified conversion
			$post_content_markdown = wp_strip_all_tags( $post_data['content'] );
		}

		// Store post data in database
		$expiry_time = intval( get_option( 'gptpg_expiry_time', 3600 ) );
		$post_id = GPTPG_Database::store_post(
			$session_id,
			$post_url,
			$post_data['title'],
			$post_data['content'],
			$post_content_markdown,
			$expiry_time
		);

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to store post data.', 'gpt-prompt-generator' ) ) );
		}

		// Extract GitHub/Gist links from post content
		$github_links = GPTPG_GitHub_Handler::extract_github_urls( $post_data['content'] );

		// Store code snippets in database
		foreach ( $github_links as $link ) {
			$snippet_type = GPTPG_GitHub_Handler::get_github_url_type( $link );
			if ( $snippet_type ) {
				GPTPG_Database::store_snippet(
					$session_id,
					$post_id,
					$link,
					$snippet_type
				);
			}
		}

		// Return success response
		wp_send_json_success( array(
			'session_id'   => $session_id,
			'post_title'   => $post_data['title'],
			'github_links' => $github_links,
		) );
	}

	/**
	 * AJAX handler for processing code snippets.
	 */
	public static function ajax_process_snippets() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gptpg-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'gpt-prompt-generator' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to use this feature.', 'gpt-prompt-generator' ) ) );
		}

		// Check if session ID was provided
		if ( ! isset( $_POST['session_id'] ) || empty( $_POST['session_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session.', 'gpt-prompt-generator' ) ) );
		}

		// Get session ID
		$session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ) );

		// Get post data from the database
		$post_data = GPTPG_Database::get_post_by_session( $session_id );
		if ( ! $post_data ) {
			wp_send_json_error( array( 'message' => __( 'Session expired or invalid.', 'gpt-prompt-generator' ) ) );
		}

		// Check if code snippets were provided
		if ( ! isset( $_POST['snippets'] ) || ! is_array( $_POST['snippets'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No code snippets provided.', 'gpt-prompt-generator' ) ) );
		}

		// Get the snippets array
		$snippets = wp_unslash( $_POST['snippets'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Get existing snippets from the database
		$existing_snippets = GPTPG_Database::get_snippets_by_session( $session_id );
		$existing_ids      = wp_list_pluck( $existing_snippets, 'id' );

		// Process snippets
		$processed_ids = array();
		$errors        = array();

		// Process each snippet
		foreach ( $snippets as $snippet ) {
			// Sanitize data
			$snippet_id  = isset( $snippet['id'] ) ? intval( $snippet['id'] ) : 0;
			$snippet_url = isset( $snippet['url'] ) ? esc_url_raw( $snippet['url'] ) : '';

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
				$new_snippet_id = GPTPG_Database::store_snippet(
					$session_id,
					$post_data->id,
					$snippet_url,
					$url_type,
					$code_content,
					true // User edited
				);
				if ( $new_snippet_id ) {
					$processed_ids[] = $new_snippet_id;
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
		$updated_snippets = GPTPG_Database::get_snippets_by_session( $session_id );

		// Prepare snippets for JSON response
		$snippet_data = array();
		foreach ( $updated_snippets as $snippet ) {
			$snippet_data[] = array(
				'id'         => $snippet->id,
				'url'        => $snippet->snippet_url,
				'type'       => $snippet->snippet_type,
				'has_content' => ! empty( $snippet->snippet_content ),
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
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gptpg-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'gpt-prompt-generator' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to use this feature.', 'gpt-prompt-generator' ) ) );
		}

		// Check if session ID was provided
		if ( ! isset( $_POST['session_id'] ) || empty( $_POST['session_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session.', 'gpt-prompt-generator' ) ) );
		}

		// Get session ID
		$session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ) );

		// Get post data from the database
		$post_data = GPTPG_Database::get_post_by_session( $session_id );
		if ( ! $post_data ) {
			wp_send_json_error( array( 'message' => __( 'Session expired or invalid.', 'gpt-prompt-generator' ) ) );
		}

		// Get snippets from the database
		$snippets = GPTPG_Database::get_snippets_by_session( $session_id );
		if ( empty( $snippets ) ) {
			wp_send_json_error( array( 'message' => __( 'No code snippets available.', 'gpt-prompt-generator' ) ) );
		}

		// Generate the prompt
		$prompt_content = GPTPG_Prompt_Generator::generate_prompt( $post_data, $snippets );

		// Store the generated prompt in the database
		GPTPG_Database::store_prompt( $session_id, $post_data->id, $prompt_content );

		// Return success response
		wp_send_json_success( array(
			'prompt' => $prompt_content,
		) );
	}

	/**
	 * Fetch post content from a URL.
	 *
	 * @param string $url Post URL.
	 * @return array|WP_Error Post data or WP_Error on failure.
	 */
	private static function fetch_post_content( $url ) {
		// Make sure URL is valid
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', __( 'Invalid URL format.', 'gpt-prompt-generator' ) );
		}

		// Prepare request arguments
		$args = array(
			'timeout'     => 30,
			'redirection' => 5,
			'sslverify'   => true,
			'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . '; GPT-Prompt-Generator/' . GPTPG_VERSION,
		);

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

		// Create a DOMDocument from the HTML
		$doc = new DOMDocument();
		
		// Suppress warnings from invalid HTML
		libxml_use_internal_errors( true );
		$doc->loadHTML( $body );
		libxml_clear_errors();

		// Extract title
		$title = '';
		$title_tags = $doc->getElementsByTagName( 'title' );
		if ( $title_tags->length > 0 ) {
			$title = $title_tags->item( 0 )->textContent;
		}

		// Find the post content
		// First try to find an article element
		$content = '';
		$xpath = new DOMXPath( $doc );

		// Try different selectors to find the main content
		$selectors = array(
			'//article',
			'//div[contains(@class, "post-content")]',
			'//div[contains(@class, "entry-content")]',
			'//div[contains(@class, "content")]',
			'//main',
			'//div[contains(@class, "main")]',
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
}

// Initialize the form handler
GPTPG_Form_Handler::init();
