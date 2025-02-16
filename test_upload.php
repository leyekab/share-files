<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', '/var/www/share/upload_errors.log');

echo "<pre>";
echo "PHP Upload Settings:\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "max_input_time: " . ini_get('max_input_time') . "\n";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "Upload Details:\n";
    if (!empty($_FILES)) {
        foreach ($_FILES as $file) {
            echo "File: " . $file['name'] . "\n";
            echo "Size: " . ($file['size'] / 1024 / 1024) . " MB\n";
            echo "Type: " . $file['type'] . "\n";
            echo "Temp path: " . $file['tmp_name'] . "\n";
            echo "Error code: " . $file['error'] . "\n\n";
            
            if ($file['error'] !== 0) {
                $errors = array(
                    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                    3 => 'The uploaded file was only partially uploaded',
                    4 => 'No file was uploaded',
                    6 => 'Missing a temporary folder',
                    7 => 'Failed to write file to disk',
                    8 => 'A PHP extension stopped the file upload'
                );
                echo "Error Message: " . $errors[$file['error']] . "\n";
            }
        }
    } else {
        echo "No files were uploaded.\n";
    }
}
echo "</pre>";
?>

<form action="" method="post" enctype="multipart/form-data">
    <input type="file" name="testfile">
    <input type="submit" value="Upload File">
</form>
