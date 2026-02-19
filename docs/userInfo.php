<?php
/**
 * USER INFO / INITIAL RESERVATION
 * Handles the initial catering reservation form submission
 */

// START OUTPUT BUFFERING IMMEDIATELY
ob_start();

// Configure error handling - NO OUTPUT BEFORE JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Database connection
 require_once(__DIR__ . '/api/config/db_config.php');

// Set JSON header FIRST
header('Content-Type: application/json; charset=utf-8');

try {
    // Verify POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Only POST requests allowed"]);
        ob_end_flush();
        exit;
    }

    // Connect to database
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database connection failed"]);
        ob_end_flush();
        exit;
    }

    $conn->set_charset("utf8mb4");

    // ============================================================
    // READ FORM DATA
    // ============================================================

    $full_name = isset($_POST['full-name']) ? trim($_POST['full-name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $guests = isset($_POST['guests']) ? intval($_POST['guests']) : 0;
    $event_date = isset($_POST['date']) ? $_POST['date'] : '';
    $event_time = isset($_POST['time']) ? $_POST['time'] : '';
    $event_type = isset($_POST['event-type']) ? $_POST['event-type'] : '';
    $contact_number = isset($_POST['phone']) ? trim($_POST['phone']) : '';

    // ============================================================
    // VALIDATE FORM DATA
    // ============================================================

    $errors = array();

    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }

    if ($guests < 1) {
        $errors[] = "Number of guests must be at least 1";
    }

    if (empty($event_date)) {
        $errors[] = "Event date is required";
    }

    if (empty($event_time)) {
        $errors[] = "Event time is required";
    }

    if (empty($event_type)) {
        $errors[] = "Event type is required";
    }

    if (empty($contact_number) || !preg_match('/^09\d{9}$/', $contact_number)) {
        $errors[] = "Valid contact number is required";
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(array(
            "status" => "error",
            "message" => "Validation failed",
            "errors" => $errors
        ));
        $conn->close();
        ob_end_flush();
        exit;
    }

    // ============================================================
    // GET CUSTOMER ID FROM EMAIL
    // ============================================================

    $customer_sql = "SELECT CustomerID FROM customers WHERE Email = ? AND AccountStatus = 'Active'";
    $customer_stmt = $conn->prepare($customer_sql);

    if (!$customer_stmt) {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Database prepare error"));
        $conn->close();
        ob_end_flush();
        exit;
    }

    $customer_stmt->bind_param("s", $email);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();

    if ($customer_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(array(
            "status" => "error",
            "message" => "User account not found. Please make sure you are logged in."
        ));
        $customer_stmt->close();
        $conn->close();
        ob_end_flush();
        exit;
    }

    $customer = $customer_result->fetch_assoc();
    $customer_id = intval($customer['CustomerID']);
    $customer_stmt->close();

    // ============================================================
    // START TRANSACTION
    // ============================================================

    $conn->begin_transaction();

    // ============================================================
    // INSERT RESERVATION RECORD
    // ============================================================

    $reservation_sql = "INSERT INTO reservations 
                        (CustomerID, ReservationType, EventType, EventDate, EventTime, 
                         NumberOfGuests, ServiceType, ContactNumber, ReservationStatus) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $reservation_stmt = $conn->prepare($reservation_sql);

    if (!$reservation_stmt) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Database error"));
        $conn->close();
        ob_end_flush();
        exit;
    }

    $reservation_type = 'Online';
    $service_type = 'Catering Only';
    $reservation_status = 'Pending';

    $reservation_stmt->bind_param(
        "issssisss",
        $customer_id,
        $reservation_type,
        $event_type,
        $event_date,
        $event_time,
        $guests,
        $service_type,
        $contact_number,
        $reservation_status
    );

    if (!$reservation_stmt->execute()) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Failed to create reservation"));
        $reservation_stmt->close();
        $conn->close();
        ob_end_flush();
        exit;
    }

    $reservation_id = $conn->insert_id;
    $reservation_stmt->close();

    // ============================================================
    // INSERT PLACEHOLDER PAYMENT RECORD
    // ============================================================

    $payment_sql = "INSERT INTO reservation_payments 
                    (ReservationID, PaymentMethod, PaymentStatus, AmountPaid, PaymentSource) 
                    VALUES (?, ?, ?, ?, ?)";

    $payment_stmt = $conn->prepare($payment_sql);

    if (!$payment_stmt) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Database error"));
        $conn->close();
        ob_end_flush();
        exit;
    }

    $payment_method = 'COD';
    $payment_status = 'Pending';
    $amount_paid = 0.00;
    $payment_source = 'Website';

    $payment_stmt->bind_param(
        "issds",
        $reservation_id,
        $payment_method,
        $payment_status,
        $amount_paid,
        $payment_source
    );

    if (!$payment_stmt->execute()) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Failed to create payment record"));
        $payment_stmt->close();
        $conn->close();
        ob_end_flush();
        exit;
    }

    $payment_stmt->close();

    // ============================================================
    // LOG TRANSACTION
    // ============================================================

    $log_sql = "INSERT INTO customer_logs 
                (CustomerID, TransactionType, Details) 
                VALUES (?, ?, ?)";

    $log_stmt = $conn->prepare($log_sql);

    if ($log_stmt) {
        $transaction_type = 'RESERVATION_INITIATED';
        $details = "Catering reservation initiated for " . $event_date;

        $log_stmt->bind_param(
            "iss",
            $customer_id,
            $transaction_type,
            $details
        );

        $log_stmt->execute();
        $log_stmt->close();
    }

    // ============================================================
    // COMMIT TRANSACTION
    // ============================================================

    $conn->commit();
    $conn->close();

    // ============================================================
    // SUCCESS RESPONSE
    // ============================================================

    http_response_code(201);
    echo json_encode(array(
        "status" => "success",
        "message" => "Reservation initiated successfully!",
        "reservationID" => $reservation_id,
        "eventDate" => $event_date,
        "eventTime" => $event_time,
        "numberOfGuests" => $guests
    ));

    ob_end_flush();
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        "status" => "error",
        "message" => "An error occurred"
    ));
    ob_end_flush();
    exit;
}

ob_end_flush();
?>