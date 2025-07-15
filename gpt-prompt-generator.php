<?php
/**
 * Plugin Name: GPT Prompt Generator
 * Plugin URI: https://www.strangerstudios.com/plugins/gpt-prompt-generator/
 * Description: Generate a ChatGPT Prompt for rewriting a Tooltip Recipe Post.
 * Version: 0.0.4
 * Author: Stranger Studios
 * Author URI: https://www.strangerstudios.com
 * Text Domain: gpt-prompt-generator
 * Domain Path: /languages
 * License: GPL-2.0+
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'GPTPG_VERSION', '0.0.4' );
define( 'GPTPG_PLUGIN_FILE', __FILE__ );
define( 'GPTPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GPTPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GPTPG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include main plugin class
require_once GPTPG_PLUGIN_DIR . 'includes/class-gptpg-main.php';

/**
 * The main function responsible for returning the GPTPG_Plugin instance.
 *
 * @return GPTPG_Plugin
 */
function gptpg_get_instance() {
	return GPTPG_Plugin::get_instance();
}

// Initialize the plugin
gptpg_get_instance();