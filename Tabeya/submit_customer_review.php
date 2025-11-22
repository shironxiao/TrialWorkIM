<?php
/**
 * Submit Customer Review API
 * Handles review submissions from logged-in customers
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tabeya_system";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    $conn->set_charset("utf8mb4");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }

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
        throw new Exception('Valid overall rating (1-5) is required');
    }

    // Verify customer exists
    $checkSql = "SELECT CustomerID, FirstName FROM customers WHERE CustomerID = ? AND AccountStatus = 'Active'";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $data['customerId']);
    $checkStmt->execute();
    $customerResult = $checkStmt->get_result();

    if ($customerResult->num_rows === 0) {
        throw new Exception('Invalid customer account');
    }

    $customer = $customerResult->fetch_assoc();
    $checkStmt->close();

    // Sanitize and prepare data
    $customerId = intval($data['customerId']);
    $overallRating = floatval($data['overallRating']);
    $foodRating = !empty($data['foodRating']) ? intval($data['foodRating']) : null;
    $portionRating = !empty($data['portionRating']) ? intval($data['portionRating']) : null;
    $serviceRating = !empty($data['serviceRating']) ? intval($data['serviceRating']) : null;
    $ambienceRating = !empty($data['ambienceRating']) ? intval($data['ambienceRating']) : null;
    $cleanlinessRating = !empty($data['cleanlinessRating']) ? intval($data['cleanlinessRating']) : null;

    $foodComment = !empty($data['foodComment']) ? trim($data['foodComment']) : null;
    $portionComment = !empty($data['portionComment']) ? trim($data['portionComment']) : null;
    $serviceComment = !empty($data['serviceComment']) ? trim($data['serviceComment']) : null;
    $ambienceComment = !empty($data['ambienceComment']) ? trim($data['ambienceComment']) : null;
    $cleanlinessComment = !empty($data['cleanlinessComment']) ? trim($data['cleanlinessComment']) : null;
    $generalComment = !empty($data['generalComment']) ? trim($data['generalComment']) : null;

    // Insert review
    $sql = "INSERT INTO customer_reviews (
                CustomerID, OverallRating, 
                FoodTasteRating, PortionSizeRating, CustomerServiceRating, 
                AmbienceRating, CleanlinessRating,
                FoodTasteComment, PortionSizeComment, CustomerServiceComment,
                AmbienceComment, CleanlinessComment, GeneralComment,
                Status, CreatedDate
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param(
        "idiiiissssss",
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

    // Log the review submission
    $logSql = "INSERT INTO customer_logs (CustomerID, TransactionType, Details) 
               VALUES (?, 'REVIEW_SUBMITTED', ?)";
    $logStmt = $conn->prepare($logSql);
    $logDetails = "Review #$reviewId submitted with rating: $overallRating";
    $logStmt->bind_param("is", $customerId, $logDetails);
    $logStmt->execute();
    $logStmt->close();

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully! It will be visible after approval.',
        'reviewId' => $reviewId
    ]);

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}

ob_end_flush();
?>