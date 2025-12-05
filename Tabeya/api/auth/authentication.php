<?php
/**
 * SELF-CONTAINED AUTHENTICATION
 * All functions built-in, no external dependencies
 */

// START OUTPUT BUFFERING FIRST
ob_start();

// Configure error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error.log');

// START SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// SET JSON HEADER
header('Content-Type: application/json; charset=utf-8');

// ERROR HANDLERS
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (ob_get_level()) ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

set_exception_handler(function($e) {
    if (ob_get_level()) ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
    exit;
});

// DATABASE CONNECTION
require_once(__DIR__ . '/../config/db_config.php');

$conn->set_charset("utf8mb4");

// ============================================================
// HELPER FUNCTIONS (Built-in)
// ============================================================

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateContactNumber($contactNumber) {
    $cleanNumber = preg_replace('/\D/', '', $contactNumber);
    return strlen($cleanNumber) === 11 && strpos($cleanNumber, '09') === 0;
}

function validatePassword($password) {
    if (strlen($password) < 6) {
        return ['valid' => false, 'message' => 'Password must be at least 6 characters'];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain at least one uppercase letter'];
    }
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain at least one number'];
    }
    return ['valid' => true];
}

function emailExists($conn, $email) {
    $sql = "SELECT COUNT(*) as count FROM customers WHERE Email = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] > 0;
}

// ============================================================
// GET REQUEST DATA
// ============================================================

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (ob_get_level()) ob_clean();
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests allowed'
    ]);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    $data = $_POST;
}

// ============================================================
// REGISTRATION
// ============================================================

if ($action === 'register') {
    try {
        // Check required fields
        $required = ['firstName', 'lastName', 'email', 'contactNumber', 'password'];
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $missing[] = $field;
            }
        }
        
        if (count($missing) > 0) {
            if (ob_get_level()) ob_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing fields: ' . implode(', ', $missing)
            ]);
            exit;
        }
        
        $firstName = trim($data['firstName']);
        $lastName = trim($data['lastName']);
        $email = trim($data['email']);
        $contactNumber = trim($data['contactNumber']);
        $password = $data['password'];
        
        // Validate email
        if (!validateEmail($email)) {
            if (ob_get_level()) ob_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email format'
            ]);
            exit;
        }
        
        // Validate contact number
        if (!validateContactNumber($contactNumber)) {
            if (ob_get_level()) ob_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Contact number must be 09XXXXXXXXX'
            ]);
            exit;
        }
        
        // Validate password
        $passwordCheck = validatePassword($password);
        if (!$passwordCheck['valid']) {
            if (ob_get_level()) ob_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $passwordCheck['message']
            ]);
            exit;
        }
        
        // Check if email exists
        if (emailExists($conn, $email)) {
            if (ob_get_level()) ob_clean();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'An account with this email already exists'
            ]);
            exit;
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Insert customer
        $sql = "INSERT INTO customers 
               (FirstName, LastName, Email, PasswordHash, ContactNumber, 
                CustomerType, FeedbackCount, TotalOrdersCount, ReservationCount, 
                AccountStatus, SatisfactionRating, CreatedDate) 
               VALUES (?, ?, ?, ?, ?, 'Online', 0, 0, 0, 'Active', 0.00, NOW())";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("sssss", $firstName, $lastName, $email, $passwordHash, $contactNumber);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $customerId = $stmt->insert_id;
        $stmt->close();
        
        // Set session
        $_SESSION['customer_id'] = $customerId;
        $_SESSION['customer_email'] = $email;
        $_SESSION['customer_name'] = $firstName . ' ' . $lastName;
        $_SESSION['customer_firstname'] = $firstName;
        $_SESSION['customer_lastname'] = $lastName;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Return success
        if (ob_get_level()) ob_clean();
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'customerId' => $customerId,
            'message' => 'Registration successful!',
            'customer' => [
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'Email' => $email,
                'CustomerID' => $customerId,
                'ContactNumber' => $contactNumber
            ]
        ]);
        
    } catch (Exception $e) {
        if (ob_get_level()) ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// ============================================================
// LOGIN
// ============================================================

else if ($action === 'login') {
    try {
        if (!isset($data['email']) || !isset($data['password'])) {
            if (ob_get_level()) ob_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Email and password required'
            ]);
            exit;
        }
        
        $email = trim($data['email']);
        $password = $data['password'];
        
        // Validate email
        if (!validateEmail($email)) {
            if (ob_get_level()) ob_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email format'
            ]);
            exit;
        }
        
        // Get customer
        $sql = "SELECT * FROM customers WHERE Email = ? AND AccountStatus = 'Active'";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            if (ob_get_level()) ob_clean();
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Email not found or account inactive'
            ]);
            exit;
        }
        
        $customer = $result->fetch_assoc();
        $stmt->close();
        
        // Verify password
        if (!password_verify($password, $customer['PasswordHash'])) {
            if (ob_get_level()) ob_clean();
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid password'
            ]);
            exit;
        }
        
        // Update last login
        $updateSql = "UPDATE customers SET LastLoginDate = NOW(), LastTransactionDate = NOW() WHERE CustomerID = ?";
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt) {
            $updateStmt->bind_param("i", $customer['CustomerID']);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        // Set session
        $_SESSION['customer_id'] = $customer['CustomerID'];
        $_SESSION['customer_email'] = $customer['Email'];
        $_SESSION['customer_name'] = $customer['FirstName'] . ' ' . $customer['LastName'];
        $_SESSION['customer_firstname'] = $customer['FirstName'];
        $_SESSION['customer_lastname'] = $customer['LastName'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Remove sensitive data
        unset($customer['PasswordHash']);
        
        // Return success
        if (ob_get_level()) ob_clean();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'customer' => $customer
        ]);
        
    } catch (Exception $e) {
        if (ob_get_level()) ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// ============================================================
// LOGOUT
// ============================================================

else if ($action === 'logout') {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    if (ob_get_level()) ob_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

// ============================================================
// INVALID ACTION
// ============================================================

else {
    if (ob_get_level()) ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action: ' . $action
    ]);
}

ob_end_flush();
$conn->close();
?>