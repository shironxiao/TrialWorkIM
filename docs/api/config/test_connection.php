<?php
/**
 * Test Database Connection
 * File: api/config/test_connection.php
 */

header('Content-Type: application/json; charset=utf-8');

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data'
    ]);
    exit;
}

// Extract connection parameters
$host = $data['host'] ?? '';
$port = $data['port'] ?? '3306';
$database = $data['database'] ?? '';
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// Validate required fields
if (empty($host) || empty($database) || empty($username)) {
    echo json_encode([
        'success' => false,
        'message' => 'Host, database name, and username are required'
    ]);
    exit;
}

// Try to connect
try {
    $conn = new mysqli($host, $username, $password, $database, $port);
    
    if ($conn->connect_error) {
        echo json_encode([
            'success' => false,
            'message' => $conn->connect_error
        ]);
        exit;
    }
    
    // Connection successful
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Connection successful!'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>