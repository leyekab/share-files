<?php
$db = new SQLite3('shares_new.db');
$result = $db->query('SELECT username FROM users');

$base_dir = '/var/www/share/files/';

while ($row = $result->fetchArray()) {
    $user_dir = $base_dir . $row['username'];
    if (!file_exists($user_dir)) {
        mkdir($user_dir, 0755);
        chown($user_dir, 'www-data');
        chgrp($user_dir, 'www-data');
    }
}

echo "User directories created successfully!\n";
?>
