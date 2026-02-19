<?php
/**
 * Submit Customer Feedback - Fixed Version
 * Supports Order/Reservation/General reviews with anonymous option
 */

// Clean output buffer and disable error display
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // ✅ Prevent HTML errors in JSON
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

header('Content-Type: application/json; charset=utf-8');

// Custom error handlers that return clean JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: $errstr in " . basename($errfile) . " line $errline");
    if (ob_get_level()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "An error occurred. Please try again."]);
    exit;
});

set_exception_handler(function($e) {
    error_log("Exception: " . $e->getMessage());
    if (ob_get_level()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    exit;
});

// Database connection
require_once(__DIR__ . '/api/config/db_config.php');
require_once(__DIR__ . '/api/functions/activity_logger.php');

try {
    // Check method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }

    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    // Validate required fields
    if (empty($data['customerId'])) {
        throw new Exception('Customer ID is required');
    }

    if (empty($data['overallRating']) || $data['overallRating'] < 1 || $data['overallRating'] > 5) {
        throw new Exception('Overall rating (1-5) is required');
    }

    // Verify customer
    $customerId = intval($data['customerId']);
    
    $stmt = $conn->prepare("SELECT FirstName, LastName FROM customers WHERE CustomerID = ? AND AccountStatus = 'Active'");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Invalid customer account');
    }

    $customer = $result->fetch_assoc();
    $stmt->close();

    // Extract feedback data
    $feedbackType = isset($data['feedbackType']) ? $data['feedbackType'] : 'General';
    $orderId = isset($data['orderId']) && $data['orderId'] > 0 ? intval($data['orderId']) : NULL;
    $reservationId = isset($data['reservationId']) && $data['reservationId'] > 0 ? intval($data['reservationId']) : NULL;
    $isAnonymous = isset($data['isAnonymous']) && $data['isAnonymous'] ? 1 : 0;
    
    $overallRating = floatval($data['overallRating']);
    $foodRating = isset($data['foodRating']) && $data['foodRating'] > 0 ? intval($data['foodRating']) : NULL;
    $portionRating = isset($data['portionRating']) && $data['portionRating'] > 0 ? intval($data['portionRating']) : NULL;
    $serviceRating = isset($data['serviceRating']) && $data['serviceRating'] > 0 ? intval($data['serviceRating']) : NULL;
    $ambienceRating = isset($data['ambienceRating']) && $data['ambienceRating'] > 0 ? intval($data['ambienceRating']) : NULL;
    $cleanlinessRating = isset($data['cleanlinessRating']) && $data['cleanlinessRating'] > 0 ? intval($data['cleanlinessRating']) : NULL;
    
    $foodComment = isset($data['foodComment']) && trim($data['foodComment']) !== '' ? trim($data['foodComment']) : NULL;
    $portionComment = isset($data['portionComment']) && trim($data['portionComment']) !== '' ? trim($data['portionComment']) : NULL;
    $serviceComment = isset($data['serviceComment']) && trim($data['serviceComment']) !== '' ? trim($data['serviceComment']) : NULL;
    $ambienceComment = isset($data['ambienceComment']) && trim($data['ambienceComment']) !== '' ? trim($data['ambienceComment']) : NULL;
    $cleanlinessComment = isset($data['cleanlinessComment']) && trim($data['cleanlinessComment']) !== '' ? trim($data['cleanlinessComment']) : NULL;
    $reviewMessage = isset($data['reviewMessage']) && trim($data['reviewMessage']) !== '' ? trim($data['reviewMessage']) : NULL;

    // Validate feedback type specific requirements
    if ($feedbackType === 'Order' && !$orderId) {
        throw new Exception('Order ID is required for order feedback');
    }
    if ($feedbackType === 'Reservation' && !$reservationId) {
        throw new Exception('Reservation ID is required for reservation feedback');
    }

    // SQL - Insert into customer_feedback
    $sql = "INSERT INTO customer_feedback (
                CustomerID, OrderID, ReservationID, FeedbackType,
                OverallRating, 
                FoodTasteRating, PortionSizeRating, ServiceRating, 
                AmbienceRating, CleanlinessRating,
                FoodTasteComment, PortionSizeComment, ServiceComment,
                AmbienceComment, CleanlinessComment, ReviewMessage,
                IsAnonymous, Status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    // Bind parameters (17 values)
    $stmt->bind_param(
        "iiisdiiiiissssssi",
        $customerId,
        $orderId,
        $reservationId,
        $feedbackType,
        $overallRating,
        $foodRating,
        $portionRating,
        $serviceRating,
        $ambienceRating,
        $cleanlinessRating,
        $foodComment,
        $portionComment,
        $serviceComment,
        $ambienceComment,
        $cleanlinessComment,
        $reviewMessage,
        $isAnonymous
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $feedbackId = $stmt->insert_id;
    $stmt->close();

    // Log the activity
    $customerName = $customer['FirstName'] . ' ' . $customer['LastName'];
    logCustomerReview(
        $conn, 
        $customerId, 
        $customerName, 
        $feedbackId, 
        $feedbackType, 
        $overallRating, 
        $isAnonymous
    );
    
    // ✅ Manually close connection before output
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

    // Success response
    if (ob_get_level()) ob_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your feedback has been submitted and is pending approval.',
        'feedbackId' => $feedbackId,
        'isAnonymous' => $isAnonymous
    ]);

} catch (Exception $e) {
    // Close connection on error
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    
    if (ob_get_level()) ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
?>