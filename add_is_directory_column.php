<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Connect to database
    $db = new SQLite3('shares_new.db');
    
    // Add is_shared column if it doesn't exist
    $query = "ALTER TABLE shares ADD COLUMN is_shared INTEGER DEFAULT 0";
    
    try {
        $db->exec($query);
        echo "Successfully added is_shared column\n";
    } catch (Exception $e) {
        echo "Column might already exist: " . $e->getMessage() . "\n";
    }
    
    // Update existing shared files
    $query = "UPDATE shares SET is_shared = 1 WHERE username = 'shared'";
    $db->exec($query);
    echo "Updated existing shared files\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
