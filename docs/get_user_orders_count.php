<?php
/**
 * Get User Orders Count
 * Counts BOTH orders and reservations (excluding cancelled ones)
 * Returns combined total for display in Profile
 * Uses OrderStatus only (WebsiteStatus removed)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once(__DIR__ . '/api/config/db_config.php');

$customerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if ($customerId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID'
    ]);
    exit();
}

// ============================================================
// Count orders (excluding cancelled ones)
// FIXED: Only check OrderStatus (WebsiteStatus removed)
// ============================================================
$sql_orders = "SELECT COUNT(*) AS total_orders
               FROM orders
               WHERE CustomerID = ?
                 AND OrderSource = 'Website'
                 AND (OrderStatus IS NULL OR LOWER(OrderStatus) != 'cancelled')";

$stmt_orders = $conn->prepare($sql_orders);

if (!$stmt_orders) {
    echo json_encode([
        'success' => false,
        'message' => 'Query preparation failed: ' . $conn->error
    ]);
    exit();
}

$stmt_orders->bind_param("i", $customerId);
$stmt_orders->execute();
$result_orders = $stmt_orders->get_result();
$orders_count = 0;

if ($result_orders && $result_orders->num_rows > 0) {
    $row = $result_orders->fetch_assoc();
    $orders_count = intval($row['total_orders']);
}
$stmt_orders->close();

// ============================================================
// Count reservations (excluding cancelled ones)
// ============================================================
$sql_reservations = "SELECT COUNT(*) AS total_reservations
                     FROM reservations
                     WHERE CustomerID = ?
                       AND (ReservationStatus IS NULL OR LOWER(ReservationStatus) != 'cancelled')";

$stmt_reservations = $conn->prepare($sql_reservations);

if (!$stmt_reservations) {
    echo json_encode([
        'success' => false,
        'message' => 'Query preparation failed: ' . $conn->error
    ]);
    exit();
}

$stmt_reservations->bind_param("i", $customerId);
$stmt_reservations->execute();
$result_reservations = $stmt_reservations->get_result();
$reservations_count = 0;

if ($result_reservations && $result_reservations->num_rows > 0) {
    $row = $result_reservations->fetch_assoc();
    $reservations_count = intval($row['total_reservations']);
}
$stmt_reservations->close();

// ============================================================
// Total is orders + reservations (both excluding cancelled)
// ============================================================
$total_count = $orders_count + $reservations_count;

error_log("DEBUG: Customer #$customerId - Orders: $orders_count, Reservations: $reservations_count, Total: $total_count");

echo json_encode([
    'success' => true,
    'total_orders' => $total_count,
    'orders_count' => $orders_count,
    'reservations_count' => $reservations_count,
    'customer_id' => $customerId
]);

$conn->close();
?>