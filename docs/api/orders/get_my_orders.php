<?php
/**
 * Get User's Orders (Account Isolated)
 * Returns ONLY the logged-in user's orders
 */

require_once(__DIR__ . '/../auth/session.php');
require_once(__DIR__ . '/../config/db_config.php');

setJsonHeaders();
requireLogin();  // ✅ User must be logged in

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
    exit;
}

$customerId = getCurrentUserId();  // ✅ Get user ID from session

try {
    // ✅ ACCOUNT ISOLATION: WHERE CustomerID = $customerId
    $sql = "SELECT 
            o.*,
            COUNT(oi.OrderItemID) as ItemCount,
            GROUP_CONCAT(oi.ProductName SEPARATOR ', ') as Items
        FROM orders o
        LEFT JOIN order_items oi ON o.OrderID = oi.OrderID
        WHERE o.CustomerID = ?
        GROUP BY o.OrderID
        ORDER BY o.OrderDate DESC, o.OrderTime DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    $stmt->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving orders: ' . $e->getMessage()
    ]);
}

?>