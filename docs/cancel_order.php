<?php
/**
 * Cancel Order
 * Updates order status to 'Cancelled' and payment status to 'Refunded' if current status is 'Pending'
 * Uses OrderStatus only (WebsiteStatus removed)
 */

header('Content-Type: application/json; charset=utf-8');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database connection
require_once(__DIR__ . '/api/config/db_config.php');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id']) || !isset($input['customer_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Order ID and Customer ID are required'
    ]);
    exit;
}

$orderId = intval($input['order_id']);
$customerId = intval($input['customer_id']);

try {
    // Start transaction
    $conn->begin_transaction();
    
    // First, verify the order belongs to the customer and check current status
    // FIXED: Only check OrderStatus (WebsiteStatus removed)
    $checkSql = "SELECT OrderStatus, OrderDate FROM orders 
                 WHERE OrderID = ? AND CustomerID = ? AND OrderSource = 'Website'";
    $checkStmt = $conn->prepare($checkSql);
    
    if (!$checkStmt) {
        throw new Exception('Failed to prepare check statement: ' . $conn->error);
    }
    
    $checkStmt->bind_param("ii", $orderId, $customerId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->rollback();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Order not found'
        ]);
        exit;
    }
    
    $order = $result->fetch_assoc();
    $checkStmt->close();
    
    // Check if order can be cancelled (must be Pending)
    // FIXED: Only use OrderStatus
    $currentStatus = $order['OrderStatus'];
    
    if (strtolower($currentStatus) !== 'pending') {
        $conn->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Only pending orders can be cancelled. Current status: ' . $currentStatus
        ]);
        exit;
    }
    
    // Optional: Check if order is not too old (uncomment if needed)
    /*
    $orderDate = new DateTime($order['OrderDate']);
    $today = new DateTime();
    $daysDiff = $today->diff($orderDate)->days;
    
    if ($daysDiff > 1) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot cancel orders older than 1 day'
        ]);
        exit;
    }
    */
    
    // Update order status to Cancelled
    // FIXED: Only update OrderStatus (WebsiteStatus removed)
    $updateOrderSql = "UPDATE orders 
                       SET OrderStatus = 'Cancelled',
                           UpdatedDate = NOW()
                       WHERE OrderID = ? AND CustomerID = ?";
    $updateOrderStmt = $conn->prepare($updateOrderSql);
    
    if (!$updateOrderStmt) {
        throw new Exception('Failed to prepare order update statement: ' . $conn->error);
    }
    
    $updateOrderStmt->bind_param("ii", $orderId, $customerId);
    
    if (!$updateOrderStmt->execute()) {
        throw new Exception('Failed to cancel order: ' . $updateOrderStmt->error);
    }
    
    $updateOrderStmt->close();
    
    // Update payment status to Refunded for this order
    $updatePaymentSql = "UPDATE payments 
                         SET PaymentStatus = 'Refunded',
                             Notes = CONCAT(COALESCE(Notes, ''), '\nOrder cancelled by customer on ', NOW())
                         WHERE OrderID = ? AND PaymentSource = 'Website'";
    $updatePaymentStmt = $conn->prepare($updatePaymentSql);
    
    if (!$updatePaymentStmt) {
        throw new Exception('Failed to prepare payment update statement: ' . $conn->error);
    }
    
    $updatePaymentStmt->bind_param("i", $orderId);
    $updatePaymentStmt->execute();
    $updatePaymentStmt->close();
    
    // Commit transaction
    $conn->commit();
    
    error_log("SUCCESS: Order #$orderId cancelled by customer #$customerId");
    
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully. Payment status updated to Refunded.'
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }
    
    error_log("ERROR cancelling order: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error cancelling order: ' . $e->getMessage()
    ]);
}
?>