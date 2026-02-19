<?php
/**
 * Get User Reservations
 * Fetches all reservations for a specific customer with their items
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
    // Fetch reservations for this customer - FIXED: Use COALESCE to handle NULL status
    $sql = "SELECT ReservationID, EventDate, EventTime, EventType, NumberOfGuests, 
                   COALESCE(ReservationStatus, 'Pending') as ReservationStatus, 
                   DeliveryOption, DeliveryAddress
            FROM reservations 
            WHERE CustomerID = ?
            ORDER BY EventDate DESC, EventTime DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $reservations = [];

    while ($row = $result->fetch_assoc()) {
        $reservation_id = $row['ReservationID'];

        // Fetch items for this reservation
        $item_sql = "SELECT ProductName, Quantity, UnitPrice, TotalPrice 
                     FROM reservation_items 
                     WHERE ReservationID = ?";

        $item_stmt = $conn->prepare($item_sql);
        if ($item_stmt) {
            $item_stmt->bind_param("i", $reservation_id);
            $item_stmt->execute();
            $item_result = $item_stmt->get_result();

            $items = [];
            $total_price = 0;

            while ($item = $item_result->fetch_assoc()) {
                $items[] = [
                    'name' => $item['ProductName'],
                    'quantity' => intval($item['Quantity']),
                    'price' => floatval($item['UnitPrice'])
                ];
                $total_price += floatval($item['TotalPrice']);
            }

            // Store items as JSON string (ViewOrder.html expects to parse it)
            $row['items'] = json_encode($items);
            $row['TotalPrice'] = $total_price;
            $item_stmt->close();
        } else {
            $row['items'] = '[]';
            $row['TotalPrice'] = 0;
        }

        $reservations[] = $row;
    }

    $stmt->close();
    $conn->close();

    error_log("DEBUG: Found " . count($reservations) . " reservations for customer ID: " . $customer_id);

    echo json_encode([
        'success' => true,
        'reservations' => $reservations,
        'count' => count($reservations)
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }

    error_log("ERROR fetching reservations: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching reservations: ' . $e->getMessage()
    ]);
}
?>