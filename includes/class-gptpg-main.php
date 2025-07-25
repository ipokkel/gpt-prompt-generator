<?php
/**
 * Main plugin class
 *
 * @package GPT_Prompt_Generator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for main plugin functionality
 */
class GPTPG_Plugin {

	/**
	 * Singleton instance
	 *
	 * @var GPTPG_Plugin $instance
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance
	 *
	 * @return GPTPG_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Include required files
		$this->includes();

		// Hook into WordPress
		$this->hooks();
	}

	/**
	 * Include required files
	 */
	private function includes() {
		// Database handler
		require_once GPTPG_PLUGIN_DIR . 'includes/class-gptpg-database.php';

		// Admin settings
		if ( is_admin() ) {
			require_once GPTPG_PLUGIN_DIR . 'admin/class-gptpg-admin.php';
		}

		// Frontend
		require_once GPTPG_PLUGIN_DIR . 'includes/class-gptpg-form-handler.php';
		require_once GPTPG_PLUGIN_DIR . 'includes/class-gptpg-shortcodes.php';
		require_once GPTPG_PLUGIN_DIR . 'includes/class-gptpg-github-handler.php';
		require_once GPTPG_PLUGIN_DIR . 'includes/class-gptpg-prompt-generator.php';
	}

	/**
	 * Hook into WordPress
	 */
	private function hooks() {
		// Register activation hook
		register_activation_hook( GPTPG_PLUGIN_FILE, array( $this, 'activate' ) );

		// Register deactivation hook
		register_deactivation_hook( GPTPG_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// Load plugin text domain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Register assets
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// Register AJAX handlers
		add_action( 'wp_ajax_gptpg_fetch_post', array( 'GPTPG_Form_Handler', 'ajax_fetch_post' ) );
		add_action( 'wp_ajax_nopriv_gptpg_fetch_post', array( 'GPTPG_Form_Handler', 'ajax_fetch_post' ) );
		add_action( 'wp_ajax_gptpg_store_markdown', array( 'GPTPG_Form_Handler', 'ajax_store_markdown' ) );
		add_action( 'wp_ajax_gptpg_process_snippets', array( 'GPTPG_Form_Handler', 'ajax_process_snippets' ) );
		add_action( 'wp_ajax_gptpg_generate_prompt', array( 'GPTPG_Form_Handler', 'ajax_generate_prompt' ) );

		// Register cleanup scheduled event
		add_action( 'gptpg_cleanup_expired_data', array( 'GPTPG_Database', 'cleanup_expired_data' ) );
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create database tables
		GPTPG_Database::create_tables();

		// Maybe add default settings
		$this->maybe_add_default_settings();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clear scheduled events
		wp_clear_scheduled_hook( 'gptpg_cleanup_expired_data' );
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'gpt-prompt-generator',
			false,
			dirname( GPTPG_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Register plugin assets
	 */
	public function register_assets() {
		// Register styles
		wp_register_style(
			'gptpg-styles',
			GPTPG_PLUGIN_URL . 'assets/css/gptpg-frontend.css',
			array(),
			GPTPG_VERSION
		);

		// Register form styles
		wp_register_style(
			'gptpg-form-styles',
			GPTPG_PLUGIN_URL . 'assets/css/gptpg-form.css',
			array(),
			GPTPG_VERSION
		);

		// Register scripts
		wp_register_script(
			'gptpg-scripts',
			GPTPG_PLUGIN_URL . 'assets/js/gptpg-frontend.js',
			array( 'jquery' ),
			GPTPG_VERSION,
			true
		);

		// Register form scripts
		wp_register_script(
			'gptpg-form-scripts',
			GPTPG_PLUGIN_URL . 'assets/js/gptpg-form.js',
			array( 'jquery' ),
			GPTPG_VERSION,
			true
		);

		// Localize scripts
		$script_vars = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'gptpg-nonce' ),
			'i18n'     => array(
				'error_fetch'      => esc_html__( 'Error fetching post. Please check the URL.', 'gpt-prompt-generator' ),
				'error_snippets'   => esc_html__( 'Error processing code snippets.', 'gpt-prompt-generator' ),
				'error_prompt'     => esc_html__( 'Error generating prompt.', 'gpt-prompt-generator' ),
				'copied'           => esc_html__( 'Copied to clipboard!', 'gpt-prompt-generator' ),
				'copy_failed'      => esc_html__( 'Copy failed. Please select and copy manually.', 'gpt-prompt-generator' ),
			),
		);

		// Localize both scripts with the same variables
		wp_localize_script('gptpg-scripts', 'gptpg_vars', $script_vars);
		wp_localize_script('gptpg-form-scripts', 'gptpg_vars', $script_vars);
	}

	/**
	 * Add default settings
	 */
	private function maybe_add_default_settings() {
		// Default prompt template (from grok-prompt-brief.md)
		if ( ! get_option( 'gptpg_prompt_template' ) ) {
			// Get default template from the brief file
			$default_template_path = GPTPG_PLUGIN_DIR . 'briefs/grok-prompt-brief.md';
			if ( file_exists( $default_template_path ) ) {
				$file_contents = file_get_contents( $default_template_path );
				$pattern = '/```text\s*(.+?)```/s';
				if ( preg_match( $pattern, $file_contents, $matches ) ) {
					$default_template = trim( $matches[1] );
					update_option( 'gptpg_prompt_template', $default_template );
				}
			}
		}

		// Other default settings
		if ( ! get_option( 'gptpg_github_token' ) ) {
			update_option( 'gptpg_github_token', '' );
		}

		if ( ! get_option( 'gptpg_expiry_time' ) ) {
			// Default: 60 minutes (in seconds)
			update_option( 'gptpg_expiry_time', 3600 );
		}

		if ( ! get_option( 'gptpg_form_page_id' ) ) {
			update_option( 'gptpg_form_page_id', 0 );
		}
	}
}
