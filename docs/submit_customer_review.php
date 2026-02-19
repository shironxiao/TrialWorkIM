<?php
/**
 * Submit Customer Review - FINAL WORKING VERSION
 * Copy this ENTIRE file to submit_customer_review.php
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

header('Content-Type: application/json; charset=utf-8');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (ob_get_level()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Error: $errstr in " . basename($errfile) . " line $errline"]);
    exit;
});

set_exception_handler(function($e) {
    if (ob_get_level()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    exit;
});

// Database connection
  require_once(__DIR__ . '/api/config/db_config.php');



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

    // Validate
    if (empty($data['customerId'])) {
        throw new Exception('Customer ID is required');
    }

    if (empty($data['overallRating']) || $data['overallRating'] < 1 || $data['overallRating'] > 5) {
        throw new Exception('Overall rating (1-5) is required');
    }

    // Verify customer
    $customerId = intval($data['customerId']);
    
    $stmt = $conn->prepare("SELECT FirstName FROM customers WHERE CustomerID = ? AND AccountStatus = 'Active'");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Invalid customer account');
    }

    $customer = $result->fetch_assoc();
    $stmt->close();

    // Prepare all values as SEPARATE variables
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
    $generalComment = isset($data['generalComment']) && trim($data['generalComment']) !== '' ? trim($data['generalComment']) : NULL;

    // SQL - 13 placeholders
    $sql = "INSERT INTO customer_reviews (
                CustomerID, OverallRating, 
                FoodTasteRating, PortionSizeRating, CustomerServiceRating, 
                AmbienceRating, CleanlinessRating,
                FoodTasteComment, PortionSizeComment, CustomerServiceComment,
                AmbienceComment, CleanlinessComment, GeneralComment,
                Status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    // BIND - 13 parameters with 13 type characters
    // i d i i i i i s s s s s s
    $stmt->bind_param(
        "idiiiiissssss",
        $customerId,
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
        $generalComment
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $reviewId = $stmt->insert_id;
    $stmt->close();

    // Success response
    if (ob_get_level()) ob_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your review has been submitted and is pending approval.',
        'reviewId' => $reviewId
    ]);

} catch (Exception $e) {
    if (ob_get_level()) ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
ob_end_flush();
?>