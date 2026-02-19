<?php
/**
 * Cancel Reservation
 * Updates reservation status to 'Cancelled' and payment status to 'Refunded' if current status is 'Pending'
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

if (!isset($input['reservation_id']) || !isset($input['customer_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Reservation ID and Customer ID are required'
    ]);
    exit;
}

$reservationId = intval($input['reservation_id']);
$customerId = intval($input['customer_id']);

try {
    // Start transaction
    $conn->begin_transaction();
    
    // First, verify the reservation belongs to the customer and check current status
    $checkSql = "SELECT ReservationStatus, EventDate FROM reservations 
                 WHERE ReservationID = ? AND CustomerID = ?";
    $checkStmt = $conn->prepare($checkSql);
    
    if (!$checkStmt) {
        throw new Exception('Failed to prepare check statement: ' . $conn->error);
    }
    
    $checkStmt->bind_param("ii", $reservationId, $customerId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->rollback();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Reservation not found'
        ]);
        exit;
    }
    
    $reservation = $result->fetch_assoc();
    $checkStmt->close();
    
    // Check if reservation can be cancelled (must be Pending)
    $currentStatus = $reservation['ReservationStatus'];
    
    if (strtolower($currentStatus) !== 'pending') {
        $conn->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Only pending reservations can be cancelled. Current status: ' . $currentStatus
        ]);
        exit;
    }
    
    // Optional: Check if event date hasn't passed (uncomment if needed)
    /*
    $eventDate = new DateTime($reservation['EventDate']);
    $today = new DateTime();
    
    if ($eventDate < $today) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot cancel reservations for past events'
        ]);
        exit;
    }
    */
    
    // Update reservation status to Cancelled
    $updateReservationSql = "UPDATE reservations 
                             SET ReservationStatus = 'Cancelled',
                                 UpdatedDate = NOW()
                             WHERE ReservationID = ? AND CustomerID = ?";
    $updateReservationStmt = $conn->prepare($updateReservationSql);
    
    if (!$updateReservationStmt) {
        throw new Exception('Failed to prepare reservation update statement: ' . $conn->error);
    }
    
    $updateReservationStmt->bind_param("ii", $reservationId, $customerId);
    
    if (!$updateReservationStmt->execute()) {
        throw new Exception('Failed to cancel reservation: ' . $updateReservationStmt->error);
    }
    
    $updateReservationStmt->close();
    
    // Update payment status to Refunded for this reservation
    $updatePaymentSql = "UPDATE payments 
                         SET PaymentStatus = 'Refunded',
                             Notes = CONCAT(COALESCE(Notes, ''), '\nReservation cancelled by customer on ', NOW())
                         WHERE ReservationID = ?";
    $updatePaymentStmt = $conn->prepare($updatePaymentSql);
    
    if (!$updatePaymentStmt) {
        throw new Exception('Failed to prepare payment update statement: ' . $conn->error);
    }
    
    $updatePaymentStmt->bind_param("i", $reservationId);
    $updatePaymentStmt->execute();
    $updatePaymentStmt->close();
    
    // Commit transaction
    $conn->commit();
    
    error_log("SUCCESS: Reservation #$reservationId cancelled by customer #$customerId");
    
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Reservation cancelled successfully. Payment status updated to Refunded.'
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }
    
    error_log("ERROR cancelling reservation: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error cancelling reservation: ' . $e->getMessage()
    ]);
}
?>