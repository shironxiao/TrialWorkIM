<?php
/**
 * Get User Orders
 * Fetches all orders for a specific customer with their items
 * Uses OrderStatus only (WebsiteStatus removed)
 */

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once(__DIR__ . '/api/config/db_config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if ($customer_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid Customer ID is required']);
    exit;
}

try {
    // Fetch orders for this customer - Using OrderStatus only
    $sql = "SELECT OrderID, OrderDate, OrderTime, TotalAmount, 
                   COALESCE(OrderStatus, 'Pending') as OrderStatus, 
                   OrderType, DeliveryOption, DeliveryAddress
            FROM orders 
            WHERE CustomerID = ? AND OrderSource = 'Website'
            ORDER BY OrderDate DESC, OrderTime DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];

    while ($row = $result->fetch_assoc()) {
        $order_id = $row['OrderID'];

        // Fetch items for this order
        $item_sql = "SELECT ProductName, Quantity, UnitPrice 
                     FROM order_items 
                     WHERE OrderID = ?";

        $item_stmt = $conn->prepare($item_sql);
        if ($item_stmt) {
            $item_stmt->bind_param("i", $order_id);
            $item_stmt->execute();
            $item_result = $item_stmt->get_result();

            $items = [];
            while ($item = $item_result->fetch_assoc()) {
                $items[] = [
                    'name' => $item['ProductName'],
                    'quantity' => intval($item['Quantity']),
                    'price' => floatval($item['UnitPrice'])
                ];
            }

            // Store items as JSON string (ViewOrder.html expects to parse it)
            $row['items'] = json_encode($items);
            $item_stmt->close();
        } else {
            $row['items'] = '[]';
        }

        $orders[] = $row;
    }

    $stmt->close();
    $conn->close();

    error_log("DEBUG: Found " . count($orders) . " orders for customer ID: " . $customer_id);

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }

    error_log("ERROR fetching orders: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching orders: ' . $e->getMessage()
    ]);
}
?>