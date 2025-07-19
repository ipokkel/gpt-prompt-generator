<?php
/**
 * GPT Prompt Generator - Migration Verification Script
 * 
 * This script verifies that all data has been properly migrated from legacy tables
 * to the new normalized schema before cleanup.
 * 
 * Usage: Load this script in your browser via 
 * http://your-site.local/wp-content/plugins/gpt-prompt-generator/verify-migration.php
 */

// Initialize WordPress
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('You need to be an administrator to run this script.');
}

global $wpdb;

echo "<h1>GPT Prompt Generator - Migration Verification</h1>";
echo "<style>
    .success { color: green; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .stats { background-color: #f9f9f9; padding: 15px; margin: 10px 0; }
</style>";

echo "<div class='stats'>";
echo "<h2>Database Tables Overview</h2>";

// Define table names
$legacy_tables = [
    'gptpg_posts' => 'Legacy Posts',
    'gptpg_code_snippets' => 'Legacy Code Snippets', 
    'gptpg_prompts' => 'Legacy Prompts'
];

$normalized_tables = [
    'gptpg_unique_posts' => 'Normalized Posts',
    'gptpg_unique_snippets' => 'Normalized Snippets',
    'gptpg_unique_prompts' => 'Normalized Prompts',
    'gptpg_sessions' => 'Sessions',
    'gptpg_session_snippets' => 'Session-Snippet Mappings'
];

$all_tables = array_merge($legacy_tables, $normalized_tables);

echo "<table>";
echo "<tr><th>Table Name</th><th>Description</th><th>Record Count</th><th>Size (MB)</th><th>Status</th></tr>";

$total_legacy_records = 0;
$total_normalized_records = 0;

foreach ($all_tables as $table => $description) {
    $table_name = $wpdb->prefix . $table;
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    
    if (!$table_exists) {
        echo "<tr><td>{$table}</td><td>{$description}</td><td colspan='2' class='warning'>Table does not exist</td><td class='warning'>Missing</td></tr>";
        continue;
    }
    
    // Get record count
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    
    // Get table size
    $size = $wpdb->get_var("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                           FROM information_schema.tables 
                           WHERE table_schema = DATABASE() 
                           AND table_name = '{$table_name}'");
    
    // Determine status
    $status = '';
    $status_class = 'info';
    
    if (array_key_exists($table, $legacy_tables)) {
        $total_legacy_records += $count;
        if ($count > 0) {
            $status = 'Legacy - Contains Data';
            $status_class = 'warning';
        } else {
            $status = 'Legacy - Empty';
            $status_class = 'success';
        }
    } else {
        $total_normalized_records += $count;
        if ($count > 0) {
            $status = 'Normalized - Active';
            $status_class = 'success';
        } else {
            $status = 'Normalized - Empty';
            $status_class = 'warning';
        }
    }
    
    echo "<tr><td>{$table}</td><td>{$description}</td><td>{$count}</td><td>{$size}</td><td class='{$status_class}'>{$status}</td></tr>";
}

echo "</table>";
echo "</div>";

echo "<div class='stats'>";
echo "<h3>Summary Statistics</h3>";
echo "<ul>";
echo "<li>Total Legacy Records: <strong>{$total_legacy_records}</strong></li>";
echo "<li>Total Normalized Records: <strong>{$total_normalized_records}</strong></li>";
echo "</ul>";
echo "</div>";

// Migration verification checks
echo "<h2>Migration Verification Checks</h2>";

$issues = [];
$warnings = [];
$successes = [];

// Check 1: Verify legacy tables are empty or data has been migrated
echo "<h3>Check 1: Legacy Table Status</h3>";
foreach ($legacy_tables as $table => $description) {
    $table_name = $wpdb->prefix . $table;
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    
    if ($count > 0) {
        $issues[] = "❌ {$description} ({$table}) still contains {$count} records";
        echo "<div class='error'>❌ {$description} still contains {$count} records - migration may be incomplete</div>";
    } else {
        $successes[] = "✅ {$description} ({$table}) is empty";
        echo "<div class='success'>✅ {$description} is empty - ready for cleanup</div>";
    }
}

// Check 2: Verify normalized tables contain data
echo "<h3>Check 2: Normalized Table Population</h3>";
foreach ($normalized_tables as $table => $description) {
    $table_name = $wpdb->prefix . $table;
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    
    if ($count === 0 && $table !== 'gptpg_sessions' && $table !== 'gptpg_session_snippets') {
        $warnings[] = "⚠️ {$description} ({$table}) is empty - this might be expected if no data has been processed";
        echo "<div class='warning'>⚠️ {$description} is empty - this might be expected if no data has been processed</div>";
    } else {
        $successes[] = "✅ {$description} ({$table}) contains {$count} records";
        echo "<div class='success'>✅ {$description} contains {$count} records</div>";
    }
}

// Check 3: Verify data consistency
echo "<h3>Check 3: Data Consistency Verification</h3>";

// Check if sessions reference valid posts and prompts
$invalid_sessions = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->prefix}gptpg_sessions s 
    LEFT JOIN {$wpdb->prefix}gptpg_unique_posts p ON s.post_id = p.post_id 
    WHERE s.post_id IS NOT NULL AND p.post_id IS NULL
");

if ($invalid_sessions > 0) {
    $issues[] = "❌ Found {$invalid_sessions} sessions referencing non-existent posts";
    echo "<div class='error'>❌ Found {$invalid_sessions} sessions referencing non-existent posts</div>";
} else {
    $successes[] = "✅ All sessions reference valid posts";
    echo "<div class='success'>✅ All sessions reference valid posts</div>";
}

// Check if session_snippets reference valid sessions and snippets
$invalid_session_snippets = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->prefix}gptpg_session_snippets ss 
    LEFT JOIN {$wpdb->prefix}gptpg_sessions s ON ss.session_id = s.session_id 
    LEFT JOIN {$wpdb->prefix}gptpg_unique_snippets sn ON ss.snippet_id = sn.snippet_id 
    WHERE s.session_id IS NULL OR sn.snippet_id IS NULL
");

if ($invalid_session_snippets > 0) {
    $issues[] = "❌ Found {$invalid_session_snippets} session-snippet mappings with invalid references";
    echo "<div class='error'>❌ Found {$invalid_session_snippets} session-snippet mappings with invalid references</div>";
} else {
    $successes[] = "✅ All session-snippet mappings have valid references";
    echo "<div class='success'>✅ All session-snippet mappings have valid references</div>";
}

// Check 4: Database version
echo "<h3>Check 4: Database Version</h3>";
$db_version = get_option('gptpg_db_version', 'Unknown');
$minimum_version = '0.0.5'; // Minimum version required for normalized schema

if ($db_version !== 'Unknown' && version_compare($db_version, $minimum_version, '>=')) {
    $successes[] = "✅ Database version is compatible ({$db_version})";
    echo "<div class='success'>✅ Database version is compatible ({$db_version}) - meets minimum requirement ({$minimum_version})</div>";
} else {
    $issues[] = "❌ Database version incompatible - Minimum required: {$minimum_version}, Found: {$db_version}";
    echo "<div class='error'>❌ Database version incompatible - Minimum required: {$minimum_version}, Found: {$db_version}</div>";
}

// Final recommendations
echo "<h2>Migration Status & Recommendations</h2>";

if (empty($issues)) {
    echo "<div class='success'>";
    echo "<h3>✅ Migration Verification PASSED</h3>";
    echo "<p>All checks passed successfully. The database appears to be properly migrated to the normalized schema.</p>";
    echo "<p><strong>Recommendation:</strong> You can proceed with legacy table cleanup.</p>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ Migration Verification FAILED</h3>";
    echo "<p>The following issues were found:</p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>{$issue}</li>";
    }
    echo "</ul>";
    echo "<p><strong>Recommendation:</strong> DO NOT proceed with legacy table cleanup until these issues are resolved.</p>";
    echo "</div>";
}

if (!empty($warnings)) {
    echo "<div class='warning'>";
    echo "<h3>⚠️ Warnings</h3>";
    echo "<ul>";
    foreach ($warnings as $warning) {
        echo "<li>{$warning}</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<div class='info'>";
echo "<h3>Next Steps</h3>";
echo "<ol>";
if (empty($issues)) {
    echo "<li>✅ Migration verification passed - ready for cleanup</li>";
    echo "<li>Create a full database backup before proceeding</li>";
    echo "<li>Run the code cleanup phase to remove legacy table references</li>";
    echo "<li>Implement the gradual table removal strategy</li>";
} else {
    echo "<li>❌ Fix the migration issues identified above</li>";
    echo "<li>Re-run this verification script until all issues are resolved</li>";
    echo "<li>Consider running the database upgrade script if migration is incomplete</li>";
}
echo "</ol>";
echo "</div>";

echo "<hr>";
echo "<p><small>Generated on: " . date('Y-m-d H:i:s') . "</small></p>";
?>
