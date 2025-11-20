<?php
/**
 * Get User's Reservations (Account Isolated)
 * Returns ONLY the logged-in user's reservations
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
            r.*,
            COUNT(ri.ReservationItemID) as ItemCount,
            COALESCE(SUM(ri.TotalPrice), 0) as TotalCost
        FROM reservations r
        LEFT JOIN reservation_items ri ON r.ReservationID = ri.ReservationID
        WHERE r.CustomerID = ?
        GROUP BY r.ReservationID
        ORDER BY r.EventDate DESC, r.EventTime DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }
    
    $stmt->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'reservations' => $reservations,
        'count' => count($reservations)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving reservations: ' . $e->getMessage()
    ]);
}

?>