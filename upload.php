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

// Database connection
$db = new SQLite3('shares_new.db');

// Get the current user
$current_user = $_SESSION['username'];

// Function to generate a random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Function to get file extension
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Function to sanitize filename
function sanitizeFilename($filename) {
    // Πίνακας αντιστοίχισης ελληνικών χαρακτήρων σε λατινικούς
    $greek_to_latin = array(
        'α' => 'a', 'ά' => 'a', 'Α' => 'A', 'Ά' => 'A',
        'β' => 'b', 'Β' => 'B',
        'γ' => 'g', 'Γ' => 'G',
        'δ' => 'd', 'Δ' => 'D',
        'ε' => 'e', 'έ' => 'e', 'Ε' => 'E', 'Έ' => 'E',
        'ζ' => 'z', 'Ζ' => 'Z',
        'η' => 'i', 'ή' => 'i', 'Η' => 'I', 'Ή' => 'I',
        'θ' => 'th', 'Θ' => 'Th',
        'ι' => 'i', 'ί' => 'i', 'ϊ' => 'i', 'ΐ' => 'i', 'Ι' => 'I', 'Ί' => 'I',
        'κ' => 'k', 'Κ' => 'K',
        'λ' => 'l', 'Λ' => 'L',
        'μ' => 'm', 'Μ' => 'M',
        'ν' => 'n', 'Ν' => 'N',
        'ξ' => 'x', 'Ξ' => 'X',
        'ο' => 'o', 'ό' => 'o', 'Ο' => 'O', 'Ό' => 'O',
        'π' => 'p', 'Π' => 'P',
        'ρ' => 'r', 'Ρ' => 'R',
        'σ' => 's', 'ς' => 's', 'Σ' => 'S',
        'τ' => 't', 'Τ' => 'T',
        'υ' => 'y', 'ύ' => 'y', 'ϋ' => 'y', 'ΰ' => 'y', 'Υ' => 'Y', 'Ύ' => 'Y',
        'φ' => 'f', 'Φ' => 'F',
        'χ' => 'x', 'Χ' => 'X',
        'ψ' => 'ps', 'Ψ' => 'Ps',
        'ω' => 'w', 'ώ' => 'w', 'Ω' => 'W', 'Ώ' => 'W',
        ' ' => '_', // Μετατροπή κενών σε underscore
        '-' => '_', // Μετατροπή παύλας σε underscore
    );
    
    // Μετατροπή σε πεζά
    $filename = strtolower($filename);
    
    // Αντικατάσταση ελληνικών χαρακτήρων
    $filename = strtr($filename, $greek_to_latin);
    
    // Διατήρηση μόνο γραμμάτων, αριθμών, κάτω παύλας και τελείας
    $filename = preg_replace("/[^a-z0-9._-]/", "", $filename);
    
    // Αντικατάσταση πολλαπλών underscores με ένα
    $filename = preg_replace('/_+/', '_', $filename);
    
    // Αφαίρεση underscore από την αρχή και το τέλος
    $filename = trim($filename, '_');
    
    return $filename;
}

// Handle both regular files and folder uploads
$files = [];
if (isset($_FILES['fileToUpload']) && !empty($_FILES['fileToUpload']['name'][0])) {
    $files = $_FILES['fileToUpload'];
} elseif (isset($_FILES['folderUpload']) && !empty($_FILES['folderUpload']['name'][0])) {
    $files = $_FILES['folderUpload'];
}

if (!empty($files)) {
    $uploadSuccess = 0;
    $uploadErrors = 0;
    $totalFiles = count($files['name']);

    // Create user directory if it doesn't exist
    $userDir = "files/" . $current_user;
    if (!file_exists($userDir)) {
        mkdir($userDir, 0775, true);
    }

    for ($i = 0; $i < $totalFiles; $i++) {
        if ($files['error'][$i] == 0) {
            $originalFilename = $files['name'][$i];
            $safeFilename = sanitizeFilename($originalFilename);
            $token = generateToken();
            
            // Create year/month directories
            $dateDir = $userDir . "/" . date("Y/m");
            if (!file_exists($dateDir)) {
                mkdir($dateDir, 0775, true);
            }

            $targetPath = $dateDir . "/" . $safeFilename;
            
            // If file exists, append number
            $counter = 1;
            $pathInfo = pathinfo($targetPath);
            while (file_exists($targetPath)) {
                $targetPath = $pathInfo['dirname'] . "/" . $pathInfo['filename'] . 
                             "_" . $counter . "." . $pathInfo['extension'];
                $counter++;
            }

            if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
                // Insert into database
                $stmt = $db->prepare('INSERT INTO shares (token, filename, username, upload_date) VALUES (:token, :filename, :username, datetime("now"))');
                $stmt->bindValue(':token', $token, SQLITE3_TEXT);
                $stmt->bindValue(':filename', str_replace("files/", "", $targetPath), SQLITE3_TEXT);
                $stmt->bindValue(':username', $current_user, SQLITE3_TEXT);
                
                if ($stmt->execute()) {
                    $uploadSuccess++;
                    
                    // Log successful upload
                    $logMessage = date('Y-m-d H:i:s') . " - User: $current_user - Uploaded: $targetPath\n";
                    file_put_contents('logs/upload.log', $logMessage, FILE_APPEND);
                } else {
                    $uploadErrors++;
                    
                    // Log database error
                    $error = $db->lastErrorMsg();
                    $logMessage = date('Y-m-d H:i:s') . " - Database Error - User: $current_user - File: $targetPath - Error: $error\n";
                    file_put_contents('logs/upload_errors.log', $logMessage, FILE_APPEND);
                }
            } else {
                $uploadErrors++;
                
                // Log upload error
                $logMessage = date('Y-m-d H:i:s') . " - Upload Error - User: $current_user - File: $originalFilename\n";
                file_put_contents('logs/upload_errors.log', $logMessage, FILE_APPEND);
            }
        } else {
            $uploadErrors++;
            
            // Log file error
            $error = $files['error'][$i];
            $logMessage = date('Y-m-d H:i:s') . " - File Error - User: $current_user - Error Code: $error\n";
            file_put_contents('logs/upload_errors.log', $logMessage, FILE_APPEND);
        }
    }

    // Redirect with appropriate message
    if ($uploadErrors == 0 && $uploadSuccess > 0) {
        header("Location: index.php?upload=success&count=$uploadSuccess");
    } elseif ($uploadSuccess > 0 && $uploadErrors > 0) {
        header("Location: index.php?upload=mixed&success=$uploadSuccess&errors=$uploadErrors");
    } else {
        header("Location: index.php?upload=error");
    }
    exit;
}

// If no file was uploaded or other error occurred
header("Location: index.php?upload=error");
exit;
?>