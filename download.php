<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

// Για debugging
$debug = false;
function debug_log($message) {
    global $debug;
    if ($debug) {
        file_put_contents('logs/debug.log', date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
    }
}

debug_log('Download requested');

if (!isset($_GET['token'])) {
    die('No token provided');
}

$token = $_GET['token'];
debug_log('Token: ' . $token);

$db = new SQLite3('shares_new.db');

$stmt = $db->prepare('SELECT filename, username FROM shares WHERE token = :token');
$stmt->bindValue(':token', $token, SQLITE3_TEXT);
$result = $stmt->execute();
$row = $result->fetchArray();

if (!$row) {
    debug_log('File not found in database');
    die('File not found in database');
}

$filename = $row['filename'];
$username = $row['username'];

debug_log("Found in DB - Username: $username, Filename: $filename");

// Απλό access control
if ($username !== 'shared' && $username !== $_SESSION['username'] && !$_SESSION['is_admin']) {
    debug_log('Access denied');
    die('Access denied');
}

// ΣΗΜΑΝΤΙΚΗ ΑΛΛΑΓΗ: Το path για shared files
$filepath = ($username === 'shared') ? 'files/shared/' . $filename : 'files/' . $filename;
debug_log("Trying to access: $filepath");

// Έλεγχος αν υπάρχει το αρχείο
if (!file_exists($filepath)) {
    debug_log("File not found at: $filepath");
    debug_log("Full path: " . realpath(dirname($filepath)) . '/' . basename($filepath));
    die("File not found: $filepath");
}

// Αν είναι directory, επέστρεψε στο index
if (is_dir($filepath)) {
    debug_log("Is directory, redirecting to index");
    header('Location: index.php');
    exit;
}

// Get file size
$filesize = filesize($filepath);
debug_log("File size: $filesize");

// Set appropriate headers
if (pathinfo($filepath, PATHINFO_EXTENSION) === 'xml') {
    header('Content-Type: application/xml');
} else {
    header('Content-Type: application/octet-stream');
}

header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . $filesize);

// Καθάρισε τα buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Διάβασε το αρχείο σε chunks για μεγάλα αρχεία
$handle = fopen($filepath, 'rb');
if ($handle === false) {
    debug_log("Could not open file");
    die("Could not open file");
}

while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}

fclose($handle);
debug_log("Download completed");
exit;
?>