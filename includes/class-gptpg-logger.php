<?php
/**
 * GPTPG Logger Class
 * 
 * Handles debug logging with configurable modes for production, review, and debug
 * 
 * @package GPT_Prompt_Generator
 * @since 0.0.10
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GPTPG Logger Class
 */
class GPTPG_Logger {

	/**
	 * Debug logging modes
	 */
	const MODE_PRODUCTION = 'production';
	const MODE_REVIEW     = 'review';
	const MODE_DEBUG      = 'debug';

	/**
	 * Log levels
	 */
	const LEVEL_INFO    = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';
	const LEVEL_DEBUG   = 'debug';

	/**
	 * Plugin log file name
	 */
	const LOG_FILE_NAME = 'gptpg-debug.log';

	/**
	 * Whether to use plugin-specific log files
	 * Can be controlled via GPTPG_USE_PLUGIN_LOGS constant
	 */
	private static $use_plugin_logs = null;

	/**
	 * Get the current debug mode
	 * 
	 * Priority order:
	 * 1. GPTPG_DEBUG_MODE constant (highest priority)
	 * 2. WordPress option 'gptpg_debug_mode' 
	 * 3. WP_DEBUG fallback (lowest priority)
	 * 
	 * @return string Debug mode: 'production', 'review', or 'debug'
	 */
	public static function get_debug_mode() {
		// Check for constant first (allows wp-config.php override)
		if ( defined( 'GPTPG_DEBUG_MODE' ) ) {
			return GPTPG_DEBUG_MODE;
		}

		// Check for WordPress option (admin setting)
		$option_mode = get_option( 'gptpg_debug_mode', self::MODE_PRODUCTION );
		if ( in_array( $option_mode, [ self::MODE_PRODUCTION, self::MODE_REVIEW, self::MODE_DEBUG ] ) ) {
			return $option_mode;
		}

		// Fallback to WP_DEBUG
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return self::MODE_REVIEW;
		}

		// Default to production mode
		return self::MODE_PRODUCTION;
	}

	/**
	 * Check if logging is enabled
	 * 
	 * @return bool True if logging should occur
	 */
	public static function is_logging_enabled() {
		$mode = self::get_debug_mode();
		return $mode !== self::MODE_PRODUCTION;
	}

	/**
	 * Check if debug mode is active
	 * 
	 * @return bool True if debug mode is active
	 */
	public static function is_debug_mode() {
		return self::get_debug_mode() === self::MODE_DEBUG;
	}

	/**
	 * Check if review mode is active
	 * 
	 * @return bool True if review mode is active
	 */
	public static function is_review_mode() {
		return self::get_debug_mode() === self::MODE_REVIEW;
	}

	/**
	 * Get the source of the current debug mode setting
	 * 
	 * @return string The source of the debug mode setting
	 */
	public static function get_debug_mode_source() {
		// Check if constant is defined (highest priority)
		if ( defined( 'GPTPG_DEBUG_MODE' ) ) {
			return 'GPTPG_DEBUG_MODE constant';
		}
		
		// Check if WordPress option is set
		$option_value = get_option( 'gptpg_debug_mode' );
		if ( $option_value ) {
			return 'WordPress option (gptpg_debug_mode)';
		}
		
		// Check if WP_DEBUG is defined
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return 'WP_DEBUG constant fallback';
		}
		
		// Default fallback
		return 'Default (production mode)';
	}

	/**
	 * Check if console debug is enabled for frontend
	 * 
	 * @return bool True if console debug should be enabled
	 */
	public static function is_console_debug_enabled() {
		// Console debug is enabled in review and debug modes
		return self::is_logging_enabled();
	}

	/**
	 * Get frontend debug information for JavaScript
	 * 
	 * @return array Debug information for frontend
	 */
	public static function get_frontend_debug_info() {
		return array(
			'mode' => self::get_debug_mode(),
			'logging' => self::is_logging_enabled(),
			'console_debug' => self::is_console_debug_enabled(),
			'source' => self::get_debug_mode_source()
		);
	}

	/**
	 * Check if plugin-specific log files should be used
	 * 
	 * @return bool True if plugin logs should be used
	 */
	public static function use_plugin_logs() {
		if ( self::$use_plugin_logs === null ) {
			// Check if constant is defined
			if ( defined( 'GPTPG_USE_PLUGIN_LOGS' ) ) {
				self::$use_plugin_logs = (bool) GPTPG_USE_PLUGIN_LOGS;
			} else {
				// Check WordPress option
				self::$use_plugin_logs = get_option( 'gptpg_use_plugin_logs', false );
			}
		}
		return self::$use_plugin_logs;
	}

	/**
	 * Get the plugin log file path
	 * 
	 * @return string Full path to plugin log file
	 */
	public static function get_plugin_log_path() {
		$log_dir = GPTPG_PLUGIN_DIR . 'logs';
		
		// Create logs directory if it doesn't exist
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			
			// Create .htaccess to protect log files
			self::create_htaccess( $log_dir );
		}
		
		return $log_dir . '/' . self::LOG_FILE_NAME;
	}

	/**
	 * Create .htaccess file to protect log directory
	 * 
	 * @param string $log_dir The log directory path
	 */
	private static function create_htaccess( $log_dir ) {
		$htaccess_file = $log_dir . '/.htaccess';
		
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# GPT Prompt Generator Log Protection\n";
			$htaccess_content .= "Order Deny,Allow\n";
			$htaccess_content .= "Deny from all\n";
			$htaccess_content .= "<Files \"*.log\">\n";
			$htaccess_content .= "    Order Deny,Allow\n";
			$htaccess_content .= "    Deny from all\n";
			$htaccess_content .= "</Files>\n";
			
			file_put_contents( $htaccess_file, $htaccess_content );
		}
	}

	/**
	 * Log a message with level control
	 * 
	 * @param string $message The message to log
	 * @param string $level   Log level (info, warning, error, debug)
	 * @param string $context Additional context (e.g., 'GitHub Handler', 'Form Handler')
	 */
	public static function log( $message, $level = self::LEVEL_INFO, $context = '' ) {
		// Don't log in production mode
		if ( ! self::is_logging_enabled() ) {
			return;
		}

		// In review mode, only log warnings and errors unless it's debug level
		if ( self::is_review_mode() && $level === self::LEVEL_DEBUG ) {
			return;
		}

		// Format the log message
		$formatted_message = self::format_log_message( $message, $level, $context );

		// Write to plugin-specific log file if enabled
		if ( self::use_plugin_logs() ) {
			self::write_to_plugin_log( $formatted_message );
		} else {
			// Use WordPress error logging (default)
			error_log( $formatted_message );
		}
	}

	/**
	 * Write message to plugin-specific log file
	 * 
	 * @param string $message Formatted log message
	 */
	private static function write_to_plugin_log( $message ) {
		$log_file = self::get_plugin_log_path();
		
		// Add timestamp and write to file
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
		
		// Append to log file (create if doesn't exist)
		file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
		
		// Rotate log file if it gets too large (> 10MB)
		self::rotate_log_if_needed( $log_file );
	}

	/**
	 * Rotate log file if it exceeds size limit
	 * 
	 * @param string $log_file Path to log file
	 */
	private static function rotate_log_if_needed( $log_file ) {
		if ( ! file_exists( $log_file ) ) {
			return;
		}
		
		$max_size = 10 * 1024 * 1024; // 10MB
		
		if ( filesize( $log_file ) > $max_size ) {
			$backup_file = $log_file . '.old';
			
			// Remove old backup if exists
			if ( file_exists( $backup_file ) ) {
				unlink( $backup_file );
			}
			
			// Move current log to backup
			rename( $log_file, $backup_file );
			
			// Create fresh log file
			touch( $log_file );
		}
	}

	/**
	 * Log debug information (only in debug mode)
	 * 
	 * @param string $message The debug message
	 * @param string $context Additional context
	 */
	public static function debug( $message, $context = '' ) {
		if ( self::is_debug_mode() ) {
			self::log( $message, self::LEVEL_DEBUG, $context );
		}
	}

	/**
	 * Log informational message
	 * 
	 * @param string $message The info message
	 * @param string $context Additional context
	 */
	public static function info( $message, $context = '' ) {
		self::log( $message, self::LEVEL_INFO, $context );
	}

	/**
	 * Log warning message
	 * 
	 * @param string $message The warning message
	 * @param string $context Additional context
	 */
	public static function warning( $message, $context = '' ) {
		self::log( $message, self::LEVEL_WARNING, $context );
	}

	/**
	 * Log error message
	 * 
	 * @param string $message The error message
	 * @param string $context Additional context
	 */
	public static function error( $message, $context = '' ) {
		self::log( $message, self::LEVEL_ERROR, $context );
	}

	/**
	 * Format log message with timestamp, level, and context
	 * 
	 * @param string $message The message to format
	 * @param string $level   Log level
	 * @param string $context Additional context
	 * @return string Formatted log message
	 */
	private static function format_log_message( $message, $level, $context = '' ) {
		$prefix = 'GPTPG';
		
		// Add level indicator
		$level_indicator = '';
		switch ( $level ) {
			case self::LEVEL_ERROR:
				$level_indicator = ' ERROR';
				break;
			case self::LEVEL_WARNING:
				$level_indicator = ' WARNING';
				break;
			case self::LEVEL_DEBUG:
				$level_indicator = ' DEBUG';
				break;
			case self::LEVEL_INFO:
			default:
				$level_indicator = ' INFO';
				break;
		}

		// Add context if provided
		if ( ! empty( $context ) ) {
			$prefix .= " [{$context}]";
		}

		return "{$prefix}{$level_indicator}: {$message}";
	}


}
