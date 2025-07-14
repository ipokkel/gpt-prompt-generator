<?php
/**
 * GPTPG Prompt Generator
 *
 * Handles prompt generation based on templates and fetched data.
 *
 * @package GPT_Prompt_Generator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GPTPG_Prompt_Generator class
 *
 * Manages the prompt generation logic.
 */
class GPTPG_Prompt_Generator {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		// Nothing to initialize for now
	}

	/**
	 * Generate a prompt from the template using post data and code snippets.
	 *
	 * @param object $post_data Post data from database.
	 * @param array  $snippets  Array of code snippets.
	 * @return string Generated prompt.
	 */
	public static function generate_prompt( $post_data, $snippets ) {
		// Get the prompt template from settings
		$template = get_option( 'gptpg_prompt_template', '' );
		
		if ( empty( $template ) ) {
			return __( 'Error: No prompt template found. Please configure the template in the plugin settings.', 'gpt-prompt-generator' );
		}
		
		// Prepare replacement data
		$post_title = $post_data->post_title;
		$post_content_markdown = $post_data->post_content_markdown;
		
		// Prepare code snippets
		$code_snippets = array();
		foreach ( $snippets as $snippet ) {
			if ( ! empty( $snippet->snippet_content ) ) {
				$code_snippets[] = array(
					'url'     => $snippet->snippet_url,
					'content' => $snippet->snippet_content,
				);
			}
		}
		
		// Replace placeholders in the template
		$prompt = $template;
		
		// Replace [post_title] with the post title
		$prompt = str_replace( '[post_title]', $post_title, $prompt );
		
		// Replace [existing_post_content] with the markdown content
		$prompt = str_replace( '[existing_post_content]', $post_content_markdown, $prompt );
		
		// Replace [link_code_recipe] with code snippets
		if ( ! empty( $code_snippets ) ) {
			$code_block = '';
			
			foreach ( $code_snippets as $snippet ) {
				// Add URL as comment at the top of the code block
				$code_block .= "// Source: {$snippet['url']}\n";
				$code_block .= "{$snippet['content']}\n\n";
			}
			
			$prompt = str_replace( '[link_code_recipe]', $code_block, $prompt );
		} else {
			$prompt = str_replace( '[link_code_recipe]', __( '[No code snippets found]', 'gpt-prompt-generator' ), $prompt );
		}
		
		// Apply filters for custom modifications
		$prompt = apply_filters( 'gptpg_generated_prompt', $prompt, $post_data, $snippets );
		
		return $prompt;
	}

	/**
	 * Get placeholder descriptions for the admin settings.
	 *
	 * @return array Array of placeholders and their descriptions.
	 */
	public static function get_placeholder_descriptions() {
		return array(
			'[link_code_recipe]'      => __( 'Replaced with the raw code from GitHub repositories or gists.', 'gpt-prompt-generator' ),
			'[existing_post_content]' => __( 'Replaced with the fetched post content in Markdown format.', 'gpt-prompt-generator' ),
			'[post_title]'            => __( 'Replaced with the title of the fetched post.', 'gpt-prompt-generator' ),
		);
	}

	/**
	 * Validate a prompt template for required placeholders.
	 *
	 * @param string $template Prompt template to validate.
	 * @return bool|array True if valid, array of missing placeholders if invalid.
	 */
	public static function validate_template( $template ) {
		// Required placeholders
		$required_placeholders = array(
			'[link_code_recipe]',
			'[existing_post_content]',
			'[post_title]',
		);
		
		$missing = array();
		
		foreach ( $required_placeholders as $placeholder ) {
			if ( false === strpos( $template, $placeholder ) ) {
				$missing[] = $placeholder;
			}
		}
		
		return empty( $missing ) ? true : $missing;
	}
}

// Initialize the prompt generator
GPTPG_Prompt_Generator::init();
