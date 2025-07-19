<?php
/**
 * GPT Prompt Generator - Legacy Table Cleanup Script
 * 
 * This script implements the gradual removal strategy for legacy database tables:
 * Phase 1: Rename legacy tables (add _deprecated_ prefix)
 * Phase 2: Monitor for any issues (manual step)
 * Phase 3: Drop renamed tables (after verification)
 * 
 * Usage: Load this script in your browser via 
 * http://your-site.local/wp-content/plugins/gpt-prompt-generator/legacy-table-cleanup.php
 */

// Initialize WordPress
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('You need to be an administrator to run this script.');
}

global $wpdb;

// Get the action from URL parameter
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'status';

echo "<h1>GPT Prompt Generator - Legacy Table Cleanup</h1>";
echo "<style>
    .success { color: green; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    .action-buttons { margin: 20px 0; }
    .action-buttons a { 
        display: inline-block; 
        padding: 10px 20px; 
        margin: 5px; 
        background: #0073aa; 
        color: white; 
        text-decoration: none; 
        border-radius: 3px; 
    }
    .action-buttons a.warning { background: #ff8c00; }
    .action-buttons a.danger { background: #dc3545; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .stats { background-color: #f9f9f9; padding: 15px; margin: 10px 0; }
</style>";

// Define legacy tables
$legacy_tables = [
    'gptpg_posts' => 'Legacy Posts',
    'gptpg_code_snippets' => 'Legacy Code Snippets', 
    'gptpg_prompts' => 'Legacy Prompts'
];

// Helper function to check if table exists
function table_exists($table_name) {
    global $wpdb;
    return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
}

// Helper function to get table record count
function get_table_count($table_name) {
    global $wpdb;
    if (!table_exists($table_name)) {
        return 0;
    }
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
}

// Process actions
switch ($action) {
    case 'rename_legacy':
        rename_legacy_tables();
        break;
    case 'restore_legacy':
        restore_legacy_tables();
        break;
    case 'drop_deprecated':
        drop_deprecated_tables();
        break;
    default:
        show_status();
        break;
}

function show_status() {
    global $wpdb, $legacy_tables;
    
    echo "<h2>Current Status</h2>";
    
    echo "<table>";
    echo "<tr><th>Table</th><th>Status</th><th>Record Count</th><th>Actions Available</th></tr>";
    
    $all_clear = true;
    $has_deprecated = false;
    
    foreach ($legacy_tables as $table => $description) {
        $table_name = $wpdb->prefix . $table;
        $deprecated_name = $wpdb->prefix . 'deprecated_' . $table;
        
        $original_exists = table_exists($table_name);
        $deprecated_exists = table_exists($deprecated_name);
        
        if ($original_exists) {
            $count = get_table_count($table_name);
            echo "<tr><td>{$table}</td><td class='warning'>Legacy table exists</td><td>{$count}</td><td>Ready for renaming</td></tr>";
            $all_clear = false;
        } elseif ($deprecated_exists) {
            $count = get_table_count($deprecated_name);
            echo "<tr><td>{$table}</td><td class='info'>Deprecated (renamed)</td><td>{$count}</td><td>Ready for dropping</td></tr>";
            $has_deprecated = true;
        } else {
            echo "<tr><td>{$table}</td><td class='success'>Cleaned up</td><td>0</td><td>No action needed</td></tr>";
        }
    }
    
    echo "</table>";
    
    // Show recommended actions
    echo "<div class='action-buttons'>";
    echo "<h3>Available Actions</h3>";
    
    if (!$all_clear && !$has_deprecated) {
        echo "<a href='?action=rename_legacy' class='warning' onclick='return confirm(\"Are you sure you want to rename legacy tables? This will add a deprecated_ prefix to them.\")'>Phase 1: Rename Legacy Tables</a>";
        echo "<p class='info'>ℹ️ This will rename legacy tables by adding 'deprecated_' prefix. This is reversible.</p>";
    }
    
    if ($has_deprecated) {
        echo "<a href='?action=restore_legacy' class='warning' onclick='return confirm(\"Are you sure you want to restore legacy tables? This will remove the deprecated_ prefix.\")'>Restore Legacy Tables</a>";
        echo "<a href='?action=drop_deprecated' class='danger' onclick='return confirm(\"Are you sure you want to permanently drop deprecated tables? This action cannot be undone!\")'>Phase 3: Drop Deprecated Tables</a>";
        echo "<p class='warning'>⚠️ Monitor your application for 1-2 weeks after renaming before proceeding to drop tables.</p>";
    }
    
    if ($all_clear && !$has_deprecated) {
        echo "<p class='success'>✅ All legacy tables have been cleaned up!</p>";
    }
    
    echo "</div>";
    
    // Show warnings and recommendations
    echo "<div class='stats'>";
    echo "<h3>Important Notes</h3>";
    echo "<ul>";
    echo "<li><strong>Always create a database backup before proceeding</strong></li>";
    echo "<li>Run the migration verification script first to ensure data integrity</li>";
    echo "<li>Monitor your application for 1-2 weeks after renaming tables</li>";
    echo "<li>Only drop tables after confirming no functionality is broken</li>";
    echo "</ul>";
    echo "</div>";
}

function rename_legacy_tables() {
    global $wpdb, $legacy_tables;
    
    echo "<h2>Phase 1: Renaming Legacy Tables</h2>";
    
    $renamed_count = 0;
    $errors = [];
    
    foreach ($legacy_tables as $table => $description) {
        $table_name = $wpdb->prefix . $table;
        $deprecated_name = $wpdb->prefix . 'deprecated_' . $table;
        
        if (table_exists($table_name)) {
            $count = get_table_count($table_name);
            
            // Check if deprecated table already exists
            if (table_exists($deprecated_name)) {
                $errors[] = "Deprecated table {$deprecated_name} already exists";
                echo "<div class='error'>❌ {$description}: Deprecated table already exists</div>";
                continue;
            }
            
            // Rename the table
            $result = $wpdb->query("RENAME TABLE {$table_name} TO {$deprecated_name}");
            
            if ($result !== false) {
                $renamed_count++;
                echo "<div class='success'>✅ {$description}: Renamed to deprecated_{$table} ({$count} records)</div>";
            } else {
                $errors[] = "Failed to rename {$table_name}";
                echo "<div class='error'>❌ {$description}: Failed to rename table</div>";
            }
        } else {
            echo "<div class='info'>ℹ️ {$description}: Table doesn't exist (already cleaned up)</div>";
        }
    }
    
    if ($renamed_count > 0) {
        echo "<div class='success'>";
        echo "<h3>✅ Phase 1 Complete</h3>";
        echo "<p>Successfully renamed {$renamed_count} legacy tables.</p>";
        echo "<p><strong>Next Steps:</strong></p>";
        echo "<ol>";
        echo "<li>Monitor your application for 1-2 weeks to ensure no functionality is broken</li>";
        echo "<li>Check error logs for any issues</li>";
        echo "<li>Run your application's main features to verify everything works</li>";
        echo "<li>If all is well, return here to proceed with Phase 3 (dropping tables)</li>";
        echo "</ol>";
        echo "</div>";
        
        // Log the action
        error_log('GPTPG: Renamed ' . $renamed_count . ' legacy tables to deprecated_* format');
    }
    
    if (!empty($errors)) {
        echo "<div class='error'>";
        echo "<h3>❌ Errors Encountered</h3>";
        foreach ($errors as $error) {
            echo "<p>{$error}</p>";
        }
        echo "</div>";
    }
    
    echo "<p><a href='?action=status'>← Back to Status</a></p>";
}

function restore_legacy_tables() {
    global $wpdb, $legacy_tables;
    
    echo "<h2>Restoring Legacy Tables</h2>";
    
    $restored_count = 0;
    $errors = [];
    
    foreach ($legacy_tables as $table => $description) {
        $table_name = $wpdb->prefix . $table;
        $deprecated_name = $wpdb->prefix . 'deprecated_' . $table;
        
        if (table_exists($deprecated_name)) {
            $count = get_table_count($deprecated_name);
            
            // Check if original table exists
            if (table_exists($table_name)) {
                $errors[] = "Original table {$table_name} already exists";
                echo "<div class='error'>❌ {$description}: Original table already exists</div>";
                continue;
            }
            
            // Restore the table
            $result = $wpdb->query("RENAME TABLE {$deprecated_name} TO {$table_name}");
            
            if ($result !== false) {
                $restored_count++;
                echo "<div class='success'>✅ {$description}: Restored from deprecated_{$table} ({$count} records)</div>";
            } else {
                $errors[] = "Failed to restore {$deprecated_name}";
                echo "<div class='error'>❌ {$description}: Failed to restore table</div>";
            }
        } else {
            echo "<div class='info'>ℹ️ {$description}: Deprecated table doesn't exist</div>";
        }
    }
    
    if ($restored_count > 0) {
        echo "<div class='success'>";
        echo "<h3>✅ Tables Restored</h3>";
        echo "<p>Successfully restored {$restored_count} legacy tables.</p>";
        echo "</div>";
        
        // Log the action
        error_log('GPTPG: Restored ' . $restored_count . ' legacy tables from deprecated_* format');
    }
    
    if (!empty($errors)) {
        echo "<div class='error'>";
        echo "<h3>❌ Errors Encountered</h3>";
        foreach ($errors as $error) {
            echo "<p>{$error}</p>";
        }
        echo "</div>";
    }
    
    echo "<p><a href='?action=status'>← Back to Status</a></p>";
}

function drop_deprecated_tables() {
    global $wpdb, $legacy_tables;
    
    echo "<h2>Phase 3: Dropping Deprecated Tables</h2>";
    echo "<div class='warning'><strong>⚠️ WARNING: This action cannot be undone!</strong></div>";
    
    $dropped_count = 0;
    $errors = [];
    $total_records = 0;
    
    foreach ($legacy_tables as $table => $description) {
        $deprecated_name = $wpdb->prefix . 'deprecated_' . $table;
        
        if (table_exists($deprecated_name)) {
            $count = get_table_count($deprecated_name);
            $total_records += $count;
            
            // Drop the table
            $result = $wpdb->query("DROP TABLE {$deprecated_name}");
            
            if ($result !== false) {
                $dropped_count++;
                echo "<div class='success'>✅ {$description}: Dropped deprecated_{$table} ({$count} records permanently deleted)</div>";
            } else {
                $errors[] = "Failed to drop {$deprecated_name}";
                echo "<div class='error'>❌ {$description}: Failed to drop table</div>";
            }
        } else {
            echo "<div class='info'>ℹ️ {$description}: Deprecated table doesn't exist</div>";
        }
    }
    
    if ($dropped_count > 0) {
        echo "<div class='success'>";
        echo "<h3>✅ Phase 3 Complete</h3>";
        echo "<p>Successfully dropped {$dropped_count} deprecated tables.</p>";
        echo "<p>Total records permanently deleted: {$total_records}</p>";
        echo "<p><strong>Database cleanup is now complete!</strong></p>";
        echo "</div>";
        
        // Mark cleanup as complete
        update_option('gptpg_legacy_cleanup_complete', true);
        update_option('gptpg_legacy_cleanup_date', current_time('mysql'));
        
        // Log the action
        error_log('GPTPG: Dropped ' . $dropped_count . ' deprecated tables, removing ' . $total_records . ' legacy records');
    }
    
    if (!empty($errors)) {
        echo "<div class='error'>";
        echo "<h3>❌ Errors Encountered</h3>";
        foreach ($errors as $error) {
            echo "<p>{$error}</p>";
        }
        echo "</div>";
    }
    
    echo "<p><a href='?action=status'>← Back to Status</a></p>";
}

echo "<hr>";
echo "<p><small>Generated on: " . date('Y-m-d H:i:s') . "</small></p>";
?>
