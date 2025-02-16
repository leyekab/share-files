<?php
$db = new SQLite3('shares_new.db');

// Add is_admin column to users table if it doesn't exist
$db->exec('CREATE TABLE IF NOT EXISTS users (
    username TEXT PRIMARY KEY,
    is_admin INTEGER DEFAULT 0
)');

// Insert pantgr as admin if not exists
$db->exec('INSERT OR IGNORE INTO users (username, is_admin) VALUES ("pantgr", 1)');

echo "Database updated successfully!";
?>
