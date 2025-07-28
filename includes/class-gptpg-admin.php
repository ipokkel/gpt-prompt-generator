<?php
/**
 * GPTPG Admin Class
 * 
 * Handles admin interface for debug logging settings and other plugin options
 * 
 * @package GPT_Prompt_Generator
 * @since 0.0.10
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GPTPG Admin Class
 */
class GPTPG_Admin {

	/**
	 * Initialize admin functionality
	 */
	public static function init() {
		// Add admin menu
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		
		// Register settings
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		
		// Add admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		
		// Add settings link to plugin page
		add_filter( 'plugin_action_links_' . GPTPG_PLUGIN_BASENAME, array( __CLASS__, 'add_settings_link' ) );
	}

	/**
	 * Add admin menu
	 */
	public static function add_admin_menu() {
		add_options_page(
			__( 'GPT Prompt Generator Settings', 'gpt-prompt-generator' ),
			__( 'GPT Prompt Generator', 'gpt-prompt-generator' ),
			'manage_options',
			'gptpg-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public static function register_settings() {
		// Register debug logging settings
		register_setting(
			'gptpg_settings',
			'gptpg_debug_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_debug_mode' ),
				'default'           => 'review',
			)
		);

		// Add settings section
		add_settings_section(
			'gptpg_debug_section',
			__( 'Debug & Logging Settings', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_debug_section_description' ),
			'gptpg-settings'
		);

		// Add debug mode field
		add_settings_field(
			'gptpg_debug_mode',
			__( 'Debug Mode', 'gpt-prompt-generator' ),
			array( __CLASS__, 'render_debug_mode_field' ),
			'gptpg-settings',
			'gptpg_debug_section'
		);
	}

	/**
	 * Sanitize debug mode setting
	 */
	public static function sanitize_debug_mode( $value ) {
		$valid_modes = array( 'production', 'review', 'debug' );
		
		if ( ! in_array( $value, $valid_modes ) ) {
			add_settings_error(
				'gptpg_debug_mode',
				'invalid_debug_mode',
				__( 'Invalid debug mode selected.', 'gpt-prompt-generator' )
			);
			return get_option( 'gptpg_debug_mode', 'review' );
		}
		
		return $value;
	}

	/**
	 * Enqueue admin assets
	 */
	public static function enqueue_admin_assets( $hook ) {
		// Only load on our settings page
		if ( 'settings_page_gptpg-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'gptpg-admin-style',
			GPTPG_PLUGIN_URL . 'assets/css/gptpg-admin.css',
			array(),
			GPTPG_VERSION
		);

		wp_enqueue_script(
			'gptpg-admin-script',
			GPTPG_PLUGIN_URL . 'assets/js/gptpg-admin.js',
			array( 'jquery' ),
			GPTPG_VERSION,
			true
		);

		// Pass data to JavaScript
		wp_localize_script(
			'gptpg-admin-script',
			'gptpg_admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'gptpg_admin_nonce' ),
				'debug_info' => GPTPG_Logger::get_frontend_debug_info(),
			)
		);
	}

	/**
	 * Add settings link to plugin page
	 */
	public static function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=gptpg-settings' ) . '">' . __( 'Settings', 'gpt-prompt-generator' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Render settings page
	 */
	public static function render_settings_page() {
		$current_mode = GPTPG_Logger::get_debug_mode();
		$is_constant_defined = defined( 'GPTPG_DEBUG_MODE' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'GPT Prompt Generator Settings', 'gpt-prompt-generator' ); ?></h1>
			
			<?php if ( $is_constant_defined ) : ?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'Note:', 'gpt-prompt-generator' ); ?></strong>
						<?php esc_html_e( 'Debug mode is currently controlled by the GPTPG_DEBUG_MODE constant in your wp-config.php file. To use these settings, remove or comment out that constant.', 'gpt-prompt-generator' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'gptpg_settings' );
				do_settings_sections( 'gptpg-settings' );
				submit_button();
				?>
			</form>

			<div class="gptpg-debug-status">
				<h2><?php esc_html_e( 'Current Debug Status', 'gpt-prompt-generator' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Active Mode', 'gpt-prompt-generator' ); ?></th>
						<td>
							<span class="gptpg-mode-badge gptpg-mode-<?php echo esc_attr( $current_mode ); ?>">
								<?php echo esc_html( ucfirst( $current_mode ) ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logging Enabled', 'gpt-prompt-generator' ); ?></th>
						<td><?php echo GPTPG_Logger::is_logging_enabled() ? '✅ Yes' : '❌ No'; ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Source', 'gpt-prompt-generator' ); ?></th>
						<td>
							<?php
							if ( $is_constant_defined ) {
								esc_html_e( 'GPTPG_DEBUG_MODE constant', 'gpt-prompt-generator' );
							} elseif ( get_option( 'gptpg_debug_mode' ) ) {
								esc_html_e( 'WordPress option (this page)', 'gpt-prompt-generator' );
							} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								esc_html_e( 'WP_DEBUG fallback', 'gpt-prompt-generator' );
							} else {
								esc_html_e( 'Default (production)', 'gpt-prompt-generator' );
							}
							?>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render debug section description
	 */
	public static function render_debug_section_description() {
		?>
		<p><?php esc_html_e( 'Configure debug logging settings for the GPT Prompt Generator plugin.', 'gpt-prompt-generator' ); ?></p>
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

		<div id="gptpg-review-guidance" style="<?php echo ( $current_mode === 'review' ) ? '' : 'display: none;'; ?>">
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
				
				<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #0073aa;">
					<h5 style="margin-top: 0;"><?php esc_html_e( '📋 Issue Report Template:', 'gpt-prompt-generator' ); ?></h5>
					<textarea readonly style="width: 100%; height: 120px; font-family: monospace; font-size: 12px; background: white; border: 1px solid #ddd; padding: 10px;" onclick="this.select();">
**Bug Report: GPT Prompt Generator**

**Environment:**
- WordPress Version: 
- Plugin Version: <?php echo esc_html( GPTPG_VERSION ); ?>
- Browser: 
- Debug Mode: Review

**Steps to Reproduce:**
1. 
2. 
3. 

**Expected Result:**


**Actual Result:**


**Browser Console Logs:**
```
[Paste GPTPG console messages here]
```

**WordPress Debug Log:**
```
[Paste relevant GPTPG log entries here]
```

**Additional Context:**

					</textarea>
					<p><small><?php esc_html_e( '👆 Click template above to select all, then copy and paste into your GitHub issue', 'gpt-prompt-generator' ); ?></small></p>
				</div>
				
				<p style="text-align: center; margin-top: 20px;">
					<a href="https://github.com/strangerstudios/gpt-prompt-generator/issues/new" target="_blank" class="button button-primary button-large">
						🚀 <?php esc_html_e( 'Report Issue on GitHub', 'gpt-prompt-generator' ); ?>
					</a>
					<a href="https://github.com/strangerstudios/gpt-prompt-generator/issues" target="_blank" class="button button-secondary" style="margin-left: 10px;">
						📋 <?php esc_html_e( 'View Existing Issues', 'gpt-prompt-generator' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}
}
