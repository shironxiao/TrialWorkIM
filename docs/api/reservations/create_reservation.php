<?php
/**
 * Create Reservation
 * Creates a new reservation with account isolation
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
    
    // Validate required fields
    $required = ['eventDate', 'eventTime', 'numberOfGuests', 'eventType', 'serviceType'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Create reservation
    $sql = "INSERT INTO reservations (
        CustomerID, ReservationType, EventType, EventDate, EventTime,
        NumberOfGuests, ProductSelection, SpecialRequests, Address,
        ServiceType, DeliveryOption, ContactNumber, ReservationStatus
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    
    $reservationType = 'Online';
    $status = 'Pending';
    
    $stmt->bind_param(
        "issssiisssss",
        $customerId,
        $reservationType,
        $data['eventType'],
        $data['eventDate'],
        $data['eventTime'],
        $data['numberOfGuests'],
        $data['productSelection'] ?? null,
        $data['specialRequests'] ?? null,
        $data['address'] ?? null,
        $data['serviceType'],
        $data['deliveryOption'] ?? null,
        $data['contactNumber'] ?? null,
        $status
    );
    
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    
    $reservationId = $stmt->insert_id;
    $stmt->close();
    
    // Add reservation items if provided
    if (!empty($data['items'])) {
        $itemSql = "INSERT INTO reservation_items (
            ReservationID, ProductName, Quantity, UnitPrice, TotalPrice
        ) VALUES (?, ?, ?, ?, ?)";
        
        $itemStmt = $conn->prepare($itemSql);
        if (!$itemStmt) {
            throw new Exception($conn->error);
        }
        
        foreach ($data['items'] as $item) {
            $totalPrice = $item['quantity'] * $item['price'];
            
            $itemStmt->bind_param(
                "isidi",
                $reservationId,
                $item['name'],
                $item['quantity'],
                $item['price'],
                $totalPrice
            );
            
            if (!$itemStmt->execute()) {
                throw new Exception($itemStmt->error);
            }
        }
        
        $itemStmt->close();
    }
    
    // Log transaction
    logTransaction($customerId, 'RESERVATION_CREATED', 'Reservation #' . $reservationId . ' created');
    
    $conn->commit();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Reservation created successfully',
        'reservationId' => $reservationId
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error creating reservation: ' . $e->getMessage()
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