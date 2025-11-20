<?php
/**
 * Create Order
 * Creates a new order with account isolation
 */

require_once(__DIR__ . '/../auth/session.php');
require_once(__DIR__ . '/../config/db_config.php');
require_once(__DIR__ . '/../functions/security.php');

setJsonHeaders();
requireLogin();  // ✅ Must be logged in

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$customerId = getCurrentUserId();  // ✅ Get user ID from session

try {
    $conn->begin_transaction();
    
    // Create order
    $orderDate = date('Y-m-d');
    $orderTime = date('H:i:s');
    $receiptNumber = 'ORD' . date('YmdHis') . rand(100, 999);
    
    $sql = "INSERT INTO orders (
        CustomerID, OrderType, OrderSource, ReceiptNumber, 
        NumberOfDiners, OrderDate, OrderTime, ItemsOrderedCount, 
        TotalAmount, OrderStatus
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    
    $orderType = $data['orderType'] ?? 'Takeout';
    $numberOfDiners = $data['numberOfDiners'] ?? 1;
    $itemsCount = count($data['items'] ?? []);
    $totalAmount = $data['totalAmount'] ?? 0;
    $orderStatus = 'Preparing';
    
    $stmt->bind_param(
        "iissiisid",
        $customerId, $orderType, 'Website', $receiptNumber,
        $numberOfDiners, $orderDate, $orderTime, $itemsCount,
        $totalAmount, $orderStatus
    );
    
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    
    $orderId = $stmt->insert_id;
    $stmt->close();
    
    // Add order items
    if (!empty($data['items'])) {
        $itemSql = "INSERT INTO order_items (
            OrderID, ProductName, Quantity, UnitPrice, SpecialInstructions
        ) VALUES (?, ?, ?, ?, ?)";
        
        $itemStmt = $conn->prepare($itemSql);
        if (!$itemStmt) {
            throw new Exception($conn->error);
        }
        
        foreach ($data['items'] as $item) {
            $itemStmt->bind_param(
                "isids",
                $orderId, $item['name'], $item['quantity'], 
                $item['price'], $item['instructions'] ?? null
            );
            
            if (!$itemStmt->execute()) {
                throw new Exception($itemStmt->error);
            }
        }
        
        $itemStmt->close();
    }
    
    // Log the transaction
    logTransaction($customerId, 'ORDER_CREATED', 'Order #' . $receiptNumber . ' created');
    
    $conn->commit();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'orderId' => $orderId,
        'receiptNumber' => $receiptNumber
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error creating order: ' . $e->getMessage()
    ]);
}

function logTransaction($customerId, $type, $details) {
    global $conn;
    $sql = "INSERT INTO customer_logs (CustomerID, TransactionType, Details) 
            VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $customerId, $type, $details);
    $stmt->execute();
    $stmt->close();
}

?>