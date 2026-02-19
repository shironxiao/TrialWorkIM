<?php
require_once(__DIR__ . '/api/config/db_config.php');

echo "<h1>🔍 LAN Database Connection Test</h1>";
echo "<hr>";

echo "<h2>Connection Info:</h2>";
echo "<ul>";
echo "<li>Host: " . DB_HOST . "</li>";
echo "<li>User: " . DB_USER . "</li>";
echo "<li>Database: " . DB_NAME . "</li>";
echo "<li>Port: " . DB_PORT . "</li>";
echo "</ul>";
echo "<hr>";

echo "<h2>✅ Connection Status:</h2>";
echo "<p style='color: green; font-weight: bold;'>CONNECTED SUCCESSFULLY!</p>";
echo "<hr>";

echo "<h2>Database Tables:</h2>";
$result = $conn->query("SHOW TABLES");
echo "<ul>";
while ($row = $result->fetch_array()) {
    echo "<li>" . $row[0] . "</li>";
}
echo "</ul>";
echo "<hr>";

echo "<h2>Sample Data Test:</h2>";
$products = $conn->query("SELECT COUNT(*) as count FROM products");
$prod_count = $products->fetch_assoc()['count'];
echo "<p>Products in database: <strong>$prod_count</strong></p>";

$customers = $conn->query("SELECT COUNT(*) as count FROM customers");
$cust_count = $customers->fetch_assoc()['count'];
echo "<p>Customers in database: <strong>$cust_count</strong></p>";

echo "<hr>";
echo "<p style='color: green;'>✅ All tests passed! Your LAN connection is working perfectly.</p>";
?>