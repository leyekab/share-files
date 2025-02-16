<?php
echo "Starting user synchronization...\n";

$db = new SQLite3('shares_new.db');

// Get users from .htpasswd
$htpasswd_users = [];
$lines = file('/etc/nginx/.htpasswd');
foreach ($lines as $line) {
    $username = explode(':', trim($line))[0];
    $htpasswd_users[] = $username;
    echo "Found in .htpasswd: $username\n";
}

// Sync with database
foreach ($htpasswd_users as $username) {
    $stmt = $db->prepare('INSERT OR IGNORE INTO users (username, is_admin) VALUES (:username, :is_admin)');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':is_admin', ($username === 'pantgr' ? 1 : 0), SQLITE3_INTEGER);
    $stmt->execute();
    echo "Synced user: $username\n";
}

// Remove users from database that don't exist in .htpasswd
$db->exec('DELETE FROM users WHERE username NOT IN ("' . implode('","', $htpasswd_users) . '")');

echo "Synchronization complete!\n";
echo "\nCurrent users in database:\n";
$result = $db->query('SELECT username, is_admin FROM users');
while ($row = $result->fetchArray()) {
    $admin_status = $row['is_admin'] ? 'admin' : 'user';
    echo "- {$row['username']} ($admin_status)\n";
}
?>
