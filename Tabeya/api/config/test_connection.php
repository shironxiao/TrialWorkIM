<?php
// Show errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_config.php';

// If connection succeeds, show success message
echo json_encode([
    'status' => 'success',
    'message' => 'Connected successfully to database: ' . DB_NAME
]);
?>
