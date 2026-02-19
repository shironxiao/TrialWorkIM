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
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error.log');

// ----------------------
// Load credentials from INI
// ----------------------
$ini_file = __DIR__ . '/db_config.ini';

if (!file_exists($ini_file)) {
    error_log("Database configuration file not found: " . $ini_file);
    die("Configuration error. Please contact support.");
}

$config = parse_ini_file($ini_file);

if (!$config) {
    error_log("Failed to parse database configuration file: " . $ini_file);
    die("Configuration error. Please contact support.");
}

define('DB_HOST', $config['server']);
define('DB_USER', $config['username']);
define('DB_PASS', $config['password']);
define('DB_NAME', $config['database']);
define('DB_PORT', $config['port']);

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
    if (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {

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

?>