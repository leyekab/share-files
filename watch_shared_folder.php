<?php
$db = new SQLite3('/var/www/share/shares_new.db');

// Function to recursively scan directory and include empty directories
function scanDirectoryRecursively($base_dir, $dir = '', &$results = array()) {
    $files = scandir($base_dir . '/' . $dir);
    
    foreach($files as $file) {
        if ($file == "." || $file == "..") continue;
        
        $relative_path = $dir ? $dir . '/' . $file : $file;
        $full_path = $base_dir . '/' . $relative_path;
        
        if (is_dir($full_path)) {
            // Add the directory itself as an entry
            $results[] = array(
                'path' => $relative_path,
                'is_dir' => true
            );
            // Scan contents of directory
            scanDirectoryRecursively($base_dir, $relative_path, $results);
        } else {
            $results[] = array(
                'path' => $relative_path,
                'is_dir' => false
            );
        }
    }
    
    return $results;
}

// Get list of files and directories already in database
$known_entries = [];
$result = $db->query("SELECT filename, is_directory FROM shares WHERE username = 'shared'");
while ($row = $result->fetchArray()) {
    $known_entries[] = $row['filename'];
}

// Scan shared directory recursively
$current_entries = scanDirectoryRecursively('/var/www/share/files/shared');

// Add new entries
foreach ($current_entries as $entry) {
    if (!in_array($entry['path'], $known_entries)) {
        // Generate token for new entry
        $token = bin2hex(random_bytes(16));
        
        // Add to database with full relative path
        $stmt = $db->prepare('INSERT INTO shares (filename, token, username, upload_date, is_directory) VALUES (:filename, :token, :username, CURRENT_TIMESTAMP, :is_directory)');
        $stmt->bindValue(':filename', $entry['path'], SQLITE3_TEXT);
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->bindValue(':username', 'shared', SQLITE3_TEXT);
        $stmt->bindValue(':is_directory', $entry['is_dir'] ? 1 : 0, SQLITE3_INTEGER);
        $stmt->execute();
        
        echo ($entry['is_dir'] ? "Added new directory: " : "Added new file: ") . $entry['path'] . "\n";
    }
}

// Remove deleted entries from database
$result = $db->query("SELECT filename FROM shares WHERE username = 'shared'");
while ($row = $result->fetchArray()) {
    $filename = $row['filename'];
    $fullPath = '/var/www/share/files/shared/' . $filename;
    if (!file_exists($fullPath)) {
        $stmt = $db->prepare('DELETE FROM shares WHERE filename = :filename AND username = :username');
        $stmt->bindValue(':filename', $filename, SQLITE3_TEXT);
        $stmt->bindValue(':username', 'shared', SQLITE3_TEXT);
        $stmt->execute();
        
        echo "Removed deleted entry: $filename\n";
    }
}
?>
