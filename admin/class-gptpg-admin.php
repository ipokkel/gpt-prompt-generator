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
				'sanitize_callback' => array( __CLASS__, 'sanitize_expiry_time' ),
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
		
		// New Debug Logging Settings
		register_setting(
			'gptpg_settings_group',
			'gptpg_debug_mode',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_debug_mode' ),
				'default' => 'review',
			)
		);
		
		register_setting(
			'gptpg_settings_group',
			'gptpg_use_plugin_logs',
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
		
		// New Debug Logging Section
		add_settings_section(
			'gptpg_debug_section',
			__( 'Debug & Logging Settings', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_debug_section' ),
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
		
		// New Debug Logging Fields
		add_settings_field(
			'gptpg_debug_mode',
			__( 'Debug Mode', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_debug_mode_field' ),
			'gptpg-settings',
			'gptpg_debug_section'
		);
		
		add_settings_field(
			'gptpg_use_plugin_logs',
			__( 'Use Plugin Log Files', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_use_plugin_logs_field' ),
			'gptpg-settings',
			'gptpg_debug_section'
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
		$stored_value = get_option( 'gptpg_expiry_time', 3600 );
		
		// Detect if the stored value is in minutes (incorrect) or seconds (correct)
		// Values less than 300 are likely stored incorrectly as minutes instead of seconds
		if ( $stored_value < 300 ) {
			// Value is likely stored as minutes, use it directly
			$expiry_time = max( 5, $stored_value );
		} else {
			// Value is stored as seconds, convert to minutes for display
			$expiry_time = $stored_value / 60;
		}
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

	/**
	 * Sanitize debug mode value.
	 *
	 * @param string $value The debug mode value.
	 * @return string Sanitized debug mode value.
	 */
	public static function sanitize_debug_mode( $value ) {
		$allowed_modes = array( 'production', 'review', 'debug' );
		return in_array( $value, $allowed_modes, true ) ? $value : 'review';
	}

	/**
	 * Sanitize expiry time value.
	 * Convert minutes input to seconds for storage.
	 *
	 * @param mixed $value The expiry time value in minutes.
	 * @return int Sanitized expiry time value in seconds.
	 */
	public static function sanitize_expiry_time( $value ) {
		$minutes = absint( $value );
		
		// Minimum 5 minutes
		if ( $minutes < 5 ) {
			$minutes = 5;
		}
		
		// Convert minutes to seconds for storage
		return $minutes * 60;
	}

	/**
	 * Render the debug section description.
	 */
	public static function render_debug_section() {
		$current_mode = GPTPG_Logger::get_debug_mode();
		$mode_source = GPTPG_Logger::get_debug_mode_source();
		$is_logging = GPTPG_Logger::is_logging_enabled();
		$console_debug = GPTPG_Logger::is_console_debug_enabled();
		
		$mode_colors = array(
			'production' => '#28a745',
			'review' => '#ffc107',
			'debug' => '#dc3545'
		);
		
		$mode_color = isset( $mode_colors[ $current_mode ] ) ? $mode_colors[ $current_mode ] : '#6c757d';
		?>
		<p><?php esc_html_e( 'Configure debug logging settings to help with troubleshooting and issue reporting.', 'gpt-prompt-generator' ); ?></p>
		
		<div class="gptpg-debug-status" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid <?php echo esc_attr( $mode_color ); ?>;">
			<h4 style="margin-top: 0;"><?php esc_html_e( 'Current Debug Status', 'gpt-prompt-generator' ); ?></h4>
			<p><strong><?php esc_html_e( 'Mode:', 'gpt-prompt-generator' ); ?></strong> 
				<span class="gptpg-mode-badge" style="background: <?php echo esc_attr( $mode_color ); ?>; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px; text-transform: uppercase;">
					<?php echo esc_html( ucfirst( $current_mode ) ); ?>
				</span>
			</p>
			<p><strong><?php esc_html_e( 'Source:', 'gpt-prompt-generator' ); ?></strong> <?php echo esc_html( $mode_source ); ?></p>
			<p><strong><?php esc_html_e( 'Logging:', 'gpt-prompt-generator' ); ?></strong> <?php echo $is_logging ? '<span style="color: #28a745;">✓ Enabled</span>' : '<span style="color: #dc3545;">✗ Disabled</span>'; ?></p>
			<p><strong><?php esc_html_e( 'Console Debug:', 'gpt-prompt-generator' ); ?></strong> <?php echo $console_debug ? '<span style="color: #28a745;">✓ Enabled</span>' : '<span style="color: #dc3545;">✗ Disabled</span>'; ?></p>
		</div>
		<?php
	}

	/**
	 * Render debug mode field
	 */
	public static function render_debug_mode_field() {
		$current_mode = get_option( 'gptpg_debug_mode', 'review' );
		$is_disabled = defined( 'GPTPG_DEBUG_MODE' );
		?>
		<fieldset>
			<label>
				<input type="radio" name="gptpg_debug_mode" value="production" <?php checked( $current_mode, 'production' ); ?> <?php disabled( $is_disabled ); ?> />
				<strong><?php esc_html_e( 'Production', 'gpt-prompt-generator' ); ?></strong>
				<p class="description"><?php esc_html_e( 'No debug logging (clean user experience)', 'gpt-prompt-generator' ); ?></p>
			</label>
			<br><br>
			<label>
				<input type="radio" name="gptpg_debug_mode" value="review" <?php checked( $current_mode, 'review' ); ?> <?php disabled( $is_disabled ); ?> />
				<strong><?php esc_html_e( 'Review', 'gpt-prompt-generator' ); ?></strong>
				<p class="description"><?php esc_html_e( 'Info, warning, and error logging (recommended for testing and support)', 'gpt-prompt-generator' ); ?></p>
			</label>
			<br><br>
			<label>
				<input type="radio" name="gptpg_debug_mode" value="debug" <?php checked( $current_mode, 'debug' ); ?> <?php disabled( $is_disabled ); ?> />
				<strong><?php esc_html_e( 'Debug', 'gpt-prompt-generator' ); ?></strong>
				<p class="description"><?php esc_html_e( 'All logging including debug messages (for developers)', 'gpt-prompt-generator' ); ?></p>
			</label>
		</fieldset>

		<?php if ( $is_disabled ) : ?>
			<p class="description" style="color: #d63384;">
				<?php esc_html_e( 'Debug mode is controlled by the GPTPG_DEBUG_MODE constant and cannot be changed here.', 'gpt-prompt-generator' ); ?>
			</p>
		<?php endif; ?>

		<div id="gptpg-review-guidance" style="<?php echo ( $current_mode === 'review' && ! $is_disabled ) ? '' : 'display: none;'; ?>">
			<div class="notice notice-info inline">
				<h4><?php esc_html_e( '🧪 Review Mode - Issue Reporting Guide', 'gpt-prompt-generator' ); ?></h4>
				<p><?php esc_html_e( 'Review mode enables detailed logging to help identify and resolve issues. When reporting problems, please collect the following information:', 'gpt-prompt-generator' ); ?></p>
				
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 15px 0;">
					<div>
						<h5><?php esc_html_e( '📊 Debug Information to Collect:', 'gpt-prompt-generator' ); ?></h5>
						<ol>
							<li><strong><?php esc_html_e( 'Browser Console Logs:', 'gpt-prompt-generator' ); ?></strong><br>
								<small><?php esc_html_e( 'Press F12 → Console tab → Filter for "GPTPG" → Copy all messages', 'gpt-prompt-generator' ); ?></small></li>
							<li><strong><?php esc_html_e( 'WordPress Debug Log:', 'gpt-prompt-generator' ); ?></strong><br>
								<small><?php esc_html_e( 'Check /wp-content/debug.log for GPTPG entries (timestamp important)', 'gpt-prompt-generator' ); ?></small></li>
							<li><strong><?php esc_html_e( 'Environment Info:', 'gpt-prompt-generator' ); ?></strong><br>
								<small><?php esc_html_e( 'WordPress version, browser type, plugin version', 'gpt-prompt-generator' ); ?></small></li>
							<li><strong><?php esc_html_e( 'Steps to Reproduce:', 'gpt-prompt-generator' ); ?></strong><br>
								<small><?php esc_html_e( 'Exact sequence of actions that trigger the issue', 'gpt-prompt-generator' ); ?></small></li>
							<li><strong><?php esc_html_e( 'Expected vs Actual:', 'gpt-prompt-generator' ); ?></strong><br>
								<small><?php esc_html_e( 'What should happen vs what actually happens', 'gpt-prompt-generator' ); ?></small></li>
						</ol>
					</div>
					
					<div>
						<h5><?php esc_html_e( '🔧 Quick Debug Tips:', 'gpt-prompt-generator' ); ?></h5>
						<ul style="list-style-type: none; padding-left: 0;">
							<li>💡 <strong><?php esc_html_e( 'Console Filtering:', 'gpt-prompt-generator' ); ?></strong><br>
								<small><?php esc_html_e( 'In browser console, type "GPTPG" in filter box to show only plugin messages', 'gpt-prompt-generator' ); ?></small></li>
							<li>📁 <strong><?php esc_html_e( 'Log File Location:', 'gpt-prompt-generator' ); ?></strong><br>
								<code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html( WP_CONTENT_DIR . '/debug.log' ); ?></code></li>
							<li>⏰ <strong><?php esc_html_e( 'Timestamp Matching:', 'gpt-prompt-generator' ); ?></strong><br>
								<small><?php esc_html_e( 'Note the exact time when issue occurs for easier log correlation', 'gpt-prompt-generator' ); ?></small></li>
							<li>🔄 <strong><?php esc_html_e( 'Reproducibility:', 'gpt-prompt-generator' ); ?></strong><br>
								<small><?php esc_html_e( 'Try to reproduce the issue 2-3 times to confirm consistency', 'gpt-prompt-generator' ); ?></small></li>
						</ul>
					</div>
				</div>
				
				<p style="text-align: center; margin-top: 20px;">
					<a href="https://github.com/ipokkel/gpt-prompt-generator/issues/new" target="_blank" class="button button-primary button-large">
						🚀 <?php esc_html_e( 'Report Issue on GitHub', 'gpt-prompt-generator' ); ?>
					</a>
					<a href="https://github.com/ipokkel/gpt-prompt-generator/issues" target="_blank" class="button button-secondary" style="margin-left: 10px;">
						📋 <?php esc_html_e( 'View Existing Issues', 'gpt-prompt-generator' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render use plugin logs field
	 */
	public static function render_use_plugin_logs_field() {
		$checked = get_option( 'gptpg_use_plugin_logs', false );
		$is_disabled = defined( 'GPTPG_USE_PLUGIN_LOGS' );
		$plugin_log_path = GPTPG_Logger::get_plugin_log_path();
		$upload_dir = wp_upload_dir();
		$log_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], dirname( $plugin_log_path ) );
		?>
		<input type="checkbox" id="gptpg_use_plugin_logs" name="gptpg_use_plugin_logs" value="1" <?php checked( $checked ); ?> <?php disabled( $is_disabled ); ?>>
		<label for="gptpg_use_plugin_logs"><?php esc_html_e( 'Use plugin-specific log files instead of WordPress debug log', 'gpt-prompt-generator' ); ?></label>
		
		<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
			<p><strong><?php esc_html_e( 'Plugin Log File Location:', 'gpt-prompt-generator' ); ?></strong></p>
			<code style="background: white; padding: 5px; border-radius: 3px; display: block; margin: 5px 0;"><?php echo esc_html( $plugin_log_path ); ?></code>
			
			<p><strong><?php esc_html_e( 'Features:', 'gpt-prompt-generator' ); ?></strong></p>
			<ul style="margin-left: 20px;">
				<li>🔒 <?php esc_html_e( 'Protected by .htaccess (not web-accessible)', 'gpt-prompt-generator' ); ?></li>
				<li>🔄 <?php esc_html_e( 'Automatic rotation when files exceed 10MB', 'gpt-prompt-generator' ); ?></li>
				<li>📅 <?php esc_html_e( 'Timestamped entries', 'gpt-prompt-generator' ); ?></li>
				<li>🧹 <?php esc_html_e( 'Old log cleanup (keeps .old backups)', 'gpt-prompt-generator' ); ?></li>
			</ul>
		</div>
		
		<?php if ( $is_disabled ) : ?>
			<p class="description" style="color: #d63384;">
				<?php esc_html_e( 'Plugin log files are controlled by the GPTPG_USE_PLUGIN_LOGS constant and cannot be changed here.', 'gpt-prompt-generator' ); ?>
			</p>
		<?php endif; ?>
		
		<p class="description">
			<?php esc_html_e( 'When enabled, plugin logs will be written to a dedicated log file instead of the WordPress debug log. This helps keep plugin logs separate and organized.', 'gpt-prompt-generator' ); ?>
		</p>
		<?php
	}
}

// Initialize the admin class
GPTPG_Admin::init();
