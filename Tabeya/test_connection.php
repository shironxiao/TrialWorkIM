<?php
// test_connection.php
require_once(__DIR__ . '/api/config/db_config.php');

echo "✅ Database connected successfully!<br>";
echo "Database: " . DB_NAME . "<br>";
echo "Host: " . DB_HOST . "<br>";

// Test query
$result = $conn->query("SHOW TABLES");
echo "<br>Tables in database:<br>";
while ($row = $result->fetch_array()) {
    echo "- " . $row[0] . "<br>";
}
?>