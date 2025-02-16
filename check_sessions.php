<?php
$db = new SQLite3('shares_new.db');

echo "Current sessions in database:\n";
$result = $db->query('SELECT * FROM sessions');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    print_r($row);
}
?>
