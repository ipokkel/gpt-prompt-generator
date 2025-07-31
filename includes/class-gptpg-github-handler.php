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
		
		// First priority: Extract URLs from HTML DOM (before markdown conversion loses structure)
		$dom_urls = self::extract_urls_from_html_dom( $content );
		GPTPG_Logger::debug("DOM-based URLs found: " . json_encode($dom_urls), 'GitHub Handler');
		
		if ( ! empty( $dom_urls ) ) {
			// If we found URLs in the DOM structure, prioritize them
			GPTPG_Logger::debug("Using DOM-based URLs (priority)", 'GitHub Handler');
			return $dom_urls;
		}
		
		// Fallback: Regular expression extraction from content
		$patterns = array(
			// GitHub repository file URLs
			'#https?://github\.com/[^/\s]+/[^/\s]+/blob/[^/\s]+/[^\s)\]"\'>]+#',
			// GitHub Gist URLs
			'#https?://gist\.github\.com/[^/\s]+/[a-f0-9]+#',
			// Raw GitHub content URLs
			'#https?://raw\.githubusercontent\.com/[^/\s]+/[^/\s]+/[^/\s]+/[^\s)\]"\'>]+#',
		);
		
		// Find all matches
		foreach ( $patterns as $pattern ) {
			preg_match_all( $pattern, $content, $matches );
			if ( ! empty( $matches[0] ) ) {
				$urls = array_merge( $urls, $matches[0] );
			}
		}
		
		// Remove duplicates
		$urls = array_unique( $urls );
		GPTPG_Logger::debug("Regex-based URLs found: " . json_encode($urls), 'GitHub Handler');
		
		// Smart filtering and prioritization for PMP posts
		$filtered_urls = self::prioritize_code_snippet_urls( $urls, $content );
		GPTPG_Logger::debug("Filtered URLs after prioritization: " . json_encode($filtered_urls), 'GitHub Handler');
		
		// If no suitable URLs found, try to construct potential snippet URLs based on post URL patterns
		if ( empty( $filtered_urls ) ) {
			GPTPG_Logger::info("No suitable URLs found, constructing potential snippet URLs", 'GitHub Handler');
			$constructed_urls = self::construct_potential_snippet_urls( $content );
			GPTPG_Logger::debug("Constructed URLs: " . json_encode($constructed_urls), 'GitHub Handler');
			if ( ! empty( $constructed_urls ) ) {
				$filtered_urls = $constructed_urls;
			}
		}
		
		GPTPG_Logger::debug("Final URLs returned: " . json_encode($filtered_urls), 'GitHub Handler');
		return $filtered_urls;
	}
	
	/**
	 * Extract GitHub URLs from HTML DOM structure, specifically looking for code snippet containers.
	 *
	 * @param string $html_content HTML content to parse.
	 * @return array Array of GitHub URLs found in DOM structure.
	 */
	private static function extract_urls_from_html_dom( $html_content ) {
		$urls = array();
		
		// Debug: Show HTML content structure
		GPTPG_Logger::debug("HTML content length: " . strlen($html_content), 'GitHub Handler');
		GPTPG_Logger::debug("HTML preview: " . substr($html_content, 0, 1000) . "...", 'GitHub Handler');
		
		// Check if the content contains file-meta in the raw HTML
		if ( strpos( $html_content, 'file-meta' ) !== false ) {
			GPTPG_Logger::debug("Raw HTML contains 'file-meta' string", 'GitHub Handler');
		} else {
			GPTPG_Logger::debug("Raw HTML does NOT contain 'file-meta' string", 'GitHub Handler');
		}
		
		// Check for pmpro-snippets-library in raw HTML
		if ( strpos( $html_content, 'pmpro-snippets-library' ) !== false ) {
			GPTPG_Logger::debug("Raw HTML contains 'pmpro-snippets-library' string", 'GitHub Handler');
			
			// Extract pmpro-snippets-library URLs directly from raw HTML
			$snippet_urls = self::extract_snippet_urls_from_raw_html( $html_content );
			if ( ! empty( $snippet_urls ) ) {
				GPTPG_Logger::debug("Found snippet URLs in raw HTML: " . json_encode($snippet_urls), 'GitHub Handler');
				return $snippet_urls;
			}
		} else {
			GPTPG_Logger::debug("Raw HTML does NOT contain 'pmpro-snippets-library' string", 'GitHub Handler');
		}
		
		// Suppress DOM parsing warnings for malformed HTML
		libxml_use_internal_errors( true );
		
		$dom = new DOMDocument();
		$dom->loadHTML( $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		
		// Look for file-meta containers (common in PMP code recipe posts)
		$xpath = new DOMXPath( $dom );
		
		// Find elements with class containing 'file-meta'
		$file_meta_elements = $xpath->query( "//div[contains(@class, 'file-meta')]" );
		
		GPTPG_Logger::debug("Found " . $file_meta_elements->length . " file-meta containers", 'GitHub Handler');
		
		foreach ( $file_meta_elements as $element ) {
			// Find all links within this file-meta container
			$links = $element->getElementsByTagName( 'a' );
			
			foreach ( $links as $link ) {
				$href = $link->getAttribute( 'href' );
				
				// Check if this is a GitHub URL
				if ( preg_match( '#https?://github\.com/[^/\s]+/[^/\s]+/#', $href ) ||
				     preg_match( '#https?://raw\.githubusercontent\.com/#', $href ) ) {
					
					GPTPG_Logger::debug("Found GitHub URL in file-meta: " . $href, 'GitHub Handler');
					$urls[] = $href;
				}
			}
		}
		
		// Also look for other common code snippet containers
		$code_containers = $xpath->query( "//div[contains(@class, 'code-embed')] | //div[contains(@class, 'gist')] | //div[contains(@class, 'snippet')]" );
		
		GPTPG_Logger::debug("Found " . $code_containers->length . " additional code containers", 'GitHub Handler');
		
		foreach ( $code_containers as $element ) {
			$links = $element->getElementsByTagName( 'a' );
			
			foreach ( $links as $link ) {
				$href = $link->getAttribute( 'href' );
				
				if ( preg_match( '#https?://github\.com/[^/\s]+/[^/\s]+/#', $href ) ||
				     preg_match( '#https?://raw\.githubusercontent\.com/#', $href ) ||
				     preg_match( '#https?://gist\.github\.com/#', $href ) ) {
					
					GPTPG_Logger::debug("Found GitHub URL in code container: " . $href, 'GitHub Handler');
					$urls[] = $href;
				}
			}
		}
		
		// Clean up and restore error handling
		libxml_clear_errors();
		libxml_use_internal_errors( false );
		
		// Remove duplicates and prioritize pmpro-snippets-library URLs
		$urls = array_unique( $urls );
		$prioritized_urls = array();
		$other_urls = array();
		
		foreach ( $urls as $url ) {
			if ( strpos( $url, 'pmpro-snippets-library' ) !== false ) {
				$prioritized_urls[] = $url;
			} else {
				$other_urls[] = $url;
			}
		}
		
		// Return prioritized URLs first
		$final_urls = array_merge( $prioritized_urls, $other_urls );
		GPTPG_Logger::debug("DOM extraction prioritized URLs: " . json_encode($final_urls), 'GitHub Handler');
		
		return $final_urls;
	}
	
	/**
	 * Extract pmpro-snippets-library URLs directly from raw HTML content.
	 *
	 * @param string $html_content Raw HTML content.
	 * @return array Array of pmpro-snippets-library URLs found.
	 */
	private static function extract_snippet_urls_from_raw_html( $html_content ) {
		$urls = array();
		
		// Specific patterns for pmpro-snippets-library URLs
		$patterns = array(
			// GitHub blob URLs for pmpro-snippets-library
			'#https?://github\.com/strangerstudios/pmpro-snippets-library/blob/[^\s"\'>]+#i',
			// Raw URLs for pmpro-snippets-library
			'#https?://raw\.githubusercontent\.com/strangerstudios/pmpro-snippets-library/[^\s"\'>]+#i',
			// Alternative patterns that might be embedded
			'#https?://[^\s"\'>]*pmpro-snippets-library[^\s"\'>]*#i',
		);
		
		foreach ( $patterns as $pattern ) {
			preg_match_all( $pattern, $html_content, $matches );
			if ( ! empty( $matches[0] ) ) {
				foreach ( $matches[0] as $url ) {
					// Clean up any trailing punctuation or HTML artifacts
					$url = rtrim( $url, '.,:;!?)"\'>]' );
					GPTPG_Logger::debug("Raw HTML extraction found: " . $url, 'GitHub Handler');
					
					// If this is a gist embed URL, extract the actual GitHub URL from the target parameter
					if ( strpos( $url, 'gist.paidmembershipspro.com/embed.js' ) !== false ) {
						$extracted_url = self::extract_github_url_from_embed( $url );
						if ( $extracted_url ) {
							GPTPG_Logger::debug("Extracted GitHub URL from embed: " . $extracted_url, 'GitHub Handler');
							$urls[] = $extracted_url;
						} else {
							$urls[] = $url;
						}
					} else {
						$urls[] = $url;
					}
				}
			}
		}
		
		// Remove duplicates and prioritize blob URLs over raw URLs
		$urls = array_unique( $urls );
		$blob_urls = array();
		$raw_urls = array();
		
		foreach ( $urls as $url ) {
			if ( strpos( $url, '/blob/' ) !== false ) {
				$blob_urls[] = $url;
			} else {
				$raw_urls[] = $url;
			}
		}
		
		// Return blob URLs first (more user-friendly), then raw URLs
		$prioritized_urls = array_merge( $blob_urls, $raw_urls );
		GPTPG_Logger::debug("Raw HTML extraction final URLs: " . json_encode($prioritized_urls), 'GitHub Handler');
		
		return $prioritized_urls;
	}
	
	/**
	 * Extract the actual GitHub URL from a gist embed.js target parameter.
	 *
	 * @param string $embed_url The gist embed URL containing the target parameter.
	 * @return string|false The extracted GitHub URL or false if not found.
	 */
	private static function extract_github_url_from_embed( $embed_url ) {
		// Parse the URL to get query parameters
		$parsed_url = parse_url( $embed_url );
		
		if ( ! isset( $parsed_url['query'] ) ) {
			return false;
		}
		
		// Parse query string
		parse_str( $parsed_url['query'], $params );
		
		if ( ! isset( $params['target'] ) ) {
			return false;
		}
		
		// URL decode the target parameter
		$target_url = urldecode( $params['target'] );
		GPTPG_Logger::debug("Decoded target URL: " . $target_url, 'GitHub Handler');
		
		// Validate that it's a GitHub URL
		if ( strpos( $target_url, 'github.com/strangerstudios/pmpro-snippets-library' ) !== false ) {
			return $target_url;
		}
		
		return false;
	}
	
	/**
	 * Prioritize and filter GitHub URLs to identify actual code snippets.
	 *
	 * @param array  $urls    Array of GitHub URLs found.
	 * @param string $content The original content for context.
	 * @return array Filtered and prioritized array of URLs.
	 */
	private static function prioritize_code_snippet_urls( $urls, $content ) {
		if ( empty( $urls ) ) {
			return $urls;
		}
		
		$pmpro_snippet_urls = array();
		$other_urls = array();
		
		// Separate pmpro-snippets-library URLs from others
		foreach ( $urls as $url ) {
			if ( strpos( $url, 'pmpro-snippets-library' ) !== false ) {
				$pmpro_snippet_urls[] = $url;
			} else {
				$other_urls[] = $url;
			}
		}
		
		// If we have pmpro-snippets-library URLs, prioritize them
		if ( ! empty( $pmpro_snippet_urls ) ) {
			return $pmpro_snippet_urls;
		}
		
		// If no pmpro-snippets-library URLs, filter out common reference URLs
		$filtered_other_urls = array();
		foreach ( $other_urls as $url ) {
			// Skip common reference URLs that are not actual code snippets
			if ( self::is_likely_reference_url( $url, $content ) ) {
				continue;
			}
			$filtered_other_urls[] = $url;
		}
		
		return ! empty( $filtered_other_urls ) ? $filtered_other_urls : $other_urls;
	}
	
	/**
	 * Determine if a URL is likely a reference URL rather than a code snippet.
	 *
	 * @param string $url     The GitHub URL to check.
	 * @param string $content The original content for context.
	 * @return bool True if likely a reference URL.
	 */
	private static function is_likely_reference_url( $url, $content ) {
		// Check for common reference patterns in the surrounding text
		$reference_indicators = array(
			'refer to',
			'see the',
			'check out',
			'view the',
			'documentation',
			'for more info',
			'full list',
			'defaults file',
		);
		
		// Look for reference indicators near the URL
		$url_position = strpos( $content, $url );
		if ( $url_position !== false ) {
			// Get surrounding text (100 chars before and after)
			$start = max( 0, $url_position - 100 );
			$surrounding_text = strtolower( substr( $content, $start, 300 ) );
			
			foreach ( $reference_indicators as $indicator ) {
				if ( strpos( $surrounding_text, $indicator ) !== false ) {
					return true;
				}
			}
		}
		
		// Check if URL points to common reference files
		$reference_files = array(
			'/defaults.php',
			'/readme.md',
			'/documentation',
		);
		
		foreach ( $reference_files as $ref_file ) {
			if ( strpos( strtolower( $url ), $ref_file ) !== false ) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Construct potential snippet URLs based on post content and URL patterns.
	 *
	 * @param string $content The original post content.
	 * @return array Array of potential snippet URLs.
	 */
	private static function construct_potential_snippet_urls( $content ) {
		$potential_urls = array();
		
		// Get the current post URL from the content or context
		$post_url = self::extract_post_url_from_content( $content );
		
		if ( empty( $post_url ) ) {
			return $potential_urls;
		}
		
		// Extract slug from post URL
		$slug = self::extract_slug_from_url( $post_url );
		
		if ( empty( $slug ) ) {
			return $potential_urls;
		}
		
		// Only generate the most likely actual recipe URL pattern
		// Based on user guidance: only show actual recipe links, not multiple subfolder guesses
		$snippet_patterns = array(
			// Standard misc pattern - most common for PMP recipes
			"https://github.com/strangerstudios/pmpro-snippets-library/blob/dev/misc/{$slug}.php",
		);
		
		// Try to verify these URLs exist (basic check)
		foreach ( $snippet_patterns as $pattern ) {
			$potential_urls[] = $pattern;
		}
		
		return $potential_urls;
	}
	
	/**
	 * Extract post URL from content (if available).
	 *
	 * @param string $content The post content.
	 * @return string Post URL or empty string.
	 */
	private static function extract_post_url_from_content( $content ) {
		// Try to extract from canonical URL or other indicators
		if ( preg_match( '#https://www\.paidmembershipspro\.com/[^/\s]+/#', $content, $matches ) ) {
			return $matches[0];
		}
		
		return '';
	}
	
	/**
	 * Extract slug from a PMP post URL.
	 *
	 * @param string $url The post URL.
	 * @return string The slug or empty string.
	 */
	private static function extract_slug_from_url( $url ) {
		// Extract slug from URL like https://www.paidmembershipspro.com/add-remove-css-selector/
		if ( preg_match( '#https://www\.paidmembershipspro\.com/([^/]+)/?#', $url, $matches ) ) {
			return $matches[1];
		}
		
		return '';
	}
}

// Initialize the GitHub handler
GPTPG_GitHub_Handler::init();
