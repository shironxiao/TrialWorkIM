<?php
/**
 * Save Database Configuration
 * File: api/config/save_config.php
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

// Extract configuration parameters
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

// Path to db_config.php
$configPath = __DIR__ . '/db_config.php';

// Create the PHP configuration file content
$configContent = <<<PHP
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
// Database credentials
// ----------------------
define('DB_HOST', '{$host}');
define('DB_USER', '{$username}');
define('DB_PASS', '{$password}');
define('DB_NAME', '{$database}');
define('DB_PORT', '{$port}');

// ----------------------
// Create connection
// ----------------------
\$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// ----------------------
// Check connection
// ----------------------
if (\$conn->connect_error) {
    // Log detailed error
    error_log("Database connection failed: " . \$conn->connect_error);

    // For AJAX requests: return JSON error
    if (!empty(\$_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

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
\$conn->set_charset("utf8mb4");

?>
PHP;

// Try to write the configuration file
try {
    // Check if directory is writable
    $configDir = dirname($configPath);
    if (!is_writable($configDir)) {
        echo json_encode([
            'success' => false,
            'message' => 'Configuration directory is not writable. Please check permissions.'
        ]);
        exit;
    }
    
    // Write the file
    $result = file_put_contents($configPath, $configContent);
    
    if ($result === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to write configuration file'
        ]);
        exit;
    }
    
    // Test the new configuration by including it
    @include($configPath);
    
    if (!isset($conn) || $conn->connect_error) {
        echo json_encode([
            'success' => false,
            'message' => 'Configuration saved but connection test failed'
        ]);
        exit;
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuration saved successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>