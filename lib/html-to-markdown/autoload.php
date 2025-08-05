<?php
/**
 * Simple autoloader for league/html-to-markdown library
 *
 * @package GPT_Prompt_Generator
 */

// Define the library namespace prefix
$league_prefix = 'League\\HTMLToMarkdown\\';

// Register the autoloader
spl_autoload_register(function ($class) use ($league_prefix) {
    // Check if the class uses the league namespace
    if (strpos($class, $league_prefix) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, strlen($league_prefix));
    
    // Replace namespace separators with directory separators
    $file = str_replace('\\', '/', $relative_class);
    
    // Build the full file path
    $file_path = __DIR__ . '/src/' . $file . '.php';
    
    // If the file exists, require it
    if (file_exists($file_path)) {
        require $file_path;
    }
});
