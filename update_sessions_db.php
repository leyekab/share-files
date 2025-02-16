<?php
$db = new SQLite3('shares_new.db');

// Create sessions table
$db->exec('CREATE TABLE IF NOT EXISTS sessions (
    session_id TEXT PRIMARY KEY,
    username TEXT,
    login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address TEXT
)');

echo "Sessions table created successfully!\n";
?>
