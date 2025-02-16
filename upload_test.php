<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo "<pre>";
    var_dump($_FILES);
    echo "</pre>";
}
?>
<form action="" method="post" enctype="multipart/form-data">
    <input type="file" name="test_file">
    <input type="submit" value="Upload">
</form>
