<?php
/**
 * GPTPG GitHub Handler
 *
 * Handles GitHub API interactions for fetching code snippets.
 *
 * @package GPT_Prompt_Generator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GPTPG_GitHub_Handler class
 *
 * Manages GitHub API interactions and code fetching.
 */
class GPTPG_GitHub_Handler {

	/**
	 * GitHub API base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://api.github.com';

	/**
	 * GitHub raw content base URL.
	 *
	 * @var string
	 */
	const RAW_CONTENT_URL = 'https://raw.githubusercontent.com';

	/**
	 * Initialize the class.
	 */
	public static function init() {
		// Nothing to initialize for now
	}

	/**
	 * Get GitHub personal access token from settings.
	 *
	 * @return string GitHub token or empty string.
	 */
	private static function get_token() {
		return get_option( 'gptpg_github_token', '' );
	}

	/**
	 * Parse GitHub repository URL to extract owner, repo, and path.
	 *
	 * @param string $url GitHub repository URL.
	 * @return array|false Array with owner, repo, and path, or false on failure.
	 */
	public static function parse_github_url( $url ) {
		$parsed = false;

		// Parse GitHub repository URL
		if ( preg_match( '#^https?://github\.com/([^/]+)/([^/]+)(/blob/([^/]+)/(.+))?$#', $url, $matches ) ) {
			$parsed = array(
				'type'  => 'repo',
				'owner' => $matches[1],
				'repo'  => $matches[2],
				'ref'   => isset( $matches[4] ) ? $matches[4] : 'master',
				'path'  => isset( $matches[5] ) ? $matches[5] : '',
			);
		}
		// Parse GitHub gist URL
		elseif ( preg_match( '#^https?://gist\.github\.com/([^/]+)/([a-f0-9]+)#', $url, $matches ) ) {
			$parsed = array(
				'type'     => 'gist',
				'username' => $matches[1],
				'gist_id'  => $matches[2],
			);
		}
		// Parse GitHub raw content URL
		elseif ( preg_match( '#^https?://raw\.githubusercontent\.com/([^/]+)/([^/]+)/([^/]+)/(.+)$#', $url, $matches ) ) {
			$parsed = array(
				'type'  => 'raw',
				'owner' => $matches[1],
				'repo'  => $matches[2],
				'ref'   => $matches[3],
				'path'  => $matches[4],
			);
		}

		return $parsed;
	}

	/**
	 * Determine if a URL is a GitHub repository, gist, or raw file URL.
	 *
	 * @param string $url URL to check.
	 * @return string|false Type of URL ('repo', 'gist', 'raw') or false if not GitHub.
	 */
	public static function get_github_url_type( $url ) {
		$parsed = self::parse_github_url( $url );
		return $parsed ? $parsed['type'] : false;
	}

	/**
	 * Fetch code content from a GitHub URL.
	 *
	 * @param string $url GitHub URL (repo, gist, or raw).
	 * @return string|WP_Error Code content or WP_Error on failure.
	 */
	public static function fetch_code_content( $url ) {
		$parsed = self::parse_github_url( $url );
		
		if ( ! $parsed ) {
			return new WP_Error( 'invalid_github_url', __( 'Invalid GitHub URL format.', 'gpt-prompt-generator' ) );
		}

		// Based on the URL type, fetch the content appropriately
		switch ( $parsed['type'] ) {
			case 'repo':
				return self::fetch_repo_file( $parsed['owner'], $parsed['repo'], $parsed['path'], $parsed['ref'] );
				
			case 'gist':
				return self::fetch_gist_file( $parsed['gist_id'] );
				
			case 'raw':
				return self::fetch_raw_file( $url );
				
			default:
				return new WP_Error( 'unknown_github_url_type', __( 'Unknown GitHub URL type.', 'gpt-prompt-generator' ) );
		}
	}

	/**
	 * Fetch file content from a GitHub repository.
	 *
	 * @param string $owner Repository owner.
	 * @param string $repo  Repository name.
	 * @param string $path  File path within the repository.
	 * @param string $ref   Branch or commit reference.
	 * @return string|WP_Error File content or WP_Error on failure.
	 */
	private static function fetch_repo_file( $owner, $repo, $path, $ref = 'master' ) {
		// First try using the GitHub API
		$api_result = self::fetch_via_api( $owner, $repo, $path, $ref );
		if ( ! is_wp_error( $api_result ) ) {
			return $api_result;
		}

		// If API failed, try fetching from raw URL
		$raw_url = self::RAW_CONTENT_URL . "/{$owner}/{$repo}/{$ref}/{$path}";
		return self::fetch_raw_file( $raw_url );
	}

	/**
	 * Fetch file content from a GitHub repository via API.
	 *
	 * @param string $owner Repository owner.
	 * @param string $repo  Repository name.
	 * @param string $path  File path within the repository.
	 * @param string $ref   Branch or commit reference.
	 * @return string|WP_Error File content or WP_Error on failure.
	 */
	private static function fetch_via_api( $owner, $repo, $path, $ref = 'master' ) {
		// Build API URL
		$api_url = self::API_BASE_URL . "/repos/{$owner}/{$repo}/contents/{$path}?ref={$ref}";
		
		// Prepare request arguments
		$args = self::prepare_request_args();
		
		// Make the request
		$response = wp_remote_get( $api_url, $args );
		
		// Check for errors
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		// Check response code
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$error_message = wp_remote_retrieve_response_message( $response );
			return new WP_Error(
				'github_api_error',
				sprintf(
					/* translators: %1$s: HTTP status code, %2$s: Error message */
					__( 'GitHub API Error: %1$s - %2$s', 'gpt-prompt-generator' ),
					$response_code,
					$error_message
				)
			);
		}
		
		// Parse response body
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );
		
		// Check if we got a valid response
		if ( ! $data || ! isset( $data->content ) || ! isset( $data->encoding ) ) {
			return new WP_Error( 'github_api_invalid_response', __( 'Invalid response from GitHub API.', 'gpt-prompt-generator' ) );
		}
		
		// Decode content (usually base64)
		if ( 'base64' === $data->encoding ) {
			return base64_decode( str_replace( array( "\n", "\r" ), '', $data->content ) );
		}
		
		return $data->content;
	}

	/**
	 * Fetch file content from a GitHub Gist.
	 *
	 * @param string $gist_id Gist ID.
	 * @return string|WP_Error File content or WP_Error on failure.
	 */
	private static function fetch_gist_file( $gist_id ) {
		// Build API URL
		$api_url = self::API_BASE_URL . "/gists/{$gist_id}";
		
		// Prepare request arguments
		$args = self::prepare_request_args();
		
		// Make the request
		$response = wp_remote_get( $api_url, $args );
		
		// Check for errors
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		// Check response code
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$error_message = wp_remote_retrieve_response_message( $response );
			return new WP_Error(
				'github_api_error',
				sprintf(
					/* translators: %1$s: HTTP status code, %2$s: Error message */
					__( 'GitHub API Error: %1$s - %2$s', 'gpt-prompt-generator' ),
					$response_code,
					$error_message
				)
			);
		}
		
		// Parse response body
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );
		
		// Check if we got a valid response
		if ( ! $data || ! isset( $data->files ) || ! is_object( $data->files ) ) {
			return new WP_Error( 'github_api_invalid_response', __( 'Invalid response from GitHub API.', 'gpt-prompt-generator' ) );
		}
		
		// Get the first file content (or concatenate all files)
		$files = get_object_vars( $data->files );
		if ( empty( $files ) ) {
			return new WP_Error( 'github_api_no_files', __( 'No files found in the gist.', 'gpt-prompt-generator' ) );
		}
		
		// Get the first file (usually there's only one)
		$file = reset( $files );
		if ( isset( $file->content ) ) {
			return $file->content;
		}
		
		// If we can't get the content directly, try the raw URL
		if ( isset( $file->raw_url ) ) {
			return self::fetch_raw_file( $file->raw_url );
		}
		
		return new WP_Error( 'github_api_no_content', __( 'Could not retrieve file content from gist.', 'gpt-prompt-generator' ) );
	}

	/**
	 * Fetch file content from a raw URL.
	 *
	 * @param string $url Raw file URL.
	 * @return string|WP_Error File content or WP_Error on failure.
	 */
	private static function fetch_raw_file( $url ) {
		// Prepare request arguments
		$args = self::prepare_request_args();
		
		// Make the request
		$response = wp_remote_get( $url, $args );
		
		// Check for errors
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		// Check response code
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$error_message = wp_remote_retrieve_response_message( $response );
			return new WP_Error(
				'raw_file_error',
				sprintf(
					/* translators: %1$s: HTTP status code, %2$s: Error message */
					__( 'Failed to fetch raw file: %1$s - %2$s', 'gpt-prompt-generator' ),
					$response_code,
					$error_message
				)
			);
		}
		
		// Return the content
		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Prepare request arguments for GitHub API calls.
	 *
	 * @return array Request arguments.
	 */
	private static function prepare_request_args() {
		$args = array(
			'timeout' => 15,
		);
		
		// Add authentication if token is available
		$token = self::get_token();
		if ( ! empty( $token ) ) {
			$args['headers'] = array(
				'Authorization' => 'token ' . $token,
			);
		}
		
		// Add user agent
		$args['user-agent'] = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . '; GPT-Prompt-Generator/' . GPTPG_VERSION;
		
		return $args;
	}

	/**
	 * Extract GitHub URLs from HTML content.
	 *
	 * @param string $content HTML content.
	 * @return array Array of GitHub URLs found in the content.
	 */
	public static function extract_github_urls( $content ) {
		$urls = array();
		
		// Regular expression to find GitHub URLs
		$patterns = array(
			// GitHub repository file URLs
			'#https?://github\.com/[^/\s]+/[^/\s]+/blob/[^/\s]+/[^\s]+#',
			// GitHub Gist URLs
			'#https?://gist\.github\.com/[^/\s]+/[a-f0-9]+#',
			// Raw GitHub content URLs
			'#https?://raw\.githubusercontent\.com/[^/\s]+/[^/\s]+/[^/\s]+/[^\s]+#',
		);
		
		// Find all matches
		foreach ( $patterns as $pattern ) {
			preg_match_all( $pattern, $content, $matches );
			if ( ! empty( $matches[0] ) ) {
				$urls = array_merge( $urls, $matches[0] );
			}
		}
		
		// Remove duplicates and return
		return array_unique( $urls );
	}
}

// Initialize the GitHub handler
GPTPG_GitHub_Handler::init();
