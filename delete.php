<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug logging function
function debug_log($message) {
    $logMessage = date('Y-m-d H:i:s') . " - $message\n";
    file_put_contents('logs/delete_debug.log', $logMessage, FILE_APPEND);
}

if (!isset($_GET['token'])) {
    die('No token provided');
}

$token = $_GET['token'];
$db = new SQLite3('shares_new.db');

// Get file information
$stmt = $db->prepare('SELECT filename, username FROM shares WHERE token = :token');
$stmt->bindValue(':token', $token, SQLITE3_TEXT);
$result = $stmt->execute();
$row = $result->fetchArray();

if (!$row) {
    debug_log("File not found in database for token: $token");
    header('Location: index.php?delete=error&reason=notfound');
    exit;
}

$filename = $row['filename'];
$fileOwner = $row['username'];

debug_log("Attempting to delete - File: $filename, Owner: $fileOwner");

// Check if user has permission to delete
if ($fileOwner !== $_SESSION['username'] && !$_SESSION['is_admin']) {
    debug_log("Permission denied for user: " . $_SESSION['username']);
    header('Location: index.php?delete=error&reason=permission');
    exit;
}

// Construct full file path based on ownership
$filepath = ($fileOwner === 'shared') ? 'files/shared/' . $filename : 'files/' . $filename;

debug_log("Full file path: $filepath");

// Begin transaction
$db->exec('BEGIN');

try {
    // Delete from database first
    $stmt = $db->prepare('DELETE FROM shares WHERE token = :token');
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);

    if (!$stmt->execute()) {
        throw new Exception("Database delete failed");
    }

    debug_log("Database entry deleted successfully");

    // Delete physical file if it exists
    if (file_exists($filepath)) {
        if (is_dir($filepath)) {
            // If it's a directory, skip deletion
            debug_log("Skipping directory deletion: $filepath");
        } else {
            // Delete file
            if (!unlink($filepath)) {
                throw new Exception("File delete failed: $filepath");
            }
            debug_log("File deleted successfully: $filepath");

            // Delete parent directories if empty (only for non-shared files)
            if ($fileOwner !== 'shared') {
                $dir = dirname($filepath);
                while ($dir !== 'files' && $dir !== '.') {
                    if (is_dir($dir) && count(scandir($dir)) <= 2) { // Only . and .. remain
                        rmdir($dir);
                        debug_log("Empty directory removed: $dir");
                    } else {
                        break;
                    }
                    $dir = dirname($dir);
                }
            }
        }
    } else {
        debug_log("File not found on disk: $filepath");
    }

    // Log deletion
    $logMessage = date('Y-m-d H:i:s') . " - User: " . $_SESSION['username'] . " deleted: $filename\n";
    file_put_contents('logs/delete.log', $logMessage, FILE_APPEND);

    // Commit transaction
    $db->exec('COMMIT');
    debug_log("Transaction committed successfully");

    header('Location: index.php?delete=success');
    exit;

} catch (Exception $e) {
    // Rollback on error
    $db->exec('ROLLBACK');
    debug_log("Error occurred, rolling back: " . $e->getMessage());

    // Log error
    $errorMessage = date('Y-m-d H:i:s') . " - Error deleting file: $filename - " . $e->getMessage() . "\n";
    file_put_contents('logs/delete_errors.log', $errorMessage, FILE_APPEND);

    header('Location: index.php?delete=error&reason=system');
    exit;
}
?>