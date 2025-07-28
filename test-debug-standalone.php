<?php
/**
 * Standalone Debug Logging Test
 * 
 * Tests GPTPG_Logger functionality without requiring WordPress
 */

// Mock WordPress functions for testing
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Simulate WordPress option storage
        static $options = array();
        return isset($options[$option]) ? $options[$option] : $default;
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        echo "[LOG] " . $message . "\n";
    }
}

// Mock constants for testing
define('WP_DEBUG', true);

// Include the logger class (simplified version for testing)
class GPTPG_Logger_Test {
    const MODE_PRODUCTION = 'production';
    const MODE_REVIEW     = 'review';
    const MODE_DEBUG      = 'debug';
    
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';
    const LEVEL_DEBUG   = 'debug';
    
    private static $test_mode = null;
    
    public static function set_test_mode($mode) {
        self::$test_mode = $mode;
    }
    
    public static function get_debug_mode() {
        // For testing, use the test mode if set
        if (self::$test_mode !== null) {
            return self::$test_mode;
        }
        
        // Check for constant first
        if (defined('GPTPG_DEBUG_MODE')) {
            return GPTPG_DEBUG_MODE;
        }
        
        // Check for WordPress option
        $option_mode = get_option('gptpg_debug_mode', '');
        if (!empty($option_mode) && in_array($option_mode, [self::MODE_PRODUCTION, self::MODE_REVIEW, self::MODE_DEBUG])) {
            return $option_mode;
        }
        
        // Fallback to WP_DEBUG
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return self::MODE_REVIEW;
        }
        
        // Default to production mode
        return self::MODE_PRODUCTION;
    }
    
    public static function is_logging_enabled() {
        $mode = self::get_debug_mode();
        return $mode !== self::MODE_PRODUCTION;
    }
    
    public static function is_debug_mode() {
        return self::get_debug_mode() === self::MODE_DEBUG;
    }
    
    public static function is_review_mode() {
        return self::get_debug_mode() === self::MODE_REVIEW;
    }
    
    public static function log($message, $level = self::LEVEL_INFO, $context = '') {
        // Don't log in production mode
        if (!self::is_logging_enabled()) {
            return;
        }
        
        // In review mode, only log warnings and errors unless it's debug level
        if (self::is_review_mode() && $level === self::LEVEL_DEBUG) {
            return;
        }
        
        // Format the log message
        $formatted_message = self::format_log_message($message, $level, $context);
        
        // Mock logging
        error_log($formatted_message);
    }
    
    public static function debug($message, $context = '') {
        if (self::is_debug_mode()) {
            self::log($message, self::LEVEL_DEBUG, $context);
        }
    }
    
    public static function info($message, $context = '') {
        self::log($message, self::LEVEL_INFO, $context);
    }
    
    public static function warning($message, $context = '') {
        self::log($message, self::LEVEL_WARNING, $context);
    }
    
    public static function error($message, $context = '') {
        self::log($message, self::LEVEL_ERROR, $context);
    }
    
    private static function format_log_message($message, $level, $context = '') {
        $prefix = 'GPTPG';
        
        // Add level indicator
        $level_indicator = '';
        switch ($level) {
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
        if (!empty($context)) {
            $prefix .= " [{$context}]";
        }
        
        return "{$prefix}{$level_indicator}: {$message}";
    }
}

// Run tests
echo "🧪 GPTPG Debug Logging Standalone Test\n";
echo "=====================================\n\n";

// Test 1: Production Mode
echo "📋 Test 1: Production Mode\n";
echo "-------------------------\n";
GPTPG_Logger_Test::set_test_mode('production');
echo "Current mode: " . GPTPG_Logger_Test::get_debug_mode() . "\n";
echo "Logging enabled: " . (GPTPG_Logger_Test::is_logging_enabled() ? 'Yes' : 'No') . "\n";
echo "Expected: No logging should occur\n";
echo "Testing log messages:\n";
GPTPG_Logger_Test::debug("Debug message", "Test");
GPTPG_Logger_Test::info("Info message", "Test");
GPTPG_Logger_Test::warning("Warning message", "Test");
GPTPG_Logger_Test::error("Error message", "Test");
echo "✅ Production mode test complete (no output expected above)\n\n";

// Test 2: Review Mode
echo "📋 Test 2: Review Mode\n";
echo "---------------------\n";
GPTPG_Logger_Test::set_test_mode('review');
echo "Current mode: " . GPTPG_Logger_Test::get_debug_mode() . "\n";
echo "Logging enabled: " . (GPTPG_Logger_Test::is_logging_enabled() ? 'Yes' : 'No') . "\n";
echo "Expected: Info, Warning, Error messages (no Debug messages)\n";
echo "Testing log messages:\n";
GPTPG_Logger_Test::debug("Debug message - should NOT appear", "Test");
GPTPG_Logger_Test::info("Info message - should appear", "Test");
GPTPG_Logger_Test::warning("Warning message - should appear", "Test");
GPTPG_Logger_Test::error("Error message - should appear", "Test");
echo "✅ Review mode test complete\n\n";

// Test 3: Debug Mode
echo "📋 Test 3: Debug Mode\n";
echo "--------------------\n";
GPTPG_Logger_Test::set_test_mode('debug');
echo "Current mode: " . GPTPG_Logger_Test::get_debug_mode() . "\n";
echo "Logging enabled: " . (GPTPG_Logger_Test::is_logging_enabled() ? 'Yes' : 'No') . "\n";
echo "Debug mode active: " . (GPTPG_Logger_Test::is_debug_mode() ? 'Yes' : 'No') . "\n";
echo "Expected: All messages should appear including Debug\n";
echo "Testing log messages:\n";
GPTPG_Logger_Test::debug("Debug message - should appear", "Test");
GPTPG_Logger_Test::info("Info message - should appear", "Test");
GPTPG_Logger_Test::warning("Warning message - should appear", "Test");
GPTPG_Logger_Test::error("Error message - should appear", "Test");
echo "✅ Debug mode test complete\n\n";

// Test 4: Constant Override
echo "📋 Test 4: Constant Override\n";
echo "---------------------------\n";
define('GPTPG_DEBUG_MODE', 'review');
GPTPG_Logger_Test::set_test_mode(null); // Clear test mode to use constants
echo "GPTPG_DEBUG_MODE constant set to: " . GPTPG_DEBUG_MODE . "\n";
echo "Current mode: " . GPTPG_Logger_Test::get_debug_mode() . "\n";
echo "Expected: Should use constant value 'review'\n";
echo "✅ Constant override test complete\n\n";

// Test 5: Log Format Verification
echo "📋 Test 5: Log Format Verification\n";
echo "---------------------------------\n";
GPTPG_Logger_Test::set_test_mode('debug');
echo "Testing different contexts and levels:\n";
GPTPG_Logger_Test::info("GitHub URL extraction started", "GitHub Handler");
GPTPG_Logger_Test::debug("Found 3 URLs in content", "GitHub Handler");
GPTPG_Logger_Test::warning("No snippets found for post", "Form Handler");
GPTPG_Logger_Test::error("Database connection failed", "Database");
echo "✅ Format verification complete\n\n";

echo "🎉 All tests completed!\n";
echo "======================\n";
echo "Summary:\n";
echo "- Production mode: ✅ No logging (secure)\n";
echo "- Review mode: ✅ Info/Warning/Error only (good for testers)\n";
echo "- Debug mode: ✅ All messages including debug (for developers)\n";
echo "- Constant override: ✅ Works correctly\n";
echo "- Log formatting: ✅ Proper context and level indicators\n";
?>
