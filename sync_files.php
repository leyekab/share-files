<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connect to database
$db = new SQLite3('shares_new.db');

// Function to generate a token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Function to get all files in directory recursively
function getAllFiles($dir) {
    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }
    
    return $files;
}

// Start transaction
$db->exec('BEGIN');

try {
    // Get all files in files directory
    $files = getAllFiles('files');
    
    // Clear existing records
    $db->exec('DELETE FROM shares');
    
    // Add each file to database
    foreach ($files as $filepath) {
        // Get relative path
        $relativePath = str_replace('files/', '', $filepath);
        
        // Get username from path
        $pathParts = explode('/', $relativePath);
        $username = $pathParts[0];
        
        // Generate new token
        $token = generateToken();
        
        // Insert into database
        $stmt = $db->prepare('INSERT INTO shares (token, filename, username, upload_date) VALUES (:token, :filename, :username, datetime("now"))');
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->bindValue(':filename', $relativePath, SQLITE3_TEXT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    // Commit changes
    $db->exec('COMMIT');
    
    echo "Database synchronized successfully.\n";
    echo "Total files processed: " . count($files) . "\n";
    
} catch (Exception $e) {
    // Rollback on error
    $db->exec('ROLLBACK');
    echo "Error: " . $e->getMessage() . "\n";
}

// Close database connection
$db->close();
?>