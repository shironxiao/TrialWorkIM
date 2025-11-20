<?php
/**
 * Customer Profile Endpoint
 * Gets current user's profile data (protected)
 */

require_once(__DIR__ . '/../auth/session.php');
require_once(__DIR__ . '/../config/db_config.php');
require_once(__DIR__ . '/../functions/security.php');
require_once(__DIR__ . '/../functions/Customer.php');

setJsonHeaders();

// ✅ REQUIRE LOGIN - Only logged-in users can access
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only GET requests are allowed.'
    ]);
    exit;
}

$customerId = getCurrentUserId();
$customer = new Customer($conn);

try {
    // Get customer data by ID
    $sql = "SELECT * FROM customers WHERE CustomerID = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Customer not found.'
        ]);
        exit;
    }
    
    $customerData = $result->fetch_assoc();
    $stmt->close();
    
    // Remove sensitive data
    unset($customerData['PasswordHash']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'customer' => $customerData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving profile: ' . $e->getMessage()
    ]);
}

?>