<?php
/**
 * Debug Logging Test Script
 * 
 * This script tests the GPTPG_Logger functionality across different modes
 * Run this via WordPress admin or command line to test logging behavior
 */

// Include WordPress if running standalone
if (!defined('ABSPATH')) {
    require_once('../../../wp-config.php');
}

// Include the plugin classes
require_once('includes/class-gptpg-logger.php');

echo "<h2>🧪 GPTPG Debug Logging Test</h2>";
echo "<p><strong>Testing different debug logging modes...</strong></p>";

// Test 1: Check current debug mode
echo "<h3>📋 Test 1: Current Debug Mode</h3>";
$current_mode = GPTPG_Logger::get_debug_mode();
echo "<p>Current debug mode: <code>{$current_mode}</code></p>";
echo "<p>Logging enabled: " . (GPTPG_Logger::is_logging_enabled() ? '✅ Yes' : '❌ No') . "</p>";
echo "<p>Debug mode active: " . (GPTPG_Logger::is_debug_mode() ? '✅ Yes' : '❌ No') . "</p>";
echo "<p>Review mode active: " . (GPTPG_Logger::is_review_mode() ? '✅ Yes' : '❌ No') . "</p>";

// Test 2: Test logging in current mode
echo "<h3>📝 Test 2: Logging in Current Mode ({$current_mode})</h3>";
echo "<p>Attempting to log messages at different levels...</p>";

GPTPG_Logger::debug("This is a debug message - should only appear in debug mode", 'Test Script');
GPTPG_Logger::info("This is an info message - should appear in review and debug modes", 'Test Script');
GPTPG_Logger::warning("This is a warning message - should appear in review and debug modes", 'Test Script');
GPTPG_Logger::error("This is an error message - should appear in review and debug modes", 'Test Script');

echo "<p>✅ Log messages sent. Check debug.log for output.</p>";

// Test 3: Test production mode behavior
echo "<h3>🚫 Test 3: Production Mode Behavior</h3>";
echo "<p>Temporarily testing production mode behavior...</p>";

// Simulate production mode by defining constant if not already defined
if (!defined('GPTPG_DEBUG_MODE_TEST')) {
    define('GPTPG_DEBUG_MODE_TEST', 'production');
}

// Create a temporary logger method that uses our test constant
class GPTPG_Logger_Test extends GPTPG_Logger {
    public static function get_debug_mode() {
        if (defined('GPTPG_DEBUG_MODE_TEST')) {
            return GPTPG_DEBUG_MODE_TEST;
        }
        return parent::get_debug_mode();
    }
}

$prod_logging_enabled = GPTPG_Logger_Test::is_logging_enabled();
echo "<p>Production mode logging enabled: " . ($prod_logging_enabled ? '❌ Yes (ERROR!)' : '✅ No (CORRECT)') . "</p>";

// Test 4: Format verification
echo "<h3>📋 Test 4: Log Format Verification</h3>";
echo "<p>Testing log message formatting...</p>";

GPTPG_Logger::info("Test message for format verification", 'Format Test');
echo "<p>✅ Format test message sent.</p>";

// Test 5: Frontend debug info
echo "<h3>🌐 Test 5: Frontend Debug Info</h3>";
$frontend_info = GPTPG_Logger::get_frontend_debug_info();
echo "<p>Frontend debug info:</p>";
echo "<pre>" . print_r($frontend_info, true) . "</pre>";

echo "<hr>";
echo "<h3>📄 Current Debug Log Contents:</h3>";
$debug_log_path = WP_CONTENT_DIR . '/debug.log';
if (file_exists($debug_log_path)) {
    $log_contents = file_get_contents($debug_log_path);
    if (!empty($log_contents)) {
        echo "<pre style='background: #f1f1f1; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: scroll;'>";
        echo htmlspecialchars($log_contents);
        echo "</pre>";
    } else {
        echo "<p><em>Debug log is empty</em></p>";
    }
} else {
    echo "<p><em>Debug log file does not exist</em></p>";
}

echo "<hr>";
echo "<p><strong>✅ Debug logging test completed!</strong></p>";
echo "<p>Check the debug log output above to verify that:</p>";
echo "<ul>";
echo "<li>Messages appear in the correct format: <code>GPTPG [Context] LEVEL: Message</code></li>";
echo "<li>In review mode: info, warning, error messages appear (debug messages are hidden)</li>";
echo "<li>In production mode: no messages should appear</li>";
echo "<li>In debug mode: all messages should appear</li>";
echo "</ul>";
?>
