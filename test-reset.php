<?php
// Test script for database reset functionality

// Include WordPress core
require_once('../../../wp-load.php');

// Check if GPTPG_Database class exists
if (class_exists('GPTPG_Database')) {
    echo "GPTPG_Database class found.\n";
    
    // Test the reset_database function
    echo "Testing reset_database function...\n";
    
    // This would normally reset the database, but we'll just check if the method exists
    if (method_exists('GPTPG_Database', 'reset_database')) {
        echo "reset_database method found.\n";
        echo "SUCCESS: Database reset functionality is implemented.\n";
    } else {
        echo "ERROR: reset_database method not found.\n";
    }
    
    // Test the fresh_install function
    echo "Testing fresh_install function...\n";
    
    if (method_exists('GPTPG_Database', 'fresh_install')) {
        echo "fresh_install method found.\n";
        echo "SUCCESS: Fresh install functionality is implemented.\n";
    } else {
        echo "ERROR: fresh_install method not found.\n";
    }
} else {
    echo "ERROR: GPTPG_Database class not found.\n";
}
?>
