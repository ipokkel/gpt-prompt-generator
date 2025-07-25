<?php
/**
 * Direct Database Table Creation
 * 
 * This is a standalone script that creates the required database tables
 * without using WordPress API functions.
 */

// Basic connection info - adjust if needed
$db_host = 'localhost';
$db_name = 'local';
$db_user = 'root';
$db_password = 'root';
$table_prefix = 'wp_';

try {
    // Connect to database
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>GPT Prompt Generator - Direct Table Creation</h1>";
    
    // Charset and collation
    $charset_collate = "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci";
    
    // Define tables to create
    $tables = [];
    
    // Table for storing sessions
    $tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}gptpg_sessions (
        session_id varchar(255) NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        post_id bigint(20) unsigned DEFAULT NULL,
        prompt_id bigint(20) unsigned DEFAULT NULL,
        created_at datetime NOT NULL,
        status varchar(50) DEFAULT 'pending',
        PRIMARY KEY (session_id),
        KEY user_id (user_id),
        KEY post_id (post_id),
        KEY prompt_id (prompt_id)
    ) $charset_collate";
    
    // Table for session to snippet mappings
    $tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}gptpg_session_snippets (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        snippet_id bigint(20) unsigned NOT NULL,
        is_user_edited tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY snippet_id (snippet_id)
    ) $charset_collate";
    
    // Table for storing posts
    $tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}gptpg_posts (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned DEFAULT NULL,
        session_id varchar(255) NOT NULL,
        post_url varchar(2083) NOT NULL,
        post_title text NOT NULL,
        post_content longtext NOT NULL,
        post_content_markdown longtext NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY expires_at (expires_at),
        KEY user_id (user_id)
    ) $charset_collate";
    
    // Table for storing code snippets
    $tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}gptpg_code_snippets (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned DEFAULT NULL,
        session_id varchar(255) NOT NULL,
        post_id bigint(20) unsigned NOT NULL,
        snippet_url varchar(2083) NOT NULL,
        snippet_type varchar(50) NOT NULL,
        snippet_content longtext,
        is_user_edited tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY post_id (post_id),
        KEY user_id (user_id)
    ) $charset_collate";
    
    // Table for storing prompts
    $tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}gptpg_prompts (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned DEFAULT NULL,
        session_id varchar(255) NOT NULL,
        post_id bigint(20) unsigned NOT NULL,
        prompt_content longtext NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY post_id (post_id),
        KEY user_id (user_id)
    ) $charset_collate";
    
    // Table for storing unique posts
    $tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}gptpg_unique_posts (
        post_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_url varchar(2083) NOT NULL,
        post_title text NOT NULL,
        post_content longtext NOT NULL,
        post_content_markdown longtext NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        expires_at datetime DEFAULT NULL,
        PRIMARY KEY (post_id),
        UNIQUE KEY post_url (post_url(191))
    ) $charset_collate";
    
    // Table for storing unique code snippets
    $tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}gptpg_unique_snippets (
        snippet_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        snippet_url varchar(2083) NOT NULL,
        snippet_type varchar(50) NOT NULL,
        snippet_content longtext,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (snippet_id),
        UNIQUE KEY snippet_url (snippet_url(191))
    ) $charset_collate";
    
    // Table for storing unique prompts
    $tables[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}gptpg_unique_prompts (
        prompt_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        prompt_content longtext NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (prompt_id),
        UNIQUE KEY prompt_hash (MD5(prompt_content))
    ) $charset_collate";
    
    // Create each table
    echo "<h2>Creating Tables:</h2>";
    echo "<ul>";
    foreach ($tables as $table_sql) {
        $conn->exec($table_sql);
        // Extract table name from SQL for display
        preg_match('/CREATE TABLE IF NOT EXISTS (\\w+)/', $table_sql, $matches);
        $table_name = isset($matches[1]) ? $matches[1] : 'Unknown table';
        echo "<li>✅ Created table: $table_name</li>";
    }
    echo "</ul>";
    
    echo "<h2>Verification:</h2>";
    echo "<ul>";
    
    // List of tables to verify
    $verify_tables = [
        "{$table_prefix}gptpg_sessions",
        "{$table_prefix}gptpg_session_snippets",
        "{$table_prefix}gptpg_posts",
        "{$table_prefix}gptpg_code_snippets",
        "{$table_prefix}gptpg_prompts",
        "{$table_prefix}gptpg_unique_posts",
        "{$table_prefix}gptpg_unique_snippets",
        "{$table_prefix}gptpg_unique_prompts"
    ];
    
    // Check if each table exists
    foreach ($verify_tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            echo "<li>✅ Table <code>$table</code> exists.</li>";
        } else {
            echo "<li>❌ Table <code>$table</code> does NOT exist.</li>";
        }
    }
    
    echo "</ul>";
    
    // Set database version
    $stmt = $conn->prepare("REPLACE INTO {$table_prefix}options (option_name, option_value, autoload) VALUES (?, ?, 'yes')");
    $option_name = 'gptpg_db_version';
    $option_value = '0.0.6'; // Assume latest version
    $stmt->execute([$option_name, $option_value]);
    
    echo "<p>Database version set to $option_value.</p>";
    
    echo "<h2>Next Steps:</h2>";
    echo "<p>1. <a href='/'>Go to your website homepage</a></p>";
    echo "<p>2. Try using the GPT Prompt Generator form again</p>";
    echo "<p>3. Delete this script when you're done</p>";

} catch(PDOException $e) {
    echo "<h1>Error</h1>";
    echo "<p>Connection failed: " . $e->getMessage() . "</p>";
    
    echo "<h2>Debugging Information:</h2>";
    echo "<pre>";
    print_r($e);
    echo "</pre>";
}
?>
