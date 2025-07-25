<?php
/**
 * GPTPG Admin
 *
 * Handles admin settings and configuration.
 *
 * @package GPT_Prompt_Generator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GPTPG_Admin class
 *
 * Manages admin settings and functionality.
 */
class GPTPG_Admin {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		// Register settings page
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		
		// Register settings
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		
		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . GPTPG_PLUGIN_BASENAME, array( __CLASS__, 'add_settings_link' ) );
		
		// Enqueue admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the settings page.
	 */
	public static function register_settings_page() {
		// Define the menu slugs
		$main_menu_slug = 'gptpg-dashboard'; // Changed this to be unique
		$settings_slug = 'gptpg-settings';
		
		// Add main menu item
		add_menu_page(
			__( 'GPT Prompt Generator', 'gpt-prompt-generator' ),
			__( 'GPT Prompt Generator', 'gpt-prompt-generator' ),
			'manage_options',
			$main_menu_slug, // This is the slug for the main menu
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-format-chat',
			30
		);
		
		// Add a Dashboard submenu that points to the same page as the main menu
		add_submenu_page(
			$main_menu_slug,
			__( 'Dashboard', 'gpt-prompt-generator' ),
			__( 'Dashboard', 'gpt-prompt-generator' ),
			'manage_options',
			$main_menu_slug,
			array( __CLASS__, 'render_dashboard_page' )
		);
		
		// Add Settings submenu
		add_submenu_page(
			$main_menu_slug,
			__( 'Settings', 'gpt-prompt-generator' ),
			__( 'Settings', 'gpt-prompt-generator' ),
			'manage_options',
			$settings_slug,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public static function register_settings() {
		// Register settings group
		register_setting(
			'gptpg_settings_group',
			'gptpg_prompt_template',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_prompt_template' ),
			)
		);
		
		register_setting(
			'gptpg_settings_group',
			'gptpg_github_token',
			array(
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		
		register_setting(
			'gptpg_settings_group',
			'gptpg_expiry_time',
			array(
				'sanitize_callback' => 'absint',
			)
		);
		
		register_setting(
			'gptpg_settings_group',
			'gptpg_form_page_id',
			array(
				'sanitize_callback' => 'absint',
			)
		);
		
		// Content Fetching Settings
		register_setting(
			'gptpg_settings_group',
			'gptpg_fetch_strategy',
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default' => 'enhanced',
			)
		);
		
		register_setting(
			'gptpg_settings_group',
			'gptpg_debug_logging',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default' => false,
			)
		);

		// Add settings sections
		add_settings_section(
			'gptpg_prompt_template_section',
			__( 'Prompt Template Settings', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_prompt_template_section' ),
			'gptpg-settings'
		);
		
		add_settings_section(
			'gptpg_github_section',
			__( 'GitHub Settings', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_github_section' ),
			'gptpg-settings'
		);
		
		add_settings_section(
			'gptpg_advanced_section',
			__( 'Advanced Settings', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_advanced_section' ),
			'gptpg-settings'
		);
		
		add_settings_section(
			'gptpg_frontend_section',
			__( 'Front-End Settings', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_frontend_section' ),
			'gptpg-settings'
		);
		
		add_settings_section(
			'gptpg_content_fetching_section',
			__( 'Content Fetching Settings', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_content_fetching_section' ),
			'gptpg-settings'
		);

		// Add settings fields
		add_settings_field(
			'gptpg_prompt_template',
			__( 'Prompt Template', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_prompt_template_field' ),
			'gptpg-settings',
			'gptpg_prompt_template_section'
		);
		
		add_settings_field(
			'gptpg_github_token',
			__( 'GitHub Personal Access Token', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_github_token_field' ),
			'gptpg-settings',
			'gptpg_github_section'
		);
		
		add_settings_field(
			'gptpg_expiry_time',
			__( 'Data Expiry Time (minutes)', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_expiry_time_field' ),
			'gptpg-settings',
			'gptpg_advanced_section'
		);
		
		add_settings_field(
			'gptpg_form_page_id',
			__( 'Form Page', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_form_page_field' ),
			'gptpg-settings',
			'gptpg_frontend_section'
		);
		
		// Content Fetching Fields
		add_settings_field(
			'gptpg_fetch_strategy',
			__( 'Fetch Strategy', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_fetch_strategy_field' ),
			'gptpg-settings',
			'gptpg_content_fetching_section'
		);
		
		add_settings_field(
			'gptpg_debug_logging',
			__( 'Debug Logging', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_debug_logging_field' ),
			'gptpg-settings',
			'gptpg_content_fetching_section'
		);
	}

	/**
	 * Sanitize prompt template.
	 *
	 * @param string $value The prompt template.
	 * @return string Sanitized template.
	 */
	public static function sanitize_prompt_template( $value ) {
		// Don't strictly sanitize the template content, as it may contain necessary formatting
		// Just make sure it's not empty
		if ( empty( trim( $value ) ) ) {
			add_settings_error(
				'gptpg_prompt_template',
				'gptpg_prompt_template_error',
				__( 'Prompt template cannot be empty.', 'gpt-prompt-generator' ),
				'error'
			);
			// Return the previous value
			return get_option( 'gptpg_prompt_template' );
		}

		// Check for required placeholders
		$required_placeholders = array(
			'[link_code_recipe]',
			'[existing_post_content]',
			'[post_title]',
		);

		$missing_placeholders = array();
		foreach ( $required_placeholders as $placeholder ) {
			if ( false === strpos( $value, $placeholder ) ) {
				$missing_placeholders[] = $placeholder;
			}
		}

		// If placeholders are missing, show error and return previous value
		if ( ! empty( $missing_placeholders ) ) {
			add_settings_error(
				'gptpg_prompt_template',
				'gptpg_prompt_template_placeholders_error',
				sprintf(
					/* translators: %s: list of missing placeholders */
					__( 'The prompt template is missing required placeholders: %s', 'gpt-prompt-generator' ),
					implode( ', ', $missing_placeholders )
				),
				'error'
			);
			// Return the previous value
			return get_option( 'gptpg_prompt_template' );
		}

		return $value;
	}

	/**
	 * Render the dashboard page.
	 */
	public static function render_dashboard_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Welcome to the GPT Prompt Generator dashboard.', 'gpt-prompt-generator' ); ?></p>
			
			<div class="gptpg-dashboard-container">
				<div class="gptpg-dashboard-section">
					<h2><?php esc_html_e( 'Quick Links', 'gpt-prompt-generator' ); ?></h2>
					<ul>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=gptpg-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'gpt-prompt-generator' ); ?></a></li>
					</ul>
				</div>
				
				<div class="gptpg-dashboard-section">
					<h2><?php esc_html_e( 'Getting Started', 'gpt-prompt-generator' ); ?></h2>
					<p><?php esc_html_e( 'To use the prompt generator form, add the following shortcode to any page:', 'gpt-prompt-generator' ); ?></p>
					<code>[gptpg_prompt_form]</code>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public static function render_settings_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Configure settings for the GPT Prompt Generator plugin.', 'gpt-prompt-generator' ); ?></p>
			
			<form action="options.php" method="post">
				<?php
				// Output security fields
				settings_fields( 'gptpg_settings_group' );
				
				// Output settings sections
				do_settings_sections( 'gptpg-settings' );
				
				// Output save button
				submit_button();
				?>
			</form>

			<hr>
			
			<div class="gptpg-info">
				<h2><?php esc_html_e( 'Plugin Information', 'gpt-prompt-generator' ); ?></h2>
				<p><?php esc_html_e( 'To use the prompt generator form, add the following shortcode to any page:', 'gpt-prompt-generator' ); ?></p>
				<code>[gptpg_prompt_form]</code>
				
				<p><?php esc_html_e( 'Alternatively, select a page from the "Form Page" dropdown in the Front-End Settings section.', 'gpt-prompt-generator' ); ?></p>
				
				<h3><?php esc_html_e( 'Available Placeholders', 'gpt-prompt-generator' ); ?></h3>
				<ul>
					<li><code>[link_code_recipe]</code> - <?php esc_html_e( 'Replaced with the raw code from GitHub repositories or gists.', 'gpt-prompt-generator' ); ?></li>
					<li><code>[existing_post_content]</code> - <?php esc_html_e( 'Replaced with the fetched post content in Markdown format.', 'gpt-prompt-generator' ); ?></li>
					<li><code>[post_title]</code> - <?php esc_html_e( 'Replaced with the title of the fetched post.', 'gpt-prompt-generator' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the prompt template section description.
	 */
	public static function render_prompt_template_section() {
		echo '<p>' . esc_html__( 'Customize the prompt template that will be used to generate prompts for ChatGPT.', 'gpt-prompt-generator' ) . '</p>';
	}

	/**
	 * Render the GitHub section description.
	 */
	public static function render_github_section() {
		echo '<p>' . esc_html__( 'Configure GitHub integration settings.', 'gpt-prompt-generator' ) . '</p>';
	}

	/**
	 * Render the advanced section description.
	 */
	public static function render_advanced_section() {
		echo '<p>' . esc_html__( 'Configure advanced plugin settings.', 'gpt-prompt-generator' ) . '</p>';
	}

	/**
	 * Render the frontend section description.
	 */
	public static function render_frontend_section() {
		echo '<p>' . esc_html__( 'Configure how the plugin integrates with your site\'s front-end.', 'gpt-prompt-generator' ) . '</p>';
	}

	/**
	 * Render the prompt template field.
	 */
	public static function render_prompt_template_field() {
		$template = get_option( 'gptpg_prompt_template', '' );
		?>
		<textarea id="gptpg_prompt_template" name="gptpg_prompt_template" class="large-text code" rows="20"><?php echo esc_textarea( $template ); ?></textarea>
		<p class="description">
			<?php
			echo wp_kses(
				__( 'The template for generating prompts. Use <code>[link_code_recipe]</code>, <code>[existing_post_content]</code>, and <code>[post_title]</code> as placeholders.', 'gpt-prompt-generator' ),
				array(
					'code' => array(),
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the GitHub token field.
	 */
	public static function render_github_token_field() {
		$token = get_option( 'gptpg_github_token', '' );
		?>
		<input type="password" id="gptpg_github_token" name="gptpg_github_token" value="<?php echo esc_attr( $token ); ?>" class="regular-text" autocomplete="new-password">
		<p class="description">
			<?php
			echo wp_kses(
				__( 'Optional: A GitHub Personal Access Token to increase API rate limits. <a href="https://github.com/settings/tokens" target="_blank" rel="noopener noreferrer">Create a token</a> with <code>repo</code> and <code>gist</code> scopes.', 'gpt-prompt-generator' ),
				array(
					'a'    => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
					'code' => array(),
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the expiry time field.
	 */
	public static function render_expiry_time_field() {
		// Get option value in seconds, convert to minutes
		$expiry_time = get_option( 'gptpg_expiry_time', 3600 ) / 60;
		?>
		<input type="number" id="gptpg_expiry_time" name="gptpg_expiry_time" value="<?php echo esc_attr( $expiry_time ); ?>" class="small-text" min="5" step="1">
		<p class="description">
			<?php esc_html_e( 'Time in minutes before temporary data is deleted from the database. Minimum 5 minutes.', 'gpt-prompt-generator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the form page field.
	 */
	public static function render_form_page_field() {
		$selected = get_option( 'gptpg_form_page_id', 0 );
		
		// Get all published pages
		$pages = get_pages( array(
			'post_status' => 'publish',
		) );
		?>
		<select id="gptpg_form_page_id" name="gptpg_form_page_id">
			<option value="0"><?php esc_html_e( '— Select a page —', 'gpt-prompt-generator' ); ?></option>
			<?php
			foreach ( $pages as $page ) {
				echo '<option value="' . esc_attr( $page->ID ) . '"' . selected( $selected, $page->ID, false ) . '>' . esc_html( $page->post_title ) . '</option>';
			}
			?>
		</select>
		<p class="description">
			<?php
			echo wp_kses(
				__( 'Select a page where the prompt generator form should appear automatically. Alternatively, use the <code>[gptpg_prompt_form]</code> shortcode on any page.', 'gpt-prompt-generator' ),
				array(
					'code' => array(),
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Add settings link to the plugins page.
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified plugin action links.
	 */
	public static function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=gptpg-dashboard' ) . '">' . __( 'Settings', 'gpt-prompt-generator' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		// Only load on plugin settings page
		if ( 'settings_page_gptpg-settings' !== $hook_suffix ) {
			return;
		}
		
		// Register admin styles
		wp_enqueue_style(
			'gptpg-admin-styles',
			GPTPG_PLUGIN_URL . 'admin/css/gptpg-admin.css',
			array(),
			GPTPG_VERSION
		);
		
		// Register admin scripts
		wp_enqueue_script(
			'gptpg-admin-scripts',
			GPTPG_PLUGIN_URL . 'admin/js/gptpg-admin.js',
			array( 'jquery' ),
			GPTPG_VERSION,
			true
		);
	}

	/**
	 * Render the content fetching section.
	 */
	public static function render_content_fetching_section() {
		?>
		<p><?php esc_html_e( 'Configure how the plugin fetches content from external URLs. These settings help bypass paywalls and membership restrictions.', 'gpt-prompt-generator' ); ?></p>
		<?php
	}

	/**
	 * Render the fetch strategy field.
	 */
	public static function render_fetch_strategy_field() {
		$selected = get_option( 'gptpg_fetch_strategy', 'enhanced' );
		$strategies = array(
			'enhanced'        => __( 'Enhanced (Recommended) - Try all methods with intelligent fallback', 'gpt-prompt-generator' ),
			'cookie_free_only' => __( 'Cookie-Free Only - Only attempt cookie-free fetching', 'gpt-prompt-generator' ),
			'internal_only'   => __( 'Internal Only - Only fetch from same domain', 'gpt-prompt-generator' ),
			'standard'        => __( 'Standard - Simple HTTP request (legacy behavior)', 'gpt-prompt-generator' ),
		);
		?>
		<select id="gptpg_fetch_strategy" name="gptpg_fetch_strategy">
			<?php foreach ( $strategies as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $selected, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Choose the content fetching strategy. Enhanced mode tries multiple methods to bypass restrictions like PMPro Limit Post Views.', 'gpt-prompt-generator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the debug logging field.
	 */
	public static function render_debug_logging_field() {
		$checked = get_option( 'gptpg_debug_logging', false );
		?>
		<input type="checkbox" id="gptpg_debug_logging" name="gptpg_debug_logging" value="1" <?php checked( $checked ); ?>>
		<label for="gptpg_debug_logging"><?php esc_html_e( 'Enable debug logging for content fetching attempts', 'gpt-prompt-generator' ); ?></label>
		<p class="description">
			<?php esc_html_e( 'When enabled, detailed logs of content fetching attempts will be written to the PHP error log for troubleshooting.', 'gpt-prompt-generator' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize checkbox value.
	 *
	 * @param mixed $value The checkbox value.
	 * @return bool Sanitized boolean value.
	 */
	public static function sanitize_checkbox( $value ) {
		return ! empty( $value );
	}
}

// Initialize the admin class
GPTPG_Admin::init();
