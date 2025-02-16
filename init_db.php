<?php
$db = new SQLite3('shares_new.db');

$db->exec('CREATE TABLE IF NOT EXISTS shares (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    token TEXT NOT NULL UNIQUE,
    username TEXT NOT NULL,
    upload_date DATETIME NOT NULL
)');

echo "Database initialized successfully!";
?>
