<?php
/**
 * Process Order Payment (Account Isolated)
 */

require_once(__DIR__ . '/../auth/session.php');
require_once(__DIR__ . '/../config/db_config.php');

setJsonHeaders();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$customerId = getCurrentUserId();

try {
    $orderId = $data['orderId'] ?? null;
    
    if (!$orderId) {
        throw new Exception('Order ID is required');
    }
    
    // ✅ VERIFY ORDER BELONGS TO USER
    $sql = "SELECT * FROM orders WHERE OrderID = ? AND CustomerID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    
    $stmt->bind_param("ii", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'This order does not belong to you'
        ]);
        exit;
    }
    
    $stmt->close();
    
    // Process payment
    $paymentSql = "INSERT INTO payments (
        OrderID, PaymentMethod, PaymentStatus, AmountPaid, PaymentSource
    ) VALUES (?, ?, ?, ?, ?)";
    
    $paymentStmt = $conn->prepare($paymentSql);
    if (!$paymentStmt) {
        throw new Exception($conn->error);
    }
    
    $paymentMethod = $data['paymentMethod'] ?? 'Cash';
    $paymentStatus = 'Completed';
    $amountPaid = $data['amountPaid'] ?? 0;
    $paymentSource = 'Website';
    
    $paymentStmt->bind_param(
        "issds",
        $orderId,
        $paymentMethod,
        $paymentStatus,
        $amountPaid,
        $paymentSource
    );
    
    if (!$paymentStmt->execute()) {
        throw new Exception($paymentStmt->error);
    }
    
    $paymentStmt->close();
    
    // Update order status
    $updateSql = "UPDATE orders SET OrderStatus = 'Completed' WHERE OrderID = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("i", $orderId);
    $updateStmt->execute();
    $updateStmt->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error processing payment: ' . $e->getMessage()
    ]);
}

?>