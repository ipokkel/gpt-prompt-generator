<?php
/**
 * GPTPG Shortcode Handler
 *
 * Handles shortcodes for displaying forms on the front-end.
 *
 * @package GPT_Prompt_Generator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GPTPG_Shortcodes class
 *
 * Manages all shortcodes used by the plugin.
 */
class GPTPG_Shortcodes {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		// Register shortcodes
		add_shortcode( 'gptpg_prompt_form', array( __CLASS__, 'prompt_form_shortcode' ) );
		
		// Add shortcode to the specified page if set
		add_action( 'the_content', array( __CLASS__, 'maybe_add_form_to_page' ) );
	}

	/**
	 * Shortcode for displaying the prompt generation form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function prompt_form_shortcode( $atts = array() ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			// Filter to customize the restricted access message
			$message = apply_filters( 
				'gptpg_restricted_access_message', 
				__( 'You must be logged in to access this feature.', 'gpt-prompt-generator' ) 
			);
			return '<div class="gptpg-restricted-access">' . esc_html( $message ) . '</div>';
		}

		// Parse attributes
		$atts = shortcode_atts(
			array(
				'form_id' => 'gptpg-form',
			),
			$atts,
			'gptpg_prompt_form'
		);

		// Enqueue required assets
		wp_enqueue_style( 'gptpg-form-styles' );
		wp_enqueue_script( 'gptpg-form-scripts' );
		
		// Note: Script variables are now localized in the main plugin file
		// This prevents duplication and ensures consistent variable availability

		// Start output buffering
		ob_start();

		// Include multi-step form template
		self::get_template( 'multi-step-form', array( 'atts' => $atts ) );

		// Return the buffered content
		return ob_get_clean();
	}

	/**
	 * Add form to the specified page if set in settings.
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public static function maybe_add_form_to_page( $content ) {
		// Get the selected page ID from settings
		$page_id = get_option( 'gptpg_form_page_id', 0 );
		
		// Check if we're on the selected page and it's not in the admin
		if ( ! is_admin() && $page_id && is_page( $page_id ) ) {
			// Add the form shortcode to the content
			$content .= do_shortcode( '[gptpg_prompt_form]' );
		}
		
		return $content;
	}

	/**
	 * Get template file with variables.
	 *
	 * @param string $template Template name (without extension).
	 * @param array  $args     Variables to pass to the template.
	 */
	public static function get_template( $template, $args = array() ) {
		// Extract variables for use in the template
		if ( ! empty( $args ) && is_array( $args ) ) {
			extract( $args );
		}
		
		// Template file path
		$template_file = GPTPG_PLUGIN_DIR . 'templates/' . $template . '.php';
		
		// Check if template exists
		if ( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			// Check for PHP templates in assets/html folder
			$php_file = GPTPG_PLUGIN_DIR . 'assets/html/' . $template . '.php';
			if ( file_exists( $php_file ) ) {
				include $php_file;
			} else {
				// Fallback to old HTML files for backward compatibility
				$html_file = GPTPG_PLUGIN_DIR . 'assets/html/' . $template . '.html';
				if ( file_exists( $html_file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					echo wp_kses_post( file_get_contents( $html_file ) );
				} else {
					echo '<!-- Template not found: ' . esc_html( $template ) . ' -->';
				}
			}
		}
	}
}

// Initialize the shortcodes
GPTPG_Shortcodes::init();
