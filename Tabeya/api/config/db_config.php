<?php
/**
 * ============================================================
 * CENTRALIZED DATABASE CONNECTION
 * Location: api/config/db_config.php
 * Use this ONE file for ALL database connections
 * ============================================================
 */

// Enable error reporting for development (turn off in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error.log');

// ----------------------
// Database credentials
// ----------------------
define('DB_HOST', '192.168.110.105');   // Admin PC IP (main server)
define('DB_USER', 'friend');            // LAN MySQL user
define('DB_PASS', '');                  // Password
define('DB_NAME', 'tabeya_system');     // Database name
define('DB_PORT', '3306');                // MySQL port

// ----------------------
// Create connection
// ----------------------
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// ----------------------
// Check connection
// ----------------------
if ($conn->connect_error) {
    // Log detailed error
    error_log("Database connection failed: " . $conn->connect_error);

    // For AJAX requests: return JSON error
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

        header('Content-Type: application/json');
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed. Please try again later.'
        ]);
        exit;
    }

    // For regular page requests: display simple message
    die("Database connection error. Please contact support.");
}

// ----------------------
// Set charset
// ----------------------
$conn->set_charset("utf8mb4");

// ----------------------
// Helper function to close connection
// ----------------------
function closeConnection() {
    global $conn;
    if ($conn) {
        $conn->close();
    }
}

// Register shutdown function to close connection automatically
register_shutdown_function('closeConnection');

?>
